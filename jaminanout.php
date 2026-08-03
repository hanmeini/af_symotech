<?php
session_start();
require_once "config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- KONEKSI ---
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) { die("Koneksi gagal: " . $conn->connect_error); }

// --- CEK LOGIN ---
if (!isset($_SESSION['username'])) {
    die("User belum login");
}

// =========================================================
// PENYERAH = USER LOGIN  ❌ TIDAK DISIMPAN KE DB
// =========================================================
$penyerah = $_SESSION['username'];       // FIX: bukan username
                                        // Hanya untuk dicetak
// ---------------------------------------------------------

// --- CEK NIK ---
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='belumvisa.php';</script>";
    exit;
}

// ===========================================================
//                      PROSES SIMPAN
// ===========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nik = $_POST['nik'];
    $tgl = $_POST['tgldoc'] ?? null;

    // ==========================================
    // PENERIMA DOKUMEN (DISIMPAN DI DATABASE)
    // ==========================================
    $penerima = $_POST['penerima'] ?? "";   // FIX
    $ketall  = $_POST['ketall'] ?? "";

    // Checkbox dokumen
    $dok = [
        'ktp'   => isset($_POST['chk_ktp']),
        'kk'    => isset($_POST['chk_kk']),
        'akta'  => isset($_POST['chk_akta']),
        'bnik'  => isset($_POST['chk_bnik']),
        'ijazah'=> isset($_POST['chk_ijazah'])
    ];

    // LIST UNTUK CETAK
    $print_list = [];
    foreach ($dok as $key => $val) {
        if ($val) $print_list[] = $key;
    }

    if (empty($print_list)) {
        echo "<script>alert('Tidak ada dokumen yang dicentang'); history.back();</script>";
        exit;
    }

    $print_param = implode(",", $print_list);

    // =======================================================
    // CEK DATA IN SUDAH ADA
    // =======================================================
    $check = $conn->prepare("SELECT * FROM jaminan WHERE nik = ? ORDER BY id DESC LIMIT 1");
    $check->bind_param("s", $nik);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows == 0) {
        echo "<script>alert('Data jaminan belum pernah dimasukkan (IN)'); history.back();</script>";
        exit;
    }

    $row = $res->fetch_assoc();
    $id  = $row['id'];

    // =======================================================
    //                 UPDATE DATA OUT
    // =======================================================
    $sql_update = "UPDATE jaminan SET ";
    $params = [];
    $types  = "";

    // setiap dokumen menyimpan PENERIMA, TANGGAL, KETERANGAN
    if ($dok['ktp']) {
        $sql_update .= "ktpout=?, tglktpout=?, ketktpout=?, ";
        $params[] = $penerima;       // FIX: penerima disimpan DB
        $params[] = $tgl;
        $params[] = $ketall;
        $types   .= "sss";
    }
    if ($dok['kk']) {
        $sql_update .= "kkout=?, tglkkkout=?, ketkkout=?, ";
        $params[] = $penerima;
        $params[] = $tgl;
        $params[] = $ketall;
        $types   .= "sss";
    }
    if ($dok['akta']) {
        $sql_update .= "aktaout=?, tglaktaout=?, ketaktaout=?, ";
        $params[] = $penerima;
        $params[] = $tgl;
        $params[] = $ketall;
        $types   .= "sss";
    }
    if ($dok['bnik']) {
        $sql_update .= "bunikout=?, tglbunikout=?, ketbunikout=?, ";
        $params[] = $penerima;
        $params[] = $tgl;
        $params[] = $ketall;
        $types   .= "sss";
    }
    if ($dok['ijazah']) {
        $sql_update .= "ijazahout=?, tglijazahout=?, ketijazahout=?, ";
        $params[] = $penerima;
        $params[] = $tgl;
        $params[] = $ketall;
        $types   .= "sss";
    }

    $sql_update .= "edit='clouse', minta='0', alesan='' WHERE id=?";
    $params[] = $id;
    $types   .= "i";

    $stmt = $conn->prepare($sql_update);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // ======================================================
    // PENYERAH DIKIRIM UNTUK CETAK SAJA
    // ======================================================

    echo "<script>
        alert('Dokumen berhasil dikeluarkan (OUT)');
        window.location='jaminanprintout.php?nik=$nik&id=$id&print=$print_param&penyerah=$penyerah';
    </script>";
    exit;
}
?>


<!DOCTYPE html>
<html>
<head>
<title>Pengeluaran Dokumen Jaminan</title>

<style>
body {
    font-family: Arial;
    background:#f7f7f7;
    padding:20px;
}
.container{
    max-width:800px;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}
h2 { text-align:center; margin-bottom:25px; }
.box {
    border:1px solid #ddd;
    padding:15px;
    border-radius:8px;
    margin-bottom:12px;
    background:#fafafa;
}
button {
    padding:12px 20px;
    font-size:16px;
    border:none;
    background:#E91E63;
    color:white;
    border-radius:8px;
    cursor:pointer;
}
button:hover { background:#C2185B; }
label { font-weight:bold; }
input[type=text] { width:98%; padding:6px; }
</style>

</head>
<body>

<div class="container">
<h2>Pengeluaran Dokumen Jaminan</h2>

<form method="POST">

    <label>NIK</label><br>
    <input type="text" name="nik" value="<?= $nik ?>" readonly style="width:300px;"><br><br>

    <!-- FIX: Penyerah hanya tampil -->
    <label>Penyerah Dokumen (Petugas)</label><br>
    <input type="text" value="<?= $penyerah ?>" readonly style="width:300px;background:#eee;"><br><br>

    <label>Penerima Dokumen</label><br>
    <input type="text" name="penerima" required placeholder="Nama penerima dokumen"><br><br>

    <label>Tanggal Pengeluaran Dokumen</label><br>
    <input type="date" name="tgldoc" required><br><br>

    <label>Keterangan status hubungan keluarga atau instansi</label><br>
    <input type="text" name="ketall" required><br><br>

    <div class="box">
        <input type="checkbox" name="chk_ktp"> KTP
    </div>

    <div class="box">
        <input type="checkbox" name="chk_kk"> Kartu Keluarga
    </div>

    <div class="box">
        <input type="checkbox" name="chk_akta"> Akta
    </div>

    <div class="box">
        <input type="checkbox" name="chk_bnik"> Buku Nikah
    </div>

    <div class="box">
        <input type="checkbox" name="chk_ijazah"> Ijazah
    </div>

    <button type="submit">SIMPAN & CETAK</button>

</form>

</div>

</body>
</html>
