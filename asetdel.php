<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/aset_helper.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = function_exists('get_db_pass') ? get_db_pass() : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');
ensureAsetKelompokDelSchema($conn);

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) {
    die("ID aset tidak valid.");
}

$tahun_param = isset($_REQUEST['tahun']) ? (int)$_REQUEST['tahun'] : (int)date('Y');
if ($tahun_param < 1900 || $tahun_param > 2155) {
    $tahun_param = (int)date('Y');
}

// Proses POST: Update kolom del (tahun hapus) atau pulihkan ke NULL (tanpa DELETE baris)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'soft_delete';
    if ($action === 'restore') {
        $stmtUpd = $conn->prepare("UPDATE aset SET del = NULL WHERE id = ?");
        $stmtUpd->bind_param("i", $id);
        $stmtUpd->execute();
        $stmtUpd->close();
        $conn->close();
        header("Location: aset.php?tahun=" . $tahun_param);
        exit();
    }

    $tahun_del = isset($_POST['tahun_del']) ? (int)$_POST['tahun_del'] : $tahun_param;
    if ($tahun_del < 1900 || $tahun_del > 2155) {
        die("<script>alert('Tahun penghapusan tidak valid (1900 - 2155).'); window.history.back();</script>");
    }

    $stmtUpd = $conn->prepare("UPDATE aset SET del = ? WHERE id = ?");
    if (!$stmtUpd) {
        die("Error prepare: " . htmlspecialchars($conn->error));
    }
    $stmtUpd->bind_param("ii", $tahun_del, $id);
    if ($stmtUpd->execute()) {
        $stmtUpd->close();
        $conn->close();
        header("Location: aset.php?tahun=" . $tahun_del);
        exit();
    } else {
        $err = htmlspecialchars($stmtUpd->error);
        $stmtUpd->close();
        $conn->close();
        die("Gagal mengupdate tahun penghapusan aset: " . $err);
    }
}

// Dukungan fallback jika dipanggil dengan GET confirm=yes
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $stmtUpd = $conn->prepare("UPDATE aset SET del = ? WHERE id = ?");
    $stmtUpd->bind_param("ii", $tahun_param, $id);
    $stmtUpd->execute();
    $stmtUpd->close();
    $conn->close();
    header("Location: aset.php?tahun=" . $tahun_param);
    exit();
}

// Ambil data aset untuk form konfirmasi
$stmt = $conn->prepare("SELECT id, kode, nama, tanggal, beli, kelompok, del FROM aset WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$aset = $res ? $res->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$aset) {
    die("Aset tidak ditemukan.");
}

$default_del_year = ($aset['del'] !== null && (int)$aset['del'] >= 1900) ? (int)$aset['del'] : $tahun_param;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hapus Aset (Set Tahun Del)</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 20px;
        font-family: Arial, sans-serif;
        background: #f2f4f8;
        color: #1e293b;
    }
    .card {
        max-width: 560px;
        margin: 30px auto;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }
    .card-header {
        background: linear-gradient(135deg, #541020, #951f3b);
        color: #ffffff;
        padding: 18px 22px;
    }
    .card-header h2 {
        margin: 0;
        font-size: 18px;
    }
    .card-body {
        padding: 22px;
    }
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 18px;
        font-size: 13px;
    }
    .info-table td {
        padding: 7px 6px;
        border-bottom: 1px solid #f1f5f9;
    }
    .info-table td.lbl {
        width: 140px;
        color: #64748b;
        font-weight: bold;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-weight: bold;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .form-group input[type="number"] {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 15px;
        font-weight: bold;
    }
    .note-box {
        background: #fefce8;
        border: 1px solid #fde68a;
        color: #854d0e;
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 12px;
        line-height: 1.5;
        margin-bottom: 18px;
    }
    .actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
        font-weight: bold;
    }
    .btn-danger { background: #951f3b; color: #fff; }
    .btn-danger:hover { background: #7a0000; }
    .btn-success { background: #16834a; color: #fff; }
    .btn-secondary { background: #e2e8f0; color: #1e293b; }
</style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h2><i class="fa-solid fa-trash-can"></i> Tandai Penghapusan Aset (Soft Delete)</h2>
    </div>
    <div class="card-body">
        <table class="info-table">
            <tr>
                <td class="lbl">Kode Aset</td>
                <td><strong><?= htmlspecialchars($aset['kode']) ?></strong></td>
            </tr>
            <tr>
                <td class="lbl">Nama Aset</td>
                <td><?= htmlspecialchars($aset['nama']) ?></td>
            </tr>
            <tr>
                <td class="lbl">Kelompok</td>
                <td><?= htmlspecialchars($aset['kelompok'] ?? '-') ?></td>
            </tr>
            <tr>
                <td class="lbl">Tanggal Perolehan</td>
                <td><?= htmlspecialchars($aset['tanggal']) ?></td>
            </tr>
            <tr>
                <td class="lbl">Harga Perolehan</td>
                <td>Rp <?= number_format((float)$aset['beli'], 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td class="lbl">Status Del Saat Ini</td>
                <td><?= $aset['del'] !== null ? 'Tahun ' . htmlspecialchars((string)$aset['del']) : 'Aktif (NULL)' ?></td>
            </tr>
        </table>

        <div class="note-box">
            <i class="fa-solid fa-circle-info"></i>
            Penghapusan aset <strong>tidak menghapus data dari database</strong>, melainkan mengisi tahun pada kolom <code>del</code>.
            Aset tetap tampil hingga laporan tahun tersebut, dan tidak tampil pada tahun berikutnya.
        </div>

        <form method="POST" action="asetdel.php">
            <input type="hidden" name="id" value="<?= (int)$aset['id'] ?>">
            <input type="hidden" name="tahun" value="<?= (int)$tahun_param ?>">
            <div class="form-group">
                <label for="tahun_del">Tahun Penghapusan Aset (kolom del):</label>
                <input type="number" id="tahun_del" name="tahun_del" min="1900" max="2155" value="<?= (int)$default_del_year ?>" required>
            </div>
            <div class="actions">
                <button type="submit" name="action" value="soft_delete" class="btn btn-danger">
                    <i class="fa-solid fa-check"></i> Simpan Tahun Del
                </button>
                <?php if ($aset['del'] !== null): ?>
                <button type="submit" name="action" value="restore" class="btn btn-success" onclick="return confirm('Aktifkan kembali aset ini (hapus status del)?')">
                    <i class="fa-solid fa-rotate-left"></i> Aktifkan Kembali (NULL)
                </button>
                <?php endif; ?>
                <a href="aset.php?tahun=<?= (int)$tahun_param ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
