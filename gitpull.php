<?php
session_start();

// Ambil konfigurasi deploy dari config.php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$cpanel_url    = getenv('CPANEL_DEPLOY_URL')    ?: 'https://af.symotech.id/gitpull.php';
$cpanel_secret = getenv('CPANEL_DEPLOY_SECRET') ?: '';
$app_env       = getenv('APP_ENV') ?: 'local';

// Cek apakah diakses dari localhost (Mode Development/Lokal)
$is_localhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
    || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost:') === 0;

// ==========================================================================
// 1. HANDLER WEBHOOK / API TRIGGER (dipanggil oleh GitHub Actions)
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'api_trigger_pull') {
    header('Content-Type: text/plain; charset=utf-8');

    $received_secret = $_POST['secret'] ?? '';
    if (empty($cpanel_secret) || !hash_equals($cpanel_secret, $received_secret)) {
        http_response_code(403);
        echo "ERROR: Kode verifikasi (Secret Key) tidak cocok atau belum dikonfigurasi.";
        exit();
    }

    // Jalankan git fetch & reset di server cPanel untuk overwrite untracked files
    set_time_limit(120);
    echo "--- Menjalankan git fetch & reset di cPanel (af.symotech.id) ---\n";
    $output = shell_exec("git fetch origin main && git reset --hard origin/main 2>&1");
    echo $output ?: "Command tidak menghasilkan output (pastikan Git terinstall & diizinkan di cPanel).";
    echo "\n\n--- Status commit terbaru setelah update ---\n";
    echo shell_exec("git log -n 1 --oneline 2>&1");
    exit();
}

