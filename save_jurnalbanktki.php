

<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

/* ===============================
   VALIDASI AKSES
================================ */
$allowed_referer_domain = "https://af.symotech.id/";
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit;
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit;
}

require_once 'config.php';

/* ===============================
   KONEKSI DATABASE
================================ */
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

/* ===============================
   GENERATE KODE TRANSAKSI
================================ */
$res = $conn->query("SELECT MAX(id) AS max_id FROM bo");
$row = $res->fetch_assoc();
$nomor = ((int)($row['max_id'] ?? 0)) + 1;
$nomor_formatted = sprintf('%04d', $nomor);

$tanggal = $_POST['tanggal'] ?? '';
$tahun   = date('Y', strtotime($tanggal));
$kode_transaksi = "BO" . $tahun . $nomor_formatted;

/* ===============================
   AMBIL DATA FORM
================================ */
$keterangan    = $_POST['keterangan'] ?? [];
$lampiran      = $_FILES['lampiran'] ?? [];
$coa           = $_POST['coa'] ?? [];
$account_name  = $_POST['account_name'] ?? [];
$debet         = $_POST['debet'] ?? [];
$kredit        = $_POST['kredit'] ?? [];
$location      = $_POST['location'] ?? '';
$devisi        = $_POST['devisi'] ?? '';
$kode_booking  = $_POST['kode_booking'] ?? [];
$kodeby        = $_POST['kodeby'] ?? '';
$niks          = json_decode($_POST['niks'] ?? '[]', true);

/* ===============================
   GABUNGKAN KETERANGAN INPUT
================================ */
$keterangan_arr = array_filter(array_map('trim', $keterangan));
$keterangan_gabung = implode(' | ', $keterangan_arr);

/* ===============================
   TAMBAH NAMA JENIS BIAYA (JIKA ADA)
================================ */
if (!empty($kodeby)) {
    $stmt = $conn->prepare("SELECT namaby FROM jenisbiaya WHERE id = ?");
    $stmt->bind_param("i", $kodeby);
    $stmt->execute();
    $stmt->bind_result($namaby);
    $stmt->fetch();
    $stmt->close();

    if (!empty($namaby)) {
        $keterangan_gabung .= " | " . $namaby;
    }
}

/* ===============================
   TAMBAH ACCOUNT NAME COA BARIS KE-2
================================ */
$coa_biaya = $coa[1] ?? '';

if ($coa_biaya !== '') {
    $stmt = $conn->prepare("
        SELECT account_name 
        FROM coa 
        WHERE account_code = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $coa_biaya);
    $stmt->execute();
    $stmt->bind_result($account_name_biaya);
    $stmt->fetch();
    $stmt->close();

    if (!empty($account_name_biaya)) {
        $keterangan_gabung .= " | " . $account_name_biaya;
    }
}

/* ===============================
   GABUNGKAN LAMPIRAN
================================ */
$lampiran_value = isset($lampiran['name'])
    ? implode(', ', $lampiran['name'])
    : '';

/* ===============================
   SIMPAN KE JURNAL
================================ */
$sql = "
INSERT INTO jurnal
(journal_number, tanggal, keterangan, lampiran, coa, account_name,
 debet, kredit, kode_booking, location, devisi)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";
$stmt = $conn->prepare($sql);

for ($i = 0; $i < count($coa); $i++) {

    $coa_val          = $coa[$i] ?? '';
    $account_name_val = $account_name[$i] ?? '';
    $debet_val        = str_replace(',', '', $debet[$i] ?? 0);
    $kredit_val       = str_replace(',', '', $kredit[$i] ?? 0);
    $kode_booking_val = $kode_booking[$i] ?? '';

    $stmt->bind_param(
        "sssssssssss",
        $kode_transaksi,
        $tanggal,
        $keterangan_gabung,
        $lampiran_value,
        $coa_val,
        $account_name_val,
        $debet_val,
        $kredit_val,
        $kode_booking_val,
        $location,
        $devisi
    );
    $stmt->execute();
}
$stmt->close();

/* ===============================
   SIMPAN KE TABEL co
================================ */
$stmt = $conn->prepare("INSERT INTO bo (bo) VALUES (?)");
$stmt->bind_param("s", $kode_transaksi);
$stmt->execute();
$stmt->close();

/* ===============================
   SIMPAN KE nikby (kodeby BOLEH KOSONG)
================================ */
if (is_array($niks) && count($niks) > 0 && $coa_biaya !== '') {

    $total_kredit = floatval(str_replace(',', '', $kredit[0] ?? 0));
    $nominal_per_nik = $total_kredit / count($niks);

    $klaim = '0';
    $kodeby_val = !empty($kodeby) ? $kodeby : null;

    $stmt = $conn->prepare("
        INSERT INTO nikby
        (nik, kodeby, coa, nom, tanggal, klaim, kode_transaksi)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($niks as $nik) {

        $nik_val      = $nik;
        $coa_val      = $coa_biaya;
        $nom_val      = $nominal_per_nik;
        $tgl_val      = $tanggal;
        $klaim_val    = $klaim;
        $trx_val      = $kode_transaksi;

        $stmt->bind_param(
            "sssdsss",
            $nik_val,
            $kodeby_val,
            $coa_val,
            $nom_val,
            $tgl_val,
            $klaim_val,
            $trx_val
        );
        $stmt->execute();
    }
    $stmt->close();
}

$conn->close();

/* ===============================
   REDIRECT
================================ */
echo "<script>
alert('Data jurnal berhasil disimpan! Kode Transaksi: $kode_transaksi');
window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
</script>";
exit;
