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


// Mengambil data dari form
$username = $_POST['username'];
$old_password = $_POST['old_password'];
$new_password = $_POST['new_password'];

// Melakukan query untuk memeriksa apakah username dan password lama cocok
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $username, $old_password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Jika username dan password lama cocok, update password baru
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $update_stmt->bind_param("ss", $new_password, $username);
    $update_stmt->execute();
    
    echo "Password berhasil diubah.";
} else {
    // Jika username atau password lama tidak cocok, tampilkan pesan kesalahan
    echo "Username atau password lama salah. Silakan coba lagi.";
}

$stmt->close();
$conn->close();
?>
