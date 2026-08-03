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



if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM bdm WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $bdm = $result->fetch_assoc();
    } else {
        echo "bdm tidak ditemukan.";
        exit();
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $kode = $_POST['kode'];
    $nama = $_POST['nama'];
    $tanggal = $_POST['tanggal'];
    $location = $_POST['location'];
    $devisi = $_POST['devisi'];


    $sql = "UPDATE bdm SET kode='$kode', nama='$nama', tanggal='$tanggal', location='$location', devisi='$devisi' WHERE id=$id";
    if ($conn->query($sql) === TRUE) {
        header("Location: bdm.php"); // Ganti dengan halaman tabel daftar bdm
        exit();
    } else {
        echo "Error: " . $conn->error;
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <title>Edit bdm</title>
    <style>
        body {
        color: maroon; /* Warna teks */
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9); /* Gradien dari atas ke bawah */
    }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        
        }
         table {
            width: 100%;
            border-collapse: collapse;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #555;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: bold;
            color: #555;
        }

        input[type="date"],
        select,
        textarea,
        input[type="textarea"] {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        button {
            background-color: #007BFF;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #0056b3;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group input {
            margin-top: 5px;
        }

        .form-footer {
            text-align: center;
        }
        
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 {
            left: 10px;
        }
        .left-icon {
            right: 10px;
        }
    </style>
</head>
<body>
     <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="bdm.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <div class="container">
        <h2>Edit bdm</h2>
        <form method="POST" action="bdmedit.php">
            <input type="hidden" name="id" value="<?= $bdm['id'] ?>">
            <div class="form-group">
                <label for="kode">Kode:</label>
                <input type="text" id="kode" name="kode" value="<?= $bdm['kode'] ?>" readonly required>
            </div>
            <div class="form-group">
                <label for="nama">Nama:</label>
                <input type="text" id="nama" name="nama" value="<?= $bdm['nama'] ?>" required>
            </div>
            <div class="form-group">
                <label for="tanggal">Tanggal:</label>
                <input type="date" id="tanggal" name="tanggal" value="<?= $bdm['tanggal'] ?>" readonly required>
            </div>
            <div class="form-group">
               <label for="location">Location:</label>
<select name="location" required>
    <?php
    // Menampilkan opsi aktif jika ada
    echo isset($bdm['location']) ? "<option value='{$bdm['location']}' selected>{$bdm['location']}</option>" : "<option value='' disabled selected>Pilih Lokasi</option>";

    // Query untuk mendapatkan opsi lainnya
  
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


            </div>
           





            </div>
            <div class="form-footer">
                <button type="submit">Update</button>
            </div>
        </form>
    </div>
        </div>
</body>
</html>
