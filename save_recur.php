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



// Ambil data dari form
$mulai = $_POST['mulai'];
$akhir = $_POST['akhir'];
$kode = $_POST['kode'];
$nama = $_POST['nama'];
$location = $_POST['location'];
$devisi = $_POST['devisi'];
$keterangan = $_POST['keterangan'];
$lampiran = $_POST['lampiran'];
$coad = $_POST['coad'];
$account_named = $_POST['account_named'];
$debet = str_replace('.', '', $_POST['debet']); // Hilangkan format angka
$coak = $_POST['coak'];
$account_namek = $_POST['account_namek'];

// Ambil nomor recur terbaru dari tabel norecur
$noRecurQuery = "SELECT id FROM norecur ORDER BY id DESC LIMIT 1";
$noRecurResult = $conn->query($noRecurQuery);
if ($noRecurResult->num_rows > 0) {
    $noRecurRow = $noRecurResult->fetch_assoc();
    $noRecur = $noRecurRow['id'] + 1;
} else {
    $noRecur = 1; // Jika tidak ada data, mulai dari 1
}

// Format nomor recurring
$formattedNoRecur = sprintf("recur%05d", $noRecur);

// Simpan nomor recurring ke tabel norecur
$insertNoRecurQuery = "INSERT INTO norecur (no_recuring) VALUES (?)";
$stmtInsertNoRecur = $conn->prepare($insertNoRecurQuery);
$stmtInsertNoRecur->bind_param("s", $formattedNoRecur);
if (!$stmtInsertNoRecur->execute()) {
    die("Gagal menyimpan nomor recurring: " . $stmtInsertNoRecur->error);
}

// Update tabel aset, set kolom rekuring dengan nilai nomor recurring
$updateAsetQuery = "UPDATE aset SET recuring = ? WHERE kode = ?";
$stmtUpdateAset = $conn->prepare($updateAsetQuery);
$stmtUpdateAset->bind_param("ss", $formattedNoRecur, $kode);
if (!$stmtUpdateAset->execute()) {
    die("Gagal memperbarui tabel aset: " . $stmtUpdateAset->error);
}

echo "Nomor recurring berhasil disimpan dan tabel aset diperbarui.";

// Konversi tanggal mulai dan akhir
$startDate = new DateTime($mulai);
$endDate = new DateTime($akhir);

// Loop untuk menyimpan data per bulan
while ($startDate <= $endDate) {
    $tanggal = $startDate->format('Y-m-d');

    // Query insert ke tabel recuring
    $sql = "INSERT INTO recuring (no_recuring, kode, tanggal, keterangan, lampiran, coad, account_named, debet, coak, account_namek, kredit, location, devisi) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssss",
        $formattedNoRecur,
        $kode,
        $tanggal,
        $keterangan,
        $lampiran,
        $coad,
        $account_named,
        $debet,
        $coak,
        $account_namek,
        $debet, // Asumsi kredit sama dengan debet
        $location,
        $devisi
    );

    if (!$stmt->execute()) {
        die("Gagal menyimpan data: " . $stmt->error);
    }

    // Tambahkan 1 bulan
    $startDate->modify('+1 month');
}

// Redirect setelah berhasil menyimpan
echo "<script>
    alert('Data berhasil disimpan!');
    window.location.href='asetrecurdetail.php?kode=$kode';
</script>";

// Tutup koneksi
$stmt->close();
$stmtInsertNoRecur->close();
$conn->close();
?>
