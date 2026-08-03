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



// Ambil ID dari URL atau POST
$asset_id = isset($_GET['id']) ? $_GET['id'] : '';

// Query untuk mengambil data aset
$sql = "SELECT id, kode, nama, location, devisi, keterangan, lampiran, beli FROM aset WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $asset_id);
$stmt->execute();

// Bind result to variables
$stmt->bind_result($id, $kode, $nama, $location, $devisi, $keterangan, $lampiran, $beli);

// Fetch the result
if ($stmt->fetch()) {
    $asset = [
        'id' => $id,
        'kode' => $kode,
        'nama' => $nama,
        'location' => $location,
        'devisi' => $devisi,
        'keterangan' => $keterangan,
         'lampiran' => $lampiran,
         'beli' => $beli
    ];
} else {
    echo "Asset not found.";
    exit();
}

// Close the statement
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Jurnal</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <style>
        body {
            color: maroon;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-image: linear-gradient(to right, #ea90e6, #6c6cc9);
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            margin-top: 20px;
        }
       label {
             width: 750px;
            font-weight: bold;
             
   position: absolute;
    
        }
        input[type="date"], select, input[type="text"] {
   
   
    right: 10px; /* Mulai dari kanan */
    width: 600px; /* Lebar input terkunci 500px */
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
position: absolute;
        }
        input[readonly] {
            background-color: #f9f9f9;
            cursor: not-allowed;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
          .form-container {
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
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


    </style>
</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <div class="container">
        <h2>Form Jurnal Recurring</h2>
        <form id="formJurnal" action="save_recur.php" method="post" enctype="multipart/form-data">
            <label for="mulai">Mulai:</label>
            <input type="date" id="mulai" name="mulai" required><br><br>

            <label for="akhir">Akhir:</label>
            <input type="date" id="akhir" name="akhir" required><br><br>

            <!-- Asset Details from the Database -->
            <label for="id">ID:</label>
            <input type="text" id="id" name="id" value="<?php echo $asset['id']; ?>" readonly><br><br>

            <label for="kode">Kode:</label>
            <input type="text" id="kode" name="kode" value="<?php echo $asset['kode']; ?>" readonly><br><br>

            <label for="nama">Nama:</label>
            <input type="text" id="nama" name="nama" value="<?php echo $asset['nama']; ?>" readonly><br><br>

            <label for="location">Location:</label>
            <input type="text" id="location" name="location" value="<?php echo $asset['location']; ?>" readonly><br><br>

            <label for="devisi">Devisi:</label>
            <input type="text" id="devisi" name="devisi" value="<?php echo $asset['devisi']; ?>" readonly><br><br>

            <label for="keterangan">Keterangan:</label>
            <input type="text" id="keterangan" name="keterangan" value="<?php echo $asset['keterangan']; ?>" readonly><br><br>

            <label for="lampiran">Lampiran:</label>
            <input type="text" id="lampiran" name="lampiran" value="<?php echo $asset['lampiran']; ?>" readonly><br><br>
 <label for="beli">Nilai Aset:</label>
            <input type="text" id="beli" name="beli" value="<?php echo $asset['beli']; ?>" readonly><br><br>
            <!-- Pilihan COA Debet -->
            <label for="coad">Pilih COA Debet:</label>
            <select id="coad" name="coad" required onchange="updateAccountName('coad', 'account_named')">
                <option value="">Pilih COA</option>
                <?php
                $sql = "SELECT account_code, account_name FROM coa WHERE layer = 4 AND account_code LIKE '7112%'";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['account_code']}'>{$row['account_code']} - {$row['account_name']}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada COA tersedia</option>";
                }
                ?>
            </select><br><br>

            <input type="text" id="account_named" name="account_named" readonly><br><br>

            <!-- Pilihan COA Kredit -->
            <label for="coak">Pilih COA Kredit:</label>
            <select id="coak" name="coak" required onchange="updateAccountName('coak', 'account_namek')">
                <option value="">Pilih COA</option>
                <?php
                $sql = "SELECT account_code, account_name FROM coa WHERE layer = 4 AND account_code LIKE '162%'";
                $result = $conn->query($sql);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['account_code']}'>{$row['account_code']} - {$row['account_name']}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada COA tersedia</option>";
                }
                ?>
            </select><br><br>

            <input type="text" id="account_namek" name="account_namek" readonly><br><br>

            <!-- Input Nominal -->
            <label for="debet">Nominal:</label>
        
        <input 
            type="text" 
            id="debet" 
            name="debet" 
            min="0" 
            value="0" 
            style="text-align: right;" 
            oninput="formatRibuan(this)" 
        >
<br><br>
            <input type="submit" value="Submit">
        </form>
    </div>

    <script>
        function updateAccountName(selectId, accountNameId) {
            const selectElement = document.getElementById(selectId);
            const accountNameInput = document.getElementById(accountNameId);

            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (selectedOption && selectedOption.text) {
                const accountName = selectedOption.text.split(' - ')[1] || '';
                accountNameInput.value = accountName;
            } else {
                accountNameInput.value = '';
            }
        }
        
        
          function formatRibuan(input) {
            // Hilangkan karakter non-digit
            let value = input.value.replace(/[^,\d]/g, "");
            
            // Pisahkan angka desimal jika ada
            let split = value.split(",");
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/g);

            // Tambahkan titik jika angka > 3 digit
            if (ribuan) {
                separator = sisa ? "." : "";
                rupiah += separator + ribuan.join(".");
            }

            // Gabungkan dengan angka desimal jika ada
            rupiah = split[1] !== undefined ? rupiah + "," + split[1] : rupiah;

            input.value = rupiah;
        }
    </script>
</body>
</html>
