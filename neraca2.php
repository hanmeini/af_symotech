<?php
// neraca2.php - Neraca Posisi Keuangan PT Arni Family (Standar Masbox)
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
        error_log("Referer warning di neraca2.php: " . $_SERVER['HTTP_REFERER']);
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

$bulan_awal = $_GET['bulan_awal'] ?? date('m');
$bulan_akhir = $_GET['bulan_akhir'] ?? $bulan_awal;
$tahun_terpilih = $_GET['tahun'] ?? date('Y');
$loc_terpilih = $_GET['location'] ?? '';
$dev_terpilih = $_GET['devisi'] ?? '';

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
    <title>Neraca Posisi Keuangan - PT Arni Family</title>
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
    <h2>NERACA (Posisi Keuangan)</h2>

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
            <a href="export_n.php?bulan_awal=<?= urlencode($bulan_awal) ?>&bulan_akhir=<?= urlencode($bulan_akhir) ?>&tahun=<?= urlencode($tahun_terpilih) ?>&location=<?= urlencode($loc_terpilih) ?>&devisi=<?= urlencode($dev_terpilih) ?>" target="_blank">
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

echo "<p style='margin: 15px 0 5px 0; color:#475569; font-size:14px;'><strong>Periode Posisi Keuangan:</strong> Kumulatif s/d " . date("d M Y", strtotime($tanggal_cutoff_akhir)) . "</p>";

$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi   = !empty($selected_devisi) ? "AND j.devisi = ?" : "";

// Query Akun Neraca (Kepala 1, 2, 3)
$query = "
    SELECT c.account_code, c.account_name, c.layer, c.parent_account, COALESCE(c.posisi, 'neraca') AS posisi,
        COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
        COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir
    FROM coa c
    LEFT JOIN jurnal j ON c.account_code = j.coa
     AND j.journal_number IS NOT NULL
    WHERE (
        c.account_code LIKE '1%'
        OR c.account_code LIKE '2%'
        OR c.account_code LIKE '3%'
    )
    $filter_location
    $filter_devisi
    GROUP BY c.account_code, c.account_name, c.layer, c.parent_account, c.posisi
    HAVING NOT (
        c.layer = 4
        AND saldo_akhir BETWEEN -0.0001 AND 0.0001
        AND c.account_code NOT IN ('31.02.01.001', '31.03.01.001', '3121.00', '3122.00', '3-1103', '3-1104', '32001', '32002')
    )
    ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) die("Kesalahan pada prepare statement: " . $conn->error);

$param_types = 'ssssss';
$params = [
    $tanggal_cutoff_awal,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_akhir
];

if (!empty($selected_location)) { $param_types .= 's'; $params[] = $selected_location; }
if (!empty($selected_devisi))   { $param_types .= 's'; $params[] = $selected_devisi; }

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$stmt->bind_result(
    $account_code, $account_name, $layer, $parent_account, $posisi,
    $saldo_awal, $total_debet, $total_kredit, $saldo_akhir
);

$data = [];
while ($stmt->fetch()) {
    $first_digit = substr($account_code, 0, 1);
    $is_credit   = is_credit_coa('', $account_code, $account_name);
    $is_contra_asset = (
        strpos($account_code, '162') === 0 || 
        strpos($account_code, '1505') === 0 || 
        strpos($account_code, '122') === 0 || 
        strpos($account_code, '1-150') === 0 || 
        strpos(strtoupper($account_name), 'AKUMULASI') !== false ||
        strpos(strtoupper($account_name), 'AKUM.') !== false
    );

    $saldo_awal_debet  = 0;
    $saldo_awal_kredit = 0;

    if ($is_contra_asset) {
        $saldo_awal_debet = (float)$saldo_awal;
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
        $debet_neraca = $saldo_akhir;
        $kredit_neraca = 0;
    } elseif ($is_credit) {
        $saldo_awal_kredit = (float)-$saldo_awal;
        $saldo_akhir = $saldo_awal_kredit - (float)$total_debet + (float)$total_kredit;
        $kredit_neraca = $saldo_akhir;
        $debet_neraca = 0;
    } else {
        $saldo_awal_debet = (float)$saldo_awal;
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
        $debet_neraca = $saldo_akhir;
        $kredit_neraca = 0;
    }

    $data[$account_code] = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'layer' => (int)$layer,
        'parent_account' => $parent_account,
        'saldo_awal_debet' => (float)$saldo_awal_debet,
        'saldo_awal_kredit' => (float)$saldo_awal_kredit,
        'total_debet' => (float)$total_debet,
        'total_kredit' => (float)$total_kredit,
        'saldo_akhir' => (float)$saldo_akhir,
        'lr_debet' => 0,
        'lr_kredit' => 0,
        'debet_neraca' => (float)$debet_neraca,
        'kredit_neraca' => (float)$kredit_neraca,
        'posisi' => $posisi
    ];
}
$stmt->close();

