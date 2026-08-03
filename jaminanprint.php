<?php
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

// Jika upload dilakukan
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

    echo "<script>alert('Upload berhasil'); window.location='jaminanprint.php?nik=$nik&id=$id&print=".implode(",", $print_docs)."';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Cetak Jaminan</title>

<style>
body {
    font-family: Arial;
    margin: 0;
    padding: 0;
}

.page {
    width: 210mm;
    min-height: 297mm;
    padding: 15mm;
    margin: auto;
    background: white;
    box-sizing: border-box;
}

.box {
    border: 1px solid #000;
    padding: 10px;
    margin-bottom: 25px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.table th, .table td {
    border: 1px solid black;
    padding: 6px;
    font-size: 14px;
}

.signature {
    margin-top: 40px;
    width: 100%;
    display: flex;
    justify-content: space-between;
    text-align: center;
}
.signature div {
    width: 45%;
}

@media print {
    #printBtn { display: none; }
    #afterPrintSection { display: none; }
}
</style>

<script>
// Setelah print, tampilkan upload TT
window.onafterprint = function(){
    document.getElementById("afterPrintSection").style.display = "block";
}
</script>

</head>
<body>

<!-- Upload TT setelah print -->
<div id="afterPrintSection" style="display:none; padding:20px;">
    <h2>Upload Tanda Terima</h2>
    <?= $upload_message ?>
</div>

<div class="page">

<button id="printBtn" onclick="window.print()" 
    style="padding:10px 20px; background:#2196F3; color:white; border:none; border-radius:6px; cursor:pointer;">
    PRINT
</button>

<h2 style="text-align:center;">FORM PENERIMAAN DOKUMEN JAMINAN</h2>

<?php  

// MAP nama parameter → nama kolom
$map = [
    "ktp"   => ["KTP",        "ktpin",    "tglktpin",    "ketktpin"],
    "kk"    => ["KK",         "kkin",     "tglkkkin",    "ketkkin"],
    "akta"  => ["Akta",       "aktain",   "tglaktain",   "ketaktain"],
    "bnik"  => ["Buku Nikah", "bunikin",  "tglbunikin",  "ketbunikin"],
    "ijazah"=> ["Ijazah",     "ijazahin", "tglijazahin", "ketijazahin"]
];

// --- buat 1 baris jika dokumen diperbolehkan dicetak ---
function baris($nama, $penerima, $tgl, $ket) {
    return "
        <tr>
            <td>$nama</td>
            <td>$tgl</td>
            <td>$penerima</td>
            <td>$ket</td>
        </tr>
    ";
}

function bagian($data, $print_docs, $map){ 

    // Tentukan penerima tanda tangan dari dokumen pertama yang dicentang
    $penerima_ttd = "";

    foreach ($print_docs as $doc) {
        $col = $map[$doc][1];
        if (!empty($data[$col])) {
            $penerima_ttd = $data[$col];
            break;
        }
    }

    $rows = "";

    foreach ($print_docs as $doc) {

        list($nama, $col_penerima, $col_tgl, $col_ket) = $map[$doc];

        $penerima = $data[$col_penerima];
        if ($penerima == "" || $penerima == null) continue;

        $rows .= baris($nama, $penerima, $data[$col_tgl], $data[$col_ket]);
    }

    $html = "
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
                ( __________________ )
            </div>
            <div>
                Yang Menerima<br><br><br><br>
                ( ".htmlspecialchars($penerima_ttd)." )
            </div>
        </div>
    </div>";

    return $html;
}

?>

<!-- Rangkap 1 -->
<?= bagian($data, $print_docs, $map) ?>

<hr style="border:1px dashed #000; margin:30px 0;">

<!-- Rangkap 2 -->
<h2 style="text-align:center;">FORM PENERIMAAN DOKUMEN JAMINAN</h2>
<?= bagian($data, $print_docs, $map) ?>

</div>

</body>
</html>
