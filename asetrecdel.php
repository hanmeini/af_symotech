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



// Periksa apakah parameter ID diterima
if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Pastikan ID adalah angka

    // Periksa apakah ada konfirmasi penghapusan
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Query untuk menghapus data berdasarkan ID
        $sql = "DELETE FROM recuring WHERE id = $id";
        if ($conn->query($sql) === TRUE) {
            header("Location: aset.php"); // Redirect ke halaman daftar recuring
            exit();
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        // Query untuk mendapatkan data yang akan dihapus
        $sql = "SELECT no_recuring, tanggal FROM recuring WHERE id = $id";
        $result = $conn->query($sql);

         if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $no_recuring = htmlspecialchars($row['no_recuring'], ENT_QUOTES, 'UTF-8'); // Hindari XSS
            $tanggal = htmlspecialchars($row['tanggal'], ENT_QUOTES, 'UTF-8'); // Hindari XSS
            echo "<script>
                 if (confirm('Apakah Anda yakin ingin menghapus data dengan No Recurring \"{$no_recuring}\" pada tanggal \"{$tanggal}\"?')) {
                    window.location.href = 'asetrecdel.php?id={$id}&confirm=yes';
                } else {
                    window.location.href = 'aset.php';
                }
            </script>";
        } else {
            echo "Data tidak ditemukan.";
        }
    }
} else {
    echo "ID tidak diberikan.";
}

// Tutup koneksi
$conn->close();
?>