// Proteksi halaman web interface: hanya user yang sudah login
if (empty($_SESSION['username'])) {
    if (!$is_localhost) {
        http_response_code(403);
        die("Akses Ditolak: Anda harus login terlebih dahulu.");
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$output_console = "";
$executed_cmd   = "";

// ==========================================================================
// 2. HANDLER AKSI WEB INTERFACE
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $output_console = "ERROR: CSRF token tidak valid.";
    } else {
        $action = $_POST['action'];

        if ($is_localhost) {
            // --- LOGIKA LOKAL ---
            if ($action === 'local_push') {
                $executed_cmd   = "git push origin main";
                $output_console = "--- Memulai Git Push ke GitHub ---\n";
                $output_console .= shell_exec("git push origin main 2>&1");

            } elseif ($action === 'trigger_cpanel') {
                $executed_cmd   = "Trigger Git Pull ke cPanel";
                $output_console = "--- Menghubungi Server cPanel ---\nURL: " . $cpanel_url . "\n\n";
                if (empty($cpanel_url) || empty($cpanel_secret)) {
                    $output_console .= "ERROR: CPANEL_DEPLOY_URL atau CPANEL_DEPLOY_SECRET belum diisi di config.php.";
                } else {
                    $postdata = http_build_query(['action' => 'api_trigger_pull', 'secret' => $cpanel_secret]);
                    $opts     = ['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $postdata, 'timeout' => 90]];
                    $context  = stream_context_create($opts);
                    $response = @file_get_contents($cpanel_url, false, $context);
                    if ($response === false) {
                        $error = error_get_last();
                        $output_console .= "ERROR: Gagal menghubungi cPanel.\nDetail: " . ($error['message'] ?? 'Koneksi timeout/gagal.');
                    } else {
                        $output_console .= "--- Tanggapan dari Server cPanel ---\n" . $response;
                    }
                }

            } elseif ($action === 'full_deploy') {
                $executed_cmd   = "git push & trigger cPanel deploy";
                $output_console = "--- LANGKAH 1: Git Push ke GitHub ---\n";
                $output_console .= shell_exec("git push origin main 2>&1") . "\n";
                $output_console .= "--- LANGKAH 2: Menghubungi Server cPanel ---\n";
                if (empty($cpanel_url) || empty($cpanel_secret)) {
                    $output_console .= "ERROR: CPANEL_DEPLOY_URL atau CPANEL_DEPLOY_SECRET belum diisi di config.php.";
                } else {
                    $postdata = http_build_query(['action' => 'api_trigger_pull', 'secret' => $cpanel_secret]);
                    $opts     = ['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $postdata, 'timeout' => 90]];
                    $context  = stream_context_create($opts);
                    $response = @file_get_contents($cpanel_url, false, $context);
                    if ($response === false) {
                        $error = error_get_last();
                        $output_console .= "ERROR: Gagal menghubungi cPanel.\nDetail: " . ($error['message'] ?? 'Koneksi timeout.');
                    } else {
                        $output_console .= "--- Tanggapan dari Server cPanel ---\n" . $response;
                    }
                }

            } elseif ($action === 'status') {
                $executed_cmd   = "git status (Lokal)";
                $output_console = shell_exec("git status 2>&1");
            }

        } else {
            // --- LOGIKA LIVE (CPANEL) ---
            if ($action === 'status') {
                $executed_cmd   = "git status (cPanel)";
                $output_console = shell_exec("git status 2>&1");
            } elseif ($action === 'pull') {
                set_time_limit(120);
                $executed_cmd   = "git fetch & reset (cPanel)";
                $output_console = shell_exec("git fetch origin main && git reset --hard origin/main 2>&1");
            } elseif ($action === 'log') {
                $executed_cmd   = "git log (cPanel)";
                $output_console = shell_exec("git log -n 5 --oneline 2>&1");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Deployer - AF Symotech</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1a6b3c;
            --primary-dark: #0f4526;
            --primary-soft: #e8f5ee;
            --ink: #1f2d37;
            --muted: #687782;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --surface: #fff;
            --radius: 8px;
            --shadow: 0 16px 42px rgba(26, 107, 60, .08);
        }
        body { min-height: 100vh; margin: 0; font-family: Inter, Arial, sans-serif; color: var(--ink); background: linear-gradient(180deg, rgba(26, 107, 60, .08), transparent 330px), var(--bg); }
        .app-nav { background: var(--primary-dark); }
        .navbar-brand { font-weight: 800; }
        .brand-mark { width: 38px; height: 38px; border-radius: var(--radius); display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,.14); color: #fff; }
        .page-shell { width: min(1000px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 46px; }
        .eyebrow { color: var(--primary); font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .page-title { margin: 6px 0 0; color: var(--primary-dark); font-size: clamp(1.7rem,3vw,2.35rem); font-weight: 800; }
        .page-subtitle { max-width: 760px; margin: 8px 0 0; color: var(--muted); line-height: 1.55; }
        .panel { border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 22px; }
        .panel-head { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--line); background: linear-gradient(135deg, var(--primary-soft), #fff); }
        .title-wrap { display: flex; align-items: center; gap: 12px; }
        .title-icon { width: 42px; height: 42px; border-radius: var(--radius); display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; background: var(--primary); color: #fff; }
        .panel-title { margin: 0; color: var(--primary-dark); font-size: 1.06rem; font-weight: 800; }
        .panel-subtitle { display: block; margin-top: 2px; color: var(--muted); font-size: .83rem; }
        .console-wrap { background-color: #0f172a; color: #10b981; font-family: 'Consolas', 'Courier New', monospace; padding: 20px; border-radius: var(--radius); font-size: 0.9rem; min-height: 350px; max-height: 550px; overflow-y: auto; border: 1px solid #1e293b; box-shadow: inset 0 2px 8px rgba(0,0,0,0.8); white-space: pre-wrap; }
        .console-prompt { color: #38bdf8; font-weight: bold; }
        .btn { border-radius: var(--radius); font-weight: 700; }
        .btn-success { background: var(--primary); border-color: var(--primary); }
        .btn-success:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-nav">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
                <span class="brand-mark"><i class="fa-solid fa-globe"></i></span>
                <span>AF Symotech</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="home.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <header style="margin-bottom: 22px;">
            <div class="eyebrow">Deployment &amp; DevOps (<?= $is_localhost ? 'LOKAL' : 'SERVER LIVE' ?>)</div>
            <h1 class="page-title">Git Repository Deployer</h1>
            <p class="page-subtitle">
                <?= $is_localhost
                    ? 'Kirim pembaruan kode dari localhost ke GitHub dan otomatis update ke cPanel dengan sekali klik.'
                    : 'Menangani penerimaan kode pembaruan dari GitHub secara otomatis.' ?>
            </p>
        </header>

        <div class="row">
            <div class="col-md-4">
                <section class="panel mb-4">
                    <div class="panel-head">
                        <div class="title-wrap">
                            <span class="title-icon"><i class="fa-solid fa-terminal"></i></span>
                            <div>
                                <h2 class="panel-title">Kontrol Deploy</h2>
                                <span class="panel-subtitle">Mode: <strong><?= $is_localhost ? 'Lokal PC' : 'cPanel Live' ?></strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4 d-grid gap-3">
                        <p class="small text-muted"><strong>Direktori Kerja:</strong><br><code class="text-break"><?= e(getcwd()) ?></code></p>

                        <?php if ($is_localhost): ?>
                            <form action="gitpull.php" method="POST" onsubmit="return confirm('Satu-Klik Deploy ke cPanel?');">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="full_deploy">
                                <button type="submit" class="btn btn-success w-100 py-3 text-start">
                                    <i class="fa-solid fa-rocket me-2 fa-lg"></i> <strong>Satu-Klik Deploy ke cPanel</strong>
                                </button>
                            </form>
                            <form action="gitpull.php" method="POST" onsubmit="return confirm('Trigger cPanel git pull?');">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="trigger_cpanel">
                                <button type="submit" class="btn btn-outline-success w-100 py-2 text-start">
                                    <i class="fa-solid fa-cloud-arrow-down me-2"></i> Trigger cPanel Git Pull
                                </button>
                            </form>
                            <form action="gitpull.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="local_push">
                                <button type="submit" class="btn btn-outline-warning w-100 py-2 text-start">
                                    <i class="fa-solid fa-upload me-2"></i> Push ke GitHub saja
                                </button>
                            </form>
                            <form action="gitpull.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="status">
                                <button type="submit" class="btn btn-outline-secondary w-100 py-2 text-start">
                                    <i class="fa-solid fa-magnifying-glass me-2"></i> Cek Status Git Lokal
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="gitpull.php" method="POST" onsubmit="return confirm('Tarik kode terbaru dari GitHub?');">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="pull">
                                <button type="submit" class="btn btn-success w-100 py-3 text-start text-white">
                                    <i class="fa-solid fa-cloud-arrow-down me-2 fa-lg"></i> <strong>Tarik Kode (git pull)</strong>
                                </button>
                            </form>
                            <form action="gitpull.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="status">
                                <button type="submit" class="btn btn-outline-primary w-100 py-2 text-start">
                                    <i class="fa-solid fa-magnifying-glass me-2"></i> Cek Status Repo cPanel
                                </button>
                            </form>
                            <form action="gitpull.php" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="action" value="log">
                                <button type="submit" class="btn btn-outline-secondary w-100 py-2 text-start">
                                    <i class="fa-solid fa-list-ol me-2"></i> Lihat Log Commit cPanel
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-md-8">
                <section class="panel">
                    <div class="panel-head">
                        <div class="title-wrap">
                            <span class="title-icon"><i class="fa-solid fa-desktop"></i></span>
                            <div>
                                <h2 class="panel-title">Output Konsol Monitor</h2>
                                <span class="panel-subtitle">Log aktivitas eksekusi perintah</span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="console-wrap" id="consoleOutput">
                            <?php if ($executed_cmd !== ""): ?>
                                <span class="console-prompt"><?= $is_localhost ? 'localhost' : 'cpanel' ?>:~$</span> <span class="text-white"><?= e($executed_cmd) ?></span>
                                <br><?= e($output_console) ?>
                            <?php else: ?>
                                <span class="text-muted">Menunggu instruksi... Pilih salah satu aksi di panel kontrol sebelah kiri.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script src="assets/jquery.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var box = document.getElementById('consoleOutput');
            if (box) box.scrollTop = box.scrollHeight;
        });
    </script>
</body>
</html>
