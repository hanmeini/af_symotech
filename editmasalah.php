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
   PROSES UPDATE
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $progress = trim($_POST['progress_masalah']);
    $done     = isset($_POST['done_masalah']) ? 1 : 0;

    $sql = "
        UPDATE masalah
        SET progress_masalah = ?, done_masalah = ?
        WHERE nik = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $progress, $done, $nik);
    $stmt->execute();

    header("Location: masalah.php");
    exit;
}

/* =======================
   AMBIL DATA
   - Ambil pendaftaran TERAKHIR
   - Cocokkan 16 digit NIK
======================= */
$sql = "
SELECT 
    m.nik,
    p.nama,
    l.nama_cabang,
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
<title>Edit Masalah</title>
<style>
body {
    font-family: Arial, sans-serif;
    background:#f4f4f4;
    padding:20px;
}
.box {
    background:#fff;
    max-width:520px;
    margin:auto;
    padding:20px;
    border-radius:6px;
}
h2 {
    text-align:center;
}
label {
    font-weight:bold;
}
textarea {
    width:100%;
    height:90px;
    padding:6px;
}
.action {
    margin-top:15px;
}
button {
    padding:8px 14px;
}
a {
    margin-left:10px;
    text-decoration:none;
}
</style>
</head>
<body>

<div class="box">
    <h2>Edit Progress Masalah</h2>

    <form method="post">
        <p><b>NIK:</b><br><?= htmlspecialchars($data['nik']) ?></p>
        <p><b>Nama:</b><br><?= htmlspecialchars($data['nama'] ?? '-') ?></p>
        <p><b>Cabang:</b><br><?= htmlspecialchars($data['nama_cabang'] ?? '-') ?></p>

        <label>Progress</label>
        <textarea name="progress_masalah" required><?= htmlspecialchars($data['progress_masalah']) ?></textarea>

        <br><br>
        <label>
            <input type="checkbox" name="done_masalah" value="1"
                <?= $data['done_masalah'] ? 'checked' : '' ?>>
            Tandai sebagai selesai
        </label>

        <div class="action">
            <button type="submit">Simpan</button>
            <a href="masalah.php">Batal</a>
        </div>
    </form>
</div>

</body>
</html>

<?php
$conn->close();
?>
