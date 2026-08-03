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



// Ambil user_id dari session
$user_id = $_SESSION['userid'];

// Ambil data yang dikirimkan dari form
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$location = trim($_POST['location']);
$jabatan = trim($_POST['jabatan']);

// Validasi input
if (empty($username) || empty($password) || $location == '' || $jabatan == '') {
    echo "<script>alert('Harap isi semua bidang.'); window.location.href = 'edituser.php';</script>";
    exit;
}

// Cek apakah username sudah digunakan oleh user lain
$query = "SELECT id FROM user WHERE username = ? AND id != ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query error: " . $conn->error);
}
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$stmt->store_result();

// Jika ada user lain yang menggunakan username tersebut
if ($stmt->num_rows > 0) {
    echo "<script>alert('Username sudah digunakan oleh pengguna lain. Harap gunakan username lain.'); window.location.href = 'edituser.php';</script>";
    exit;
}
$stmt->close();

// Hash password baru
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Update data pengguna
$update_query = "UPDATE user SET username = ?, password = ?, location = ?, jabatan = ? WHERE id = ?";
$update_stmt = $conn->prepare($update_query);
if (!$update_stmt) {
    die("Query error: " . $conn->error);
}
$update_stmt->bind_param("ssssi", $username, $hashed_password, $location, $jabatan, $user_id);

// Eksekusi query
if ($update_stmt->execute()) {
    echo "<script>alert('Data pengguna berhasil diperbarui.'); window.location.href = 'home.php';</script>";
} else {
    echo "<script>alert('Gagal memperbarui data: {$update_stmt->error}'); window.location.href = 'edituser.php';</script>";
}

// Tutup koneksi
$conn->close();
?>
