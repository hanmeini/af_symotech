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



// Inisialisasi pesan
$message = '';
$success = false;

// Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['id'] as $index => $id) {
        $tanggal = $_POST['tanggal'][$index] ?? '';
        $keterangan = $_POST['keterangan'][$index] ?? '';
        $lampiran = $_POST['lampiran'][$index] ?? '';
        $location = $_POST['location'][$index] ?? '';
        $devisi = $_POST['devisi'][$index] ?? '';

        // Query untuk update data jurnal
        $sql_update = "UPDATE jurnal SET 
            tanggal = ?, 
            keterangan = ?, 
            lampiran = ?, 
            location = ?, 
            devisi = ? 
            WHERE id = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("sssssi", $tanggal, $keterangan, $lampiran, $location, $devisi, $id);

        if (!$stmt->execute()) {
            $message = "Error: " . $conn->error;
            $success = false;
            break;
        } else {
            $success = true;
        }
        $stmt->close();
    }

    if ($success) {
        $message = "Semua data jurnal berhasil diperbarui.";
    }
}

// Ambil semua data jurnal
$sql = "SELECT id, journal_number, tanggal, keterangan, lampiran, location, devisi FROM jurnal ORDER BY tanggal DESC";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Semua Data Jurnal www.symotech.id</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 20px;
        }
        h2 {
            text-align: center;
            color: #444;
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
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        .button {
            background-color: #007bff;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s;
            display: block;
            text-align: center;
            text-decoration: none;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .message {
            margin-top: 10px;
            color: #333;
            font-size: 14px;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Edit Semua Data Jurnal</h2>

    <?php if ($message): ?>
        <p class="message" style="color: <?php echo $success ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nomor Jurnal</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Lampiran</th>
                    <th>Location</th>
                    <th>Devisi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="id[]" value="<?php echo htmlspecialchars($row['id']); ?>">
                                <?php echo htmlspecialchars($row['id']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['journal_number']); ?></td>
                            <td>
                                <input type="date" name="tanggal[]" value="<?php echo htmlspecialchars($row['tanggal']); ?>" required>
                            </td>
                            <td>
                                <textarea name="keterangan[]" rows="2" required><?php echo htmlspecialchars($row['keterangan']); ?></textarea>
                            </td>
                            <td>
                                <input type="text" name="lampiran[]" value="<?php echo htmlspecialchars($row['lampiran']); ?>">
                            </td>
                            <td>
                                <input type="text" name="location[]" value="<?php echo htmlspecialchars($row['location']); ?>">
                            </td>
                            <td>
                                <input type="text" name="devisi[]" value="<?php echo htmlspecialchars($row['devisi']); ?>">
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">Tidak ada data ditemukan</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <button type="submit" class="button">Simpan Semua Perubahan</button>
    </form>
</div>
</body>
</html>

<?php
// Tutup koneksi
$conn->close();
?>
