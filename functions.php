<?php
// functions.php
// small helpers used across pages

function pdo_connect() {
    $servername  = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
    $db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
    $db_password = getenv('DB_PASS');
    $database    = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi gagal: " . $e->getMessage());
    }
}

/**
 * Execute a COUNT query stored in menu.query_red or query_yellow.
 * If $is_location_filtered is true, append location filter.
 * IMPORTANT: menu.query_* must be a SELECT COUNT(*) ... (no trailing semicolon)
 */
function run_count_query(PDO $pdo, string $rawQuery, bool $is_location_filtered, string $locationName) {
    if (!$rawQuery) return 0;
    $q = trim($rawQuery);
    // Avoid multiple semicolons
    $q = rtrim($q, "; \t\n\r\0\x0B");

    if ($is_location_filtered) {
        // if query already contains 'where' (case-insensitive) append AND location = :loc
        if (stripos($q, ' where ') !== false) {
            $q .= " AND location = :loc";
        } else {
            $q .= " WHERE location = :loc";
        }
    }

    try {
        $stmt = $pdo->prepare($q);
        if ($is_location_filtered) {
            $stmt->bindValue(':loc', $locationName);
        }
        $stmt->execute();
        $res = $stmt->fetchColumn();
        return (int)$res;
    } catch (Exception $e) {
        // If the query fails, return 0 but log to a file for debugging
        error_log("Counter query failed: " . $e->getMessage() . " -- Query: " . $q);
        return 0;
    }
}

