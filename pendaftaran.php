<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

// ================ SESSION LOCATION ================
$session_location = $_SESSION['location'] ?? 0;

// ================ KONEKSI DATABASE ================
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

/* =======================
   AJAX CEK NIK 16 DIGIT
======================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'cek_nik') {

    header('Content-Type: application/json');

    $nik16 = substr(trim($_GET['nik'] ?? ''), 0, 16);

    $res = [
        'status'   => 'lanjut',
        'msg'      => '',
        'redirect' => ''
    ];

    if (strlen($nik16) === 16) {

        /* ==================================================
           PRIORITAS 1 — CEK MASALAH (PALING UTAMA)
        ================================================== */
        $cek = $conn->prepare("
            SELECT id 
            FROM masalah 
            WHERE nik = ? 
            LIMIT 1
        ");
        $cek->bind_param("s", $nik16);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $res['status']   = 'masalah';
            $res['redirect'] = "detailmasalah.php?id=".$nik16;
            echo json_encode($res);
            exit;
        }
        $cek->close();


        /* ==================================================
           PRIORITAS 2 — CEK PENDAFTARAN AKTIVE
        ================================================== */
        $cek = $conn->prepare("
            SELECT l.nama_cabang, s.nama
            FROM pendaftaran p
            LEFT JOIN location l ON p.cabang = l.idl
            LEFT JOIN sponsor s ON p.sponsor = s.nik
            WHERE LEFT(p.nik,16)=? 
              AND p.status='aktive'
            ORDER BY p.id DESC
            LIMIT 1
        ");
        $cek->bind_param("s", $nik16);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $cek->bind_result($exist_cabang, $exist_sponsor);
            $cek->fetch();

            $res['status'] = 'aktive';
            $res['msg'] = "NIK sudah terdaftar di \"$exist_cabang\" dengan sponsor \"$exist_sponsor\"";
            echo json_encode($res);
            exit;
        }
        $cek->close();


        /* ==================================================
           PRIORITAS 3 — CEK PENDAFTARAN NONAKTIVE
        ================================================== */
        $cek = $conn->prepare("
            SELECT nik 
            FROM pendaftaran 
            WHERE LEFT(nik,16)=? 
              AND status='nonaktive'
            ORDER BY id DESC 
            LIMIT 1
        ");
        $cek->bind_param("s", $nik16);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            $res['status']   = 'nonaktive';
            $res['redirect'] = "copypendaftaran.php?nik=".$nik16;
            echo json_encode($res);
            exit;
        }
        $cek->close();
    }

    // jika tidak kena kondisi apa pun
    echo json_encode($res);
    exit;
}


// ================ DROPDOWN DATA ================
$negaraList = $conn->query("SELECT negara FROM negara WHERE status = 'aktive'");
$sponsorList = $conn->query("SELECT nik, nama FROM sponsor ORDER BY nama ASC");
$cabangList = $conn->query("SELECT idl, nama_cabang FROM location ORDER BY nama_cabang ASC");

