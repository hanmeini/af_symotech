<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumkesehatan2.php';</script>";
    exit;
}

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

// Fungsi upload file (opsional jika user ganti serkes)
function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        return null; // Tidak ada file baru
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file $file_key tidak valid. Harus jpg/jpeg/png/gif/pdf.");
    }
    $filename = uniqid($prefix . '_', true) . '.' . $ext;
    $path = rtrim($upload_dir, '/') . '/' . $filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal mengunggah file $file_key.");
    }
    return $filename;
}

// Ambil data kesehatan2
$stmt = $conn->prepare("SELECT provider_serkes, no_serkes, serkes, tgl_serkes, tglend_serkes, no_perjanjian, status_kes 
                        FROM kesehatan2 WHERE nik = ?");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data kesehatan 2 tidak ditemukan.'); window.location.href='belumkesehatan2.php';</script>";
    exit;
}

$stmt->bind_result($provider_serkes, $no_serkes, $serkes_file, $tgl_serkes, $tglend_serkes, $no_perjanjian, $status_kes);
$stmt->fetch();
$stmt->close();

// Ambil tanggal_kesehatan2 dari tabel pendaftaran
$stmt2 = $conn->prepare("SELECT tanggal_kesehatan2 FROM pendaftaran WHERE nik=?");
$stmt2->bind_param("s", $nik);
$stmt2->execute();
$stmt2->bind_result($tanggal_kesehatan2);
$stmt2->fetch();
$stmt2->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_kesehatan2 = $_POST['tanggal_kesehatan2'] ?? '';
    $provider_serkes   = trim($_POST['provider_serkes'] ?? '');
    $no_serkes         = trim($_POST['no_serkes'] ?? '');
    $tgl_serkes        = $_POST['tgl_serkes'] ?? '';
    $tglend_serkes     = $_POST['tglend_serkes'] ?? '';
    $no_perjanjian     = trim($_POST['no_perjanjian'] ?? '');
    $status_kes        = $_POST['status_kes'] ?? '';
    $edit              = 'clouse';

    // Cek apakah ada file baru
    $new_serkes = upload_file('serkes', 'serkes', $upload_dir);
    if ($new_serkes) {
        $serkes_file = $new_serkes;
    }

    // Update data kesehatan2
    $update = $conn->prepare("UPDATE kesehatan2 SET 
        provider_serkes=?, no_serkes=?, serkes=?, tgl_serkes=?, tglend_serkes=?, 
        no_perjanjian=?, status_kes=?, edit=? 
        WHERE nik=?");
    $update->bind_param("sssssssss", $provider_serkes, $no_serkes, $serkes_file, $tgl_serkes, $tglend_serkes, $no_perjanjian, $status_kes, $edit, $nik);

    if ($update->execute()) {
        // Update tanggal_kesehatan2 di tabel pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_kesehatan2=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_kesehatan2, $nik);
        $upd2->execute();
        $upd2->close();

        // CEK UNVIT
        if ($status_kes === 'unvit') {
            $upd_batal = $conn->prepare("UPDATE pendaftaran SET status='batal' WHERE nik=?");
            $upd_batal->bind_param("s", $nik);
            $upd_batal->execute();
            $upd_batal->close();
        }

        echo "<script>
            alert('Data kesehatan berhasil diperbarui.');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>alert('Gagal memperbarui data.'); window.location.href='belumkesehatan2.php';</script>";
    }

    $update->close();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Kesehatan 2</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input, select {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #007bff;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #0056b3;
        }
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
</div>
<div class="container">
    <h2>Edit Data Kesehatan 2</h2>
    <form method="post" enctype="multipart/form-data">
        <label>Tanggal Kesehatan</label>
        <input type="date" name="tanggal_kesehatan2" required value="<?= htmlspecialchars($tanggal_kesehatan2 ?: date('Y-m-d')) ?>">

        <label>NIK</label>
        <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>Provider Serkes</label>
        <input type="text" name="provider_serkes" required value="<?= htmlspecialchars($provider_serkes) ?>">

        <label>No Serkes</label>
        <input type="text" name="no_serkes" required value="<?= htmlspecialchars($no_serkes) ?>">

        <label>Serkes Saat Ini</label>
        <?php if ($serkes_file): ?>
            <p><a href="/pekerja/<?= htmlspecialchars($serkes_file) ?>" target="_blank">Lihat File</a></p>
        <?php endif; ?>
        <input type="file" name="serkes" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <label>Tanggal Serkes</label>
        <input type="date" name="tgl_serkes" required value="<?= htmlspecialchars($tgl_serkes) ?>">

        <label>Tanggal Berakhir</label>
        <input type="date" name="tglend_serkes" required value="<?= htmlspecialchars($tglend_serkes) ?>">

        <label>No Perjanjian</label>
        <input type="text" name="no_perjanjian" value="<?= htmlspecialchars($no_perjanjian) ?>">

        <label>Status Kesehatan</label>
        <select name="status_kes" required>
            <option value="">-- Pilih Status --</option>
            <option value="vit" <?= $status_kes === 'vit' ? 'selected' : '' ?>>Vit</option>
            <option value="pending" <?= $status_kes === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="unvit" <?= $status_kes === 'unvit' ? 'selected' : '' ?>>Unvit</option>
        </select>

        <input type="submit" value="Perbarui">
    </form>
</div>
</body>
</html>
<?php $conn->close(); ?>
