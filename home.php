<?php
// ============================================
// ✅ SESSION AMAN
// ============================================
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

// ============================================
// ✅ CEK LOGIN VALID
// ============================================
if (
    !isset($_SESSION['userid']) ||
    !isset($_SESSION['bagian']) ||
    !isset($_SESSION['jabatan']) ||
    !isset($_SESSION['location'])
) {
    header("Location: index.php");
    exit();
}

$userid   = $_SESSION['userid'];
$username = $_SESSION['username'] ?? '';
$bagian   = $_SESSION['bagian'];
$jabatan  = $_SESSION['jabatan'];
$location = $_SESSION['location']; // HO / CABANG

require_once 'config.php';

// ============================================
// ✅ KONEKSI DATABASE
// ============================================
$servername  = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database    = getenv('DB_NAME');

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// ============================================
// ✅ ATURAN AKSES MENU
// ============================================
if ($jabatan == 1 || strtolower($jabatan) === 'super admin' || $username === 'af') {
    // SUPER ADMIN / USER AF → semua menu aktif
    $sql = "SELECT * FROM menu WHERE aktif = 1 ORDER BY urutan";
    $stmt = $pdo->query($sql);
} else {
    if ($location === "HO" || $location === "1" || $location == 1) {
        // LOKASI HO → akses berdasarkan bagian / akses_location_menu HO
        $sql = "
            SELECT DISTINCT m.*
            FROM menu m
            LEFT JOIN akses_bagian ab ON ab.id_menu = m.id_menu AND ab.id_bagian = :bagian_id
            LEFT JOIN akses_location_menu alm ON alm.id_menu = m.id_menu AND alm.type = 'HO'
            WHERE m.aktif = 1
            AND (
                ab.id_menu IS NOT NULL 
                OR alm.id_menu IS NOT NULL 
                OR NOT EXISTS (SELECT 1 FROM akses_bagian WHERE id_bagian = :bagian_id2)
            )
            ORDER BY m.urutan
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'bagian_id' => $bagian,
            'bagian_id2' => $bagian
        ]);
    } else {
        // LOKASI CABANG → akses berdasarkan cabang / akses_location_menu CABANG
        $sql = "
            SELECT DISTINCT m.*
            FROM menu m
            LEFT JOIN akses_jabatan aj ON aj.id_menu = m.id_menu AND (aj.id_jabatan = :jabatan_id OR aj.id_jabatan = (SELECT idj FROM jabatan WHERE jabatan = :jabatan_nama LIMIT 1))
            LEFT JOIN akses_location_menu alm ON alm.id_menu = m.id_menu AND alm.type = 'CABANG'
            WHERE m.aktif = 1
            AND (
                alm.id_menu IS NOT NULL 
                OR aj.id_menu IS NOT NULL 
                OR NOT EXISTS (SELECT 1 FROM akses_location_menu WHERE type = 'CABANG')
            )
            ORDER BY m.urutan
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'jabatan_id' => $jabatan,
            'jabatan_nama' => $jabatan
        ]);
    }
}

$menus = $stmt->fetchAll();

// ============================================
// ✅ HITUNG BADGE PER MENU (SAFE TRY-CATCH)
// ============================================
foreach ($menus as &$m) {
    // === BADGE 1 ===
    $m['badge1'] = "";
    if (!empty($m['badge1_sql'])) {
        try {
            $sql1 = $m['badge1_sql'];

            if ($location == 1 || $location === "HO" || $location === "1") {
                // HO tidak filter location
                $sql1 = preg_replace('/AND\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\b/i', '', $sql1);
                $sql1 = preg_replace('/WHERE\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\s+AND/i', 'WHERE', $sql1);
                $sql1 = preg_replace('/WHERE\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\b/i', '', $sql1);
            }

            $stmt1 = $pdo->prepare($sql1);

            if (strpos($sql1, ':location') !== false) {
                $stmt1->bindValue(":location", $location, PDO::PARAM_INT);
            }

            $stmt1->execute();
            $m['badge1'] = $stmt1->fetchColumn() ?: "";
        } catch (Throwable $e) {
            $m['badge1'] = "";
        }
    }

    // === BADGE 2 ===
    $m['badge2'] = "";
    if (!empty($m['badge2_sql'])) {
        try {
            $sql2 = $m['badge2_sql'];

            if ($location == 1 || $location === "HO" || $location === "1") {
                $sql2 = preg_replace('/AND\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\b/i', '', $sql2);
                $sql2 = preg_replace('/WHERE\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\s+AND/i', 'WHERE', $sql2);
                $sql2 = preg_replace('/WHERE\s+([a-z0-9_]+\.)?cabang\s*=\s*:location\b/i', '', $sql2);
            }

            $stmt2 = $pdo->prepare($sql2);

            if (strpos($sql2, ':location') !== false) {
                $stmt2->bindValue(":location", $location, PDO::PARAM_INT);
            }

            $stmt2->execute();
            $m['badge2'] = $stmt2->fetchColumn() ?: "";
        } catch (Throwable $e) {
            $m['badge2'] = "";
        }
    }
}
unset($m);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
/* ====== GLOBAL STYLES ====== */
body {
    background-image: url('background.jpg');
    background-size: cover;
    background-position: center;
    font-family: ROG, sans-serif;
    margin: 0;
    padding: 4rem 2rem;
    display: flex;
    justify-content: center;
    text-align: center;
    height: 100vh;
}

