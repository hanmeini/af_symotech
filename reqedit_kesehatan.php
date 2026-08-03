<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

$nik = trim($_GET['nik'] ?? '');
$error = '';
$nama = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alasan = trim($_POST['alasan'] ?? '');

    if ($nik && $alasan !== '') {
        // Langsung update berdasarkan NIK, ambil data terakhir (jika ada)
        $stmtUpdate = $conn->prepare("
            UPDATE kesehatan 
            SET alesan = ?, minta = 1 
            WHERE nik = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtUpdate->bind_param("ss", $alasan, $nik);
        if ($stmtUpdate->execute()) {
            $stmtUpdate->close();
            echo "<script>alert('Permintaan edit berhasil dikirim.'); window.location.href = 'belumkesehatan.php';</script>";
            exit;
        } else {
            $error = "Gagal mengirim permintaan edit.";
        }
    } else {
        $error = "Alasan wajib diisi.";
    }
} else {
    // Tampilkan nama peserta
    if ($nik) {
        $stmt = $conn->prepare("SELECT nama FROM pendaftaran WHERE nik = ?");
        $stmt->bind_param("s", $nik);
        $stmt->execute();
        $stmt->bind_result($nama);
        $stmt->fetch();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Request Edit Setelah Penempatan</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        label { display: block; margin-bottom: 8px; }
        textarea { width: 100%; height: 100px; padding: 10px; margin-bottom: 15px; }
        input[type="submit"] { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 10px; }
        .info { margin-bottom: 10px; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <h2>Request Edit Data Setelah Penempatan</h2>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($nama): ?>
        <div class="info">NIK: <?= htmlspecialchars($nik) ?> - Nama: <?= htmlspecialchars($nama) ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="alasan">Alasan Perubahan:</label>
        <textarea name="alasan" id="alasan" required></textarea>
        <input type="submit" value="Kirim Permintaan Edit">
    </form>
</div>
</body>
</html>
