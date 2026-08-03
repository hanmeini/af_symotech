<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://af.symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config.php';



// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");



// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}



// Ambil nomor transaksi terakhir dari tabel `bo`
$query = "SELECT MAX(id) AS max_nomor FROM bo";
$result = $conn->query($query);

$max_nomor = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['max_nomor'] : 0;
$nomor = intval($max_nomor) + 1;
$nomor_formatted = sprintf('%04d', $nomor);

// Ambil data dari form
$tanggal = $_POST['tanggal'];
$keterangan = isset($_POST['keterangan']) ? $_POST['keterangan'] : [];
$lampiran = $_FILES['lampiran'];
$coa = $_POST['coa'];
$account_name = $_POST['account_name'];
$debet = str_replace(',', '', $_POST['debet']); 
$kredit = str_replace(',', '', $_POST['kredit']); 
$location = $_POST['location'];
$devisi = $_POST['devisi'];
$kodeaset = $_POST['kodeaset'];
$namaaset = $_POST['namaaset'];


$bulan = date('m', strtotime($tanggal));
$tahun = date('Y', strtotime($tanggal));
$kode_transaksi = "BO$tahun$nomor_formatted";

// **Proses simpan data jurnal**
$sql_jurnal = "INSERT INTO jurnal (journal_number, tanggal, keterangan, lampiran, coa, account_name, debet, kredit, kode_booking, location, devisi) 
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql_jurnal);
// Menghitung total debet untuk aset
$total_debet = 0;

// Looping untuk tabel jurnal
for ($i = 0; $i < count($coa); $i++) {
    $coa_value = $coa[$i];
    $account_name_value = isset($account_name[$i]) ? $account_name[$i] : '';
    $debet_value = isset($debet[$i]) ? $debet[$i] : 0;
    $kredit_value = isset($kredit[$i]) ? $kredit[$i] : 0;
    $keterangan_value = isset($keterangan[$i]) ? $keterangan[$i] : '';
    $lampiran_value = implode(', ', $lampiran['name']); // Gabungkan nama file lampiran

    // Tambahkan ke total debet
    $total_debet += $debet_value;

    $stmt->bind_param("sssssssssss", $kode_transaksi, $tanggal, $keterangan_value, $lampiran_value, $coa_value, 
                      $account_name_value, $debet_value, $kredit_value, $kode_transaksi, $location, $devisi);
    $stmt->execute();
}

// Simpan data ke tabel aset dengan total debet
$sql_aset = "INSERT INTO bdm (kode, nama, tanggal, keterangan, lampiran, beli, location, devisi) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql_aset);

if ($stmt) {
    $stmt->bind_param("sssssdss", $kodeaset, $namaaset, $tanggal, $keterangan_value, $lampiran_value, $total_debet, $location, $devisi);

    if ($stmt->execute()) {
        echo "Data aset berhasil disimpan.";
    } else {
        echo "Error saat menyimpan data aset: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Error mempersiapkan query aset: " . $conn->error;
}

// Simpan kode transaksi di tabel bo
$sql3 = "INSERT INTO bo (bo) VALUES (?)"; // Pastikan 'jurnal' adalah nama kolom yang ada di tabel bo
$stmt = $conn->prepare($sql3);
$stmt->bind_param("s", $kode_transaksi);
$stmt->execute();

// Tutup statement dan koneksi
$stmt->close();

// Tutup koneksi
$conn->close();

// Tampilkan notifikasi berhasil
echo "<script>
    alert('Data jurnal berhasil disimpan! Kode Transaksi: $kode_transaksi');
    window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
</script>";
exit();
?>

