
<?php
// ============================================================
// DAFTAR ASET - AF_SYMOTECH
// Filter Tahun + Subtotal Kelompok + Penyusutan
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

date_default_timezone_set('Asia/Jakarta');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_only_cookies' => true,
        'use_strict_mode' => true
    ]);
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once __DIR__ . '/config.php';

// ============================================================
// DATABASE
// ============================================================

$servername = getenv('DB_HOST') ?: '';
$db_username = getenv('DB_USER') ?: '';
$db_password = function_exists('get_db_pass') ? get_db_pass() : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$database = getenv('DB_NAME') ?: '';

if (!$servername || !$db_username || !$database) {
    die("Konfigurasi database tidak lengkap.");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $servername,
        $db_username,
        $db_password,
        $database
    );

    $conn->set_charset('utf8mb4');
    require_once __DIR__ . '/aset_helper.php';
    ensureAsetKelompokDelSchema($conn);

} catch (Throwable $e) {
    error_log($e->getMessage());
    die("Koneksi database gagal.");
}

// ============================================================
// HELPER
// ============================================================

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($value)
{
    return number_format((float)$value, 2, ',', '.');
}

function tanggalIndo($tanggal)
{
    if (!$tanggal || $tanggal === '0000-00-00') {
        return '-';
    }

    return date('d/m/Y', strtotime($tanggal));
}

// ============================================================
// KELOMPOK
// ============================================================

$kelompokList = [
    'Tanah',
    'Kelompok 1',
    'Kelompok 2',
    'Kelompok 3',
    'Kelompok 4',
    'Bangunan Permanen',
    'Bangunan Tidak Permanen'
];

// ============================================================
// TAHUN TERAKHIR DATA
// ============================================================

$sqlTahun = "
    SELECT MAX(tahun) AS tahun_terakhir
    FROM (
        SELECT YEAR(tanggal) AS tahun
        FROM aset
        WHERE tanggal IS NOT NULL

        UNION ALL

        SELECT YEAR(tanggal) AS tahun
        FROM susut
        WHERE tanggal IS NOT NULL

        UNION ALL

        SELECT CAST(del AS UNSIGNED) AS tahun
        FROM aset
        WHERE del IS NOT NULL
    ) AS data_tahun
";

$resultTahun = $conn->query($sqlTahun);
$rowTahun = $resultTahun->fetch_assoc();

$tahunTerakhir = (int)($rowTahun['tahun_terakhir'] ?? date('Y'));

if ($tahunTerakhir <= 0) {
    $tahunTerakhir = (int)date('Y');
}

// ============================================================
// RANGE TAHUN
// ============================================================

$sqlRange = "
    SELECT
        MIN(tahun) AS tahun_awal,
        MAX(tahun) AS tahun_akhir
    FROM (
        SELECT YEAR(tanggal) AS tahun
        FROM aset
        WHERE tanggal IS NOT NULL

        UNION ALL

        SELECT YEAR(tanggal) AS tahun
        FROM susut
        WHERE tanggal IS NOT NULL
    ) AS x
";

$resultRange = $conn->query($sqlRange);
$range = $resultRange->fetch_assoc();

$tahunAwal = (int)($range['tahun_awal'] ?? $tahunTerakhir);
$tahunAkhir = max(
    $tahunTerakhir,
    (int)($range['tahun_akhir'] ?? $tahunTerakhir)
);

if ($tahunAwal <= 0) {
    $tahunAwal = $tahunTerakhir;
}

// ============================================================
// FILTER TAHUN
// ============================================================

$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : $tahunTerakhir;

if ($tahun < 1900 || $tahun > 2155) {
    $tahun = $tahunTerakhir;
}

$tanggalMulai = sprintf('%04d-01-01', $tahun);
$tanggalAkhir = sprintf('%04d-01-01', $tahun + 1);

// ============================================================
// QUERY ASET
//
// ATURAN DEL:
// del NULL = selalu tampil
// del = 2025, laporan 2025 = tampil
// del = 2025, laporan 2026 = tidak tampil
// del = 2026, laporan 2025 = tampil
//
// KONDISI:
// a.del IS NULL OR a.del >= tahun laporan
// ============================================================

