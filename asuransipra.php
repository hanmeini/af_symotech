<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Ambil data edit & minta dari tabel asuransipra
$editData = [];
$res = $conn->query("SELECT nik, edit, minta FROM asuransipra ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    if (!isset($editData[$row['nik']])) {
        $editData[$row['nik']] = ['edit' => $row['edit'], 'minta' => (int)$row['minta']];
    }
}
$res->free();

// Filter pencarian
$keyword = trim($_GET['keyword'] ?? '');
$filter = '';
$params = [];
$types = '';

if ($keyword !== '') {
    $keyword = "%$keyword%";
    $filter = " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params = [$keyword, $keyword, $keyword];
    $types = 'sss';
}

// ---------- TABEL 1: Belum Input ----------
$sql1 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_keluar_id
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' AND (p.tanggal_asuransipra IS NULL OR p.tanggal_asuransipra = '0000-00-00') AND p.tanggal_keluar_id > 0
$filter
ORDER BY p.tanggal_asuransipra DESC
";
$stmt1 = $conn->prepare($sql1);
if ($types) $stmt1->bind_param($types, ...$params);
$stmt1->execute();
$stmt1->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_asuransipra);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = ($tanggal_asuransipra && $tanggal_asuransipra !== '0000-00-00') ? (new DateTime($tanggal_asuransipra))->diff(new DateTime())->days : 0;
    $data_belum[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_asuransipra', 'umur');
}
$stmt1->close();

// ---------- TABEL 2: Sudah Input ----------
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_asuransipra
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN asuransipra k ON k.nik = p.nik
WHERE p.status = 'aktive' 
  AND p.tanggal_asuransipra IS NOT NULL 
  AND p.tanggal_asuransipra != '0000-00-00'  

  $filter
ORDER BY p.tanggal_asuransipra DESC
";

$stmt2 = $conn->prepare($sql2);
if ($types) $stmt2->bind_param($types, ...$params);
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_asuransipra);

$data_sudah = [];
while ($stmt2->fetch()) {
    $umur = ($tanggal_asuransipra && $tanggal_asuransipra !== '0000-00-00') 
        ? (new DateTime($tanggal_asuransipra))->diff(new DateTime())->days 
        : 0;
    $edit_val = $editData[$nik]['edit'] ?? '';
    $minta_val = $editData[$nik]['minta'] ?? 0;
    $data_sudah[] = compact(
        'id', 'nik', 'nama', 'nama_cabang', 
        'negara', 'tanggal_asuransipra', 
        'umur', 'edit_val', 'minta_val'
    );
}
$stmt2->close();

$sql3 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_asuransipra
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN asuransipra k ON k.nik = p.nik
WHERE p.status = 'aktive' 
  AND p.tanggal_asuransipra IS NOT NULL 
  AND p.tanggal_asuransipra != '0000-00-00'

  $filter
ORDER BY p.tanggal_asuransipra DESC
";
$stmt3 = $conn->prepare($sql3);
if ($types) $stmt3->bind_param($types, ...$params);
$stmt3->execute();
$stmt3->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_asuransipra);

$data_pending = [];
while ($stmt3->fetch()) {
    $umur = ($tanggal_asuransipra && $tanggal_asuransipra !== '0000-00-00') ? (new DateTime($tanggal_asuransipra))->diff(new DateTime())->days : 0;
    $edit_val = $editData[$nik]['edit'] ?? '';
    $minta_val = $editData[$nik]['minta'] ?? 0;
    $data_pending[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_asuransipra', 'umur', 'edit_val', 'minta_val');
}
$stmt3->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data TKI asuransipra</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2, h3 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"] { padding: 8px; width: 250px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn-asuransipra {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-asuransipra:hover { background: #138496; }
        .btn-disabled {
            background: gray;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-reqedit {
            background: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
        }
        
        .home-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
        <a href="home.php" class="home-btn">🏠</a>
<div class="container">
    <h2>Data TKI Aktif & Asuransi Pra</h2>

    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Diinput Asuransi Pra</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>negara</th>
            <th>keluar ID</th>
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
                <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                <td><?= htmlspecialchars($row['negara']) ?></td>
                <td><?= $row['tanggal_asuransipra'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td><a class="btn-asuransipra" href="input_asuransipra.php?nik=<?= urlencode($row['nik']) ?>">Input asuransipra</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    
    

    <h3>Sudah Diinput Asuransi Pra</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>negara</th>
            <th>Tanggal asuransipra</th>
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
                <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                <td><?= htmlspecialchars($row['negara']) ?></td>
                <td><?= $row['tanggal_asuransipra'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td>
                    <?php if ($row['edit_val'] === 'open'): ?>
                        <a class="btn-asuransipra" href="edit_asuransipra.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                    <?php elseif ($row['minta_val'] == 1): ?>
                        <span class="btn-disabled">Menunggu Izin</span>
                    <?php else: ?>
                        <a class="btn-reqedit" href="reqedit_asuransipra.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
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
