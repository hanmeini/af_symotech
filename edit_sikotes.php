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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumsikotes.php';</script>";
    exit;
}

/* ============================
   ✅ Ambil Data sikotes Terbaru
   ============================ */
$stmt = $conn->prepare("
    SELECT siko, tgl_siko, no_perjanjian, alesan 
    FROM sikotes 
    WHERE nik = ? 
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data sikotes tidak ditemukan'); window.location.href='belumsikotes.php';</script>";
    exit;
}

$stmt->bind_result($siko_file, $tgl_siko, $no_perjanjian, $alesan);
$stmt->fetch();
$stmt->close();

/* ============================
   ✅ PROSES UPDATE
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_sikotes = $_POST['tanggal_sikotes'] ?? '';
    $provider_siko   = trim($_POST['provider_siko'] ?? '');
    $no_siko         = trim($_POST['no_siko'] ?? '');
    $tgl_siko        = $_POST['tgl_siko'] ?? '';
    $no_perjanjian   = trim($_POST['no_perjanjian'] ?? '');
    $edit            = "clouse";

    // lokasi upload file
    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // default pakai file lama
    $siko_file_new = $siko_file;

    // ✅ Jika ada file baru
    if (!empty($_FILES['siko']['name']) && $_FILES['siko']['error'] === 0) {

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['siko']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Format file tidak valid');</script>";
        } else {

            $new_file = "siko_" . $nik . "_" . time() . "." . $ext;
            $target_file = rtrim($upload_dir, '/') . '/' . $new_file;

            if (move_uploaded_file($_FILES['siko']['tmp_name'], $target_file)) {
                $siko_file_new = $new_file;
            } else {
                echo "<script>alert('Gagal upload file baru, file lama tetap dipakai');</script>";
            }
        }
    }

    /* ============================
       ✅ UPDATE sikotes
       ============================ */
    $upd = $conn->prepare("
        UPDATE sikotes SET 
            siko = ?, 
            tgl_siko = ?, 
            no_perjanjian = ?, 
            edit = ? 
        WHERE nik = ?
        ORDER BY id DESC LIMIT 1
    ");

    $upd->bind_param("sssssss",
        $siko_file_new,
        $tgl_siko, $no_perjanjian, $edit, $nik
    );

    if ($upd->execute()) {

        // ✅ Update tanggal_sikotes di tabel pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tanggal_sikotes=? WHERE nik=?");
        $upd2->bind_param("ss", $tanggal_sikotes, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data sikotes berhasil diperbarui');
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
    <title>Edit Sikotes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background:#f0f0f0; padding:20px; }
        .container {
            max-width:600px; background:#fff; padding:20px;
            margin:auto; border-radius:8px; 
            box-shadow:0 0 10px rgba(0,0,0,0.1);
        }
        label { font-weight:bold; display:block; margin-top:15px; }
        input {
            width:100%; padding:8px; 
            border:1px solid #ccc; border-radius:4px; margin-top:5px;
        }
        input[type=submit] {
            background:#007bff; color:white; border:none;
            padding:10px; font-size:16px; cursor:pointer; margin-top:20px;
        }
        .home-icon1, .left-icon {
            position:absolute; top:0; font-size:24px; color:maroon;
        }
        .home-icon1 { left:10px; }
        .left-icon { right:10px; }
    </style>
</head>
<body>

<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="left-icon"><i class="fas fa-circle-left"></i></a>
</div>

<div class="container">
    <h2 style="text-align:center;">Edit Data Sikotes</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Sikotes</label>
        <input type="date" name="tanggal_sikotes" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

       

        <label>File Sikotes</label>
        <?php if (!empty($siko_file)): ?>
            <p>File Sekarang:
                <a href="/pekerja/<?= htmlspecialchars($siko_file) ?>" target="_blank">Lihat File</a>
            </p>
        <?php endif; ?>
        <input type="file" name="siko" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <label>Tanggal Dibuat Sikotes</label>
        <input type="date" name="tgl_siko" required value="<?= htmlspecialchars($tgl_siko) ?>">

        <label>No Perjanjian</label>
        <input type="text" name="no_perjanjian" value="<?= htmlspecialchars($no_perjanjian) ?>">

        <input type="submit" value="Perbarui">
    </form>

</div>

</body>
</html>

<?php $conn->close(); ?>
