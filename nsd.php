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



require 'vendor/autoload.php'; // Pastikan path ini sesuai dengan lokasi Composer autoload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Memastikan data berisi array yang benar
if (empty($data)) {
    die('Data kosong!'); // Jika data kosong, hentikan script
}

// Membuat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Menambahkan header ke file Excel
$headers = [
    'Layer 1', 'Layer 2', 'Layer 3', 'Layer 4', 'Nama Akun', 
    'Saldo Awal Debet', 'Saldo Awal Kredit', 'Total Debet', 'Total Kredit', 
    'Saldo Akhir', 'LR Debet', 'LR Kredit', 'Debet Neraca', 'Kredit Neraca'
];

$sheet->fromArray($headers, NULL, 'A1'); // Memasukkan header ke baris pertama

// Fungsi untuk memastikan angka tidak dalam format notasi ilmiah
function formatNumber($number) {
    return number_format($number, 0, ',', '.'); // Memformat dengan tanda titik sebagai pemisah ribuan
}

// Memasukkan data ke dalam file Excel
$rowNumber = 2; // Mulai dari baris kedua setelah header
foreach ($data as $row) {
    $sheet->setCellValue('A' . $rowNumber, $row['account_code']);
    $sheet->setCellValue('B' . $rowNumber, $row['account_name']);
    $sheet->setCellValue('C' . $rowNumber, $row['layer']);
    $sheet->setCellValue('D' . $rowNumber, $row['parent_account']);
    $sheet->setCellValue('E' . $rowNumber, formatNumber($row['saldo_awal_debet']));
    $sheet->setCellValue('F' . $rowNumber, formatNumber($row['saldo_awal_kredit']));
    $sheet->setCellValue('G' . $rowNumber, formatNumber($row['total_debet']));
    $sheet->setCellValue('H' . $rowNumber, formatNumber($row['total_kredit']));
    $sheet->setCellValue('I' . $rowNumber, formatNumber($row['saldo_akhir']));
    $sheet->setCellValue('J' . $rowNumber, formatNumber($row['lr_debet']));
    $sheet->setCellValue('K' . $rowNumber, formatNumber($row['lr_kredit']));
    $sheet->setCellValue('L' . $rowNumber, formatNumber($row['debet_neraca']));
    $sheet->setCellValue('M' . $rowNumber, formatNumber($row['kredit_neraca']));
    $rowNumber++;
}

// Menulis ke file Excel
$writer = new Xlsx($spreadsheet);

// Mengunduh file Excel
$filename = 'Laporan_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>
