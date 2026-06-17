<?php
/**
 * SendMail Web Installer
 * Standalone wizard — no Laravel, no Composer needed at runtime.
 * Included by public/index.php before Laravel boots.
 */

session_name('sm_install');
session_start();

$root     = dirname(dirname(__DIR__)); // project root (public/install/ → public/ → root)
$step     = $_SESSION['install_step'] ?? 1;
$errors   = [];

// ── Detect app URL ────────────────────────────────────────────────────────────
function detect_app_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $base   = rtrim(dirname($script), '/');
    return rtrim($scheme . '://' . $host . $base, '/');
}

// ── Process POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'check_req') {
        // Step 1 → 2: just advance
        $_SESSION['install_step'] = 2;
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    } elseif ($action === 'save_db') {
        $db = [
            'host'   => trim($_POST['host']   ?? ''),
            'port'   => trim($_POST['port']   ?? '3306'),
            'name'   => trim($_POST['name']   ?? ''),
            'user'   => trim($_POST['user']   ?? ''),
            'pass'   => $_POST['pass']        ?? '',
            'prefix' => trim($_POST['prefix'] ?? 'sm_'),
        ];
        if (empty($db['host'])) $errors[] = 'Host obbligatorio.';
        if (empty($db['name'])) $errors[] = 'Nome database obbligatorio.';
        if (empty($db['user'])) $errors[] = 'Utente obbligatorio.';
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $db['prefix'])) {
            $errors[] = 'Prefisso non valido (solo lettere, numeri, underscore).';
        }
        if (empty($errors)) {
            try {
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
                new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $_SESSION['install_db']   = $db;
                $_SESSION['install_step'] = 3;
                header('Location: ' . $_SERVER['REQUEST_URI']);
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Connessione fallita: ' . htmlspecialchars($e->getMessage());
            }
        }
        $step = 2;
        $_SESSION['install_step'] = 2;

    } elseif ($action === 'save_app') {
        $app = [
            'app_name'    => trim($_POST['app_name']    ?? ''),
            'app_url'     => rtrim(trim($_POST['app_url'] ?? ''), '/'),
            'admin_name'  => trim($_POST['admin_name']  ?? ''),
            'admin_email' => trim($_POST['admin_email'] ?? ''),
            'admin_pass'  => $_POST['admin_pass']       ?? '',
            'admin_pass2' => $_POST['admin_pass2']      ?? '',
        ];
        if (empty($app['app_name']))   $errors[] = 'Nome applicazione obbligatorio.';
        if (empty($app['app_url']))    $errors[] = 'URL applicazione obbligatorio.';
        if (empty($app['admin_name'])) $errors[] = 'Nome admin obbligatorio.';
        if (!filter_var($app['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email admin non valida.';
        if (strlen($app['admin_pass']) < 8) $errors[] = 'Password minimo 8 caratteri.';
        if ($app['admin_pass'] !== $app['admin_pass2']) $errors[] = 'Le password non coincidono.';
        if (empty($errors)) {
            $_SESSION['install_app']  = $app;
            $_SESSION['install_step'] = 4;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $step = 3;
        $_SESSION['install_step'] = 3;

    } elseif ($action === 'run_install') {
        if (empty($_SESSION['install_db']) || empty($_SESSION['install_app'])) {
            $_SESSION['install_step'] = 1;
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $step = 4;
        // execute installation below, outside the POST block
    }
}

// ── HTML helpers ──────────────────────────────────────────────────────────────
function wiz_open(string $title, int $current): void
{
    $steps = ['Requisiti', 'Database', 'Configurazione', 'Installazione'];
    $bars  = '';
    foreach ($steps as $i => $label) {
        $n   = $i + 1;
        $cls = $n < $current ? 'done' : ($n === $current ? 'active' : 'todo');
        $bars .= "<div class=\"si {$cls}\"><div class=\"sd\">{$n}</div><div class=\"sl\">{$label}</div></div>";
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — SendMail Installer</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{--brand:#8B5CF6;--brand-h:#7A41E8;--brand-a:#6730CC;--brand-light:#EBE4FD;--slate-800:#2C3542;--slate-50:#F6F7F9}
body{background:var(--slate-50);font-family:"Plus Jakarta Sans",system-ui,sans-serif;color:var(--slate-800)}
h1,h2,h3,h4,h5,h6{font-family:"Poppins",sans-serif;font-weight:600}
.wiz{max-width:640px;margin:2.5rem auto;padding:0 1rem}
.brand{margin-bottom:1.5rem;text-align:center}
.sbar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;position:relative}
.sbar::before{content:'';position:absolute;top:16px;left:16px;right:16px;height:2px;background:#D8DCE2;z-index:0}
.si{display:flex;flex-direction:column;align-items:center;gap:6px;z-index:1;flex:1}
.sd{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;font-family:"Poppins",sans-serif}
.sl{font-size:.75rem;text-align:center;white-space:nowrap}
.si.done .sd{background:#1FA971;color:#fff}.si.done .sl{color:#1FA971}
.si.active .sd{background:var(--brand);color:#fff}.si.active .sl{color:var(--brand);font-weight:600}
.si.todo .sd{background:#D8DCE2;color:#647082}.si.todo .sl{color:#647082}
.rr{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0}
.rr:last-child{border-bottom:none}
.card{border-color:#D8DCE2;box-shadow:0 1px 4px rgba(0,0,0,.06)!important}
.btn-primary{background:var(--brand);border-color:var(--brand);color:#fff;font-weight:600}
.btn-primary:hover{background:var(--brand-h);border-color:var(--brand-h);color:#fff}
.btn-primary:active{background:var(--brand-a);border-color:var(--brand-a);color:#fff}
.btn-success{font-weight:600}
.btn-outline-secondary{font-weight:500}
.form-control:focus{border-color:var(--brand);box-shadow:0 0 0 .25rem rgba(139,92,246,.2)}
</style>
</head>
<body>
<div class="wiz">
<div class="brand">
  <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMDAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCAzMDAgODAiIGZvbnQtZmFtaWx5PSJQb3BwaW5zLCBzYW5zLXNlcmlmIj48ZyB0cmFuc2Zvcm09InRyYW5zbGF0ZSgyIDgpIiBmaWxsPSJub25lIiBzdHJva2Utd2lkdGg9IjQuNSIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIyOSIgY3k9IjMxIiByPSI4LjUiIHN0cm9rZT0iIzhCNUNGNiI+PC9jaXJjbGU+PHBhdGggZD0iTTM3LjUgMzFWMzRhNiA2IDAgMCAwIDEyIDBWMzFhMjAgMjAgMCAxIDAtNyAxNS4yIiBzdHJva2U9IiM4QjVDRjYiPjwvcGF0aD48cGF0aCBkPSJNNDggMTIgNTggMTAgNTYgMjAiIHN0cm9rZT0iIzhCNUNGNiI+PC9wYXRoPjwvZz48dGV4dCB4PSI4MCIgeT0iNTIiIGZvbnQtc2l6ZT0iNDQiIGZvbnQtd2VpZ2h0PSI3MDAiIGxldHRlci1zcGFjaW5nPSItMSI+PHRzcGFuIGZpbGw9IiMzRjRCNUIiPlNlbmQ8L3RzcGFuPjx0c3BhbiBmaWxsPSIjOEI1Q0Y2Ij5tYWlsPC90c3Bhbj48L3RleHQ+PC9zdmc+" alt="SendMail" height="52">
</div>
<div class="sbar">{$bars}</div>
<div class="card shadow-sm"><div class="card-body p-4">
<h5 class="mb-4">{$title}</h5>
HTML;
}

function wiz_close(): void
{
    echo '</div></div><p class="text-center mt-3" style="font-size:.8rem;color:#8A93A3">SendMail — Self-hosted Newsletter Platform</p></div></body></html>';
}

function req_row(string $label, bool $ok, string $detail = ''): void
{
    $icon = $ok ? '<span class="text-success">✔</span>' : '<span class="text-danger">✘</span>';
    echo "<div class=\"rr\"><span>{$label}" . ($detail ? " <small class=\"text-muted\">{$detail}</small>" : '') . "</span>{$icon}</div>\n";
}

$installUrl = $_SERVER['REQUEST_URI'];

// ── Step 1: Requirements ──────────────────────────────────────────────────────
if ($step === 1) {
    $checks = [
        ['PHP ≥ 8.2',             version_compare(PHP_VERSION, '8.2.0', '>='),   'Trovato: ' . PHP_VERSION],
        ['pdo_mysql',             extension_loaded('pdo_mysql'),                  ''],
        ['mbstring',              extension_loaded('mbstring'),                   ''],
        ['openssl',               extension_loaded('openssl'),                    ''],
        ['fileinfo',              extension_loaded('fileinfo'),                   ''],
        ['storage/ scrivibile',   is_writable($root . '/storage'),               ''],
        ['bootstrap/cache/ scriv.',is_writable($root . '/bootstrap/cache'),      ''],
        ['.env scrivibile',       !file_exists($root . '/.env') || is_writable($root . '/.env'), ''],
        ['install/ scrivibile',   is_writable(__DIR__),                          ''],
    ];
    $allOk = array_reduce($checks, fn($c, $r) => $c && $r[1], true);

    wiz_open('Verifica requisiti', 1);
    foreach ($checks as [$label, $ok, $detail]) req_row($label, $ok, $detail);
    if ($allOk): ?>
        <form method="POST" class="mt-4">
            <input type="hidden" name="_action" value="check_req">
            <button type="submit" class="btn btn-primary w-100">Continua →</button>
        </form>
    <?php else: ?>
        <div class="alert alert-danger mt-4">Risolvi i requisiti mancanti prima di procedere.</div>
        <a href="<?= htmlspecialchars($installUrl) ?>" class="btn btn-outline-secondary w-100 mt-2">Ricontrolla</a>
    <?php endif;
    wiz_close();
    exit;
}

// ── Step 2: Database ──────────────────────────────────────────────────────────
if ($step === 2) {
    $vals = $_SESSION['install_db'] ?? ['host'=>'127.0.0.1','port'=>'3306','name'=>'','user'=>'','pass'=>'','prefix'=>'sm_'];
    wiz_open('Configurazione database', 2);
    if ($errors): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
        <input type="hidden" name="_action" value="save_db">
        <div class="row g-3">
            <div class="col-8">
                <label class="form-label">Host *</label>
                <input type="text" name="host" class="form-control" value="<?= htmlspecialchars($vals['host']) ?>" required>
            </div>
            <div class="col-4">
                <label class="form-label">Porta</label>
                <input type="text" name="port" class="form-control" value="<?= htmlspecialchars($vals['port']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Nome database *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($vals['name']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Utente *</label>
                <input type="text" name="user" class="form-control" value="<?= htmlspecialchars($vals['user']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Password</label>
                <input type="password" name="pass" class="form-control" value="<?= htmlspecialchars($vals['pass']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Prefisso tabelle</label>
                <input type="text" name="prefix" class="form-control" value="<?= htmlspecialchars($vals['prefix']) ?>">
                <div class="form-text">Default <code>sm_</code> — cambia solo per installazioni multiple nello stesso DB.</div>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="fetch('<?= htmlspecialchars($installUrl) ?>', {method:'POST', body: new URLSearchParams({_action:'check_req'})}).then(() => location.reload())">
                ← Indietro
            </button>
            <button type="submit" class="btn btn-primary flex-grow-1">Testa connessione e continua →</button>
        </div>
    </form>
    <?php wiz_close();
    exit;
}

// ── Step 3: App config + Admin ────────────────────────────────────────────────
if ($step === 3) {
    $vals = $_SESSION['install_app'] ?? [
        'app_name'   => 'SendMail',
        'app_url'    => detect_app_url(),
        'admin_name' => '',
        'admin_email'=> '',
        'admin_pass' => '',
        'admin_pass2'=> '',
    ];
    wiz_open('Configurazione app e account admin', 3);
    if ($errors): ?>
        <div class="alert alert-danger"><?= implode('<br>', $errors) ?></div>
    <?php endif; ?>
    <form method="POST" novalidate>
        <input type="hidden" name="_action" value="save_app">
        <h6 class="text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em">Applicazione</h6>
        <div class="row g-3 mb-4">
            <div class="col-6">
                <label class="form-label">Nome applicazione *</label>
                <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($vals['app_name']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">URL applicazione *</label>
                <input type="url" name="app_url" class="form-control" value="<?= htmlspecialchars($vals['app_url']) ?>" required>
                <div class="form-text">Senza slash finale. Rilevato: <code><?= htmlspecialchars(detect_app_url()) ?></code></div>
            </div>
        </div>
        <h6 class="text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em">Account amministratore</h6>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Nome *</label>
                <input type="text" name="admin_name" class="form-control" value="<?= htmlspecialchars($vals['admin_name']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Email *</label>
                <input type="email" name="admin_email" class="form-control" value="<?= htmlspecialchars($vals['admin_email']) ?>" required>
            </div>
            <div class="col-6">
                <label class="form-label">Password * <small class="text-muted">(min. 8 caratteri)</small></label>
                <input type="password" name="admin_pass" class="form-control" required minlength="8">
            </div>
            <div class="col-6">
                <label class="form-label">Conferma password *</label>
                <input type="password" name="admin_pass2" class="form-control" required>
            </div>
        </div>
        <div class="mt-4 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="fetch('<?= htmlspecialchars($installUrl) ?>', {method:'POST', body: new URLSearchParams({_action:'save_db', host:'<?= htmlspecialchars(addslashes($_SESSION['install_db']['host'] ?? '')) ?>'})}).then(() => { history.go(0) })">
                ← Indietro
            </button>
            <button type="submit" class="btn btn-primary flex-grow-1">Continua →</button>
        </div>
    </form>
    <?php wiz_close();
    exit;
}

// ── Step 4: Summary + Execute ─────────────────────────────────────────────────
$db  = $_SESSION['install_db'];
$app = $_SESSION['install_app'];
$p   = $db['prefix'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['_action'] ?? '') !== 'run_install') {
    // Show summary
    wiz_open('Riepilogo — pronto per l\'installazione', 4);
    ?>
    <div class="mb-4">
        <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem;letter-spacing:.05em">Database</h6>
        <table class="table table-sm table-borderless mb-3">
            <tr><td class="text-muted" style="width:40%">Host</td><td><?= htmlspecialchars("{$db['host']}:{$db['port']}") ?></td></tr>
            <tr><td class="text-muted">Database</td><td><?= htmlspecialchars($db['name']) ?></td></tr>
            <tr><td class="text-muted">Utente</td><td><?= htmlspecialchars($db['user']) ?></td></tr>
            <tr><td class="text-muted">Prefisso</td><td><code><?= htmlspecialchars($p) ?></code></td></tr>
        </table>
        <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem;letter-spacing:.05em">Applicazione</h6>
        <table class="table table-sm table-borderless mb-0">
            <tr><td class="text-muted" style="width:40%">Nome</td><td><?= htmlspecialchars($app['app_name']) ?></td></tr>
            <tr><td class="text-muted">URL</td><td><?= htmlspecialchars($app['app_url']) ?></td></tr>
            <tr><td class="text-muted">Admin email</td><td><?= htmlspecialchars($app['admin_email']) ?></td></tr>
        </table>
    </div>
    <form method="POST">
        <input type="hidden" name="_action" value="run_install">
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary"
                    onclick="<?php
                        $db_back = array_merge(['_action'=>'save_db'], $db);
                        $qs = http_build_query($db_back);
                        echo "fetch('" . htmlspecialchars($installUrl) . "',{method:'POST',body:new URLSearchParams('" . addslashes($qs) . "')}).then(()=>location.reload())";
                    ?>">
                ← Indietro
            </button>
            <button type="submit" class="btn btn-success flex-grow-1">🚀 Installa SendMail</button>
        </div>
    </form>
    <?php
    wiz_close();
    exit;
}

// ── Run Installation ──────────────────────────────────────────────────────────
$log   = [];
$abort = false;
$pdo   = null;

function ilog(array &$log, string $msg, bool $ok): void { $log[] = [$msg, $ok]; }

// 1. Connessione DB
try {
    $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    ilog($log, 'Connessione al database', true);
} catch (PDOException $e) {
    ilog($log, 'Connessione al database: ' . $e->getMessage(), false);
    $abort = true;
}

// 2. Crea tabelle
if (!$abort) {
    foreach (install_sql($p) as [$name, $sql]) {
        try {
            $pdo->exec($sql);
            ilog($log, "Tabella: {$name}", true);
        } catch (PDOException $e) {
            ilog($log, "Tabella {$name}: " . $e->getMessage(), false);
            $abort = true; break;
        }
    }
}

// 3. Migrations record
if (!$abort) {
    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES (?, 1)');
        foreach (install_migrations() as $m) $stmt->execute([$m]);
        ilog($log, 'Registro migrazioni', true);
    } catch (PDOException $e) {
        ilog($log, 'Registro migrazioni: ' . $e->getMessage(), false);
        $abort = true;
    }
}

// 4. Admin user
if (!$abort) {
    try {
        $hash = password_hash($app['admin_pass'], PASSWORD_BCRYPT, ['cost' => 12]);
        $now  = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO `users` (`name`,`email`,`password`,`email_verified_at`,`created_at`,`updated_at`) VALUES (?,?,?,?,?,?)')
            ->execute([$app['admin_name'], $app['admin_email'], $hash, $now, $now, $now]);
        ilog($log, 'Account amministratore creato', true);
    } catch (PDOException $e) {
        ilog($log, 'Account admin: ' . $e->getMessage(), false);
        $abort = true;
    }
}

// 5. Write .env
if (!$abort) {
    try {
        $key = 'base64:' . base64_encode(random_bytes(32));
        file_put_contents($root . '/.env', install_env($app['app_name'], $app['app_url'], $key, $db));
        ilog($log, 'File .env generato', true);
    } catch (Throwable $e) {
        ilog($log, 'Scrittura .env: ' . $e->getMessage(), false);
        $abort = true;
    }
}

// 6. Marker
if (!$abort) {
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
    ilog($log, 'Installazione completata', true);
}

// Output
wiz_open($abort ? 'Installazione fallita' : 'Installazione completata!', 4);
?>
<div class="mb-4">
<?php foreach ($log as [$msg, $ok]): ?>
    <div class="rr">
        <span><?= htmlspecialchars($msg) ?></span>
        <?= $ok ? '<span class="text-success">✔</span>' : '<span class="text-danger">✘</span>' ?>
    </div>
<?php endforeach ?>
</div>
<?php if ($abort): ?>
    <div class="alert alert-danger">L'installazione si è interrotta. Correggi gli errori e riprova.</div>
    <form method="POST"><input type="hidden" name="_action" value="run_install">
    <button type="submit" class="btn btn-outline-danger w-100">Riprova</button></form>
<?php else: ?>
    <?php session_unset(); session_destroy(); ?>
    <div class="alert alert-success">SendMail installato correttamente. Puoi accedere.</div>
    <a href="<?= htmlspecialchars($app['app_url']) ?>/login" class="btn btn-primary w-100">Vai al login →</a>
<?php endif;
wiz_close();
exit;

// ── SQL definitions ───────────────────────────────────────────────────────────
function install_sql(string $p): array
{
    return [
        ['migrations', "CREATE TABLE IF NOT EXISTS `migrations` (
          `id` int unsigned NOT NULL AUTO_INCREMENT, `migration` varchar(255) NOT NULL, `batch` int NOT NULL,
          PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['users', "CREATE TABLE IF NOT EXISTS `users` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL, `email_verified_at` timestamp NULL DEFAULT NULL,
          `password` varchar(255) NOT NULL, `remember_token` varchar(100) DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`), UNIQUE KEY `users_email_unique` (`email`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['password_reset_tokens', "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
          `email` varchar(255) NOT NULL, `token` varchar(255) NOT NULL,
          `created_at` timestamp NULL DEFAULT NULL, PRIMARY KEY (`email`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['sessions', "CREATE TABLE IF NOT EXISTS `sessions` (
          `id` varchar(255) NOT NULL, `user_id` bigint unsigned DEFAULT NULL,
          `ip_address` varchar(45) DEFAULT NULL, `user_agent` text, `payload` longtext NOT NULL,
          `last_activity` int NOT NULL, PRIMARY KEY (`id`),
          KEY `sessions_user_id_index` (`user_id`), KEY `sessions_last_activity_index` (`last_activity`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['cache', "CREATE TABLE IF NOT EXISTS `cache` (
          `key` varchar(255) NOT NULL, `value` mediumtext NOT NULL, `expiration` bigint NOT NULL,
          PRIMARY KEY (`key`), KEY `cache_expiration_index` (`expiration`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['cache_locks', "CREATE TABLE IF NOT EXISTS `cache_locks` (
          `key` varchar(255) NOT NULL, `owner` varchar(255) NOT NULL, `expiration` bigint NOT NULL,
          PRIMARY KEY (`key`), KEY `cache_locks_expiration_index` (`expiration`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['jobs', "CREATE TABLE IF NOT EXISTS `jobs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `queue` varchar(255) NOT NULL,
          `payload` longtext NOT NULL, `attempts` smallint unsigned NOT NULL,
          `reserved_at` int unsigned DEFAULT NULL, `available_at` int unsigned NOT NULL,
          `created_at` int unsigned NOT NULL, PRIMARY KEY (`id`), KEY `jobs_queue_index` (`queue`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['job_batches', "CREATE TABLE IF NOT EXISTS `job_batches` (
          `id` varchar(255) NOT NULL, `name` varchar(255) NOT NULL, `total_jobs` int NOT NULL,
          `pending_jobs` int NOT NULL, `failed_jobs` int NOT NULL, `failed_job_ids` longtext NOT NULL,
          `options` mediumtext DEFAULT NULL, `cancelled_at` int DEFAULT NULL,
          `created_at` int NOT NULL, `finished_at` int DEFAULT NULL, PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ['failed_jobs', "CREATE TABLE IF NOT EXISTS `failed_jobs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `uuid` varchar(255) NOT NULL,
          `connection` text NOT NULL, `queue` text NOT NULL, `payload` longtext NOT NULL,
          `exception` longtext NOT NULL, `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`), UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}lists", "CREATE TABLE IF NOT EXISTS `{$p}lists` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `api_token` varchar(64) DEFAULT NULL,
          `name` varchar(255) NOT NULL, `from_name` varchar(255) NOT NULL,
          `from_email` varchar(255) NOT NULL, `reply_to` varchar(255) DEFAULT NULL,
          `double_optin` tinyint(1) NOT NULL DEFAULT '0',
          `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`), UNIQUE KEY `{$p}lists_api_token_unique` (`api_token`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}subscribers", "CREATE TABLE IF NOT EXISTS `{$p}subscribers` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `list_id` bigint unsigned NOT NULL,
          `email` varchar(255) NOT NULL, `first_name` varchar(255) DEFAULT NULL,
          `last_name` varchar(255) DEFAULT NULL, `company` varchar(255) DEFAULT NULL,
          `status` enum('subscribed','unsubscribed','bounced','complained','unconfirmed') NOT NULL DEFAULT 'subscribed',
          `token` varchar(64) NOT NULL, `subscribed_at` timestamp NULL DEFAULT NULL,
          `unsubscribed_at` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`), UNIQUE KEY `{$p}subscribers_token_unique` (`token`),
          KEY `{$p}subscribers_list_id_index` (`list_id`), KEY `{$p}subscribers_email_index` (`email`),
          CONSTRAINT `{$p}subscribers_list_id_foreign` FOREIGN KEY (`list_id`) REFERENCES `{$p}lists` (`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}campaigns", "CREATE TABLE IF NOT EXISTS `{$p}campaigns` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `subject` varchar(255) NOT NULL,
          `from_name` varchar(255) NOT NULL, `from_email` varchar(255) NOT NULL,
          `reply_to` varchar(255) DEFAULT NULL, `html_content` longtext, `design_json` longtext,
          `text_content` text,
          `status` enum('draft','scheduled','sending','sent','paused') NOT NULL DEFAULT 'draft',
          `scheduled_at` timestamp NULL DEFAULT NULL, `sent_at` timestamp NULL DEFAULT NULL,
          `total_recipients` int NOT NULL DEFAULT '0',
          `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}campaign_lists", "CREATE TABLE IF NOT EXISTS `{$p}campaign_lists` (
          `campaign_id` bigint unsigned NOT NULL, `list_id` bigint unsigned NOT NULL,
          PRIMARY KEY (`campaign_id`,`list_id`),
          CONSTRAINT `{$p}campaign_lists_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `{$p}campaigns` (`id`) ON DELETE CASCADE,
          CONSTRAINT `{$p}campaign_lists_list_id_foreign` FOREIGN KEY (`list_id`) REFERENCES `{$p}lists` (`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}campaign_sends", "CREATE TABLE IF NOT EXISTS `{$p}campaign_sends` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `campaign_id` bigint unsigned NOT NULL,
          `subscriber_id` bigint unsigned NOT NULL,
          `status` enum('pending','sent','failed','bounced','complained') NOT NULL DEFAULT 'pending',
          `sent_at` timestamp NULL DEFAULT NULL, `message_id` varchar(255) DEFAULT NULL,
          `delivered_at` timestamp NULL DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `{$p}campaign_sends_campaign_id_index` (`campaign_id`),
          KEY `{$p}campaign_sends_subscriber_id_index` (`subscriber_id`),
          KEY `{$p}campaign_sends_message_id_index` (`message_id`),
          CONSTRAINT `{$p}campaign_sends_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `{$p}campaigns` (`id`) ON DELETE CASCADE,
          CONSTRAINT `{$p}campaign_sends_subscriber_id_foreign` FOREIGN KEY (`subscriber_id`) REFERENCES `{$p}subscribers` (`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}campaign_opens", "CREATE TABLE IF NOT EXISTS `{$p}campaign_opens` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `campaign_id` bigint unsigned NOT NULL,
          `subscriber_id` bigint unsigned NOT NULL, `opened_at` timestamp NOT NULL,
          `ip` varchar(45) DEFAULT NULL, `user_agent` text, PRIMARY KEY (`id`),
          KEY `{$p}campaign_opens_campaign_id_index` (`campaign_id`),
          CONSTRAINT `{$p}campaign_opens_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `{$p}campaigns` (`id`) ON DELETE CASCADE,
          CONSTRAINT `{$p}campaign_opens_subscriber_id_foreign` FOREIGN KEY (`subscriber_id`) REFERENCES `{$p}subscribers` (`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}campaign_clicks", "CREATE TABLE IF NOT EXISTS `{$p}campaign_clicks` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `campaign_id` bigint unsigned NOT NULL,
          `subscriber_id` bigint unsigned NOT NULL, `original_url` text NOT NULL,
          `clicked_at` timestamp NOT NULL, `ip` varchar(45) DEFAULT NULL, PRIMARY KEY (`id`),
          KEY `{$p}campaign_clicks_campaign_id_index` (`campaign_id`),
          CONSTRAINT `{$p}campaign_clicks_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `{$p}campaigns` (`id`) ON DELETE CASCADE,
          CONSTRAINT `{$p}campaign_clicks_subscriber_id_foreign` FOREIGN KEY (`subscriber_id`) REFERENCES `{$p}subscribers` (`id`) ON DELETE CASCADE
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}settings", "CREATE TABLE IF NOT EXISTS `{$p}settings` (
          `key` varchar(100) NOT NULL, `value` text, PRIMARY KEY (`key`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}blacklist", "CREATE TABLE IF NOT EXISTS `{$p}blacklist` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `email` varchar(255) NOT NULL,
          `list_ids` json DEFAULT NULL, `reason` text,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`), UNIQUE KEY `{$p}blacklist_email_unique` (`email`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
        ["{$p}unlayer_blocks", "CREATE TABLE IF NOT EXISTS `{$p}unlayer_blocks` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL,
          `body` json NOT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"],
    ];
}

function install_migrations(): array
{
    return [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2026_06_14_080001_create_sm_lists_table',
        '2026_06_14_080002_create_sm_subscribers_table',
        '2026_06_14_080003_create_sm_campaigns_table',
        '2026_06_14_080004_create_sm_campaign_sends_table',
        '2026_06_14_080005_create_sm_campaign_opens_table',
        '2026_06_14_080006_create_sm_campaign_clicks_table',
        '2026_06_14_080007_create_sm_settings_table',
        '2026_06_15_053838_create_sm_blacklist_table',
        '2026_06_15_063429_create_sm_unlayer_blocks_table',
        '2026_06_15_064650_create_sm_campaign_lists_table',
        '2026_06_15_064651_drop_list_id_from_sm_campaigns',
        '2026_06_15_083121_add_design_json_to_sm_campaigns',
        '2026_06_15_104849_add_delivery_fields_to_sm_campaign_sends',
        '2026_06_15_110336_add_api_token_to_sm_lists',
    ];
}

function install_env(string $appName, string $appUrl, string $key, array $db): string
{
    $name   = str_contains($appName, ' ') ? "\"{$appName}\"" : $appName;
    $prefix = $db['prefix'];
    return <<<ENV
APP_NAME={$name}
APP_ENV=production
APP_KEY={$key}
APP_DEBUG=false
APP_URL={$appUrl}

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST={$db['host']}
DB_PORT={$db['port']}
DB_DATABASE={$db['name']}
DB_USERNAME={$db['user']}
DB_PASSWORD={$db['pass']}

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database

QUEUE_CONNECTION=sync

SM_TABLE_PREFIX={$prefix}
ENV;
}
