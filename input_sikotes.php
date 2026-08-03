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

/* ==============================
   CEK BIAYA NIKBY (SIKOTES)
   kodeby = 5
============================== */
$q = $conn->prepare("
    SELECT id, kodeby, nom, tanggal 
    FROM nikby 
    WHERE nik=? AND klaim='0' AND kodeby='5'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$res = $q->get_result();

if ($res->num_rows == 0) {
    echo "<script>
        alert('Biaya sikotes belum diinput atau sudah diklaim');
        window.location='home.php';
    </script>";
    exit;
}
$dataNikby = $res->fetch_assoc();
$q->close();

/* ==============================
   COA & NAMA BIAYA (SIKOTES)
============================== */
$q2 = $conn->prepare("
    SELECT COA, namaby 
    FROM jenisbiaya 
    WHERE id=? 
    LIMIT 1
");
$q2->bind_param("i", $dataNikby['kodeby']);
$q2->execute();
$jb = $q2->get_result()->fetch_assoc();
$q2->close();

$coa_biaya  = $jb['COA'];
$nama_biaya = $jb['namaby'];

/* ==============================
   AMBIL CABANG -> LOCATION
============================== */
$qCab = $conn->prepare("
    SELECT cabang 
    FROM pendaftaran 
    WHERE nik=? 
    LIMIT 1
");
$qCab->bind_param("s", $nik);
$qCab->execute();
$rCab = $qCab->get_result()->fetch_assoc();
$location = $rCab ? $rCab['cabang'] : "-";
$qCab->close();

$nilaiNom     = $dataNikby['nom'];
$tanggalTrans = $dataNikby['tanggal'];

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* ==============================
   FUNGSI UPLOAD FILE (TETAP)
============================== */
function upload_file($file_key, $prefix, $upload_dir) {
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== 0) {
        die("File $file_key wajib diunggah.");
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file $file_key tidak valid.");
    }
    $filename = uniqid($prefix . '_', true) . '.' . $ext;
    $path = rtrim($upload_dir,'/') . '/' . $filename;
    if (!move_uploaded_file($_FILES[$file_key]['tmp_name'], $path)) {
        die("Gagal mengunggah file $file_key.");
    }
    return $filename;
}

/* =====================================================
   FORM SIKOTES
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_sikotes = $_POST['tanggal_sikotes'] ?? '';
    $tgl_siko        = $_POST['tgl_siko'] ?? '';
    $edit            = 'clouse';

    // Upload file sikotes
    $siko_file = upload_file('siko', 'siko', $upload_dir);

    // Insert sikotes
    $stmt = $conn->prepare("
        INSERT INTO sikotes 
        (nik, siko, tgl_siko, edit)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $nik, $siko_file, $tgl_siko, $edit);

    if ($stmt->execute()) {

        // Update pendaftaran
        $up = $conn->prepare("
            UPDATE pendaftaran 
            SET tanggal_sikotes=? 
            WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_sikotes, $nik);
        $up->execute();
        $up->close();

// ==============================
// KODE TRANSAKSI JURNAL
// ==============================
$qJM = $conn->query("SELECT MAX(id) AS max_nomor FROM JM");
$rowJM = $qJM->fetch_assoc();
$max_nomor = $rowJM['max_nomor'] ?? 0;

$nomor = intval($max_nomor) + 1;
$nomor_formatted = sprintf('%04d', $nomor);
$kode_transaksi = "JM" . date('Y') . $nomor_formatted;

// ==============================
// JURNAL - Debet
// ==============================
$ket_baris = $nama_biaya . " - " . $nik;
$ket_baris1 = $nama_biaya . " - " . $nik;

$j1 = $conn->prepare("
    INSERT INTO jurnal 
    (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
    VALUES (?, ?, ?, ?, ?, 0, NULL, ?)
");
$j1->bind_param("sssdis", 
    $kode_transaksi,
    $tanggal_kesehatan,
    $ket_baris,
    $coa_biaya,
    $nilaiNom,
    $location
);
$j1->execute();
$j1->close();

// ==============================
// JURNAL - Kredit (13102)
// ==============================
$j2 = $conn->prepare("
    INSERT INTO jurnal 
    (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
    VALUES (?, ?, ?, '13102', 0, ?, NULL, ?)
");
$j2->bind_param("sssss",
    $kode_transaksi,
    $tanggal_kesehatan,
    $ket_baris1,
    $nilaiNom,
    $location
);
$j2->execute();
$j2->close();

// ==============================
// UPDATE NIKBY → klaim
// ==============================
$up = $conn->prepare("UPDATE nikby SET klaim=? WHERE id=?");
$up->bind_param("si", $kode_transaksi, $dataNikby['id']);
$up->execute();
$up->close();


// Simpan kode transaksi di tabel JM
$sql1 = "INSERT INTO JM (jurnal) VALUES (?)";
$stmt = $conn->prepare($sql1);
$stmt->bind_param("s", $kode_transaksi);
$stmt->execute();

// Tutup statement dan koneksi
$stmt->close();
}
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data sikotes</title>
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
        input, select {
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
         .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
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
    <h2>Formulir Sikotes</h2>
   <form method="post" enctype="multipart/form-data">
        <label>Tanggal sikotes</label>
        <input type="date" name="tanggal_sikotes" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" name="nik" value="<?= htmlspecialchars($nik) ?>" readonly>
        
          <label>siko (jpg/png/jpeg/gif/pdf)</label>
        <input type="file" name="siko" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>Tanggal siko</label>
        <input type="date" name="tgl_siko" required>

      
        
       

        <input type="submit" value="Simpan">
    </form>
</div>
</body>
</html>
<?php $conn->close(); ?>