$sql = "
    SELECT
        a.id,
        a.kode,
        a.nama,
        a.tanggal,
        a.beli,
        a.location,
        a.recuring,
        a.keteranganlok,
        a.kelompok,
        a.del,

        l.nama_cabang,

        COALESCE(s.akum_susut, 0) AS akum_susut,
        COALESCE(s.susut_tahun_ini, 0) AS susut_tahun_ini

    FROM aset a

    LEFT JOIN location l
        ON a.location = l.idl

    LEFT JOIN (
        SELECT
            kode,

            SUM(COALESCE(susut, 0)) AS akum_susut,

            SUM(
                CASE
                    WHEN tanggal >= ?
                     AND tanggal < ?
                    THEN COALESCE(susut, 0)
                    ELSE 0
                END
            ) AS susut_tahun_ini

        FROM susut

        WHERE tanggal < ?

        GROUP BY kode

    ) s ON s.kode = a.kode

    WHERE
        a.tanggal < ?

        AND (
            a.del IS NULL
            OR a.del >= ?
        )

    ORDER BY
        FIELD(
            a.kelompok,
            'Tanah',
            'Kelompok 1',
            'Kelompok 2',
            'Kelompok 3',
            'Kelompok 4',
            'Bangunan Permanen',
            'Bangunan Tidak Permanen'
        ),
        a.id ASC
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssss",
    $tanggalMulai,
    $tanggalAkhir,
    $tanggalAkhir,
    $tanggalAkhir,
    $tahun
);

$stmt->execute();

$stmt->bind_result(
    $id,
    $kode,
    $nama,
    $tanggalAset,
    $beli,
    $location,
    $recuring,
    $keteranganlok,
    $kelompok,
    $del,
    $namaCabang,
    $akumSusut,
    $susutTahunIni
);

// ============================================================
// PENAMPUNG KELOMPOK
// ============================================================

$dataKelompok = [];

foreach ($kelompokList as $namaKelompok) {

    $dataKelompok[$namaKelompok] = [
        'items' => [],
        'beli' => 0,
        'akum' => 0,
        'tahun' => 0,
        'nilai_buku' => 0
    ];
}

$totalBeli = 0;
$totalAkum = 0;
$totalTahunIni = 0;
$totalNilaiBuku = 0;
$jumlahAset = 0;

// ============================================================
// AMBIL DATA
// ============================================================

while ($stmt->fetch()) {

    $kode = (string)$kode;
    $nama = (string)$nama;
    $kelompok = (string)$kelompok;

    if (!isset($dataKelompok[$kelompok])) {
        $kelompok = 'Tanah';
    }

    $nilaiBeli = (float)$beli;
    $nilaiAkum = (float)$akumSusut;
    $nilaiTahunIni = (float)$susutTahunIni;

    $nilaiBuku = $nilaiBeli - $nilaiAkum;

    $item = [
        'id' => $id,
        'kode' => $kode,
        'nama' => $nama,
        'tanggal' => $tanggalAset,
        'beli' => $nilaiBeli,
        'akum' => $nilaiAkum,
        'tahun' => $nilaiTahunIni,
        'nilai_buku' => $nilaiBuku,
        'location' => $location,
        'nama_cabang' => $namaCabang,
        'recuring' => $recuring,
        'kelompok' => $kelompok
    ];

    $dataKelompok[$kelompok]['items'][] = $item;

    $dataKelompok[$kelompok]['beli'] += $nilaiBeli;
    $dataKelompok[$kelompok]['akum'] += $nilaiAkum;
    $dataKelompok[$kelompok]['tahun'] += $nilaiTahunIni;
    $dataKelompok[$kelompok]['nilai_buku'] += $nilaiBuku;

    $totalBeli += $nilaiBeli;
    $totalAkum += $nilaiAkum;
    $totalTahunIni += $nilaiTahunIni;
    $totalNilaiBuku += $nilaiBuku;

    $jumlahAset++;
}

$stmt->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Daftar Aset - AF Symotech</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 15px;
    font-family: Arial, sans-serif;
    background: #f2f4f8;
    color: #263247;
}

