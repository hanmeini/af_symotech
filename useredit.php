<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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



$userid = $_GET['id'];

// Ambil data user berdasarkan ID
$sql = "SELECT userid, username, location, jabatan FROM me WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();

// Bind hasil ke variabel
$stmt->bind_result($db_userid, $db_username, $db_location, $db_jabatan);

if ($stmt->fetch()) {
    $row = [
        'userid' => $db_userid,
        'username' => $db_username,
        'location' => $db_location,
        'jabatan' => $db_jabatan,
    ];
} else {
    echo "User tidak ditemukan.";
    exit();
}

$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $location = $_POST['location'];
    $jabatan = $_POST['jabatan'];

    $updateSql = "UPDATE users SET username = ?, location = ?, jabatan = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sssi", $username, $location, $jabatan, $user_id);

    if ($updateStmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui!'); window.location.href='daftaruser.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }

    $updateStmt->close();
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(to right, #74ebd5, #9face6);
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
        }

        form label {
            display: block;
            font-weight: bold;
            margin: 10px 0 5px;
            color: #555;
        }

        input[type="text"], input[type="password"], input[type="email"], select {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }

        form button {
            width: 100%;
            padding: 10px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        form button:hover {
            background: #0056b3;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #007bff;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Edit User</h2>
        <form method="POST">
            <label>Username:</label>
            <input type="text" name="username" value="<?= htmlspecialchars($row['username']); ?>" required>
         
    <select id="jabatan" name="jabatan" required>
        <option value="" disabled selected hidden>Pilih Jabatan</option>
        <?php
        
        if ($conn->connect_error) {
            die("Koneksi gagal: " . $conn->connect_error);
        }
        $sql = "SELECT jabatan FROM jabatan";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<option value='" . $row['jabatan'] . "'>" . $row['jabatan'] . "</option>";
            }
        } else {
            echo "<option value=''>Tidak ada data jabatan tersedia</option>";
        }

        ?>
    </select>
    <br>

     <br>
   
                
            <select name="location" required>
                <option value="" disabled selected>Pilih Lokasi</option>
                <?php
              $sql2 = "SELECT idl, nama_cabang FROM location";
                $result2 = $conn->query($sql2);
                if ($result2->num_rows > 0) {
                    while ($row = $result2->fetch_assoc()) {
                        echo "<option value='{$row["idl"]}'>{$row["nama_cabang"]}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada cabang tersedia</option>";
                }
                ?>
                ?>
            </select>

            
            </label>
            <br>

            <button type="submit">Simpan</button>
        </form>
        <a href="daftaruser.php" class="back-link">← Kembali ke Daftar User</a>
    </div>
</body>
</html>