/** Get menus active for display, ordered */
function get_active_menus(PDO $pdo) {
    $stmt = $pdo->query("SELECT * FROM menu WHERE aktif = 1 ORDER BY urutan ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Simple admin check: adapt to your real rules */
function is_admin() {
    return isset($_SESSION['userid']) && $_SESSION['userid'] == 1;
}

/**
 * Standard Helper: Membersihkan format nominal angka (string ribuan titik/koma) menjadi float
 */
if (!function_exists('clean_nominal')) {
    function clean_nominal($val) {
        if (is_numeric($val)) return (float)$val;
        if (empty($val)) return 0.0;
        $val = trim((string)$val);
        $val = preg_replace('/[^0-9\.,\-]/', '', $val);
        $hasComma = (strpos($val, ',') !== false);
        $hasDot   = (strpos($val, '.') !== false);

        if ($hasComma && $hasDot) {
            if (strrpos($val, ',') > strrpos($val, '.')) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            } else {
                $val = str_replace(',', '', $val);
            }
        } elseif ($hasDot) {
            $parts = explode('.', $val);
            if (count($parts) > 2) {
                $val = str_replace('.', '', $val);
            }
        } elseif ($hasComma) {
            $parts = explode(',', $val);
            if (count($parts) > 2) {
                $val = str_replace(',', '', $val);
            } else {
                $val = str_replace(',', '.', $val);
            }
        }
        return (float)$val;
    }
}

/**
 * Standard Helper: Cek periode tutup buku terakhir
 */
if (!function_exists('getLatestClosedPeriod')) {
    function getLatestClosedPeriod($conn) {
        $result = ['bulan' => 0, 'tahun' => 0];
        if (!$conn) return $result;
        $q = $conn->query("SELECT bulan, tahun FROM close ORDER BY tahun DESC, bulan DESC LIMIT 1");
        if ($q && $row = $q->fetch_assoc()) {
            $result['bulan'] = (int)$row['bulan'];
            $result['tahun'] = (int)$row['tahun'];
        }
        return $result;
    }
}

/**
 * Standard Helper: Cek apakah periode tanggal transaksi telah ditutup
 */
if (!function_exists('isPeriodClosed')) {
    function isPeriodClosed($conn, $tanggal) {
        if (empty($tanggal)) return false;
        $m = (int)date('n', strtotime($tanggal));
        $y = (int)date('Y', strtotime($tanggal));

        $closed = getLatestClosedPeriod($conn);
        if ($closed['tahun'] > 0) {
            if ($y < $closed['tahun'] || ($y == $closed['tahun'] && $m <= $closed['bulan'])) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Standard Helper: Menentukan apakah sebuah COA bersaldo normal Kredit atau Debet
 */
if (!function_exists('is_credit_coa')) {
    function is_credit_coa($dc = '', $code = '', $name = '') {
        $code = (string)$code;
        $name = strtoupper(trim((string)$name));
        $dc   = strtolower(trim((string)$dc));

        // Kontra-aset (Akumulasi Depresiasi/Penyusutan) di kepala 1 wajib disajikan di Debet sebagai pengurang (nilai minus)
        if (
            strpos($code, '162') === 0 || 
            strpos($code, '1505') === 0 || 
            strpos($code, '122') === 0 || 
            strpos($code, '1-150') === 0 || 
            strpos($name, 'AKUMULASI') !== false ||
            strpos($name, 'AKUM.') !== false
        ) {
            return false;
        }

        if ($dc === 'credit' || $dc === 'kredit') return true;
        if ($dc === 'debet' || $dc === 'debit') return false;

        // Fallback berdasarkan digit pertama COA:
        // Kepala 2 (Kewajiban), 3 (Ekuitas/Modal), 4 (Pendapatan), 8 (Pendapatan Lain) bersaldo normal KREDIT
        $first = substr($code, 0, 1);
        if (in_array($first, ['2', '3', '4', '8'])) {
            return true;
        }

        // Kepala 1 (Aset), 5 (HPP), 6 (Beban Operasional), 7 (Beban Lain), 9 (Pajak) bersaldo normal DEBET
        return false;
    }
}

/**
 * Standard Helper: Pencocokan hierarki akun parent vs child berbasis layer
 */
if (!function_exists('match_coa_layer')) {
    function match_coa_layer($p_layer, $p_code, $c_code, $c_parent = '') {
        if (!empty($c_parent) && $c_parent === $p_code) return true;

        $p_layer = (int)$p_layer;
        $p_code = (string)$p_code;
        $c_code = (string)$c_code;

        // Format Dot Notation: XX.XX.XX.XXX (misal 11.01.01.001)
        if (strpos($p_code, '.') !== false || strpos($c_code, '.') !== false) {
            if ($p_layer === 1) {
                return substr($c_code, 0, 1) === substr($p_code, 0, 1);
            }
            if ($p_layer === 2) {
                $p_clean = rtrim($p_code, '.');
                return strpos($c_code, $p_clean . '.') === 0 || substr($c_code, 0, 5) === substr($p_code, 0, 5);
            }
            if ($p_layer === 3) {
                $p_clean = rtrim($p_code, '.');
                return strpos($c_code, $p_clean . '.') === 0 || substr($c_code, 0, 8) === substr($p_code, 0, 8);
            }
        }

        // Format Dash: X-XXXX
        if (strpos($p_code, '-') !== false && strpos($c_code, '-') !== false) {
            if ($p_layer === 1) return substr($c_code, 0, 1) === substr($p_code, 0, 1);
            if ($p_layer === 2) return substr($c_code, 0, 3) === substr($p_code, 0, 3);
            if ($p_layer === 3) return substr($c_code, 0, 4) === substr($p_code, 0, 4);
        }

        // Fallback digit prefix biasa (1100, 1110, 1111)
        if ($p_layer === 1) {
            return substr($c_code, 0, 1) === substr($p_code, 0, 1);
        }
        if ($p_layer === 2) {
            $prefix = rtrim($p_code, '0');
            if (strlen($prefix) < 2) $prefix = substr($p_code, 0, 2);
            return strpos($c_code, $prefix) === 0;
        }
        if ($p_layer === 3) {
            $prefix = rtrim($p_code, '0');
            if (strlen($prefix) < 3) $prefix = substr($p_code, 0, 3);
            return strpos($c_code, $prefix) === 0;
        }

        return false;
    }
}

/**
 * Standard Helper: Mendeteksi kode akun Laba Ditahan Tahun Lalu & Laba Tahun Berjalan secara dinamis
 */
if (!function_exists('find_re_accounts')) {
    function find_re_accounts($data = []) {
        $prev_code = null;
        $curr_code = null;

        // 1. Cek kandidat kode baku standard ekosistem
        $prev_candidates = ['31.02.01.001', '31.02.01.000', '3121.00', '3121', '311200', '3-1103', '32001', '3201', '3210'];
        $curr_candidates = ['31.03.01.001', '31.03.01.000', '3122.00', '3122', '311400', '3-1104', '32002', '3202', '3220'];

        foreach ($prev_candidates as $cand) {
            if (isset($data[$cand])) { $prev_code = $cand; break; }
        }
        foreach ($curr_candidates as $cand) {
            if (isset($data[$cand])) { $curr_code = $cand; break; }
        }

        // 2. Fallback pencocokan berdasarkan nama akun
        if (!$prev_code || !$curr_code) {
            foreach ($data as $code => $row) {
                $name = strtoupper(trim((string)($row['account_name'] ?? '')));
                if (!$prev_code && (
                    strpos($name, 'RE TAHUN LALU') !== false ||
                    strpos($name, 'LABA DITAHAN TAHUN LALU') !== false ||
                    strpos($name, 'LABA/RUGI DITAHAN') !== false ||
                    $name === 'LABA DITAHAN' ||
                    $name === 'LABA/RUGI DITAHAN'
                )) {
                    $prev_code = $code;
                }
                if (!$curr_code && (
                    strpos($name, 'RE TAHUN BERJALAN') !== false ||
                    strpos($name, 'LABA TAHUN BERJALAN') !== false ||
                    strpos($name, 'LABA BERJALAN') !== false ||
                    strpos($name, 'LABA/RUGI BERJALAN') !== false ||
                    $name === 'LABA BERJALAN' ||
                    $name === 'LABA/RUGI BERJALAN'
                )) {
                    $curr_code = $code;
                }
            }
        }

        return [
            'prev' => $prev_code ?: '31.02.01.001',
            'curr' => $curr_code ?: '31.03.01.001'
        ];
    }
}

/**
 * Standard Helper: Resolusi kode akun dinamis dari kandidat atau pencarian nama
 */
if (!function_exists('resolve_coa_code')) {
    function resolve_coa_code($conn, array $candidates, string $search_name = '', string $fallback = '') {
        if (!empty($candidates)) {
            $quoted = [];
            foreach ($candidates as $cand) {
                $quoted[] = "'" . $conn->real_escape_string($cand) . "'";
            }
            $sql = "SELECT account_code FROM coa WHERE account_code IN (" . implode(',', $quoted) . ") LIMIT 1";
            $res = $conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) {
                return $row['account_code'];
            }
        }

        if (!empty($search_name)) {
            $escaped = $conn->real_escape_string($search_name);
            $sql = "SELECT account_code FROM coa WHERE account_name LIKE '%{$escaped}%' ORDER BY layer DESC LIMIT 1";
            $res = $conn->query($sql);
            if ($res && $row = $res->fetch_assoc()) {
                return $row['account_code'];
            }
        }

        return $fallback;
    }
}

