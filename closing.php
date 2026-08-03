<?php
// Menampilkan error jika ada
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Mulai session dengan konfigurasi keamanan tambahan
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

// Validasi referer untuk keamanan
$allowed_referer_domain = "https://af.symotech.id/";
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Koneksi ke database
require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

try {
    $conn = new mysqli($servername, $db_username, $db_password, $database);
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("<p>Error: " . $e->getMessage() . "</p>");
}

// Proses closing hanya jika request method adalah POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<script>
        if (!confirm('Sudahkan membuat rekonsiliasi bank USD dengan laba/rugi selisih kurs?')) {
            window.location.href = 'neraca_saldo.php';
        }
    </script>";
    
    // Ambil data dari form
    $bulan = isset($_POST['bulan']) ? (int) $_POST['bulan'] : 0;
    $tahun = isset($_POST['tahun']) ? (int) $_POST['tahun'] : 0;
    $coa_list = $_POST['coa'] ?? [];
    $saldo_akhir_list = $_POST['saldo_akhir'] ?? [];

    // Validasi input
    if (empty($bulan) || empty($tahun) || empty($coa_list) || empty($saldo_akhir_list)) {
        echo "<script>alert('Kesalahan: Data tidak lengkap. Harap periksa kembali!'); window.location.href='neraca_saldo.php';</script>";
        exit();
    }

    // 🔍 Cek data recurring yang belum diproses
    $cekRecurring = $conn->prepare("SELECT COUNT(*) as jumlah FROM recuring WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (ab = '')");
    if (!$cekRecurring) {
        echo "<script>alert('Error dalam query cek recurring!'); window.location.href='neraca_saldo.php';</script>";
        exit();
    }
    $cekRecurring->bind_param("ii", $bulan, $tahun);
    $cekRecurring->execute();
    $cekRecurring->bind_result($jumlahRecurring);
    $cekRecurring->fetch();
    $cekRecurring->close();

    if ($jumlahRecurring > 0) {
        echo "<script>alert('Masih ada data recurring yang belum diproses. Closing dibatalkan.'); window.location.href='neraca_saldo.php';</script>";
        exit();
    }

    // 🔍 Cek apakah data sudah pernah ditutup
    $cekClosing = $conn->prepare("SELECT COUNT(*) as jumlah FROM close WHERE bulan = ? AND tahun = ?");
    if (!$cekClosing) {
        echo "<script>alert('Error dalam query cek closing!'); window.location.href='neraca_saldo.php';</script>";
        exit();
    }
    $cekClosing->bind_param("ii", $bulan, $tahun);
    $cekClosing->execute();
    $cekClosing->bind_result($jumlahClosing);
    $cekClosing->fetch();
    $cekClosing->close();

    if ($jumlahClosing > 0) {
        echo "<script>alert('Data untuk bulan dan tahun ini sudah ditutup sebelumnya. Closing dibatalkan.'); window.location.href='neraca_saldo.php';</script>";
        exit();
    }

    // 🚀 Jika semua pengecekan lolos, mulai proses closing
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO close (bulan, tahun, coa, saldo_akhir) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Query insert gagal: " . $conn->error);
        }

        foreach ($coa_list as $index => $coa) {
            $saldo_akhir = (float) $saldo_akhir_list[$index];
            $stmt->bind_param("iisd", $bulan, $tahun, $coa, $saldo_akhir);
            $stmt->execute();
        }

        $conn->commit();
        echo "<script>
            alert('Data berhasil disimpan!');
            window.location.href = 'neraca_saldo.php';
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='neraca_saldo.php';</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    echo "<script>alert('Invalid request'); window.location.href='neraca_saldo.php';</script>";
}
?>
