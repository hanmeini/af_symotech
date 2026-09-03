<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
$database   = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}

$session_location = isset($_SESSION['location']) ? intval($_SESSION['location']) : 0;

// Filter parameter
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$tgl_mulai = isset($_GET['tgl_mulai']) ? trim($_GET['tgl_mulai']) : '';
$tgl_selesai = isset($_GET['tgl_selesai']) ? trim($_GET['tgl_selesai']) : '';
$filter_klaim = isset($_GET['klaim']) ? trim($_GET['klaim']) : '';
$cabang_get = isset($_GET['cabang']) ? intval($_GET['cabang']) : 0;

$where = "1=1";
if ($session_location > 1) {
    $where .= " AND p.cabang = " . $session_location;
} elseif ($cabang_get > 0) {
    $where .= " AND p.cabang = " . $cabang_get;
}

if ($keyword !== '') {
    $esc = $conn->real_escape_string($keyword);
    $where .= " AND (nb.nik LIKE '%$esc%' OR p.nama LIKE '%$esc%' OR jb.namaby LIKE '%$esc%')";
}

if ($tgl_mulai !== '') {
    $esc_start = $conn->real_escape_string($tgl_mulai);
    $where .= " AND nb.tanggal >= '$esc_start'";
}
if ($tgl_selesai !== '') {
    $esc_end = $conn->real_escape_string($tgl_selesai);
    $where .= " AND nb.tanggal <= '$esc_end'";
}

if ($filter_klaim === '1') {
    $where .= " AND nb.klaim = '1'";
} elseif ($filter_klaim === '0') {
    $where .= " AND nb.klaim = '0'";
}

// KPI Summary
$summary_sql = "
SELECT 
    COUNT(DISTINCT nb.nik) AS total_tki,
    COUNT(nb.id) AS total_transaksi,
    COALESCE(SUM(nb.nom), 0) AS grand_total,
    COALESCE(SUM(CASE WHEN nb.klaim = '1' THEN nb.nom ELSE 0 END), 0) AS total_klaim,
    COALESCE(SUM(CASE WHEN nb.klaim = '0' THEN nb.nom ELSE 0 END), 0) AS total_unclaimed
FROM nikby nb
LEFT JOIN pendaftaran p ON nb.nik = p.nik
LEFT JOIN jenisbiaya jb ON nb.kodeby = jb.id
WHERE $where
";
$sum_res = $conn->query($summary_sql);
$kpi = $sum_res ? $sum_res->fetch_assoc() : ['total_tki' => 0, 'total_transaksi' => 0, 'grand_total' => 0, 'total_klaim' => 0, 'total_unclaimed' => 0];

// Rekap Per Pekerja
$rekap_sql = "
SELECT 
    nb.nik,
    p.nama,
    p.negara,
    l.nama_cabang,
    COUNT(nb.id) AS jml_item,
    SUM(nb.nom) AS total_biaya,
    SUM(CASE WHEN nb.klaim = '1' THEN nb.nom ELSE 0 END) AS sudah_klaim,
    SUM(CASE WHEN nb.klaim = '0' THEN nb.nom ELSE 0 END) AS belum_klaim
FROM nikby nb
LEFT JOIN pendaftaran p ON nb.nik = p.nik
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN jenisbiaya jb ON nb.kodeby = jb.id
WHERE $where
GROUP BY nb.nik, p.nama, p.negara, l.nama_cabang
ORDER BY total_biaya DESC
";
$rekap_res = $conn->query($rekap_sql);

// Query Detail Transaksi
$detail_sql = "
SELECT 
    nb.id,
    nb.nik,
    p.nama,
    jb.namaby,
    nb.nom,
    nb.tanggal,
    nb.klaim,
    nb.kode_transaksi,
    l.nama_cabang
FROM nikby nb
LEFT JOIN pendaftaran p ON nb.nik = p.nik
LEFT JOIN location l ON p.cabang = l.idl
LEFT JOIN jenisbiaya jb ON nb.kodeby = jb.id
WHERE $where
ORDER BY nb.tanggal DESC, nb.id DESC
";
$detail_res = $conn->query($detail_sql);

