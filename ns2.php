<?php
// ns2.php - Neraca Saldo (Lajur) PT Arni Family (Standar Masbox)
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

// Validasi referer fleksibel
if (isset($_SERVER['HTTP_REFERER'])) {
    $referer_host = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    $current_host = $_SERVER['HTTP_HOST'] ?? '';
    $allowed_hosts = [$current_host, 'af.symotech.id', 'localhost', '127.0.0.1'];
    if ($referer_host && !in_array($referer_host, $allowed_hosts) && strpos($current_host, $referer_host) === false) {
        error_log("Referer warning di ns2.php: " . $_SERVER['HTTP_REFERER']);
    }
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';
require_once 'functions.php';

$servername  = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$database    = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Handle GET parameter untuk filter
$bulan_awal = $_GET['bulan_awal'] ?? date('m');
$bulan_akhir = $_GET['bulan_akhir'] ?? $bulan_awal;
$tahun_terpilih = $_GET['tahun'] ?? date('Y');
$loc_terpilih = $_GET['location'] ?? '';
$dev_terpilih = $_GET['devisi'] ?? '';

// Array bulan
$bulan_arr = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Neraca Saldo - PT Arni Family</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1800px; margin: 0 auto; background: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); border-radius: 8px; }
        h2 { text-align: center; color: #333; margin-top: 5px; }
        form { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 20px; align-items: center; }
        select, button { padding: 8px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #007bff; color: #fff; cursor: pointer; transition: background 0.3s; border: none; }
        button:hover { background-color: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: right; }
        th { background-color: #007bff; color: white; text-align: center; font-size: 13px; }
        tr:nth-child(even) { background-color: #fcfcfc; }
        tr:hover { background-color: #e6f7ff; }
        .home-icon1 { position: absolute; top: 15px; left: 15px; color: maroon; font-size: 24px; text-decoration: none; }
        .home-icon1:hover { color: #800000; }
        .badge-audit { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .badge-pass { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .badge-fail { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
<div class='table-container'>
    <a href='home.php' class='home-icon1' title='Kembali ke Dashboard'><i class='fas fa-home'></i></a>
</div>
<div class="container">
    <h2>NERACA SALDO (Lajur)</h2>

    <form method="get">
        <label>Bulan Awal:</label>
        <select name="bulan_awal">
            <?php foreach ($bulan_arr as $key => $value): ?>
                <option value="<?= $key ?>" <?= ($key == $bulan_awal) ? 'selected' : '' ?>><?= $value ?></option>
            <?php endforeach; ?>
        </select>

        <label>Bulan Akhir:</label>
        <select name="bulan_akhir">
            <?php foreach ($bulan_arr as $key => $value): ?>
                <option value="<?= $key ?>" <?= ($key == $bulan_akhir) ? 'selected' : '' ?>><?= $value ?></option>
            <?php endforeach; ?>
        </select>

        <label>Tahun:</label>
        <select name="tahun">
            <?php
            $current_y = (int)date('Y');
            for ($i = 2023; $i <= $current_y + 1; $i++): ?>
                <option value="<?= $i ?>" <?= ($i == $tahun_terpilih) ? 'selected' : '' ?>><?= $i ?></option>
            <?php endfor; ?>
        </select>

        <select name="location">
            <option value="">All Location</option>
            <?php
            $resLoc = $conn->query("SELECT idl, nama_cabang FROM location ORDER BY nama_cabang");
            if ($resLoc && $resLoc->num_rows > 0) {
                while ($row = $resLoc->fetch_assoc()) {
                    $sel = ($row['idl'] == $loc_terpilih) ? 'selected' : '';
                    echo "<option value='{$row["idl"]}' $sel>{$row["nama_cabang"]}</option>";
                }
            }
            ?>
        </select>

        <button type="submit"><i class="fa-solid fa-filter"></i> Tampilkan</button>
    </form>

    <!-- Kontrol Layer & Tombol Export -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top: 15px; background:#f8fafc; padding:10px 15px; border-radius:6px; border:1px solid #e2e8f0;">
        <div style="display:flex; gap:15px; font-size:13px; font-weight:600; color:#475569;">
            <label><input type="checkbox" id="toggleLayer1" checked> Layer 1</label>
            <label><input type="checkbox" id="toggleLayer2" checked> Layer 2</label>
            <label><input type="checkbox" id="toggleLayer3" checked> Layer 3</label>
            <label><input type="checkbox" id="toggleLayer4" checked> Layer 4</label>
        </div>
        <div>
            <a href="export_ns.php?bulan_awal=<?= urlencode($bulan_awal) ?>&bulan_akhir=<?= urlencode($bulan_akhir) ?>&tahun=<?= urlencode($tahun_terpilih) ?>&location=<?= urlencode($loc_terpilih) ?>&devisi=<?= urlencode($dev_terpilih) ?>" target="_blank">
                <button type="button" style="background-color: #28a745; color: white;"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
            </a>
        </div>
    </div>

<?php
$selected_location = $_GET['location'] ?? '';
$selected_devisi   = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

$tanggal_cutoff_awal  = "$tahun_terpilih-" . str_pad($bulan_awal, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun_terpilih-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

echo "<p style='margin: 15px 0 5px 0; color:#475569; font-size:14px;'><strong>Periode:</strong> " . date("d M Y", strtotime($tanggal_cutoff_awal)) . " s/d " . date("d M Y", strtotime($tanggal_cutoff_akhir)) . "</p>";

$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi   = !empty($selected_devisi) ? "AND j.devisi = ?" : "";

// Query Utama dari Tabel COA + Jurnal
$query = "
    SELECT c.account_code, c.account_name, c.layer, c.parent_account, COALESCE(c.posisi, '') AS posisi,
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
     AND j.journal_number IS NOT NULL
        $filter_location
        $filter_devisi
    GROUP BY c.account_code, c.account_name, c.layer, c.parent_account, c.posisi
    ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) die("Kesalahan pada prepare statement: " . $conn->error);

$param_types = 'ssssssssssssss';
$params = [
    $tanggal_cutoff_awal, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_awal,
    $tanggal_cutoff_akhir, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir
];

if (!empty($selected_location)) { $param_types .= 's'; $params[] = $selected_location; }
if (!empty($selected_devisi))   { $param_types .= 's'; $params[] = $selected_devisi; }

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$stmt->bind_result(
    $account_code, $account_name, $layer, $parent_account, $posisi, $saldo_awal,
    $total_debet, $total_kredit, $saldo_akhir,
    $lr_debet, $lr_kredit, $debet_neraca, $kredit_neraca
);

$data = [];
while ($stmt->fetch()) {
    $first_digit = substr($account_code, 0, 1);
    $is_credit   = is_credit_coa('', $account_code, $account_name);

    $saldo_awal_debet  = 0;
    $saldo_awal_kredit = 0;

    // Saldo awal hanya berlaku untuk akun Neraca (kepala 1, 2, 3)
    if ($posisi === 'neraca' || in_array($first_digit, ['1','2','3'])) {
        if ($is_credit) {
            $saldo_awal_kredit = (float)-$saldo_awal;
        } else {
            $saldo_awal_debet  = (float)$saldo_awal;
        }
    }

    // Saldo akhir umum
    if ($is_credit) {
        $saldo_akhir = $saldo_awal_kredit - (float)$total_debet + (float)$total_kredit;
    } else {
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
    }

    // Klasifikasi P&L (Kepala 4, 5, 6, 7, 8, 9 atau posisi P&L)
    $is_pl = ($posisi === 'P&L') || in_array($first_digit, ['4','5','6','7','8','9']);
    if ($is_pl) {
        if ($is_credit) {
            $lr_kredit = $saldo_akhir;
            $lr_debet  = 0;
        } else {
            $lr_debet  = $saldo_akhir;
            $lr_kredit = 0;
        }
    } else {
        $lr_debet  = 0;
        $lr_kredit = 0;
    }

    // Klasifikasi Neraca (Kepala 1, 2, 3)
    if (!$is_pl) {
        if ($is_credit) {
            $kredit_neraca = $saldo_akhir;
            $debet_neraca  = 0;
        } else {
            $debet_neraca  = $saldo_akhir;
            $kredit_neraca = 0;
        }
    } else {
        $debet_neraca  = 0;
        $kredit_neraca = 0;
    }

    $data[$account_code] = [
        'account_code'      => $account_code,
        'account_name'      => $account_name,
        'layer'             => (int)$layer,
        'parent_account'    => $parent_account,
        'posisi'            => $posisi,
        'saldo_awal_debet'  => (float)$saldo_awal_debet,
        'saldo_awal_kredit' => (float)$saldo_awal_kredit,
        'total_debet'       => (float)$total_debet,
        'total_kredit'      => (float)$total_kredit,
        'saldo_akhir'       => (float)$saldo_akhir,
        'lr_debet'          => (float)$lr_debet,
        'lr_kredit'         => (float)$lr_kredit,
        'debet_neraca'      => (float)$debet_neraca,
        'kredit_neraca'     => (float)$kredit_neraca
    ];
}
$stmt->close();

// Query Transaksi pada Akun yang Belum Terdaftar di Tabel COA (Unmapped Accounts)
$sql_unmapped = "
    SELECT j.coa,
        COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
        COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir,
        COALESCE(SUM(CASE WHEN (j.coa LIKE '4%' OR j.coa LIKE '5%' OR j.coa LIKE '6%' OR j.coa LIKE '7%' OR j.coa LIKE '8%' OR j.coa LIKE '9%') AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS lr_debet,
        COALESCE(SUM(CASE WHEN (j.coa LIKE '4%' OR j.coa LIKE '5%' OR j.coa LIKE '6%' OR j.coa LIKE '7%' OR j.coa LIKE '8%' OR j.coa LIKE '9%') AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS lr_kredit,
        COALESCE(SUM(CASE WHEN (j.coa LIKE '1%' OR j.coa LIKE '2%' OR j.coa LIKE '3%') AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS debet_neraca,
        COALESCE(SUM(CASE WHEN (j.coa LIKE '1%' OR j.coa LIKE '2%' OR j.coa LIKE '3%') AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS kredit_neraca
    FROM jurnal j
    LEFT JOIN coa c ON c.account_code = j.coa
    WHERE j.journal_number IS NOT NULL
      AND c.account_code IS NULL
      $filter_location
      $filter_devisi
    GROUP BY j.coa
";

$stmt_unmapped = $conn->prepare($sql_unmapped);
if ($stmt_unmapped) {
    $stmt_unmapped->bind_param($param_types, ...$params);
    $stmt_unmapped->execute();
    $res_unmapped = $stmt_unmapped->get_result();
    while ($row_u = $res_unmapped->fetch_assoc()) {
        $u_code = $row_u['coa'];
        $first_digit = substr($u_code, 0, 1);
        $is_credit   = is_credit_coa('', $u_code, '');

        $saldo_awal_debet  = 0;
        $saldo_awal_kredit = 0;
        if (in_array($first_digit, ['1','2','3'])) {
            if ($is_credit) {
                $saldo_awal_kredit = (float)-$row_u['saldo_awal'];
            } else {
                $saldo_awal_debet  = (float)$row_u['saldo_awal'];
            }
        }

        if ($is_credit) {
            $saldo_akhir = $saldo_awal_kredit - (float)$row_u['total_debet'] + (float)$row_u['total_kredit'];
        } else {
            $saldo_akhir = $saldo_awal_debet + (float)$row_u['total_debet'] - (float)$row_u['total_kredit'];
        }

        $is_pl = in_array($first_digit, ['4','5','6','7','8','9']);
        if ($is_pl) {
            if ($is_credit) {
                $lr_kredit = $saldo_akhir;
                $lr_debet  = 0;
            } else {
                $lr_debet  = $saldo_akhir;
                $lr_kredit = 0;
            }
            $debet_neraca  = 0;
            $kredit_neraca = 0;
        } else {
            $lr_debet  = 0;
            $lr_kredit = 0;
            if ($is_credit) {
                $kredit_neraca = $saldo_akhir;
                $debet_neraca  = 0;
            } else {
                $debet_neraca  = $saldo_akhir;
                $kredit_neraca = 0;
            }
        }

        $data[$u_code] = [
            'account_code'      => $u_code,
            'account_name'      => 'Akun ' . $u_code,
            'layer'             => 4,
            'parent_account'    => substr($u_code, 0, 5),
            'posisi'            => $is_pl ? 'P&L' : 'neraca',
            'saldo_awal_debet'  => (float)$saldo_awal_debet,
            'saldo_awal_kredit' => (float)$saldo_awal_kredit,
            'total_debet'       => (float)$row_u['total_debet'],
            'total_kredit'      => (float)$row_u['total_kredit'],
            'saldo_akhir'       => (float)$saldo_akhir,
            'lr_debet'          => (float)$lr_debet,
            'lr_kredit'         => (float)$lr_kredit,
            'debet_neraca'      => (float)$debet_neraca,
            'kredit_neraca'     => (float)$kredit_neraca
        ];
    }
    $stmt_unmapped->close();
}

ksort($data);

// ======================================================
// HITUNG SELISIH NERACA TAHUN SEBELUMNYA & TAHUN BERJALAN
// ======================================================
$tahun_awal = $tahun_terpilih . '-01-01';

// Selisih Neraca Tahun Sebelumnya (< 1 Jan tahun terpilih)
$sql_prev = "
    SELECT
        COALESCE(SUM(CASE WHEN j.coa LIKE '1%' THEN (j.debet - j.kredit) ELSE 0 END), 0) AS aset,
        COALESCE(SUM(CASE WHEN j.coa LIKE '2%' OR j.coa LIKE '3%' THEN (j.kredit - j.debet) ELSE 0 END), 0) AS kewajiban_modal
    FROM jurnal j
    WHERE j.journal_number IS NOT NULL
      AND j.tanggal < ?
";
$stmt_prev = $conn->prepare($sql_prev);
$stmt_prev->bind_param("s", $tahun_awal);
$stmt_prev->execute();
$res_prev = $stmt_prev->get_result()->fetch_assoc();
$selisih_tahun_sebelumnya = (float)$res_prev['aset'] - (float)$res_prev['kewajiban_modal'];
$stmt_prev->close();

// Selisih Neraca Tahun Berjalan (1 Jan s/d Cutoff Akhir)
$sql_curr = "
    SELECT
        COALESCE(SUM(CASE WHEN j.coa LIKE '1%' THEN (j.debet - j.kredit) ELSE 0 END), 0) AS aset,
        COALESCE(SUM(CASE WHEN j.coa LIKE '2%' OR j.coa LIKE '3%' THEN (j.kredit - j.debet) ELSE 0 END), 0) AS kewajiban_modal
    FROM jurnal j
    WHERE j.journal_number IS NOT NULL
      AND j.tanggal BETWEEN ? AND ?
";
$stmt_curr = $conn->prepare($sql_curr);
$stmt_curr->bind_param("ss", $tahun_awal, $tanggal_cutoff_akhir);
$stmt_curr->execute();
$res_curr = $stmt_curr->get_result()->fetch_assoc();
$selisih_tahun_berjalan = (float)$res_curr['aset'] - (float)$res_curr['kewajiban_modal'];
$stmt_curr->close();

// Deteksi Kode Akun Retained Earnings secara dinamis
$re_accounts = find_re_accounts($data);
$prev_year_re_code = $re_accounts['prev'];
$curr_year_re_code = $re_accounts['curr'];

// Suntikkan Laba Ditahan Tahun Lalu ke Neraca
if (isset($data[$prev_year_re_code])) {
    $data[$prev_year_re_code]['kredit_neraca'] += $selisih_tahun_sebelumnya;
    $data[$prev_year_re_code]['saldo_akhir']   += $selisih_tahun_sebelumnya;
} else {
    $data[$prev_year_re_code] = [
        'account_code'      => $prev_year_re_code,
        'account_name'      => 'Laba Ditahan Tahun Lalu',
        'layer'             => 4,
        'parent_account'    => substr($prev_year_re_code, 0, 5),
        'posisi'            => 'neraca',
        'saldo_awal_debet'  => 0,
        'saldo_awal_kredit' => $selisih_tahun_sebelumnya,
        'total_debet'       => 0,
        'total_kredit'      => 0,
        'saldo_akhir'       => $selisih_tahun_sebelumnya,
        'lr_debet'          => 0,
        'lr_kredit'         => 0,
        'debet_neraca'      => 0,
        'kredit_neraca'     => $selisih_tahun_sebelumnya
    ];
}

// Suntikkan Laba Berjalan ke Neraca
if (isset($data[$curr_year_re_code])) {
    $data[$curr_year_re_code]['kredit_neraca'] += $selisih_tahun_berjalan;
    $data[$curr_year_re_code]['saldo_akhir']   += $selisih_tahun_berjalan;
} else {
    $data[$curr_year_re_code] = [
        'account_code'      => $curr_year_re_code,
        'account_name'      => 'Laba Tahun Berjalan',
        'layer'             => 4,
        'parent_account'    => substr($curr_year_re_code, 0, 5),
        'posisi'            => 'neraca',
        'saldo_awal_debet'  => 0,
        'saldo_awal_kredit' => 0,
        'total_debet'       => 0,
        'total_kredit'      => 0,
        'saldo_akhir'       => $selisih_tahun_berjalan,
        'lr_debet'          => 0,
        'lr_kredit'         => 0,
        'debet_neraca'      => 0,
        'kredit_neraca'     => $selisih_tahun_berjalan
    ];
}

ksort($data);

// ======================================================
// AKUMULASI NILAI PARENT (LAYER 1, 2, 3) SECARA BERSIH
// ======================================================
// Reset saldo akun parent
foreach ($data as $code => &$val) {
    if ($val['layer'] < 4) {
        $val['saldo_awal_debet']  = 0;
        $val['saldo_awal_kredit'] = 0;
        $val['total_debet']       = 0;
        $val['total_kredit']      = 0;
        $val['saldo_akhir']       = 0;
        $val['lr_debet']          = 0;
        $val['lr_kredit']         = 0;
        $val['debet_neraca']      = 0;
        $val['kredit_neraca']     = 0;
    }
}
unset($val);

// Akumulasikan hanya dari child layer 4 & unmapped
foreach ($data as $p_code => &$p_val) {
    if ($p_val['layer'] >= 4) continue;

    foreach ($data as $c_code => $c_val) {
        if ($c_val['layer'] < 4) continue;

        if (match_coa_layer($p_val['layer'], $p_code, $c_code, $c_val['parent_account'])) {
            $p_val['saldo_awal_debet']  += $c_val['saldo_awal_debet'];
            $p_val['saldo_awal_kredit'] += $c_val['saldo_awal_kredit'];
            $p_val['total_debet']       += $c_val['total_debet'];
            $p_val['total_kredit']      += $c_val['total_kredit'];
            $p_val['saldo_akhir']       += $c_val['saldo_akhir'];
            $p_val['lr_debet']          += $c_val['lr_debet'];
            $p_val['lr_kredit']         += $c_val['lr_kredit'];
            $p_val['debet_neraca']      += $c_val['debet_neraca'];
            $p_val['kredit_neraca']     += $c_val['kredit_neraca'];
        }
    }
}
unset($p_val);

// Total Grand Total (Hanya dari layer 4 & unmapped)
$total = [
    'saldo_awal_debet'  => 0,
    'saldo_awal_kredit' => 0,
    'total_debet'       => 0,
    'total_kredit'      => 0,
    'saldo_akhir_debet' => 0,
    'saldo_akhir_kredit'=> 0,
    'lr_debet'          => 0,
    'lr_kredit'         => 0,
    'debet_neraca'      => 0,
    'kredit_neraca'     => 0
];

foreach ($data as $row) {
    if ($row['layer'] != 4) continue;

    $total['saldo_awal_debet']  += $row['saldo_awal_debet'];
    $total['saldo_awal_kredit'] += $row['saldo_awal_kredit'];
    $total['total_debet']       += $row['total_debet'];
    $total['total_kredit']      += $row['total_kredit'];

    $is_credit = is_credit_coa('', $row['account_code'], $row['account_name']);
    if ($is_credit) {
        $total['saldo_akhir_kredit'] += $row['saldo_akhir'];
    } else {
        $total['saldo_akhir_debet']  += $row['saldo_akhir'];
    }

    $total['lr_debet']      += $row['lr_debet'];
    $total['lr_kredit']     += $row['lr_kredit'];
    $total['debet_neraca']  += $row['debet_neraca'];
    $total['kredit_neraca'] += $row['kredit_neraca'];
}

// Laba Rugi Berjalan & Neraca
$laba_rugi_bulan_ini = $total['lr_kredit'] - $total['lr_debet'];
$selisih_neraca      = $total['debet_neraca'] - $total['kredit_neraca'];
$selisih_neraca_lr   = $selisih_neraca - $laba_rugi_bulan_ini;

$is_ns_balanced = abs($selisih_neraca_lr) < 0.01;
$closed_info    = getLatestClosedPeriod($conn);
$is_period_closed = isPeriodClosed($conn, $tanggal_cutoff_akhir);
?>

    <!-- Panel Audit Integritas Saldo -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin: 15px 0; padding:12px 18px; border-radius:6px; background: <?= $is_ns_balanced ? '#f0fdf4' : '#fef2f2' ?>; border: 1px solid <?= $is_ns_balanced ? '#bbf7d0' : '#fecaca' ?>;">
        <div>
            <span class="badge-audit <?= $is_ns_balanced ? 'badge-pass' : 'badge-fail' ?>">
                <i class="fa-solid <?= $is_ns_balanced ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                Status Neraca Saldo: <?= $is_ns_balanced ? 'BALANCE 100%' : 'TERDAPAT SELISIH (Rp ' . number_format($selisih_neraca_lr, 2) . ')' ?>
            </span>
        </div>
        <div style="font-size:13px; color:#475569;">
            <span>Tutup Buku Terakhir: <strong><?= $closed_info['tahun'] > 0 ? $bulan_arr[str_pad((string)$closed_info['bulan'], 2, '0', STR_PAD_LEFT)] . ' ' . $closed_info['tahun'] : 'Belum Pernah' ?></strong></span>
            <?php if ($is_period_closed): ?>
                <span class="badge-audit badge-pass" style="margin-left:8px;"><i class="fa-solid fa-lock"></i> Periode Terkunci</span>
            <?php else: ?>
                <span class="badge-audit badge-fail" style="margin-left:8px;"><i class="fa-solid fa-lock-open"></i> Periode Terbuka</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tabel Neraca Saldo Lajur -->
    <div style="overflow-x:auto;">
    <table id="tableNeracaSaldo">
        <thead>
            <tr>
                <th rowspan="2">Akun</th>
                <th rowspan="2" style="text-align:left;">Keterangan</th>
                <th colspan="2">Saldo Awal</th>
                <th colspan="2">Mutasi</th>
                <th colspan="2">Saldo Setelah Mutasi</th>
                <th colspan="2">Laba Rugi</th>
                <th colspan="2">Neraca</th>
            </tr>
            <tr>
                <th>Debet</th>
                <th>Kredit</th>
                <th>Debet</th>
                <th>Kredit</th>
                <th>Debet</th>
                <th>Kredit</th>
                <th>Debet</th>
                <th>Kredit</th>
                <th>Debet</th>
                <th>Kredit</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row):
            $is_c = is_credit_coa('', $row['account_code'], $row['account_name']);
            $sa_deb = ($row['saldo_awal_debet'] != 0) ? number_format($row['saldo_awal_debet'], 0) : '-';
            $sa_kre = ($row['saldo_awal_kredit'] != 0) ? number_format($row['saldo_awal_kredit'], 0) : '-';
            $mu_deb = ($row['total_debet'] != 0) ? number_format($row['total_debet'], 0) : '-';
            $mu_kre = ($row['total_kredit'] != 0) ? number_format($row['total_kredit'], 0) : '-';

            $sm_deb = (!$is_c && $row['saldo_akhir'] != 0) ? number_format($row['saldo_akhir'], 0) : '-';
            $sm_kre = ($is_c && $row['saldo_akhir'] != 0) ? number_format($row['saldo_akhir'], 0) : '-';

            $lr_deb = ($row['lr_debet'] != 0) ? number_format($row['lr_debet'], 0) : '-';
            $lr_kre = ($row['lr_kredit'] != 0) ? number_format($row['lr_kredit'], 0) : '-';

            $ne_deb = ($row['debet_neraca'] != 0) ? number_format($row['debet_neraca'], 0) : '-';
            $ne_kre = ($row['kredit_neraca'] != 0) ? number_format($row['kredit_neraca'], 0) : '-';

            $rowStyle = "";
            if ($row['layer'] == 1) $rowStyle = "font-weight:bold; background-color:#e2e8f0;";
            elseif ($row['layer'] == 2) $rowStyle = "font-weight:bold; background-color:#f1f5f9;";
            elseif ($row['layer'] == 3) $rowStyle = "font-weight:600; background-color:#f8fafc;";

            $is_unmapped = (strpos($row['account_name'], 'Akun ') === 0);
            if ($is_unmapped) {
                $rowStyle .= "color:#c2410c;";
            }
        ?>
            <tr class="layer-<?= $row['layer'] ?>" style="<?= $rowStyle ?>">
                <td style="text-align:center;"><?= htmlspecialchars($row['account_code']) ?></td>
                <td style="text-align:left; padding-left:<?= ($row['layer'] - 1) * 16 + 8 ?>px;">
                    <?= htmlspecialchars($row['account_name']) ?>
                    <?php if ($is_unmapped): ?>
                        <span style="font-size:10px; background:#ffedd5; color:#c2410c; padding:2px 6px; border-radius:4px; margin-left:4px;">Unmapped</span>
                    <?php endif; ?>
                </td>
                <td><?= $sa_deb ?></td>
                <td><?= $sa_kre ?></td>
                <td><?= $mu_deb ?></td>
                <td><?= $mu_kre ?></td>
                <td><?= $sm_deb ?></td>
                <td><?= $sm_kre ?></td>
                <td><?= $lr_deb ?></td>
                <td><?= $lr_kre ?></td>
                <td><?= $ne_deb ?></td>
                <td><?= $ne_kre ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold; background-color:#e2e8f0; border-top:2px solid #334155;">
                <td colspan="2" style="text-align:center;">TOTAL SALDO</td>
                <td><?= number_format($total['saldo_awal_debet'], 0) ?></td>
                <td><?= number_format($total['saldo_awal_kredit'], 0) ?></td>
                <td><?= number_format($total['total_debet'], 0) ?></td>
                <td><?= number_format($total['total_kredit'], 0) ?></td>
                <td><?= number_format($total['saldo_akhir_debet'], 0) ?></td>
                <td><?= number_format($total['saldo_akhir_kredit'], 0) ?></td>
                <td><?= number_format($total['lr_debet'], 0) ?></td>
                <td><?= number_format($total['lr_kredit'], 0) ?></td>
                <td><?= number_format($total['debet_neraca'], 0) ?></td>
                <td><?= number_format($total['kredit_neraca'], 0) ?></td>
            </tr>
            <tr style="font-weight:bold; background-color:#f8fafc;">
                <td colspan="8" style="text-align:center;">LABA / RUGI PERIODE BERJALAN</td>
                <td><?= ($laba_rugi_bulan_ini > 0) ? number_format($laba_rugi_bulan_ini, 0) : '-' ?></td>
                <td><?= ($laba_rugi_bulan_ini < 0) ? number_format(abs($laba_rugi_bulan_ini), 0) : '-' ?></td>
                <td>-</td>
                <td><?= ($laba_rugi_bulan_ini > 0) ? number_format($laba_rugi_bulan_ini, 0) : '-' ?></td>
            </tr>
            <tr style="font-weight:bold; background-color:#e2e8f0;">
                <td colspan="8" style="text-align:center;">TOTAL SETELAH LABA / RUGI</td>
                <td><?= number_format($total['lr_debet'] + max(0, $laba_rugi_bulan_ini), 0) ?></td>
                <td><?= number_format($total['lr_kredit'] + max(0, -$laba_rugi_bulan_ini), 0) ?></td>
                <td><?= number_format($total['debet_neraca'], 0) ?></td>
                <td><?= number_format($total['kredit_neraca'] + $laba_rugi_bulan_ini, 0) ?></td>
            </tr>
        </tfoot>
    </table>
    </div>

    <!-- Panel Tutup Buku (Closing) Periode -->
    <div style="margin-top:25px; padding:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
        <h4 style="margin:0 0 10px 0; color:#1e293b;"><i class="fa-solid fa-lock"></i> Tutup Buku (Closing) Periode</h4>
        <p style="margin:0 0 15px 0; font-size:13px; color:#64748b;">
            Menyimpan snapshot saldo akhir layer 4 ke tabel penutupan buku resmi. Perhitungan dilakukan mandiri oleh server dari histori jurnal untuk menjamin integritas pembukuan.
        </p>

        <?php if ($is_period_closed): ?>
            <div style="background:#d4edda; color:#155724; padding:12px 16px; border-radius:6px; border:1px solid #c3e6cb; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-check-circle"></i> Periode bulan <?= $bulan_arr[str_pad((string)$bulan_akhir, 2, '0', STR_PAD_LEFT)] ?> <?= $tahun_terpilih ?> telah resmi ditutup.
            </div>
        <?php else: ?>
            <form action="closing.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menutup buku periode bulan <?= $bulan_arr[str_pad((string)$bulan_akhir, 2, '0', STR_PAD_LEFT)] ?> <?= $tahun_terpilih ?>? Seluruh saldo layer 4 akan dibukukan secara permanen.');" style="display:inline-flex; gap:10px; margin:0;">
                <input type="hidden" name="bulan" value="<?= (int)$bulan_akhir ?>">
                <input type="hidden" name="tahun" value="<?= (int)$tahun_terpilih ?>">
                <button type="submit" style="background-color:#dc3545; color:white; padding:10px 18px; border-radius:6px; font-weight:bold;">
                    <i class="fa-solid fa-lock"></i> Tutup Buku Periode <?= $bulan_arr[str_pad((string)$bulan_akhir, 2, '0', STR_PAD_LEFT)] ?> <?= $tahun_terpilih ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function toggleLayer(layerNum, isVisible) {
        var rows = document.querySelectorAll('.layer-' + layerNum);
        rows.forEach(function(row) {
            row.style.display = isVisible ? '' : 'none';
        });
    }

    [1, 2, 3, 4].forEach(function(num) {
        var chk = document.getElementById('toggleLayer' + num);
        if (chk) {
            chk.addEventListener('change', function() {
                toggleLayer(num, this.checked);
            });
        }
    });
});
</script>
</body>
</html>
