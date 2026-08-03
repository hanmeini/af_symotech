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


// Tangkap bulan, tahun, dan lokasi yang dipilih
$selected_month = isset($_GET['bulan']) ? (int)$_GET['bulan'] : date('m');
$selected_year = isset($_GET['tahun']) ? (int)$_GET['tahun'] : date('Y');
$selected_location = isset($_GET['location']) ? $_GET['location'] : 'all';
$selected_devisi = isset($_GET['devisi']) ? $_GET['devisi'] : 'all';

// Kondisi filter untuk lokasi
$location_condition = '';
if ($selected_location !== 'all') {
    $location_condition = " AND location = '" . $conn->real_escape_string($selected_location) . "'";
}
$devisi_condition = '';
if ($selected_devisi !== 'all') {
    $devisi_condition = " AND devisi = '" . $conn->real_escape_string($selected_devisi) . "'";
}
if ($selected_month == 0) {
    $previous_month = 12;
    $previous_year = $selected_year - 1;
} elseif ($selected_month == 1) {
    $previous_month = 12;
    $previous_year = $selected_year - 1;
} else {
    $previous_month = $selected_month - 1;
    $previous_year = $selected_year;
}


// Ambil data coa termasuk posisi
$sql_coa = "SELECT account_code, account_name, posisi, dc FROM coa ORDER BY CAST(account_code AS UNSIGNED) ASC";
$result_coa = $conn->query($sql_coa);

$coa_data = [];
while ($row = $result_coa->fetch_assoc()) {
    $coa = $row['account_code'];
    $coa_data[$coa] = [
        'saldo_awal' => 0,
        'total_debet' => 0,
        'total_kredit' => 0,
        'saldo_akhir' => 0,
        'account_name' => $row['account_name'],
        'posisi' => $row['posisi'],
        'dc' => $row['dc'],
         'location' => '',
        'devisi' => ''
    ];
}

// Ambil saldo awal dari tabel close untuk bulan sebelumnya dengan filter lokasi
$sql_saldo_awal = "SELECT c.coa, c.saldo_akhir, c.location, c.devisi 
                    FROM close c 
                    WHERE c.bulan = ? AND c.tahun = ? $location_condition $devisi_condition 
                    AND c.saldo_akhir > 0 
                    ORDER BY CAST(c.coa AS UNSIGNED) ASC";
$stmt_saldo_awal = $conn->prepare($sql_saldo_awal);
$stmt_saldo_awal->bind_param('ii', $previous_month, $previous_year);
$stmt_saldo_awal->execute();
$stmt_saldo_awal->bind_result($coa, $saldoawal, $location, $devisi);

while ($stmt_saldo_awal->fetch()) {
    if (isset($coa_data[$coa])) {
        // Cek jika posisi adalah "P&L", set saldo_awal menjadi 0
        if ($coa_data[$coa]['posisi'] === 'P&L') {
            $coa_data[$coa]['saldo_awal'] = 0;
        } else {
            $coa_data[$coa]['saldo_awal'] = $saldoawal;
        }
        $coa_data[$coa]['location'] = $location;
    }
}
$stmt_saldo_awal->close();

// Periksa apakah bulan dipilih adalah 0 (untuk semua bulan)
if ($selected_month == 0) {
    // Query untuk mengambil semua bulan dalam tahun yang dipilih
    $sql_jurnal = "SELECT coa, SUM(debet) AS total_debet, SUM(kredit) AS total_kredit, location 
                   FROM jurnal 
                   WHERE YEAR(tanggal) = ? $location_condition
                   GROUP BY coa, location 
                   HAVING total_debet > 0 OR total_kredit > 0 
                   ORDER BY CAST(coa AS UNSIGNED) ASC";
    $stmt_jurnal = $conn->prepare($sql_jurnal);
    $stmt_jurnal->bind_param('i', $selected_year);  // Hanya tahun yang perlu di-bind
} else {
    // Query untuk bulan yang dipilih saja
    $sql_jurnal = "SELECT coa, SUM(debet) AS total_debet, SUM(kredit) AS total_kredit, location 
                   FROM jurnal 
                   WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? $location_condition
                   GROUP BY coa, location 
                   HAVING total_debet > 0 OR total_kredit > 0 
                   ORDER BY CAST(coa AS UNSIGNED) ASC";
    $stmt_jurnal = $conn->prepare($sql_jurnal);
    $stmt_jurnal->bind_param('ii', $selected_month, $selected_year);  // Bind bulan dan tahun
}

// Eksekusi query
$stmt_jurnal->execute();
$stmt_jurnal->bind_result($coa_jurnal, $total_debet, $total_kredit, $location);

while ($stmt_jurnal->fetch()) {
    if (isset($coa_data[$coa_jurnal])) {
        $coa_data[$coa_jurnal]['total_debet'] = $total_debet;
        $coa_data[$coa_jurnal]['total_kredit'] = $total_kredit;
        $coa_data[$coa_jurnal]['location'] = $location;
    } else {
        $coa_data[$coa_jurnal] = [
            'saldo_awal' => 0,
            'total_debet' => $total_debet,
            'total_kredit' => $total_kredit,
            'saldo_akhir' => 0,
            'account_name' => '',
            'location' => $location
        ];
    }
}
$stmt_jurnal->close();