// Tangkap transaksi akun belum terdaftar di tabel COA (kepala 1, 2, 3)
$sql_missing = "
    SELECT j.coa,
        COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
        COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir
    FROM jurnal j
    LEFT JOIN coa c ON c.account_code = j.coa
    WHERE j.journal_number IS NOT NULL
      AND c.account_code IS NULL
      AND (j.coa LIKE '1%' OR j.coa LIKE '2%' OR j.coa LIKE '3%')
      $filter_location
      $filter_devisi
    GROUP BY j.coa
    HAVING NOT (saldo_akhir BETWEEN -0.0001 AND 0.0001)
";

$stmt_missing = $conn->prepare($sql_missing);
if ($stmt_missing) {
    $stmt_missing->bind_param($param_types, ...$params);
    $stmt_missing->execute();
    $res_missing = $stmt_missing->get_result();
    while ($row_m = $res_missing->fetch_assoc()) {
        $m_code = $row_m['coa'];
        $first_digit = substr($m_code, 0, 1);
        $is_credit   = is_credit_coa('', $m_code, '');

        $saldo_awal_debet  = 0;
        $saldo_awal_kredit = 0;

        if ($is_credit) {
            $saldo_awal_kredit = (float)-$row_m['saldo_awal'];
            $saldo_akhir = $saldo_awal_kredit - (float)$row_m['total_debet'] + (float)$row_m['total_kredit'];
            $kredit_neraca = $saldo_akhir;
            $debet_neraca = 0;
        } else {
            $saldo_awal_debet = (float)$row_m['saldo_awal'];
            $saldo_akhir = $saldo_awal_debet + (float)$row_m['total_debet'] - (float)$row_m['total_kredit'];
            $debet_neraca = $saldo_akhir;
            $kredit_neraca = 0;
        }

        $data[$m_code] = [
            'account_code' => $m_code,
            'account_name' => 'Akun ' . $m_code,
            'layer' => 4,
            'parent_account' => substr($m_code, 0, 5),
            'saldo_awal_debet' => (float)$saldo_awal_debet,
            'saldo_awal_kredit' => (float)$saldo_awal_kredit,
            'total_debet' => (float)$row_m['total_debet'],
            'total_kredit' => (float)$row_m['total_kredit'],
            'saldo_akhir' => (float)$saldo_akhir,
            'lr_debet' => 0,
            'lr_kredit' => 0,
            'debet_neraca' => (float)$debet_neraca,
            'kredit_neraca' => (float)$kredit_neraca,
            'posisi' => 'neraca'
        ];
    }
    $stmt_missing->close();
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
// AKUMULASI NILAI PARENT (LAYER 1, 2, 3) PASCA INJEKSI RE
// ======================================================
foreach ($data as $code => &$val) {
    if ($val['layer'] < 4) {
        $val['saldo_awal_debet']  = 0;
        $val['saldo_awal_kredit'] = 0;
        $val['total_debet']       = 0;
        $val['total_kredit']      = 0;
        $val['saldo_akhir']       = 0;
        $val['debet_neraca']      = 0;
        $val['kredit_neraca']     = 0;
    }
}
unset($val);

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
            $p_val['debet_neraca']      += $c_val['debet_neraca'];
            $p_val['kredit_neraca']     += $c_val['kredit_neraca'];
        }
    }
}
unset($p_val);

// Grand Total Neraca (hanya leaf layer 4 & unmapped)
$total_aset = 0;
$total_kewajiban = 0;
$total_ekuitas = 0;

foreach ($data as $row) {
    if ($row['layer'] != 4) continue;
    $first = substr($row['account_code'], 0, 1);

    if ($first === '1') {
        $total_aset += $row['debet_neraca'];
    } elseif ($first === '2') {
        $total_kewajiban += $row['kredit_neraca'];
    } elseif ($first === '3') {
        $total_ekuitas += $row['kredit_neraca'];
    }
}

$total_passiva = $total_kewajiban + $total_ekuitas;
$selisih_neraca_posisi = $total_aset - $total_passiva;
$is_balance = abs($selisih_neraca_posisi) < 0.01;

// Audit Laba Bersih P&L Riil YTD (Kepala 4 s/d 9)
$sql_pnl = "
    SELECT
        COALESCE(SUM(CASE WHEN j.coa LIKE '4%' OR j.coa LIKE '8%' THEN (j.kredit - j.debet) ELSE 0 END), 0) AS pendapatan,
        COALESCE(SUM(CASE WHEN j.coa LIKE '5%' OR j.coa LIKE '6%' OR j.coa LIKE '7%' OR j.coa LIKE '9%' THEN (j.debet - j.kredit) ELSE 0 END), 0) AS beban
    FROM jurnal j
    WHERE j.journal_number IS NOT NULL
      AND j.tanggal BETWEEN ? AND ?
