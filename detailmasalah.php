<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

/* =======================
   KONEKSI DATABASE
======================= */
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

/* =======================
   VALIDASI PARAMETER
======================= */
$nik = trim($_GET['id'] ?? '');
if ($nik === '') {
    die("NIK tidak ditemukan");
}

/* =======================
   AMBIL DATA DETAIL
   - Cocokkan 16 digit NIK
   - Ambil pendaftaran TERAKHIR
======================= */
$sql = "
SELECT 
    m.id,
    m.tanggal,
    m.nik,
    p.nama,
    l.nama_cabang,
    m.masalah,
    m.progress_masalah,
    m.done_masalah
FROM masalah m
LEFT JOIN (
    SELECT p1.*
    FROM pendaftaran p1
    INNER JOIN (
        SELECT LEFT(nik,16) AS nik16, MAX(id) AS max_id
        FROM pendaftaran
        GROUP BY LEFT(nik,16)
    ) p2
    ON LEFT(p1.nik,16) = p2.nik16
    AND p1.id = p2.max_id
) p ON p.nik LIKE CONCAT(m.nik, '%')
LEFT JOIN location l ON l.idl = p.cabang
WHERE m.nik = ?
ORDER BY m.tanggal DESC
LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $nik);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Data tidak ditemukan");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Masalah</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    padding: 20px;
}
.box {
    background: #fff;
    max-width: 650px;
    margin: auto;
    padding: 20px;
    border-radius: 6px;
}
h2 {
    text-align: center;
}
.row {
    margin-bottom: 12px;
}
label {
    font-weight: bold;
}
.action {
    margin-top: 20px;
}
.action a {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    font-size: 14px;
}
.btn-back { background: #6c757d; }
.btn-edit { background: #ffc107; color: #000; }
</style>
</head>
<body>

<div class="box">
    <h2>Detail Masalah</h2>

    <div class="row"><label>NIK</label><br><?= htmlspecialchars($data['nik']) ?></div>
    <div class="row"><label>Nama</label><br><?= htmlspecialchars($data['nama'] ?? '-') ?></div>
    <div class="row"><label>Cabang</label><br><?= htmlspecialchars($data['nama_cabang'] ?? '-') ?></div>
    <div class="row"><label>Tanggal</label><br><?= htmlspecialchars($data['tanggal']) ?></div>
    <div class="row"><label>Masalah</label><br><?= nl2br(htmlspecialchars($data['masalah'])) ?></div>
    <div class="row"><label>Progress</label><br><?= htmlspecialchars($data['progress_masalah']) ?></div>
    <div class="row"><label>Status</label><br><?= $data['done_masalah'] ? 'Selesai' : 'Belum Selesai' ?></div>

    <div class="action">
        <a href="masalah.php" class="btn-back">← Kembali</a>
        <a href="editmasalah.php?id=<?= urlencode($data['nik']) ?>" class="btn-edit">Update</a>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
