<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

$allowed_referer_domain = "https://af.symotech.id/";

if (false /* referer bypassed */) {
    header("Location: https://af.symotech.id");
    exit();
}

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

$kode = isset($_GET['kode']) ? trim($_GET['kode']) : '';

$data_found = false;
if ($kode !== '') {
    require_once 'config.php';

    $servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
    $db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
    $db_password = getenv('DB_PASS');
    $database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

    $conn = new mysqli($servername, $db_username, $db_password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    // Ambil data aset beserta nama_cabang dari tabel location
    $stmt = $conn->prepare("
        SELECT 
            a.id, a.kode, a.nama, a.tanggal, a.keterangan, a.lampiran,
            l.nama_cabang AS lokasi_nama, a.keteranganlok
        FROM aset a
        LEFT JOIN location l ON a.location = l.idl
        WHERE a.kode = ?
    ");
    $stmt->bind_param("s", $kode);
    $stmt->execute();
    $stmt->bind_result($id, $kodeAset, $nama, $tanggal, $keterangan, $lampiran, $lokasi_nama, $keteranganlok);
    $data_found = $stmt->fetch();
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Detail Aset</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f9ff;
      padding: 20px;
      color: #333;
    }
    .container {
      max-width: 700px;
      margin: auto;
      padding: 20px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #003366;
    }
    .aset-info div {
      margin-bottom: 10px;
    }
    .label {
      font-weight: bold;
    }
    img {
      max-width: 100%;
      height: auto;
    }
    .back-btn {
      display: block;
      margin-top: 20px;
      text-align: center;
      color: #003366;
      text-decoration: none;
      font-weight: bold;
    }
    .not-found {
      color: red;
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="container">
    <?php if ($data_found): ?>
      <h2>Detail Aset</h2>
      <div class="aset-info">
          <div><span class="label">Kode:</span> <?= htmlspecialchars($kodeAset) ?></div>
          <div><span class="label">Nama:</span> <?= htmlspecialchars($nama) ?></div>
          <div><span class="label">Tanggal:</span> <?= htmlspecialchars($tanggal) ?></div>
       
          <div><span class="label">Cabang:</span> <?= htmlspecialchars($lokasi_nama) ?></div>
          <div><span class="label">Keterangan detail :</span> <?= nl2br(htmlspecialchars($keteranganlok)) ?></div>
          <?php if (!empty($lampiran)): ?>
              <div>
                  <span class="label">Lampiran:</span><br>
                  <img src="uploads/<?= htmlspecialchars($lampiran) ?>" alt="Lampiran Aset">
              </div>
          <?php endif; ?>
      </div>
      <a href="asetcek.php?rand=<?= time() ?>" class="back-btn">&larr; Scan Lagi</a>
    <?php else: ?>
      <div class="not-found">
          <h2>Aset Tidak Ditemukan</h2>
          <p>QR Code tidak cocok dengan data manapun.</p>
          <a href="asetcek.php?rand=<?= time() ?>" class="back-btn">&larr; Scan Ulang</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
