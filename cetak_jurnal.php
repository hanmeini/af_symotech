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



// Ambil nomor jurnal dari URL
$journal_number = isset($_GET['kode_transaksi']) ? $_GET['kode_transaksi'] : '';

// Query untuk mendapatkan data dari tabel jurnal berdasarkan nomor jurnal
$sql = "SELECT * FROM jurnal WHERE journal_number LIKE ?";
$stmt = $conn->prepare($sql);

$journal_number = "%" . $journal_number . "%"; // tambahkan wildcard
$stmt->bind_param("s", $journal_number);

$stmt->execute();
$stmt->store_result(); // untuk cek jumlah baris hasil


// Ambil data jika ada hasil
$data = [];

if ($stmt->num_rows > 0) {
    // Bind result variables
    $stmt->bind_result($id, $journal_number, $tanggal, $keterangan, $lampiran, $coa, $account_name, $debet, $kredit, $posting, $kode_booking, $location_id, $devisi, $supcust);

    // Fetch the results
    while ($stmt->fetch()) {
        // Ambil nama_cabang dari tabel location berdasarkan idl
        $nama_cabang = '';
        if (!is_null($location_id)) {
            $sql_loc = "SELECT nama_cabang FROM location WHERE idl = ?";
            $loc_stmt = $conn->prepare($sql_loc);
            $loc_stmt->bind_param("i", $location_id);
            $loc_stmt->execute();
            $loc_stmt->bind_result($nama_cabang_result);
            if ($loc_stmt->fetch()) {
                $nama_cabang = $nama_cabang_result;
            }
            $loc_stmt->close();
        }

        $data[] = [
            'id' => $id,
            'journal_number' => $journal_number,
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
            'lampiran' => $lampiran,
            'location' => $nama_cabang, // ✅ Menampilkan nama_cabang
            'devisi' => $devisi,
            'coa' => $coa,
            'account_name' => $account_name,
            'debet' => $debet,
            'kredit' => $kredit,
            'posting' => $posting,
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
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-size: 14px; /* Smaller font for body */
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
            font-size: 13px; /* Smaller font for table */
        }
        th, td {
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        h2 {
            text-align: center;
            color: #333;
            font-size: 18px; /* Adjusted font size */
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .signature-row {
            padding-top: 20px;
            font-size: 12px; /* Smaller font for signature section */
        }
        .signature-row label {
            display: inline-block;
            width: 200px;
        }
        .signature-row input {
            width: 250px;
            text-align: center;
            border: none;
            outline: none;
            background: transparent;
            font-size: 12px; /* Smaller font for input fields */
        }
        /* Styling for print */
        @media print {
            body * {
                visibility: hidden;
            }
            .container, .container * {
                visibility: visible;
            }
            .container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }

        /* Styling for Print Button */
        .print-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .print-button:hover {
            background-color: #45a049;
        }
            .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Dua kolom dengan ukuran sama */
        gap: 20px; /* Jarak antar kolom */
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
    <div class='table-container'>
    <a href='home.php' class='home-icon1'>
        <i class='fas fa-home'></i>
    </a>
    <a href='home.php' class='left-icon'>
        <i class='fa-solid fa-circle-left'></i>
    </a>
    <div class="container">
        <h2>Cetak Journal</h2>
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
    echo '<p> Lampiran : ' . htmlspecialchars($data[0]['lampiran'] ?? '') . '</p>';
}
?>

        <?php if (!empty($data)) { ?>
            <table id="journalTable">
                <thead>
                    <tr>
                        <th>COA</th>
                        <th>Nama Akun</th>
                        <th>Debet</th>
                        <th>Kredit</th>
                        <th>Keterangan</th>
                        <th>Posting</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalDebet = 0;
                    $totalKredit = 0;
                    foreach ($data as $row) {
                        $totalDebet += $row['debet'];
                        $totalKredit += $row['kredit'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['coa'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['account_name'] ?? ''); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['debet'] ?? 0, 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['kredit'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($row['keterangan'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['posting'] ?? ''); ?></td>
                        </tr>
                    <?php } ?>
                     <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Total</td>
                            <td style="text-align: right;"><?php echo number_format($totalDebet, 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($totalKredit, 2); ?></td>
                            <td></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Selisih</td>
                            <td colspan="3" style="text-align: right;"><?php echo number_format($totalDebet - $totalKredit, 2); ?></td>
                        </tr>
                </tbody>
            </table>
        <?php } elseif (isset($message)) { ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php } ?>
        
        <!-- Tanda Tangan di bawah tabel dan total -->
     <div class="signature-row">
    <label for="staff_sign">Staff Acc: (.........................)</label>
 
    &nbsp;&nbsp; <!-- Spasi antara kolom -->

    <label for="man_sign">Manager Acc: (.........................)</label>
        &nbsp;&nbsp; <!-- Spasi antara kolom -->

    <label for="man_sign">Direktur: (.........................)</label>
    
</div>
   </div>
        <!-- Tombol Print -->
        <div style="text-align: center; margin-top: 20px;">
            <button class="print-button" onclick="printPage()">Print</button>
            
            <button class="print-button" onclick="window.location.href='paymentvoucher.php?kode_transaksi=<?php echo $journal_number; ?>'">
    Payment Voucher
</button>
        </div>

    </div>

    <script>
        function printPage() {
            window.print();
        }
    </script>
</body>
</html>
