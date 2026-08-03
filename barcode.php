<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/vendor1/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Color\Color;

// Ambil parameter kode
$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';

if ($kode === '') {
    die("Kode tidak ditemukan!");
}

// Buat QR Code
$result = Builder::create()
    ->data($kode)
    ->encoding(new Encoding('UTF-8'))
    ->size(250)
    ->margin(10)
    ->foregroundColor(new Color(0, 0, 0))
    ->backgroundColor(new Color(255, 255, 255))
    ->build();

// Output PNG
header('Content-Type: '.$result->getMimeType());
echo $result->getString();
exit;
