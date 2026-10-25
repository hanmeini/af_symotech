<?php
// export_ns.php - Export Excel Neraca Saldo Lajur PT Arni Family (Standar Masbox)
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

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_once 'config.php';
require_once 'functions.php';

$bulan_awal     = isset($_GET['bulan_awal']) ? $_GET['bulan_awal'] : date('m');
$bulan_akhir    = isset($_GET['bulan_akhir']) ? $_GET['bulan_akhir'] : $bulan_awal;
$tahun_terpilih = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$selected_location = $_GET['location'] ?? '';
$selected_devisi   = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

$tanggal_cutoff_awal  = "$tahun_terpilih-" . str_pad($bulan_awal, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun_terpilih-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

$servername  = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$database    = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Koneksi ke database gagal: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi   = !empty($selected_devisi) ? "AND j.devisi = ?" : "";

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
if (!$stmt) die("Kesalahan prepare statement: " . $conn->error);

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

    if ($posisi === 'neraca' || in_array($first_digit, ['1','2','3'])) {
        if ($is_credit) {
            $saldo_awal_kredit = (float)-$saldo_awal;
        } else {
            $saldo_awal_debet  = (float)$saldo_awal;
        }
    }

    if ($is_credit) {
        $saldo_akhir = $saldo_awal_kredit - (float)$total_debet + (float)$total_kredit;
    } else {
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
    }

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

// Tangkap Unmapped Accounts
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
            'account_name'      => 'Akun ' . $u_code . ' (Unmapped)',
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

// Suntikan Laba Ditahan Dinamis
$tahun_awal = $tahun_terpilih . '-01-01';

$sql_prev = "
    SELECT
        COALESCE(SUM(CASE WHEN j.coa LIKE '1%' THEN (j.debet - j.kredit) ELSE 0 END), 0) AS aset,
        COALESCE(SUM(CASE WHEN j.coa LIKE '2%' OR j.coa LIKE '3%' THEN (j.kredit - j.debet) ELSE 0 END), 0) AS kewajiban_modal
    FROM jurnal j
    WHERE j.journal_number IS NOT NULL AND j.tanggal < ?
";
$stmt_prev = $conn->prepare($sql_prev);
$stmt_prev->bind_param("s", $tahun_awal);
$stmt_prev->execute();
$res_prev = $stmt_prev->get_result()->fetch_assoc();
$selisih_tahun_sebelumnya = (float)$res_prev['aset'] - (float)$res_prev['kewajiban_modal'];
$stmt_prev->close();

$sql_curr = "
    SELECT
        COALESCE(SUM(CASE WHEN j.coa LIKE '1%' THEN (j.debet - j.kredit) ELSE 0 END), 0) AS aset,
        COALESCE(SUM(CASE WHEN j.coa LIKE '2%' OR j.coa LIKE '3%' THEN (j.kredit - j.debet) ELSE 0 END), 0) AS kewajiban_modal
    FROM jurnal j
    WHERE j.journal_number IS NOT NULL AND j.tanggal BETWEEN ? AND ?
";
$stmt_curr = $conn->prepare($sql_curr);
$stmt_curr->bind_param("ss", $tahun_awal, $tanggal_cutoff_akhir);
$stmt_curr->execute();
$res_curr = $stmt_curr->get_result()->fetch_assoc();
$selisih_tahun_berjalan = (float)$res_curr['aset'] - (float)$res_curr['kewajiban_modal'];
$stmt_curr->close();

$re_accounts = find_re_accounts($data);
$prev_year_re_code = $re_accounts['prev'];
$curr_year_re_code = $re_accounts['curr'];

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

// Reset & Akumulasi Parent
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

// Grand Total
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

$laba_rugi_bulan_ini = $total['lr_kredit'] - $total['lr_debet'];

// ======================================================
// GENERATE SPREADSHEET (PHPSPREADSHEET)
// ======================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Neraca Saldo Lajur');

// Header Dokumen
$sheet->mergeCells('A1:L1');
$sheet->setCellValue('A1', 'PT ARNI FAMILY');
$sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:L2');
$sheet->setCellValue('A2', 'NERACA SALDO (LAJUR)');
$sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A3:L3');
$sheet->setCellValue('A3', 'Periode: ' . date("d M Y", strtotime($tanggal_cutoff_awal)) . ' s/d ' . date("d M Y", strtotime($tanggal_cutoff_akhir)));
$sheet->getStyle('A3')->getFont()->setSize(10)->setItalic(true);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table Header
$sheet->mergeCells('A5:A6')->setCellValue('A5', 'Akun');
$sheet->mergeCells('B5:B6')->setCellValue('B5', 'Keterangan');
$sheet->mergeCells('C5:D5')->setCellValue('C5', 'Saldo Awal');
$sheet->setCellValue('C6', 'Debet')->setCellValue('D6', 'Kredit');
$sheet->mergeCells('E5:F5')->setCellValue('E5', 'Mutasi');
$sheet->setCellValue('E6', 'Debet')->setCellValue('F6', 'Kredit');
$sheet->mergeCells('G5:H5')->setCellValue('G5', 'Saldo Setelah Mutasi');
$sheet->setCellValue('G6', 'Debet')->setCellValue('H6', 'Kredit');
$sheet->mergeCells('I5:J5')->setCellValue('I5', 'Laba Rugi');
$sheet->setCellValue('I6', 'Debet')->setCellValue('J6', 'Kredit');
$sheet->mergeCells('K5:L5')->setCellValue('K5', 'Neraca');
$sheet->setCellValue('K6', 'Debet')->setCellValue('L6', 'Kredit');

$headerRange = 'A5:L6';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F497D');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$rowNum = 7;
$numFmt = '#,##0;[Red]-#,##0;"-"';

foreach ($data as $r) {
    $is_c = is_credit_coa('', $r['account_code'], $r['account_name']);

    $sheet->setCellValue('A' . $rowNum, $r['account_code']);
    $sheet->setCellValue('B' . $rowNum, str_repeat('   ', max(0, $r['layer'] - 1)) . $r['account_name']);

    $sheet->setCellValue('C' . $rowNum, $r['saldo_awal_debet'] ?: 0);
    $sheet->setCellValue('D' . $rowNum, $r['saldo_awal_kredit'] ?: 0);
    $sheet->setCellValue('E' . $rowNum, $r['total_debet'] ?: 0);
    $sheet->setCellValue('F' . $rowNum, $r['total_kredit'] ?: 0);

    $sheet->setCellValue('G' . $rowNum, (!$is_c && $r['saldo_akhir'] != 0) ? $r['saldo_akhir'] : 0);
    $sheet->setCellValue('H' . $rowNum, ($is_c && $r['saldo_akhir'] != 0) ? $r['saldo_akhir'] : 0);

    $sheet->setCellValue('I' . $rowNum, $r['lr_debet'] ?: 0);
    $sheet->setCellValue('J' . $rowNum, $r['lr_kredit'] ?: 0);

    $sheet->setCellValue('K' . $rowNum, $r['debet_neraca'] ?: 0);
    $sheet->setCellValue('L' . $rowNum, $r['kredit_neraca'] ?: 0);

    // Styling baris
    if ($r['layer'] == 1) {
        $sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    } elseif ($r['layer'] == 2) {
        $sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
    } elseif ($r['layer'] == 3) {
        $sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
    }

    $sheet->getStyle("C{$rowNum}:L{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
    $rowNum++;
}

// Baris Total Saldo
$sheet->mergeCells("A{$rowNum}:B{$rowNum}")->setCellValue("A{$rowNum}", 'TOTAL SALDO');
$sheet->setCellValue("C{$rowNum}", $total['saldo_awal_debet']);
$sheet->setCellValue("D{$rowNum}", $total['saldo_awal_kredit']);
$sheet->setCellValue("E{$rowNum}", $total['total_debet']);
$sheet->setCellValue("F{$rowNum}", $total['total_kredit']);
$sheet->setCellValue("G{$rowNum}", $total['saldo_akhir_debet']);
$sheet->setCellValue("H{$rowNum}", $total['saldo_akhir_kredit']);
$sheet->setCellValue("I{$rowNum}", $total['lr_debet']);
$sheet->setCellValue("J{$rowNum}", $total['lr_kredit']);
$sheet->setCellValue("K{$rowNum}", $total['debet_neraca']);
$sheet->setCellValue("L{$rowNum}", $total['kredit_neraca']);

$sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
$sheet->getStyle("C{$rowNum}:L{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
$rowNum++;

// Baris Laba / Rugi Berjalan
$sheet->mergeCells("A{$rowNum}:H{$rowNum}")->setCellValue("A{$rowNum}", 'LABA / RUGI PERIODE BERJALAN');
$sheet->setCellValue("I{$rowNum}", ($laba_rugi_bulan_ini > 0) ? $laba_rugi_bulan_ini : 0);
$sheet->setCellValue("J{$rowNum}", ($laba_rugi_bulan_ini < 0) ? abs($laba_rugi_bulan_ini) : 0);
$sheet->setCellValue("K{$rowNum}", 0);
$sheet->setCellValue("L{$rowNum}", ($laba_rugi_bulan_ini > 0) ? $laba_rugi_bulan_ini : 0);

$sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
$sheet->getStyle("I{$rowNum}:L{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
$rowNum++;

// Baris Total Setelah Laba / Rugi
$sheet->mergeCells("A{$rowNum}:H{$rowNum}")->setCellValue("A{$rowNum}", 'TOTAL SETELAH LABA / RUGI');
$sheet->setCellValue("I{$rowNum}", $total['lr_debet'] + max(0, $laba_rugi_bulan_ini));
$sheet->setCellValue("J{$rowNum}", $total['lr_kredit'] + max(0, -$laba_rugi_bulan_ini));
$sheet->setCellValue("K{$rowNum}", $total['debet_neraca']);
$sheet->setCellValue("L{$rowNum}", $total['kredit_neraca'] + $laba_rugi_bulan_ini);

$sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowNum}:L{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
$sheet->getStyle("I{$rowNum}:L{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);

// Border Semua Tabel
$sheet->getStyle("A5:L{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size kolom
foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Download Header
$filename = "Neraca_Saldo_Lajur_AF_" . $tahun_terpilih . "_" . $bulan_awal . "-" . $bulan_akhir . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
