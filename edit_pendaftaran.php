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

// ====== AMBIL NIK ======
$nik = $_GET['nik'] ?? '';
if (!$nik) {
    die("NIK tidak ditemukan.");
}

// ====== PASTIKAN KOLOM KODEPOS & NAMAS ======
$chkCol = $conn->query("SHOW COLUMNS FROM pendaftaran LIKE 'kodepos'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftaran ADD COLUMN kodepos VARCHAR(10) DEFAULT NULL AFTER kelurahan");
}
$chkNamas = $conn->query("SHOW COLUMNS FROM pendaftaran LIKE 'namas'");
if ($chkNamas && $chkNamas->num_rows === 0) {
    $conn->query("ALTER TABLE pendaftaran ADD COLUMN namas VARCHAR(100) DEFAULT NULL AFTER perkawinan");
}

// ====== AMBIL DATA LAMA ======
$stmt = $conn->prepare("SELECT 
    nik, nama, tempat_lahir, tanggal_lahir, pendidikan,
    alamat, kodepos, kelurahan, kecamatan, kabupaten, telp, email,
    ft, ktp, kk, ftp, perkawinan, namas,
    nama_ibu, nama_ayah, alamat_orang_tua, telp_ortu,
    nama_ahli_waris, hubungan, alamat_ahli_waris, telp_ahli_waris, email_ahli_waris,
    cabang, sponsor, negara, tanggal_pendaftaran
    FROM pendaftaran WHERE nik=?
");
$stmt->bind_param("s", $nik);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("Data tidak ditemukan.");
}

