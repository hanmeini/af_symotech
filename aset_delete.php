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



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'])) {
    $deleteIds = $_POST['delete_ids'];
    
    if (!empty($deleteIds)) {
        // Mengonversi array ID menjadi string yang bisa digunakan dalam query SQL
        $idList = implode(',', array_map('intval', $deleteIds));
        
        // Query untuk menghapus data berdasarkan ID yang dipilih
        $sql = "DELETE FROM recuring WHERE id IN ($idList)";
        
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('Data berhasil dihapus!'); window.location.href='aset.php';</script>";
        } else {
            echo "<script>alert('Gagal menghapus data: " . $conn->error . "'); window.location.href='aset.php';</script>";
        }
    }
} else {
    echo "<script>alert('Tidak ada data yang dipilih!'); window.location.href='aset.php';</script>";
}

$conn->close();
?>
