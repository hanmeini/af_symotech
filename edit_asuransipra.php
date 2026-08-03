<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumasuransipra.php';</script>";
    exit;
}

// ✅ Ambil data terakhir
$stmt = $conn->prepare("
    SELECT provider_asuransipra, no_asuransipra, asuransipra, 
           tgl_asuransipra, tglend_asuransipra, no_perjanjian
    FROM asuransipra 
    WHERE nik = ? 
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data Asuransi PRA tidak ditemukan.'); window.location.href='belumasuransipra.php';</script>";
    exit;
}

$stmt->bind_result(
    $provider_asuransipra,
    $no_asuransipra,
    $asuransipra_file,
    $tgl_asuransipra,
    $tglend_asuransipra,
    $no_perjanjian
);
$stmt->fetch();
$stmt->close();


// ✅ Jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_asuransipra = $_POST['tanggal_asuransipra'] ?? '';
    $provider_asuransipra = trim($_POST['provider_asuransipra'] ?? '');
    $no_asuransipra = trim($_POST['no_asuransipra'] ?? '');
    $tgl_asuransipra = $_POST['tgl_asuransipra'] ?? '';
    $tglend_asuransipra = $_POST['tglend_asuransipra'] ?? '';
    $no_perjanjian = trim($_POST['no_perjanjian'] ?? '');
    $edit = 'clouse';

    // Folder penyimpanan
    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // Default memakai file lama
    $asuransipra_file_new = $asuransipra_file;

    // ✅ Jika upload file baru
    if (!empty($_FILES['asuransipra']['name']) && $_FILES['asuransipra']['error'] === 0) {

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['asuransipra']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {

            $new_filename = "asuransipra_" . $nik . "_" . time() . "." . $ext;
            $target = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['asuransipra']['tmp_name'], $target)) {
                $asuransipra_file_new = $new_filename;
            } else {
                echo "<script>alert('Gagal upload file baru, tetap memakai file lama.');</script>";
            }

        } else {
            echo "<script>alert('Format file tidak valid.');</script>";
        }
    }

    // ✅ UPDATE DATA
    $update = $conn->prepare("
        UPDATE asuransipra SET 
            provider_asuransipra=?, no_asuransipra=?, asuransipra=?,
            tgl_asuransipra=?, tglend_asuransipra=?, no_perjanjian=?, edit=?
        WHERE nik=?
    ");

    $update->bind_param(
        "ssssssss",
        $provider_asuransipra,
        $no_asuransipra,
        $asuransipra_file_new,
        $tgl_asuransipra,
        $tglend_asuransipra,
        $no_perjanjian,
        $edit,
        $nik
    );

    if ($update->execute()) {

        // ✅ Update tanggal di tabel pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_asuransipra=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_asuransipra, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data Asuransi PRA berhasil diperbarui.');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
        exit;

    } else {
        echo "<script>alert('Gagal update: " . $update->error . "');</script>";
    }

    $update->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Asuransi PRA</title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .container {
            max-width: 650px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        label { display:block; margin-top:12px; font-weight:bold; }
        input, select {
            width:100%; padding:8px; margin-top:5px;
            border-radius:4px; border:1px solid #ccc;
        }
        input[type=submit] {
            background:#007bff; color:#fff; margin-top:20px; font-weight:bold;
        }
        a { color:blue; }
    </style>
</head>
<body>

<div class="container">
    <h2>Edit Asuransi PRA</h2>

<form method="POST" enctype="multipart/form-data">

<label>Tanggal Asuransi PRA</label>
<input type="date" name="tanggal_asuransipra" value="<?= date('Y-m-d') ?>">

<label>NIK</label>
<input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

<label>Provider Asuransi</label>
<input type="text" name="provider_asuransipra" value="<?= htmlspecialchars($provider_asuransipra) ?>">

<label>No Asuransi</label>
<input type="text" name="no_asuransipra" value="<?= htmlspecialchars($no_asuransipra) ?>">

<label>Tanggal Terbit</label>
<input type="date" name="tgl_asuransipra" value="<?= $tgl_asuransipra ?>">

<label>Tanggal Berakhir</label>
<input type="date" name="tglend_asuransipra" value="<?= $tglend_asuransipra ?>">

<label>No Perjanjian</label>
<input type="text" name="no_perjanjian" value="<?= htmlspecialchars($no_perjanjian) ?>">

<label>File Asuransi PRA</label>
<?php if (!empty($asuransipra_file)): ?>
    <p>File saat ini: <a href="/pekerja/<?= htmlspecialchars($asuransipra_file) ?>" target="_blank">Lihat File</a></p>
<?php endif; ?>
<input type="file" name="asuransipra" accept=".jpg,.jpeg,.png,.gif,.pdf">

<input type="submit" value="Update">

</form>
</div>

</body>
</html>

<?php $conn->close(); ?>
