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

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config.php';

// Ambil parameter dari URL
$bulan_awal = isset($_GET['bulan_awal']) ? (int)$_GET['bulan_awal'] : (int)date('m');
$bulan_akhir = isset($_GET['bulan_akhir']) ? (int)$_GET['bulan_akhir'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$selected_location = $_GET['location'] ?? '';
$selected_devisi = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

// Hitung tanggal cutoff berdasarkan bulan awal dan akhir
$tanggal_cutoff_awal = "$tahun-" . str_pad($bulan_awal, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

// Koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) die("Koneksi ke database pertama gagal: " . $conn->connect_error);



// Siapkan filter query
$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi = !empty($selected_devisi) ? "AND j.devisi = ?" : "";

// Query laporan
$query = "
    SELECT c.account_code, c.account_name, c.layer, c.parent_account,
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
    WHERE c.account_code NOT LIKE '1%' 
    AND c.account_code NOT LIKE '2%' 
    AND c.account_code NOT LIKE '3%'
        $filter_location
        $filter_devisi
    GROUP BY c.account_code, c.account_name, c.layer, c.parent_account
    ORDER BY c.account_code ASC
";

// Prepare the SQL statement
$stmt = $conn->prepare($query);
if (!$stmt) die("Kesalahan pada prepare statement: " . $conn->error);

// Prepare the parameter types and parameters for the prepared statement
$param_types = 'ssssssssssssss';
$params = [
    $tanggal_cutoff_awal, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_awal,
    $tanggal_cutoff_akhir, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir
];

if (!empty($selected_location)) {
    $param_types .= 's';
    $params[] = $selected_location;
}
if (!empty($selected_devisi)) {
    $param_types .= 's';
    $params[] = $selected_devisi;
}

// Bind the parameters to the statement and execute
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$stmt->bind_result(
    $account_code, $account_name, $layer, $parent_account, $saldo_awal,
    $total_debet, $total_kredit, $saldo_akhir,
    $lr_debet, $lr_kredit, $debet_neraca, $kredit_neraca
);

// Fetch the data and process it
$data = [];
while ($stmt->fetch()) {
    $first_digit = substr($account_code, 0, 1);
    $saldo_awal_debet = 0;
    $saldo_awal_kredit = 0;

    if (in_array($first_digit, ['1', '2', '3'])) {
        if ($first_digit === '1') {
            $saldo_awal_debet = max(0, (float)$saldo_awal);
        } elseif (in_array($first_digit, ['2', '3'])) {
            $saldo_awal_kredit = max(0, (float)$saldo_awal);
        }
    }

    if (in_array($first_digit, ['1', '5', '6', '7', '9'])) {
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
    } elseif (in_array($first_digit, ['2', '4', '8'])) {
        $saldo_akhir = $saldo_awal_kredit - (float)$total_debet + (float)$total_kredit;
    }

    if (in_array($first_digit, ['4', '8'])) {
        $lr_kredit = $saldo_akhir;
    } else {
        $lr_kredit = 0;
    }

    if (in_array($first_digit, ['5', '6', '7', '9'])) {
        $lr_debet = $saldo_akhir;
    } else {
        $lr_debet = 0;
    }

    $debet_neraca = $first_digit === '1' ? $saldo_akhir : 0;
    $kredit_neraca = in_array($first_digit, ['2', '3']) ? $saldo_akhir : 0;

    $data[$account_code] = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'layer' => $layer,
        'parent_account' => $parent_account,
        'saldo_awal_debet' => $saldo_awal_debet,
        'saldo_awal_kredit' => $saldo_awal_kredit,
        'total_debet' => (float)$total_debet,
        'total_kredit' => (float)$total_kredit,
        'saldo_akhir' => $saldo_akhir,
        'lr_debet' => $lr_debet,
        'lr_kredit' => $lr_kredit,
        'debet_neraca' => (float)$debet_neraca,
        'kredit_neraca' => (float)$kredit_neraca
    ];
}
ksort($data);

// Calculate parent totals
foreach ($data as $key => &$values) {
    if ($values['layer'] == 4) continue;

    foreach ($data as $child_key => $child_values) {
        $match = false;
        if ($values['layer'] == 1 && substr($child_values['account_code'], 0, 1) === substr($values['account_code'], 0, 1)) $match = true;
        if ($values['layer'] == 2 && substr($child_values['account_code'], 0, 2) === substr($values['account_code'], 0, 2)) $match = true;
        if ($values['layer'] == 3 && substr($child_values['account_code'], 0, 3) === substr($values['account_code'], 0, 3)) $match = true;

        if ($match) {
            $values['saldo_awal_debet'] += $child_values['saldo_awal_debet'];
            $values['saldo_awal_kredit'] += $child_values['saldo_awal_kredit'];
            $values['total_debet'] += $child_values['total_debet'];
            $values['total_kredit'] += $child_values['total_kredit'];
            $values['saldo_akhir'] += $child_values['saldo_akhir'];
            $values['lr_debet'] += $child_values['lr_debet'];
            $values['lr_kredit'] += $child_values['lr_kredit'];
            $values['debet_neraca'] += $child_values['debet_neraca'];
            $values['kredit_neraca'] += $child_values['kredit_neraca'];
        }
    }
}

// Calculate totals
$total = [
    'saldo_awal_debet' => 0,
    'saldo_awal_kredit' => 0,
    'total_debet' => 0,
    'total_kredit' => 0,
    'saldo_akhir' => 0,
    'lr_debet' => 0,
    'lr_kredit' => 0,
    'debet_neraca' => 0,
    'kredit_neraca' => 0
];

foreach ($data as $row) {
    if ($row['layer'] == 4) {
        $total['saldo_awal_debet'] += $row['saldo_awal_debet'];
        $total['saldo_awal_kredit'] += $row['saldo_awal_kredit'];
        $total['total_debet'] += $row['total_debet'];
        $total['total_kredit'] += $row['total_kredit'];
        $total['saldo_akhir'] += $row['saldo_akhir'];
        $total['lr_debet'] += $row['lr_debet'];
        $total['lr_kredit'] += $row['lr_kredit'];
        $total['debet_neraca'] += $row['debet_neraca'];
        $total['kredit_neraca'] += $row['kredit_neraca'];
    }
}


// Generate Excel file
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Writing headers to spreadsheet
$sheet->setCellValue('A1', 'Layer 1');
$sheet->setCellValue('B1', 'Layer 2');
$sheet->setCellValue('C1', 'Layer 3');
$sheet->setCellValue('D1', 'Layer 4');
$sheet->setCellValue('E1', 'Nama Akun');

$sheet->setCellValue('F1', 'LR Debet');
$sheet->setCellValue('G1', 'LR Kredit');


// Write each row of data to the spreadsheet
$rowNumber = 2;
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowNumber, $row['layer'] == 1 ? $row['account_code'] : '');
    $sheet->setCellValue('B' . $rowNumber, $row['layer'] == 2 ? $row['account_code'] : '');
    $sheet->setCellValue('C' . $rowNumber, $row['layer'] == 3 ? $row['account_code'] : '');
    $sheet->setCellValue('D' . $rowNumber, $row['layer'] == 4 ? $row['account_code'] : '');
    $sheet->setCellValue('E' . $rowNumber, $row['account_name']);

    $sheet->setCellValue('F' . $rowNumber, $row['lr_debet']);
    $sheet->setCellValue('G' . $rowNumber, $row['lr_kredit']);

    $rowNumber++;
}

