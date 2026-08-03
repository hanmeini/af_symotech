<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';

// ====== PASTIKAN SESSION LENGKAP ======
if (!isset($_SESSION['userid']) || !isset($_SESSION['location'])) {
    die("Akses ditolak.");
}

$session_location = intval($_SESSION['location']); // HO = 1, cabang lainnya > 1

// ====== KONEKSI DB ======
$conn = new mysqli(
    getenv('DB_HOST') ?: die("DB_HOST belum diset"),
    getenv('DB_USER') ?: die("DB_USER belum diset"),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die("DB_NAME belum diset")
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// ====== LOKASI UPLOAD ======
$upload_dir = __DIR__ . '/pekerja/';

// ====== FUNGSI UPLOAD ======
function upload_file($key, $prefix, $dir, $old)
{
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === 0) {

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            die("Format file $key tidak valid.");
        }

        $filename = uniqid($prefix . '_') . '.' . $ext;
        $path = "$dir/$filename";

        if (!move_uploaded_file($_FILES[$key]['tmp_name'], $path)) {
            die("Gagal upload $key");
        }

        return $filename;
    }
    return $old;
}

/* ================= AMBIL NIK 16 DIGIT KIRI ================= */
$nik_input = $_GET['nik'] ?? '';
if (!$nik_input) die("NIK tidak ditemukan");

$nik_prefix = substr($nik_input, 0, 16);

// ====== AMBIL DATA LAMA ======
$stmt = $conn->prepare("SELECT 
    nik, nama, tempat_lahir, tanggal_lahir, pendidikan,
    alamat, kelurahan, kecamatan, kabupaten, telp, email,
    ft, ktp, kk, ftp, perkawinan,
    nama_ibu, nama_ayah, alamat_orang_tua, telp_ortu,
    nama_ahli_waris, hubungan, alamat_ahli_waris, telp_ahli_waris, email_ahli_waris,
    cabang, sponsor, negara, tanggal_pendaftaran
    FROM pendaftaran WHERE LEFT(nik,16)=?
    ORDER BY nik DESC
    LIMIT 1
");
$stmt->bind_param("s", $nik_prefix);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("Data tidak ditemukan.");
}

$stmt->bind_result(
    $nik, $nama, $tempat_lahir, $tanggal_lahir, $pendidikan,
    $alamat, $kelurahan, $kecamatan, $kabupaten, $telp, $email,
    $ft, $ktp, $kk, $ftp, $perkawinan,
    $nama_ibu, $nama_ayah, $alamat_orang_tua, $telp_ortu,
    $nama_ahli_waris, $hubungan, $alamat_ahli_waris, $telp_ahli_waris, $email_ahli_waris,
    $cabang, $sponsor, $negara, $tanggal_pendaftaran
);
$stmt->fetch();
$stmt->close();

// ====== DROPDOWN ======
$negaraList  = $conn->query("SELECT negara FROM negara WHERE status='aktive'");
$sponsorList = $conn->query("SELECT nik,nama FROM sponsor ORDER BY nama ASC");
$cabangList  = $conn->query("SELECT idl, nama_cabang FROM location ORDER BY nama_cabang ASC");

