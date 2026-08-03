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



// Ambil data dari form
$selected_ids = $_POST['selected_ids']; // Data ID yang dipilih dari form sebelumnya
if (empty($selected_ids)) {
    echo "<script>
        alert('Tidak ada data yang dipilih!');
        window.history.back();
    </script>";
    exit();
}

// Ambil data `no_recuring` dan detail terkait berdasarkan ID
$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
$query = "SELECT id, no_recuring, kode, tanggal, keterangan, coad, account_named, debet, coak, account_namek, kredit, location, devisi 
          FROM recuring 
          WHERE id IN ($placeholders)";
$stmt = $conn->prepare($query);

// Bind parameter ID
$stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
$stmt->execute();

// Gantikan `get_result()` dengan `bind_result()`
$stmt->store_result();
$stmt->bind_result(
    $id, $no_recuring, $kode, $tanggal, $keterangan, $coad, $account_named, 
    $debet, $coak, $account_namek, $kredit, $location, $devisi
);

if ($stmt->num_rows === 0) {
    echo "<script>
        alert('Data tidak ditemukan!');
        window.history.back();
    </script>";
    exit();
}

// Siapkan query untuk menyimpan data ke tabel jurnal
$insert_jurnal_query = "INSERT INTO jurnal (journal_number, tanggal, keterangan, lampiran, coa, account_name, debet, kredit, kode_booking, location, devisi) 
                        VALUES (?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?)";
$insert_jm_query = "INSERT INTO JM (jurnal) VALUES (?)";

// Siapkan query untuk menyimpan data ke tabel susut
$insert_susut_query = "INSERT INTO susut (kode, tanggal, keterangan, susut, recuring) 
                       VALUES (?, ?, ?, ?, ?)";

// Siapkan query untuk update kolom `ab` di tabel recuring
$update_recuring_query = "UPDATE recuring SET ab = ? WHERE id = ?";

$insert_jurnal_stmt = $conn->prepare($insert_jurnal_query);
$insert_jm_stmt = $conn->prepare($insert_jm_query);
$insert_susut_stmt = $conn->prepare($insert_susut_query);
$update_recuring_stmt = $conn->prepare($update_recuring_query);

// Proses setiap baris data dan simpan ke jurnal, susut, dan update tabel recuring
while ($stmt->fetch()) {
    // Siapkan nilai tetap untuk bind_param
    $zero_debet = 0; // Nilai untuk debet
    $zero_kredit = 0; // Nilai untuk kredit

    // Masukkan data debet ke jurnal
    $insert_jurnal_stmt->bind_param(
        "ssssssdisi",
        $no_recuring,  // journal_number
        $tanggal,      // tanggal
        $keterangan,   // keterangan
        $coad,         // coa
        $account_named,// account_name
        $debet,        // debet
        $zero_kredit,  // kredit (0 untuk baris debet)
        $no_recuring,  // kode_booking
        $location,     // location
        $devisi        // devisi
    );
    $insert_jurnal_stmt->execute();

    // Masukkan data kredit ke jurnal
    $insert_jurnal_stmt->bind_param(
        "ssssssdisi",
        $no_recuring,   // journal_number
        $tanggal,       // tanggal
        $keterangan,    // keterangan
        $coak,          // coa
        $account_namek, // account_name
        $zero_debet,    // debet (0 untuk baris kredit)
        $kredit,        // kredit
        $no_recuring,   // kode_booking
        $location,      // location
        $devisi         // devisi
    );
    $insert_jurnal_stmt->execute();

    // Masukkan data ke tabel susut
    $insert_susut_stmt->bind_param(
        "sssds",
        $kode,          // kode
        $tanggal,       // tanggal
        $keterangan,    // keterangan
        $debet,         // susut
        $no_recuring    // recuring
    );
    $insert_susut_stmt->execute();

    // Masukkan nomor jurnal ke tabel JM
    $insert_jm_stmt->bind_param("s", $no_recuring);
    $insert_jm_stmt->execute();

    // Update kolom `ab` di tabel recuring
    $update_recuring_stmt->bind_param("si", $no_recuring, $id);
    $update_recuring_stmt->execute();
}

// Tutup statement dan koneksi
$insert_jurnal_stmt->close();
$insert_jm_stmt->close();
$insert_susut_stmt->close();
$update_recuring_stmt->close();
$stmt->close();
$conn->close();

// Tampilkan notifikasi berhasil dan arahkan ke cetak_jurnal.php
echo "<script>
    alert('Data jurnal, susut, dan update tabel recuring berhasil disimpan!');
    window.location.href = 'cetak_jurnal.php?kode_transaksi=" . urlencode($no_recuring) . "';
</script>";
exit();

?>
