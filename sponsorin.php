<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

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

// Ambil daftar bank
$bankList = $conn->query("SELECT id_bank, n_bank FROM bank ORDER BY n_bank");

// Ambil data sponsor untuk edit jika ada
$edit = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $st = $conn->prepare("SELECT id_sponsor, nik, nama, alamat, telp, id_bank, norekb, tanggal FROM sponsor WHERE id_sponsor=?");
    $st->bind_param("i", $id);
    $st->execute();
    $st->bind_result($id_sponsor, $nik, $nama, $alamat, $telp, $id_bank, $norekb, $tanggal);
    if ($st->fetch()) {
        $edit = [
            'id_sponsor' => $id_sponsor,
            'nik'        => $nik,
            'nama'       => $nama,
            'alamat'     => $alamat,
            'telp'       => $telp,
            'id_bank'    => $id_bank,
            'norekb'     => $norekb,
            'tanggal'    => $tanggal
        ];
    }
    $st->close();
}

// Proses simpan/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nik     = trim($_POST['nik'] ?? '');
    $nama    = trim($_POST['nama'] ?? '');
    $alamat  = trim($_POST['alamat'] ?? '');
    $telp    = trim($_POST['telp'] ?? '');
    $id_bank = intval($_POST['id_bank'] ?? 0);
    $norekb  = trim($_POST['norekb'] ?? '');
    $id_edit = intval($_POST['id_edit'] ?? 0);

    if ($nik && $nama && $alamat && $telp && $id_bank && $norekb) {
        if ($id_edit === 0) {
            $stmt = $conn->prepare("INSERT INTO sponsor (nik, nama, alamat, telp, id_bank, norekb) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $nik, $nama, $alamat, $telp, $id_bank, $norekb);
        } else {
            $stmt = $conn->prepare("UPDATE sponsor SET nik=?, nama=?, alamat=?, telp=?, id_bank=?, norekb=? WHERE id_sponsor=?");
            $stmt->bind_param("ssssisi", $nik, $nama, $alamat, $telp, $id_bank, $norekb, $id_edit);
        }

        if ($stmt->execute()) {
            echo "<script>alert('Data berhasil disimpan'); location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
            exit;
        } else {
            echo "<script>alert('Gagal menyimpan data');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Semua kolom harus diisi');</script>";
    }
}

// Tampilkan semua data sponsor
$list_stmt = $conn->prepare("
    SELECT s.id_sponsor, s.nik, s.nama, s.alamat, s.telp, b.n_bank, s.norekb,
           DATE_FORMAT(s.tanggal, '%d-%m-%Y %H:%i') as tgl
    FROM sponsor s
    LEFT JOIN bank b ON s.id_bank = b.id_bank
    ORDER BY s.tanggal DESC
");
$list_stmt->execute();
$list_stmt->bind_result($id_sponsor, $nik, $nama, $alamat, $telp, $n_bank, $norekb, $tgl);
$sponsorList = [];
while ($list_stmt->fetch()) {
    $sponsorList[] = [
        'id_sponsor' => $id_sponsor,
        'nik'        => $nik,
        'nama'       => $nama,
        'alamat'     => $alamat,
        'telp'       => $telp,
        'n_bank'     => $n_bank,
        'norekb'     => $norekb,
        'tgl'        => $tgl
    ];
}
$list_stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Data Sponsor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background: #f0f0f0; }
        .container { width: 90%; max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #28a745; color: white; cursor: pointer; border: none; margin-top: 15px; }
        input[type="submit"]:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background: #4CAF50; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { background: #007bff; color: white; padding: 4px 8px; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
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
        <h1><?= $edit ? 'Edit' : 'Tambah' ?> Data Sponsor</h1>
        <form method="post">
            <label>NIK</label>
            <input type="text" name="nik" value="<?= $edit ? htmlspecialchars($edit['nik'] ?? '') : '' ?>" required>

            <label>Nama</label>
            <input type="text" name="nama" value="<?= $edit ? htmlspecialchars($edit['nama'] ?? '') : '' ?>" required>

            <label>Alamat</label>
            <textarea name="alamat" rows="3" required><?= $edit ? htmlspecialchars($edit['alamat'] ?? '') : '' ?></textarea>

            <label>Telepon</label>
            <input type="text" name="telp" value="<?= $edit ? htmlspecialchars($edit['telp'] ?? '') : '' ?>" required>

            <label>Bank</label>
            <select name="id_bank" required>
                <option value="">-- Pilih Bank --</option>
                <?php $bankList->data_seek(0); while($bank = $bankList->fetch_assoc()): ?>
                    <option value="<?= $bank['id_bank'] ?>" <?= $edit && $edit['id_bank'] == $bank['id_bank'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bank['n_bank'] ?? '') ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>No. Rekening</label>
            <input type="text" name="norekb" value="<?= $edit ? htmlspecialchars($edit['norekb'] ?? '') : '' ?>" required>

            <?php if ($edit): ?>
                <input type="hidden" name="id_edit" value="<?= $edit['id_sponsor'] ?>">
            <?php endif; ?>

            <input type="submit" value="<?= $edit ? 'Update' : 'Simpan' ?>">
        </form>

        <h2>Daftar Sponsor</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>NIK</th>
                    <th>Nama</th>
                    <th>Alamat</th>
                    <th>Telp</th>
                    <th>Bank</th>
                    <th>No. Rek</th>
                    <th>Tanggal</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($sponsorList)): ?>
                <?php foreach ($sponsorList as $row): ?>
                    <tr>
                        <td><?= $row['id_sponsor'] ?></td>
                        <td><?= htmlspecialchars($row['nik'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['alamat'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['telp'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['n_bank'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['norekb'] ?? '') ?></td>
                        <td><?= $row['tgl'] ?></td>
                        <td><a class="btn" href="?edit=<?= $row['id_sponsor'] ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9">Belum ada data</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php $conn->close(); ?>
