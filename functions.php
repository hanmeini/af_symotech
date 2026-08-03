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
