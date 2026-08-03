<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$nik = trim($_GET['nik'] ?? '');
$error = '';
$nama = '';

/* ===============================
   ✅ PROSES KETIKA KIRIM FORM
   =============================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $alasan = trim($_POST['alasan'] ?? '');

    if ($nik && $alasan !== '') {

        // ✅ Update data terakhir di tabel asuransipa
        $stmtUpdate = $conn->prepare("
            UPDATE asuransipra
            SET alesan = ?, minta = 1
            WHERE nik = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmtUpdate->bind_param("ss", $alasan, $nik);

        if ($stmtUpdate->execute()) {
            $stmtUpdate->close();
            echo "<script>
                    alert('Permintaan edit Asuransi PRA berhasil dikirim.');
                    window.location.href='asuransipra.php';
                  </script>";
            exit;
        } else {
            $error = "Gagal mengirim permintaan edit.";
        }

    } else {
        $error = "Alasan wajib diisi.";
    }

} else {

    /* ===============================
       ✅ Ambil nama berdasarkan NIK
       =============================== */
    if ($nik) {
        $stmt = $conn->prepare("SELECT nama FROM pendaftaran WHERE nik = ?");
        $stmt->bind_param("s", $nik);
        $stmt->execute();
        $stmt->bind_result($nama);
        $stmt->fetch();
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Request Edit Asuransi PRA</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { 
            max-width: 600px; 
            margin: auto; 
            background: #fff; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        textarea { 
            width: 100%; 
            height: 120px; 
            padding: 10px; 
            margin-bottom: 15px; 
            border-radius: 4px; 
            border: 1px solid #ccc;
        }
        input[type="submit"] { 
            padding: 10px 20px; 
            background: #007bff; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        input[type="submit"]:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 10px; }
        .info { margin-bottom: 10px; font-style: italic; }
    </style>
</head>
<body>

<div class="container">
    <h2>Request Edit Asuransi PRA</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($nama): ?>
        <div class="info">NIK: <?= htmlspecialchars($nik) ?> - Nama: <?= htmlspecialchars($nama) ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="alasan">Alasan Perubahan:</label>
        <textarea name="alasan" id="alasan" required></textarea>

        <input type="submit" value="Kirim Permintaan Edit">
    </form>
</div>

</body>
</html>

<?php $conn->close(); ?>