// Daftar Cabang untuk filter
$cabang_options = $conn->query("SELECT idl, nama_cabang FROM location ORDER BY idl ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Rekap Biaya TKI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
        .container { max-width: 1300px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1, h2, h3 { color: #1e293b; margin-top: 0; }
        .top-nav { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn-back { background: #475569; color: #fff; padding: 9px 16px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; }
        .btn-back:hover { background: #334155; }
        .btn-print { background: #0284c7; color: #fff; padding: 9px 16px; border-radius: 5px; text-decoration: none; font-weight: bold; font-size: 14px; cursor: pointer; border: none; }
        .btn-print:hover { background: #0369a1; }
        
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; position: relative; border-left: 5px solid #0284c7; }
        .kpi-card.green { border-left-color: #10b981; }
        .kpi-card.amber { border-left-color: #f59e0b; }
        .kpi-card.purple { border-left-color: #8b5cf6; }
        .kpi-title { font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; }
        .kpi-value { font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 5px; }

        .filter-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-bottom: 25px; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; min-width: 150px; }
        .filter-group label { font-size: 12px; font-weight: bold; color: #475569; margin-bottom: 4px; }
        .filter-group input, .filter-group select { padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 13px; }
        .btn-submit { background: #0284c7; color: #fff; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px; height: 35px; }
        .btn-submit:hover { background: #0369a1; }
        .btn-reset { background: #94a3b8; color: #fff; padding: 8px 14px; border-radius: 4px; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; height: 19px; }

        .nav-tabs { display: flex; gap: 10px; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
        .tab-btn { padding: 10px 18px; border: none; background: none; font-weight: bold; font-size: 14px; color: #64748b; cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .tab-btn.active { color: #0284c7; border-bottom-color: #0284c7; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 13px; }
        th, td { padding: 10px 12px; border: 1px solid #e2e8f0; text-align: left; }
        th { background: #1e293b; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f8fafc; }
        tr:hover { background: #f1f5f9; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        @media print {
            .top-nav, .filter-panel, .nav-tabs, .btn-print { display: none !important; }
            body { padding: 0; background: #fff; }
            .container { box-shadow: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="top-nav">
        <a href="home.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Menu Utama</a>
        <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak Laporan</button>
    </div>

    <h1><i class="fa-solid fa-file-invoice-dollar"></i> Laporan Rekapitulasi Biaya TKI</h1>
    <p style="color: #64748b; margin-top: -5px; margin-bottom: 20px;">Laporan rincian pengeluaran uang muka biaya proses dan status klaim jurnal kas/bank per calon pekerja migran.</p>

    <!-- KPI Summary Cards -->
    <div class="kpi-grid">
        <div class="kpi-card purple">
            <div class="kpi-title">Total Pekerja & Transaksi</div>
            <div class="kpi-value"><?= number_format($kpi['total_tki']) ?> Org <span style="font-size: 14px; font-weight: normal; color: #64748b;">(<?= number_format($kpi['total_transaksi']) ?> trx)</span></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Total Biaya Tercatat</div>
            <div class="kpi-value">Rp <?= number_format($kpi['grand_total'], 0, ',', '.') ?></div>
        </div>
        <div class="kpi-card green">
            <div class="kpi-title">Sudah Diklaim (Terjurnal)</div>
            <div class="kpi-value">Rp <?= number_format($kpi['total_klaim'], 0, ',', '.') ?></div>
        </div>
        <div class="kpi-card amber">
            <div class="kpi-title">Belum Diklaim (Outstanding)</div>
            <div class="kpi-value">Rp <?= number_format($kpi['total_unclaimed'], 0, ',', '.') ?></div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="filter-panel">
        <form method="get" action="">
            <div class="filter-row">
                <div class="filter-group" style="flex: 2;">
                    <label>Pencarian NIK / Nama / Biaya</label>
                    <input type="text" name="q" placeholder="Cari NIK atau Nama Pekerja..." value="<?= htmlspecialchars($keyword) ?>">
                </div>
                <?php if ($session_location <= 1): ?>
                <div class="filter-group">
                    <label>Cabang</label>
                    <select name="cabang">
                        <option value="0">Semua Cabang</option>
                        <?php while ($c = $cabang_options->fetch_assoc()): ?>
                            <option value="<?= $c['idl'] ?>" <?= $cabang_get == $c['idl'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nama_cabang']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label>Status Klaim</label>
                    <select name="klaim">
                        <option value="">Semua Status</option>
                        <option value="1" <?= $filter_klaim === '1' ? 'selected' : '' ?>>Sudah Diklaim</option>
                        <option value="0" <?= $filter_klaim === '0' ? 'selected' : '' ?>>Belum Diklaim</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Tgl Mulai</label>
                    <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>">
                </div>
                <div class="filter-group">
                    <label>Tgl Selesai</label>
                    <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>">
                </div>
                <div>
                    <button type="submit" class="btn-submit"><i class="fa-solid fa-filter"></i> Filter</button>
                    <a href="biayapertki.php" class="btn-reset">Reset</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabs Navigation -->
    <div class="nav-tabs">
        <button class="tab-btn active" onclick="switchTab('rekap')"><i class="fa-solid fa-users"></i> Rekap Per Pekerja</button>
        <button class="tab-btn" onclick="switchTab('detail')"><i class="fa-solid fa-list-check"></i> Rincian Seluruh Transaksi</button>
    </div>

    <!-- TAB 1: Rekap Per Pekerja -->
    <div id="tab-rekap">
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">No</th>
                    <th>NIK</th>
                    <th>Nama Pekerja</th>
                    <th>Cabang</th>
                    <th>Negara</th>
                    <th class="text-center">Jml Transaksi</th>
                    <th class="text-right">Total Biaya</th>
                    <th class="text-right">Sudah Klaim</th>
                    <th class="text-right">Belum Klaim</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rekap_res && $rekap_res->num_rows > 0): ?>
                    <?php $no = 1; while ($r = $rekap_res->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><b><?= htmlspecialchars($r['nik']) ?></b></td>
                            <td><?= htmlspecialchars($r['nama'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['nama_cabang'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['negara'] ?? '-') ?></td>
                            <td class="text-center"><?= number_format($r['jml_item']) ?> trx</td>
                            <td class="text-right"><b>Rp <?= number_format($r['total_biaya'], 0, ',', '.') ?></b></td>
                            <td class="text-right" style="color: #166534;">Rp <?= number_format($r['sudah_klaim'], 0, ',', '.') ?></td>
                            <td class="text-right" style="color: #b45309; font-weight: bold;">Rp <?= number_format($r['belum_klaim'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <a href="detail_pekerja.php?nik=<?= urlencode($r['nik']) ?>" style="color:#0284c7; text-decoration:none; font-weight:bold;"><i class="fa-solid fa-user"></i> Profil</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" class="text-center" style="color: #64748b; padding: 20px;">Tidak ada data rekap biaya yang sesuai filter.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- TAB 2: Rincian Transaksi -->
    <div id="tab-detail" style="display: none;">
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 40px;">No</th>
                    <th>Tanggal</th>
                    <th>NIK</th>
                    <th>Nama Pekerja</th>
                    <th>Cabang</th>
                    <th>Jenis Biaya</th>
                    <th class="text-right">Nominal</th>
                    <th class="text-center">Status</th>
                    <th>No Transaksi / JM</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($detail_res && $detail_res->num_rows > 0): ?>
                    <?php $no = 1; while ($d = $detail_res->fetch_assoc()): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($d['tanggal'])) ?></td>
                            <td><b><?= htmlspecialchars($d['nik']) ?></b></td>
                            <td><?= htmlspecialchars($d['nama'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['nama_cabang'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['namaby'] ?? '-') ?></td>
                            <td class="text-right">Rp <?= number_format($d['nom'], 0, ',', '.') ?></td>
                            <td class="text-center">
                                <?php if ($d['klaim'] == '1'): ?>
                                    <span class="badge badge-green"><i class="fa-solid fa-check"></i> DIKLAIM</span>
                                <?php else: ?>
                                    <span class="badge badge-red"><i class="fa-solid fa-clock"></i> PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= htmlspecialchars($d['kode_transaksi'] ?: '-') ?></code></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center" style="color: #64748b; padding: 20px;">Tidak ada transaksi biaya ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    if (tab === 'rekap') {
        document.getElementById('tab-rekap').style.display = 'block';
        document.getElementById('tab-detail').style.display = 'none';
        event.currentTarget.classList.add('active');
    } else {
        document.getElementById('tab-rekap').style.display = 'none';
        document.getElementById('tab-detail').style.display = 'block';
        event.currentTarget.classList.add('active');
    }
}
</script>
</body>
</html>
<?php $conn->close(); ?>