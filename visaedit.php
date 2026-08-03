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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumvisa.php';</script>";
    exit;
}

/* ================================
   AMBIL DATA VISA TERBARU
================================ */
$stmt = $conn->prepare("
    SELECT id, visa, tgl_visa, tglend_visa, edit, minta
    FROM visa
    WHERE nik = ?
    ORDER BY id DESC LIMIT 1
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<script>alert('Data visa tidak ditemukan'); window.location.href='belumvisa.php';</script>";
    exit;
}

$stmt->bind_result($id, $visa, $tgl_visa, $tglend_visa, $edit, $minta);
$stmt->fetch();
$stmt->close();


/* ================================
   PROSES UPDATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $visa_new       = $_POST['visa'] ?? '';
    $tgl_visa_new   = $_POST['tgl_visa'] ?? '';
    $tglend_new     = $_POST['tglend_visa'] ?? '';
    $edit_status    = "clouse";
    $minta_status   = "0";

    $up = $conn->prepare("
        UPDATE visa SET
            visa = ?,
            tgl_visa = ?,
            tglend_visa = ?,
            edit = ?,
            minta = ?
        WHERE id = ?
    ");

    $up->bind_param("sssssi",
        $visa_new,
        $tgl_visa_new,
        $tglend_new,
        $edit_status,
        $minta_status,
        $id
    );

    if ($up->execute()) {

        $upd2 = $conn->prepare("UPDATE pendaftaran SET tglvisa=? WHERE nik=?");
        $upd2->bind_param("ss", $tgl_visa_new, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data visa berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";

    } else {
        echo "<script>alert('Gagal update visa: {$up->error}');</script>";
    }

    $up->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Data Visa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body { font-family: Arial; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 650px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 4px; border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #28a745; color: white; font-weight: bold;
            border: none; margin-top: 20px; cursor: pointer; padding: 10px;
        }
        input[type="submit"]:hover {
            background: #218838;
        }
        .home-icon1, .left-icon {
            position: absolute; top: 10px; color: maroon; font-size: 24px;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>

<a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
<a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<div class="container">
    <h2>Edit Visa</h2>

    <form method="post">

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>Nomor Visa</label>
        <input type="text" name="visa" required value="<?= htmlspecialchars($visa) ?>">

        <label>Tanggal Terbit Visa</label>
        <input type="date" name="tgl_visa" required value="<?= htmlspecialchars($tgl_visa) ?>">

        <label>Tanggal Berakhir Visa</label>
        <input type="date" name="tglend_visa" required value="<?= htmlspecialchars($tglend_visa) ?>">

        <input type="submit" value="Perbarui Data">

    </form>
</div>

</body>
</html>
