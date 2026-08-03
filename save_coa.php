<?php
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

$allowed_referer_domain = "https://af.symotech.id/";

// Validasi referer
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Validasi login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load config
require_once 'config.php';

// Ambil variabel dari environment
$servername  = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database    = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



// Koneksi
$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}



// Tangkap data dari POST
$account_code    = $_POST['account_code'];
$account_name    = $_POST['account_name'];
$posisi          = $_POST['posisi'];
$dc              = $_POST['dc'];
$open            = $_POST['open'];
$parent_account  = $_POST['parent_account'];
$layer           = $_POST['layer'];

// Insert ke database
$sql = "INSERT INTO coa (account_code, account_name, posisi, dc, open, parent_account, layer)
        VALUES ('$account_code', '$account_name', '$posisi', '$dc', '$open', '$parent_account', '$layer')";

if ($conn->query($sql) === TRUE) {
    echo "Data COA berhasil disimpan.";
    echo "<script>
            setTimeout(function() {
                window.location.href = 'coa.php';
            }, 800);
          </script>";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
