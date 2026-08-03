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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='keluarid.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_keluar_id = $_POST['tanggal_keluar_id'] ?? '';
    $provider_jamsos = trim($_POST['provider_jamsos'] ?? '');
    $no_jamsos = trim($_POST['no_jamsos'] ?? '');
    $tgl_jamsos = $_POST['tgl_jamsos'] ?? '';
    $tglend_jamsos = $_POST['tglend_jamsos'] ?? '';
    $edit = 'clouse';

    $stmt = $conn->prepare("INSERT INTO keluar_id 
        (nik, provider_jamsos, no_jamsos, tgl_jamsos, tglend_jamsos, edit) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nik, $provider_jamsos, $no_jamsos, $tgl_jamsos, $tglend_jamsos, $edit);

    if ($stmt->execute()) {
        // Update tanggal_keluar_id di tabel pendaftaran berdasarkan input
        $update = $conn->prepare("UPDATE pendaftaran SET tanggal_keluar_id = ? WHERE nik = ?");
        $update->bind_param("ss", $tanggal_keluar_id, $nik);
        $update->execute();
        $update->close();

        echo "<script>
            alert('Data Keluar ID berhasil disimpan');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>
            alert('Gagal menyimpan data');
            window.location.href='keluarid.php';
        </script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Keluar ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 700px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #dc3545;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #c82333;
        }
        .icon-home, .icon-back {
            position: absolute;
            top: 10px;
            font-size: 24px;
            color: maroon;
        }
        .icon-home { left: 20px; }
        .icon-back { right: 20px; }
    </style>
</head>
<body>
    <a href="home.php" class="icon-home"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="icon-back"><i class="fas fa-circle-left"></i></a>

    <div class="container">
        <h2>Form Keluar ID</h2>
        <form method="post">
            <label>Tanggal Keluar ID</label>
            <input type="date" name="tanggal_keluar_id" required value="<?= date('Y-m-d') ?>">

            <label>NIK</label>
            <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

            <label>Provider JAMSOS</label>
            <input type="text" name="provider_jamsos">

            <label>No JAMSOS</label>
            <input type="text" name="no_jamsos" required>

            <label>Tanggal JAMSOS</label>
            <input type="date" name="tgl_jamsos" required>

            <label>Tanggal Berakhir JAMSOS</label>
            <input type="date" name="tglend_jamsos" required>

            <input type="submit" value="Simpan">
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
