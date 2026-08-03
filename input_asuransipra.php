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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumasuransipra.php';</script>";
    exit;
}

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* ==============================
   CEK BIAYA NIKBY (ASURANSI PRA)
   kodeby = 3
============================== */
$q = $conn->prepare("
    SELECT id, kodeby, nom, tanggal
    FROM nikby
    WHERE nik=? AND klaim='0' AND kodeby='3'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$q->bind_result($nikby_id, $kodeby, $nilaiNom, $tanggalTrans);

if (!$q->fetch()) {
    echo "<script>
        alert('Biaya asuransi pra belum diinput atau sudah diklaim');
        window.location='home.php';
    </script>";
    $q->close();
    exit;
}
$q->close();

/* ==============================
   COA & NAMA BIAYA (ASURANSI PRA)
============================== */
$q2 = $conn->prepare("
    SELECT COA, namaby
    FROM jenisbiaya
    WHERE id=?
    LIMIT 1
");
$q2->bind_param("i", $kodeby);
$q2->execute();
$q2->bind_result($coa_biaya, $nama_biaya);
$q2->fetch();
$q2->close();

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
$qCab->bind_result($location);
$qCab->fetch();
$location = $location ?: "-";
$qCab->close();

/* =======================
   FUNGSI UPLOAD (TETAP)
======================= */
function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        die("File $file_key wajib diunggah.");
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file $file_key tidak valid.");
    }
    $filename = uniqid($prefix.'_') . '.' . $ext;
    $path = rtrim($upload_dir,'/').'/'.$filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal upload $file_key");
    }
    return $filename;
}

/* =====================================================
   FORM ASURANSI PRA
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_asuransipra = $_POST['tanggal_asuransipra'] ?? '';
    $provider_asuransipra = trim($_POST['provider_asuransipra'] ?? '');
    $no_asuransipra = trim($_POST['no_asuransipra'] ?? '');
    $tgl_asuransipra = $_POST['tgl_asuransipra'] ?? '';
    $tglend_asuransipra = $_POST['tglend_asuransipra'] ?? '';
    $no_perjanjian = trim($_POST['no_perjanjian'] ?? '');
    $edit = 'clouse';

    // Upload file
    $asuransipra_file = upload_file('asuransipra', 'asuransipra', $upload_dir);

    // INSERT ASURANSI PRA
    $stmt = $conn->prepare("
        INSERT INTO asuransipra
        (nik, provider_asuransipra, no_asuransipra, asuransipra,
         tgl_asuransipra, tglend_asuransipra, no_perjanjian, edit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "ssssssss",
        $nik,
        $provider_asuransipra,
        $no_asuransipra,
        $asuransipra_file,
        $tgl_asuransipra,
        $tglend_asuransipra,
        $no_perjanjian,
        $edit
    );

    if ($stmt->execute()) {

        // Update pendaftaran
        $up = $conn->prepare("
            UPDATE pendaftaran
            SET tanggal_asuransipra=?
            WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_asuransipra, $nik);
        $up->execute();
        $up->close();

        echo "<script>
            alert('Data asuransi pra berhasil disimpan');
            window.location='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
        exit;

    } else {
        echo "<script>alert('Gagal menyimpan data: {$stmt->error}');</script>";
    }

    $stmt->close();
}
// ==============================
// KODE TRANSAKSI JURNAL
// ==============================
$qJM = $conn->query("SELECT MAX(id) AS max_nomor FROM JM");
$rowJM = $qJM->fetch_assoc();
$max_nomor = $rowJM['max_nomor'] ?? 0;

$nomor = intval($max_nomor) + 1;
$nomor_formatted = sprintf('%04d', $nomor);
$kode_transaksi = "JM" . date('Y') . $nomor_formatted;

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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data asuransipra</title>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #28a745;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #218838;
        }
         .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>

<form method="post" enctype="multipart/form-data">
    <label>Tanggal Asuransi Pra</label>
    <input type="date" name="tanggal_asuransipra" value="<?= date('Y-m-d') ?>" required>

    <label>Provider Asuransi</label>
    <input type="text" name="provider_asuransipra" required>

    <label>No Asuransi</label>
    <input type="text" name="no_asuransipra" required>

    <label>File Asuransi</label>
    <input type="file" name="asuransipra" required>

    <label>Tanggal Mulai</label>
    <input type="date" name="tgl_asuransipra" required>

    <label>Tanggal Berakhir</label>
    <input type="date" name="tglend_asuransipra" required>

    <label>No Perjanjian (Opsional)</label>
    <input type="text" name="no_perjanjian">

    <button type="submit">Simpan</button>
</form>

</body>
</html>

<?php $conn->close(); ?>