";
$stmt_pnl = $conn->prepare($sql_pnl);
$stmt_pnl->bind_param("ss", $tahun_awal, $tanggal_cutoff_akhir);
$stmt_pnl->execute();
$res_pnl = $stmt_pnl->get_result()->fetch_assoc();
$laba_bersih_pnl_ytd = (float)$res_pnl['pendapatan'] - (float)$res_pnl['beban'];
$stmt_pnl->close();

$diff_audit_lr = $laba_bersih_pnl_ytd - $selisih_tahun_berjalan;
$is_pnl_synced = abs($diff_audit_lr) < 0.01;
?>

    <!-- Panel Audit Integritas Posisi Keuangan -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin: 15px 0; padding:12px 18px; border-radius:6px; background: <?= $is_balance ? '#f0fdf4' : '#fef2f2' ?>; border: 1px solid <?= $is_balance ? '#bbf7d0' : '#fecaca' ?>;">
        <div>
            <span class="badge-audit <?= $is_balance ? 'badge-pass' : 'badge-fail' ?>">
                <i class="fa-solid <?= $is_balance ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                Status Keseimbangan Neraca: <?= $is_balance ? 'BALANCE 100%' : 'TERDAPAT SELISIH (Rp ' . number_format($selisih_neraca_posisi, 2) . ')' ?>
            </span>
            <span class="badge-audit <?= $is_pnl_synced ? 'badge-pass' : 'badge-fail' ?>" style="margin-left:8px;">
                <i class="fa-solid <?= $is_pnl_synced ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                Integritas P&amp;L vs RE: <?= $is_pnl_synced ? 'SINKRON' : 'SELISIH (Rp ' . number_format($diff_audit_lr, 2) . ')' ?>
            </span>
        </div>
        <div style="font-size:13px; color:#475569;">
            <span>Total Aset: <strong>Rp <?= number_format($total_aset, 0) ?></strong></span> |
            <span>Kewajiban + Ekuitas: <strong>Rp <?= number_format($total_passiva, 0) ?></strong></span>
        </div>
    </div>

    <!-- Tabel Neraca Posisi Keuangan -->
    <div style="overflow-x:auto;">
    <table id="tableNeraca">
        <thead>
            <tr>
                <th style="width:140px;">Akun</th>
                <th style="text-align:left;">Keterangan</th>
                <th style="width:200px;">Debet (Aset)</th>
                <th style="width:200px;">Kredit (Kewajiban &amp; Ekuitas)</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row):
            $first = substr($row['account_code'], 0, 1);
            $is_c  = is_credit_coa('', $row['account_code'], $row['account_name']);

            $deb_val = ($first === '1' && $row['debet_neraca'] != 0) ? number_format($row['debet_neraca'], 0) : '-';
            $kre_val = (in_array($first, ['2', '3']) && $row['kredit_neraca'] != 0) ? number_format($row['kredit_neraca'], 0) : '-';

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
                <td style="text-align:left; padding-left:<?= ($row['layer'] - 1) * 20 + 8 ?>px;">
                    <?= htmlspecialchars($row['account_name']) ?>
                    <?php if ($is_unmapped): ?>
                        <span style="font-size:10px; background:#ffedd5; color:#c2410c; padding:2px 6px; border-radius:4px; margin-left:4px;">Unmapped</span>
                    <?php endif; ?>
                </td>
                <td><?= $deb_val ?></td>
                <td><?= $kre_val ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:bold; background-color:#e2e8f0; border-top:2px solid #334155;">
                <td colspan="2" style="text-align:center;">TOTAL POSISI KEUANGAN</td>
                <td><?= number_format($total_aset, 0) ?></td>
                <td><?= number_format($total_passiva, 0) ?></td>
            </tr>
            <tr style="font-weight:bold; background-color: <?= $is_balance ? '#f0fdf4' : '#fef2f2' ?>;">
                <td colspan="2" style="text-align:center;">SELISIH KESEIMBANGAN (ASET - PASSIVA)</td>
                <td colspan="2" style="text-align:center; color: <?= $is_balance ? '#155724' : '#721c24' ?>;">
                    Rp <?= number_format($selisih_neraca_posisi, 2) ?>
                    <?php if ($is_balance): ?>
                        <span style="font-size:12px; font-weight:normal; margin-left:6px;"><i class="fa-solid fa-circle-check"></i> Sempurna</span>
                    <?php else: ?>
                        <span style="font-size:12px; font-weight:normal; margin-left:6px;"><i class="fa-solid fa-circle-xmark"></i> Tidak Seimbang</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tfoot>
    </table>
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
