<?php

require 'vendor/autoload.php'; // Pastikan path ini benar sesuai struktur file Anda
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Load konfigurasi dari config.php
require_once 'config.php';



// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}



use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Koneksi Database

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Membuat Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data BDM');

// Header Kolom
$headers = ['No', 'Kode', 'Nama', 'Tanggal', 'Lokasi', 'Devisi', 'Beli', 'Akum Susut', 'Nilai Buku', 'No Recuring'];
$sheet->fromArray($headers, null, 'A1');

// Mengambil Data dari Database
$sql = "SELECT * FROM bdm ORDER BY id";
$result = $conn->query($sql);
$rowNum = 2;
$no = 1;

while ($row = $result->fetch_assoc()) {
    $kode = $row['kode'];
    
    // Hitung total penyusutan
    $stmt = $conn->prepare("SELECT SUM(susut) FROM susut WHERE kode = ?");
    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $stmt->bind_result($total_susut);
    $stmt->fetch();
    $stmt->close();
    $total_susut = $total_susut ?? 0;
    
    $nilai_buku = $row['beli'] - $total_susut;

    // Isi Data ke Excel
    $sheet->setCellValue('A' . $rowNum, $no++);
    $sheet->setCellValue('B' . $rowNum, $row['kode']);
    $sheet->setCellValue('C' . $rowNum, $row['nama']);
    $sheet->setCellValue('D' . $rowNum, $row['tanggal']);
    $sheet->setCellValue('E' . $rowNum, $row['location']);
    $sheet->setCellValue('F' . $rowNum, $row['devisi']);
    $sheet->setCellValue('G' . $rowNum, $row['beli']);
    $sheet->setCellValue('H' . $rowNum, $total_susut);
    $sheet->setCellValue('I' . $rowNum, $nilai_buku);
    $sheet->setCellValue('J' . $rowNum, $row['recuring']);
    $rowNum++;
}

// Tutup Koneksi
$conn->close();

// Atur Header untuk Download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Data_BDM.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();