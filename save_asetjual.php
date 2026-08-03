<?php

ssession_start([
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



$query = "SELECT max(id) AS max_nomor FROM JM";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    $max_nomor = $row['max_nomor'];

    if ($max_nomor) {
        // Tambahkan 1 ke nomor maksimum
        $nomor = intval($max_nomor) + 1;
    } else {
        // Jika tidak ada nomor maksimum, mulai dari 1
        $nomor = 1;
    }

    // Format nomor menjadi 4 digit
    $nomor_formatted = sprintf('%04d', $nomor);
} else {
    // Jika query gagal, atur nomor menjadi 1
    $nomor_formatted = '00001';
}

// Ambil data dari form
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];
$lampiran = $_POST['lampiran'];
$coa = $_POST['coa'];
$account_name = $_POST['account_name'];
$debet = str_replace(',', '', $_POST['debet']);
$kredit = str_replace(',', '', $_POST['kredit']);
$location = $_POST['location'];
$devisi = $_POST['devisi'];
$id = $_POST['id'];

$bulan = date('m', strtotime($tanggal)); // Format bulan dalam angka (01-12)
$tahun = date('Y', strtotime($tanggal)); // Format tahun dalam angka (contoh: 2024)

// Bentuk kode transaksi
$kode_transaksi = "JM" . "$tahun" . $nomor_formatted;

// Query untuk menyimpan data
$sql = "INSERT INTO jurnal (journal_number, tanggal, keterangan, lampiran, coa, account_name, debet, kredit, location, devisi) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

// Siapkan statement
$stmt = $conn->prepare($sql);

// Bind parameter ke statement
$stmt->bind_param("ssssssssss", $kode_transaksi, $tanggal_value, $keterangan_value, $lampiran_value, $coa_value, $account_name_value, $debet_value, $kredit_value, $location_value, $devisi);

// Lakukan looping untuk menyimpan setiap baris jurnal
for ($i = 0; $i < count($coa); $i++) {
    // Set nilai parameter untuk setiap iterasi
    $tanggal_value = $tanggal;
    $coa_value = $coa[$i];
    $account_name_value = $account_name[$i];
    $debet_value = $debet[$i];
    $kredit_value = $kredit[$i];
    $keterangan_value = $keterangan;
    $location_value = $location;

    // Eksekusi statement
    $stmt->execute();
}

// Tutup statement
$stmt->close();

// Simpan kode transaksi di tabel JM
$sql1 = "INSERT INTO JM (jurnal) VALUES (?)";
$stmt = $conn->prepare($sql1);
$stmt->bind_param("s", $kode_transaksi);
$stmt->execute();
$stmt->close();

// Hapus data dari tabel aset
$sql2 = "DELETE FROM aset WHERE id = ?";
$stmt = $conn->prepare($sql2);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

// Tutup koneksi
$conn->close();

// Tampilkan notifikasi berhasil dan arahkan ke cetak_jurnal.php dengan kode transaksi
echo "<script>
    alert('Data jurnal berhasil disimpan dan aset dengan ID $id telah dihapus! Kode Transaksi: $kode_transaksi');
    window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
</script>";

exit();
?>
