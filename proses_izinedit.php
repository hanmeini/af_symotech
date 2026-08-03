<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['izinkan'])) {
    $nik = mysqli_real_escape_string($conn, $_POST['nik']);
    $tabel = mysqli_real_escape_string($conn, $_POST['tabel']);

    // Validasi nama tabel
    $allowed_tables = [
        'pendaftaran',
        'kesehatan',
        'kesehatan2',
        'sikotes',
        'dapat_job',
        'ujikom',
        'keluar_id',
         'skck',
           'asuransipra',
        'pembuatan_paspor',
          'visa',
       'penerbangan'
    ];

    if (!in_array($tabel, $allowed_tables)) {
        die("Tabel tidak valid.");
    }

    // Jalankan update
    $sql = "UPDATE `$tabel` SET edit = 'open', minta = '0' WHERE nik = '$nik'";
    if ($conn->query($sql)) {
        // Tampilkan animasi dan delay sebelum redirect
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Mengizinkan Edit</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding-top: 100px;
                    background-color: #f0f0f0;
                }
                .loader {
                    border: 8px solid #f3f3f3;
                    border-top: 8px solid #3498db;
                    border-radius: 50%;
                    width: 60px;
                    height: 60px;
                    animation: spin 2s linear infinite;
                    margin: auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .message {
                    margin-top: 20px;
                    font-size: 18px;
                    color: #333;
                }
            </style>
            <script>
                setTimeout(function() {
                    window.location.href = "izinedit.php";
                }, 1000); // 3 detik delay
            </script>
        </head>
        <body>
            <div class="loader"></div>
            <div class="message">Memproses izin edit... Mohon tunggu sebentar.</div>
        </body>
        </html>';
        exit;
    } else {
        echo "Gagal memperbarui: " . $conn->error;
    }
}
?>
