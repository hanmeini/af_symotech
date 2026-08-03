<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

/* =========================
   ✅ KONEKSI DATABASE
   ========================= */
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

/* =========================
   ✅ AMBIL NIK
   ========================= */
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='saatjob.php';</script>";
    exit;
}

/* =========================
   ✅ KONFIGURASI UPLOAD
   ========================= */
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* ✅ Fungsi Upload File */
function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        die("File $file_key wajib diunggah.");
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format file $file_key tidak valid. Harus jpg/jpeg/png/gif/pdf.");
    }

    $filename = uniqid($prefix . '_', true) . '.' . $ext;
    $path = rtrim($upload_dir, '/') . '/' . $filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal mengunggah file $file_key.");
    }

    return $filename;
}

/* =========================
   ✅ PROSES SIMPAN DATA
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_dapat_job = $_POST['tanggal_dapat_job'];
    $jabatan           = trim($_POST['jabatan']);
    $nama_mitra        = trim($_POST['nama_mitra']);
    $edit              = "clouse"; // ✅ wajib dimasukkan ke DB

    // ✅ Upload file job
    $upload_job = upload_file('upload_job', 'job', $upload_dir);

    /* ✅ INSERT sesuai tabel + kolom edit */
    $stmt = $conn->prepare("
        INSERT INTO dapat_job 
        (nik, tanggal_dapat_job, upload_job, jabatan, nama_mitra, edit)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssssss",
        $nik, $tanggal_dapat_job, $upload_job, $jabatan, $nama_mitra, $edit
    );

    if ($stmt->execute()) {

        // ✅ UPDATE tanggal
        $update = $conn->prepare("UPDATE pendaftaran SET tanggal_saat_job=? WHERE nik=?");
        $update->bind_param("ss", $tanggal_dapat_job, $nik);
        $update->execute();
        $update->close();

        echo "<script>
            alert('Data Dapat Job berhasil disimpan');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";

    } else {
        echo "<script>alert('Error: " . $stmt->error . "'); history.back();</script>";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Dapat Job</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 700px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; margin-bottom: 20px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 4px; border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #007bff; color: white;
            border: none; font-weight: bold;
            margin-top: 20px; cursor: pointer;
            padding: 10px;
        }
        input[type="submit"]:hover {
            background: #0056b3;
        }
        .top-btn {
            font-size: 22px; color: maroon;
            text-decoration: none; margin: 10px;
        }
    </style>
</head>
<body>

<a href="home.php" class="top-btn"><i class="fas fa-home"></i></a>

<div class="container">

    <h2>FORM INPUT - DAPAT JOB</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Dapat Job</label>
        <input type="date" name="tanggal_dapat_job" value="<?= date('Y-m-d') ?>" required>

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>Upload File Job (jpg/png/pdf)</label>
        <input type="file" name="upload_job" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>Jabatan</label>
        <input type="text" name="jabatan" required>

        <label>Nama Mitra/AGENCY</label>
        <input type="text" name="nama_mitra">

        <input type="submit" value="Simpan Data">

    </form>

</div>

</body>
</html>
<?php $conn->close(); ?>
