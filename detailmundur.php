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
if ($conn->connect_error) die("Koneksi gagal");

/* =======================
   AMBIL ID
======================= */
$id = intval($_GET['id'] ?? 0);
if (!$id) die("ID tidak valid");

/* =======================
   AMBIL DATA
======================= */
$stmt = $conn->prepare("
    SELECT 
        m.*,
        p.nama,
        l.nama_cabang
    FROM mundur m
    LEFT JOIN pendaftaran p ON LEFT(p.nik,16) = m.nik
    LEFT JOIN location l ON l.idl = p.cabang
    WHERE m.id = ?
    ORDER BY p.id DESC
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$data = $result->fetch_assoc()) {
    die("Data tidak ditemukan");
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Pengunduran Diri</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    padding: 20px;
}
.container {
    max-width: 700px;
    background: #fff;
    padding: 20px;
    margin: auto;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
h2 { text-align: center; }

.table {
    width: 100%;
    border-collapse: collapse;
}
.table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
    vertical-align: top;
}
.table td:first-child {
    width: 180px;
    font-weight: bold;
    background: #f7f7f7;
}

.box-alasan {
    background: #f9f9f9;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
    white-space: pre-wrap;
}

.btn {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
}
.btn a {
    padding: 10px 16px;
    border-radius: 5px;
    font-weight: bold;
    text-decoration: none;
    color: #fff;
}
.btn-back { background: #6c757d; }
.btn-edit { background: #17a2b8; }
</style>
</head>
<body>

<div class="container">
<h2>Detail Pengunduran Diri</h2>

<table class="table">
    <tr>
        <td>Tanggal</td>
        <td><?= htmlspecialchars($data['tanggal']) ?></td>
    </tr>
    <tr>
        <td>NIK</td>
        <td><?= htmlspecialchars($data['nik']) ?></td>
    </tr>
    <tr>
        <td>Nama</td>
        <td><?= htmlspecialchars($data['nama'] ?? '-') ?></td>
    </tr>
    <tr>
        <td>Cabang</td>
        <td><?= htmlspecialchars($data['nama_cabang'] ?? '-') ?></td>
    </tr>
    <tr>
        <td>Status</td>
        <td><?= htmlspecialchars(strtoupper($data['edit'])) ?></td>
    </tr>
    <tr>
        <td>Alasan Mundur</td>
        <td>
            <div class="box-alasan">
                <?= nl2br(htmlspecialchars($data['alasan'])) ?>
            </div>
        </td>
    </tr>
</table>

<div class="btn">
    <a href="mundur.php" class="btn-back">Kembali</a>
    <a href="editmundur.php?id=<?= $data['id'] ?>" class="btn-edit">Edit</a>
</div>

</div>

</body>
</html>

<?php $conn->close(); ?>
