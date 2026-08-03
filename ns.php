<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://af.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}



?>
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$tahunan = ($bulan === 'tahunan');

if ($tahunan) {
    $tanggal_cutoff_awal = ($tahun - 1) . "-12-31";
    $tanggal_cutoff_akhir = "$tahun-12-31";
} else {
    $tanggal_cutoff_awal = "$tahun-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
    $tanggal_cutoff_akhir = date("Y-m-t", strtotime($tanggal_cutoff_awal));
}

echo "<form method='GET'>
    Pilih Bulan: <select name='bulan'>
        <option value='tahunan' " . ($tahunan ? 'selected' : '') . ">Tahunan</option>";
for ($i = 1; $i <= 12; $i++) {
    $selected = ($bulan == $i) ? 'selected' : '';
    echo "<option value='$i' $selected>$i</option>";
}

echo "</select>
    Pilih Tahun: <input type='number' name='tahun' value='$tahun'>
    <input type='submit' value='Filter'>
</form>";

$query = "
    SELECT c.account_code, c.account_name,
        COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
        COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir,
        COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS lr_debet,
        COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS lr_kredit,
        COALESCE(SUM(CASE WHEN c.posisi = 'neraca' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS debet_neraca,
        COALESCE(SUM(CASE WHEN c.posisi = 'neraca' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS kredit_neraca
    FROM coa c
    LEFT JOIN jurnal j ON c.account_code = j.coa
    GROUP BY c.account_code, c.account_name
    ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Kesalahan pada prepare statement: " . $conn->error);
}

$stmt->bind_param('ssssssssssssss', 
    $tanggal_cutoff_awal, $tanggal_cutoff_awal, $tanggal_cutoff_akhir, 
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_akhir, 
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_awal, 
    $tanggal_cutoff_akhir, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir);
$stmt->execute();
$stmt->bind_result(
    $account_code, $account_name, $saldo_awal, $total_debet, $total_kredit, $saldo_akhir, 
    $lr_debet, $lr_kredit, $debet_neraca, $kredit_neraca
);

$data = [];
while ($stmt->fetch()) {
    $data[$account_code] = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'saldo_awal' => (float) $saldo_awal,
        'total_debet' => (float) $total_debet,
        'total_kredit' => (float) $total_kredit,
        'saldo_akhir' => (float) $saldo_akhir,
        'lr_debet' => (float) $lr_debet,
        'lr_kredit' => (float) $lr_kredit,
        'debet_neraca' => (float) $debet_neraca,
        'kredit_neraca' => (float) $kredit_neraca
    ];
}

ksort($data);
foreach ($data as $key => &$values) {
    for ($i = 1; $i <= 5; $i++) {
        $prefix = substr($key, 0, 5 - $i) . str_repeat('0', $i);
        if ($prefix !== $key && isset($data[$prefix])) {
            foreach (['saldo_awal', 'total_debet', 'total_kredit', 'saldo_akhir', 'lr_debet', 'lr_kredit', 'debet_neraca', 'kredit_neraca'] as $col) {
                $data[$prefix][$col] += $values[$col];
            }
        }
    }
}

unset($values);

echo "<table border='1'>
<tr>
    <th>Account Code</th>
    <th>Account Name</th>
    <th>Saldo Awal</th>
    <th>Total Debet</th>
    <th>Total Kredit</th>
    <th>Saldo Akhir</th>
    <th>LR Debet</th>
    <th>LR Kredit</th>
    <th>Debet Neraca</th>
    <th>Kredit Neraca</th>
</tr>";

foreach ($data as $values) {
    echo "<tr>
        <td>{$values['account_code']}</td>
        <td>{$values['account_name']}</td>
        <td align='right'>" . number_format($values['saldo_awal'], 2) . "</td>
        <td align='right'>" . number_format($values['total_debet'], 2) . "</td>
        <td align='right'>" . number_format($values['total_kredit'], 2) . "</td>
        <td align='right'>" . number_format($values['saldo_akhir'], 2) . "</td>
        <td align='right'>" . number_format($values['lr_debet'], 2) . "</td>
        <td align='right'>" . number_format($values['lr_kredit'], 2) . "</td>
        <td align='right'>" . number_format($values['debet_neraca'], 2) . "</td>
        <td align='right'>" . number_format($values['kredit_neraca'], 2) . "</td>
    </tr>";
}

echo "</table>";
$stmt->close();
$conn->close();

?>
