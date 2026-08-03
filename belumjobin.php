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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumjoblist.php';</script>";
    exit;
}

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

// Fungsi upload file
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_sebelum_job = $_POST['tanggal_sebelum_job'] ?? '';
    $no_job = trim($_POST['no_job'] ?? '');
    $no_SIP2MI = trim($_POST['no_SIP2MI'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $gaji2 = trim($_POST['gaji2'] ?? '');
    $negara = trim($_POST['negara'] ?? '');
    $nama_mitra = trim($_POST['nama_mitra'] ?? '');
    $alamat_mitra = trim($_POST['alamat_mitra'] ?? '');
    $telp_mitra = trim($_POST['telp_mitra'] ?? '');
    $email_mitra = trim($_POST['email_mitra'] ?? '');
    $edit = 'clouse';

    // Upload file SIP2MI
    $SIP2MI_file = upload_file('SIP2MI', 'sip2mi', $upload_dir);

    $stmt = $conn->prepare("INSERT INTO sebelum_job 
        (nik, no_job, no_SIP2MI, SIP2MI, jabatan, gaji2, negara, nama_mitra, alamat_mitra, telp_mitra, email_mitra, edit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssss", 
        $nik, $no_job, $no_SIP2MI, $SIP2MI_file, $jabatan, $gaji2, 
        $negara, $nama_mitra, $alamat_mitra, $telp_mitra, $email_mitra, $edit
    );

    if ($stmt->execute()) {
        // Update tanggal_sebelum_job di tabel pendaftaran
        $update = $conn->prepare("UPDATE pendaftaran SET tanggal_sebelum_job = ? WHERE nik = ?");
        $update->bind_param("ss", $tanggal_sebelum_job, $nik);
        $update->execute();
        $update->close();

        echo "<script>
            alert('Data job berhasil disimpan');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>
            alert('Gagal menyimpan data');
            window.location.href='belumjoblist.php';
        </script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Sebelum Job</title>
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
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
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
    <h2>Form Sebelum Job</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Tanggal Sebelum Job</label>
        <input type="date" name="tanggal_sebelum_job" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>No Job</label>
        <input type="text" name="no_job" required>

        <label>No SIP2MI</label>
        <input type="text" name="no_SIP2MI" required>

        <label>Upload Dokumen SIP2MI (jpg/png/jpeg/gif/pdf)</label>
        <input type="file" name="SIP2MI" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>Jabatan</label>
        <input type="text" name="jabatan" required>

        <label>Gaji</label>
        <input type="text" name="gaji2" required>

        <label>Negara</label>
        <input type="text" name="negara" required>

        <label>Nama Mitra</label>
        <input type="text" name="nama_mitra">

        <label>Alamat Mitra</label>
        <input type="text" name="alamat_mitra" required>

        <label>Telp Mitra</label>
        <input type="text" name="telp_mitra">

        <label>Email Mitra</label>
        <input type="email" name="email_mitra">

        <input type="submit" value="Simpan">
    </form>
</div>
</body>
</html>
<?php $conn->close(); ?>
