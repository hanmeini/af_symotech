<?php
session_start();
require 'vendor/autoload.php'; // Pastikan PhpSpreadsheet terinstal

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak diizinkan.");
}

// Ambil data filter dari form dengan validasi
$coa = isset($_POST['coa']) ? trim($_POST['coa']) : '';
$start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$devisi = isset($_POST['devisi']) ? trim($_POST['devisi']) : '';

// Validasi input
if (empty($coa) || empty($start_date)) {
    die("COA dan Tanggal Mulai wajib diisi.");
}

require_once 'config.php';
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

// Buat koneksi ke database
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Query untuk saldo awal
$sql_saldo_awal = "
    SELECT COALESCE(SUM(debet), 0) - COALESCE(SUM(kredit), 0)
    FROM jurnal
    WHERE coa = ? AND tanggal < ?
";
$stmt_saldo_awal = $conn->prepare($sql_saldo_awal);
$stmt_saldo_awal->bind_param("ss", $coa, $start_date);
$stmt_saldo_awal->execute();
$stmt_saldo_awal->bind_result($saldo_awal);
$stmt_saldo_awal->fetch();
$stmt_saldo_awal->close();

// Query utama dengan JOIN ke tabel location
$sql = "
    SELECT jurnal.tanggal, jurnal.journal_number, jurnal.coa, jurnal.account_name,
           jurnal.debet, jurnal.kredit, jurnal.keterangan, jurnal.lampiran,
           location.nama_cabang, jurnal.devisi
    FROM jurnal
    LEFT JOIN location ON jurnal.location = location.idl
    WHERE jurnal.coa = ? AND jurnal.tanggal >= ?
";

$params = [$coa, $start_date];
$types = "ss";

if (!empty($end_date)) {
    $sql .= " AND jurnal.tanggal <= ?";
    $params[] = $end_date;
    $types .= "s";
}
if (!empty($location)) {
    $sql .= " AND location.nama_cabang LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}
if (!empty($devisi)) {
    $sql .= " AND jurnal.devisi LIKE ?";
    $params[] = "%$devisi%";
    $types .= "s";
}

$sql .= " ORDER BY jurnal.tanggal";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->bind_result($tanggal, $journal_number, $coa_result, $account_name, $debet, $kredit, $keterangan, $lampiran, $nama_cabang, $devisi);

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header kolom
$headers = ["Tanggal", "No Jurnal", "COA", "Account Name", "Debet", "Kredit", "Saldo", "Keterangan", "Lampiran", "Cabang", "Divisi"];
$sheet->fromArray($headers, NULL, 'A1');

// Styling header
$headerStyle = [
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]
];
$sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

// Auto-size kolom
foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Isi data
$row = 2;
$saldo = $saldo_awal;
$sheet->setCellValue("G$row", $saldo); // Saldo Awal
$row++;

while ($stmt->fetch()) {
    $saldo += $debet - $kredit;
    $sheet->fromArray([
        $tanggal, $journal_number, $coa_result, $account_name,
        $debet, $kredit, $saldo, $keterangan, $lampiran,
        $nama_cabang, $devisi
    ], NULL, "A$row");

    // Format angka
    $sheet->getStyle("E$row:G$row")->getNumberFormat()->setFormatCode('#,##0.00');
    $row++;
}

// Tutup statement dan koneksi
$stmt->close();
$conn->close();

// Simpan sebagai file Excel
$filename = "Ledger_" . date('YmdHis') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>