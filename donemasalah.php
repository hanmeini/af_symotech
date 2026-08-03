<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

/* =======================
   KONEKSI DATABASE
======================= */
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// ✅ Ambil lokasi dari session (0 atau 1 = HO, > 1 = Cabang)
$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;

/* =======================
   PENCARIAN & CABANG
======================= */
$cari = trim($_GET['cari'] ?? '');

/* =======================
   QUERY DATA
   - TAMPILKAN MASALAH SUDAH SELESAI
   - NIK masalah = 16 digit
   - NIK pendaftaran bisa 17 digit
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
    m.progress_masalah
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
WHERE m.done_masalah = 1
";

$params = [];
$types = '';

// ✅ LOGIKA CABANG: Jika yang login BUKAN HO (ID > 1), kunci hanya pada cabangnya sendiri!
if ($session_location > 1) {
    $sql .= " AND p.cabang = ? ";
    $params[] = $session_location;
    $types .= 'i';
}

if ($cari !== '') {
    $sql .= " AND (m.nik LIKE ? OR p.nama LIKE ?)";
    $like = "%$cari%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$sql .= " ORDER BY m.tanggal DESC";

$stmt = $conn->prepare($sql);

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Masalah Selesai</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f4f4;
    padding: 20px;
}
h2 { text-align: center; }

.top-bar {
    display: flex;
    justify-content: space-between;
    max-width: 1200px;
    margin: auto;
    margin-bottom: 15px;
}
.top-bar a {
    text-decoration: none;
    padding: 8px 14px;
    border-radius: 5px;
    font-weight: bold;
    font-size: 14px;
    color: #fff;
}
.btn-home { background: #6c757d; }
.btn-back { background: #007bff; }

.search-box {
    max-width: 600px;
    margin: auto;
    margin-bottom: 10px;
}
.search-box input {
    width: 100%;
    padding: 8px;
}

table {
    width: 100%;
    max-width: 1200px;
    margin: auto;
    background: #fff;
    border-collapse: collapse;
}
th, td {
    padding: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
}
th { background: #eee; }
tr:hover { background: #f9f9f9; }

.action a {
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 4px;
    color: #fff;
    text-decoration: none;
}
.btn-detail { background: #17a2b8; }
</style>
</head>
<body>

<h2>Daftar Masalah (Sudah Selesai)</h2>

<div class="top-bar">
    <a href="home.php" class="btn-home">Home</a>
    <a href="masalah.php" class="btn-back">Masalah Aktif</a>
</div>

<div class="search-box">
    <form method="get">
        <input type="text" name="cari" placeholder="Cari NIK atau Nama..."
               value="<?= htmlspecialchars($cari) ?>">
    </form>
</div>

<table>
<thead>
<tr>
    <th>No</th>
    <th>Tanggal</th>
    <th>NIK</th>
    <th>Nama</th>
    <th>Cabang</th>
    <th>Masalah</th>
    <th>Progress</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php
$no = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
            <td>{$no}</td>
            <td>{$row['tanggal']}</td>
            <td>{$row['nik']}</td>
            <td>" . htmlspecialchars($row['nama'] ?? '-') . "</td>
            <td>" . htmlspecialchars($row['nama_cabang'] ?? '-') . "</td>
            <td>" . nl2br(htmlspecialchars($row['masalah'])) . "</td>
            <td>" . htmlspecialchars($row['progress_masalah']) . "</td>
            <td class='action'>
                <a href='detailmasalah.php?id={$row['nik']}' class='btn-detail'>Detail</a>
            </td>
        </tr>";
        $no++;
    }
} else {
    echo "<tr>
        <td colspan='8' style='text-align:center'>Tidak ada data</td>
    </tr>";
}
?>
</tbody>
</table>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
