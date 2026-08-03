<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

$allowed_referer_domain = "https://af.symotech.id";
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

if (!isset($_SESSION['userid'])) {
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

// Toggle status aktif/nonaktif
if (isset($_GET['toggle'])) {
    $id_toggle = intval($_GET['toggle']);
    $stmt = $conn->prepare("SELECT status FROM negara WHERE idn = ?");
    $stmt->bind_param("i", $id_toggle);
    $stmt->execute();
    $stmt->bind_result($current_status);
    if ($stmt->fetch()) {
        $new_status = ($current_status === 'aktive') ? 'nonaktive' : 'aktive';
    }
    $stmt->close();

    if (isset($new_status)) {
        $stmt = $conn->prepare("UPDATE negara SET status = ? WHERE idn = ?");
        $stmt->bind_param("si", $new_status, $id_toggle);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: negara.php");
    exit();
}

// Simpan data baru atau update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $negara = trim($_POST['negara']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if (empty($negara)) {
        echo "<script>alert('Nama negara tidak boleh kosong');</script>";
    } else {
        if ($id_edit == 0) {
            $stmt = $conn->prepare("INSERT INTO negara (negara, status) VALUES (?, 'aktive')");
            $stmt->bind_param("s", $negara);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Data negara berhasil ditambahkan');</script>";
        } else {
            $stmt = $conn->prepare("UPDATE negara SET negara = ? WHERE idn = ?");
            $stmt->bind_param("si", $negara, $id_edit);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Data negara berhasil diperbarui'); window.location='negara.php';</script>";
        }
    }
}

// Edit mode
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT idn, negara FROM negara WHERE idn = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($idn, $negara);
    if ($stmt->fetch()) {
        $editData = [
            'idn' => $idn,
            'negara' => $negara
        ];
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM negara ORDER BY negara ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input dan Tampil Data Negara</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; }
        .container { width: 80%; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        form { margin-bottom: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { margin-top: 15px; background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-button { background-color: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; margin-right: 5px; }
        .action-button:hover { background-color: #0056b3; }
        .status-active { color: green; font-weight: bold; }
        .status-nonactive { color: red; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h1><?= $editData ? 'Edit' : 'Input' ?> Negara</h1>
    <form method="POST">
        <label>Nama Negara:</label>
        <input type="text" name="negara" value="<?= $editData ? htmlspecialchars($editData['negara']) : '' ?>" required>
        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['idn'] ?>">
        <?php endif; ?>
        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <h2>Daftar Negara</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Negara</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['idn'] ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td class="<?= $row['status'] === 'aktive' ? 'status-active' : 'status-nonactive' ?>">
                        <?= $row['status'] ?>
                    </td>
                    <td>
                        
                        <a class="action-button" href="?toggle=<?= $row['idn'] ?>" onclick="return confirm('Ubah status negara ini?')">
                            <?= $row['status'] === 'aktive' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">Tidak ada data</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
