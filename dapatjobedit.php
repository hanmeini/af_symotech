<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'), getenv('DB_USER'),
    getenv('DB_PASS'), getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
if (!$nik) die("NIK tidak ditemukan");

// Ambil data terakhir
$result = $conn->query("SELECT * FROM dapat_job WHERE nik='$nik' ORDER BY id DESC LIMIT 1");
if ($result->num_rows === 0) die("Data tidak ditemukan");

$data = $result->fetch_assoc();

/* ======================
   ✅ PROSES UPDATE
   ====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_dapat_job = $_POST['tanggal_saat_job'];
    $idreg            = trim($_POST['idreg']);
    $no_job           = trim($_POST['no_job']);
    $jabatan          = trim($_POST['jabatan']);
    $gaji2            = trim($_POST['gaji2']);
    $negara           = trim($_POST['negara']);
    $nama_mitra       = trim($_POST['nama_mitra']);

    $no_perjanjian    = trim($_POST['no_perjanjian']);
    $tgl_terbit       = $_POST['tgl_terbit'];
    $durasi           = trim($_POST['durasi']);
    $tgl_akhir        = $_POST['tgl_akhir'];   
    $gaji             = trim($_POST['gaji']);

    $identitas_penguna = trim($_POST['identitas_penguna']);
    $nama_penguna     = trim($_POST['nama_penguna']);
    $alamat_penguna   = trim($_POST['alamat_penguna']);
    $kota_penguna     = trim($_POST['kota_penguna']);
    $telp_penguna     = trim($_POST['telp_penguna']);
    $email_penguna    = trim($_POST['email_penguna']);

    $edit = 'clouse';

    // ✅ UPDATE dapat_job
    $stmt = $conn->prepare("
        UPDATE dapat_job SET
            tanggal_dapat_job=?, idreg=?, no_job=?, jabatan=?, gaji2=?, negara=?, nama_mitra=?,
            no_perjanjian=?, tgl_terbit=?, durasi=?, tgl_akhir=?, gaji=?,
            identitas_penguna=?, nama_penguna=?, alamat_penguna=?, kota_penguna=?,
            telp_penguna=?, email_penguna=?, edit=?
        WHERE nik=?
    ");

    $stmt->bind_param(
        "ssssssssssssssssssss",
        $tanggal_dapat_job, $idreg, $no_job, $jabatan, $gaji2, $negara, $nama_mitra,
        $no_perjanjian, $tgl_terbit, $durasi, $tgl_akhir, $gaji,
        $identitas_penguna, $nama_penguna, $alamat_penguna, $kota_penguna,
        $telp_penguna, $email_penguna, $edit,
        $nik
    );

    if ($stmt->execute()) {

        // ✅ UPDATE ke pendaftaran
        $upd2 = $conn->prepare("
            UPDATE pendaftaran 
            SET tanggal_saat_job = ? 
            WHERE nik = ?
        ");
        $upd2->bind_param("ss", $tanggal_dapat_job, $nik);
        $upd2->execute();
        $upd2->close();

        echo "<script>
            alert('Data berhasil diperbarui');
            window.location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";

    } else {
        echo "<script>alert('Gagal update: " . $stmt->error . "');</script>";
    }

    $stmt->close();
}


?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Dapat Job</title>
<style>
body { font-family: Arial; background: #f0f0f0; padding: 20px; }
.container {
    background:#fff; padding:20px; margin:auto; max-width:700px;
    border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,0.1);
}
label { margin-top:12px; display:block; font-weight:bold; }
input { width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:4px; }
input[type=submit] { background:#007bff; color:#fff; margin-top:20px; font-weight:bold; }
</style>
</head>
<body>

<div class="container">
<h2>Edit Dapat Job</h2>

<form method="POST">

<label>Tanggal Dapat Job</label>
<input type="date" name="tanggal_saat_job" value="<?= $data['tanggal_dapat_job'] ?>">

<label>ID Reg</label>
<input type="text" name="idreg" value="<?= $data['idreg'] ?>">

<label>No Job</label>
<input type="text" name="no_job" value="<?= $data['no_job'] ?>">

<label>Jabatan</label>
<input type="text" name="jabatan" value="<?= $data['jabatan'] ?>">

<label>Gaji (kolom gaji2)</label>
<input type="text" name="gaji2" value="<?= $data['gaji2'] ?>">

<label>Negara</label>
<input type="text" name="negara" value="<?= $data['negara'] ?>">

<label>Nama Mitra</label>
<input type="text" name="nama_mitra" value="<?= $data['nama_mitra'] ?>">

<label>No Perjanjian</label>
<input type="text" name="no_perjanjian" value="<?= $data['no_perjanjian'] ?>">

<label>Tanggal Terbit</label>
<input type="date" name="tgl_terbit" value="<?= $data['tgl_terbit'] ?>">

<label>Durasi</label>
<input type="text" name="durasi" value="<?= $data['durasi'] ?>">

<label>Tanggal Akhir Kontrak (BARU)</label>
<input type="date" name="tgl_akhir" value="<?= $data['tgl_akhir'] ?>">

<label>Gaji</label>
<input type="text" name="gaji" value="<?= $data['gaji'] ?>">

<label>Identitas Pengguna</label>
<input type="text" name="identitas_penguna" value="<?= $data['identitas_penguna'] ?>">

<label>Nama Pengguna</label>
<input type="text" name="nama_penguna" value="<?= $data['nama_penguna'] ?>">

<label>Alamat Pengguna</label>
<input type="text" name="alamat_penguna" value="<?= $data['alamat_penguna'] ?>">

<label>Kota Pengguna</label>
<input type="text" name="kota_penguna" value="<?= $data['kota_penguna'] ?>">

<label>Telp Pengguna</label>
<input type="text" name="telp_penguna" value="<?= $data['telp_penguna'] ?>">

<label>Email Pengguna</label>
<input type="email" name="email_penguna" value="<?= $data['email_penguna'] ?>">

<input type="submit" value="Update">

</form>

</div>

</body>
</html>
