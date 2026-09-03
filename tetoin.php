<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='teto.php';</script>";
    exit;
}

// Ambil data pekerja
$q = $conn->prepare("SELECT p.nik, p.nama, p.negara, l.nama_cabang FROM pendaftaran p LEFT JOIN location l ON p.cabang = l.idl WHERE p.nik = ? LIMIT 1");
$q->bind_param("s", $nik);
$q->execute();
$pekerja = $q->get_result()->fetch_assoc();
$q->close();

if (!$pekerja) {
    echo "<script>alert('Data TKI tidak ditemukan'); window.location.href='teto.php';</script>";
    exit;
}

$upload_dir = __DIR__ . '/pekerja/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

function upload_file($field, $prefix, $dir) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== 0) {
        return null;
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) die("Format file tidak valid!");
    $name = uniqid($prefix . "_") . "." . $ext;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . "/" . $name)) {
        die("Gagal upload file.");
    }
    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_teto   = $_POST['tanggal_teto'] ?? date('Y-m-d');
    $nama_dokumen   = strtoupper(trim($_POST['nama_dokumen'] ?? ''));
    $nomor_dokumen  = strtoupper(trim($_POST['nomor_dokumen'] ?? ''));
    $edit           = 'clouse';

    if (empty($nama_dokumen)) {
        die("Nama dokumen TETO wajib diisi.");
    }

    $file_dokumen = upload_file('file_dokumen', 'teto', $upload_dir);

    $stmt = $conn->prepare("
        INSERT INTO teto (nik, nama_dokumen, nomor_dokumen, file_dokumen, tanggal_teto, edit)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $nik, $nama_dokumen, $nomor_dokumen, $file_dokumen, $tanggal_teto, $edit);

    if ($stmt->execute()) {
        $stmt->close();

        // Update tanggal_teto di pendaftaran
        $up = $conn->prepare("UPDATE pendaftaran SET tanggal_teto=? WHERE nik=?");
        $up->bind_param("ss", $tanggal_teto, $nik);
        $up->execute();
        $up->close();

        echo "<script>
            alert('Dokumen TETO berhasil disimpan!');
            window.location.href='teto.php';
        </script>";
        exit;
    } else {
        echo "<script>alert('Gagal menyimpan TETO: " . $stmt->error . "');</script>";
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Dokumen TETO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container { max-width: 600px; background: #fff; padding: 20px; margin: auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="date"], select, input[type="file"] { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        input[type="text"] { text-transform: uppercase; }
        input[type="submit"] { background: #28a745; color: white; font-weight: bold; border: none; margin-top: 20px; cursor: pointer; padding: 10px; width: 100%; border-radius: 4px; }
        input[type="submit"]:hover { background: #218838; }
        .back-btn { display: inline-block; margin-bottom: 15px; color: #3498db; text-decoration: none; font-weight: bold; }
        .info-box { background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <a href="teto.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar TETO</a>
    <h2>Input Dokumen TETO</h2>

    <div class="info-box">
        <strong>NIK:</strong> <?= htmlspecialchars($pekerja['nik']) ?><br>
        <strong>Nama:</strong> <?= htmlspecialchars($pekerja['nama']) ?><br>
        <strong>Negara Tujuan:</strong> <?= htmlspecialchars($pekerja['negara']) ?><br>
        <strong>Cabang:</strong> <?= htmlspecialchars($pekerja['nama_cabang'] ?? '-') ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <label>Tanggal TETO</label>
        <input type="date" name="tanggal_teto" value="<?= date('Y-m-d') ?>" required>

        <label>Nama Dokumen TETO</label>
        <input type="text" name="nama_dokumen" placeholder="Contoh: VISA TETO / ENDORSEMENT TETO" required>

        <label>Nomor Dokumen TETO</label>
        <input type="text" name="nomor_dokumen" placeholder="Nomor Dokumen/Sertifikat TETO">

        <label>Upload Dokumen TETO (PDF / JPG / PNG)</label>
        <input type="file" name="file_dokumen" accept=".jpg,.jpeg,.png,.pdf" required>

        <input type="submit" value="Simpan Dokumen TETO">
    </form>
</div>
</body>
</html>
<?php $conn->close(); ?>
