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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumpenerbangan.php';</script>";
    exit;
}

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* =====================================
   AMBIL DATA TERAKHIR
===================================== */
$stmt = $conn->prepare("
    SELECT id, no_booking, tiket, tgl, edit
    FROM penerbangan
    WHERE nik = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data penerbangan tidak ditemukan'); window.location.href='belumpenerbangan.php';</script>";
    exit;
}

$stmt->bind_result($id, $no_booking, $tiket, $tgl, $edit);
$stmt->fetch();
$stmt->close();


/* =====================================
    FUNCTION UPLOAD
===================================== */
function upload_file($file_key, $prefix, $upload_dir)
{
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        return null; // tidak wajib upload saat edit
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format file tidak valid. Harus jpg/jpeg/png/gif/pdf.");
    }

    $filename = uniqid($prefix . "_", true) . "." . $ext;
    $path = rtrim($upload_dir, "/") . "/" . $filename;

    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal upload file");
    }

    return $filename;
}


/* =====================================
   PROSES UPDATE
===================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $no_booking_new = $_POST['no_booking'] ?? '';
    $tgl_new        = $_POST['tgl'] ?? '';
    $edit_status    = "clouse";

    // Upload baru? jika tidak → pakai file lama
    $tiket_new = upload_file('tiket', 'tiket', $upload_dir);
    if (!$tiket_new) {
        $tiket_new = $tiket;
    }

    $up = $conn->prepare("
        UPDATE penerbangan SET
            no_booking = ?,
            tiket = ?,
            tgl = ?,
            edit = ?
        WHERE id = ?
    ");
    $up->bind_param("ssssi",
        $no_booking_new,
        $tiket_new,
        $tgl_new,
        $edit_status,
        $id
    );

    if ($up->execute()) {

        // Update tgl penerbangan di tabel pendaftaran
        $upd2 = $conn->prepare("UPDATE pendaftaran SET tglterbang=? WHERE nik=?");
        $upd2->bind_param("ss", $tgl_new, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data penerbangan berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";

    } else {
        echo "<script>alert('Gagal update: {$up->error}');</script>";
    }

    $up->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Penerbangan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 650px; background: #fff; padding: 20px; margin: auto;
            border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 4px; border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #007bff; color: white; border: none;
            margin-top: 20px; padding: 10px; cursor: pointer;
        }
        input[type="submit"]:hover { background: #0056b3; }
        .btn-view {
            display: inline-block; background: green; padding: 6px 12px;
            color: white; text-decoration: none; border-radius: 5px; margin-top: 8px;
        }
        .home-icon1, .left-icon {
            position: absolute; top: 10px; font-size: 24px; color: maroon;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>

<body>

<a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
<a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<div class="container">
    <h2>Edit Data Penerbangan</h2>

    <form method="post" enctype="multipart/form-data">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>No Booking</label>
        <input type="text" name="no_booking" required value="<?= htmlspecialchars($no_booking) ?>">

        <label>Tanggal Terbang</label>
        <input type="date" name="tgl" required value="<?= htmlspecialchars($tgl) ?>">

        <label>Tiket Baru (Opsional)</label>
        <input type="file" name="tiket" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <?php if ($tiket): ?>
            <p>
                File Saat Ini:
                <a class="btn-view" href="/pekerja/<?= htmlspecialchars($tiket) ?>" target="_blank">Lihat Tiket</a>
            </p>
        <?php endif; ?>

        <input type="submit" value="Perbarui Data">

    </form>
</div>

</body>
</html>