// Add total layer
$sheet->setCellValue('A' . $rowNumber, 'Total');

$sheet->setCellValue('F' . $rowNumber, $total['lr_debet']);
$sheet->setCellValue('G' . $rowNumber, $total['lr_kredit']);

// After adding the total row
$rowNumber++;

// Calculate Laba Rugi Bulan Ini
$laba_rugi_bulan_ini = $total['lr_kredit'] - $total['lr_debet'];

// Add Laba Rugi Bulan Ini row
$sheet->setCellValue('A' . $rowNumber, 'LABA RUGI BULAN INI');
$sheet->setCellValue('B' . $rowNumber, ''); // Empty cells for layers
$sheet->setCellValue('C' . $rowNumber, '');
$sheet->setCellValue('D' . $rowNumber, '');
$sheet->setCellValue('E' . $rowNumber, ''); // Account Name

$sheet->setCellValue('F' . $rowNumber, ''); // Empty for LR Debet

$sheet->setCellValue('G' . $rowNumber, number_format($laba_rugi_bulan_ini, 0)); // Laba Rugi Bulan Ini



// After that, continue with the rest of the file generation as usual.

// Set headers for Excel download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="LR_' . $bulan_awal . '_' . $bulan_akhir . '_' . $tahun . '.xlsx"');
header('Cache-Control: max-age=0');

// Write to output
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
