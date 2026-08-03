<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

$allowed_referer_domain = "https://af.symotech.id/";
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database   = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}



$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$search = "%$keyword%";
$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;
$filter_cabang = "";
if ($session_location > 1) {
    $filter_cabang = " AND pendaftaran.cabang = " . $session_location . " ";
}

// Query utama
$sql = "
SELECT 
    nikby.id,
    nikby.nik,
    pendaftaran.nama,
    jenisbiaya.namaby,
    nikby.nom,
    nikby.tanggal,
    nikby.klaim,
    nikby.kode_transaksi
FROM nikby
LEFT JOIN pendaftaran ON nikby.nik = pendaftaran.nik
LEFT JOIN jenisbiaya ON nikby.kodeby = jenisbiaya.id
WHERE (nikby.nik LIKE ? OR pendaftaran.nama LIKE ?) $filter_cabang
ORDER BY nikby.tanggal DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $search, $search);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $nik, $nama, $namaby, $nom, $tanggal, $klaim, $kode_transaksi);

// Rekap query
$rekap_sql = "
SELECT 
    nikby.nik,
    pendaftaran.nama,
    SUM(nikby.nom) AS total_biaya
FROM nikby
LEFT JOIN pendaftaran ON nikby.nik = pendaftaran.nik
WHERE (nikby.nik LIKE ? OR pendaftaran.nama LIKE ?) $filter_cabang
GROUP BY nikby.nik, pendaftaran.nama
ORDER BY pendaftaran.nama ASC
";
$rekap_stmt = $conn->prepare($rekap_sql);
$rekap_stmt->bind_param("ss", $search, $search);
$rekap_stmt->execute();
$rekap_stmt->store_result();
$rekap_stmt->bind_result($rekap_nik, $rekap_nama, $rekap_total);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Biaya Per TKI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        h2, h3 {
            text-align: center;
            color: #333;
        }
        .search-box {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 8px;
            width: 300px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        button {
            padding: 8px 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            margin-left: 10px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .klaim-yes {
            color: green;
            font-weight: bold;
        }
        .klaim-no {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2><i class="fas fa-money-check-alt"></i> Data Biaya Per TKI</h2>

<div class="search-box">
    <form method="get" action="">
        <input type="text" name="q" placeholder="Cari NIK atau Nama..." value="<?= htmlspecialchars($keyword) ?>">
        <button type="submit"><i class="fas fa-search"></i> Cari</button>
    </form>
</div>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Jenis Biaya</th>
            <th>Nominal</th>
            <th>Tanggal</th>
            <th>Klaim</th>
            <th>Kode Transaksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($stmt->num_rows > 0): ?>
            <?php while ($stmt->fetch()): ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><?= htmlspecialchars($nik) ?></td>
                    <td><?= htmlspecialchars($nama) ?></td>
                    <td><?= htmlspecialchars($namaby) ?></td>
                    <td>Rp <?= number_format($nom, 2, ',', '.') ?></td>
                    <td><?= date('d-m-Y', strtotime($tanggal)) ?></td>
                    <td class="<?= $klaim == '1' ? 'klaim-yes' : 'klaim-no' ?>">
                        <?= $klaim == '1' ? 'Sudah' : 'Belum' ?>
                    </td>
                    <td><?= htmlspecialchars($kode_transaksi) ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8">Tidak ada data ditemukan.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h3><i class="fas fa-chart-bar"></i> Rekap Total Biaya per TKI</h3>
<table>
    <thead>
        <tr>
            <th>NIK</th>
            <th>Nama</th>
            <th>Total Biaya</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($rekap_stmt->num_rows > 0): ?>
            <?php while ($rekap_stmt->fetch()): ?>
                <tr>
                    <td><?= htmlspecialchars($rekap_nik) ?></td>
                    <td><?= htmlspecialchars($rekap_nama) ?></td>
                    <td>Rp <?= number_format($rekap_total, 2, ',', '.') ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">Tidak ada rekap biaya ditemukan.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>

<?php
$rekap_stmt->close();
$stmt->close();
$conn->close();
?>