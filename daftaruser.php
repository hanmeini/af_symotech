<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

4
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Master User</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        color: maroon;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9);
    }

    .container {
        overflow: hidden;
    }

    .table-container {
        width: 100%;
        overflow-x: auto;
        margin-top: 20px;
        color: white;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        color: white;
    }

    table, th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    th {
        background-color: maroon;
    }

    th, td {
        padding: 8px;
    }

    .home-icon i, .left-icon i {
        color: maroon;
        font-size: 24px;
    }

    h2 {
        text-align: center;
        margin-bottom: 20px;
        color: maroon;
    }

    .search-box {
        margin-bottom: 15px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .search-box input {
        padding: 8px;
        width: 50%;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    .search-box button {
        margin-left: 10px;
        padding: 8px 15px;
        background-color: maroon;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .search-box button:hover {
        background-color: #8b0000;
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
</style>
</head>
<body>

<div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h2>DAFTAR USER</h2>

    <!-- Filter input and Add button -->
    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari berdasarkan username atau lokasi...">
        <button onclick="location.href='daftar.php'"><i class="fa-solid fa-plus"></i> Add</button>
    </div>

    <?php
   

    // Query untuk mengambil data dari tabel user dengan join ke location dan jabatan
    $sql = "SELECT m.userid, m.username, m.location, m.jabatan,
                   COALESCE(l.nama_cabang, m.location) AS nama_cabang,
                   COALESCE(j.jabatan, m.jabatan) AS nama_jabatan
            FROM me m
            LEFT JOIN location l ON (m.location = CAST(l.idl AS CHAR) OR m.location = l.nama_cabang)
            LEFT JOIN jabatan j ON (m.jabatan = CAST(j.idj AS CHAR) OR m.jabatan = j.jabatan)
            ORDER BY m.userid";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        echo "<table id='userTable'>";
        echo "<thead><tr><th>User ID</th><th>Username</th><th>Cabang</th><th>Jabatan</th><th>Action</th></tr></thead>";
        echo "<tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row["userid"]) . "</td>";
            echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
            echo "<td>" . htmlspecialchars($row["nama_cabang"]) . "</td>";
            echo "<td>" . htmlspecialchars($row["nama_jabatan"]) . "</td>";
            echo "<td style='max-width: 70px; white-space: nowrap;'>";
 echo "<a href='useredit.php?id=" . $row["userid"] . "' style='margin-right: 15px;'>
        <i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px; color: blue;'></i>
      </a>";
echo "<a href='userdel.php?id=" . $row["userid"] . "'>
        <i class='fa-solid fa-trash-can' style='font-size: 20px; color: red;'></i>
      </a>";

            echo "</td>";
            echo "</tr>";
        }
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "Tidak ada data user.";
    }
    $conn->close();
    ?>

</div>

<script>
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        table = document.getElementById("userTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Default hide the row
            tdUsername = tr[i].getElementsByTagName("td")[1]; // Username
            tdLocation = tr[i].getElementsByTagName("td")[2]; // Location
            if (tdUsername || tdLocation) {
                if (tdUsername.innerHTML.toLowerCase().indexOf(filter) > -1 || tdLocation.innerHTML.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = ""; // Show the row
                }
            }
        }
    }
</script>

<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>
