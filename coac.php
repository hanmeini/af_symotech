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


// Pastikan variabel $search didefinisikan
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses pencarian
$query = "SELECT * FROM coa WHERE layer = 4";

if (!empty($search)) {
    $query .= " AND (account_code LIKE '%$search%' OR account_name LIKE '%$search%')";
}

$result = $conn2->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>symotech.id-COA</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .search-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .search-container input[type="text"] {
            width: calc(100% - 120px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .search-container button {
            padding: 10px 20px;
            background: #5cb85c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .search-container button:hover {
            background: #4cae4c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background: #5cb85c;
            color: #fff;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        tr:hover {
            background: #f1f1f1;
        }
        .add-button {
            display: block;
            width: fit-content;
            margin-bottom: 10px;
            padding: 10px 15px;
            background: #5cb85c;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .add-button:hover {
            background: #4cae4c;
        }
    </style>
   <script>
      function selectkodebooking(account_code, account_name) {
    if (window.opener) {
        // Kirim kedua nilai ke halaman utama
        window.opener.setbookingCode(account_code, account_name); // Kirim kedua nilai
        window.close(); // Tutup popup
    } else {
        alert('Tidak dapat mengirim data ke halaman utama.');
    }
}
   </script>
</head>
<body>
    <h1>COA</h1>
    <div class="search-container">
        <form method="GET">
            <input type="text" name="search" placeholder="NO COA atau Nama COA..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Cari</button>
        </form>
    </div>
    <table>
        <thead>
            <tr>
                <th>COA</th>
                <th>Nama</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['account_code']) ?></td>
                        <td><?= htmlspecialchars($row['account_name']) ?></td>
                        <td>
                            <button 
    class="select-button" 
    onclick="selectkodebooking('<?= htmlspecialchars($row['account_code']) ?>', '<?= htmlspecialchars($row['account_name']) ?>')">
    Pilih
</button>

                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Tidak ada data ditemukan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
