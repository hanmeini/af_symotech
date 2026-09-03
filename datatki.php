<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

if (!isset($_SESSION['location'])) {
    die("Session tidak valid.");
}
$session_location = intval($_SESSION['location']);

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// ✅ Ambil negara unik
$negaraList = [];
$negQuery = $conn->query("SELECT DISTINCT negara FROM pendaftaran WHERE negara <> '' ORDER BY negara");
while ($r = $negQuery->fetch_assoc()) {
    $negaraList[] = $r['negara'];
}

// ✅ Aktifkan / Nonaktifkan
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $toggle = ($_GET['toggle'] === 'nonaktive') ? 'nonaktive' : 'aktive';

    $stmt = $conn->prepare("UPDATE pendaftaran SET status=? WHERE id=?");
    $stmt->bind_param("si", $toggle, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: datatki.php");
    exit;
}

// ==============================
// ✅ FILTER PENCARIAN
// ==============================
$keyword = trim($_GET['keyword'] ?? '');
$filter_negara = trim($_GET['negara'] ?? '');

$where = "1";
$params = [];
$types = "";

// ✅ Jika cabang > 1 → tampilkan hanya cabang user
if ($session_location > 1) {
    $where .= " AND p.cabang = ?";
    $params[] = $session_location;
    $types .= "i";
}

// ✅ Keyword pencarian
// Cari berdasarkan NIK / nama / nama_cabang
if ($keyword !== "") {
    $keywordLike = "%$keyword%";

    $where .= " AND (p.nik LIKE ? OR p.nama LIKE ? OR l.nama_cabang LIKE ?)";
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $params[] = $keywordLike;
    $types .= "sss";
}

// ✅ Filter negara
if ($filter_negara !== "") {
    $where .= " AND p.negara = ?";
    $params[] = $filter_negara;
    $types .= "s";
}

// ==============================
// ✅ QUERY UTAMA
// ==============================
$sql = "
SELECT 
    p.id, p.nik, p.nama, l.nama_cabang, p.status, p.negara,
    p.tanggal_pendaftaran, p.edit, p.minta,
    CASE 
        WHEN p.tanggal_setelah_penempatan IS NOT NULL AND p.tanggal_setelah_penempatan <> '0000-00-00' THEN 'Setelah Penempatan'
        WHEN p.tanggal_pembuatan_pasport IS NOT NULL AND p.tanggal_pembuatan_pasport <> '0000-00-00' THEN 'Pembuatan Pasport'
        WHEN p.tanggal_keluar_id IS NOT NULL AND p.tanggal_keluar_id <> '0000-00-00' THEN 'Keluar ID'
        WHEN p.tanggal_saat_job IS NOT NULL AND p.tanggal_saat_job <> '0000-00-00' THEN 'Saat Job'
        WHEN p.tanggal_sebelum_job IS NOT NULL AND p.tanggal_sebelum_job <> '0000-00-00' THEN 'Sebelum Job'
        WHEN p.tanggal_ujikom IS NOT NULL AND p.tanggal_ujikom <> '0000-00-00' THEN 'Ujikom'
        WHEN p.tanggal_asuransipra IS NOT NULL AND p.tanggal_asuransipra <> '0000-00-00' THEN 'Asuransi Pra'
        WHEN p.tanggal_sikotes IS NOT NULL AND p.tanggal_sikotes <> '0000-00-00' THEN 'Sikotes'
        WHEN p.tanggal_kesehatan2 IS NOT NULL AND p.tanggal_kesehatan2 <> '0000-00-00' THEN 'Kesehatan 2'
        WHEN p.tanggal_kesehatan IS NOT NULL AND p.tanggal_kesehatan <> '0000-00-00' THEN 'Kesehatan'
        WHEN p.tanggal_pendaftaran IS NOT NULL AND p.tanggal_pendaftaran <> '0000-00-00' THEN 'Pendaftaran'
        ELSE 'Belum Proses'
    END AS proses
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE $where
ORDER BY p.tanggal_pendaftaran DESC
";

$stmt = $conn->prepare($sql);

if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$stmt->bind_result($id, $nik, $nama, $nama_cabang, $status, $negara, $tanggal, $edit, $minta, $proses);

