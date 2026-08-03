<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

$allowed_referer_domain = "https://af.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);

if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$row = [];

if ($id > 0) {
    $query = $conn->prepare("SELECT id, debet FROM recuring WHERE id = ?");
    $query->bind_param("i", $id);
    $query->execute();
    $query->bind_result($id, $debet);

    if ($query->fetch()) {
        $row['id'] = $id;
        $row['debet'] = $debet;
    } else {
        echo "Data tidak ditemukan.";
        exit;
    }
    $query->close();
} else {
    echo "ID tidak valid.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominal = floatval($_POST['debet']); // Ambil nilai dari form input

    $update = $conn->prepare("UPDATE recuring SET debet = ?, kredit = ? WHERE id = ?");
    $update->bind_param("ddi", $nominal, $nominal, $id);

    if ($update->execute()) {
echo "<script>alert('Nominal berhasil diperbarui. Silakan refresh halaman detail recuring.'); window.close();</script>";

    } else {
        echo "<script>alert('Gagal memperbarui nominal.');</script>";
    }
    $update->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Nominal Asetrec</title>
</head>
<body>
    <h1>Edit Nominal Asetrec</h1>
    <form method="POST">
        <label for="debet">Nominal:</label>
        <input type="number" name="debet" id="debet" value="<?php echo htmlspecialchars($row['debet']); ?>" required>
        <br><br>
        <button type="submit">Update</button>
        <a href="asetrec.php">Batal</a>
    </form>
</body>
</html>
