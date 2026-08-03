<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
$pdo = pdo_connect();
if (!isset($_SESSION['userid']) || !is_admin()) { header("Location: index.html"); exit; }

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add') {
        $nama = trim($_POST['nama_menu']);
        $file = trim($_POST['file_menu']);
        $icon = trim($_POST['icon_menu']);
        $urutan = intval($_POST['urutan'] ?? 0);
        $aktif = isset($_POST['aktif']) ? 1 : 0;
        $qr = $_POST['query_red'] ?: null;
        $qy = $_POST['query_yellow'] ?: null;
        $isloc = isset($_POST['is_location_filtered']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO menu (nama_menu,file_menu,icon_menu,query_red,query_yellow,is_location_filtered,aktif,urutan) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$nama,$file,$icon,$qr,$qy,$isloc,$aktif,$urutan]);
        $msg = 'Menu ditambahkan';
    } elseif ($_POST['action'] === 'delete') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM menu WHERE id = ?")->execute([$id]);
        $msg = 'Menu dihapus';
    }
}

$menus = $pdo->query("SELECT * FROM menu ORDER BY urutan ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html><head><meta charset="utf-8"><title>Manage Menu</title></head><body>
<h2>Manajemen Menu</h2>
<?php if ($msg) echo "<div style='color:green;'>".htmlspecialchars($msg)."</div>"; ?>
<form method="post" style="border:1px solid #eee;padding:12px;margin-bottom:12px">
  <input type="hidden" name="action" value="add">
  <label>Nama Menu</label><br><input name="nama_menu" required style="width:100%"><br>
  <label>File Menu</label><br><input name="file_menu" required style="width:100%"><br>
  <label>Icon (FA class)</label><br><input name="icon_menu" style="width:100%"><br>
  <label>Query (red)</label><br><textarea name="query_red" style="width:100%" placeholder="SELECT COUNT(*) FROM pendaftaran WHERE status='aktive' AND tanggal_kesehatan = 0"></textarea><br>
  <label>Query (yellow)</label><br><textarea name="query_yellow" style="width:100%" placeholder="SELECT COUNT(*) FROM kesehatan WHERE edit='open'"></textarea><br>
  <label><input type="checkbox" name="is_location_filtered" checked> Filter by location (menambahkan AND location = :loc)</label><br>
  <label><input type="checkbox" name="aktif" checked> Aktif</label>
  <label>Urutan<br><input type="number" name="urutan" value="0"></label><br>
  <button type="submit">Tambah Menu</button>
</form>

<h3>Daftar Menu</h3>
<table border="1" cellpadding="6" cellspacing="0">
<tr><th>ID</th><th>Nama</th><th>File</th><th>Icon</th><th>Red</th><th>Yellow</th><th>Aktif</th><th>Aksi</th></tr>
<?php foreach ($menus as $m): ?>
<tr>
  <td><?= $m['id'] ?></td>
  <td><?= htmlspecialchars($m['nama_menu']) ?></td>
  <td><?= htmlspecialchars($m['file_menu']) ?></td>
  <td><?= htmlspecialchars($m['icon_menu']) ?></td>
  <td style="max-width:300px;white-space:pre-wrap;font-size:12px"><?= htmlspecialchars($m['query_red']) ?></td>
  <td style="max-width:300px;white-space:pre-wrap;font-size:12px"><?= htmlspecialchars($m['query_yellow']) ?></td>
  <td><?= $m['aktif'] ? 'Ya' : 'Tidak' ?></td>
  <td>
    <form method="post" style="display:inline" onsubmit="return confirm('Hapus menu?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $m['id'] ?>">
      <button type="submit">Hapus</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
</body></html>
