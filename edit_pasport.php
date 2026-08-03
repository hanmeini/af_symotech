<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='pasportidlist.php';</script>";
    exit;
}

// ✅ Ambil data paspor
$stmt = $conn->prepare("
    SELECT nama_paspor, no_paspor, pasport, tgl_paspor, tglend_paspor, penerbit_paspor 
    FROM pembuatan_paspor 
    WHERE nik = ? 
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data paspor tidak ditemukan'); window.location.href='pasportidlist.php';</script>";
    exit;
}

$stmt->bind_result($nama_paspor, $no_paspor, $pasport_lama, $tgl_paspor, $tglend_paspor, $penerbit_paspor);
$stmt->fetch();
$stmt->close();

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

function upload_pasport_file($file_key, $nik, $upload_dir) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        return false; // Tidak ada upload baru
    }

    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file paspor tidak valid.");
    }

    $filename = "paspor_" . $nik . "_" . time() . "." . $ext;
    $filepath = rtrim($upload_dir, "/") . "/" . $filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $filepath)) {
        die("Gagal upload file paspor baru.");
    }

    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_pembuatan_pasport = $_POST['tanggal_pembuatan_pasport'];
    $nama_paspor = trim($_POST['nama_paspor']);
    $no_paspor = trim($_POST['no_paspor']);
    $tgl_paspor = $_POST['tgl_paspor'];
    $tglend_paspor = $_POST['tglend_paspor'];
    $penerbit_paspor = trim($_POST['penerbit_paspor']);
    $edit = 'clouse';

    // ✅ Upload file baru (jika ada)
    $file_baru = upload_pasport_file("pasport", $nik, $upload_dir);

    if ($file_baru) {
        // Hapus file lama
        $old_file = rtrim($upload_dir, "/") . "/" . $pasport_lama;
        if (file_exists($old_file)) {
            unlink($old_file);
        }
        $pasport_final = $file_baru;
    } else {
        $pasport_final = $pasport_lama; // tetap pakai file lama
    }

    // ✅ Update tabel pembuatan_paspor
    $update = $conn->prepare("
        UPDATE pembuatan_paspor SET
            nama_paspor=?, no_paspor=?, pasport=?, 
            tgl_paspor=?, tglend_paspor=?, penerbit_paspor=?, edit=?
        WHERE nik=?
    ");

    $update->bind_param(
        "ssssssss",
        $nama_paspor, $no_paspor, $pasport_final,
        $tgl_paspor, $tglend_paspor, $penerbit_paspor, $edit,
        $nik
    );

    if ($update->execute()) {

        // ✅ Update tanggal di pendaftaran
        $upd2 = $conn->prepare("
            UPDATE pendaftaran 
            SET tanggal_pembuatan_pasport=? 
            WHERE nik=?
        ");
        $upd2->bind_param("ss", $tanggal_pembuatan_pasport, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data Paspor berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>alert('Gagal update data!');</script>";
    }

    $update->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Paspor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background:#f0f0f0; padding:20px; }
        .container {
            max-width: 700px; background:#fff; padding:20px; margin:auto;
            border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.1);
            position:relative;
        }
        label { font-weight:bold; margin-top:15px; display:block; }
        input { padding:8px; width:100%; border-radius:4px; border:1px solid #ccc; margin-top:5px; }
        input[type=submit] { background:#17a2b8; color:white; margin-top:20px; cursor:pointer; }
        input[type=submit]:hover { background:#138496; }
        .icon-home, .icon-back {
            position:absolute; top:10px; font-size:24px; color:maroon;
        }
        .icon-home { left:20px; }
        .icon-back { right:20px; }
    </style>
</head>
<body>

<a href="home.php" class="icon-home"><i class="fas fa-home"></i></a>
<a href="daftaruser.php" class="icon-back"><i class="fas fa-circle-left"></i></a>

<div class="container">

<h2>Edit Data Paspor</h2>

<form method="post" enctype="multipart/form-data">

    <label>Tanggal Pembuatan Paspor</label>
    <input type="date" name="tanggal_pembuatan_pasport" value="<?= date('Y-m-d') ?>" required>

    <label>NIK</label>
    <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

    <label>Nama di Paspor</label>
    <input type="text" name="nama_paspor" value="<?= htmlspecialchars($nama_paspor) ?>" required>

    <label>No Paspor</label>
    <input type="text" name="no_paspor" value="<?= htmlspecialchars($no_paspor) ?>" required>

    <label>File Paspor (baru jika ingin mengganti)</label>

    <?php if (!empty($pasport_lama)): ?>
        <p>File lama: 
            <a href="/pekerja/<?= htmlspecialchars($pasport_lama) ?>" target="_blank">Lihat File</a>
        </p>
    <?php endif; ?>
    
    <input type="file" name="pasport" accept=".jpg,.jpeg,.png,.gif,.pdf">

    <label>Tanggal Terbit Paspor</label>
    <input type="date" name="tgl_paspor" value="<?= htmlspecialchars($tgl_paspor) ?>" required>

    <label>Tanggal Berakhir Paspor</label>
    <input type="date" name="tglend_paspor" value="<?= htmlspecialchars($tglend_paspor) ?>" required>

    <label>Penerbit Paspor</label>
    <input type="text" name="penerbit_paspor" value="<?= htmlspecialchars($penerbit_paspor) ?>" required>

    <input type="submit" value="Perbarui">

</form>

</div>
</body>
</html>
<?php $conn->close(); ?>
