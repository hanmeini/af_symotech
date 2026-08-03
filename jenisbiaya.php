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

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Proses simpan/update
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Permintaan tidak valid (CSRF).");
    }

    $namaby = trim($_POST['namaby']);
    $coa = trim($_POST['coa']);
    $status = trim($_POST['status']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if (empty($namaby) || empty($coa) || !in_array($status, ['0', '1'])) {
        echo "<script>alert('Semua kolom wajib diisi');</script>";
    } else {
        if ($id_edit == 0) {

            // INSERT
            $stmt = $conn->prepare("INSERT INTO jenisbiaya (namaby, COA, status) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $namaby, $coa, $status);

            if ($stmt->execute()) {
                echo "<script>alert('Data berhasil ditambahkan');</script>";
            } else {
                echo "<script>alert('Gagal menambahkan data');</script>";
            }
            $stmt->close();

        } else {

            // UPDATE
            $stmt = $conn->prepare("UPDATE jenisbiaya SET namaby = ?, COA = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sssi", $namaby, $coa, $status, $id_edit);

            if ($stmt->execute()) {
                echo "<script>alert('Data berhasil diperbarui'); window.location='" . $_SERVER['PHP_SELF'] . "';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui data');</script>";
            }

            $stmt->close();
        }
    }
}

// Mode edit
$editData = null;
if (isset($_GET['edit'])) {

    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id, namaby, COA, status FROM jenisbiaya WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($id, $namaby, $coa, $status);

    if ($stmt->fetch()) {
        $editData = [
            'id' => $id,
            'namaby' => $namaby,
            'coa' => $coa,
            'status' => $status
        ];
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM jenisbiaya ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input dan Tampil Jenis Biaya</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; }
        .container { width: 80%; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        form { margin-bottom: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], select { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { margin-top: 15px; background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-button { background-color: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
        .action-button:hover { background-color: #0056b3; }
        .home-icon, .left-icon {
            position: absolute;
            top: 10px;
            font-size: 24px;
            color: maroon;
        }
        .home-icon { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>

<div class="table-container">
    <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<div class="container">
    <h1><?= $editData ? 'Edit' : 'Input' ?> Jenis Biaya</h1>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <label>Nama Biaya:</label>
        <input type="text" name="namaby" value="<?= $editData ? htmlspecialchars($editData['namaby']) : '' ?>" required>

        <label>COA:</label>
        <input type="text" name="coa" value="<?= $editData ? htmlspecialchars($editData['coa']) : '' ?>" required>

        <label>Status:</label>
        <select name="status" required>
            <option value="0" <?= $editData && $editData['status'] === '0' ? 'selected' : '' ?>>Tidak Aktif</option>
            <option value="1" <?= $editData && $editData['status'] === '1' ? 'selected' : '' ?>>Aktif</option>
        </select>

        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['id'] ?>">
        <?php endif; ?>

        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <h2>Data Jenis Biaya</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Biaya</th>
                <th>COA</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>

        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['namaby']) ?></td>
                    <td><?= htmlspecialchars($row['COA']) ?></td>
                    <td><?= $row['status'] === '1' ? 'Aktif' : 'Tidak Aktif' ?></td>
                    <td><a class="action-button" href="?edit=<?= $row['id'] ?>">Edit</a></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">Tidak ada data</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>

<?php $conn->close(); ?>