// ================ SIMPAN DATA ================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nik     = trim($_POST['nik'] ?? '');
    $cabang  = ($_SESSION['location'] == 1) ? intval($_POST['cabang']) : $session_location;
    $sponsor = trim($_POST['sponsor'] ?? '');
    $tanggal_pendaftaran = $_POST['tanggal_pendaftaran'] ?? '';

    if (empty($tanggal_pendaftaran)) {
        die("Tanggal pendaftaran wajib diisi.");
    }

    // CEK DUPLIKAT
    $cek = $conn->prepare("
        SELECT p.nik, l.nama_cabang, s.nama
        FROM pendaftaran p
        LEFT JOIN location l ON p.cabang = l.idl
        LEFT JOIN sponsor s ON p.sponsor = s.nik
        WHERE p.nik = ?
    ");
    $cek->bind_param("s", $nik);
    $cek->execute();
    $cek->store_result();
    if ($cek->num_rows > 0) {
        $cek->bind_result($exist_nik, $exist_cabang, $exist_sponsor);
        $cek->fetch();
        echo "<script>
            alert('NIK sudah terdaftar di \"$exist_cabang\" dengan sponsor \"$exist_sponsor\"');
            window.location.href='home.php';
        </script>";
        exit;
    }
    $cek->close();

    // DATA FORM
    $nama              = trim($_POST['nama'] ?? '');
    $namas             = trim($_POST['namas'] ?? '');
    $tempat_lahir      = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir     = $_POST['tanggal_lahir'] ?? '';
    $pendidikan        = trim($_POST['pendidikan'] ?? '');
    $alamat            = trim($_POST['alamat'] ?? '');
    $kelurahan         = trim($_POST['kelurahan'] ?? '');
    $kecamatan         = trim($_POST['kecamatan'] ?? '');
    $kabupaten         = trim($_POST['kabupaten'] ?? '');
    $telp              = trim($_POST['telp'] ?? '');
    $status            = 'aktive';
    $email             = trim($_POST['email'] ?? '');
    $nama_ibu          = trim($_POST['nama_ibu'] ?? '');
    $nama_ayah         = trim($_POST['nama_ayah'] ?? '');
    $alamat_orang_tua  = trim($_POST['alamat_orang_tua'] ?? '');
    $telp_ortu         = trim($_POST['telp_ortu'] ?? '');
    $nama_ahli_waris   = trim($_POST['nama_ahli_waris'] ?? '');
    $hubungan          = trim($_POST['hubungan'] ?? '');
    $alamat_ahli_waris = trim($_POST['alamat_ahli_waris'] ?? '');
    $telp_ahli_waris   = trim($_POST['telp_ahli_waris'] ?? '');
    $email_ahli_waris  = trim($_POST['email_ahli_waris'] ?? '');
    $negara            = trim($_POST['negara'] ?? '');
    $perkawinan        = trim($_POST['status_perkawinan'] ?? 'lajang');
    $edit              = 'clouse';

    // UPLOAD FILE
    $upload_dir = __DIR__ . '/pekerja/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    function upload_file($f, $prefix, $dir, $wajib = true) {
        if (!isset($_FILES[$f]) || $_FILES[$f]['error'] !== 0) {
            return $wajib ? die("File $f wajib diunggah.") : null;
        }
        $allowed = ['jpg','jpeg','png','gif','pdf'];
        $ext = strtolower(pathinfo($_FILES[$f]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) die("Format file $f tidak valid!");
        $name = uniqid($prefix . "_") . "." . $ext;
        if (!move_uploaded_file($_FILES[$f]['tmp_name'], $dir . "/" . $name)) {
            die("Gagal upload $f");
        }
        return $name;
    }

    $ft       = upload_file('ft', 'foto', $upload_dir);
    $ktp_file = upload_file('ktp', 'ktp',  $upload_dir);
    $kk_file  = upload_file('kk',  'kk',   $upload_dir, false);

    $ftp_file = null;
    if ($perkawinan !== 'lajang') {
        $ftp_file = upload_file('ftp', 'perkawinan', $upload_dir);
    }

    // SIMPAN KE DATABASE
    $stmt = $conn->prepare("INSERT INTO pendaftaran 
        (nik, nama, tempat_lahir, tanggal_lahir, pendidikan, alamat, kelurahan, kecamatan, kabupaten, telp, status, email, ft,
         nama_ibu, nama_ayah, alamat_orang_tua, telp_ortu, nama_ahli_waris, hubungan, alamat_ahli_waris, 
         telp_ahli_waris, email_ahli_waris, cabang, edit, negara, sponsor, tanggal_pendaftaran, ktp, kk, perkawinan, ftp, namas)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param(
        str_repeat("s", 32),
        $nik, $nama, $tempat_lahir, $tanggal_lahir, $pendidikan, $alamat, $kelurahan, $kecamatan, $kabupaten, $telp,
        $status, $email, $ft,
        $nama_ibu, $nama_ayah, $alamat_orang_tua, $telp_ortu,
        $nama_ahli_waris, $hubungan, $alamat_ahli_waris, $telp_ahli_waris, $email_ahli_waris,
        $cabang, $edit, $negara, $sponsor, $tanggal_pendaftaran,
        $ktp_file, $kk_file, $perkawinan, $ftp_file, $namas
    );

    if ($stmt->execute()) {
        echo "<script>alert('Data berhasil disimpan'); window.location.href='detail_pekerja.php?nik={$nik}';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data: {$stmt->error}');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Formulir Pendaftaran</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        body { font-family: Arial; background: #f4f4f4; padding-bottom: 60px; }
        .container { max-width: 800px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
        label { font-weight: bold; margin-top: 10px; display: block; }
        input, textarea, select { width: 100%; padding: 8px; margin-top: 4px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        input[type="submit"] { background: #28a745; color: white; border: none; margin-top: 15px; }
        input[type="submit"]:hover { background: #218838; cursor: pointer; }
        .home-icon1, .left-icon { position: absolute; top: 0; color: maroon; font-size: 24px; }
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
    <h2>Formulir Pendaftaran</h2>

    <form method="post" enctype="multipart/form-data">

        <label>Tanggal Pendaftaran</label>
        <input type="date" name="tanggal_pendaftaran" required value="<?= date('Y-m-d') ?>">

        <label>NIK</label>
        <input type="text" name="nik" id="nik" required>

        <label>Nama</label>
        <input type="text" name="nama" required>

        <label>Status Perkawinan</label>
        <select name="status_perkawinan" id="status_perkawinan" required>
            <option value="">-- Pilih Status --</option>
            <option value="lajang">Lajang</option>
            <option value="menikah">Menikah</option>
            <option value="duda cerai mati">Duda Cerai Mati</option>
            <option value="duda cerai hidup">Duda Cerai Hidup</option>
            <option value="janda cerai mati">Janda Cerai Mati</option>
            <option value="janda cerai hidup">Janda Cerai Hidup</option>
        </select>

        <label id="lbl_ftp">Surat Perkawinan/Cerai/Kematian</label>
        <input type="file" name="ftp" id="ftp" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <label id="lbl_namas">Nama Suami/Istri</label>
        <input type="text" name="namas" id="namas">

        <label>Foto</label>
        <input type="file" name="ft" accept=".jpg,.jpeg,.png,.gif" required>

        <label>KTP</label>
        <input type="file" name="ktp" accept=".jpg,.jpeg,.png,.gif,.pdf" required>

        <label>KK</label>
        <input type="file" name="kk" accept=".jpg,.jpeg,.png,.gif,.pdf">

        <label>Tempat Lahir</label>
        <input type="text" name="tempat_lahir" required>

        <label>Tanggal Lahir</label>
        <input type="date" name="tanggal_lahir" required>

        <label>Pendidikan</label>
        <input type="text" name="pendidikan" required>

        <label>Alamat</label>
        <textarea name="alamat"></textarea>

        <label>Provinsi</label>
        <select id="select_prov" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;">
            <option value="">-- Pilih Provinsi --</option>
        </select>
        <input type="hidden" id="provinsi" name="provinsi">

        <label>Kabupaten/Kota</label>
        <select id="select_kab" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;" disabled>
            <option value="">-- Pilih Kabupaten --</option>
        </select>
        <input type="hidden" id="kabupaten" name="kabupaten">

        <label>Kecamatan</label>
        <select id="select_kec" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;" disabled>
            <option value="">-- Pilih Kecamatan --</option>
        </select>
        <input type="hidden" id="kecamatan" name="kecamatan">

        <label>Kelurahan</label>
        <select id="select_kel" required style="width:100%; padding:10px; border-radius:5px; border:1px solid #ccc; margin-bottom:10px;" disabled>
            <option value="">-- Pilih Kelurahan --</option>
        </select>
        <input type="hidden" id="kelurahan" name="kelurahan">

        <label>Telp</label>
        <input type="text" name="telp">

        <label>Email</label>
        <input type="email" name="email">

        <label>Nama Ibu</label>
        <input type="text" name="nama_ibu">

        <label>Nama Ayah</label>
        <input type="text" name="nama_ayah">

        <label>Alamat Orang Tua</label>
        <input type="text" name="alamat_orang_tua" required>

        <label>Telp Orang Tua</label>
        <input type="text" name="telp_ortu">

        <label>Nama Ahli Waris</label>
        <input type="text" name="nama_ahli_waris">

        <label>Hubungan</label>
        <input type="text" name="hubungan">

        <label>Alamat Ahli Waris</label>
        <input type="text" name="alamat_ahli_waris" required>

        <label>Telp Ahli Waris</label>
        <input type="text" name="telp_ahli_waris">

        <label>Email Ahli Waris</label>
        <input type="email" name="email_ahli_waris">

        <!-- CABANG -->
        <label>Cabang</label>
        <?php if ($session_location == 1): ?>
            <select name="cabang" required>
                <option value="">-- Pilih Cabang --</option>
                <?php while($cb = $cabangList->fetch_assoc()): ?>
                    <option value="<?= $cb['idl'] ?>"><?= htmlspecialchars($cb['nama_cabang']) ?></option>
                <?php endwhile; ?>
            </select>
        <?php else: ?>
            <input type="hidden" name="cabang" value="<?= $session_location ?>">
            <input type="text" disabled value="Cabang Anda: <?= $session_location ?>">
        <?php endif; ?>

        <label>Negara</label>
        <select name="negara" required>
            <option value="">-- Pilih Negara --</option>
            <?php while($ng = $negaraList->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($ng['negara']) ?>">
                    <?= htmlspecialchars($ng['negara']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Sponsor</label>
        <select id="sponsor" name="sponsor" style="width: 100%;" required>
            <option value="">-- Pilih Sponsor --</option>
            <?php while($sp = $sponsorList->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($sp['nik']) ?>">
                    <?= htmlspecialchars($sp['nik'] . ' - ' . $sp['nama']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <input type="submit" value="Simpan">
    </form>
</div>

<script>
const nikInput = document.getElementById('nik');

nikInput.addEventListener('keyup', function () {
    let nik = this.value.substring(0,16);

    if (nik.length === 16) {
        console.log("CEK NIK:", nik);

        fetch(`pendaftaran.php?ajax=cek_nik&nik=${nik}`)
        .then(r => r.json())
        .then(d => {
            console.log("HASIL:", d);

            if (d.status === 'masalah') {
                window.location.href = d.redirect;
                return;
            }

            if (d.status === 'aktive') {
                alert(d.msg);
                window.location.href = 'home.php';
                return;
            }

            if (d.status === 'nonaktive') {
                window.location.href = d.redirect;
                return;
            }
        })
        .catch(err => console.error("ERROR AJAX:", err));
    }
});

$(document).ready(function(){
    $('#sponsor').select2({
        placeholder: "-- Pilih Sponsor --",
        allowClear: true
    });

    // TAMPILKAN FILE PERKAWINAN HANYA JIKA TIDAK LAJANG
    $('#status_perkawinan').on('change', function(){
        let val = $(this).val();
        if(val === 'lajang' || val === ''){
            $('#ftp, #lbl_ftp, #namas, #lbl_namas').hide(); 
        } else {
            $('#ftp, #lbl_ftp, #namas, #lbl_namas').show();
        }
    }).trigger('change');
});

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
        })
        .catch(err => console.error("Gagal load provinsi:", err));

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
        
        selKab.disabled = true;
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
                    selKab.disabled = false;
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
        
        selKec.disabled = true;
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
                    selKec.disabled = false;
                });
        }
    });

    document.getElementById('select_kec').addEventListener('change', function() {
        const kecId = this.value;
        const kecName = this.options[this.selectedIndex].text;
        document.getElementById('kecamatan').value = kecName;

        const selKel = document.getElementById('select_kel');
        selKel.innerHTML = '<option value="">-- Pilih Kelurahan --</option>';
        selKel.disabled = true;

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
                    selKel.disabled = false;
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
