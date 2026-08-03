<?php
session_start();
require_once "config.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- KONEKSI DATABASE ---
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// --- CEK LOGIN ---
if (!isset($_SESSION['username'])) {
    die("User belum login");
}
$penerima = $_SESSION['username'];

// --- CEK NIK ---
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='jaminan.php';</script>";
    exit;
}

// --- VERIFIKASI NIK ADA DI TABEL PENDAFTARAN ---
$cekNik = $conn->prepare("SELECT nik FROM pendaftaran WHERE nik = ? LIMIT 1");
$cekNik->bind_param("s", $nik);
$cekNik->execute();
$resCek = $cekNik->get_result();

if ($resCek->num_rows == 0) {
    echo "<script>alert('NIK tidak terdaftar di tabel pendaftaran!'); window.location.href='jaminan.php';</script>";
    exit;
}


// ===========================================================
//                      PROSES SIMPAN
// ===========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nik = $_POST['nik'];
    $tgl = $_POST['tgldoc'] ?? null;

    // Checkbox dokumen
    $dok = [
        'ktp'    => isset($_POST['chk_ktp']),
        'kk'     => isset($_POST['chk_kk']),
        'akta'   => isset($_POST['chk_akta']),
        'bnik'   => isset($_POST['chk_bnik']),
        'ijazah' => isset($_POST['chk_ijazah'])
    ];

    // Dokumen yang dicentang → untuk dikirim ke print
    $print_list = [];
    foreach ($dok as $key => $v) {
        if ($v) $print_list[] = $key;
    }

    if (empty($print_list)) {
        echo "<script>alert('Tidak ada dokumen yang dicentang'); history.back();</script>";
        exit;
    }

    $print_param = implode(",", $print_list);

    // Cek apakah jaminan sudah ada → update atau insert
    $check = $conn->prepare("SELECT * FROM jaminan WHERE nik = ? ORDER BY id DESC LIMIT 1");
    $check->bind_param("s", $nik);
    $check->execute();
    $res = $check->get_result();


    // =======================================================
    //                         UPDATE
    // =======================================================
    if ($res->num_rows > 0) {

        $row = $res->fetch_assoc();
        $id = $row['id'];

        $fields = [];
        $params = [];
        $types  = "";

        // ================ KTP ====================
        if ($dok['ktp']) {
            $fields[] = "ktpin=?, tglktpin=?, ktpout='', ketktpout='', tglktpout=NULL";
            $params[] = $penerima;
            $params[] = $tgl;
            $types   .= "ss";
        }

        // ================ KK =====================
        if ($dok['kk']) {
            $fields[] = "kkin=?, tglkkkin=?, kkout='', ketkkout='', tglkkkout=NULL";
            $params[] = $penerima;
            $params[] = $tgl;
            $types   .= "ss";
        }

        // ================ AKTA ===================
        if ($dok['akta']) {
            $fields[] = "aktain=?, tglaktain=?, aktaout='', ketaktaout='', tglaktaout=NULL";
            $params[] = $penerima;
            $params[] = $tgl;
            $types   .= "ss";
        }

        // ================ BUKU NIKAH =============
        if ($dok['bnik']) {
            $fields[] = "bunikin=?, tglbunikin=?, bunikout='', ketbunikout='', tglbunikout=NULL";
            $params[] = $penerima;
            $params[] = $tgl;
            $types   .= "ss";
        }

        // ================ IJAZAH =================
        if ($dok['ijazah']) {
            $fields[] = "ijazahin=?, tglijazahin=?, ijazahout='', ketijazahout='', tglijazahout=NULL";
            $params[] = $penerima;
            $params[] = $tgl;
            $types   .= "ss";
        }

        if (empty($fields)) {
            echo "<script>alert('Tidak ada dokumen yang dicentang'); history.back();</script>";
            exit;
        }

        // gabungkan semua field SET
        $sql_update = "UPDATE jaminan SET " . implode(", ", $fields);

        // tambahan tetap
        $sql_update .= ", edit='clouse', minta='0', alesan='' WHERE id=?";

        // parameter id
        $params[] = $id;
        $types   .= "i";

        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        echo "<script>
                alert('Data berhasil diupdate');
                window.location='jaminanprint.php?nik=$nik&id=$id&print=$print_param';
              </script>";
        exit;
    }



    // =======================================================
    //                         INSERT
    // =======================================================
    $ktpin    = $dok['ktp']    ? $penerima : "";
    $kkin     = $dok['kk']     ? $penerima : "";
    $aktain   = $dok['akta']   ? $penerima : "";
    $bunikin  = $dok['bnik']   ? $penerima : "";
    $ijazahin = $dok['ijazah'] ? $penerima : "";

    $tglktpin    = $ktpin    ? $tgl : null;
    $tglkkkin    = $kkin     ? $tgl : null;
    $tglaktain   = $aktain   ? $tgl : null;
    $tglbunikin  = $bunikin  ? $tgl : null;
    $tglijazahin = $ijazahin ? $tgl : null;

    $kosong = "";

    $stmt = $conn->prepare("
        INSERT INTO jaminan (
            nik, tglktpin, tglkkkin, tglaktain, tglbunikin, tglijazahin,
            ktpin, kkin, aktain, bunikin, ijazahin,
            tt1, tt2, tt3, tt4,
            edit, minta, alesan
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'clouse','0','')
    ");

    $stmt->bind_param(
        "sssssssssssssss",
        $nik,
        $tglktpin, $tglkkkin, $tglaktain, $tglbunikin, $tglijazahin,
        $ktpin, $kkin, $aktain, $bunikin, $ijazahin,
        $kosong, $kosong, $kosong, $kosong
    );

    $stmt->execute();
    $last_id = $conn->insert_id;

    echo "<script>
            alert('Data tersimpan');
            window.location='jaminanprint.php?nik=$nik&id=$last_id&print=$print_param';
          </script>";
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Input Penerimaan Jaminan</title>

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
    background:#2196F3;
    color:white;
    border-radius:8px;
    cursor:pointer;
}
button:hover { background:#1976D2; }
label { font-weight:bold; }
</style>

</head>
<body>

<div class="container">
<h2>Penerimaan Dokumen Jaminan</h2>

<form method="POST">

    <label>NIK</label><br>
    <input type="text" name="nik" value="<?= $nik ?>" readonly style="width:300px;"><br><br>

    <label>Penerima Dokumen</label><br>
    <input type="text" value="<?= $penerima ?>" readonly style="width:300px;background:#eee;"><br><br>

    <label>Tanggal Penerimaan Dokumen</label><br>
    <input type="date" name="tgldoc" required style="width:200px;"><br><br>

    <div class="box"><input type="checkbox" name="chk_ktp"> KTP</div>
    <div class="box"><input type="checkbox" name="chk_kk"> Kartu Keluarga</div>
    <div class="box"><input type="checkbox" name="chk_akta"> Akta</div>
    <div class="box"><input type="checkbox" name="chk_bnik"> Buku Nikah</div>
    <div class="box"><input type="checkbox" name="chk_ijazah"> Ijazah</div>

    <button type="submit">SIMPAN & CETAK</button>

</form>

</div>

</body>
</html>
