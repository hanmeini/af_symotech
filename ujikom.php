<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

/* ============================
   ✅ CEK SESSION
   ============================ */
if (!isset($_SESSION['userid']) || !isset($_SESSION['location'])) {
    header("Location: index.php");
    exit();
}

$location = (int)$_SESSION['location']; // ✅ penting

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

/* ============================
   ✅ Ambil data edit/minta
   ============================ */
$editData = [];
$res = $conn->query("SELECT nik, edit, minta FROM ujikom ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    if (!isset($editData[$row['nik']])) {
        $editData[$row['nik']] = ['edit' => $row['edit'], 'minta' => (int)$row['minta']];
    }
}
$res->free();

/* ============================
   ✅ Filter pencarian
   ============================ */
$keyword = trim($_GET['keyword'] ?? '');
$filter = '';
$params = [];
$types = '';

/* --- Pencarian keyword --- */
if ($keyword !== '') {
    $keyword = "%$keyword%";
    $filter .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params = [$keyword, $keyword, $keyword];
    $types = 'sss';
}

/* --- Filter cabang berdasarkan session --- */
if ($location > 1) {  
    // ✅ jika bukan HO, wajib filter cabang
    $filter .= " AND p.cabang = ?";
    $params[] = $location;
    $types .= "i";
}

/* ---------------------------
   ✅ TABEL 1: Belum Input UJIKOM
   --------------------------- */
$sql1 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_ujikom, p.tanggal_saat_job
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' 
  AND (p.tanggal_ujikom IS NULL OR p.tanggal_ujikom = '0000-00-00')
  AND p.tanggal_saat_job > 0
  $filter
ORDER BY p.id DESC
";

$stmt1 = $conn->prepare($sql1);
if ($types) $stmt1->bind_param($types, ...$params);
$stmt1->execute();
$stmt1->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_ujikom, $tanggal_saat_job);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = ($tanggal_ujikom && $tanggal_ujikom !== '0000-00-00') ?
        (new DateTime($tanggal_ujikom))->diff(new DateTime())->days : 0;
    $data_belum[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_ujikom', 'umur', 'tanggal_saat_job');
}
$stmt1->close();

/* ---------------------------
   ✅ TABEL 2: Sudah Input UJIKOM
   --------------------------- */
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_ujikom
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN ujikom k ON k.nik = p.nik
WHERE p.status = 'aktive'
  AND p.tanggal_ujikom IS NOT NULL
  AND p.tanggal_ujikom != '0000-00-00'
  $filter
ORDER BY p.tanggal_ujikom DESC
";

$stmt2 = $conn->prepare($sql2);
if ($types) $stmt2->bind_param($types, ...$params);
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_ujikom);

$data_sudah = [];
while ($stmt2->fetch()) {

    $umur = ($tanggal_ujikom && $tanggal_ujikom !== '0000-00-00')
        ? (new DateTime($tanggal_ujikom))->diff(new DateTime())->days
        : 0;

    $edit_val = $editData[$nik]['edit'] ?? '';
    $minta_val = $editData[$nik]['minta'] ?? 0;

    $data_sudah[] = compact(
        'id', 'nik', 'nama', 'nama_cabang',
        'negara', 'tanggal_ujikom',
        'umur', 'edit_val', 'minta_val'
    );
}
$stmt2->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Uji Kompetensi (Ujikom)</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .container { width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2, h3 { text-align: center; }
        form { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }

        .btn-ujikom { background: #17a2b8; padding: 6px 12px; color: #fff; border-radius: 4px; text-decoration: none; }
        .btn-reqedit { background: #007bff; padding: 6px 12px; color: #fff; border-radius: 4px; text-decoration: none; }
        .btn-disabled { background: gray; padding: 6px 12px; color: #fff; border-radius: 4px; }
        
        .home-btn { padding: 8px 14px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

<a href="home.php" class="home-btn">🏠 Home</a>

<div class="container">
    <h2>Data TKI Aktif - Uji Kompetensi</h2>

    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Diinput Ujikom</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>NIK</th><th>Nama</th><th>Cabang</th><th>Negara</th>
                <th>Tanggal</th><th>Umur</th><th>Aksi</th>
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
                <td><?= $row['tanggal_saat_job'] ?></td>
                <td><?= $row['umur'] ?> hari</td>
                <td><a class="btn-ujikom" href="input_ujikom.php?nik=<?= urlencode($row['nik']) ?>">Input</a></td>
            </tr>
        <?php endforeach; ?>

        </tbody>
    </table>


    <h3>Sudah Diinput Ujikom</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th><th>NIK</th><th>Nama</th><th>Cabang</th><th>Negara</th>
                <th>Tanggal</th><th>Umur</th><th>Aksi</th>
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
                <td><?= $row['tanggal_ujikom'] ?></td>
                <td><?= $row['umur'] ?> hari</td>

                <td>
                    <?php if ($row['edit_val'] === 'open'): ?>
                        <a class="btn-ujikom" href="edit_ujikom.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>

                    <?php elseif ($row['minta_val'] == 1): ?>
                        <span class="btn-disabled">Menunggu Izin</span>

                    <?php else: ?>
                        <a class="btn-reqedit" href="reqedit_ujikom.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
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
