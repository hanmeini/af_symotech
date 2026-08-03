<?php

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

// Ambil data dari formulir
$username = trim($_POST['username']);
$password = trim($_POST['password']);
$location = trim($_POST['location']);
$jabatan = trim($_POST['jabatan']);

// Validasi apakah semua bidang terisi
if (empty($username) || empty($password) || $location == 'pilih_lokasi') {
    echo "<script>alert('Harap isi semua bidang.'); window.location.href = 'home.php';</script>";
    exit;
}

// Validasi apakah username sudah digunakan
$query = "SELECT username FROM me WHERE username = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("<script>alert('Query error: {$conn->error}'); window.location.href = 'home.php';</script>");
}
$stmt->bind_param("s", $username);
$stmt->execute();

// Gunakan bind_result dan fetch sebagai pengganti get_result
$stmt->bind_result($existingUsername);
$usernameExists = $stmt->fetch();
$stmt->close();

if ($usernameExists) {
    echo "<script>alert('Username sudah digunakan. Harap gunakan username lain.'); window.location.href = 'home.php';</script>";
    exit;
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Tambahkan pengguna baru ke database
$insert_query = "INSERT INTO me (username, password, location, jabatan) VALUES (?, ?, ?, ?)";
$insert_stmt = $conn->prepare($insert_query);
if (!$insert_stmt) {
    die("<script>alert('Query error: {$conn->error}'); window.location.href = 'home.php';</script>");
}
$insert_stmt->bind_param("ssss", $username, $hashed_password, $location, $jabatan);

if ($insert_stmt->execute()) {
    echo "<script>alert('User berhasil ditambahkan'); window.location.href = 'home.php';</script>";
} else {
    echo "<script>alert('Gagal menambahkan data: {$insert_stmt->error}'); window.location.href = 'home.php';</script>";
}

// Tutup koneksi
$conn->close();
?>
