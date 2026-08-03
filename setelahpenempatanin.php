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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='setelahpenempatan.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_setelah_penempatan = $_POST['tanggal_setelah_penempatan'] ?? '';
    $provider_jamsos_A = trim($_POST['provider_jamsos_A'] ?? '');
    $no_jamsos_A = trim($_POST['no_jamsos_A'] ?? '');
    $tgl_jamsos_A = $_POST['tgl_jamsos_A'] ?? '';
    $tglend_jamsos_A = $_POST['tglend_jamsos_A'] ?? '';
    $edit = 'clouse';

    $stmt = $conn->prepare("INSERT INTO setelah_penempatan 
        (nik, provider_jamsos_A, no_jamsos_A, tgl_jamsos_A, tglend_jamsos_A, edit) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nik, $provider_jamsos_A, $no_jamsos_A, $tgl_jamsos_A, $tglend_jamsos_A, $edit);

    if ($stmt->execute()) {
        // Update tanggal_setelah_penempatan di tabel pendaftaran berdasarkan input
        $update = $conn->prepare("UPDATE pendaftaran SET tanggal_setelah_penempatan = ? WHERE nik = ?");
        $update->bind_param("ss", $tanggal_setelah_penempatan, $nik);
        $update->execute();
        $update->close();

        echo "<script>
            alert('Data Setelah Penempatan berhasil disimpan');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>
            alert('Gagal menyimpan data');
            window.location.href='setelahpenempatan.php';
        </script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Setelah Penempatan</title>
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
            background: #28a745;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background: #218838;
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
        <h2>Form Setelah Penempatan</h2>
        <form method="post">
            <label>Tanggal Setelah Penempatan</label>
            <input type="date" name="tanggal_setelah_penempatan" required value="<?= date('Y-m-d') ?>">

            <label>NIK</label>
            <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

            <label>Provider JAMSOS A</label>
            <input type="text" name="provider_jamsos_A" required>

            <label>No JAMSOS A</label>
            <input type="text" name="no_jamsos_A" required>

            <label>Tanggal Mulai JAMSOS A</label>
            <input type="date" name="tgl_jamsos_A" required>

            <label>Tanggal Berakhir JAMSOS A</label>
            <input type="date" name="tglend_jamsos_A" required>

            <input type="submit" value="Simpan">
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
