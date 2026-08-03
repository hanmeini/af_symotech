<?php
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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='dapatjob.php';</script>";
    exit;
}

/* ======================
   ✅ Ambil data terakhir
   ====================== */
$stmt = $conn->prepare("
    SELECT id, tanggal_dapat_job, upload_job, jabatan, nama_mitra, edit
    FROM dapat_job 
    WHERE nik = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data dapat job tidak ditemukan');window.location.href='dapatjob.php';</script>";
    exit;
}

$stmt->bind_result($id, $tanggal_dapat_job, $upload_job, $jabatan, $nama_mitra, $edit);
$stmt->fetch();
$stmt->close();

/* ======================
   ✅ Upload Function
   ====================== */
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        return null; // ✅ Tidak wajib upload
    }

    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format file tidak valid. Harus jpg/jpeg/png/gif/pdf.");
    }

    $filename = uniqid($prefix . '_', true) . "." . $ext;
    $path = rtrim($upload_dir, "/") . "/" . $filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal upload file.");
    }
    return $filename;
}

/* ======================
   ✅ Proses update
   ====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_new = $_POST['tanggal_dapat_job'] ?? '';
    $jabatan_new = trim($_POST['jabatan'] ?? '');
    $mitra_new   = trim($_POST['nama_mitra'] ?? '');
    $edit        = "clouse";

    $file_new = upload_file('upload_job', 'job', $upload_dir);

    // Jika upload baru kosong → gunakan file lama
    if (!$file_new) {
        $file_new = $upload_job;
    }

    // UPDATE
    $up = $conn->prepare("
        UPDATE dapat_job SET
            tanggal_dapat_job = ?, 
            upload_job = ?, 
            jabatan = ?, 
            nama_mitra = ?, 
            edit = ?
        WHERE id = ?
    ");
    $up->bind_param("sssssi", $tanggal_new, $file_new, $jabatan_new, $mitra_new, $edit, $id);

    if ($up->execute()) {

        // Update tanggal di pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_saat_job=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_new, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data dapat job berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>alert('Gagal update: ".$up->error."');</script>";
    }

    $up->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Edit Dapat Job</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body { font-family: Arial; background: #f0f0f0; padding: 20px; }
    .container {
        max-width: 700px; background: #fff; padding: 20px;
        margin: auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 { text-align: center; margin-bottom: 20px; }
    label { margin-top: 15px; font-weight: bold; display: block; }
    input { width: 100%; padding: 8px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
    input[type="submit"] {
        margin-top: 20px; padding: 10px; background: #007bff;
        color: white; border: none; cursor: pointer; font-size: 16px;
    }
    input[type="submit"]:hover { background: #0056b3; }
    a.btn-view { padding: 8px 12px; background: green; color: white; border-radius: 5px; text-decoration: none; }
</style>
</head>
<body>

<div class="container">

    <h2>Edit Dapat Job</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Dapat Job</label>
        <input type="date" name="tanggal_dapat_job" required value="<?= htmlspecialchars($tanggal_dapat_job) ?>">

        <label>Jabatan</label>
        <input type="text" name="jabatan" required value="<?= htmlspecialchars($jabatan) ?>">

        <label>Nama Mitra</label>
        <input type="text" name="nama_mitra" value="<?= htmlspecialchars($nama_mitra) ?>">

        <label>Upload File Job (jpg/png/pdf)</label>
        <input type="file" name="upload_job" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <?php if ($upload_job): ?>
            <p>
                File Saat Ini:
                <a class="btn-view" href="/pekerja/<?= htmlspecialchars($upload_job) ?>" target="_blank">Lihat File</a>
            </p>
        <?php endif; ?>

        <input type="submit" value="Perbarui Data">
    </form>

</div>

</body>
</html>
