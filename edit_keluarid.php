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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumkeluarid.php';</script>";
    exit;
}

/* ============================
   ✅ Ambil Data keluar_id Terbaru
   ============================ */
$stmt = $conn->prepare("SELECT Id_tki, upload_idtki, alesan 
                        FROM keluar_id 
                        WHERE nik = ? 
                        ORDER BY id DESC LIMIT 1");

$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data Keluar ID tidak ditemukan'); window.location.href='belumkeluarid.php';</script>";
    exit;
}

$stmt->bind_result($Id_tki, $upload_idtki, $alesan);
$stmt->fetch();
$stmt->close();

/* ============================
   ✅ PROSES UPDATE
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_keluar_id = $_POST['tanggal_keluar_id'] ?? '';
    $Id_tki            = trim($_POST['Id_tki'] ?? '');
    $edit              = "clouse";

    // lokasi upload file
    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // default pakai file lama
    $upload_idtki_new = $upload_idtki;

    // Jika ada file baru diupload
    if (!empty($_FILES['upload_idtki']['name']) && $_FILES['upload_idtki']['error'] === 0) {

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['upload_idtki']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Format file tidak valid');</script>";
        } else {
            $new_file = "idtki_" . $nik . "_" . time() . "." . $ext;
            $target_file = rtrim($upload_dir, '/') . '/' . $new_file;

            if (move_uploaded_file($_FILES['upload_idtki']['tmp_name'], $target_file)) {
                $upload_idtki_new = $new_file;
            } else {
                echo "<script>alert('Gagal upload file baru, file lama tetap dipakai');</script>";
            }
        }
    }

    /* ============================
       ✅ UPDATE keluar_id
       ============================ */
    $upd = $conn->prepare("
        UPDATE keluar_id SET 
            Id_tki = ?, 
            upload_idtki = ?, 
            edit = ? 
        WHERE nik = ?
        ORDER BY id DESC LIMIT 1
    ");
    $upd->bind_param("ssss", $Id_tki, $upload_idtki_new, $edit, $nik);

    if ($upd->execute()) {

        // ✅ Update pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_keluar_id=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_keluar_id, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data Keluar ID berhasil diperbarui');
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
    <title>Edit Keluar ID</title>
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
        input {
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
    <h2>Edit Data Keluar ID</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Keluar ID</label>
        <input type="date" name="tanggal_keluar_id" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>ID TKI</label>
        <input type="text" name="Id_tki" required value="<?= htmlspecialchars($Id_tki) ?>">

        <label>File ID TKI</label>
        <?php if (!empty($upload_idtki)): ?>
            <p>File Saat Ini:
                <a href="/pekerja/<?= htmlspecialchars($upload_idtki) ?>" target="_blank">Lihat</a>
            </p>
        <?php endif; ?>

        <input type="file" name="upload_idtki" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <input type="submit" value="Perbarui Data">
    </form>

</div>

</body>
</html>

<?php $conn->close(); ?>
