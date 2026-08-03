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



// Ambil parameter kode dari URL
if (!isset($_GET['kode'])) {
    die("KODE tidak ditemukan!");
}
$kode = $_GET['kode'];

// Ambil nilai kolom `beli` dari tabel `bdm`
$sql_beli = "SELECT kode, nama, beli FROM aset WHERE kode = ?";
$stmt_beli = $conn->prepare($sql_beli);
$stmt_beli->bind_param("s", $kode);
$stmt_beli->execute();
$stmt_beli->bind_result($kode, $nama, $beli);

if ($stmt_beli->fetch()) {
    $beliValue = $beli;
} else {
    $beliValue = 0; // Default jika tidak ditemukan
}

$stmt_beli->close();

// Total Kredit dihitung sebelumnya
$totalKredit = 0;




// Query untuk mendapatkan data berdasarkan kode
$query = "SELECT id, no_recuring, kode, tanggal, keterangan, lampiran, coad, account_named, debet, coak, account_namek, kredit, location, devisi, ab 
          FROM recuring 
          WHERE kode = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $kode);
$stmt->execute();

// Bind hasil query ke variabel
$stmt->bind_result($id, $no_recuring, $kode, $tanggal, $keterangan, $lampiran, $coad, $account_named, $debet, $coak, $account_namek, $kredit, $location, $devisi, $ab);

