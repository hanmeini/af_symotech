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
$where = "p.status = 'aktive' AND p.tanggal_sikotes IS NOT NULL AND p.tanggal_sikotes != '0000-00-00' AND p.negara ='TAIWAN'";
$params = [];
$types = '';

if ($session_location > 1) {
    $where .= " AND p.cabang = ? ";
    $params[] = $session_location;
    $types .= 'i';
}

if ($keyword !== '') {
    $keyword_param = "%$keyword%";
    $where .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $params[] = $keyword_param;
    $types .= 'sss';
}

$sql = "SELECT 
            p.id, p.nik, p.nama, p.cabang, p.status, p.negara, 
            p.tanggal_sikotes, p.tanggal_skck,
            l.nama_cabang 
        FROM pendaftaran p 
        LEFT JOIN location l ON p.cabang = l.idl
        WHERE $where 
        ORDER BY p.tanggal_sikotes DESC";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stmt->bind_result($id, $nik, $nama, $cabang, $status, $negara, $tanggal_pembuatan_pasport, $tanggal_setelah_penempatan, $nama_cabang);

$data_belum = [];
$data_sudah = [];
while ($stmt->fetch()) {
    $row = [
        'id' => $id,
        'nik' => $nik,
        'nama' => $nama,
        'cabang' => $cabang,
        'nama_cabang' => $nama_cabang,
        'status' => $status,
        'negara' => $negara,
        'tanggal_pembuatan_pasport' => $tanggal_pembuatan_pasport,
        'tanggal_setelah_penempatan' => $tanggal_setelah_penempatan
    ];

    if (!$tanggal_setelah_penempatan || $tanggal_setelah_penempatan === '0000-00-00') {
        $data_belum[] = $row;
    } else {
        $data_sudah[] = $row;
    }
}
$stmt->close();

// cek ke tabel skck
foreach ($data_sudah as &$row) {
    $nik = $row['nik'];
    $row['edit'] = '';
    $row['minta'] = 0;

    $stmt2 = $conn->prepare("SELECT edit, minta FROM skck WHERE nik = ? ORDER BY id DESC LIMIT 1");
    $stmt2->bind_param("s", $nik);
    $stmt2->execute();
    $stmt2->bind_result($edit, $minta);
    if ($stmt2->fetch()) {
        $row['edit'] = $edit;
        $row['minta'] = (int)$minta;
    }
    $stmt2->close();

    $row['umur'] = 0;
    if ($row['tanggal_pembuatan_pasport'] && $row['tanggal_pembuatan_pasport'] !== '0000-00-00') {
        $start = new DateTime($row['tanggal_pembuatan_pasport']);
        $today = new DateTime();
        $row['umur'] = $start->diff($today)->days;
    }
}
unset($row);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data TKI SKCK</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"] { padding: 8px; width: 250px; }
        input[type="submit"] { padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn {
            padding: 6px 10px;
            font-size: 13px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 4px;
        }
        .btn-input { background: #28a745; color: white; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-reqedit { background: #007bff; color: white; }
        .btn-disabled { background: #6c757d; color: white; cursor: default; }
        .home-btn { position: absolute; top: 10px; left: 10px; font-size: 24px; text-decoration: none; }
    </style>
</head>
<body>
<div class="container">
    <a href="home.php" class="home-btn">🏠</a>
    <h2>Data TKI SKCK</h2>
    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Isi Tanggal Setelah Penempatan</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
            <th>Tanggal Paspor</th>
            <th>Umur (Hari)</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($data_belum)): ?>
            <?php foreach ($data_belum as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nik']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td><?= $row['tanggal_pembuatan_pasport'] ?></td>
                    <td><?= (new DateTime($row['tanggal_pembuatan_pasport']))->diff(new DateTime())->days ?> hari</td>
                    <td><a class="btn btn-input" href="skck_input.php?nik=<?= urlencode($row['nik']) ?>">Input SKCK</a></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h3>Sudah Isi SKCK</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
            <th>Tanggal Paspor</th>
            <th>Umur (Hari)</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($data_sudah)): ?>
            <?php foreach ($data_sudah as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nik']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td><?= $row['tanggal_pembuatan_pasport'] ?></td>
                    <td><?= $row['umur'] ?> hari</td>
                    <td>
                        <?php if ($row['edit'] === 'open'): ?>
                            <a class="btn btn-edit" href="skck_edit.php?nik=<?= urlencode($row['nik']) ?>">Edit SKCK</a>
                        <?php elseif ((int)$row['minta'] === 1): ?>
                            <span class="btn btn-disabled">Menunggu Ijin Edit</span>
                        <?php else: ?>
                            <a class="btn btn-reqedit" href="skck_reqedit.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?>
