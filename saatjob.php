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

// Ambil data edit dan minta dari saat_job
$editData = [];
$res = $conn->query("SELECT nik, edit, minta FROM saat_job ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    if (!isset($editData[$row['nik']])) {
        $editData[$row['nik']] = ['edit' => $row['edit'], 'minta' => (int)$row['minta']];
    }
}
$res->free();

// ✅ Ambil lokasi dari session (0 atau 1 = HO, > 1 = Cabang)
$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;

// Pencarian & Filter Cabang
$keyword = trim($_GET['keyword'] ?? '');
$params = [];
$types = '';
$filter = '';

// ✅ LOGIKA CABANG: Jika yang login BUKAN HO (ID > 1), kunci hanya pada cabangnya sendiri!
if ($session_location > 1) {
    $filter .= " AND p.cabang = ? ";
    $params[] = $session_location;
    $types .= 'i';
}

if ($keyword !== '') {
    $keyword = "%$keyword%";
    $filter .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'sss';
}

// ---------- TABEL 1 ----------
$sql1 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_sebelum_job
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' AND p.tanggal_sebelum_job > 0 
  AND (p.tanggal_saat_job IS NULL OR p.tanggal_saat_job = '0000-00-00')
  $filter
ORDER BY p.tanggal_sebelum_job DESC
";
$stmt1 = $conn->prepare($sql1);
if ($types) $stmt1->bind_param($types, ...$params);
$stmt1->execute();
$stmt1->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_sebelum_job);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = 0;
    if ($tanggal_sebelum_job && $tanggal_sebelum_job !== '0000-00-00') {
        $umur = (new DateTime($tanggal_sebelum_job))->diff(new DateTime())->days;
    }
    $data_belum[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_sebelum_job', 'umur');
}
$stmt1->close();

// ---------- TABEL 2 ----------
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_sebelum_job, p.tanggal_saat_job
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' AND p.tanggal_saat_job IS NOT NULL AND p.tanggal_saat_job != '0000-00-00'
  $filter
ORDER BY p.tanggal_saat_job DESC
";
$stmt2 = $conn->prepare($sql2);
if ($types) $stmt2->bind_param($types, ...$params);
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_sebelum_job, $tanggal_saat_job);

$data_sudah = [];
while ($stmt2->fetch()) {
    $umur = 0;
    if ($tanggal_sebelum_job && $tanggal_sebelum_job !== '0000-00-00') {
        $umur = (new DateTime($tanggal_sebelum_job))->diff(new DateTime())->days;
    }

    $edit_val = $editData[$nik]['edit'] ?? '';
    $minta_val = $editData[$nik]['minta'] ?? 0;

    $data_sudah[] = [
        'id' => $id,
        'nik' => $nik,
        'nama' => $nama,
        'nama_cabang' => $nama_cabang,
        'negara' => $negara,
        'tanggal_sebelum_job' => $tanggal_sebelum_job,
        'tanggal_saat_job' => $tanggal_saat_job,
        'umur' => $umur,
        'edit' => $edit_val,
        'minta' => $minta_val
    ];
}
$stmt2->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data TKI Saat Job</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2, h3 { text-align: center; }
        form { text-align: center; margin-bottom: 20px; }
        input[type="text"] { padding: 8px; width: 250px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn-job { background: #ffc107; color: black; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; }
        .btn-job:hover { background: #e0a800; }
        .btn-disabled { background: gray; color: white; padding: 6px 12px; border-radius: 4px; font-size: 14px; }
        .btn-reqedit { background: #007bff; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
      <a href="home.php" class="home-btn">🏠</a>
    <h2>Data TKI Saat Job</h2>
    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Diinput Saat Job</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
                      <th>Negara</th>
            <th>Tanggal Sebelum Job</th>
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
                <td><?= $row['tanggal_sebelum_job'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td><a class="btn-job" href="saatjobin.php?nik=<?= urlencode($row['nik']) ?>">Input</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h3>Sudah Diinput Saat Job</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
                        <th>Negara</th>
            <th>Tanggal Saat Job</th>

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
                <td><?= $row['tanggal_saat_job'] ?></td>
               
                <td>
                    <?php if ($row['edit'] === 'open'): ?>
                        <a class="btn-job" href="edit_saatjob.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                    <?php elseif ($row['minta'] == 1): ?>
                        <span class="btn-disabled">Menunggu Izin</span>
                    <?php else: ?>
                        <a class="btn-reqedit" href="reqedit_saatjob.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
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
