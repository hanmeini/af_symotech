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
   PROSES UPDATE
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal = $_POST['tanggal'] ?? '';
    $alasan  = $_POST['alasan'] ?? '';
    $edit    = $_POST['edit'] ?? 'open';

    if (!$tanggal || !$alasan) {
        echo "<script>alert('Tanggal dan alasan wajib diisi');</script>";
    } else {

        $stmt = $conn->prepare("
            UPDATE mundur SET
                tanggal = ?,
                alasan  = ?,
                edit    = ?
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $tanggal, $alasan, $edit, $id);
        $stmt->execute();
        $stmt->close();

        echo "<script>
            alert('Data pengunduran diri berhasil diupdate');
            location='mundur.php';
        </script>";
        exit;
    }
}

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
<title>Edit Pengunduran Diri</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f0f0f0;
    padding: 20px;
}
.container {
    max-width: 600px;
    background: #fff;
    padding: 20px;
    margin: auto;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
h2 { text-align:center; }
label {
    display: block;
    margin-top: 15px;
    font-weight: bold;
}
input, textarea, select {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    border-radius: 4px;
    border: 1px solid #ccc;
}
input[readonly] { background: #f5f5f5; }
textarea { min-height: 120px; }
.btn {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
input[type="submit"], .btn a {
    width: 48%;
    padding: 10px;
    font-weight: bold;
    border-radius: 4px;
    text-align: center;
    text-decoration: none;
}
input[type="submit"] {
    background: #ffc107;
    color: #000;
    border: none;
    cursor: pointer;
}
.btn a {
    background: #6c757d;
    color: #fff;
}
</style>
</head>
<body>

<div class="container">
<h2>Edit Pengunduran Diri</h2>

<form method="post">

    <label>Tanggal</label>
    <input type="date" name="tanggal" value="<?= htmlspecialchars($data['tanggal']) ?>" required>

    <label>NIK</label>
    <input type="text" value="<?= htmlspecialchars($data['nik']) ?>" readonly>

    <label>Nama</label>
    <input type="text" value="<?= htmlspecialchars($data['nama'] ?? '-') ?>" readonly>

    <label>Cabang</label>
    <input type="text" value="<?= htmlspecialchars($data['nama_cabang'] ?? '-') ?>" readonly>

    <label>Alasan Mundur</label>
    <textarea name="alasan" required><?= htmlspecialchars($data['alasan']) ?></textarea>

    <label>Status</label>
    <select name="edit">
        <option value="open"   <?= $data['edit']=='open'?'selected':'' ?>>Open</option>
        <option value="clouse" <?= $data['edit']=='clouse'?'selected':'' ?>>Closed</option>
    </select>

    <div class="btn">
        <input type="submit" value="Update">
        <a href="mundur.php">Batal</a>
    </div>
</form>

</div>

</body>
</html>

<?php $conn->close(); ?>
