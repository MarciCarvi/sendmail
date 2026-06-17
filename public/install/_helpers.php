<?php

function installer_guard(): void
{
    if (file_exists(__DIR__ . '/.installed')) {
        header('Location: /');
        exit;
    }
}

function session_start_once(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('sm_install');
        session_start();
    }
}

function detect_app_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '/install/index.php';
    $appPath = preg_replace('#/install/[^/]*$#', '', $script);
    return rtrim($scheme . '://' . $host . $appPath, '/');
}

function html_open(string $title, int $step): string
{
    $steps = ['Requisiti', 'Database', 'Configurazione', 'Installazione'];
    $bars  = '';
    foreach ($steps as $i => $label) {
        $n    = $i + 1;
        $cls  = $n < $step ? 'done' : ($n === $step ? 'active' : 'todo');
        $bars .= "<div class=\"step-item {$cls}\"><div class=\"step-dot\">{$n}</div><div class=\"step-label\">{$label}</div></div>";
    }
    return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — SendMail Installer</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f0f2f5}
.installer-card{max-width:640px;margin:2.5rem auto;padding:0 1rem}
.brand{font-size:1.4rem;font-weight:700;color:#0d6efd;margin-bottom:1.5rem;text-align:center}
.step-bar{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2rem;position:relative}
.step-bar::before{content:'';position:absolute;top:16px;left:16px;right:16px;height:2px;background:#dee2e6;z-index:0}
.step-item{display:flex;flex-direction:column;align-items:center;gap:6px;z-index:1;flex:1}
.step-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem}
.step-label{font-size:.75rem;text-align:center;white-space:nowrap}
.step-item.done .step-dot{background:#198754;color:#fff}
.step-item.done .step-label{color:#198754}
.step-item.active .step-dot{background:#0d6efd;color:#fff}
.step-item.active .step-label{color:#0d6efd;font-weight:600}
.step-item.todo .step-dot{background:#dee2e6;color:#6c757d}
.step-item.todo .step-label{color:#6c757d}
.req-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f0f0f0}
.req-row:last-child{border-bottom:none}
</style>
</head>
<body>
<div class="installer-card">
<div class="brand">📧 SendMail Installer</div>
<div class="step-bar">{$bars}</div>
<div class="card shadow-sm">
<div class="card-body p-4">
<h5 class="mb-4">{$title}</h5>
HTML;
}

function html_close(): string
{
    return <<<HTML
</div></div>
<p class="text-center text-muted small mt-3">SendMail — Self-hosted Newsletter Platform</p>
</div>
</body>
</html>
HTML;
}

function check_requirement(string $label, bool $ok, string $detail = ''): void
{
    $icon  = $ok ? '<span class="text-success">✔</span>' : '<span class="text-danger">✘</span>';
    $color = $ok ? '' : 'text-danger';
    echo "<div class=\"req-row\"><span>{$label}" . ($detail ? " <small class=\"text-muted\">{$detail}</small>" : '') . "</span>{$icon}</div>\n";
}