$totalDebet = 0;
$totalKredit = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Detail Data Recurring</title>
    <style>
       body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
            color: #333;
        }

        h1 {
            color: #444;
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px auto;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
            color: #333;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        a {
            text-decoration: none;
            font-size: 20px;
            color: #555;
        }

        a:hover {
            color: #ff0000;
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
   .rekap {
    background-color: #f8f9fa;
    padding: 5px;
    margin: 5px 0;
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.rekap {
    background-color: #b8f9fa;
    padding: 0px;
    margin: 0px 0;
    border-radius: 10px;
    box-shadow: 0 5px 5px rgba(5, 5, 5, 0.5);
    max-width: 400px; /* Mengatur lebar maksimum */
    width: 100%; /* Agar responsif */
}

.rekap h2 {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 0px;
}

.rekap-list {
    list-style: none;
    padding: 0;
    margin: 0;
   
}

    .rekap-list li {
        padding: 8px 0;
        font-size: 1rem;
        color: #555;
        display: flex;
        justify-content: space-between;
}

.rekap-list li span {
    color: #333;
    font-weight: bold;
}

    </style>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
    
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h1>Detail Data Recurring</h1>
    
    <div class="rekap">

    <ul class="rekap-list">
        <li><span>Kode:</span> <strong><?= htmlspecialchars($kode) ?></strong></li>
        <li><span>Nama:</span> <strong><?= htmlspecialchars($nama) ?></strong></li>
        <li><span>Nilai ASET:</span> <strong id="nilaiBDM"></strong></li>
        <li><span>Total Recurring:</span> <strong id="totalRecurring"></strong></li>
        <li><span>Selisih:</span> <strong id="selisih"></strong></li>
        <li><span>Total Recurring Terpakai:</span> <strong id="kreditTerpakai"></strong></li>
        <li><span>Total Sisa Recurring:</span> <strong id="kreditBelumTerpakai"></strong></li>
    </ul>
</div>

    <?php


       $totalTerpakai = 0;
    $totalBelumTerpakai = 0;
    if ($stmt->fetch()) {
         echo "<style>
                .hidden { display: none; }
                .rekap { margin-top: 20px; font-weight: bold; }
              </style>";

        echo "<form id='deleteForm' method='POST' action='aset_delete.php'>";
        echo "<table border='1'>";
        echo "<tr>
                <th><input type='checkbox' id='checkAll'></th>
                <th>No</th>
                <th class='hidden'>ID</th>
                <th>No Recurring</th>
                <th>Kode</th>
                <th>Tanggal</th>
                <th>Keterangan</th>
                <th>Lampiran</th>
                <th>COA Debit</th>
                <th>Account Name Debit</th>
                <th>Debet</th>
                <th>COA Kredit</th>
                <th>Account Name Kredit</th>
                <th>Kredit</th>
                <th>Location</th>
                <th>Devisi</th>
                <th>Terpakai</th>
                <th>Action</th>
              </tr>";

        $no = 1;
        do {
            echo "<tr>";
            echo "<td>";
            if (empty($ab)) {
                echo "<input type='checkbox' name='delete_ids[]' value='$id' class='checkItem'>";
            }
            echo "</td>";
    echo "<td>" . $no++ . "</td>"; // Perbaikan nomor urut
            echo "<td class='hidden'>$id</td>";
            echo "<td>" . htmlspecialchars($no_recuring) . "</td>";
            echo "<td>" . htmlspecialchars($kode) . "</td>";
            echo "<td>" . htmlspecialchars($tanggal) . "</td>";
            echo "<td>" . htmlspecialchars($keterangan) . "</td>";
            echo "<td>" . htmlspecialchars($lampiran) . "</td>";
            echo "<td>" . htmlspecialchars($coad) . "</td>";
            echo "<td>" . htmlspecialchars($account_named) . "</td>";
            echo "<td>" . number_format($debet, 2) . "</td>";
            echo "<td>" . htmlspecialchars($coak) . "</td>";
            echo "<td>" . htmlspecialchars($account_namek) . "</td>";
            echo "<td>" . number_format($kredit, 2) . "</td>";
            echo "<td>" . htmlspecialchars($location) . "</td>";
            echo "<td>" . htmlspecialchars($devisi) . "</td>";
            echo "<td>" . htmlspecialchars($ab) . "</td>";

            // Logika untuk menyembunyikan tombol jika kolom `ab` ada isinya
            echo "<td style='text-align: center;'>";
            if (empty($ab)) {
               echo "<a href='#' onclick=\"window.open('asetrecedit.php?id=" . $id . "', 'popupWindow', 'width=600,height=400,left=100,top=100'); return false;\">
        <i class='fa-solid fa-screwdriver-wrench' style='color: blue;'></i>
      </a>";
                echo "<span style='margin: 0 3px;'></span>";
                echo "<a href='asetrecdel.php?id=" . $id . "'><i class='fas fa-trash-alt' style='color: red;'></i></a>";
                
            $totalBelumTerpakai += $kredit;
            } else {
                $totalTerpakai += $kredit;
            }
            echo "</td>";

            echo "</tr>";

            // Tambahkan nilai ke total debet dan kredit
            $totalDebet += $debet;
            $totalKredit += $kredit;
        } while ($stmt->fetch());

        // Tampilkan total debet dan kredit
        echo "<tr style='font-weight: bold; background-color: #f4f4f4;'>";
        echo "<td colspan='8' style='text-align: right;'>Total</td>";
        echo "<td>" . number_format($totalDebet, 2) . "</td>";
        echo "<td colspan='2'></td>";
        echo "<td>" . number_format($totalKredit, 2) . "</td>";
        echo "<td colspan='3'></td>";
        echo "</tr>";

        echo "</table>";
        echo "<button type='submit' id='deleteSelected'>Hapus yang Terceklist</button>";
        echo "</form>";

        echo "<script>
                document.getElementById('checkAll').addEventListener('click', function() {
                    let checkboxes = document.querySelectorAll('.checkItem');
                    checkboxes.forEach(cb => cb.checked = this.checked);
                });
              </script>";
              
              
       
    
  
    } else {
        echo "<p>Data tidak ditemukan untuk kode: " . htmlspecialchars($kode) . "</p>";
    }
    
    


    // Tutup koneksi
    $stmt->close();
    $conn->close();
    ?>
    <script>
        // Format angka dengan koma sebagai desimal dan titik sebagai ribuan
        function formatNumber(num) {
            return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Ambil nilai dari PHP
        const nilaiBDM = <?= json_encode($beliValue) ?>;
        const totalRecurring = <?= json_encode($totalKredit) ?>;
        const selisih = <?= json_encode($beliValue - $totalKredit) ?>;
        const totalTerpakai = <?= json_encode($totalTerpakai) ?>;
        const totalBelumTerpakai = <?= json_encode($totalBelumTerpakai) ?>;

        // Tampilkan nilai di HTML
        document.getElementById('nilaiBDM').textContent = "" + formatNumber(nilaiBDM);
        document.getElementById('totalRecurring').textContent = " " + formatNumber(totalRecurring);
        document.getElementById('selisih').textContent = " " + formatNumber(selisih);
        document.getElementById('kreditTerpakai').textContent = " " + formatNumber(totalTerpakai);
        document.getElementById('kreditBelumTerpakai').textContent = " " + formatNumber(totalBelumTerpakai);
        </script>

</body>
</html>
