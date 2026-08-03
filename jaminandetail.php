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

// --- AMBIL DATA ---
$nik = $_GET['nik'] ?? '';
if (!$nik) die("NIK tidak ditemukan");

$q = $conn->query("SELECT * FROM jaminan WHERE nik='$nik'");
$data = $q->fetch_assoc();
if (!$data) die("Data tidak ditemukan");

?>
<!DOCTYPE html>
<html>
<head>
<title>Detail Jaminan</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    padding: 20px;
}
.container {
    max-width: 1100px;
    margin: auto;
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow:0 0 10px rgba(0,0,0,0.15);
}
h2 { text-align: center; margin-bottom: 25px; }

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.table th, .table td {
    border:1px solid #bbb;
    padding: 8px;
    text-align: center;
}
.table th {
    background: #1976D2;
    color: white;
}
.doc-head {
    background: #f1f1f1;
    font-weight: bold;
    text-align: left;
}

.tt-btn {
    padding: 8px 20px;
    margin-top: 10px;
    margin-bottom: 5px;
    background: #1976D2;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}
.tt-btn:hover { background: #125A9C; }

.tt-img {
    display: none;
    margin-top: 10px;
    max-width: 260px;
    border: 1px solid #aaa;
    padding: 6px;
    border-radius: 6px;
}
</style>

<script>
function showTT(id) {
    let target = document.getElementById(id);
    target.style.display = (target.style.display === "none" || target.style.display === "") 
        ? "block" : "none";
}
</script>

</head>
<body>

<div class="container">

<h2>Detail Data Jaminan</h2>

<!-- ===============================
         INFORMASI UTAMA
=============================== -->
<table class="table">
    <tr><th colspan="6">Informasi Utama</th></tr>
    <tr><td>NIK</td><td colspan="5"><?= $data['nik'] ?></td></tr>
    
</table>


<!-- ===============================
       TABEL MODEL C
=============================== -->
<table class="table">

<tr>
    <th>Dokumen</th>
    <th>Tgl IN</th>
    <th>Tgl OUT</th>
    <th>IN</th>
    <th>OUT</th>
    <th>Keterangan</th>
</tr>

<!-- KTP -->
<tr class="doc-head"><td colspan="6">KTP</td></tr>
<tr>
    <td>KTP</td>
    <td><?= $data['tglktpin'] ?></td>
    <td><?= $data['tglktpout'] ?></td>
    <td><?= $data['ktpin'] ?></td>
    <td><?= $data['ktpout'] ?></td>
    <td><?= $data['ketktpout'] ?></td>
</tr>

<!-- KK -->
<tr class="doc-head"><td colspan="6">KK</td></tr>
<tr>
    <td>KK</td>
    <td><?= $data['tglkkkin'] ?></td>
    <td><?= $data['tglkkkout'] ?></td>
    <td><?= $data['kkin'] ?></td>
    <td><?= $data['kkout'] ?></td>
    <td><?= $data['ketkkout'] ?></td>
</tr>

<!-- AKTA -->
<tr class="doc-head"><td colspan="6">AKTA</td></tr>
<tr>
    <td>Akta</td>
    <td><?= $data['tglaktain'] ?></td>
    <td><?= $data['tglaktaout'] ?></td>
    <td><?= $data['aktain'] ?></td>
    <td><?= $data['aktaout'] ?></td>
    <td><?= $data['ketaktaout'] ?></td>
</tr>

<!-- Buku Nikah -->
<tr class="doc-head"><td colspan="6">BUKU NIKAH</td></tr>
<tr>
    <td>Buku Nikah</td>
    <td><?= $data['tglbunikin'] ?></td>
    <td><?= $data['tglbunikout'] ?></td>
    <td><?= $data['bunikin'] ?></td>
    <td><?= $data['bunikout'] ?></td>
    <td><?= $data['ketbunikout'] ?></td>
</tr>

<!-- Ijazah -->
<tr class="doc-head"><td colspan="6">IJAZAH</td></tr>
<tr>
    <td>Ijazah</td>
    <td><?= $data['tglijazahin'] ?></td>
    <td><?= $data['tglijazahout'] ?></td>
    <td><?= $data['ijazahin'] ?></td>
    <td><?= $data['ijazahout'] ?></td>
    <td><?= $data['ketijazahout'] ?></td>
</tr>

</table>


<!-- ===============================
       TANDA TANGAN
=============================== -->
<h3 style="margin-top:30px;">Tanda Terima (TT)</h3>

<?php
$upload_dir = "/home/symotech/af.symotech.id/pekerja/";
$base_url   = "https://af.symotech.id/pekerja/";

$tt_list = ["tt1","tt2","tt3","tt4"];

foreach ($tt_list as $t) {

    $file = $data[$t];
    $img_url = $base_url . $file;
    $real_path = $upload_dir . $file;

    echo "<button class='tt-btn' onclick=\"showTT('$t')\">Lihat ".strtoupper($t)."</button><br>";

    if ($file != "" && file_exists($real_path)) {
        echo "<img id='$t' class='tt-img' src='$img_url'>";
    } else {
        echo "<div id='$t' class='tt-img'><i>Belum upload</i></div>";
    }
}
?>

</div>

</body>
</html>
