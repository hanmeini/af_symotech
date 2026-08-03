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







// Pastikan user sudah login
if (!isset($_SESSION['username'])) {
    die("Akses ditolak. Silakan login terlebih dahulu.");
}

// Pastikan lokasi = HO / Cabang 1 (ID 1)
if (!isset($_SESSION['location']) || ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== '1' && $_SESSION['location'] != 1)) {
    die("Akses hanya diizinkan untuk user dengan lokasi 'HO' / Cabang 1.");
}

$allowed_referer_domain = "https://af.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
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
 ?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            background-image: url('background.jpg'); /* Ubah dengan URL/path gambar background Anda */
            background-size: cover;
            background-position: center;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            margin: auto;
        }
        h2 {
            text-align: center;
        }
        form {
            margin-top: 10px;
            margin-bottom: 10px;
        }
        label {
            display: block;
            margin-bottom: 10px;
        }
        input[type="text"], input[type="password"], input[type="email"], select {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            box-sizing: border-box;
        }
        button[type="submit"] {
            width: 95%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #0056b3;
        }
        #imagePreview {
            max-width: 200px;
            margin-top: 10px;
        }
        #imagePreview img {
            width: 100%;
            border-radius: 10px;
        }
        footer {
            background: var(--primary-color);
            color: #fff;
            text-align: center;
            padding: 1rem 0;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
         .home-icon i, .left-icon i {
        color: maroon;
        font-size: 24px;
    }

    .logo {
        width: 100px;
        height: auto;
        display: block;
        margin: 0 auto;
    }

    .home-icon {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
    }
      .home-icon {
            position: fixed;
            top: 15px;
            left: 15px;
            font-size: 28px;
            background: white;
            padding: 10px;
            border-radius: 50%;
            box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.3);
        }
        .home-icon a {
            text-decoration: none;
            color: black;
        }
    </style>
</head>
<body>
    
        <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="daftaruser.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <div class="container">
        <h2>Pendaftaran USER</h2>
        
        <form id="registration-form" action="proses_pendaftaran.php" method="post" enctype="multipart/form-data">
            <input type="text" id="username" name="username" placeholder="Username" required>
            <div style="position: relative;">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <span style="position: absolute; top: 40%; right: 10px; cursor: pointer;" onclick="togglePasswordVisibility()">
                   
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
    <select id="jabatan" name="jabatan" required>
        <option value="" disabled selected hidden>Pilih Jabatan</option>
        <?php
      
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
    <button type="button" onclick="openPopup()" style="padding: 4px 15px; background-color: maroon; color: white; border: none; border-radius: 5px; cursor: pointer;">
        <i class="fa-solid fa-plus"></i> Add
    </button>
</div>

            <select id="location" name="location" required>
    <option value="" disabled selected hidden>Pilih AKSES</option>
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
</select>

            
            </label>
            <br>
            <button type="submit">daftar</button>
        </form>
    </div>
    <script>
       
        function togglePasswordVisibility() {
            var passwordInput = document.getElementById("password");
            var passwordToggle = document.getElementById("password-toggle");
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                passwordToggle.classList.replace("fa-eye-slash", "fa-eye");
            } else {
                passwordInput.type = "password";
                passwordToggle.classList.replace("fa-eye", "fa-eye-slash");
            }
        }
        document.addEventListener('contextmenu', function(e) { e.preventDefault(); });
        document.addEventListener('keydown', function(e) {
            if (e.keyCode === 123 || (e.ctrlKey && e.shiftKey && e.keyCode === 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode === 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode === 'U'.charCodeAt(0))) {
                e.preventDefault();
            }
        });
    </script>
    <script>
    function openPopup() {
        const popupWidth = 600;
        const popupHeight = 400;
        const left = (screen.width / 2) - (popupWidth / 2);
        const top = (screen.height / 2) - (popupHeight / 2);

        window.open(
            'jabatan.php', 
            'JabatanPopup', 
            `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=no,scrollbars=yes`
        );
    }
</script>
    <footer><p>copyright &copy; 2024 SYMOTECH 08112753040</p></footer>
</body>
</html>

