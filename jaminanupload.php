<?php
session_start();
require_once "config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Cek login
if (!isset($_SESSION['username'])) {
    die("User belum login");
}

// --- Cek nik
$nik = $_GET['nik'] ?? "";
if (!$nik) {
    die("NIK tidak ditemukan");
}

// --- KONEKSI
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// --- Ambil data jaminan terakhir
$q = $conn->query("SELECT * FROM jaminan WHERE nik='$nik' ORDER BY id DESC LIMIT 1");
$data = $q->fetch_assoc();

if (!$data) {
    die("Data jaminan tidak ditemukan.");
}

$id = $data['id'];

// Tentukan TT berikutnya
$next_tt = "";
if ($data['tt1'] == "") $next_tt = "tt1";
else if ($data['tt2'] == "") $next_tt = "tt2";
else if ($data['tt3'] == "") $next_tt = "tt3";
else if ($data['tt4'] == "") $next_tt = "tt4";

// Jika semua TT sudah penuh
if ($next_tt == "") {
    $done_message = "<h2 style='color:green;text-align:center'>Semua tanda terima sudah diupload.</h2>";
} else {
    $done_message = "";
}

// --- Proses Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $next_tt != "") {

    $allowed = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($_FILES['file_tt']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("<script>alert('Format tidak diizinkan'); history.back();</script>");
    }

    // Direktori upload
    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";

    $filename = uniqid($next_tt . "_") . "." . $ext;

    if (!move_uploaded_file($_FILES['file_tt']['tmp_name'], $upload_dir . $filename)) {
        die("<script>alert('Upload gagal'); history.back();</script>");
    }

    // Simpan ke DB sesuai urutan TT
    $conn->query("UPDATE jaminan SET $next_tt='$filename' WHERE id='$id'");

    echo "<script>
            alert('Upload $next_tt berhasil!');
            window.location='jaminanupload.php?nik=$nik';
          </script>";
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Upload Tanda Terima Jaminan</title>

<style>
body {
    font-family: Arial;
    background: #f4f4f4;
    padding: 20px;
}
.container {
    width: 500px;
    margin: auto;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
h2 { text-align:center; margin-bottom:20px; }
.box {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 8px;
    background:#fafafa;
    margin-top:10px;
}
button {
    padding:12px 20px;
    font-size:16px;
    border:none;
    background:#4CAF50;
    color:white;
    border-radius:8px;
    cursor:pointer;
}
button:hover { background:#388E3C; }

input[type=file] { padding:8px; width:100%; }
.info { font-size:13px; color:#555; margin-bottom:15px; }

.completed {
    padding:10px;
    background:#d4edda;
    border:1px solid #c3e6cb;
    color:#155724;
    margin-top:20px;
    border-radius:8px;
    text-align:center;
}
</style>
</head>
<body>

<div class="container">

<h2>Upload Tanda Terima</h2>

<div class="info">
    <b>NIK:</b> <?= htmlspecialchars($nik) ?><br>
    <b>ID Jaminan:</b> <?= $id ?><br><br>
</div>

<?php if ($next_tt == ""): ?>

    <div class="completed">
        Semua TT sudah lengkap.<br><br>
        ✔ TT1<br>
        ✔ TT2<br>
        ✔ TT3<br>
        ✔ TT4
    </div>

<?php else: ?>

    <div class="box">
        <b>TT Berikutnya:</b> <?= strtoupper($next_tt) ?><br><br>

        <form method="POST" enctype="multipart/form-data">
            <label>Upload <?= strtoupper($next_tt) ?>:</label><br>
            <input type="file" name="file_tt" required><br><br>
            <button type="submit">UPLOAD</button>
        </form>
    </div>

<?php endif; ?>

<?= $done_message ?>

</div>

</body>
</html>
