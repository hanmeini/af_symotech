<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

/* =======================
   KONEKSI DATABASE
======================= */
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) die("Koneksi gagal");

/* =======================
   AJAX CEK NIK
   - Ambil pendaftaran TERAKHIR
   - Cek apakah sudah ada di tabel masalah
======================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cek_nik') {

    $nik16 = substr(trim($_GET['nik'] ?? ''), 0, 16);

    $data = [
        'nama'   => '',
        'cabang' => '',
        'ada'    => false
    ];

    if (strlen($nik16) === 16) {

        /* CEK SUDAH ADA DI MASALAH */
        $cek = $conn->prepare("
            SELECT 1 
            FROM masalah 
            WHERE nik = ? AND (done_masalah = 0 OR done_masalah IS NULL)
            LIMIT 1
        ");
        $cek->bind_param("s", $nik16);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $data['ada'] = true;
            $cek->close();

            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
        $cek->close();

        /* AMBIL DATA PENDAFTARAN TERAKHIR */
        $sql = "
            SELECT 
                p.nama,
                l.nama_cabang
            FROM pendaftaran p
            LEFT JOIN location l ON l.idl = p.cabang
            WHERE LEFT(p.nik,16) = ?
            ORDER BY p.id DESC
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nik16);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $data['nama']   = $row['nama'];
            $data['cabang'] = $row['nama_cabang'];
        }
        $stmt->close();
    }

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/* =======================
   PROSES SIMPAN
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal = $_POST['tanggal'] ?? '';
    $nik     = trim($_POST['nik'] ?? '');
    $masalah = trim($_POST['masalah'] ?? '');

    if (!$tanggal || !$nik || !$masalah) {
        echo "<script>alert('Tanggal, NIK, dan Masalah wajib diisi');</script>";
    } else {

        $nik16 = substr($nik, 0, 16);

        /* CEK DUPLIKAT (SERVER SIDE) */
        $cek = $conn->prepare("
            SELECT 1 
            FROM masalah 
            WHERE nik = ? AND (done_masalah = 0 OR done_masalah IS NULL)
            LIMIT 1
        ");
        $cek->bind_param("s", $nik16);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            echo "<script>
                alert('NIK ini masih memiliki masalah yang belum selesai');
                history.back();
            </script>";
            exit;
        }
        $cek->close();

        $conn->begin_transaction();

        try {

            /* AUTO ID */
            $q  = $conn->query("SELECT IFNULL(MAX(id),0)+1 AS next_id FROM masalah");
            $id = $q->fetch_assoc()['next_id'];

            $progress_masalah = '';
            $done_masalah     = 0;
            $edit             = 'open';

            /* INSERT MASALAH */
            $stmt = $conn->prepare("
                INSERT INTO masalah
                (id, tanggal, nik, masalah, progress_masalah, done_masalah, edit)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issssis",
                $id,
                $tanggal,
                $nik16,
                $masalah,
                $progress_masalah,
                $done_masalah,
                $edit
            );
            $stmt->execute();
            $stmt->close();

            /* UPDATE STATUS PENDAFTARAN */
            $up = $conn->prepare("
                UPDATE pendaftaran
                SET status = 'nonaktive'
                WHERE LEFT(nik,16) = ?
            ");
            $up->bind_param("s", $nik16);
            $up->execute();
            $up->close();

            $conn->commit();

            echo "<script>
                alert('Masalah berhasil disimpan');
                location='masalah.php';
            </script>";
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Terjadi kesalahan penyimpanan');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Input Masalah</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f0f0f0;
    padding: 20px;
}
.container {
    max-width: 600px;
    background: #fff;
    padding: 20px;
    margin: auto;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
h2 { text-align:center; }
label {
    display: block;
    margin-top: 15px;
    font-weight: bold;
}
input, textarea {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    border-radius: 4px;
    border: 1px solid #ccc;
}
input[readonly] { background: #f5f5f5; }
textarea { min-height: 120px; }
.btn {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
}
input[type="submit"], .btn a {
    width: 48%;
    padding: 10px;
    font-weight: bold;
    border-radius: 4px;
    text-align: center;
    text-decoration: none;
}
input[type="submit"] {
    background: #28a745;
    color: #fff;
    border: none;
    cursor: pointer;
}
.btn a {
    background: #6c757d;
    color: #fff;
}
.error {
    color: red;
    margin-top: 10px;
    font-weight: bold;
}
</style>
</head>
<body>

<div class="container">
<h2>Input Masalah</h2>

<form method="post" id="formMasalah">
    <label>Tanggal</label>
    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>

    <label>NIK</label>
    <input type="text" name="nik" id="nik" required>

    <label>Nama</label>
    <input type="text" id="nama" readonly>

    <label>Cabang</label>
    <input type="text" id="cabang" readonly>

    <div id="msg" class="error"></div>

    <label>Masalah</label>
    <textarea name="masalah" required></textarea>

    <div class="btn">
        <input type="submit" value="Simpan">
        <a href="masalah.php">Batal</a>
    </div>
</form>
</div>

<script>
const nikInput = document.getElementById('nik');
const msg      = document.getElementById('msg');
const submitBtn = document.querySelector('input[type="submit"]');

nikInput.addEventListener('keyup', function () {
    const nik = this.value.substring(0,16);

    msg.textContent = '';
    submitBtn.disabled = false;

    if (nik.length === 16) {
        fetch(`inputmasalah.php?ajax=cek_nik&nik=${nik}`)
        .then(r => r.json())
        .then(d => {

            if (d.ada) {
                msg.textContent = 'NIK ini masih memiliki masalah yang belum selesai';
                document.getElementById('nama').value = '';
                document.getElementById('cabang').value = '';
                submitBtn.disabled = true;
                return;
            }

            document.getElementById('nama').value   = d.nama || '';
            document.getElementById('cabang').value = d.cabang || '';
        });
    } else {
        document.getElementById('nama').value = '';
        document.getElementById('cabang').value = '';
    }
});
</script>

</body>
</html>

<?php $conn->close(); ?>