$data = [];
while ($stmt->fetch()) {
    $data[] = [
        "id"      => $id,
        "nik"     => $nik,
        "nama"    => $nama,
        "cabang"  => $nama_cabang,
        "status"  => $status,
        "negara"  => $negara,
        "tanggal" => $tanggal,
        "edit"    => $edit,
        "minta"   => $minta,
        "proses"  => $proses
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data TKI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1100px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,.1); }
        h2 { text-align: center; }
        form { margin-bottom: 20px; text-align: center; }
        input[type="text"], select { padding: 8px; width: 250px; margin-right: 10px; }
        input[type="submit"] { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #343a40; color: white; }
        tr:nth-child(even) { background: #f2f2f2; }
        .btn { padding: 6px 10px; border-radius: 4px; color: white; text-decoration: none; margin-right: 4px; display: inline-block; }
        .btn-detail { background: #28a745; }
        .btn-edit { background: #ffc107; color: black; }
        .btn-req { background: blue; }
        .btn-nonaktif { background: #dc3545; }
        .btn-aktif { background: #17a2b8; }
        .btn-gray { background: #6c757d; color: white; }
        .status-aktive { color: green; font-weight: bold; }
        .status-nonaktive { color: red; font-weight: bold; }
        .status-batal { background: #dc3545; color: white; font-weight: bold; padding: 3px 8px; border-radius: 4px; display: inline-block; font-size: 12px; }
        .home-btn { display: inline-block; margin-bottom: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; }
        
        td .btn {
    margin: 4px 2px; /* jarak antar tombol */
    display: inline-block;
}

td {
    white-space: normal; /* agar wrap bisa terjadi */
}

@media (max-width: 768px) {
    td .btn {
        display: block;      /* tampil satu per baris di layar kecil */
        width: 100%;
    }
}

    </style>
</head>
<body>
    <a href="home.php" class="home-btn"><i class="fa-solid fa-arrow-left"></i> Kembali ke Menu</a>
<div class="container">
    <h2>Data TKI Terdaftar</h2>

    <form method="get">
        <input type="text" name="keyword" placeholder="Cari NIK / Nama / Cabang" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
        <select name="negara">
            <option value="">-- Semua Negara --</option>
            <?php foreach ($negaraList as $neg): ?>
                <option value="<?= htmlspecialchars($neg) ?>" <?= ($neg == ($_GET['negara'] ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($neg) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" value="Cari">
    </form>

    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>NIK</th>
            <th>Nama</th>
            <th>Cabang</th>
            <th>Negara</th>
            <th>Status</th>
           <th>Tanggal Daftar</th>
            <th>Proses</th>
            <th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!empty($data)): ?>
            <?php foreach ($data as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['nik']) ?></td>
                    <td><?= htmlspecialchars($row['nama']) ?></td>
                    <td><?= htmlspecialchars($row['cabang']) ?></td>
                   <td><?= htmlspecialchars($row['negara'] ?? '') ?></td>
                    <td class="status-<?= $row['status'] ?>">
                        <?php if ($row['status'] === 'aktive'): ?>
                            <span class="status-aktive">Aktif</span>
                        <?php elseif ($row['status'] === 'batal'): ?>
                            <span class="status-batal">BATAL (UNFIT)</span>
                        <?php else: ?>
                            <span class="status-nonaktive">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                  
                    <td><?= $row['tanggal'] ?></td>
                     <td><?= $row['proses'] ?></td>
                    
                    <td>
                        <a class="btn btn-detail" href="detail_pekerja.php?nik=<?= urlencode($row['nik']) ?>">Detail</a>
                        <?php if ($row['status'] === 'aktive'): ?>
                            <a class="btn btn-nonaktif" href="?id=<?= $row['id'] ?>&toggle=nonaktive" onclick="return confirm('Nonaktifkan data ini?')">Nonaktifkan</a>
                        <?php else: ?>
                            <a class="btn btn-aktif" href="?id=<?= $row['id'] ?>&toggle=aktive" onclick="return confirm('Aktifkan data ini?')">Aktifkan</a>
                        <?php endif; ?>

                        <?php if ($row['edit'] === 'open'): ?>
                            <a class="btn btn-edit" href="edit_pendaftaran.php?nik=<?= urlencode($row['nik']) ?>">Edit</a>
                        <?php elseif ((int)$row['minta'] === 1): ?>
                            <span class="btn btn-gray">Menunggu Izin</span>
                        <?php else: ?>
                            <a class="btn btn-req" href="reqedit_pendaftaran.php?nik=<?= urlencode($row['nik']) ?>">Req Edit</a>
                        <?php endif; ?>
                     
                            <a class="btn btn-req" href="jaminanin.php?nik=<?= urlencode($row['nik']) ?>">input jaminan</a>
                 
                        
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9" style="text-align:center;">Data tidak ditemukan.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?>
