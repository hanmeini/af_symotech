
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

/* =======================
   KONEKSI DB
======================= */
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) die("Koneksi gagal");

/* =======================
   VALIDASI NIK
======================= */
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan');location='belumkeluarid.php';</script>";
    exit;
}

/* =======================
   PROSES SIMPAN
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal_setelah_penempatan = $_POST['tanggal_keluar_id'] ?? '';

    if (!$tanggal_setelah_penempatan) {
        echo "<script>alert('Tanggal wajib diisi');</script>";
    } else {

        $nik_lama = $nik;
        $nik_baru = $nik . 'T';

        $conn->begin_transaction();
        try {

            /* UPDATE tanggal setelah penempatan */
            $up = $conn->prepare("
                UPDATE pendaftaran
                SET tanggal_setelah_penempatan = ?
                WHERE nik = ?
            ");
            $up->bind_param("ss", $tanggal_setelah_penempatan, $nik_lama);
            if (!$up->execute()) {
                throw new Exception("Gagal update tanggal penempatan");
            }
            $up->close();

            /* AMBIL SEMUA TABEL */
            $tables = [];
            $res = $conn->query("SHOW TABLES");
            while ($row = $res->fetch_array()) {
                $tables[] = $row[0];
            }

            /* UPDATE SEMUA KOLOM nik */
            foreach ($tables as $table) {

                $cek = $conn->query("
                    SELECT COUNT(*) AS ada
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '$table'
                    AND COLUMN_NAME = 'nik'
                ");
                $ada = $cek->fetch_assoc()['ada'];

                if ($ada > 0) {
                    $sql = "UPDATE `$table` SET nik=? WHERE nik=?";
                    $st  = $conn->prepare($sql);
                    $st->bind_param("ss", $nik_baru, $nik_lama);
                    $st->execute();
                    $st->close();
                }
            }

    /* UPDATE STATUS PENDAFTARAN */
            $up = $conn->prepare("
                UPDATE pendaftaran
                SET status = 'nonaktive'
                WHERE NIK  = ?
            ");
            $up->bind_param("s", $nik_baru);
            $up->execute();
            $up->close();
            $conn->commit();

            echo "<script>
                alert('Tanggal terbang disimpan & NIK berhasil ditandai T');
                location='detail_pekerja.php?nik=" . urlencode($nik_baru) . "';
            </script>";
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            die("Proses gagal: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>SUDAH TERBANG</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
        .container {
            max-width: 600px;
            background: #fff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input {
            width: 100%; padding: 8px; margin-top: 5px;
            border-radius: 4px; border: 1px solid #ccc;
        }
        input[type="submit"] {
            background: #28a745;
            color: white;
            font-weight: bold;
            border: none;
            margin-top: 20px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background: #218838; }
        .home-icon1, .left-icon {
            position: absolute; top: 0; font-size: 24px; color: maroon;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="daftaruser.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
</div>

<div class="container">
    <h2>Input Data sudah terbang</h2>

    <form method="post">
        <label>Tanggal Terbang</label>
        <input type="date" name="tanggal_keluar_id" value="<?= date('Y-m-d') ?>" required>

        <label>NIK</label>
        <input type="text" value="<?= htmlspecialchars($nik) ?>" readonly>

        <input type="submit" value="Simpan Data">
    </form>
</div>
</body>
</html>

<?php $conn->close(); ?>