$stmt->bind_result(
    $nik, $nama, $tempat_lahir, $tanggal_lahir, $pendidikan,
    $alamat, $kodepos, $kelurahan, $kecamatan, $kabupaten, $telp, $email,
    $ft, $ktp, $kk, $ftp, $perkawinan, $namas,
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

    $nama              = strtoupper(trim($_POST['nama'] ?? ''));
    $namas             = strtoupper(trim($_POST['namas'] ?? ''));
    $tempat_lahir      = strtoupper(trim($_POST['tempat_lahir'] ?? ''));
    $tanggal_lahir     = $_POST['tanggal_lahir'] ?? '';
    $pendidikan        = strtoupper(trim($_POST['pendidikan'] ?? ''));
    $alamat            = strtoupper(trim($_POST['alamat'] ?? ''));
    $kodepos           = trim($_POST['kodepos'] ?? '');
    $kelurahan         = strtoupper(trim($_POST['kelurahan'] ?? ''));
    $kecamatan         = strtoupper(trim($_POST['kecamatan'] ?? ''));
    $kabupaten         = strtoupper(trim($_POST['kabupaten'] ?? ''));
    $telp              = trim($_POST['telp'] ?? '');
    $email             = strtolower(trim($_POST['email'] ?? ''));
    $perkawinan        = strtolower(trim($_POST['perkawinan'] ?? 'lajang'));
    $nama_ibu          = strtoupper(trim($_POST['nama_ibu'] ?? ''));
    $nama_ayah         = strtoupper(trim($_POST['nama_ayah'] ?? ''));
    $alamat_orang_tua  = strtoupper(trim($_POST['alamat_orang_tua'] ?? ''));
    $telp_ortu         = trim($_POST['telp_ortu'] ?? '');
    $nama_ahli_waris   = strtoupper(trim($_POST['nama_ahli_waris'] ?? ''));
    $hubungan          = strtoupper(trim($_POST['hubungan'] ?? ''));
    $alamat_ahli_waris = strtoupper(trim($_POST['alamat_ahli_waris'] ?? ''));
    $telp_ahli_waris   = trim($_POST['telp_ahli_waris'] ?? '');
    $email_ahli_waris  = strtolower(trim($_POST['email_ahli_waris'] ?? ''));
    $negara            = strtoupper(trim($_POST['negara'] ?? ''));
    $sponsor           = trim($_POST['sponsor'] ?? '');
    $tanggal_pendaftaran = $_POST['tanggal_pendaftaran'] ?? '';

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
    $stmt = $conn->prepare("UPDATE pendaftaran SET
        nama=?, tempat_lahir=?, tanggal_lahir=?, pendidikan=?,
        alamat=?, kodepos=?, kelurahan=?, kecamatan=?, kabupaten=?, telp=?,
        email=?, perkawinan=?, namas=?, ft=?, ktp=?, kk=?, ftp=?,
        nama_ibu=?, nama_ayah=?, alamat_orang_tua=?, telp_ortu=?,
        nama_ahli_waris=?, hubungan=?, alamat_ahli_waris=?, telp_ahli_waris=?, email_ahli_waris=?,
        cabang=?, sponsor=?, negara=?, tanggal_pendaftaran=?, edit='clouse'
        WHERE nik=?
    ");

    $stmt->bind_param(
        "ssssssssssssssssssssssssssissss",
        $nama, $tempat_lahir, $tanggal_lahir, $pendidikan,
        $alamat, $kodepos, $kelurahan, $kecamatan, $kabupaten, $telp,
        $email, $perkawinan, $namas, $ft, $ktp, $kk, $ftp,
        $nama_ibu, $nama_ayah, $alamat_orang_tua, $telp_ortu,
        $nama_ahli_waris, $hubungan, $alamat_ahli_waris, $telp_ahli_waris, $email_ahli_waris,
        $cabang, $sponsor, $negara, $tanggal_pendaftaran, $nik
    );

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil diperbarui');location.href='detail_pekerja.php?nik=" . urlencode($nik) . "';</script>";
    } else {
        echo "<script>alert('Gagal: {$stmt->error}');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Pendaftaran</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { font-family: Arial; background:#f5f5f5; padding-bottom: 60px; }
        .container { max-width:900px; margin:20px auto; padding:20px; background:white; border-radius:8px; box-shadow:0 0 10px rgba(0,0,0,.1); }
        label { font-weight:bold; margin-top:10px; display:block; }
        input, textarea, select { width:100%; padding:7px; margin-top:3px; box-sizing:border-box; border:1px solid #ccc; border-radius:4px; }
        input[type="text"], textarea { text-transform: uppercase; }
        .kodepos-wrapper { display: flex; gap: 10px; align-items: center; margin-top: 4px; }
        .kodepos-status { font-size: 13px; font-weight: bold; }
        input[type="submit"] { background:#007bff; color:white; border:0; margin-top:15px; cursor:pointer; padding:10px; font-weight:bold; }
        input[type="submit"]:hover { background:#0056b3; }
        .preview img { max-height:100px; margin-top:3px; }
    </style>
</head>
<body>
<div class="container">
<h2>Edit Pendaftaran</h2>

<form method="post" enctype="multipart/form-data">

<label>NIK (tidak dapat diubah)</label>
<input type="text" value="<?= htmlspecialchars($nik) ?>" disabled>

<label>Tanggal Pendaftaran</label>
<input type="date" name="tanggal_pendaftaran" value="<?= $tanggal_pendaftaran ?>">

<label>Nama</label>
<input type="text" name="nama" value="<?= htmlspecialchars($nama) ?>" required>

<label>Tempat Lahir</label>
<input type="text" name="tempat_lahir" value="<?= htmlspecialchars($tempat_lahir) ?>" required>

<label>Tanggal Lahir</label>
<input type="date" name="tanggal_lahir" value="<?= $tanggal_lahir ?>" required>

<label>Pendidikan</label>
<input type="text" name="pendidikan" value="<?= htmlspecialchars($pendidikan) ?>" required>

<label>Alamat</label>
<textarea name="alamat" required><?= htmlspecialchars($alamat) ?></textarea>

<label>Kode Pos <span style="font-size:12px; color:#0d6efd; font-weight:normal;"><i class="fa-solid fa-bolt"></i> Masukkan 5 digit kode pos untuk auto-fill Provinsi, Kab/Kota, Kecamatan & Kelurahan</span></label>
<div class="kodepos-wrapper">
    <input type="text" name="kodepos" id="kodepos" maxlength="5" value="<?= htmlspecialchars($kodepos ?? '') ?>" placeholder="Contoh: 50123" style="max-width:200px;">
    <span id="kodepos_status" class="kodepos-status"></span>
</div>

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
<select name="perkawinan" id="status_perkawinan">
    <option value="lajang" <?= $perkawinan=='lajang'?'selected':'' ?>>Lajang</option>
    <option value="menikah" <?= $perkawinan=='menikah'?'selected':'' ?>>Menikah</option>
    <option value="duda cerai mati" <?= $perkawinan=='duda cerai mati'?'selected':'' ?>>Duda Cerai Mati</option>
    <option value="duda cerai hidup" <?= $perkawinan=='duda cerai hidup'?'selected':'' ?>>Duda Cerai Hidup</option>
    <option value="janda cerai mati" <?= $perkawinan=='janda cerai mati'?'selected':'' ?>>Janda Cerai Mati</option>
    <option value="janda cerai hidup" <?= $perkawinan=='janda cerai hidup'?'selected':'' ?>>Janda Cerai Hidup</option>
</select>

<div id="wrapper_pasangan" style="<?= ($perkawinan=='lajang'||!$perkawinan)?'display:none;':'' ?>">
    <label id="lbl_namas">Nama Suami/Istri</label>
    <input type="text" name="namas" id="namas" value="<?= htmlspecialchars($namas ?? '') ?>">

    <label id="lbl_ftp">Surat Perkawinan / Cerai / Kematian</label>
    <?php if ($ftp): ?><a href="/pekerja/<?= $ftp ?>" target="_blank"><i class="fa-solid fa-file-pdf"></i> Lihat Dokumen Lama</a><br><?php endif; ?>
    <input type="file" name="ftp" id="ftp">
</div>

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

<input type="submit" value="Perbarui">
</form>
</div>
<script>
// EMSIFA API WILAYAH HIERARCHY
const API_BASE = 'https://www.emsifa.com/api-wilayah-indonesia/api';

$(document).ready(function(){
    // DYNAMIC SHOW/HIDE NAMA PASANGAN & SURAT PERKAWINAN
    $('#status_perkawinan').on('change', function(){
        let val = $(this).val();
        if(val === 'lajang' || val === ''){
            $('#wrapper_pasangan').hide(); 
        } else {
            $('#wrapper_pasangan').show();
        }
    });

    // AUTO-FILL WILAYAH VIA API KODE POS
    $('#kodepos').on('input', function() {
        const kp = $(this).val().trim();
        const statusEl = $('#kodepos_status');
        
        if (kp.length === 5) {
            statusEl.html('<span style="color:#0d6efd;"><i class="fa-solid fa-spinner fa-spin"></i> Mencari wilayah...</span>');
            
            fetch(`https://kodepos.vercel.app/search/?q=${encodeURIComponent(kp)}`)
                .then(r => r.json())
                .then(res => {
                    if (res && res.data && res.data.length > 0) {
                        const items = res.data;
                        const first = items[0];

                        const provName = (first.province || '').toUpperCase();
                        const kabName  = (first.regency || first.city || '').toUpperCase();
                        const kecName  = (first.district || '').toUpperCase();

                        $('#provinsi').val(provName);
                        $('#kabupaten').val(kabName);
                        $('#kecamatan').val(kecName);

                        $('#select_prov').html(`<option value="${provName}" selected>${provName}</option>`);
                        $('#select_kab').prop('disabled', false).html(`<option value="${kabName}" selected>${kabName}</option>`);
                        $('#select_kec').prop('disabled', false).html(`<option value="${kecName}" selected>${kecName}</option>`);

                        let kelOptions = '';
                        items.forEach((item, idx) => {
                            const vName = (item.village || item.subdistrict || '').toUpperCase();
                            kelOptions += `<option value="${vName}" ${idx === 0 ? 'selected' : ''}>${vName}</option>`;
                        });
                        $('#select_kel').prop('disabled', false).html(kelOptions);
                        $('#kelurahan').val($('#select_kel').val());

                        statusEl.html('<span style="color:#198754;"><i class="fa-solid fa-circle-check"></i> ' + kecName + ', ' + kabName + '</span>');
                    } else {
                        statusEl.html('<span style="color:#dc3545;"><i class="fa-solid fa-circle-exclamation"></i> Tidak ditemukan. Silakan pilih manual di bawah.</span>');
                    }
                })
                .catch(err => {
                    console.error("Gagal cari kodepos:", err);
                    statusEl.html('<span style="color:#dc3545;"><i class="fa-solid fa-triangle-exclamation"></i> Gagal koneksi API. Silakan pilih manual.</span>');
                });
        } else if (kp.length > 0 && kp.length < 5) {
            statusEl.html('<span style="color:#6c757d;">(Ketik 5 digit)</span>');
        } else {
            statusEl.html('');
        }
    });

    // AUTO UPPERCASE SAAT MENGETIK
    $('input[type="text"], textarea').on('input', function() {
        if (this.id !== 'kodepos' && this.id !== 'nik') {
            this.value = this.value.toUpperCase();
        }
    });
});

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