.container {
    max-width: 1800px;
    margin: auto;
}

.header {
    background: linear-gradient(135deg, #541020, #951f3b);
    color: white;
    border-radius: 13px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 4px 12px rgba(0,0,0,.12);
}

.header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.header h2 {
    margin: 0;
    font-size: 23px;
}

.header p {
    margin: 7px 0 0;
    font-size: 13px;
    opacity: .85;
}

.header a {
    color: white;
    font-size: 23px;
    margin-right: 12px;
    text-decoration: none;
}

.filter-panel {
    background: white;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 12px;
}

.filter-group {
    flex: 1;
    min-width: 170px;
}

.filter-group label {
    display: block;
    font-size: 12px;
    font-weight: bold;
    margin-bottom: 6px;
    color: #596579;
}

.filter-group input,
.filter-group select {
    width: 100%;
    height: 40px;
    border: 1px solid #d9dee8;
    border-radius: 7px;
    padding: 8px 10px;
    font-size: 14px;
    background: #fff;
}

.btn {
    border: none;
    border-radius: 7px;
    padding: 10px 13px;
    color: white;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
    transition: .2s;
}

.btn:hover {
    opacity: .85;
    transform: translateY(-1px);
}

.btn-primary { background: #7c1831; }
.btn-success { background: #16834a; }
.btn-info { background: #087da5; }
.btn-warning { background: #c58a14; }
.btn-secondary { background: #5e6878; }
.btn-danger { background: #c73535; }
.btn-dark { background: #283548; }

.button-area {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 14px;
}

.dashboard {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}

.stat-card {
    background: white;
    padding: 17px;
    border-radius: 11px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    border-left: 4px solid #8c1935;
    min-width: 0;
}

.stat-card:nth-child(2) { border-color: #087da5; }
.stat-card:nth-child(3) { border-color: #16834a; }
.stat-card:nth-child(4) { border-color: #c58a14; }

.stat-label {
    font-size: 12px;
    color: #778195;
    margin-bottom: 9px;
    font-weight: bold;
}

.stat-value {
    font-size: 17px;
    font-weight: bold;
    color: #263247;
    overflow-wrap: anywhere;
}

.stat-icon {
    float: right;
    font-size: 22px;
    opacity: .3;
}

.table-card {
    background: white;
    border-radius: 12px;
    padding: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}

.table-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 5px 3px 13px;
}

.table-heading h3 {
    margin: 0;
    font-size: 17px;
    color: #541020;
}

.table-heading span {
    color: #778195;
    font-size: 12px;
}

.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1250px;
    border-collapse: collapse;
    font-size: 12px;
}

thead th {
    background: #541020;
    color: white;
    padding: 12px 8px;
    text-align: center;
    border: 1px solid #753044;
    white-space: nowrap;
}

tbody td {
    padding: 9px 8px;
    border: 1px solid #e5e8ef;
    color: #364154;
}

tbody tr.detail-row:nth-child(even) {
    background: #f8f9fc;
}

tbody tr.detail-row:hover {
    background: #fff4f5;
}

.text-center { text-align: center; }

.text-right {
    text-align: right;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.kode {
    font-weight: bold;
    color: #7c1831;
}

.nama-aset {
    min-width: 180px;
    font-weight: bold;
}

.negative { color: #d12b2b !important; }
.positive { color: #16834a !important; }

.group-heading td {
    background: #e9edf5;
    color: #541020;
    font-weight: bold;
    font-size: 13px;
    padding: 11px;
    border-top: 2px solid #c9ceda;
}

.group-subtotal td {
    background: #f1e8eb;
    color: #541020;
    font-weight: bold;
    border-top: 2px solid #d5b6c0;
    padding: 11px 8px;
}

.grand-total td {
    background: #541020;
    color: white;
    font-weight: bold;
    padding: 14px 8px;
    font-size: 13px;
    border: 1px solid #753044;
}

.action-cell {
    min-width: 115px;
    text-align: center;
}

.action-grid {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 11px;
    flex-wrap: wrap;
}

.action-grid a {
    text-decoration: none;
    font-size: 17px;
}

.empty {
    text-align: center;
    padding: 35px !important;
    color: #8891a0;
}

.badge-year {
    display: inline-block;
    background: #fff;
    color: #7c1831;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.footer-note {
    font-size: 11px;
    color: #788397;
    margin-top: 12px;
    line-height: 1.7;
}

@media(max-width: 900px) {
    .dashboard {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 600px) {
    body { padding: 7px; }
    .header { padding: 15px; }
    .header h2 { font-size: 18px; }
    .dashboard { gap: 8px; }
    .stat-card { padding: 12px; }
    .stat-value { font-size: 13px; }
    .filter-group { min-width: 100%; }
    .button-area .btn { flex: 1 1 45%; }
    .table-card { padding: 7px; }
}
</style>
</head>

<body>

<div class="container">

    <div class="header">
        <div class="header-top">
            <div>
                <a href="home.php" title="Home">
                    <i class="fas fa-house"></i>
                </a>

                <h2 style="display:inline-block;">
                    DAFTAR ASET
                </h2>

                <p>Manajemen Aset Tetap dan Penyusutan</p>
            </div>

            <span class="badge-year">
                TAHUN <?= h($tahun) ?>
            </span>
        </div>
    </div>

    <!-- FILTER -->

    <div class="filter-panel">

        <form method="GET" action="">

            <div class="filter-row">

                <div class="filter-group">
                    <label>
                        <i class="fa-solid fa-calendar"></i>
                        Pilih Tahun Laporan
                    </label>

                    <select name="tahun" onchange="this.form.submit()">

                        <?php for ($y = $tahunAkhir; $y >= $tahunAwal; $y--): ?>

                            <option value="<?= $y ?>"
                                <?= $tahun == $y ? 'selected' : '' ?>>

                                <?= $y ?>
                                <?= $y == $tahunTerakhir ? '(Data Terakhir)' : '' ?>

                            </option>

                        <?php endfor; ?>

                    </select>
                </div>

                <div class="filter-group">
                    <label>
                        <i class="fa-solid fa-magnifying-glass"></i>
                        Cari Aset
                    </label>

                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Kode, nama, lokasi..."
                        onkeyup="filterTable()"
                    >
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-filter"></i>
                        Tampilkan
                    </button>
                </div>

            </div>

        </form>

        <!-- TOMBOL ACTION -->

        <div class="button-area">
            <?php if (file_exists(__DIR__ . '/asetin.php')): ?>
            <a href="asetin.php" class="btn btn-success">
                <i class="fa-solid fa-plus"></i>
                Add via Bank
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/dp_penjualan_aset.php')): ?>
            <a href="dp_penjualan_aset.php" class="btn btn-info">
                <i class="fa-solid fa-hand-holding-dollar"></i>
                DP Penjualan Aset
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/asetinc.php')): ?>
            <a href="asetinc.php" class="btn btn-success">
                <i class="fa-solid fa-plus"></i>
                Add via Cash
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/aset_nj.php')): ?>
            <a href="aset_nj.php" class="btn btn-secondary">
                <i class="fa-solid fa-plus"></i>
                Add Tanpa Jurnal
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/asetcut.php')): ?>
            <a href="asetcut.php?tahun=<?= (int)$tahun ?>" class="btn btn-warning">
                <i class="fa-solid fa-scissors"></i>
                Aset Cut-off
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/download_excel.php')): ?>
            <a href="download_excel.php?tahun=<?= (int)$tahun ?>" class="btn btn-success">
                <i class="fa-solid fa-file-excel"></i>
                Download Excel
            </a>
            <?php endif; ?>

            <?php if (file_exists(__DIR__ . '/asetcekrec.php')): ?>
            <a href="asetcekrec.php" class="btn btn-dark">
                <i class="fa-solid fa-rotate"></i>
                Cek Recuring
            </a>
            <?php endif; ?>
        </div>

    </div>

    <!-- DASHBOARD -->

    <div class="dashboard">

        <div class="stat-card">
            <i class="fa-solid fa-building stat-icon"></i>
            <div class="stat-label">JUMLAH ASET</div>
            <div class="stat-value">
                <?= number_format($jumlahAset, 0, ',', '.') ?>
            </div>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-wallet stat-icon"></i>
            <div class="stat-label">TOTAL PEROLEHAN</div>
            <div class="stat-value">
                Rp <?= rupiah($totalBeli) ?>
            </div>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-chart-line stat-icon"></i>
            <div class="stat-label">
                PENYUSUTAN TAHUN <?= h($tahun) ?>
            </div>
            <div class="stat-value">
                Rp <?= rupiah($totalTahunIni) ?>
            </div>
        </div>

        <div class="stat-card">
            <i class="fa-solid fa-coins stat-icon"></i>
            <div class="stat-label">TOTAL NILAI BUKU</div>
            <div class="stat-value">
                Rp <?= rupiah($totalNilaiBuku) ?>
            </div>
        </div>

    </div>

    <!-- TABEL -->

    <div class="table-card">

        <div class="table-heading">

            <h3>
                <i class="fa-solid fa-table-list"></i>
                Rincian Aset per Kelompok
            </h3>

            <span>
                Periode laporan: 01 Januari <?= h($tahun) ?>
                - 31 Desember <?= h($tahun) ?>
            </span>

        </div>

        <div class="table-container">

            <table id="asetTable">

                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kelompok</th>
                        <th>Kode</th>
                        <th>Nama Aset</th>
                        <th>Tanggal</th>
                        <th>Lokasi</th>
                        <th>Beli</th>
                        <th>Akum Susut</th>
                        <th>Penyusutan Tahun Ini</th>
                        <th>Nilai Buku</th>
                        <th>No Recuring</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                <?php

                $no = 1;
                $adaData = false;

                foreach ($kelompokList as $namaKelompok):

                    $group = $dataKelompok[$namaKelompok];

                    if (count($group['items']) === 0) {
                        continue;
                    }

                    $adaData = true;

                ?>

                    <!-- HEADER KELOMPOK -->

                    <tr class="group-heading"
                        data-type="heading"
                        data-group="<?= h($namaKelompok) ?>">

                        <td colspan="12">

                            <i class="fa-solid fa-folder-open"></i>

                            <?= h($namaKelompok) ?>

                            <span style="float:right;">
                                <?= count($group['items']) ?> Aset
                            </span>

                        </td>
                    </tr>

                    <!-- DETAIL ASET -->

                    <?php foreach ($group['items'] as $row): ?>

                        <tr
                            class="detail-row"
                            data-type="detail"
                            data-group="<?= h($namaKelompok) ?>"
                        >

                            <td class="text-center">
                                <?= $no++ ?>
                            </td>

                            <td>
                                <?= h($row['kelompok']) ?>
                            </td>

                            <td class="kode">
                                <?= h($row['kode']) ?>
                            </td>

                            <td class="nama-aset">
                                <?= h($row['nama']) ?>
                            </td>

                            <td class="text-center">
                                <?= tanggalIndo($row['tanggal']) ?>
                            </td>

                            <td>
                                <?= h($row['nama_cabang'] ?? '') ?>
                            </td>

                            <td class="text-right">
                                <?= rupiah($row['beli']) ?>
                            </td>

                            <td class="text-right">
                                <?= rupiah($row['akum']) ?>
                            </td>

                            <td class="text-right positive">
                                <?= rupiah($row['tahun']) ?>
                            </td>

                            <td class="text-right <?= $row['nilai_buku'] < 0 ? 'negative' : '' ?>">
                                <?= rupiah($row['nilai_buku']) ?>
                            </td>

                            <td class="text-center">
                                <?= h($row['recuring']) ?>
                            </td>

                            <td class="action-cell">

                                <div class="action-grid">

                                    <a href="#"
                                       onclick="openPopup('asetrecur.php?id=<?= urlencode($row['id']) ?>'); return false;"
                                       title="Lihat BDM Recur"
                                       style="color:#7c1831;">
                                        <i class="fa-solid fa-chart-gantt"></i>
                                    </a>

                                    <a href="#"
                                       onclick="openPopup('asetrecurdetail.php?kode=<?= urlencode($row['kode']) ?>'); return false;"
                                       title="Lihat Detail"
                                       style="color:#087da5;">
                                        <i class="fa-solid fa-list"></i>
                                    </a>

                                    <a href="#"
                                       onclick="openPopup('asetedit.php?id=<?= urlencode($row['id']) ?>'); return false;"
                                       title="Edit Aset"
                                       style="color:#16834a;">
                                        <i class="fa-solid fa-screwdriver-wrench"></i>
                                    </a>

                                    <a href="#"
                                       onclick="openPopup('asetjual.php?id=<?= urlencode($row['id']) ?>'); return false;"
                                       title="Jual Aset"
                                       style="color:#c58a14;">
                                        <i class="fa-solid fa-cart-shopping"></i>
                                    </a>

                                    <a href="barcode.php?kode=<?= urlencode($row['kode']) ?>"
                                       title="Barcode"
                                       style="color:#087da5;">
                                        <i class="fa-solid fa-qrcode"></i>
                                    </a>

                                    <a href="asetdel.php?id=<?= urlencode($row['id']) ?>&tahun=<?= (int)$tahun ?>"
                                       title="Hapus Aset"
                                       style="color:#c73535;">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <!-- SUBTOTAL -->

                    <tr
                        class="group-subtotal"
                        data-type="subtotal"
                        data-group="<?= h($namaKelompok) ?>"
                    >

                        <td colspan="6" class="text-right">
                            SUBTOTAL <?= strtoupper(h($namaKelompok)) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($group['beli']) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($group['akum']) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($group['tahun']) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($group['nilai_buku']) ?>
                        </td>

                        <td colspan="2"></td>

                    </tr>

                <?php endforeach; ?>

                <!-- GRAND TOTAL -->

                <?php if ($adaData): ?>

                    <tr class="grand-total">

                        <td colspan="6" class="text-right">
                            TOTAL KESELURUHAN
                        </td>

                        <td class="text-right">
                            <?= rupiah($totalBeli) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($totalAkum) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($totalTahunIni) ?>
                        </td>

                        <td class="text-right">
                            <?= rupiah($totalNilaiBuku) ?>
                        </td>

                        <td colspan="2"></td>

                    </tr>

                <?php else: ?>

                    <tr>
                        <td colspan="12" class="empty">
                            <i class="fa-solid fa-folder-open fa-2x"></i>
                            <br><br>
                            Tidak ada data aset untuk tahun <?= h($tahun) ?>.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="footer-note">

            <i class="fa-solid fa-circle-info"></i>

            Akum Susut = seluruh penyusutan sampai dengan tahun laporan.

            <br>

            Penyusutan Tahun Ini = penyusutan khusus tahun <?= h($tahun) ?>.

            <br>

            Nilai Buku = Harga Perolehan - Akumulasi Penyusutan.

            <br>

            Aset dengan del NULL tetap ditampilkan. Aset dengan del sama
            dengan tahun laporan juga tetap ditampilkan. Aset baru tidak
            ditampilkan mulai tahun setelah tahun penghapusannya.

        </div>

    </div>

</div>

<script>

function openPopup(url) {
    window.open(
        url,
        '_blank',
        'width=900,height=1050,scrollbars=yes,resizable=yes'
    );
}

function filterTable() {

    const filter = document.getElementById('searchInput')
        .value.toLowerCase().trim();

    const rows = Array.from(
        document.querySelectorAll('#asetTable tbody tr')
    );

    const groupCount = {};

    rows.forEach(function(row) {

        if (row.dataset.type !== 'detail') {
            return;
        }

        const cocok = row.innerText.toLowerCase().includes(filter);

        row.style.display = cocok ? '' : 'none';

        const group = row.dataset.group;

        if (cocok) {
            groupCount[group] = (groupCount[group] || 0) + 1;
        }

    });

    rows.forEach(function(row) {

        const type = row.dataset.type;

        if (type === 'heading' || type === 'subtotal') {

            const group = row.dataset.group;

            row.style.display =
                groupCount[group] > 0 ? '' : 'none';
        }

    });

}

</script>

</body>
</html>
