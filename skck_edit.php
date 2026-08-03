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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumskck.php';</script>";
    exit;
}

/* ================================
   ✅ Ambil Data SKCK Terbaru
================================*/
$stmt = $conn->prepare("
    SELECT skck, tgl_skck, tglend_skck 
    FROM skck 
    WHERE nik = ? 
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data SKCK tidak ditemukan'); window.location.href='belumskck.php';</script>";
    exit;
}

$stmt->bind_result($skck_file, $tgl_skck, $tglend_skck);
$stmt->fetch();
$stmt->close();


/* ================================
   ✅ PROSES UPDATE
================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_skck  = $_POST['tanggal_skck'] ?? '';
    
    $tgl_skck      = $_POST['tgl_skck'] ?? '';
    $tglend_skck   = $_POST['tglend_skck'] ?? '';
    $edit          = "clouse";

    // lokasi upload file
    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // default pakai file lama
    $skck_file_new = $skck_file;

    // ✅ Jika user upload file baru
    if (!empty($_FILES['skck']['name']) && $_FILES['skck']['error'] === 0) {

        $allowed = ['jpg','jpeg','png','gif','pdf'];
        $ext = strtolower(pathinfo($_FILES['skck']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {

            $new_file = "skck_" . $nik . "_" . time() . "." . $ext;
            $target_file = rtrim($upload_dir, '/') . '/' . $new_file;

            if (move_uploaded_file($_FILES['skck']['tmp_name'], $target_file)) {
                $skck_file_new = $new_file;
            } else {
                echo "<script>alert('Gagal upload file baru, file lama digunakan');</script>";
            }

        } else {
            echo "<script>alert('Format file tidak valid');</script>";
        }
    }

    /* ✅ UPDATE DATABASE */
    $upd = $conn->prepare("
        UPDATE skck SET 
            skck = ?, 
            tgl_skck = ?, 
            tglend_skck = ?, 
            edit = ?
        WHERE nik = ?
        ORDER BY id DESC LIMIT 1
    ");

    $upd->bind_param("sssss", $skck_file_new, $tgl_skck, $tglend_skck, $edit, $nik);

    if ($upd->execute()) {

        // ✅ update tanggal_skck ke pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_skck=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_skck, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data SKCK berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";

    } else {
        echo "<script>alert('Gagal update: " . $upd->error . "');</script>";
    }

    $upd->close();
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data SKCK</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 600px; background:#fff; padding:20px;
            margin:auto; border-radius:8px;
            box-shadow:0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align:center; }
        label { font-weight:bold; margin-top:15px; display:block; }
        input, textarea {
            width:100%; padding:8px; border-radius:4px;
            border:1px solid #ccc; margin-top:5px;
        }
        input[type=submit] {
            background:#007bff; color:white; font-weight:bold;
            border:none; margin-top:20px; cursor:pointer;
        }
        .home-icon1, .left-icon {
            position:absolute; top:0; color:maroon; font-size:24px;
        }
        .home-icon1 { left:10px; }
        .left-icon { right:10px; }
    </style>
</head>
<body>

<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
</div>

<div class="container">
    <h2>Edit Data SKCK</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal SKCK</label>
        <input type="date" name="tanggal_skck" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        

        <label>File SKCK</label>
        <?php if (!empty($skck_file)): ?>
            <p>File saat ini:
                <a href="/pekerja/<?= htmlspecialchars($skck_file) ?>" target="_blank">Lihat</a>
            </p>
        <?php endif; ?>
        <input type="file" name="skck" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <label>Tanggal Terbit SKCK</label>
        <input type="date" name="tgl_skck" required value="<?= htmlspecialchars($tgl_skck) ?>">

        <label>Tanggal Berakhir SKCK</label>
        <input type="date" name="tglend_skck" required value="<?= htmlspecialchars($tglend_skck) ?>">

        <input type="submit" value="Perbarui">
    </form>

</div>

</body>
</html>

<?php $conn->close(); ?>
