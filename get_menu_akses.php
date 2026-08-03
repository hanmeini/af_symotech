<?php
require_once 'config.php';

$servername = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME');

// Buat koneksi PDO aman
try {
    $pdo = new PDO(
        "mysql:host=" . getenv('DB_HOST') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$id_bagian = isset($_GET['bagian']) ? (int)$_GET['bagian'] : 0;
$id_jabatan = isset($_GET['jabatan']) ? (int)$_GET['jabatan'] : 0;

if ($id_bagian === 0 || $id_jabatan === 0) {
    echo "<p style='text-align:center;color:#777;'>Silakan pilih bagian dan jabatan terlebih dahulu.</p>";
    exit;
}

// 1️⃣ Ambil menu yang sudah diatur untuk bagian tersebut
$sqlMenuBagian = "
    SELECT m.id_menu, m.nama_menu, m.file_menu, m.icon_menu
    FROM akses_bagian ab
    JOIN menu m ON ab.id_menu = m.id_menu
    WHERE ab.id_bagian = ?
    ORDER BY m.nama_menu
";
$stmt = $pdo->prepare($sqlMenuBagian);
$stmt->execute([$id_bagian]);
$menu = $stmt->fetchAll();

// 2️⃣ Ambil menu yang sudah diaktifkan untuk jabatan dalam bagian ini
$sqlAksesJabatan = "
    SELECT id_menu FROM akses_jabatan
    WHERE id_bagian = ? AND id_jabatan = ?
";
$stmt = $pdo->prepare($sqlAksesJabatan);
$stmt->execute([$id_bagian, $id_jabatan]);
$akses = array_column($stmt->fetchAll(), 'id_menu');

if (empty($menu)) {
    echo "<p style='text-align:center;color:#777;'>Belum ada menu yang diatur untuk bagian ini.</p>";
    exit;
}
?>

<table class="menu-table">
    <thead>
        <tr>
            <th style="width:60px;">Akses</th>
            <th>Nama Menu</th>
            <th>File</th>
            <th>Icon</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($menu as $m): ?>
        <tr>
            <td style="text-align:center;">
                <input type="checkbox" name="menu_id[]" value="<?= $m['id_menu'] ?>"
                    <?= in_array($m['id_menu'], $akses) ? 'checked' : '' ?>>
            </td>
            <td><?= htmlspecialchars($m['nama_menu']) ?></td>
            <td><?= htmlspecialchars($m['file_menu']) ?></td>
            <td><i class="<?= htmlspecialchars($m['icon_menu']) ?>"></i></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<style>
.menu-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    border-radius: 8px;
    overflow: hidden;
}
.menu-table th {
    background: #3498db;
    color: #fff;
    padding: 10px;
    text-align: left;
}
.menu-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}
.menu-table tr:hover {
    background: #f9f9f9;
}
.menu-table i {
    font-size: 1.2em;
    color: #444;
}
</style>