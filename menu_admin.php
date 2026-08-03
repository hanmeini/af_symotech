<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/config.php';


// ✅ VALIDASI LOGIN
if (!isset($_SESSION['userid']) || !isset($_SESSION['bagian']) || !isset($_SESSION['jabatan'])) {
    header("Location: ../index.html");
    exit();
}

// ✅ AMBIL ENV
$servername  = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database    = getenv('DB_NAME');

$pdo = new PDO(
    "mysql:host=$servername;dbname=$database;charset=utf8mb4",
    $db_username,
    $db_password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// ✅ Ambil semua data
$menus   = $pdo->query("SELECT * FROM menu ORDER BY urutan ASC")->fetchAll();
$bagian  = $pdo->query("SELECT id, nama_bagian FROM bagian ORDER BY nama_bagian")->fetchAll();
$jabatan = $pdo->query("SELECT idj, jabatan FROM jabatan ORDER BY jabatan")->fetchAll();

// ✅ Tambah Menu
if (isset($_POST['action']) && $_POST['action'] === 'add_menu') {
    $stmt = $pdo->prepare("INSERT INTO menu (nama_menu, file_menu, icon_menu, urutan, aktif)
        VALUES (?, ?, ?, ?, ?)");

    $stmt->execute([
        $_POST['nama_menu'], $_POST['file_menu'], $_POST['icon_menu'],
        intval($_POST['urutan']), isset($_POST['aktif']) ? 1 : 0
    ]);

    // ✅ AUTO CREATE FILE
    $filepath = "../" . $_POST['file_menu'];
    if (!file_exists($filepath)) {
        file_put_contents($filepath, "<h1>".$_POST['nama_menu']."</h1>");
    }

    echo "<script>alert('✅ Menu berhasil ditambah'); location.href='menu_admin.php';</script>";
    exit;
}

// ✅ Hapus Menu
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM menu WHERE id_menu=?")->execute([$id]);
    $pdo->prepare("DELETE FROM akses_jabatan WHERE id_menu=?")->execute([$id]);

    echo "<script>alert('✅ Menu berhasil dihapus'); location.href='menu_admin.php';</script>";
    exit;
}

// ✅ Simpan Akses Menu
if (isset($_POST['action']) && $_POST['action'] === 'save_access') {
    $id_menu = intval($_POST['id_menu']);

    // hapus akses lama
    $pdo->prepare("DELETE FROM akses_jabatan WHERE id_menu=?")->execute([$id_menu]);

    // insert akses baru
    if (!empty($_POST['akses'])) {
        foreach ($_POST['akses'] as $ak) {
            list($id_bagian, $id_jabatan) = explode("-", $ak);

            $stmt = $pdo->prepare("INSERT INTO akses_jabatan (id_menu, id_bagian, id_jabatan)
                VALUES (?, ?, ?)");
            $stmt->execute([$id_menu, $id_bagian, $id_jabatan]);
        }
    }

    echo "<script>alert('✅ Akses menu berhasil diperbarui'); location.href='menu_admin.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>PANEL MENU ADMIN</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
body { font-family: Arial; background:#eef2f7; padding:20px; }
.box { background:white; padding:20px; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,.1); margin-bottom:25px; }
table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border-bottom:1px solid #ddd; }
th { background:#007bff; color:white; }
button { padding:6px 12px; background:#007bff; color:white; border:0; border-radius:6px; cursor:pointer; }
.badge { display:inline-block; background:#111; color:#fff; padding:4px 7px; border-radius:5px; font-size:12px; }
</style>
</head>
<body>

<h2>⚙️ PANEL MENU ADMIN</h2>

<div class="box">
<h3>➕ Tambah Menu</h3>
<form method="post">
<input type="hidden" name="action" value="add_menu">
Nama Menu: <input type="text" name="nama_menu" required><br><br>
File Menu: <input type="text" name="file_menu" required placeholder="example.php"><br><br>
Icon: <input type="text" name="icon_menu" placeholder="fa-solid fa-home"><br><br>
Urutan: <input type="number" name="urutan" min="1" value="1"><br><br>
<input type="checkbox" name="aktif" checked> Aktif<br><br>
<button type="submit">✅ Simpan</button>
</form>
</div>

<div class="box">
<h3>📋 Daftar Menu</h3>
<table>
<tr>
<th>No</th>
<th>Nama</th>
<th>File</th>
<th>Urutan</th>
<th>Status</th>
<th>Akses</th>
<th>Aksi</th>
</tr>
<?php $no=1; foreach ($menus as $m): ?>
<tr>
<td><?= $no++ ?></td>
<td><?= htmlspecialchars($m['nama_menu']) ?></td>
<td><?= $m['file_menu'] ?></td>
<td><?= $m['urutan'] ?></td>
<td><?= $m['aktif'] ? "✅ Aktif" : "❌" ?></td>

<td>
<a href="menu_admin_access.php?id=<?= $m['id_menu'] ?>"><button>🔑 Atur</button></a>
</td>

<td>
<a href="?delete=<?= $m['id_menu'] ?>" onclick="return confirm('Hapus menu?')">
<button style="background:red;">🗑 Hapus</button></a>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

</body>
</html>
