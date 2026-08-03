<?php
session_start();
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

$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;
$keyword = trim($_GET['keyword'] ?? '');
$params = [];
$types = '';
$filter = '';

if ($session_location > 1) {
    $filter .= " AND p.cabang = ? ";
    $params[] = $session_location;
    $types .= 'i';
}

if ($keyword !== '') {
    $keyword_param = "%$keyword%";
    $filter .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= 'sss';
}

// --- TABEL 1: Belum punya tanggal paspor ---
$sql1 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_asuransipra
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' 
  AND p.tanggal_asuransipra > 0 
  AND (p.tanggal_pembuatan_pasport IS NULL OR p.tanggal_pembuatan_pasport = '0000-00-00')
  $filter
ORDER BY p.tanggal_asuransipra DESC
";
$stmt1 = $conn->prepare($sql1);
if ($types) {
    $stmt1->bind_param($types, ...$params);
}
$stmt1->execute();
$stmt1->bind_result($id, $nik, $nama, $cabang, $negara, $tanggal_keluar_id);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = 0;
    if ($tanggal_keluar_id && $tanggal_keluar_id !== '0000-00-00') {
        $start = new DateTime($tanggal_keluar_id);
        $today = new DateTime();
        $umur = $start->diff($today)->days;
    }

    $data_belum[] = compact('id', 'nik', 'nama', 'cabang', 'negara', 'tanggal_keluar_id', 'umur');
}
$stmt1->close();

// --- TABEL 2: Sudah punya tanggal paspor (ambil dulu semua, baru proses edit/minta) ---
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_pembuatan_pasport 
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' 
  AND p.tanggal_pembuatan_pasport IS NOT NULL 
  AND p.tanggal_pembuatan_pasport != '0000-00-00'
  $filter
ORDER BY p.tanggal_pembuatan_pasport DESC
";
$stmt2 = $conn->prepare($sql2);
if ($types) {
    $stmt2->bind_param($types, ...$params);
}
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $cabang, $negara, $tanggal_pembuatan_pasport);

$rows_sudah = [];
while ($stmt2->fetch()) {
    $umur = 0;
    if ($tanggal_pembuatan_pasport && $tanggal_pembuatan_pasport !== '0000-00-00') {
        $start = new DateTime($tanggal_pembuatan_pasport);
        $today = new DateTime();
        $umur = $start->diff($today)->days;
    }

    $rows_sudah[] = [
        'id' => $id,
        'nik' => $nik,
        'nama' => $nama,
        'cabang' => $cabang,
        'negara' => $negara,
        'tanggal_pembuatan_pasport' => $tanggal_pembuatan_pasport,
        'umur' => $umur
    ];
}
$stmt2->close();

// --- Proses cek edit dan minta (setelah stmt2 ditutup) ---
$data_sudah = [];
foreach ($rows_sudah as $row) {
    $nik = $row['nik'];
    $edit_val = '';
    $minta_val = 0;

    $stmt3 = $conn->prepare("SELECT edit, minta FROM pembuatan_paspor WHERE nik = ? ORDER BY id DESC LIMIT 1");
    $stmt3->bind_param("s", $nik);
    $stmt3->execute();
    $stmt3->bind_result($edit, $minta);
    if ($stmt3->fetch()) {
        $edit_val = $edit;
        $minta_val = (int)$minta;
    }
    $stmt3->close();

    $row['edit'] = $edit_val;
    $row['minta'] = $minta_val;
    $data_sudah[] = $row;
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Pembuatan Paspor</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h2, h3 { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ccc; }
        th { background: #333; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-input { background: #28a745; color: white; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-reqedit { background: #007bff; color: white; }
        .btn-disabled { background: #6c757d; color: white; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="container">
         <a href="home.php" class="home-btn">🏠</a>
    <h2>Data Pembuatan Paspor</h2>

    <h3>Belum Memiliki Tanggal Pembuatan Paspor</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>negara</th>
            <th>Tanggal Asuransi PRA</th>
            <th>Umur (Hari)</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data_belum as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nik']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['cabang']) ?></td>
                <td><?= htmlspecialchars($row['negara']) ?></td>
                <td><?= $row['tanggal_keluar_id'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td><a class="btn btn-input" href="pasportin.php?nik=<?= urlencode($row['nik']) ?>">Input</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Sudah Memiliki Tanggal Pembuatan Paspor</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>negara</th>
            <th>Tanggal Paspor</th>
            <th>Umur (Hari)</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data_sudah as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nik']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['cabang']) ?></td>
                <td><?= htmlspecialchars($row['negara']) ?></td>
                <td><?= $row['tanggal_pembuatan_pasport'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td>
                    <?php if ($row['edit'] === 'open'): ?>
                        <a class="btn btn-edit" href="edit_pasport.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                    <?php elseif ($row['minta'] == 1): ?>
                        <span class="btn btn-disabled">Menunggu Izin</span>
                    <?php else: ?>
                        <a class="btn btn-reqedit" href="reqedit_pembuatanpaspor.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?>
