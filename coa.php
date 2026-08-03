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




// Query untuk mengambil data dari tabel COA
$sql = "SELECT account_code FROM coa";

// Lakukan eksekusi query
$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input COA</title>
<link rel="stylesheet" 
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        color: maroon; /* Warna teks */
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9); /* Gradien dari atas ke bawah */
    }

    h2 {
        text-align: center; /* Pusatkan judul */
        margin-bottom: 20px; /* Beri jarak bawah */
        color: maroon;
    }

    .container {
        overflow: hidden; /* Atasi masalah clearfix */
    }

    .form-container {
        width: 200px; /* Lebar formulir */
        float: left; /* Letakkan formulir di sebelah kiri */
        background-color: #fff; /* Warna latar belakang formulir */
        padding: 20px; /* Ruang dalam formulir */
        border-radius: 10px; /* Sudut bulat */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Bayangan formulir */
         margin: 10px;
    }

    label {
        font-weight: bold; /* Tebal kan label */
    }

    input[type="text"],
    select {
        width: 100%; /* Lebar input */
        padding: 8px; /* Ruang dalam input */
        margin-bottom: 15px; /* Beri jarak bawah */
        border: 1px solid #ccc; /* Garis tepi */
        border-radius: 5px; /* Sudut bulat */
        box-sizing: border-box; /* Hitung lebar dengan tepi dan padding */
    }

    input[type="submit"] {
        width: 100%; /* Lebar tombol submit */
        padding: 10px; /* Ruang dalam tombol */
        background-color: maroon; /* Warna latar belakang tombol */
        color: #fff; /* Warna teks tombol */
        border: none; /* Hilangkan garis tepi */
        border-radius: 5px; /* Sudut bulat */
        cursor: pointer; /* Tampilkan kursor tangan saat diarahkan */
        transition: background-color 0.3s; /* Efek transisi */
    }

    input[type="submit"]:hover {
        background-color: #8b0000; /* Warna latar belakang tombol saat dihover */
    }

    /* Gaya untuk tabel */
    .table-container {
        width: 50%; /* Lebar tabel */
        float: right; /* Letakkan tabel di sebelah kanan */
        margin-top: 20px; /* Beri jarak atas */
        FONT COLOR: WHITE;
    }

    table {
        width: 100%; /* Gunakan lebar penuh */
        border-collapse: collapse; /* Gabungkan batas sel */
        color: white
    }

    table, th, td {
        border: 1px solid #ddd; /* Garis tepi */
        padding: 8px; /* Ruang dalam sel */
        text-align: left; /* Rata kiri teks dalam sel */
        FONT COLOR: WHITE;
    }

    th {
        background-color: mAROON; /* Warna latar belakang header */
    }
        .home-icon i {
        color: maroon;
        font-size: 24px;
         float: left;
    }

    .left-icon i {
        color: maroon;
        font-size: 24px;
         float: right;
    }
.logo {
    width: 200px; /* Lebar gambar */
    height: auto; /* Biarkan tinggi otomatis sesuai rasio aspek */
    display: block; /* Set gambar menjadi blok agar dapat menggunakan margin auto */
    margin: 0 auto; /* Atur margin otomatis secara horizontal untuk menengahkan gambar */
}
th, td {
    padding: 2px; /* Ubah sesuai kebutuhan */
}
tr {
    height: 5px; /* Ubah sesuai kebutuhan */
}

