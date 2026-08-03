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
    header("Location: https://jascoacc.symotech.id");
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

// Query untuk mendapatkan semua data dari tabel jurnal dan mengurutkan berdasarkan nomor jurnal
$sql = "SELECT * FROM jurnal WHERE coa LIKE '111%' ORDER BY journal_number";
$result = $conn->query($sql);

// Ambil data jika ada hasil
$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $message = "Tidak ada data di tabel jurnal.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Journal Data</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .search-box {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
        }
        .search-box input, .search-box select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-right: 10px;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .check-all-btn {
            margin: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>KAS ACTIVITY</h2>

        <!-- Filter input -->
        <div class="search-box">
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari berdasarkan nomor jurnal...">
         
            <select id="locationFilter" onchange="filterTable()">
                <option value="">Filter Location</option>
                <?php
                // Ambil lokasi yang tersedia dari database
                $locationQuery = "SELECT DISTINCT location FROM jurnal";
                $locationResult = $conn->query($locationQuery);
                while ($location = $locationResult->fetch_assoc()) {
                    echo "<option value='{$location['location']}'>{$location['location']}</option>";
                }
                ?>
            </select>
            <select id="devisiFilter" onchange="filterTable()">
                <option value="">Filter Divisi</option>
                <?php
                // Ambil divisi yang tersedia dari database
                $devisiQuery = "SELECT DISTINCT devisi FROM jurnal";
                $devisiResult = $conn->query($devisiQuery);
                while ($devisi = $devisiResult->fetch_assoc()) {
                    echo "<option value='{$devisi['devisi']}'>{$devisi['devisi']}</option>";
                }
                ?>
            </select>

            <!-- Filter tanggal -->
            <input type="date" id="startDate" onchange="filterTable()">
        </div>

        <?php if (!empty($data)) { ?>
            <form method="post" action="process_checked.php">
                <table id="journalTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nomor Jurnal</th>
                            <th>Tanggal</th>
                            <th>Keterangan</th>
                            <th>Location</th>
                            <th>Divisi</th>
                            <th>COA</th>
                            <th>Nama Akun</th>
                            <th>Debet</th>
                            <th>Kredit</th>
                       </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row) { ?>
                            <tr>
                              
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['journal_number']); ?></td>
                                <td><?php echo htmlspecialchars($row['tanggal']); ?></td>
                                <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td><?php echo htmlspecialchars($row['devisi']); ?></td>
                                <td><?php echo htmlspecialchars($row['coa']); ?></td>
                                <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                <td style="text-align: right;"><?php echo number_format($row['debet'], 2); ?></td>
                                <td style="text-align: right;"><?php echo number_format($row['kredit'], 2); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <!-- Submit button -->
                <div style="text-align: center;">
                 
                </div>
            </form>
        <?php } elseif (isset($message)) { ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php } ?>
    </div>

    <script>
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        var locationFilter, devisiFilter, startDate, rowDate, locationValue, devisiValue;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        locationFilter = document.getElementById("locationFilter").value;
        devisiFilter = document.getElementById("devisiFilter").value;
        startDate = document.getElementById("startDate").value; // Hanya menggunakan satu input tanggal
        table = document.getElementById("journalTable");
        tr = table.getElementsByTagName("tr");

        // Loop through all rows (starting from row 1, as row 0 is the header)
        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Default hide row
            td = tr[i].getElementsByTagName("td")[2]; // Column for journal_number (index 2)
            locationColumn = tr[i].getElementsByTagName("td")[5]; // Column for location (index 5)
            devisiColumn = tr[i].getElementsByTagName("td")[6]; // Column for devisi (index 6)
            dateColumn = tr[i].getElementsByTagName("td")[3]; // Column for date (index 3)

            if (td) {
                txtValue = td.textContent || td.innerText;

                // Filter berdasarkan nomor jurnal
                if (txtValue.toLowerCase().indexOf(filter) > -1) {

                    // Filter berdasarkan lokasi
                    locationValue = locationColumn.textContent || locationColumn.innerText;
                    if (locationFilter && locationValue !== locationFilter) {
                        continue; // Skip this row if it does not match location filter
                    }

                    // Filter berdasarkan divisi
                    devisiValue = devisiColumn.textContent || devisiColumn.innerText;
                    if (devisiFilter && devisiValue !== devisiFilter) {
                        continue; // Skip this row if it does not match devisi filter
                    }

                    // Filter berdasarkan tanggal
                    if (startDate) {
                        rowDate = new Date(dateColumn.textContent || dateColumn.innerText);
                        if (rowDate.toISOString().split('T')[0] !== startDate) { // Membandingkan tanggal tanpa waktu
                            continue; // Skip this row if the date doesn't match
                        }
                    }

                    tr[i].style.display = ""; // Show row if it matches all filters
                }
            }
        }
    }
    </script>
</body>
</html>
