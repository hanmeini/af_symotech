<?php
// export_n.php - Export Excel Neraca Posisi Keuangan PT Arni Family (Standar Masbox)
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
if (!$stmt) die("Kesalahan prepare statement: " . $conn->error);

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

// Tangkap Unmapped Accounts
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
            'account_name' => 'Akun ' . $m_code . ' (Unmapped)',
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

// Reset & Akumulasi Parent Pasca-Injeksi RE
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

// Grand Total
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

// ======================================================
// GENERATE SPREADSHEET (PHPSPREADSHEET)
// ======================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Neraca Posisi Keuangan');

// Header Dokumen
$sheet->mergeCells('A1:D1');
$sheet->setCellValue('A1', 'PT ARNI FAMILY');
$sheet->getStyle('A1')->getFont()->setSize(14)->setBold(true);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A2:D2');
$sheet->setCellValue('A2', 'NERACA (POSISI KEUANGAN)');
$sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->mergeCells('A3:D3');
$sheet->setCellValue('A3', 'Periode Kumulatif s/d ' . date("d M Y", strtotime($tanggal_cutoff_akhir)));
$sheet->getStyle('A3')->getFont()->setSize(10)->setItalic(true);
$sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table Header
$sheet->setCellValue('A5', 'Akun');
$sheet->setCellValue('B5', 'Keterangan');
$sheet->setCellValue('C5', 'Debet (Aset)');
$sheet->setCellValue('D5', 'Kredit (Kewajiban & Ekuitas)');

$headerRange = 'A5:D5';
$sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'));
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F497D');
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$rowNum = 6;
$numFmt = '#,##0;[Red]-#,##0;"-"';

foreach ($data as $r) {
    $first = substr($r['account_code'], 0, 1);
    $deb_val = ($first === '1' && $r['debet_neraca'] != 0) ? $r['debet_neraca'] : 0;
    $kre_val = (in_array($first, ['2', '3']) && $r['kredit_neraca'] != 0) ? $r['kredit_neraca'] : 0;

    $sheet->setCellValue('A' . $rowNum, $r['account_code']);
    $sheet->setCellValue('B' . $rowNum, str_repeat('   ', max(0, $r['layer'] - 1)) . $r['account_name']);
    $sheet->setCellValue('C' . $rowNum, $deb_val);
    $sheet->setCellValue('D' . $rowNum, $kre_val);

    if ($r['layer'] == 1) {
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
    } elseif ($r['layer'] == 2) {
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
    } elseif ($r['layer'] == 3) {
        $sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
    }

    $sheet->getStyle("C{$rowNum}:D{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
    $rowNum++;
}

// Total Posisi Keuangan
$sheet->mergeCells("A{$rowNum}:B{$rowNum}")->setCellValue("A{$rowNum}", 'TOTAL POSISI KEUANGAN');
$sheet->setCellValue("C{$rowNum}", $total_aset);
$sheet->setCellValue("D{$rowNum}", $total_passiva);
$sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
$sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2E8F0');
$sheet->getStyle("C{$rowNum}:D{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);
$rowNum++;

// Baris Selisih Keseimbangan
$sheet->mergeCells("A{$rowNum}:B{$rowNum}")->setCellValue("A{$rowNum}", 'SELISIH KESEIMBANGAN (ASET - PASSIVA)');
$sheet->mergeCells("C{$rowNum}:D{$rowNum}")->setCellValue("C{$rowNum}", $selisih_neraca_posisi);
$sheet->getStyle("A{$rowNum}:D{$rowNum}")->getFont()->setBold(true);
$sheet->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("C{$rowNum}")->getNumberFormat()->setFormatCode($numFmt);

// Border Semua Tabel
$sheet->getStyle("A5:D{$rowNum}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Auto-size kolom
foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Download Header
$filename = "Neraca_Posisi_Keuangan_AF_" . $tahun_terpilih . "_" . $bulan_awal . "-" . $bulan_akhir . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
