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

$servername = getenv('DB_HOST') ?: die("DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$cari = isset($_GET['cari']) ? $_GET['cari'] : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>BDM CUT-OFF</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { color: maroon; font-family: Arial, sans-serif; background-image: linear-gradient(to right, #ea90e6, #6c6cc9); margin: 0; padding: 0; }
        .table-container { width: 100%; overflow-x: auto; margin-top: 20px; color: white; }
        table { width: 100%; border-collapse: collapse; color: white; }
        th { background-color: maroon; }
        th, td { padding: 6px; border: 1px solid #ddd; text-align: left; font-size: 12px; }
        h2 { text-align: center; color: maroon; }
        .search-box { text-align: center; margin: 15px 0; }
        .search-box input, .search-box select, .search-box button { margin: 5px; padding: 6px; border-radius: 5px; border: 1px solid #ccc; }
        .search-box button { background: maroon; color: white; cursor: pointer; }
         .home-icon {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
    }
    }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="bdm.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
<div class="table-container">
    <h2>BDM CUT-OFF</h2>

    <div class="search-box">
        <form method="get">
            <label for="bulan">Bulan:</label>
            <select name="bulan" id="bulan">
                <option value="tahunan" <?= ($bulan == 'tahunan') ? 'selected' : '' ?>>Tahunan</option>
                <?php
                for ($b = 1; $b <= 12; $b++) {
                    $selected = ($b == $bulan) ? 'selected' : '';
                    echo "<option value='$b' $selected>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
                }
                ?>
            </select>
            <label for="tahun">Tahun:</label>
            <select name="tahun" id="tahun">
                <?php
                for ($t = date('Y') - 5; $t <= date('Y'); $t++) {
                    $selected = ($t == $tahun) ? 'selected' : '';
                    echo "<option value='$t' $selected>$t</option>";
                }
                ?>
            </select>
            <label for="cari">Cari:</label>
            <input type="text" name="cari" value="<?= $cari ?>" placeholder="Kode/Nama BDM">
            <button type="submit">Tampilkan</button>

        </form>
                    <button 
    onclick="location.href='export_bdmp.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&cari=<?= urlencode($cari) ?>'" 
    class="btn btn-success"
>
    <i class="fa fa-file-excel"></i> Download Excel
</button>
    </div>


<?php
// Penanganan periode
if ($bulan == "tahunan") {
    $periode_start = "$tahun-01-01";
    $periode_end = "$tahun-12-31";
    $cutoff_sebelumnya = date("Y-12-31", strtotime(($tahun - 1) . "-12-31"));
    $label_periode = "Tahunan $tahun";
} else {
    $bulan = str_pad($bulan, 2, "0", STR_PAD_LEFT);
    $periode_start = "$tahun-$bulan-01";
    $periode_end = date("Y-m-t", strtotime($periode_start));

    if ($bulan == "01") {
        $cutoff_sebelumnya = date("Y-12-31", strtotime(($tahun - 1) . "-12-31"));
    } else {
        $cutoff_sebelumnya = date("Y-m-t", strtotime("-1 month", strtotime($periode_start)));
    }

    $label_periode = "Periode " . date("F Y", strtotime($periode_start));
}

echo "<h2>Laporan BDM dan Pembebanan - $label_periode</h2>";

$sql = "SELECT kode, nama, tanggal, beli FROM bdm WHERE kode LIKE ? OR nama LIKE ? ORDER BY kode ASC";
$stmt = $conn->prepare($sql);
$cari_param = "%$cari%";
$stmt->bind_param("ss", $cari_param, $cari_param);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($kode, $nama, $tanggal, $beli);

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr>
        <th>No</th>
        <th>Kode</th>
        <th>Nama</th>
        <th>Tanggal Perolehan</th>
        <th>Harga Perolehan</th>
        <th>Nilai Buku Awal Periode</th>
        <th>Akumulasi Penyusutan s.d. $periode_end</th>
        <th>Penyusutan Periode Ini</th>
        <th>Nilai Buku Akhir</th>
      </tr>";

$no = 1;
$total_harga = 0;
$total_akumulasi = 0;
$total_akhir = 0;

while ($stmt->fetch()) {
    // Total susut sampai akhir periode
    $stmt_akum = $conn->prepare("SELECT SUM(susut) FROM susut WHERE kode = ? AND tanggal <= ?");
    $stmt_akum->bind_param("ss", $kode, $periode_end);
    $stmt_akum->execute();
    $stmt_akum->bind_result($akumulasi);
    $stmt_akum->fetch();
    $stmt_akum->close();
    $akumulasi = $akumulasi ?: 0;

    // Total susut sebelum periode
    $stmt_prev = $conn->prepare("SELECT SUM(susut) FROM susut WHERE kode = ? AND tanggal <= ?");
    $stmt_prev->bind_param("ss", $kode, $cutoff_sebelumnya);
    $stmt_prev->execute();
    $stmt_prev->bind_result($akum_prev);
    $stmt_prev->fetch();
    $stmt_prev->close();
    $akum_prev = $akum_prev ?: 0;

    $nilaibukuawal = $beli - $akum_prev;
    $penyusutan_periode = $akumulasi - $akum_prev;
    $nilai_buku_akhir = $beli - $akumulasi;

    echo "<tr>
            <td align='center'>$no</td>
            <td>$kode</td>
            <td>$nama</td>
            <td>$tanggal</td>
            <td align='right'>" . number_format($beli, 0, ',', '.') . "</td>
            <td align='right'>" . number_format($nilaibukuawal, 0, ',', '.') . "</td>
            <td align='right'>" . number_format($akumulasi, 0, ',', '.') . "</td>
            <td align='right'>" . number_format($penyusutan_periode, 0, ',', '.') . "</td>
            <td align='right'>" . number_format($nilai_buku_akhir, 0, ',', '.') . "</td>
          </tr>";

    $total_harga += $beli;
    $total_akumulasi += $akumulasi;
    $total_akhir += $nilai_buku_akhir;
    $no++;
}

echo "<tr style='font-weight:bold; background:#4B0000'>
        <td colspan='4' align='center'>TOTAL</td>
        <td align='right'>" . number_format($total_harga, 0, ',', '.') . "</td>
        <td align='right'></td>
        <td align='right'>" . number_format($total_akumulasi, 0, ',', '.') . "</td>
        <td align='right'></td>
        <td align='right'>" . number_format($total_akhir, 0, ',', '.') . "</td>
      </tr>";
echo "</table>";

$stmt->close();
$conn->close();
?>
