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

;

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



// Ambil nomor jurnal dari URL
$journal_number = isset($_GET['kode_transaksi']) ? $_GET['kode_transaksi'] : '';

// Inisialisasi variabel untuk pesan JavaScript
$javascript_alert = '';

// Jika tombol posting ditekan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['ids'])) {
        // Update kolom posting menjadi 'sudah' berdasarkan id
        $ids = implode(',', array_map('intval', $_POST['ids']));
        $sql_update = "UPDATE jurnal SET posting = 'sudah' WHERE id IN ($ids)";
        if ($conn->query($sql_update) === TRUE) {
            $javascript_alert = "<script>
                if (confirm('Posting berhasil. Apakah Anda ingin melihat laporan?')) {
                    window.location.href = 'report.php';
                }
            </script>";
        } else {
            $javascript_alert = "<script>alert('Error: " . addslashes($conn->error) . "');</script>";
        }
    }
}

// Query untuk mendapatkan data dari tabel jurnal berdasarkan nomor jurnal
$sql = "SELECT * FROM jurnal WHERE journal_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $journal_number);
$stmt->execute();
$stmt->store_result();

// Ambil data jika ada hasil
$data = [];
if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $journal_number, $tanggal, $keterangan, $lampiran, $coa, $account_name, $debet, $kredit, $posting, $kode_booking, $location, $devisi, $supcust);
    while ($stmt->fetch()) {
        $data[] = [
            'id' => $id,
            'journal_number' => $journal_number,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
            'lampiran' => $lampiran,
            'coa' => $coa,
            'account_name' => $account_name,
            'debet' => $debet,
            'kredit' => $kredit,
            'posting' => $posting,
            'location' => $location,
            'devisi' => $devisi,
        ];
    }
} else {
    $message = "Tidak ada data untuk nomor jurnal tersebut.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Journal Data</title>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 1080px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: #444;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            color: #333;
        }
        .total-row td {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-align: center;
            display: inline-block;
            text-decoration: none;
            font-size: 16px;
        }
        .button:hover {
            background-color: #0056b3;
        }
        p {
            text-align: center;
            color: #444;
        }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            padding: 10px;
        }
        .two-columns div {
            background-color: #f9f9f9;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .two-columns p {
            margin: 0;
            color: #333;
            font-size: 14px;
       
    
    
    }
     .home-icon1, .left-icon {
            position: float;
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
    
   <div class="container">
       <div class='table-container'>
           
    <a href='home.php' class='home-icon1'>
        <i class='fas fa-home'></i>
    </a>
      &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->  &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
          &nbsp;&nbsp; <!-- Spasi antara kolom -->
        &nbsp;&nbsp; <!-- Spasi antara kolom -->
     
    
    <a href='home.php' class='left-icon'>
        <i class='fa-solid fa-circle-left'></i>
    </a>
        <h2>POSTING JURNAL</h2>
        
        <div class="two-columns">
            <?php if (!empty($data)) { ?>
                <div>
                    <p>Journal Number: <?php echo htmlspecialchars($data[0]['journal_number'] ?? ''); ?></p>
                    <p>Tanggal: <?php echo htmlspecialchars($data[0]['tanggal'] ?? ''); ?></p>
                </div>
                <div>
                    <p>Location: <?php echo htmlspecialchars($data[0]['location'] ?? ''); ?></p>
                    <p>Devisi: <?php echo htmlspecialchars($data[0]['devisi'] ?? ''); ?></p>
                </div>
            <?php } ?>
        </div>

        <?php
        if (!empty($data)) {
            echo '<p> Keterangan : ' . htmlspecialchars($data[0]['keterangan'] ?? '') . '</p>';
            echo '<p> Lampiran : ' . htmlspecialchars($data[0]['lampiran'] ?? '') . '</p>';
        }
        ?>

        <div style="margin-bottom: 10px;">
            <button type="button" class="button" onclick="selectAll()">Select All</button>
            <button type="button" class="button" onclick="deselectAll()">Deselect All</button>
        </div>

        <?php if (!empty($data)) { ?>
            <form method="post">
                <table id="journalTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>COA</th>
                            <th>Nama Akun</th>
                            <th>Debet</th>
                            <th>Kredit</th>
                            <th>Posting</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalDebet = 0;
                        $totalKredit = 0;
                        foreach ($data as $row) {
                            // Pastikan nilai tidak NULL
                            $debet = $row['debet'] ?? 0;
                            $kredit = $row['kredit'] ?? 0;

                            $totalDebet += $debet;
                            $totalKredit += $kredit;
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="ids[]" value="<?php echo htmlspecialchars($row['id']); ?>">
                                    <?php echo htmlspecialchars($row['id']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['coa']); ?></td>
                                <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                                <td style="text-align: right;"><?php echo number_format((float)$debet, 2); ?></td>
                                <td style="text-align: right;"><?php echo number_format((float)$kredit, 2); ?></td>
                                <td><?php echo htmlspecialchars($row['posting'] == 'sudah' ? 'Posted' : 'Not Posted'); ?></td>
                            </tr>
                        <?php } ?>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Total</td>
                            <td style="text-align: right;"><?php echo number_format((float)$totalDebet, 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format((float)$totalKredit, 2); ?></td>
                            <td></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Selisih</td>
                            <td colspan="2" style="text-align: right;"><?php echo number_format((float)($totalDebet - $totalKredit), 2); ?></td>
                        </tr>
                    </tbody>
                </table>
                <button type="submit" class="button">Posting</button>
            </form>
        <?php } else { ?>
            <p><?php echo $message ?? ''; ?></p>
        <?php } ?>
    </div>

    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = true);
        }

        function deselectAll() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = false);
        }
    </script>

    <?php echo $javascript_alert; ?>
</body>
</html>
