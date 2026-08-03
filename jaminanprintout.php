<?php
session_start();
require_once "config.php";

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = $_GET['nik'] ?? '';
$id  = $_GET['id'] ?? '';
$print_docs = isset($_GET['print']) ? explode(",", $_GET['print']) : [];

// Yang MENYERAHKAN = username (tidak disimpan di DB)
$penyerah = $_SESSION['username'] ?? "USER";

// cek data
if (!$nik || !$id) {
    die("Data tidak lengkap");
}

$q = $conn->query("SELECT * FROM jaminan WHERE id='$id' AND nik='$nik'");
$data = $q->fetch_assoc();

if (!$data) {
    die("Data tidak ditemukan.");
}

// Tentukan TT berikutnya
$next_tt = "";
if ($data['tt1'] == "") $next_tt = "tt1";
else if ($data['tt2'] == "") $next_tt = "tt2";
else if ($data['tt3'] == "") $next_tt = "tt3";
else if ($data['tt4'] == "") $next_tt = "tt4";

if ($next_tt == "") {
    $upload_message = "<h2 style='color:green;text-align:center'>Semua TT sudah lengkap.</h2>";
} else {
    $upload_message = "
    <h2>Upload $next_tt</h2>
    <form method='POST' enctype='multipart/form-data'>
        <input type='file' name='file_tt' required>
        <br><br>
        <button type='submit'>Upload</button>
    </form>
    ";
}

// proses upload TT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $next_tt != "") {

    $allowed = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($_FILES['file_tt']['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format tidak diizinkan.");
    }

    $upload_dir = "/home/symotech/af.symotech.id/pekerja/";
    $filename = uniqid($next_tt."_").".".$ext;
    move_uploaded_file($_FILES['file_tt']['tmp_name'], $upload_dir.$filename);

    $conn->query("UPDATE jaminan SET $next_tt='$filename' WHERE id='$id'");

    echo "<script>alert('Upload berhasil'); window.location='jaminanprintout.php?nik=$nik&id=$id&print=".implode(",", $print_docs)."';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Cetak Jaminan</title>

<style>
body { font-family: Arial; margin: 0; padding: 0; }
.page {
    width: 210mm; min-height: 297mm; padding: 15mm; margin: auto;
    background: white; box-sizing: border-box;
}
.box { border: 1px solid #000; padding: 10px; margin-bottom: 25px; }
.table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.table th, .table td {
    border: 1px solid black; padding: 6px; font-size: 14px;
}
.signature { margin-top: 40px; display: flex; justify-content: space-between; }
.signature div { width: 45%; text-align: center; }

@media print {
    #printBtn { display: none; }
    #afterPrintSection { display: none; }
}
</style>

<script>
window.onafterprint = function(){
    document.getElementById("afterPrintSection").style.display = "block";
}
</script>

</head>
<body>

<!-- Upload TT muncul setelah print -->
<div id="afterPrintSection" style="display:none; padding:20px;">
    <h2>Upload Tanda Terima</h2>
    <?= $upload_message ?>
</div>

<div class="page">

<button id="printBtn" onclick="window.print()" 
    style="padding:10px 20px; background:#2196F3; color:white; border:none; border-radius:6px; cursor:pointer;">
    PRINT
</button>

<h2 style="text-align:center;">FORM PENGAMBILAN DOKUMEN JAMINAN</h2>

<?php  

// MAP dokumen ke kolom DB (OUT)
$map = [
    "ktp"   => ["KTP",        "ktpout",    "tglktpout",    "ketktpout"],
    "kk"    => ["KK",         "kkout",     "tglkkkout",    "ketkkout"],
    "akta"  => ["Akta",       "aktaout",   "tglaktaout",   "ketaktaout"],
    "bnik"  => ["Buku Nikah", "bunikout",  "tglbunikout",  "ketbunikout"],
    "ijazah"=> ["Ijazah",     "ijazahout", "tglijazahout", "ketijazahout"]
];

// buat baris tabel
function baris($nama, $tgl, $penerima, $ket) {
    return "
        <tr>
            <td>$nama</td>
            <td>$tgl</td>
            <td>$penerima</td>
            <td>$ket</td>
        </tr>
    ";
}

function bagian($data, $print_docs, $map, $penyerah){

    $rows = "";

    // ambil penerima dari DB (dokumen pertama yg dicentang)
    $penerima_final = "________________";

    foreach ($print_docs as $doc) {

        list($nama, $col_penerima, $col_tgl, $col_ket) = $map[$doc];

        // jika kolom penerima ada, jadikan penerima final
        if (!empty($data[$col_penerima]) && $penerima_final == "________________") {
            $penerima_final = $data[$col_penerima];
        }

        if (empty($data[$col_penerima])) continue;

        $rows .= baris($nama, $data[$col_tgl], $data[$col_penerima], $data[$col_ket]);
    }

    return "
    <div class='box'>
        <table class='table'>
            <tr>
                <th>Dokumen</th><th>Tanggal</th><th>Penerima</th><th>Keterangan</th>
            </tr>
            $rows
        </table>

        <div class='signature'>
            <div>
                Yang Menyerahkan<br><br><br><br>
                ( $penyerah )
            </div>
            <div>
                Yang Menerima<br><br><br><br>
                ( ".htmlspecialchars($penerima_final)." )
            </div>
        </div>
    </div>";
}
?>

<!-- Rangkap 1 -->
<?= bagian($data, $print_docs, $map, $penyerah) ?>

<hr style="border:1px dashed #000; margin:30px 0;">

<!-- Rangkap 2 -->
<h2 style="text-align:center;">FORM PENGAMBILAN DOKUMEN JAMINAN</h2>
<?= bagian($data, $print_docs, $map, $penyerah) ?>

</div>

</body>
</html>
