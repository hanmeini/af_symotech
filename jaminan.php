<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config.php';

// ✅ Ambil lokasi dari session
$location = $_SESSION['location'] ?? 0;

// ✅ Koneksi MySQLi
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// ✅ Ambil data edit & minta dari tabel kesehatan
$editData = [];
$res = $conn->query("SELECT nik, edit, minta FROM jaminan ORDER BY id DESC");
while ($row = $res->fetch_assoc()) {
    if (!isset($editData[$row['nik']])) {
        $editData[$row['nik']] = [
            'edit' => $row['edit'],
            'minta' => (int)$row['minta']
        ];
    }
}
$res->free();

// ✅ Filter pencarian
$keyword = trim($_GET['keyword'] ?? '');
$filter = '';
$params = [];
$types = '';

// ✅ Filter lokasi jika bukan HO
if ($location > 1) {
    $filter .= " AND p.cabang = ?";
    $params[] = $location;
    $types .= 'i';
}

// ✅ Filter keyword
if ($keyword !== '') {
    $keyword = "%$keyword%";
    $filter .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= 'sss';
}



// ---------- TABEL 2: Sudah Input ----------
$sql2 = "
SELECT p.id, p.nik, p.nama, l.nama_cabang, p.negara, p.tanggal_kesehatan
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN jaminan JM ON JM.nik = p.nik
WHERE JM.kkin IS NOT NULL
  AND JM.ktpin IS NOT NULL
   AND JM.ijazahin IS NOT NULL
     AND JM.aktain IS NOT NULL
       AND JM.bunikin IS NOT NULL
      


$filter
ORDER BY p.tanggal_kesehatan DESC
";
$stmt2 = $conn->prepare($sql2);
if ($types) $stmt2->bind_param($types, ...$params);
$stmt2->execute();
$stmt2->bind_result($id, $nik, $nama, $nama_cabang, $negara, $tanggal_kesehatan);

$data_sudah = [];
while ($stmt2->fetch()) {
    $umur = ($tanggal_kesehatan && $tanggal_kesehatan !== '0000-00-00')
        ? (new DateTime($tanggal_kesehatan))->diff(new DateTime())->days
        : 0;
    $edit_val = $editData[$nik]['edit'] ?? '';
    $minta_val = $editData[$nik]['minta'] ?? 0;
    $data_sudah[] = compact('id', 'nik', 'nama', 'nama_cabang', 'negara', 'tanggal_kesehatan', 'umur', 'edit_val', 'minta_val');
}
$stmt2->close();


?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data TKI Kesehatan</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2, h3 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"] { padding: 8px; width: 250px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn-kesehatan {
            background: #17a2b8;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-kesehatan:hover { background: #138496; }
        .btn-disabled {
            background: gray;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-reqedit {
            background: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            text-decoration: none;
        }
        
        .home-btn {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
        <a href="home.php" class="home-btn">🏠</a>
<div class="container">
    <h2>Data JAMINAN TKI</h2>

<form method="get">
    <input type="text" name="keyword" 
           placeholder="Cari NIK / Nama / Cabang" 
           value="<?= htmlspecialchars($_GET['keyword'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="submit" value="Cari">
<a class="btn-reqedit" 
   id="btnInputBaru"
   href="jaminanin.php?nik=">
   INPUT BARU
</a>

</form>

   
    
        <h3>Jaminan</h3>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
    
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($data_sudah as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nik']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
       
                <td>
                    <?php if ($row['edit_val'] === 'open'): ?>
                        <a class="btn-kesehatan" href="jaminanin.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                    <?php elseif ($row['minta_val'] == 1): ?>
                        <span class="btn-disabled">Menunggu Izin</span>
                    <?php else: ?>
                        <a class="btn-reqedit" href="reqedit_jaminan.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
                    <?php endif; ?><br><br>
                    <a class="btn-kesehatan" href="jaminanout.php?nik=<?= urlencode($row['nik']) ?>">PENGAMBILAN</a><br><br>
                    <a class="btn-kesehatan" href="jaminandetail.php?nik=<?= urlencode($row['nik']) ?>">DETAIL</a><br><br>
                       <a class="btn-kesehatan" href="jaminanupload.php?nik=<?= urlencode($row['nik']) ?>">UPLOADTT</a>
                    
                </td>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const input = document.querySelector("input[name='keyword']");
    const btnInputBaru = document.getElementById("btnInputBaru");

    input.addEventListener("input", function() {
        let kw = input.value.trim();
        btnInputBaru.href = "jaminanin.php?nik=" + encodeURIComponent(kw);
    });
});
</script>

</body>
</html>
<?php $conn->close(); ?>
