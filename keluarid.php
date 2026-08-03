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

$keyword = trim($_GET['keyword'] ?? '');
$where = "p.status = 'aktive' AND p.tanggal_saat_job > 0";
$params = [];
$types = '';

if ($keyword !== '') {
    $keyword = "%$keyword%";
    $where .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params = [$keyword, $keyword, $keyword];
    $types = 'sss';
}

// ----------- TABEL 1: Belum Keluar ID -----------
$sql1 = "SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_ujikom
         FROM pendaftaran p 
         LEFT JOIN location l ON p.cabang = l.idl 
         WHERE $where AND (p.tanggal_keluar_id = 0 OR p.tanggal_keluar_id IS NULL OR p.tanggal_keluar_id = '0000-00-00') AND p.tanggal_ujikom > 0
         ORDER BY p.tanggal_ujikom DESC";
$stmt1 = $conn->prepare($sql1);
if ($types) {
    $stmt1->bind_param($types, ...$params);
}
$stmt1->execute();
$stmt1->store_result();
$stmt1->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_saat_job);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = 0;
    if ($tanggal_saat_job && $tanggal_saat_job !== '0000-00-00') {
        $start = new DateTime($tanggal_saat_job);
        $today = new DateTime();
        $umur = $start->diff($today)->days;
    }

    $data_belum[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_saat_job', 'umur');
}
$stmt1->close();

// ----------- TABEL 2: Sudah Keluar ID -----------
$sql2 = "SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_ujikom, p.tanggal_keluar_id 
         FROM pendaftaran p 
         LEFT JOIN location l ON p.cabang = l.idl 
         WHERE $where AND p.tanggal_keluar_id IS NOT NULL AND p.tanggal_keluar_id != '0000-00-00' 
         ORDER BY p.tanggal_keluar_id DESC";
$stmt2 = $conn->prepare($sql2);
if ($types) {
    $stmt2->bind_param($types, ...$params);
}
$stmt2->execute();
$stmt2->store_result();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_saat_job, $tanggal_keluar_id);

$data_sudah = [];
while ($stmt2->fetch()) {
    $umur = 0;
    if ($tanggal_saat_job && $tanggal_saat_job !== '0000-00-00') {
        $start = new DateTime($tanggal_saat_job);
        $today = new DateTime();
        $umur = $start->diff($today)->days;
    }

    $stmt3 = $conn->prepare("SELECT edit, minta FROM keluar_id WHERE nik = ? ORDER BY id DESC LIMIT 1");
    $stmt3->bind_param("s", $nik);
    $stmt3->execute();
    $stmt3->bind_result($edit, $minta);
    $edit_val = '';
    $minta_val = 0;
    if ($stmt3->fetch()) {
        $edit_val = $edit;
        $minta_val = (int)$minta;
    }
    $stmt3->close();

    $data_sudah[] = [
        'id' => $id,
        'nik' => $nik,
        'nama' => $nama,
        'nama_cabang' => $nama_cabang,
        'negara' => $negara,
        'tanggal_saat_job' => $tanggal_saat_job,
        'tanggal_keluar_id' => $tanggal_keluar_id,
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
    <title>Data Keluar ID</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; }
        h2, h3 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"] { padding: 8px; width: 250px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { padding: 6px 10px; font-size: 13px; border-radius: 4px; text-decoration: none; }
        .btn-input { background: #28a745; color: white; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-reqedit { background: #007bff; color: white; }
        .btn-disabled { background: #6c757d; color: white; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="container">
         <a href="home.php" class="home-btn">🏠</a>
    <h2>Data TKI Keluar ID</h2>
    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Keluar ID</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
   <th>Negara</th>
            <th>Tanggal Uji Kom</th>
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
                    <td><?= $row['tanggal_saat_job'] ?></td>
                    <td><?= $row['umur'] ?> hari</td>
                    <td><a class="btn btn-input" href="keluaridin.php?nik=<?= urlencode($row['nik']) ?>">Input</a></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="8" style="text-align:center;">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h3>Sudah Keluar ID</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
          <th>Negara</th>

            <th>Tanggal Keluar ID</th>
     
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
                    <td><?= $row['tanggal_keluar_id'] ?></td>
       
                    <td>
                        <?php if ($row['edit'] === 'open'): ?>
                            <a class="btn btn-edit" href="edit_keluarid.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                        <?php elseif ($row['minta'] == 1): ?>
                            <span class="btn btn-disabled">Menunggu Izin</span>
                        <?php else: ?>
                            <a class="btn btn-reqedit" href="reqedit_keluarid.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9" style="text-align:center;">Tidak ada data.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
