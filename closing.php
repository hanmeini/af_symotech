<?php
// closing.php - Proses Tutup Buku (Closing) Periode PT Arni Family (Standar Masbox)
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

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';
require_once 'functions.php';

$servername  = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = (getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
$database    = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulan = isset($_POST['bulan']) ? (int)$_POST['bulan'] : 0;
    $tahun = isset($_POST['tahun']) ? (int)$_POST['tahun'] : 0;

    if (empty($bulan) || empty($tahun)) {
        echo "<script>alert('Kesalahan: Parameter bulan dan tahun tidak valid untuk closing!'); window.location.href='ns2.php';</script>";
        exit();
    }

    // 1. Cek data recurring yang belum diproses (jika tabel recuring ada)
    $chk_rec = $conn->query("SHOW TABLES LIKE 'recuring'");
    if ($chk_rec && $chk_rec->num_rows > 0) {
        $cekRecurring = $conn->prepare("SELECT COUNT(*) as jumlah FROM recuring WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (ab = '' OR ab IS NULL)");
        if ($cekRecurring) {
            $cekRecurring->bind_param("ii", $bulan, $tahun);
            $cekRecurring->execute();
            $cekRecurring->bind_result($jumlahRecurring);
            $cekRecurring->fetch();
            $cekRecurring->close();

            if ($jumlahRecurring > 0) {
                echo "<script>alert('Masih ada data recurring yang belum diproses. Closing dibatalkan.'); window.location.href='ns2.php';</script>";
                exit();
            }
        }
    }

    // 2. Cek apakah data periode ini sudah pernah ditutup
    $cekClosing = $conn->prepare("SELECT COUNT(*) as jumlah FROM close WHERE bulan = ? AND tahun = ?");
    if (!$cekClosing) {
        echo "<script>alert('Error query cek closing: " . addslashes($conn->error) . "'); window.location.href='ns2.php';</script>";
        exit();
    }
    $cekClosing->bind_param("ii", $bulan, $tahun);
    $cekClosing->execute();
    $cekClosing->bind_result($jumlahClosing);
    $cekClosing->fetch();
    $cekClosing->close();

    if ($jumlahClosing > 0) {
        echo "<script>alert('Data untuk bulan $bulan dan tahun $tahun ini sudah ditutup sebelumnya. Closing dibatalkan.'); window.location.href='ns2.php';</script>";
        exit();
    }

    $bulan_str = str_pad((string)$bulan, 2, '0', STR_PAD_LEFT);
    $tgl_akhir_periode = date('Y-m-t', strtotime("$tahun-$bulan_str-01"));

    // 3. Hitung ulang saldo akhir resmi di server & simpan ke tabel close secara mandiri
    $conn->begin_transaction();
    try {
        $stmt_close = $conn->prepare("INSERT INTO close (bulan, tahun, coa, saldo_akhir) VALUES (?, ?, ?, ?)");
        if (!$stmt_close) {
            throw new Exception("Query insert closing gagal: " . $conn->error);
        }

        // Query seluruh akun aktif layer 4 dari tabel coa
        $sql_calc_close = "
            SELECT c.account_code, c.account_name, COALESCE(c.dc, '') AS dc, COALESCE(c.posisi, '') AS posisi,
                COALESCE(SUM(j.debet - j.kredit), 0) AS raw_saldo
            FROM coa c
            LEFT JOIN jurnal j ON c.account_code = j.coa
             AND j.journal_number IS NOT NULL
             AND j.tanggal <= ?
            WHERE c.layer = 4
            GROUP BY c.account_code, c.account_name, c.dc, c.posisi
        ";
        $stmt_calc = $conn->prepare($sql_calc_close);
        if ($stmt_calc) {
            $stmt_calc->bind_param("s", $tgl_akhir_periode);
            $stmt_calc->execute();
            $res_calc = $stmt_calc->get_result();
            while ($row_c = $res_calc->fetch_assoc()) {
                $ac_code = $row_c['account_code'];
                $is_credit = is_credit_coa($row_c['dc'], $ac_code, $row_c['account_name']);
                $is_contra_asset = (
                    strpos($ac_code, '162') === 0 || 
                    strpos($ac_code, '1505') === 0 || 
                    strpos($ac_code, '122') === 0 || 
                    strpos($ac_code, '1-150') === 0 || 
                    strpos(strtoupper($row_c['account_name']), 'AKUMULASI') !== false ||
                    strpos(strtoupper($row_c['account_name']), 'AKUM.') !== false
                );

                $raw_val = (float)$row_c['raw_saldo'];
                if ($is_contra_asset) {
                    $calc_saldo = $raw_val;
                } else {
                    $calc_saldo = $is_credit ? -$raw_val : $raw_val;
                }

                $stmt_close->bind_param("iisd", $bulan, $tahun, $ac_code, $calc_saldo);
                $stmt_close->execute();
            }
            $stmt_calc->close();
        }

        // Tangkap juga transaksi pada unmapped coa (jika ada jurnal yang COA-nya belum terdaftar)
        $sql_missing_close = "
            SELECT j.coa,
                COALESCE(SUM(j.debet - j.kredit), 0) AS raw_saldo
            FROM jurnal j
            LEFT JOIN coa c ON c.account_code = j.coa
            WHERE j.journal_number IS NOT NULL
              AND c.account_code IS NULL
              AND j.tanggal <= ?
            GROUP BY j.coa
        ";
        $stmt_missing = $conn->prepare($sql_missing_close);
        if ($stmt_missing) {
            $stmt_missing->bind_param("s", $tgl_akhir_periode);
            $stmt_missing->execute();
            $res_missing = $stmt_missing->get_result();
            while ($row_m = $res_missing->fetch_assoc()) {
                $ac_code = $row_m['coa'];
                $is_credit = is_credit_coa('', $ac_code, '');
                $raw_val = (float)$row_m['raw_saldo'];
                $calc_saldo = $is_credit ? -$raw_val : $raw_val;

                $stmt_close->bind_param("iisd", $bulan, $tahun, $ac_code, $calc_saldo);
                $stmt_close->execute();
            }
            $stmt_missing->close();
        }

        $stmt_close->close();
        $conn->commit();

        echo "<script>
            alert('Proses tutup buku periode " . $bulan_str . "/" . $tahun . " berhasil diselesaikan.');
            window.location.href = 'ns2.php?bulan_awal=" . $bulan_str . "&bulan_akhir=" . $bulan_str . "&tahun=" . $tahun . "';
        </script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error saat proses closing: " . addslashes($e->getMessage()) . "'); window.location.href='ns2.php';</script>";
    }

    $conn->close();
} else {
    header("Location: ns2.php");
    exit();
}