// Ambil account_name berdasarkan COA dari tabel coa
foreach ($coa_data as $coa_code => &$data) {
    $sql_account_name = "SELECT account_name, posisi, dc FROM coa WHERE account_code = ?";
    $stmt_account_name = $conn->prepare($sql_account_name);
    $stmt_account_name->bind_param('s', $coa_code);
    $stmt_account_name->execute();
    $stmt_account_name->bind_result($account_name, $posisi, $dc);
    if ($stmt_account_name->fetch()) {
        $data['account_name'] = $account_name;
        $data['posisi'] = $posisi;
        $data['dc'] = $dc;
    } else {
        $data['account_name'] = "Nama akun tidak ditemukan";
    }
    $stmt_account_name->close();

    // Perhitungan saldo akhir sesuai kondisi
    if (is_array($data)) {
        if (in_array($coa_code[0], ['2', '3', '4', '8'])) {
            $data['saldo_akhir'] = $data['saldo_awal'] - $data['total_debet'] + $data['total_kredit'];
        } else {
            $data['saldo_akhir'] = $data['saldo_awal'] + $data['total_debet'] - $data['total_kredit'];
        }

        // Hitung LR Debit dan Kredit
        if ($data['posisi'] === 'P&L') {
            if ($data['dc'] === 'debet') {
                $data['lr_debit'] = $data['saldo_akhir'];
            } elseif ($data['dc'] === 'credit') {
                $data['lr_kredit'] = $data['saldo_akhir'];
            }
        }

        // Hitung Neraca Debit dan Kredit
        if ($data['posisi'] === 'neraca') {
            if ($data['dc'] === 'debet') {
                $data['neraca_debit'] = $data['saldo_akhir'];
            } elseif ($data['dc'] === 'credit') {
                $data['neraca_kredit'] = $data['saldo_akhir'];
            }
        }
    } else {
        error_log("Data untuk COA $coa_code tidak valid.");
    }
}

// Filter COA yang tidak memiliki saldo awal, total debit, atau total kredit
$filtered_coa_data = array_filter($coa_data, function ($data, $coa_code) {
    // Cek jika kode COA diawali dengan angka '1' atau '2'
    if (strpos($coa_code, '4') === 0 || strpos($coa_code, '5') === 0 || strpos($coa_code, '6') === 0 || strpos($coa_code, '7') === 0|| strpos($coa_code, '8') === 0 || strpos($coa_code, '9') === 0)    {
        return false; // Tidak tampilkan COA yang dimulai dengan 1 atau 2
    }

    // Menampilkan COA jika saldo_awal, total_debet, atau total_kredit lebih dari 0
    return $data['saldo_awal'] > 0 || $data['total_debet'] > 0 || $data['total_kredit'] > 0;
}, ARRAY_FILTER_USE_BOTH);  // Gunakan ARRAY_FILTER_USE_BOTH untuk mengakses $coa_code