</style>
</head>
<body>
    
      
     
  
       
    <div class="form-group" style="display: flex; overflow: hidden;">
  

    <div class="container">
        <div class="form-container">
            <form action="save_coa.php" method="post">
                <img src="logo.png" alt="Logo" class="logo"> 
                    <h2>Input COA</h2>
                <label for="account_code">Account Code:</label><br>
                <input type="text" id="account_code" name="account_code"><br><br>

                <label for="account_name">Account Name:</label><br>
                <input type="text" id="account_name" name="account_name"><br><br>
                
                   <label for="layer">Layer</label><br>
              <select id="layer" name="layer">
                    <option value="">- Pilih -</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                     <option value="4">4</option>
                </select><br><br>
                
                
                <label for="posisi">Neraca/P&amp;L:</label><br>
                <select id="posisi" name="posisi">
                    <option value="">- Pilih -</option>
                    <option value="neraca">Neraca</option>
                    <option value="P&L">P&amp;L</option>
                </select><br><br>
           
                <label for="dc">Posisi:</label><br>
                <select id="dc" name="dc">
                    <option value="">- Pilih -</option>
                    <option value="debet">Debet</option>
                    <option value="credit">Credit</option>
                </select><br><br>
           
                <label for="open">Opening Balance:</label><br>
                <input type="text" id="open" name="open"><br><br>
           
                <label for="parent_account">Parent Account:</label><br>
                <select id="parent_account" name="parent_account">
    <option value="">- No Parent -</option>
    <?php
    // Periksa apakah query menghasilkan baris-baris data
    if ($result->num_rows > 0) {
        // Loop melalui setiap baris hasil query
        while ($row = $result->fetch_assoc()) {
            // Buat opsi untuk setiap baris data
       echo '<option value="' . $row['account_code'] . '">' . $row['account_code'] . '</option>';

        }
    } else {
        // Jika tidak ada data yang ditemukan, tampilkan pesan
        echo '<option value="">Data tidak tersedia</option>';
    }
    ?>
</select><br><br>

                <input type="submit" value="Submit">
            </form>
        </div>
          </div>

        <div class="table-container">
            <a href="home.php" class="home-icon">
            <i class="fas fa-home"></i>
              <a href="home.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
             <h2>DAFTAR COA</h2>
        
     <?php


$sql = "SELECT * FROM coa ORDER BY account_code";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output data ke dalam tabel HTML
    echo "<table id='coaTable'>";
    echo "<thead><tr><th>Report Account</th><th>Transaction Account</th><th>Layer</th><th>Account Name</th><th>Neraca/P&L</th><th>Posisi</th><th>Opening Balance</th><th>Action</th></tr></thead>";
    echo "<tbody>";
    while($row = $result->fetch_assoc()) {
        // Output setiap baris data COA ke dalam tabel
        echo "<tr>";
        
        echo "<input type='hidden' name='account_code' value='" . $row["account_code"] . "'>";

        // Output account_code sesuai layer
        if ($row["layer"] != 4) {
            echo "<td>" . htmlspecialchars($row["account_code"], ENT_QUOTES, 'UTF-8') . "</td>";
        } else {
            echo "<td></td>"; // Jika bukan layer 4, biarkan kolom kosong
        }
        
       
        // Output transaction account
        if ($row["layer"] == 4) {
            echo "<td>" . $row["account_code"] . "</td>";
        } else {
            echo "<td></td>"; // Jika bukan layer 4, biarkan kolom kosong
        }
        
        // Output kolom lainnya
        echo "<td>" . $row["layer"] . "</td>";
        echo "<td>" . $row["account_name"] . "</td>";
        echo "<td>" . $row["posisi"] . "</td>";
        echo "<td>" . $row["dc"] . "</td>";
        echo "<td>" . $row["open"] . "</td>";
        
        echo "<td style='max-width: 70px; white-space: nowrap;'>";

        // Tautan edit dengan menambahkan id dari setiap baris COA sebagai parameter
        echo "<a href='editcoa.php?id=" . $row["id"] . "' style='margin-top: 0px; float: left;'><i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px;margin-right: 20px;  white-space: nowrap; color: blue;'></i></a>";
        
        // Tautan hapus dengan menambahkan id dari setiap baris COA sebagai parameter
        echo "<a href='deletecoa.php?id=" . $row["id"] . "' style='margin-top: 0px; float: right;'><i class='fa-solid fa-trash-can' style='font-size: 20px; margin-left: 10px;  white-space: nowrap; color: red;'></i></a>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
} else {
    echo "Tidak ada data COA.";
}
$conn->close();
?>

    

  
    </div>
    </div>
</body>
</html>
