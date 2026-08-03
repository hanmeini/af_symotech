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


?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Scan QR Aset</title>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f9ff;
      margin: 0;
      padding: 20px;
      color: #333;
    }
    .container {
      max-width: 600px;
      margin: auto;
      padding: 20px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    #reader {
      width: 100%;
      margin-bottom: 20px;
    }
    h2 {
      color: #003366;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Scan QR Code Aset</h2>
    <div id="reader"></div>
    <p>Arahkan kamera ke QR Code untuk melihat detail aset.</p>
  </div>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
        // Setelah berhasil scan, arahkan ke asetcekdetail.php
        window.location.href = "asetcekdetail.php?kode=" + encodeURIComponent(decodedText);
    }

    function onScanFailure(error) {
        // Optional: console log jika scan gagal
        console.warn(`Scan gagal: ${error}`);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        {
            fps: 10,
            qrbox: 250,
            rememberLastUsedCamera: true,
            showTorchButtonIfSupported: true
        },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
  </script>
</body>
</html>
