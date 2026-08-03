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

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

/* =======================
   DROPDOWN NEGARA
======================= */
$negaraOpt = [];
$q = $conn->query("SELECT negara FROM negara WHERE status='aktive' ORDER BY negara");
while ($r = $q->fetch_assoc()) {
    $negaraOpt[] = $r['negara'];
}

/* =======================
   HAPUS DATA
======================= */
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM tariffee WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: fee.php");
    exit();
}

/* =======================
   SIMPAN / UPDATE
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $negara  = trim($_POST['negara']);
    $tarif   = preg_replace('/[^\d.]/', '', $_POST['tarif']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if ($negara === '' || $tarif === '') {
        echo "<script>alert('Lengkapi semua data!');</script>";
    } else {
        if ($id_edit === 0) {
            $stmt = $conn->prepare(
                "INSERT INTO tariffee (negara, tarif) VALUES (?, ?)"
            );
            $stmt->bind_param("sd", $negara, $tarif);
            $stmt->execute();
            $stmt->close();

            echo "<script>alert('Fee berhasil ditambahkan');</script>";
        } else {
            $stmt = $conn->prepare(
                "UPDATE tariffee SET negara = ?, tarif = ? WHERE id = ?"
            );
            $stmt->bind_param("sdi", $negara, $tarif, $id_edit);
            $stmt->execute();
            $stmt->close();

            echo "<script>alert('Fee berhasil diperbarui'); window.location='fee.php';</script>";
        }
    }
}

/* =======================
   MODE EDIT
======================= */
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare(
        "SELECT id, negara, tarif FROM tariffee WHERE id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($eid, $enegara, $etarif);
    if ($stmt->fetch()) {
        $editData = [
            'id' => $eid,
            'negara' => $enegara,
            'tarif' => $etarif
        ];
    }
    $stmt->close();
}

/* =======================
   LIST DATA
======================= */
$result = $conn->query("SELECT * FROM tariffee ORDER BY negara");
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Master Fee</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; background:#f4f4f9; }
.container {
    width: 70%; margin: 30px auto; padding: 20px;
    background:#fff; border-radius:8px;
    box-shadow:0 4px 8px rgba(0,0,0,.1);
}
h1, h2 { text-align:center; }
label { display:block; margin-top:10px; }
input, select {
    width:100%; padding:10px; margin-top:5px;
    border:1px solid #ccc; border-radius:4px;
}
input[type=submit] {
    margin-top:15px;
    background:#28a745; color:#fff;
    border:none; cursor:pointer;
}
input[type=submit]:hover { background:#218838; }

table {
    width:100%; border-collapse:collapse; margin-top:20px;
}
th, td {
    border:1px solid #ddd; padding:10px;
}
th { background:#4CAF50; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }

.action-btn {
    padding:5px 10px; border-radius:4px;
    text-decoration:none; color:#fff;
}
.btn-edit { background:#007bff; }
.btn-edit:hover { background:#0056b3; }
.btn-del { background:#dc3545; }
.btn-del:hover { background:#b02a37; }

.home-icon {
    position:fixed; top:15px; left:15px;
    font-size:24px; color:maroon;
}
</style>
</head>

<body>
<a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>

<div class="container">
<h1><?= $editData ? 'Edit Fee' : 'Input Fee' ?></h1>

<form method="POST">
    <label>Negara</label>
    <select name="negara" required>
        <option value="">-- Pilih Negara --</option>
        <?php foreach ($negaraOpt as $n): ?>
            <option value="<?= htmlspecialchars($n) ?>"
                <?= ($editData && $editData['negara'] === $n) ? 'selected' : '' ?>>
                <?= htmlspecialchars($n) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Tarif Fee</label>
    <input type="number" step="0.01" min="0" name="tarif"
        value="<?= $editData ? $editData['tarif'] : '' ?>" required>

    <?php if ($editData): ?>
        <input type="hidden" name="id_edit" value="<?= $editData['id'] ?>">
    <?php endif; ?>

    <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
</form>

<h2>Daftar Fee</h2>
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Negara</th>
    <th>Tarif</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
<?php while ($r = $result->fetch_assoc()): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['negara']) ?></td>
    <td><?= number_format($r['tarif'], 2, ',', '.') ?></td>
    <td>
        <a class="action-btn btn-edit" href="?edit=<?= $r['id'] ?>">Edit</a>
        <a class="action-btn btn-del"
           href="?delete=<?= $r['id'] ?>"
           onclick="return confirm('Yakin hapus data fee ini?')">
           Hapus
        </a>
    </td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="4">Belum ada data fee</td></tr>
<?php endif; ?>
</tbody>
</table>

</div>
</body>
</html>

<?php $conn->close(); ?>
