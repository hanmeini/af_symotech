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

;

// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}


$message = "";

// Proses form saat disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $kurs = $_POST['kurs'];

    // Validasi input
    if (!empty($tanggal) && is_numeric($kurs)) {
        $sql = "INSERT INTO kurs (tanggal, kurs) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sd", $tanggal, $kurs);

        if ($stmt->execute()) {
            $message = "Data kurs berhasil ditambahkan.";
            // Redirect kembali ke kurs.php setelah berhasil menyimpan data
            header("Location: kurs.php");
            exit();
        } else {
            $message = "Terjadi kesalahan: " . $conn->error;
        }

        $stmt->close();
    } else {
        $message = "Pastikan semua kolom diisi dengan benar.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kurs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-image: linear-gradient(to right, #ea90e6, #6c6cc9);
            color: maroon;
            margin: 0;
            padding: 0;
        }

        .form-container {
            width: 100%;
            max-width: 400px;
            margin: 50px auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        label {
            font-weight: bold;
        }

        input[type="date"], input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: maroon;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover {
            background-color: #8b0000;
        }

        .message {
            margin-top: 10px;
            padding: 10px;
            background-color: #f0f0f0;
            color: maroon;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Tambah Data Kurs</h2>
        <form method="POST" action="">
            <label for="tanggal">Tanggal:</label>
            <input type="date" id="tanggal" name="tanggal" required>

            <label for="kurs">Kurs:</label>
            <input type="text" id="kurs" name="kurs" placeholder="Masukkan nilai kurs" required>

            <input type="submit" value="Tambah Kurs">
        </form>

        <?php if (!empty($message)): ?>
            <div class="message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>