/* ====== HEADER ====== */
header {
    position: relative;
    top: 0;
    left: 10;
    width: 100%;
    text-align: center;
    margin-bottom: 0;
}

#headt {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding: 0 20px;
    box-sizing: border-box;
}

.logo {
    position: fixed;
    top: 10px;
    left: 10px;
    width: 400px;
    height: auto;
    filter: drop-shadow(0 0 0px rgba(255, 255, 255, 1));
}

/* ====== CONTAINER ====== */
.container {
    max-width: 1200px;
    margin: 10 auto;
    padding: 20px 10px;
    position: relative;
}

/* ====== ANIMATION ====== */
@keyframes jatuh {
    0% {
        transform: translateY(-100px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

/* ====== TEXT STYLES ====== */
h1 {
    margin: 0;
    text-align: center;
    font-size: 60px;
    overflow: hidden;
    background-clip: text;
    -webkit-background-clip: text;
    color: white;
}

h2 {
    color: white;
    margin: 10px 0 0;
    text-align: center;
}

h3 {
    color: white;
    margin: 10px 0;
    font-size: 36px;
    text-align: center;
    animation: jatuh 2s ease-in-out forwards;
}

h4 {
    color: white;
    margin: 0;
    text-align: center;
    font-size: 20px;
}

p {
    color: white;
    margin: 0;
}

/* ====== ANIMATED TEXT ====== */
.animated-text {
    display: inline-block;
    animation: jatuh 1s ease-in-out forwards;
    opacity: 0;
}

/* ====== HOME ICON ====== */
.home-icon {
    position: absolute;
    top: 10px;
    right: 10px;
    color: #f00;
    transform: scale(1.3);
    transition: transform 0.3s;
}

.home-icon:hover {
    transform: scale(3.1);
    color: blue;
}

/* ====== ICON GRID ====== */
.icons {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 20px;
    justify-content: center;
    padding: 20px;
}

.icon {
    text-align: center;
    margin: 10px;
    padding: 10px;
    background-color: rgba(10, 100, 10, 0.8);
    border-radius: 10px;
    transition: transform 0.3s ease;
}

.icon i {
    font-size: 50px;
    background: linear-gradient(to right, #fff, #fff, #fff, #fff, skyblue, #0e504d);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.icon p {
    color: white;
    font-weight: bold;
    margin: 10px 0 0;
}

.icon:hover {
    transform: scale(1.2);
}

/* ====== CONTACT FORM ====== */
.contact-form {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.contact-form h3 {
    color: white;
    margin-bottom: 20px;
}

.contact-form input[type="text"],
.contact-form input[type="email"],
.contact-form textarea {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border: none;
    border-radius: 5px;
    background-color: #06424a;
    color: white;
    transition: background-color 0.3s;
}

.contact-form input:focus,
.contact-form textarea:focus {
    outline: none;
    box-shadow: 0 0 5px #0e504d;
}

.contact-form button {
    background-color: #06424a;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 20px;
    font-size: 30px;
    cursor: pointer;
    transition: background-color 0.3s;
    width: 500%;
}

.contact-form button:hover {
    background-color: #0e504d;
}

form button[type="submit"] {
    background-color: #0529f5;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 10px;
    font-size: 15px;
    width: 30%;
    margin-bottom: 20px;
    cursor: pointer;
    transition: background-color 0.3s;
}

/* ====== FOOTER ====== */
footer {
    background: var(--primary-color);
    color: #fff;
    text-align: center;
    padding: 1rem 0;
    position: fixed;
    bottom: 0;
    width: 100%;
    z-index: 1000;
}

footer p {
    margin: 0;
    font-size: 0.8rem;
}

/* ====== SECTIONS ====== */
.section {
    background: #fff;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 10px;
    box-shadow: 0 0 10px #ccc;
}

.status {
    font-size: 18px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.card {
    background-color: #e9f5ff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 2px 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.card:hover {
    transform: scale(1.02);
}

.card h4 {
    margin: 0 0 10px;
    color: #0056b3;
}

.card p {
    margin: 0;
    color: #333;
}

/* ====== SECTION BACKGROUNDS ====== */
#tentang {
    background-image: linear-gradient(to right, #41acba, #015d91, #06424a);
    color: white;
}

#layanan {
    background-image: linear-gradient(to right, #41acba, #bf721b, #06424a);
    color: white;
}

#portofolio {
    background-image: linear-gradient(to right, #41acba, #8c3577, #06424a);
    color: white;
}

#kontak {
    background-image: linear-gradient(to right, #41acba, #0c7d34, #06424a);
    color: white;
}

/* ====== PORTOFOLIO GRID ====== */
.portofolio-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.icon-box {
    width: 100%;
}
/* ===== BADGE OVERLAY ===== */
.icon {
    position: relative;
    overflow: visible;
}

.badge-overlay {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 50;
}

.badge-overlay span {
    padding: 3px 7px;
    min-width: 26px;
    text-align: center;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

/* Merah */
.badge-red {
    background: #d9534f;  /* merah bootstrap */
    color: #fff;
}

/* Kuning/Oranye */
.badge-yellow {
    background: #f0ad4e;
    color: #111;
}




/* ====== TABLET RESPONSIVE ====== */
@media only screen and (max-width: 720px) {
    .icons {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        font-size: 10px;
    }

    .icon {
        aspect-ratio: 1 / 1;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
    }

    .logo {
        width: 50%;
        height: auto;
    }
    h1 {
    margin: 0;
    text-align: center;
    font-size: 30px;
    overflow: hidden;
    background-clip: text;
    -webkit-background-clip: text;
    color: white;
}

}
</style>

    
    
</head>

 
<body>
   
      
        
<header style="position: fixed;">
<img src="logo.png" alt="Logo" width="100" style="position: absolute; left: 10px; top: 10px;">
   <a href="logout.php" class="left-icon" style="position: absolute; right: 20px; font-size: 24px; text-decoration: none; color: red;">
        <i class="fa-solid fa-person-walking-dashed-line-arrow-right"></i>
      <p>logout</p>
</a>
    
</header>


                  
   <div class="container">

    <h1 id="animated-heading">PT. ARNI FAMILY</h1>

    <section id="hero">
        <div class="icons">

        <?php if (!empty($menus)): ?>
            <?php foreach ($menus as $m): ?>
            
                <div class="icon">

                    <?php if ($m['badge1'] !== "" || $m['badge2'] !== ""): ?>
                        <div class="badge-overlay">
    <?php if (!empty($m['badge1'])): ?>
        <span class="badge-red"><?= htmlspecialchars($m['badge1']) ?></span>
    <?php endif; ?>
    <?php if (!empty($m['badge2'])): ?>
        <span class="badge-yellow"><?= htmlspecialchars($m['badge2']) ?></span>
    <?php endif; ?>
</div>

                    <?php endif; ?>

                    <a href="<?= htmlspecialchars($m['file_menu']) ?>" style="text-decoration:none;">
                        <i class="<?= htmlspecialchars($m['icon_menu']) ?>"></i>
                        <p style="color:white; font-weight:bold;"><?= htmlspecialchars($m['nama_menu']) ?></p>
                    </a>

                </div>

            <?php endforeach; ?>
        <?php endif; ?>

        </div>
    </section>

</div>

<footer>
    <p>© 2025 SYMOTECH</p>
</footer>

<script>
// ==== Animasi Tulisan PT ARNI FAMILY ====
document.addEventListener("DOMContentLoaded", function() {
    var heading = document.getElementById('animated-heading');
    var text = heading.textContent.trim();
    heading.textContent = '';

    for (var i = 0; i < text.length; i++) {
        var span = document.createElement('span');
        span.textContent = text[i];
        span.classList.add('animated-text');
        span.style.animationDelay = (i * 0.1) + 's';
        heading.appendChild(span);
    }
});

// ==== Disable klik kanan + F12 ====
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', function(e) {
    if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && ['I','C'].includes(String.fromCharCode(e.keyCode))) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
        e.preventDefault();
    }
});
</script>

</body>
</html>