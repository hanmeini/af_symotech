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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumkesehatan2.php';</script>";
    exit;
}

/* ==============================
   CEK BIAYA NIKBY (KESEHATAN FULL)
   kodeby = 8
============================== */
$q = $conn->prepare("
    SELECT id, kodeby, nom, tanggal 
    FROM nikby 
    WHERE nik=? AND klaim='0' AND kodeby='8'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$res = $q->get_result();

if ($res->num_rows == 0) {
    echo "<script>
        alert('Biaya kesehatan belum diinput atau sudah diklaim');
        window.location='home.php';
    </script>";
    exit;
}
$dataNikby = $res->fetch_assoc();
$q->close();

/* ==============================
   COA & NAMA BIAYA (KESEHATAN)
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

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* ==============================
   FUNGSI UPLOAD FILE (TETAP)
============================== */
function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        die("File $file_key wajib diunggah.");
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file $file_key tidak valid.");
    }
    $filename = uniqid($prefix . '_', true) . '.' . $ext;
    $path = rtrim($upload_dir,'/') . '/' . $filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal mengunggah file $file_key.");
    }
    return $filename;
}

/* =====================================================
   FORM KESEHATAN FULL
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_kesehatan2 = $_POST['tanggal_kesehatan2'] ?? '';
    $provider_serkes    = trim($_POST['provider_serkes'] ?? '');
    $no_serkes          = trim($_POST['no_serkes'] ?? '');
    $tgl_serkes         = $_POST['tgl_serkes'] ?? '';
    $tglend_serkes      = $_POST['tglend_serkes'] ?? '';
    $no_perjanjian      = trim($_POST['no_perjanjian'] ?? '');
    $status_kes         = $_POST['status_kes'] ?? '';
    $edit               = 'clouse';

    // dipakai untuk update visa
    $tgl_siko = $_POST['tgl_siko'] ?? '';

    // Upload serkes
    $serkes_file = upload_file('serkes', 'serkes', $upload_dir);

    // Insert kesehatan2
    $stmt = $conn->prepare("
        INSERT INTO kesehatan2
        (nik, provider_serkes, no_serkes, serkes, tgl_serkes, tglend_serkes, no_perjanjian, status_kes, edit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssss",
        $nik,
        $provider_serkes,
        $no_serkes,
        $serkes_file,
        $tgl_serkes,
        $tglend_serkes,
        $no_perjanjian,
        $status_kes,
        $edit
    );

    if ($stmt->execute()) {

        // Update pendaftaran
        $up = $conn->prepare("
            UPDATE pendaftaran 
            SET tanggal_kesehatan2=? 
            WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_kesehatan2, $nik);
        $up->execute();
        $up->close();

        // Update tglvisa jika bukan TAIWAN
        $cek = $conn->prepare("SELECT negara FROM pendaftaran WHERE nik=?");
        $cek->bind_param("s", $nik);
        $cek->execute();
        $cek->bind_result($negara);
        if ($cek->fetch()) {
            if (strtoupper($negara) !== 'TAIWAN') {
                $up2 = $conn->prepare("
                    UPDATE pendaftaran 
                    SET tglvisa=? 
                    WHERE nik=?
                ");
                $up2->bind_param("ss", $tgl_siko, $nik);
                $up2->execute();
                $up2->close();
            }
        }
        $cek->close();

        // CEK UNVIT
        if ($status_kes === 'unvit') {
            $upd_batal = $conn->prepare("UPDATE pendaftaran SET status='batal' WHERE nik=?");
            $upd_batal->bind_param("s", $nik);
            $upd_batal->execute();
            $upd_batal->close();
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
}
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Kesehatan 2</title>
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
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    </div>
<div class="container">
    <h2>Formulir Kesehatan 2  (FULL)</h2>
   <form method="post" enctype="multipart/form-data">
        <label>Tanggal Kesehatan</label>
        <input type="date" name="tanggal_kesehatan2" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>Provider Serkes</label>
        <input type="text" name="provider_serkes" required>

        <label>No Serkes</label>
        <input type="text" name="no_serkes" required>
        
          <label>Serkes (jpg/png/jpeg/gif/pdf)</label>
        <input type="file" name="serkes" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>Tanggal Serkes</label>
        <input type="date" name="tgl_serkes" required>

        <label>Tanggal Berakhir</label>
        <input type="date" name="tglend_serkes" required>

        <label>No Perjanjian (Opsional)</label>
        <input type="text" name="no_perjanjian">

        <label>Status Kesehatan</label>
        <select name="status_kes" required>
            <option value="">-- Pilih Status --</option>
            <option value="vit">Vit</option>
            <option value="pending">Pending</option>
            <option value="unvit">Unvit</option>
        </select>

        <input type="submit" value="Simpan">
    </form>
</div>
</body>
</html>
<?php $conn->close(); ?>
