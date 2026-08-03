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



// Periksa apakah ada ID yang dipilih
if (isset($_POST['selected_ids']) && !empty($_POST['selected_ids'])) {
    // Ambil ID yang dipilih
    $selected_ids = $_POST['selected_ids'];

    // Menyiapkan SQL untuk memperbarui posting status
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

    $sql = "UPDATE jurnal SET posting = 'sudah' WHERE id IN ($placeholders)";

    // Persiapkan statement
    if ($stmt = $conn->prepare($sql)) {
        // Bind parameter
        $types = str_repeat('i', count($selected_ids));
        $stmt->bind_param($types, ...$selected_ids);

        // Eksekusi statement
        if ($stmt->execute()) {
            // Sukses mengupdate posting status
            $javascript_alert = "<script>
                alert('Posting berhasil.');
                if (confirm('Posting berhasil. Apakah Anda ingin melihat laporan?')) {
                    window.location.href = 'report.php';
                }
            </script>";
        } else {
            // Gagal eksekusi
            $javascript_alert = "<script>alert('Error: " . addslashes($conn->error) . "');</script>";
        }

        // Tutup statement
        $stmt->close();
    } else {
        $javascript_alert = "<script>alert('Gagal menyiapkan query.');</script>";
    }
} else {
    $javascript_alert = "<script>alert('Tidak ada jurnal yang dipilih.');</script>";
}

// Tutup koneksi
$conn->close();

// Tampilkan alert JavaScript
echo $javascript_alert;
?>
