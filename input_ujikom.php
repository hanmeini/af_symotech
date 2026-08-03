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
    echo "<script>alert('NIK tidak ditemukan'); window.location='belumujikom.php';</script>";
    exit;
}

$upload_dir = "/home/symotech/af.symotech.id/pekerja/";

/* =======================
   FUNGSI UPLOAD (TETAP)
======================= */
function upload_file($key, $prefix, $dir) {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== 0) {
        die("File $key wajib diunggah");
    }
    $allowed = ['jpg','jpeg','png','gif','pdf'];
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        die("Format file $key tidak valid");
    }
    $name = uniqid($prefix.'_') . '.' . $ext;
    $path = rtrim($dir,'/').'/'.$name;
    if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
        die("Upload $key gagal");
    }
    return $name;
}

/* ==============================
   CEK BIAYA NIKBY (UJIKOM)
============================== */
$q = $conn->prepare("
    SELECT id, kodeby, nom, tanggal
    FROM nikby
    WHERE nik=? AND klaim='0' AND kodeby='2'
    LIMIT 1
");
$q->bind_param("s", $nik);
$q->execute();
$q->bind_result($nikby_id, $kodeby, $nilaiNom, $tanggalTrans);

if (!$q->fetch()) {
    echo "<script>
        alert('Biaya ujikom belum diinput atau sudah diklaim');
        window.location='home.php';
    </script>";
    $q->close();
    exit;
}
$q->close();

/* ==============================
   COA & NAMA BIAYA (UJIKOM)
============================== */
$q2 = $conn->prepare("
    SELECT COA, namaby
    FROM jenisbiaya
    WHERE id=?
    LIMIT 1
");
$q2->bind_param("i", $kodeby);
$q2->execute();
$q2->bind_result($coa_biaya, $nama_biaya);
$q2->fetch();
$q2->close();

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
$qCab->bind_result($location);
$qCab->fetch();
$location = $location ?: "-";
$qCab->close();

/* ==============================
   KODE TRANSAKSI JURNAL
============================== */
$qJM = $conn->query("SELECT MAX(id) AS max_nomor FROM JM");
$rowJM = $qJM->fetch_assoc();
$nomor = intval($rowJM['max_nomor'] ?? 0) + 1;
$kode_transaksi = "JM" . date('Y') . sprintf('%04d', $nomor);

/* =====================================================
   FORM UJIKOM
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_ujikom = $_POST['tanggal_ujikom'] ?? '';
    $tgl_ujikom     = $_POST['tgl_ujikom'] ?? '';
    $tglend_ujikom  = $_POST['tglend_ujikom'] ?? '';
    $edit           = 'clouse';

    // Upload wajib
    $ujikom_file = upload_file('ujikom', 'ujikom', $upload_dir);

    // INSERT UJIKOM (TANPA status_ujikom)
    $stmt = $conn->prepare("
        INSERT INTO ujikom
        (nik, ujikom, tgl_ujikom, tglend_ujikom, edit)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssss",
        $nik,
        $ujikom_file,
        $tgl_ujikom,
        $tglend_ujikom,
        $edit
    );

    if ($stmt->execute()) {

        // UPDATE tanggal ujikom
        $up = $conn->prepare("
            UPDATE pendaftaran
            SET tanggal_ujikom=?
            WHERE nik=?
        ");
        $up->bind_param("ss", $tanggal_ujikom, $nik);
        $up->execute();
        $up->close();

        // ==============================
        // JURNAL - DEBET UJIKOM
        // ==============================
        $ket = $nama_biaya . " - " . $nik;

        $j1 = $conn->prepare("
            INSERT INTO jurnal
            (journal_number, tanggal, keterangan, coa, debet, kredit, posting, location)
            VALUES (?, ?, ?, ?, ?, 0, NULL, ?)
        ");
        $j1->bind_param(
            "sssdis",
            $kode_transaksi,
            $tanggal_ujikom,
            $ket,
            $coa_biaya,
            $nilaiNom,
            $location
        );
        $j1->execute();
        $j1->close();

        echo "<script>
            alert('Data ujikom & jurnal berhasil disimpan');
            window.location='detail_pekerja.php?nik=" . urlencode($nik) . "';
        </script>";
        exit;

    } else {
        echo "<script>alert('Gagal menyimpan ujikom: {$stmt->error}');</script>";
    }

    $stmt->close();
}

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


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Data ujikom</title>
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

<form method="post" enctype="multipart/form-data">
    <label>Tanggal Ujikom</label>
    <input type="date" name="tanggal_ujikom" value="<?= date('Y-m-d') ?>" required>

    <label>File Ujikom</label>
    <input type="file" name="ujikom" required>

    <label>Tanggal Mulai</label>
    <input type="date" name="tgl_ujikom" required>

    <label>Tanggal Berakhir</label>
    <input type="date" name="tglend_ujikom" required>

    <button type="submit">Simpan</button>
</form>

</body>
</html>

<?php $conn->close(); ?>
