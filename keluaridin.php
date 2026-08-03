<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

/* =======================
   KONEKSI DB
======================= */
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) die("Koneksi gagal");

/* =======================
   VALIDASI NIK
======================= */
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan');location='belumkeluarid.php';</script>";
    exit;
}

/* =======================
   UPLOAD DIR
======================= */
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

/* =======================
   FUNGSI UPLOAD
======================= */
function upload_file($key, $prefix, $dir) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== 0) {
        die("File wajib diunggah");
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) die("Format file tidak valid");

    $name = uniqid($prefix.'_') . '.' . $ext;
    $path = rtrim($dir,'/') . '/' . $name;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
        die("Upload gagal");
    }
    return $name;
}

/* =======================
   CEK BIAYA KELUAR ID (kodeby=10)
======================= */
$q = $conn->prepare("
    SELECT id, kodeby, nom
    FROM nikby
    WHERE nik=? AND klaim='0' AND kodeby='10'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$q->bind_result($nikby_id, $kodeby, $nilaiNom);
if (!$q->fetch()) {
    echo "<script>alert('Biaya keluar ID belum ada / sudah diklaim');location='home.php';</script>";
    exit;
}
$q->close();

/* =======================
   COA & NAMA BIAYA
======================= */
$q2 = $conn->prepare("SELECT COA, namaby FROM jenisbiaya WHERE id=?");
$q2->bind_param("i", $kodeby);
$q2->execute();
$q2->bind_result($coa_biaya, $nama_biaya);
$q2->fetch();
$q2->close();

/* =======================
   CABANG → LOCATION
======================= */
$qc = $conn->prepare("SELECT cabang FROM pendaftaran WHERE nik=?");
$qc->bind_param("s", $nik);
$qc->execute();
$qc->bind_result($location);
$qc->fetch();
$location = $location ?: "-";
$qc->close();

/* =======================
   KODE JURNAL
======================= */
$qm = $conn->query("SELECT MAX(id) AS mx FROM JM");
$r  = $qm->fetch_assoc();
$no = intval($r['mx'] ?? 0) + 1;
$kode_jurnal = "JM".date('Y').sprintf('%04d',$no);

/* ======================================================
   PROSES SIMPAN
====================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_keluar_id = $_POST['tanggal_keluar_id'];
    $Id_tki            = trim($_POST['Id_tki']);
    $edit              = "clouse";
    $minta             = "0";
    $alesan            = "";

    $file_id = upload_file('upload_idtki', 'keluarid', $upload_dir);

    $conn->begin_transaction();
    try {

        /* INSERT keluar_id */
        $in = $conn->prepare("
            INSERT INTO keluar_id
            (nik, Id_tki, upload_idtki, edit, minta, alesan)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $in->bind_param("ssssss", $nik, $Id_tki, $file_id, $edit, $minta, $alesan);
        $in->execute();

        /* UPDATE pendaftaran */
        $up = $conn->prepare("
            UPDATE pendaftaran SET tanggal_keluar_id=? WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_keluar_id, $nik);
        $up->execute();

        /* JURNAL DEBET */
        $ket = $nama_biaya." - ".$nik;
        $jd = $conn->prepare("
            INSERT INTO jurnal
            (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
            VALUES (?, ?, ?, ?, ?, 0, NULL, ?)
        ");
        $jd->bind_param("sssdis",
            $kode_jurnal,
            $tanggal_keluar_id,
            $ket,
            $coa_biaya,
            $nilaiNom,
            $location
        );
        $jd->execute();

        /* JURNAL KREDIT (13102) */
        $jk = $conn->prepare("
            INSERT INTO jurnal
            (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
            VALUES (?, ?, ?, '13102', 0, ?, NULL, ?)
        ");
        $jk->bind_param("ssdis",
            $kode_jurnal,
            $tanggal_keluar_id,
            $ket,
            $nilaiNom,
            $location
        );
        $jk->execute();

        /* UPDATE nikby → klaim */
        $uk = $conn->prepare("UPDATE nikby SET klaim=? WHERE id=?");
        $uk->bind_param("si", $kode_jurnal, $nikby_id);
        $uk->execute();

        /* SIMPAN JM */
        $sj = $conn->prepare("INSERT INTO JM (jurnal) VALUES (?)");
        $sj->bind_param("s", $kode_jurnal);
        $sj->execute();

        $conn->commit();

        echo "<script>
            alert('Keluar ID & jurnal berhasil disimpan');
            location='detail_pekerja.php?nik=".urlencode($nik)."';
        </script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Gagal: ".$e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Keluar ID TKI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 600px;
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
            background: #28a745;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background: #218838; }
        .home-icon1, .left-icon {
            position: absolute; top: 0; font-size: 24px; color: maroon;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    </div>

<div class="container">
    <h2>Input Data Keluar ID</h2>

    <form method="post" enctype="multipart/form-data">
        
        <label>Tanggal Keluar ID</label>
        <input type="date" name="tanggal_keluar_id" value="<?= date('Y-m-d') ?>" required>

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <label>ID TKI</label>
        <input type="text" name="Id_tki" required>

        <label>Upload ID TKI (jpg/png/jpeg/gif/pdf)</label>
        <input type="file" name="upload_idtki" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <input type="submit" value="Simpan Data">
    </form>
</div>
</body>
</html>

<?php $conn->close(); ?>
