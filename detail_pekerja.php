<?php
// detail_pekerja.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config.php';

// cek login sederhana (sesuaikan bila ada sistem otentikasi yang berbeda)
if (!isset($_SESSION['userid'])) {
    header('Location: index.php');
    exit;
}

// koneksi mysqli menggunakan env
$conn = new mysqli(
    getenv('DB_HOST') ?: die('DB_HOST belum diset'),
    getenv('DB_USER') ?: die('DB_USER belum diset'),
    getenv('DB_PASS'),
    getenv('DB_NAME') ?: die('DB_NAME belum diset')
);
if ($conn->connect_error) {
    die('Koneksi gagal: ' . $conn->connect_error);
}

$nik = trim($_GET['nik'] ?? '');
if (!$nik) {
    echo "<script>alert('NIK tidak ditemukan'); window.location.href='home.php';</script>";
    exit;
}

// helper untuk escape
function h($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// helper untuk tombol file
function fileButton($filename, $label = 'Lihat File') {
    if (!$filename) return '<em>Tidak ada file</em>';
    $url = '/pekerja/' . rawurlencode($filename);
    return '<a class="file-btn" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . h($label) . '</a>';
}

// ---------------------------
// 1) Data pendaftaran (utama)
// ---------------------------
$sql = "
SELECT p.nik, p.nama, p.perkawinan, p.tempat_lahir, p.tanggal_lahir, p.Pendidikan, p.alamat, p.kelurahan, p.kecamatan, p.kabupaten,
       p.telp, p.email, p.status, p.ft, p.ktp, p.kk, p.ftp, p.nama_ibu, p.nama_ayah, p.alamat_orang_tua, p.telp_ortu,
       p.nama_ahli_waris, p.hubungan, p.alamat_ahli_waris, p.telp_ahli_waris, p.email_ahli_waris,
       p.negara, p.tanggal_pendaftaran, p.tanggal_kesehatan, p.tanggal_saat_job, p.tanggal_pembuatan_pasport,
       p.tanggal_sikotes, p.tanggal_skck, p.cabang,
       l.nama_cabang,
       p.minta, p.alesan
FROM pendaftaran p
LEFT JOIN location l ON p.cabang = l.idl
WHERE p.nik = ?
LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result(
    $db_nik, $db_nama, $db_perkawinan, $db_tempat_lahir, $db_tanggal_lahir, $db_pendidikan, $db_alamat, $db_kelurahan, $db_kecamatan, $db_kabupaten,
    $db_telp, $db_email, $db_status, $db_ft, $db_ktp, $db_kk, $db_ftp, $db_nama_ibu, $db_nama_ayah, $db_alamat_orang_tua, $db_telp_ortu,
    $db_nama_ahli_waris, $db_hubungan, $db_alamat_ahli_waris, $db_telp_ahli_waris, $db_email_ahli_waris,
    $db_negara, $db_tanggal_pendaftaran, $db_tanggal_kesehatan, $db_tanggal_saat_job, $db_tanggal_pembuatan_pasport,
    $db_tanggal_sikotes, $db_tanggal_skck, $db_cabang,
    $db_nama_cabang,
    $db_minta, $db_alesan
);
$has = $stmt->fetch();
$stmt->close();

if (!$has) {
    echo "<script>alert('Data pendaftaran tidak ditemukan'); window.location.href='home.php';</script>";
    exit;
}

// ---------------------------
// 2) Kesehatan PRA (kesehatan) - terakhir
// ---------------------------
$kesehatan = null;
$stmt = $conn->prepare("SELECT provider_serkes, no_serkes, serkes, tgl_serkes, tglend_serkes, no_perjanjian, status_kes, edit, minta, alesan FROM kesehatan WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($k_provider, $k_no, $k_file, $k_tgl, $k_tglend, $k_no_perj, $k_status, $k_edit, $k_minta, $k_alesan);
if ($stmt->fetch()) {
    $kesehatan = compact('k_provider','k_no','k_file','k_tgl','k_tglend','k_no_perj','k_status','k_edit','k_minta','k_alesan');
}
$stmt->close();

// ---------------------------
// 3) Dapat Job - terakhir
// ---------------------------
$dapat_job = null;
$stmt = $conn->prepare("SELECT tanggal_dapat_job, idreg, no_job, jabatan, gaji2, negara, nama_mitra, no_perjanjian, tgl_terbit, durasi, tgl_akhir, gaji, identitas_penguna, nama_penguna, alamat_penguna, kota_penguna, telp_penguna, email_penguna, edit, minta, alesan FROM dapat_job WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($dj_tanggal, $dj_idreg, $dj_no_job, $dj_jabatan, $dj_gaji2, $dj_negara, $dj_nama_mitra, $dj_no_perj, $dj_tgl_terbit, $dj_durasi, $dj_tgl_akhir, $dj_gaji, $dj_identitas_penguna, $dj_nama_penguna, $dj_alamat_penguna, $dj_kota_penguna, $dj_telp_penguna, $dj_email_penguna, $dj_edit, $dj_minta, $dj_alesan);
if ($stmt->fetch()) {
    $dapat_job = compact(
        'dj_tanggal','dj_idreg','dj_no_job','dj_jabatan','dj_gaji2','dj_negara','dj_nama_mitra','dj_no_perj','dj_tgl_terbit','dj_durasi','dj_tgl_akhir','dj_gaji',
        'dj_identitas_penguna','dj_nama_penguna','dj_alamat_penguna','dj_kota_penguna','dj_telp_penguna','dj_email_penguna','dj_edit','dj_minta','dj_alesan'
    );
}
$stmt->close();

// ---------------------------
// 4) Ujikom - terakhir
// ---------------------------
$ujikom = null;
$stmt = $conn->prepare("SELECT provider_ujikom, no_ujikom, ujikom, tgl_ujikom, tglend_ujikom, no_perjanjian, edit, minta, alesan FROM ujikom WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($u_provider, $u_no, $u_file, $u_tgl, $u_tglend, $u_no_perj, $u_edit, $u_minta, $u_alesan);
if ($stmt->fetch()) {
    $ujikom = compact('u_provider','u_no','u_file','u_tgl','u_tglend','u_no_perj','u_edit','u_minta','u_alesan');
}
$stmt->close();

// ---------------------------
// 5) Keluar ID - terakhir
// ---------------------------
$keluar_id = null;
$stmt = $conn->prepare("SELECT Id_tki, upload_idtki, alesan, edit, minta FROM keluar_id WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($k_idtki, $k_upload_idtki, $k_alesan_k, $k_edit_k, $k_minta_k);
if ($stmt->fetch()) {
    $keluar_id = compact('k_idtki','k_upload_idtki','k_alesan_k','k_edit_k','k_minta_k');
}
$stmt->close();

// ---------------------------
// 6) Asuransi PRA - terakhir (asuransipra)
// ---------------------------
$asuransipra = null;
$stmt = $conn->prepare("SELECT provider_asuransipra, no_asuransipra, asuransipra, tgl_asuransipra, tglend_asuransipra, no_perjanjian, edit, minta, alesan FROM asuransipra WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($a_provider, $a_no, $a_file, $a_tgl, $a_tglend, $a_no_perj, $a_edit, $a_minta, $a_alesan);
if ($stmt->fetch()) {
    $asuransipra = compact('a_provider','a_no','a_file','a_tgl','a_tglend','a_no_perj','a_edit','a_minta','a_alesan');
}
$stmt->close();

// ---------------------------
// 7) Pembuatan Paspor - terakhir
// ---------------------------
$paspor = null;
$stmt = $conn->prepare("SELECT nama_paspor, no_paspor, pasport, tgl_paspor, tglend_paspor, penerbit_paspor, edit, minta, alesan FROM pembuatan_paspor WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($p_nama, $p_no, $p_file, $p_tgl, $p_tglend, $p_penerbit, $p_edit, $p_minta, $p_alesan);
if ($stmt->fetch()) {
    $paspor = compact('p_nama','p_no','p_file','p_tgl','p_tglend','p_penerbit','p_edit','p_minta','p_alesan');
}
$stmt->close();

// ---------------------------
// 8) Sikotes - terakhir
// ---------------------------
$sikotes = null;
$stmt = $conn->prepare("SELECT provider_siko, no_siko, siko, tgl_siko, tglend_siko, no_perjanjian, status_siko, edit, minta, alesan FROM sikotes WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($s_provider, $s_no, $s_file, $s_tgl, $s_tglend, $s_no_perj, $s_status, $s_edit, $s_minta, $s_alesan);
if ($stmt->fetch()) {
    $sikotes = compact('s_provider','s_no','s_file','s_tgl','s_tglend','s_no_perj','s_status','s_edit','s_minta','s_alesan');
}
$stmt->close();

// ---------------------------
// 9) SKCK - terakhir
// ---------------------------
$skck = null;
$stmt = $conn->prepare("SELECT provider_skck, no_skck, skck, tgl_skck, tglend_skck, no_perjanjian, edit, minta, alesan FROM skck WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($sk_provider, $sk_no, $sk_file, $sk_tgl, $sk_tglend, $sk_no_perj, $sk_edit, $sk_minta, $sk_alesan);
if ($stmt->fetch()) {
    $skck = compact('sk_provider','sk_no','sk_file','sk_tgl','sk_tglend','sk_no_perj','sk_edit','sk_minta','sk_alesan');
}
$stmt->close();

// ---------------------------
// 10) Kesehatan FULL (kesehatan2) - terakhir
// ---------------------------
$kesehatan2 = null;
$stmt = $conn->prepare("SELECT provider_serkes, no_serkes, serkes, tgl_serkes, tglend_serkes, no_perjanjian, status_kes, edit, minta, alesan FROM kesehatan2 WHERE nik = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param('s', $nik);
$stmt->execute();
$stmt->bind_result($kf_provider, $kf_no, $kf_file, $kf_tgl, $kf_tglend, $kf_no_perj, $kf_status, $kf_edit, $kf_minta, $kf_alesan);
if ($stmt->fetch()) {
    $kesehatan2 = compact('kf_provider','kf_no','kf_file','kf_tgl','kf_tglend','kf_no_perj','kf_status','kf_edit','kf_minta','kf_alesan');
}
$stmt->close();

$conn->close();

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Detail Pendaftaran - <?= h($db_nama) ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:20px; }
    .wrap { max-width:1100px; margin:0 auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
    h1,h2 { margin:0 0 12px; }
    .grid { display:flex; gap:20px; flex-wrap:wrap; }
    .col { flex:1 1 320px; min-width:280px; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    td { padding:8px; vertical-align:top; border-bottom:1px solid #eee; }
    .foto { max-width:220px; text-align:center; }
    .foto img { width:100%; border-radius:6px; box-shadow:0 2px 6px rgba(0,0,0,.12); }
    .file-btn { display:inline-block; padding:8px 12px; background:#2d87f0; color:#fff; text-decoration:none; border-radius:6px; margin:4px 0; }
    .file-btn:visited { color:#fff; }
    .section { margin-bottom:22px; }
    .label { font-weight:700; color:#333; }
    .small { font-size:13px; color:#666; }
    .note { font-style:italic; color:#666; }
    .back { display:inline-block; margin-bottom:10px; padding:8px 12px; background:#6c757d; color:#fff; text-decoration:none; border-radius:6px; }
</style>
</head>
<body>
<div class="wrap">
    <a href="home.php" class="back">🏠 Kembali</a>
    <h1>Detail TKI — <?= h($db_nama) ?> (<?= h($db_nik) ?>)</h1>

    <!-- 1) Pendaftaran -->
    <div class="section">
        <h2>Pendaftaran</h2>
        <div class="grid">
            <div class="col">
                <table>
                    <tr><td class="label">NIK</td><td><?= h($db_nik) ?></td></tr>
                    <tr><td class="label">Nama</td><td><?= h($db_nama) ?></td></tr>
                    <tr><td class="label">Tempat, Tgl Lahir</td><td><?= h($db_tempat_lahir) ?>, <?= h($db_tanggal_lahir) ?></td></tr>
                    <tr><td class="label">Pendidikan</td><td><?= h($db_pendidikan) ?></td></tr>
                    <tr><td class="label">Perkawinan</td><td><?= h($db_perkawinan) ?></td></tr>
                    <tr><td class="label">Alamat</td><td><?= h($db_alamat) ?></td></tr>
                    <tr><td class="label">Kel/Kec/Kab</td><td><?= h($db_kelurahan) ?> / <?= h($db_kecamatan) ?> / <?= h($db_kabupaten) ?></td></tr>
                    <tr><td class="label">Telp</td><td><?= h($db_telp) ?></td></tr>
                    <tr><td class="label">Email</td><td><?= h($db_email) ?></td></tr>
                    <tr><td class="label">Cabang</td><td><?= h($db_nama_cabang ?: $db_cabang) ?></td></tr>
                    <tr><td class="label">Negara</td><td><?= h($db_negara) ?></td></tr>
                    <tr><td class="label">Tanggal Daftar</td><td><?= h($db_tanggal_pendaftaran) ?></td></tr>
                </table>
            </div>

            <div class="col foto">
                <?php if (!empty($db_ft)): ?>
                    <img src="/pekerja/<?= rawurlencode($db_ft) ?>" alt="Foto <?= h($db_nama) ?>">
                <?php else: ?>
                    <div class="note">Tidak ada foto</div>
                <?php endif; ?>

                <div style="margin-top:12px;">
                    <div><?= fileButton($db_ktp, 'Lihat KTP') ?></div>
                    <div><?= fileButton($db_kk, 'Lihat KK') ?></div>
                    <div><?= fileButton($db_ftp, 'Lihat Surat Perkawinan/Cerai/Kematian') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2) Kesehatan PRA -->
    <div class="section">
        <h2>Kesehatan PRA</h2>
        <?php if ($kesehatan): ?>
            <table>
                <tr><td class="label">Provider</td><td><?= h($kesehatan['k_provider']) ?></td></tr>
                <tr><td class="label">No Serkes</td><td><?= h($kesehatan['k_no']) ?></td></tr>
                <tr><td class="label">Tanggal</td><td><?= h($kesehatan['k_tgl']) ?> — s/d <?= h($kesehatan['k_tglend']) ?></td></tr>
                <tr><td class="label">No Perjanjian</td><td><?= h($kesehatan['k_no_perj'] ?: '-') ?></td></tr>
                <tr><td class="label">Status</td><td><?= h(strtoupper($kesehatan['k_status'])) ?></td></tr>
                <tr><td class="label">File Serkes</td><td><?= fileButton($kesehatan['k_file'], 'Lihat Serkes PRA') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($kesehatan['k_edit']) ?> <?= $kesehatan['k_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($kesehatan['k_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Kesehatan PRA.</p>
        <?php endif; ?>
    </div>

    <!-- 3) Dapat Job -->
    <div class="section">
        <h2>Dapat Job</h2>
        <?php if ($dapat_job): ?>
            <table>
                <tr><td class="label">Tanggal Dapat Job</td><td><?= h($dapat_job['dj_tanggal']) ?></td></tr>
                <tr><td class="label">ID Reg</td><td><?= h($dapat_job['dj_idreg']) ?></td></tr>
                <tr><td class="label">No Job</td><td><?= h($dapat_job['dj_no_job']) ?></td></tr>
                <tr><td class="label">Jabatan</td><td><?= h($dapat_job['dj_jabatan']) ?></td></tr>
                <tr><td class="label">Gaji</td><td><?= h($dapat_job['dj_gaji2']) ?> (<?= h($dapat_job['dj_gaji']) ?>)</td></tr>
                <tr><td class="label">Negara</td><td><?= h($dapat_job['dj_negara']) ?></td></tr>
                <tr><td class="label">Nama Mitra</td><td><?= h($dapat_job['dj_nama_mitra'] ?: '-') ?></td></tr>
                <tr><td class="label">No Perjanjian</td><td><?= h($dapat_job['dj_no_perj'] ?: '-') ?></td></tr>
                <tr><td class="label">Tgl Terbit</td><td><?= h($dapat_job['dj_tgl_terbit']) ?></td></tr>
                <tr><td class="label">Durasi</td><td><?= h($dapat_job['dj_durasi']) ?> — Tgl Akhir: <?= h($dapat_job['dj_tgl_akhir']) ?></td></tr>
                <tr><td class="label">Pengguna / Kontak</td><td><?= h($dapat_job['dj_nama_penguna'] ?: '-') ?> / <?= h($dapat_job['dj_telp_penguna'] ?: '-') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($dapat_job['dj_edit']) ?> <?= $dapat_job['dj_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($dapat_job['dj_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Dapat Job.</p>
        <?php endif; ?>
    </div>

    <!-- 4) Ujikom -->
    <div class="section">
        <h2>Ujikom</h2>
        <?php if ($ujikom): ?>
            <table>
                <tr><td class="label">Provider</td><td><?= h($ujikom['u_provider']) ?></td></tr>
                <tr><td class="label">No Ujikom</td><td><?= h($ujikom['u_no']) ?></td></tr>
                <tr><td class="label">Tanggal</td><td><?= h($ujikom['u_tgl']) ?> — s/d <?= h($ujikom['u_tglend']) ?></td></tr>
                <tr><td class="label">File Ujikom</td><td><?= fileButton($ujikom['u_file'], 'Lihat Ujikom') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($ujikom['u_edit']) ?> <?= $ujikom['u_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($ujikom['u_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Ujikom.</p>
        <?php endif; ?>
    </div>

    <!-- 5) Keluar ID -->
    <div class="section">
        <h2>Keluar ID</h2>
        <?php if ($keluar_id): ?>
            <table>
                <tr><td class="label">ID TKI</td><td><?= h($keluar_id['k_idtki']) ?></td></tr>
                <tr><td class="label">File ID TKI</td><td><?= fileButton($keluar_id['k_upload_idtki'], 'Lihat File ID TKI') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($keluar_id['k_edit_k']) ?> <?= $keluar_id['k_minta_k'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($keluar_id['k_alesan_k'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Keluar ID.</p>
        <?php endif; ?>
    </div>

    <!-- 6) Asuransi PRA -->
    <div class="section">
        <h2>Asuransi PRA</h2>
        <?php if ($asuransipra): ?>
            <table>
                <tr><td class="label">Provider</td><td><?= h($asuransipra['a_provider']) ?></td></tr>
                <tr><td class="label">No Asuransi</td><td><?= h($asuransipra['a_no']) ?></td></tr>
                <tr><td class="label">Tanggal</td><td><?= h($asuransipra['a_tgl']) ?> — s/d <?= h($asuransipra['a_tglend']) ?></td></tr>
                <tr><td class="label">File Asuransi</td><td><?= fileButton($asuransipra['a_file'], 'Lihat File Asuransi') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($asuransipra['a_edit']) ?> <?= $asuransipra['a_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($asuransipra['a_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Asuransi PRA.</p>
        <?php endif; ?>
    </div>

    <!-- 7) Pembuatan Paspor -->
    <div class="section">
        <h2>Pembuatan Paspor</h2>
        <?php if ($paspor): ?>
            <table>
                <tr><td class="label">Nama di Paspor</td><td><?= h($paspor['p_nama']) ?></td></tr>
                <tr><td class="label">No Paspor</td><td><?= h($paspor['p_no']) ?></td></tr>
                <tr><td class="label">Tanggal</td><td><?= h($paspor['p_tgl']) ?> — s/d <?= h($paspor['p_tglend']) ?></td></tr>
                <tr><td class="label">Penerbit</td><td><?= h($paspor['p_penerbit']) ?></td></tr>
                <tr><td class="label">File Paspor</td><td><?= fileButton($paspor['p_file'], 'Lihat File Paspor') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($paspor['p_edit']) ?> <?= $paspor['p_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($paspor['p_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Pembuatan Paspor.</p>
        <?php endif; ?>
    </div>

    <!-- 8) Sikotes -->
    <div class="section">
        <h2>Sikotes</h2>
        <?php if ($sikotes): ?>
            <table>
                
                <tr><td class="label">Tanggal</td><td><?= h($sikotes['s_tgl']) ?> — s/d <?= h($sikotes['s_tglend']) ?></td></tr>
                <tr><td class="label">File Sikotes</td><td><?= fileButton($sikotes['s_file'], 'Lihat File Sikotes') ?></td></tr>
                <tr><td class="label">Status</td><td><?= h(strtoupper($sikotes['s_status'])) ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($sikotes['s_edit']) ?> <?= $sikotes['s_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($sikotes['s_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Sikotes.</p>
        <?php endif; ?>
    </div>

    <!-- 9) SKCK -->
    <div class="section">
        <h2>SKCK</h2>
        <?php if ($skck): ?>
            <table>
                <tr><td class="label">Tanggal</td><td><?= h($skck['sk_tgl']) ?> — s/d <?= h($skck['sk_tglend']) ?></td></tr>
                <tr><td class="label">File SKCK</td><td><?= fileButton($skck['sk_file'], 'Lihat File SKCK') ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($skck['sk_edit']) ?> <?= $skck['sk_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($skck['sk_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data SKCK.</p>
        <?php endif; ?>
    </div>

    <!-- 10) Kesehatan FULL (kesehatan2) -->
    <div class="section">
        <h2>Kesehatan FULL</h2>
        <?php if ($kesehatan2): ?>
            <table>
                <tr><td class="label">Provider</td><td><?= h($kesehatan2['kf_provider']) ?></td></tr>
                <tr><td class="label">No Serkes</td><td><?= h($kesehatan2['kf_no']) ?></td></tr>
                <tr><td class="label">Tanggal</td><td><?= h($kesehatan2['kf_tgl']) ?> — s/d <?= h($kesehatan2['kf_tglend']) ?></td></tr>
                <tr><td class="label">File Serkes Full</td><td><?= fileButton($kesehatan2['kf_file'], 'Lihat Serkes FULL') ?></td></tr>
                <tr><td class="label">Status</td><td><?= h(strtoupper($kesehatan2['kf_status'])) ?></td></tr>
                <tr><td class="label">Edit</td><td><?= h($kesehatan2['kf_edit']) ?> <?= $kesehatan2['kf_minta'] ? ' — Menunggu Izin' : '' ?></td></tr>
                <tr><td class="label">Alasan</td><td><?= h($kesehatan2['kf_alesan'] ?: '-') ?></td></tr>
            </table>
        <?php else: ?>
            <p class="note">Belum ada data Kesehatan FULL.</p>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
