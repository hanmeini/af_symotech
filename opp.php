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

// Pastikan tabel opp ada
$conn->query("
CREATE TABLE IF NOT EXISTS opp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(30) NOT NULL,
    nomor_kartu VARCHAR(100) NOT NULL,
    nama_dokumen VARCHAR(255) DEFAULT 'E-KTKLN',
    file_dokumen VARCHAR(255) DEFAULT NULL,
    tanggal_opp DATE DEFAULT NULL,
    edit ENUM('open','clouse') DEFAULT 'clouse',
    minta INT DEFAULT 0,
    alesan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Pastikan kolom tanggal_opp ada di pendaftaran
$chkCol = $conn->query("SHOW COLUMNS FROM pendaftaran LIKE 'tanggal_opp'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftaran ADD COLUMN tanggal_opp DATE DEFAULT NULL AFTER tanggal_suhan");
}

// Ambil data edit & minta dari tabel opp
$editData = [];
$res = $conn->query("SELECT nik, edit, minta FROM opp ORDER BY id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (!isset($editData[$row['nik']])) {
            $editData[$row['nik']] = ['edit' => $row['edit'], 'minta' => (int)$row['minta']];
        }
    }
    $res->free();
}

// Filter pencarian & cabang
$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;
$keyword = trim($_GET['keyword'] ?? '');
$filter = '';
$params = [];
$types = '';

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

// ---------- TABEL 1: Belum Input OPP / KTKLN (Semua Negara) ----------
$sql1 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_pendaftaran
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.status = 'aktive' 
  AND (p.tanggal_opp IS NULL OR p.tanggal_opp = '0000-00-00')
$filter
ORDER BY p.tanggal_pendaftaran DESC
";
$stmt1 = $conn->prepare($sql1);
if ($types) $stmt1->bind_param($types, ...$params);
$stmt1->execute();
$stmt1->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_pendaftaran);

$data_belum = [];
while ($stmt1->fetch()) {
    $umur = ($tanggal_pendaftaran && $tanggal_pendaftaran !== '0000-00-00') 
        ? (new DateTime($tanggal_pendaftaran))->diff(new DateTime())->days 
        : 0;
    $data_belum[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_pendaftaran', 'umur');
}
$stmt1->close();

// ---------- TABEL 2: Sudah Input OPP / KTKLN ----------
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_opp,
       o.nomor_kartu, o.nama_dokumen, o.file_dokumen, o.edit, o.minta
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
INNER JOIN opp o ON o.nik = p.nik
WHERE p.status = 'aktive' 
  AND p.tanggal_opp IS NOT NULL 
  AND p.tanggal_opp != '0000-00-00'
$filter
ORDER BY p.tanggal_opp DESC
";
$stmt2 = $conn->prepare($sql2);
if ($types) $stmt2->bind_param($types, ...$params);
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_opp, $nomor_kartu, $nama_dokumen, $file_dokumen, $edit_val, $minta_val);

$data_sudah = [];
while ($stmt2->fetch()) {
    $umur = ($tanggal_opp && $tanggal_opp !== '0000-00-00') 
        ? (new DateTime($tanggal_opp))->diff(new DateTime())->days 
        : 0;
    $data_sudah[] = compact(
        'id', 'nik', 'nama', 'nama_cabang',
        'negara', 'tanggal_opp', 'nomor_kartu', 'nama_dokumen', 'file_dokumen',
        'umur', 'edit_val', 'minta_val'
    );
}
$stmt2->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data OPP / KTKLN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2, h3 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"] { padding: 8px; width: 250px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 25px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn-input { background: #28a745; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; }
        .btn-input:hover { background: #218838; }
        .btn-view { background: #17a2b8; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 13px; }
        .btn-view:hover { background: #138496; }
        .home-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <a href="home.php" class="home-btn"><i class="fa-solid fa-arrow-left"></i> Kembali ke Menu</a>
<div class="container">
    <h2>Data OPP & E-KTKLN (Semua Negara)</h2>

    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($keyword_clean ?? ($_GET['keyword'] ?? '')) ?>">
        <input type="submit" value="Cari">
    </form>

    <h3>Belum Diinput OPP / KTKLN (<?= count($data_belum) ?>)</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
            <th>Tanggal Daftar</th>
            <th>Umur (Hari)</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($data_belum)): ?>
            <tr><td colspan="8" style="text-align:center; color:#6c757d;">Semua pekerja aktif sudah memiliki data OPP / KTKLN.</td></tr>
        <?php else: ?>
            <?php foreach ($data_belum as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nik']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td><?= $row['tanggal_pendaftaran'] ?></td>
                    <td><?= $row['umur'] ?> hari</td>
                    <td>
                        <a class="btn-input" href="oppin.php?nik=<?= urlencode($row['nik']) ?>"><i class="fa-solid fa-plus"></i> Input OPP/KTKLN</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <h3>Sudah Diinput OPP / KTKLN (<?= count($data_sudah) ?>)</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
            <th>Tanggal OPP</th>
            <th>Nomor Kartu</th>
            <th>Nama Dokumen</th>
            <th>File Dokumen</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($data_sudah)): ?>
            <tr><td colspan="10" style="text-align:center; color:#6c757d;">Belum ada data OPP / KTKLN.</td></tr>
        <?php else: ?>
            <?php foreach ($data_sudah as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nik']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td><?= $row['tanggal_opp'] ?></td>
                    <td><?= htmlspecialchars($row['nomor_kartu']) ?></td>
                    <td><?= htmlspecialchars($row['nama_dokumen']) ?></td>
                    <td>
                        <?php if (!empty($row['file_dokumen'])): ?>
                            <a class="btn-view" href="/pekerja/<?= rawurlencode($row['file_dokumen']) ?>" target="_blank"><i class="fa-solid fa-file"></i> Lihat Dokumen</a>
                        <?php else: ?>
                            <span style="color:gray;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-view" style="background:#6c757d;" href="detail_pekerja.php?nik=<?= urlencode($row['nik']) ?>"><i class="fa-solid fa-eye"></i> Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?>