// Inisialisasi variabel untuk menampung total dari masing-masing kolom
$total_saldo_awal = 0;
$total_debet = 0;
$total_kredit = 0;
$total_saldo_akhir = 0;
$total_lr_debit = 0;
$total_lr_kredit = 0;
$total_neraca_debit = 0;
$total_neraca_kredit = 0;

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Neraca Saldo</title>
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
            overflow: hidden;
            margin: 20px;
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
            padding: 2px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: maroon;
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
        .no-data {
            text-align: center;
            color: white;
            font-style: italic;
        }
        .filter-box {
            text-align: center;
            margin-bottom: 20px;
        }
        .filter-box form {
            display: inline-block;
        }
        .filter-box select {
            padding: 5px;
            font-size: 16px;
        }
        .hidden-columns td:nth-last-child(-n+2) {
    display: none;
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
  <h1 style='text-align: center;'>NERACA</h1>
<div class='container'>
    <div class='filter-box'>
        <form method='GET' action=''>
            <label for='bulan'>Bulan:</label>
            <select name='bulan' id='bulan'>
                <option value='1' " . ($selected_month == 1 ? 'selected' : '') . ">Januari</option>
                <option value='2' " . ($selected_month == 2 ? 'selected' : '') . ">Februari</option>
                <option value='3' " . ($selected_month == 3 ? 'selected' : '') . ">Maret</option>
                <option value='4' " . ($selected_month == 4 ? 'selected' : '') . ">April</option>
                <option value='5' " . ($selected_month == 5 ? 'selected' : '') . ">Mei</option>
                <option value='6' " . ($selected_month == 6 ? 'selected' : '') . ">Juni</option>
                <option value='7' " . ($selected_month == 7 ? 'selected' : '') . ">Juli</option>
                <option value='8' " . ($selected_month == 8 ? 'selected' : '') . ">Agustus</option>
                <option value='9' " . ($selected_month == 9 ? 'selected' : '') . ">September</option>
                <option value='10' " . ($selected_month == 10 ? 'selected' : '') . ">Oktober</option>
                <option value='11' " . ($selected_month == 11 ? 'selected' : '') . ">November</option>
                <option value='12' " . ($selected_month == 12 ? 'selected' : '') . ">Desember</option>
                                <option value='0' " . ($selected_month == 0 ? 'selected' : '') . ">Tahunan</option>
            </select>
            <label for='tahun'>Tahun:</label>
            <select name='tahun' id='tahun'>
                <option value='2022' " . ($selected_year == 2022 ? 'selected' : '') . ">2022</option>
                <option value='2023' " . ($selected_year == 2023 ? 'selected' : '') . ">2023</option>
                <option value='2024' " . ($selected_year == 2024 ? 'selected' : '') . ">2024</option>
            </select>
             <!-- Tambahkan filter lokasi -->
        <label for='location'>Lokasi:</label>
        <select name='location' id='location'>
            <option value='all'>Semua Lokasi</option>
            <option value='Semarang' " . ($_GET['location'] == 'Semarang' ? 'selected' : '') . ">Semarang</option>
            <option value='Solo' " . ($_GET['location'] == 'Solo' ? 'selected' : '') . ">Solo</option>
            <option value='Surabaya' " . ($_GET['location'] == 'Surabaya' ? 'selected' : '') . ">Surabaya</option>
            <option value='Jakarta' " . ($_GET['location'] == 'Jakarta' ? 'selected' : '') . ">Jakarta</option>
            <!-- Tambahkan opsi lokasi lainnya -->
        </select>
            
            <button type='submit'>Filter</button>
        </form>
    </div>";

echo "<div class='table-container'>
<form method='POST' action='closing.php'>
        <table>
            <thead>
                <tr>
                    <th>COA</th>
                    <th>Account Name</th>
                    
                    <th>AKTIVA</th>
                    <th>PACIVA</th>
                      <th>Location</th>
                      <th>DEVISI</th>
                </tr>
                </tr>
            </thead>
            <tbody>";

if (count($filtered_coa_data) > 0) {
    foreach ($filtered_coa_data as $coa_code => $data) {
        echo "<tr>
                <td>" . $coa_code . "</td>
                <td>" . $data['account_name'] . "</td>
               
                <td>" . number_format($data['neraca_debit'], 2) . "</td>
                <td>" . number_format($data['neraca_kredit'], 2) . "</td>
                  <td>" . $data['location'] . "</td>
                     <td>" . $data['devisi'] . "</td>
              </tr>";

        // Hitung total
        $total_saldo_awal += $data['saldo_awal'];
        $total_debet += $data['total_debet'];
        $total_kredit += $data['total_kredit'];
        $total_saldo_akhir += $data['saldo_akhir'];
        $total_lr_debit += $data['lr_debit'];
        $total_lr_kredit += $data['lr_kredit'];
        $total_neraca_debit += $data['neraca_debit'];
        $total_neraca_kredit += $data['neraca_kredit'];
    }

    // Tampilkan total
    echo "<tr>
            <td colspan='2'><strong>Total</strong></td>
            <td style='display:none;'><strong>" . number_format($total_saldo_awal, 2) . "</strong></td>
            <td style='display:none;'><strong>" . number_format($total_debet, 2) . "</strong></td>
            <td style='display:none;'><strong>" . number_format($total_kredit, 2) . "</strong></td>
            <td style='display:none;'><strong>" . number_format($total_saldo_akhir, 2) . "</strong></td>
            <td style='display:none;'><strong>" . number_format($total_lr_debit, 2) . "</strong></td>
            <td style='display:none;'><strong>" . number_format($total_lr_kredit, 2) . "</strong></td>
            <td><strong>" . number_format($total_neraca_debit, 2) . "</strong></td>
            <td><strong>" . number_format($total_neraca_kredit, 2) . "</strong></td>
        </tr>";


   // Hitung laba rugi dan selisih neraca
$laba_rugi = $total_lr_kredit - $total_lr_debit;
$selisih_neraca = $total_neraca_debit - $total_neraca_kredit;

// Hitung selisih antara LR dan Neraca
$selisih_lr_neraca = $laba_rugi - $selisih_neraca;

// Tampilkan baris Laba atau Rugi


// Tampilkan baris Selisih Neraca
echo "<tr>
        <td colspan='4'><strong>Selisih Neraca</strong></td>
        <td>" . number_format($selisih_neraca, 2) . "</td>
    </tr>";

// Tampilkan baris Selisih LR dengan Neraca


} else {
    echo "<tr><td colspan='10' class='no-data'>Tidak ada data yang ditemukan.</td></tr>";
}

    echo "      </table>
      
            </form>
          </div>";
          
echo "</tbody></table></div></div></body></html>";

$conn->close();
?>
