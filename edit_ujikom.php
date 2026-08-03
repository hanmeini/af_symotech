<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'), getenv('DB_USER'),
    getenv('DB_PASS'), getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) die("NIK tidak ditemukan");

// ✅ Ambil data ujikom terakhir
$result = $conn->query("SELECT * FROM ujikom WHERE nik='$nik' ORDER BY id DESC LIMIT 1");
if ($result->num_rows === 0) die("Data ujikom tidak ditemukan.");

$data = $result->fetch_assoc();

// Direktori file
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

function upload_file_optional($key, $prefix, $upload_dir, $old_file)
{
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === 4) {
        return $old_file;
    }

    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array($ext, $allowed)) {
        die("Format file tidak valid.");
    }

    $name = uniqid($prefix . "_", true) . "." . $ext;
    $path = rtrim($upload_dir, '/') . '/' . $name;

    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
        die("Gagal upload file.");
    }

    return $name;
}

/* =====================
   ✅ PROSES UPDATE
   ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

   
    $tgl_ujikom      = $_POST['tgl_ujikom'];
    $tglend_ujikom   = $_POST['tglend_ujikom'];
    
    $edit            = "clouse";

    // ✅ File baru atau tetap file lama
    $ujikom_file = upload_file_optional("ujikom", "ujikom", $upload_dir, $data['ujikom']);

    // ✅ UPDATE ujikom
    $stmt = $conn->prepare("
        UPDATE ujikom SET
            provider_ujikom=?, no_ujikom=?, ujikom=?, 
            tgl_ujikom=?, tglend_ujikom=?, no_perjanjian=?, edit=? 
        WHERE nik=? ORDER BY id DESC LIMIT 1
    ");

    $stmt->bind_param(
        "sssss",
        $ujikom_file,
        $tgl_ujikom, $tglend_ujikom,  $edit,
        $nik
    );

    if ($stmt->execute()) {

        // ✅ Update tanggal_ujikom di pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_ujikom=? WHERE nik=?");
        $upd2->bind_param("ss", $tgl_ujikom, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data ujikom berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
        exit;

    } else {
        echo "<script>alert('Gagal update: " . $stmt->error . "');</script>";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Ujikom</title>
<style>
body { font-family: Arial; background: #f0f0f0; padding: 20px; }
.container {
    background: #fff; padding: 20px; margin: auto; max-width: 700px;
    border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
label { margin-top: 12px; display: block; font-weight: bold; }
input { width: 100%; padding: 8px; margin-top: 5px; border:1px solid #ccc; border-radius:4px; }
input[type=submit] { background:#007bff; color:#fff; font-weight:bold; margin-top:20px; cursor:pointer; }
</style>
</head>
<body>

<div class="container">
<h2>Edit Data Ujikom</h2>

<form method="POST" enctype="multipart/form-data">



<label>File Ujikom (Upload jika ingin ganti)</label>
<input type="file" name="ujikom" accept=".jpg,.jpeg,.png,.gif,.pdf">
<p>File lama: <b><?= $data['ujikom'] ?></b></p>

<label>Tanggal Ujikom</label>
<input type="date" name="tgl_ujikom" value="<?= $data['tgl_ujikom'] ?>">

<label>Tanggal Berakhir</label>
<input type="date" name="tglend_ujikom" value="<?= $data['tglend_ujikom'] ?>">



<input type="submit" value="Update">

</form>

</div>

</body>
</html>
