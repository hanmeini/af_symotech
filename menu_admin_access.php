<?php
session_start();
require_once __DIR__ . '/config.php';


$id_menu = intval($_GET['id'] ?? 0);
if ($id_menu <= 0) die("Menu tidak valid");

$pdo = new PDO(
    "mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB_NAME').";charset=utf8mb4",
    getenv('DB_USER'),
    getenv('DB_PASS'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$menu = $pdo->query("SELECT * FROM menu WHERE id_menu=$id_menu")->fetch();
$bagian = $pdo->query("SELECT * FROM bagian ORDER BY nama_bagian")->fetchAll();
$jabatan = $pdo->query("SELECT * FROM jabatan ORDER BY jabatan")->fetchAll();
$akses   = $pdo->query("SELECT CONCAT(id_bagian,'-',id_jabatan) as akses FROM akses_jabatan WHERE id_menu=$id_menu")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html>
<head>
<title>Akses Menu</title>
<style>
body{font-family:Arial;padding:20px;background:#f5f5f5}
.box{background:white;padding:20px;border-radius:10px;max-width:700px;margin:auto}
button{padding:8px 12px;border:0;border-radius:6px;background:#007bff;color:white;cursor:pointer;margin-top:15px}
</style>
</head>
<body>
<div class="box">
<h2>🔑 Pengaturan Akses Menu</h2>
<h3><?= $menu['nama_menu'] ?></h3>

<form method="post" action="menu_admin.php">
<input type="hidden" name="action" value="save_access">
<input type="hidden" name="id_menu" value="<?= $id_menu ?>">

<table border="1" width="100%" cellspacing="0" cellpadding="6">
<tr><th>Bagian</th><th>Jabatan</th><th>Akses</th></tr>

<?php foreach ($bagian as $b): ?>
<?php foreach ($jabatan as $j): ?>
<tr>
<td><?= $b['nama_bagian'] ?></td>
<td><?= $j['jabatan'] ?></td>
<td>
<input type="checkbox"
name="akses[]"
value="<?= $b['id']."-".$j['idj'] ?>"
<?= in_array($b['id']."-".$j['idj'], $akses) ? "checked" : "" ?>
>
</td>
</tr>
<?php endforeach; ?>
<?php endforeach; ?>
</table>

<button type="submit">✅ Simpan Akses</button>
</form>
</div>
</body>
</html>