// ====== UPDATE DATA ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama              = trim($_POST['nama']);
    $tempat_lahir      = trim($_POST['tempat_lahir']);
    $tanggal_lahir     = $_POST['tanggal_lahir'];
    $pendidikan        = trim($_POST['pendidikan']);
    $alamat            = trim($_POST['alamat']);
    $kelurahan         = trim($_POST['kelurahan']);
    $kecamatan         = trim($_POST['kecamatan']);
    $kabupaten         = trim($_POST['kabupaten']);
    $telp              = trim($_POST['telp']);
    $email             = trim($_POST['email']);
    $perkawinan        = trim($_POST['perkawinan']);
    $nama_ibu          = trim($_POST['nama_ibu']);
    $nama_ayah         = trim($_POST['nama_ayah']);
    $alamat_orang_tua  = trim($_POST['alamat_orang_tua']);
    $telp_ortu         = trim($_POST['telp_ortu']);
    $nama_ahli_waris   = trim($_POST['nama_ahli_waris']);
    $hubungan          = trim($_POST['hubungan']);
    $alamat_ahli_waris = trim($_POST['alamat_ahli_waris']);
    $telp_ahli_waris   = trim($_POST['telp_ahli_waris']);
    $email_ahli_waris  = trim($_POST['email_ahli_waris']);
    $negara            = trim($_POST['negara']);
    $sponsor           = trim($_POST['sponsor']);
    $tanggal_pendaftaran = $_POST['tanggal_pendaftaran'];

    // ✅ Cabang sesuai aturan HO / CABANG
    if ($session_location == 1) {
        $cabang = intval($_POST['cabang']); // HO boleh pilih bebas
    } else {
        $cabang = $session_location;        // CABANG dipaksa ikut session
    }

    // FILE UPLOAD
    $ft  = upload_file('ft', 'foto', $upload_dir, $ft);
    $ktp = upload_file('ktp', 'ktp', $upload_dir, $ktp);
    $kk  = upload_file('kk', 'kk', $upload_dir, $kk);
    $ftp = upload_file('ftp', 'ftp', $upload_dir, $ftp);

    // UPDATE
    $stmt = $conn->prepare("insert pendaftaran SET
        nama=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?,
        alamat=?, kelurahan=?, kecamatan=?, kabupaten=?, telp=?,
        email=?, perkawinan=?, ft=?, ktp=?, kk=?, ftp=?,
        nama_ibu=?, nama_ayah=?, alamat_orang_tua=?, telp_ortu=?,
        nama_ahli_waris=?, hubungan=?, alamat_ahli_waris=?, telp_ahli_waris=?, email_ahli_waris=?,
        cabang=?, sponsor=?, negara=?, tanggal_pendaftaran=?, edit='clouse'
        WHERE nik=?
    ");

    $stmt->bind_param(
        "ssssssssssssssssssssssssissss",
        $nama, $tempat_lahir, $tanggal_lahir, $pendidikan,
        $alamat, $kelurahan, $kecamatan, $kabupaten, $telp,
        $email, $perkawinan, $ft, $ktp, $kk, $ftp,
        $nama_ibu, $nama_ayah, $alamat_orang_tua, $telp_ortu,
        $nama_ahli_waris, $hubungan, $alamat_ahli_waris, $telp_ahli_waris, $email_ahli_waris,
        $cabang, $sponsor, $negara, $tanggal_pendaftaran, $nik
    );

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil copyi');location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';</script>";
    } else {
        echo "<script>alert('Gagal: {$stmt->error}');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Copy Pendaftaran</title>
    <style>
        body { font-family: Arial; background:#f5f5f5; }
        .container { max-width:900px; margin:20px auto; padding:20px; background:white; border-radius:8px; }
        label { font-weight:bold; margin-top:10px; display:block; }
        input, textarea, select { width:100%; padding:7px; margin-top:3px; }
        input[type="submit"] { background:#007bff; color:white; border:0; margin-top:15px; }
        input[type="submit"]:hover { background:#0056b3; cursor:pointer; }
        .preview img { max-height:100px; margin-top:3px; }
    </style>
</head>
<body>
<div class="container">
<h2>Edit Pendaftaran</h2>

<form method="post" enctype="multipart/form-data">

<label>NIK (tidak dapat diubah)</label>
<input type="text" value="<?= htmlspecialchars($nik_prefix) ?>" disabled>

<label>Tanggal Pendaftaran</label>
<input type="date" name="tanggal_pendaftaran" value="<?= $tanggal_pendaftaran ?>">

<label>Nama</label>
<input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>">

<label>Tempat Lahir</label>
<input type="text" name="tempat_lahir" value="<?= htmlspecialchars($tempat_lahir) ?>">

<label>Tanggal Lahir</label>
<input type="date" name="tanggal_lahir" value="<?= $tanggal_lahir ?>">

<label>Pendidikan</label>
<input type="text" name="pendidikan" value="<?= htmlspecialchars($pendidikan) ?>">

<label>Alamat</label>
<textarea name="alamat"><?= htmlspecialchars($alamat) ?></textarea>

<label>Provinsi</label>
<select id="select_prov" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;">
    <option value="">-- Pilih Provinsi --</option>
</select>
<input type="hidden" id="provinsi" name="provinsi">

<label>Kabupaten/Kota</label>
<select id="select_kab" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;">
    <option value=""><?= $kabupaten ? htmlspecialchars($kabupaten) : '-- Pilih Kabupaten --' ?></option>
</select>
<input type="hidden" id="kabupaten" name="kabupaten" value="<?= htmlspecialchars($kabupaten) ?>">

<label>Kecamatan</label>
<select id="select_kec" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;">
    <option value=""><?= $kecamatan ? htmlspecialchars($kecamatan) : '-- Pilih Kecamatan --' ?></option>
</select>
<input type="hidden" id="kecamatan" name="kecamatan" value="<?= htmlspecialchars($kecamatan) ?>">

<label>Kelurahan</label>
<select id="select_kel" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;">
    <option value=""><?= $kelurahan ? htmlspecialchars($kelurahan) : '-- Pilih Kelurahan --' ?></option>
</select>
<input type="hidden" id="kelurahan" name="kelurahan" value="<?= htmlspecialchars($kelurahan) ?>">

<label>Telp</label>
<input type="text" name="telp" value="<?= htmlspecialchars($telp) ?>">

<label>Email</label>
<input type="email" name="email" value="<?= htmlspecialchars($email) ?>">

<label>Status Perkawinan</label>
<select name="perkawinan">
    <option value="lajang" <?= $perkawinan=='lajang'?'selected':'' ?>>Lajang</option>
    <option value="menikah" <?= $perkawinan=='menikah'?'selected':'' ?>>Menikah</option>
    <option value="duda cerai mati" <?= $perkawinan=='duda cerai mati'?'selected':'' ?>>Duda Cerai Mati</option>
    <option value="duda cerai hidup" <?= $perkawinan=='duda cerai hidup'?'selected':'' ?>>Duda Cerai Hidup</option>
    <option value="janda cerai mati" <?= $perkawinan=='janda cerai mati'?'selected':'' ?>>Janda Cerai Mati</option>
    <option value="janda cerai hidup" <?= $perkawinan=='janda cerai hidup'?'selected':'' ?>>Janda Cerai Hidup</option>
</select>

<label>Foto</label>
<?php if ($ft): ?><img src="/pekerja/<?= $ft ?>" height="80"><?php endif; ?>
<input type="file" name="ft">

<label>KTP</label>
<?php if ($ktp): ?><a href="/pekerja/<?= $ktp ?>" target="_blank">Lihat KTP</a><?php endif; ?>
<input type="file" name="ktp">

<label>KK</label>
<?php if ($kk): ?><a href="/pekerja/<?= $kk ?>" target="_blank">Lihat KK</a><?php endif; ?>
<input type="file" name="kk">

<label>Surat Perkawinan / Cerai</label>
<?php if ($ftp): ?><a href="/pekerja/<?= $ftp ?>" target="_blank">Lihat Dokumen</a><?php endif; ?>
<input type="file" name="ftp">

<label>Nama Ibu</label>
<input type="text" name="nama_ibu" value="<?= htmlspecialchars($nama_ibu) ?>">

<label>Nama Ayah</label>
<input type="text" name="nama_ayah" value="<?= htmlspecialchars($nama_ayah) ?>">

<label>Alamat Orang Tua</label>
<input type="text" name="alamat_orang_tua" value="<?= htmlspecialchars($alamat_orang_tua) ?>">

<label>Telp Orang Tua</label>
<input type="text" name="telp_ortu" value="<?= htmlspecialchars($telp_ortu) ?>">

<label>Nama Ahli Waris</label>
<input type="text" name="nama_ahli_waris" value="<?= htmlspecialchars($nama_ahli_waris) ?>">

<label>Hubungan</label>
<input type="text" name="hubungan" value="<?= htmlspecialchars($hubungan) ?>">

<label>Alamat Ahli Waris</label>
<input type="text" name="alamat_ahli_waris" value="<?= htmlspecialchars($alamat_ahli_waris) ?>">

<label>Telp Ahli Waris</label>
<input type="text" name="telp_ahli_waris" value="<?= htmlspecialchars($telp_ahli_waris) ?>">

<label>Email Ahli Wari</label>
<input type="email" name="email_ahli_waris" value="<?= htmlspecialchars($email_ahli_waris) ?>">

<label>Cabang</label>
<?php if ($session_location == 1): ?>
    <!-- HO → BEBAS PILIH -->
    <select name="cabang">
        <?php 
        $cabangList->data_seek(0);
        while ($cb = $cabangList->fetch_assoc()): ?>
        <option value="<?= $cb['idl'] ?>" <?= $cb['idl']==$cabang?'selected':'' ?>>
            <?= htmlspecialchars($cb['nama_cabang']) ?>
        </option>
        <?php endwhile; ?>
    </select>
<?php else: ?>
    <!-- CABANG → FIX -->
    <input type="text" value="<?= $cabang ?>" disabled>
    <input type="hidden" name="cabang" value="<?= $cabang ?>">
<?php endif; ?>

<label>Negara</label>
<select name="negara">
    <?php while ($ng = $negaraList->fetch_assoc()): ?>
        <option value="<?= $ng['negara'] ?>" <?= $negara==$ng['negara']?'selected':'' ?>>
            <?= $ng['negara'] ?>
        </option>
    <?php endwhile; ?>
</select>

<label>Sponsor</label>
<select name="sponsor">
    <?php 
    $sponsorList->data_seek(0);
    while ($sp = $sponsorList->fetch_assoc()): ?>
        <option value="<?= $sp['nik'] ?>" <?= $sponsor==$sp['nik']?'selected':'' ?>>
            <?= $sp['nik']." - ".$sp['nama'] ?>
        </option>
    <?php endwhile; ?>
</select>

<input type="submit" value="Salin Data">
</form>
</div>
<script>
// EMSIFA API WILAYAH HIERARCHY
const API_BASE = 'https://www.emsifa.com/api-wilayah-indonesia/api';

document.addEventListener('DOMContentLoaded', () => {
    // Fetch Provinsi
    fetch(`${API_BASE}/provinces.json`)
        .then(r => r.json())
        .then(provs => {
            const selProv = document.getElementById('select_prov');
            provs.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                selProv.appendChild(opt);
            });
        });

    document.getElementById('select_prov').addEventListener('change', function() {
        const provId = this.value;
        const provName = this.options[this.selectedIndex].text;
        document.getElementById('provinsi').value = provName;

        const selKab = document.getElementById('select_kab');
        const selKec = document.getElementById('select_kec');
        const selKel = document.getElementById('select_kel');
        
        selKab.innerHTML = '<option value="">-- Pilih Kabupaten --</option>';
        selKec.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        
        selKab.disabled = false;
        selKec.disabled = true;
        selKel.disabled = true;

        if (provId) {
            fetch(`${API_BASE}/regencies/${provId}.json`)
                .then(r => r.json())
                .then(kabs => {
                    kabs.forEach(k => {
                        const opt = document.createElement('option');
                        opt.value = k.id;
                        opt.textContent = k.name;
                        selKab.appendChild(opt);
                    });
                });
        }
    });

    document.getElementById('select_kab').addEventListener('change', function() {
        const kabId = this.value;
        const kabName = this.options[this.selectedIndex].text;
        document.getElementById('kabupaten').value = kabName;

        const selKec = document.getElementById('select_kec');
        const selKel = document.getElementById('select_kel');
        
        selKec.innerHTML = '<option value="">-- Pilih Kecamatan --</option>';
        selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        
        selKec.disabled = false;
        selKel.disabled = true;

        if (kabId) {
            fetch(`${API_BASE}/districts/${kabId}.json`)
                .then(r => r.json())
                .then(kecs => {
                    kecs.forEach(k => {
                        const opt = document.createElement('option');
                        opt.value = k.id;
                        opt.textContent = k.name;
                        selKec.appendChild(opt);
                    });
                });
        }
    });

    document.getElementById('select_kec').addEventListener('change', function() {
        const kecId = this.value;
        const kecName = this.options[this.selectedIndex].text;
        document.getElementById('kecamatan').value = kecName;

        const selKel = document.getElementById('select_kel');
        selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        selKel.disabled = false;

        if (kecId) {
            fetch(`${API_BASE}/villages/${kecId}.json`)
                .then(r => r.json())
                .then(kels => {
                    kels.forEach(k => {
                        const opt = document.createElement('option');
                        opt.value = k.id;
                        opt.textContent = k.name;
                        selKel.appendChild(opt);
                    });
                });
        }
    });

    document.getElementById('select_kel').addEventListener('change', function() {
        const kelName = this.options[this.selectedIndex].text;
        document.getElementById('kelurahan').value = kelName;
    });
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>
