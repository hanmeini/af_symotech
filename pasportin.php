<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='pasportidlist.php';</script>";
    exit;
}

/* ==============================
   CEK BIAYA NIKBY (PASPORT)
   kodeby = 4
============================== */
$q = $conn->prepare("
    SELECT id, kodeby, nom, tanggal 
    FROM nikby 
    WHERE nik=? AND klaim='0' AND kodeby='4'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$res = $q->get_result();

if ($res->num_rows == 0) {
    echo "<script>
        alert('Biaya pasport belum diinput atau sudah diklaim');
        window.location='home.php';
    </script>";
    exit;
}
$dataNikby = $res->fetch_assoc();
$q->close();

/* ==============================
   COA & NAMA BIAYA (PASPORT)
============================== */
$q2 = $conn->prepare("
    SELECT COA, namaby 
    FROM jenisbiaya 
    WHERE id=? 
    LIMIT 1
");
$q2->bind_param("i", $dataNikby['kodeby']);
$q2->execute();
$jb = $q2->get_result()->fetch_assoc();
$q2->close();

$coa_biaya  = $jb['COA'];
$nama_biaya = $jb['namaby'];

/* ==============================
   AMBIL CABANG -> LOCATION
============================== */
$qCab = $conn->prepare("
    SELECT cabang 
    FROM pendaftaran 
    WHERE nik=? 
    LIMIT 1
");
$qCab->bind_param("s", $nik);
$qCab->execute();
$rCab = $qCab->get_result()->fetch_assoc();
$location = $rCab ? $rCab['cabang'] : "-";
$qCab->close();

$nilaiNom     = $dataNikby['nom'];
$tanggalTrans = $dataNikby['tanggal'];

/* ==============================
   KODE TRANSAKSI JURNAL
============================== */
$qJM = $conn->query("SELECT MAX(id) AS max_nomor FROM JM");
$rowJM = $qJM->fetch_assoc();
$nomor = intval($rowJM['max_nomor'] ?? 0) + 1;
$kode_transaksi = "JM" . date('Y') . sprintf('%04d', $nomor);

/* ==============================
   LOKASI UPLOAD FILE
============================== */
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

/* ==============================
   FUNGSI UPLOAD PASPORT (TETAP)
============================== */
function upload_pasport($file_key, $nik, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        die("File Paspor wajib diunggah.");
    }

    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format file tidak valid.");
    }

    $filename = "paspor_" . $nik . "_" . time() . "." . $ext;
    $filepath = rtrim($upload_dir,'/').'/'.$filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $filepath)) {
        die("Gagal mengunggah file paspor.");
    }

    return $filename;
}

/* =====================================================
   FORM PASPORT
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_pembuatan_pasport = $_POST['tanggal_pembuatan_pasport'] ?? '';
    $nama_paspor     = trim($_POST['nama_paspor'] ?? '');
    $no_paspor       = trim($_POST['no_paspor'] ?? '');
    $tgl_paspor      = $_POST['tgl_paspor'] ?? '';
    $tglend_paspor   = $_POST['tglend_paspor'] ?? '';
    $penerbit_paspor = trim($_POST['penerbit_paspor'] ?? '');
    $edit = 'clouse';

    // Upload file paspor
    $pasport_file = upload_pasport('pasport', $nik, $upload_dir);

    // INSERT PASPORT
    $stmt = $conn->prepare("
        INSERT INTO pembuatan_paspor
        (nik, nama_paspor, no_paspor, pasport, tgl_paspor, tglend_paspor, penerbit_paspor, edit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssssss",
        $nik,
        $nama_paspor,
        $no_paspor,
        $pasport_file,
        $tgl_paspor,
        $tglend_paspor,
        $penerbit_paspor,
        $edit
    );

    if ($stmt->execute()) {

        // Update pendaftaran
        $up = $conn->prepare("
            UPDATE pendaftaran
            SET tanggal_pembuatan_pasport=?
            WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_pembuatan_pasport, $nik);
        $up->execute();
        $up->close();

        echo "<script>
            alert('Data pasport berhasil disimpan');
            window.location='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
        exit;

    } else {
        echo "<script>alert('Gagal menyimpan pasport: {$stmt->error}');</script>";
    }

    $stmt->close();
}

// ==============================
// JURNAL - Debet
// ==============================
$ket_baris = $nama_biaya . " - " . $nik;
$ket_baris1 = $nama_biaya . " - " . $nik;

$j1 = $conn->prepare("
    INSERT INTO jurnal 
    (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
    VALUES (?, ?, ?, ?, ?, 0, NULL, ?)
");
$j1->bind_param("sssdis", 
    $kode_transaksi,
    $tanggal_kesehatan,
    $ket_baris,
    $coa_biaya,
    $nilaiNom,
    $location
);
$j1->execute();
$j1->close();
// ==============================
// JURNAL - Kredit (13102)
// ==============================
$j2 = $conn->prepare("
    INSERT INTO jurnal 
    (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
    VALUES (?, ?, ?, '13102', 0, ?, NULL, ?)
");
$j2->bind_param("sssss",
    $kode_transaksi,
    $tanggal_kesehatan,
    $ket_baris1,
    $nilaiNom,
    $location
);
$j2->execute();
$j2->close();

// ==============================
// UPDATE NIKBY → klaim
// ==============================
$up = $conn->prepare("UPDATE nikby SET klaim=? WHERE id=?");
$up->bind_param("si", $kode_transaksi, $dataNikby['id']);
$up->execute();
$up->close();


// Simpan kode transaksi di tabel JM
$sql1 = "INSERT INTO JM (jurnal) VALUES (?)";
$stmt = $conn->prepare($sql1);
$stmt->bind_param("s", $kode_transaksi);
$stmt->execute();

// Tutup statement dan koneksi
$stmt->close();


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Pembuatan Paspor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 700px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 4px; border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #17a2b8;
            color: white; font-weight: bold; margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background: #138496; }
        .icon-home, .icon-back {
            position: absolute; top: 10px; font-size: 24px; color: maroon;
        }
        .icon-home { left: 20px; }
        .icon-back { right: 20px; }
    </style>
</head>
<body>

<a href="home.php" class="icon-home"><i class="fas fa-home"></i></a>
<a href="daftaruser.php" class="icon-back"><i class="fas fa-circle-left"></i></a>

<div class="container">
    <h2>Form Pembuatan Paspor</h2>
    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Pembuatan Paspor</label>
        <input type="date" name="tanggal_pembuatan_pasport" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>Nama di Paspor</label>
        <input type="text" name="nama_paspor" required>

        <label>No Paspor</label>
        <input type="text" name="no_paspor" required>

        <label>File Paspor (jpg/png/jpeg/gif/pdf)</label>
        <input type="file" name="pasport" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>Tanggal Terbit Paspor</label>
        <input type="date" name="tgl_paspor" required>

        <label>Tanggal Berakhir Paspor</label>
        <input type="date" name="tglend_paspor" required>

        <label>Penerbit Paspor</label>
        <input type="text" name="penerbit_paspor" required>

        <input type="submit" value="Simpan">

    </form>
</div>

</body>
</html>
<?php $conn->close(); ?>
