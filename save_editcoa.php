<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mulai session dengan konfigurasi aman
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

$allowed_referer_domain = "https://af.symotech.id/";

// Validasi referer
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Validasi session login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi
require_once 'config.php';

// Ambil variabel dari environment
$servername   = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username  = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password  = getenv('DB_PASS');
$database     = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

// Buat koneksi
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

// Proses jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input
    $required_fields = ['id', 'account_name', 'posisi', 'dc', 'open', 'parent_account', 'layer'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            die("Kesalahan: Field '$field' tidak ditemukan.");
        }
    }

    // Ambil data dari form
    $id             = intval($_POST['id']);
    $account_name   = trim($_POST['account_name']);
    $posisi         = trim($_POST['posisi']);
    $dc             = trim($_POST['dc']);
    $open           = trim($_POST['open']);
    $parent_account = trim($_POST['parent_account']);
    $layer          = trim($_POST['layer']);

    // Gunakan prepared statement
    $stmt = $conn->prepare("UPDATE coa SET account_name = ?, posisi = ?, dc = ?, open = ?, parent_account = ?, layer = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $account_name, $posisi, $dc, $open, $parent_account, $layer, $id);

    if ($stmt->execute()) {
        echo "Data COA berhasil diubah.";
        echo "<script>
                setTimeout(function() {
                    window.location.href = 'coa.php';
                }, 800);
              </script>";
    } else {
        echo "Kesalahan saat memperbarui data: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Tidak ada data yang disubmit.";
}
?>