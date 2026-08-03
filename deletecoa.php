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



// Pastikan ID COA telah disediakan melalui URL
if(isset($_GET['id']) && !empty($_GET['id'])) {
    // Lakukan koneksi ke database (sesuaikan dengan pengaturan server Anda)
    

    // Jika pengguna telah mengkonfirmasi penghapusan
    if(isset($_GET['confirmed'])) {
        if ($_GET['confirmed'] === 'yes') {
            // Persiapkan pernyataan SQL untuk menghapus COA berdasarkan ID
            $sql = "DELETE FROM coa WHERE id = ?";

            // Persiapkan pernyataan prepared
            $stmt = $conn->prepare($sql);

            // Bind parameter
            $stmt->bind_param("i", $_GET['id']);

            if ($stmt->execute() === TRUE) {
                echo "COA berhasil dihapus.";
                // Menunda pengalihan ke halaman coa.php selama 2 detik
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'coa.php';
                        }, 800);
                      </script>";
            } else {
                // Jika terjadi kesalahan saat menghapus, tampilkan pesan kesalahan
                echo "Error: " . $conn->error;
            }

            // Tutup pernyataan
            $stmt->close();
        } else {
            // Jika pengguna membatalkan penghapusan, arahkan kembali ke halaman coa.php
            echo "<script>window.location.href = 'coa.php';</script>";
        }
    } else {
        // Fungsi untuk menampilkan popup konfirmasi
        function showConfirmation() {
            echo <<<HTML
            <script>
            if(confirm("Apakah Anda yakin ingin menghapus COA ini?")) {
                window.location.href = 'deletecoa.php?id={$_GET['id']}&confirmed=yes';
            } else {
                window.location.href = 'coa.php';
            }
            </script>
            HTML;
        }

        // Tampilkan popup konfirmasi penghapusan
        showConfirmation();
    }

    // Tutup koneksi
    $conn->close();
} else {
    // Jika ID tidak tersedia, tampilkan pesan kesalahan
    echo "ID COA tidak ditemukan.";
}
?>
