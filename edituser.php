<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);

// Batasi akses berdasarkan HTTP_REFERER
$allowed_referer_domain = "https://af.symotech.id/";
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}


// Pastikan require config.php jika diperlukan
require_once 'config.php';

// Ambil konfigurasi database dari environment
$host = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$dbname = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");
$username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$password = getenv('DB_PASS');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Jika perlu koneksi MySQLi (misalnya untuk kompatibilitas kode lama)
$conn = new mysqli($host, $username, $password, $dbname);

// Periksa koneksi MySQLi
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}


// Ambil user_id dari session
$id = $_SESSION['userid'];

// Proses perubahan password
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Cek apakah password lama benar
    $query = "SELECT password FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Query error: " . $conn->error);
    }
    $stmt->bind_param("i", $id); // Menggunakan $id untuk memastikan parameter sesuai
    $stmt->execute();
    $stmt->bind_result($db_password); // Ambil password yang ter-hash dari database
    $stmt->fetch();
    $stmt->close();

    // Verifikasi password lama menggunakan password_verify()
    if (password_verify($current_password, $db_password)) {
        // Cek apakah password baru dan konfirmasi password cocok
        if ($new_password == $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_query = "UPDATE users SET password = ? WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                die("Query error: " . $conn->error);
            }
            $update_stmt->bind_param("si", $hashed_password, $id);
            if ($update_stmt->execute()) {
                $message = "Password berhasil diperbarui!";
            } else {
                $message = "Gagal memperbarui password.";
            }
            $update_stmt->close();
        } else {
            $message = "Password baru dan konfirmasi password tidak cocok.";
        }
    } else {
        $message = "Password lama salah.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Password www.symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            color: #333;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .message {
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .session-debug {
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .session-debug table {
            width: 100%;
            border-collapse: collapse;
        }

        .session-debug table, .session-debug th, .session-debug td {
            border: 1px solid #ddd;
        }

        .session-debug th, .session-debug td {
            padding: 10px;
            text-align: left;
        }

        .session-debug th {
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
  

<div class="container">
    <h1>Update Password</h1>

    <?php if (isset($message)) { ?>
        <div class="message <?php echo (strpos($message, 'berhasil') !== false) ? 'success' : ''; ?>">
            <?php echo $message; ?>
        </div>
    <?php } ?>

    <form action="" method="POST">
        <div class="form-group">
            <label for="current_password">Password Lama</label>
            <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="form-group">
            <label for="new_password">Password Baru</label>
            <input type="password" id="new_password" name="new_password" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password Baru</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn">Update Password</button>
    </form>

    <a href="index.html" class="btn">Kembali ke Halaman Login</a>

    <!-- Display session data in a structured format -->
    <div class="session-debug">
        <h2>Informasi Session</h2>
        <table>
            <tr>
                <th>Key</th>
                <th>Value</th>
            </tr>
            <?php
            // Menampilkan session dalam bentuk tabel
            foreach ($_SESSION as $key => $value) {
                echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars(print_r($value, true)) . "</td></tr>";
            }
            ?>
        </table>
    </div>
</div>

</body>
</html>

<?php
// Tutup koneksi setelah form selesai ditampilkan
$conn->close();
?>
