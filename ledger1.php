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


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Ledger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .home-icon1, .left-icon {
            position: absolute;
            top: 20px;
            font-size: 24px;
            color: maroon;
        }

        .home-icon1 {
            left: 20px;
        }

        .left-icon {
            right: 20px;
        }

        form {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            margin-right: 10px;
        }

        input[type="text"], input[type="date"] {
            width: calc(100% - 110px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        @media screen and (max-width: 600px) {
            input[type="text"], input[type="date"] {
                width: 100%;
            }

            button {
                width: 100%;
                padding: 12px;
            }
        }
        .form-layout {
    display: grid;
    grid-template-columns: 1fr 2fr; /* Label dan input */
    gap: 15px;
    align-items: center;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-bottom: 5px;
}

input[type="text"], input[type="date"] {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
}

button {
    padding: 10px 20px;
    font-size: 16px;
    background-color: #4CAF50;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

button:hover {
    background-color: #45a049;
}

.form-group.full-width {
    grid-column: span 2; /* Membuat tombol penuh pada dua kolom */
    text-align: center;
}

@media screen and (max-width: 600px) {
    .form-layout {
        grid-template-columns: 1fr; /* Satu kolom untuk layar kecil */
    }

    .form-group.full-width {
        grid-column: span 1;
    }
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
    <h1>Laporan Ledger</h1>
    <form method="POST" class="form-layout">
        <div class="form-group">
            <label for="coa">COA:</label>
            <input type="text" name="coa" id="coa" placeholder="Masukkan COA" required>
        </div>
        <div class="form-group">
            <label for="start_date">Tanggal Mulai:</label>
            <input type="date" name="start_date" id="start_date" required>
        </div>
        <div class="form-group">
            <label for="location">Lokasi:</label>
            <input type="text" name="location" id="location" placeholder="Lokasi">
        </div>
        <div class="form-group">
            <label for="end_date">Tanggal Selesai:</label>
            <input type="date" name="end_date" id="end_date">
        </div>
        <div class="form-group">
            <label for="devisi">Divisi:</label>
            <input type="text" name="devisi" id="devisi" placeholder="Divisi">
        </div>
        <div class="form-group full-width">
            <button type="submit">Cari</button>
        </div>
    </form>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $coa = $_POST['coa'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        $location = $_POST['location'] ?? null;
        $devisi = $_POST['devisi'] ?? null;

     

      $conn = new mysqli($servername, $db_username, $db_password, $database);
        if ($conn->connect_error) {
            die("Koneksi gagal: " . $conn->connect_error);
        }

        $sql_saldo_awal = "
            SELECT SUM(debet) - SUM(kredit) AS saldo_awal
            FROM jurnal 
            WHERE coa = ? AND tanggal < ?
        ";
        $stmt_saldo_awal = $conn->prepare($sql_saldo_awal);
        $stmt_saldo_awal->bind_param("ss", $coa, $start_date);
        $stmt_saldo_awal->execute();
        $stmt_saldo_awal->bind_result($saldo_awal);
        $stmt_saldo_awal->fetch();
        $stmt_saldo_awal->close();

        $saldo_awal = $saldo_awal ?? 0;

        $sql = "
            SELECT tanggal, journal_number, coa, account_name, debet, kredit, keterangan, lampiran, location, devisi
            FROM jurnal 
            WHERE coa = ? AND tanggal >= ?
        ";
        if ($end_date) { $sql .= " AND tanggal <= ?"; }
        if ($location) { $sql .= " AND location LIKE ?"; }
        if ($devisi) { $sql .= " AND devisi LIKE ?"; }
        $sql .= " ORDER BY tanggal";

        $stmt = $conn->prepare($sql);
        $params = [$coa, $start_date];
        $types = "ss";
        if ($end_date) { $params[] = $end_date; $types .= "s"; }
        if ($location) { $params[] = "%$location%"; $types .= "s"; }
        if ($devisi) { $params[] = "%$devisi%"; $types .= "s"; }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($tanggal, $journal_number, $coa_result, $account_name, $debet, $kredit, $keterangan, $lampiran, $location, $devisi);

        echo "<h2>Ledger untuk COA: " . htmlspecialchars($coa) . "</h2>";
        echo "<table>
                <tr>
                    <th>Tanggal</th>
                    <th>No Jurnal</th>
                    <th>COA</th>
                    <th>Account Name</th>
                    <th>Debet</th>
                    <th>Kredit</th>
                    <th>Saldo</th>
                    <th>Keterangan</th>
                    <th>Lampiran</th>
                    <th>Location</th>
                    <th>Divisi</th>
                </tr>";

        echo "<tr>
                <td colspan='6'></td>
                <td><strong>Saldo Awal: </strong>" . number_format($saldo_awal, 0, ',', '.') . "</td>
                <td colspan='4'></td>
              </tr>";

        $saldo_sebelumnya = $saldo_awal;
        while ($stmt->fetch()) {
            $saldo = $debet - $kredit + $saldo_sebelumnya;
            echo "<tr>
                    <td>" . htmlspecialchars($tanggal) . "</td>
                    <td>" . htmlspecialchars($journal_number) . "</td>
                    <td>" . htmlspecialchars($coa_result) . "</td>
                    <td>" . htmlspecialchars($account_name) . "</td>
                    <td>" . number_format($debet, 0, ',', '.') . "</td>
                    <td>" . number_format($kredit, 0, ',', '.') . "</td>
                    <td>" . number_format($saldo, 0, ',', '.') . "</td>
                    <td>" . htmlspecialchars($keterangan) . "</td>
                    <td>" . htmlspecialchars($lampiran) . "</td>
                    <td>" . htmlspecialchars($location) . "</td>
                    <td>" . htmlspecialchars($devisi) . "</td>
                  </tr>";
            $saldo_sebelumnya = $saldo;
        }

        echo "</table>";
        $stmt->close();
        $conn->close();
    }
    ?>
</div>
</div>
<script>
document.querySelector("form").addEventListener("submit", function() {
    document.getElementById("coa_input").value = document.getElementById("coa").value;
    document.getElementById("start_date_input").value = document.getElementById("start_date").value;
    document.getElementById("end_date_input").value = document.getElementById("end_date").value;
    document.getElementById("location_input").value = document.getElementById("location").value;
    document.getElementById("devisi_input").value = document.getElementById("devisi").value;
});
</script>

</body>
</html>
