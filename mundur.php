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
   QUERY DATA MUNDUR
======================= */
$sql = "
SELECT 
    m.id,
    m.tanggal,
    m.nik,
    m.alasan,
    m.edit,
    p.nama,
    l.nama_cabang
FROM mundur m
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
WHERE m.edit = 'open'
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
<title>Daftar Pengunduran Diri</title>
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
.btn-add { background: #28a745; }
.btn-done { background: #007bff; }

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
    margin-right: 4px;
}
.btn-detail { background: #17a2b8; }
.btn-edit { background: #ffc107; color: #000; }
</style>
</head>
<body>

<h2>Daftar Pengunduran Diri (Open)</h2>

<div class="top-bar">
    <div>
        <a href="home.php" class="btn-home">Home</a>
    </div>
    <div>
        <a href="inputmundur.php" class="btn-add">+ Input Mundur</a>
        <a href="mundurselesai.php" class="btn-done">Data Closed</a>
    </div>
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
    <th>Alasan</th>
    <th>Status</th>
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
            <td>" . nl2br(htmlspecialchars($row['alasan'])) . "</td>
            <td>" . htmlspecialchars($row['edit']) . "</td>
            <td class='action'>
                <a href='detailmundur.php?id={$row['id']}' class='btn-detail'>Detail</a>
                <a href='editmundur.php?id={$row['id']}' class='btn-edit'>Update</a>
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
