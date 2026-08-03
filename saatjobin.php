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
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='saatjob.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal_saat_job = $_POST['tanggal_saat_job'] ?? '';
    $idreg = trim($_POST['idreg'] ?? '');
    $no_perjanjian = trim($_POST['no_perjanjian'] ?? '');
    $tgl_terbit = $_POST['tgl_terbit'] ?? '';
    $durasi = trim($_POST['durasi'] ?? '');
    $gaji = trim($_POST['gaji'] ?? '');
    $identitas_penguna = trim($_POST['identitas_penguna'] ?? '');
    $nama_penguna = trim($_POST['nama_penguna'] ?? '');
    $alamat_penguna = trim($_POST['alamat_penguna'] ?? '');
    $kota_penguna = trim($_POST['kota_penguna'] ?? '');
    $telp_penguna = trim($_POST['telp_penguna'] ?? '');
    $email_penguna = trim($_POST['email_penguna'] ?? '');
    $edit = 'clouse';

    $stmt = $conn->prepare("INSERT INTO saat_job 
        (nik, idreg, no_perjanjian, tgl_terbit, durasi, gaji, identitas_penguna, nama_penguna, alamat_penguna, kota_penguna, telp_penguna, email_penguna, edit) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssssss", $nik, $idreg, $no_perjanjian, $tgl_terbit, $durasi, $gaji, $identitas_penguna, $nama_penguna, $alamat_penguna, $kota_penguna, $telp_penguna, $email_penguna, $edit);

    if ($stmt->execute()) {
        // Update tanggal_saat_job di tabel pendaftaran dari input
        $update = $conn->prepare("UPDATE pendaftaran SET tanggal_saat_job = ? WHERE nik = ?");
        $update->bind_param("ss", $tanggal_saat_job, $nik);
        $update->execute();
        $update->close();

        echo "<script>
            alert('Data saat job berhasil disimpan');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
    } else {
        echo "<script>
            alert('Gagal menyimpan data');
            window.location.href='saatjob.php';
        </script>";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data Saat Job</title>
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
        .home-icon1, .back-icon {
            position: absolute;
            top: 10px;
            font-size: 24px;
            color: maroon;
        }
        .home-icon1 { left: 20px; }
        .back-icon { right: 20px; }
    </style>
</head>
<body>
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="back-icon"><i class="fas fa-circle-left"></i></a>

    <div class="container">
        
        <h2>Form Saat Job</h2>
        <form method="post">
            <label>Tanggal Saat Job</label>
            <input type="date" name="tanggal_saat_job" required value="<?= date('Y-m-d') ?>">

            <label>NIK</label>
            <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>

            <label>ID Reg</label>
            <input type="text" name="idreg" required>

            <label>No Perjanjian</label>
            <input type="text" name="no_perjanjian">

            <label>Tanggal Terbit</label>
            <input type="date" name="tgl_terbit">

            <label>Durasi</label>
            <input type="text" name="durasi" required>

            <label>Gaji</label>
            <input type="text" name="gaji">

            <label>Identitas Pengguna</label>
            <input type="text" name="identitas_penguna">

            <label>Nama Pengguna</label>
            <input type="text" name="nama_penguna">

            <label>Alamat Pengguna</label>
            <input type="text" name="alamat_penguna">

            <label>Kota Pengguna</label>
            <input type="text" name="kota_penguna">

            <label>Telp Pengguna</label>
            <input type="text" name="telp_penguna">

            <label>Email Pengguna</label>
            <input type="email" name="email_penguna">

            <input type="submit" value="Simpan">
        </form>
    </div>
</body>
</html>
<?php $conn->close(); ?>
