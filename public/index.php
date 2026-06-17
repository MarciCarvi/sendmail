<?php

// Shared hosting subfolder fix: strip /public from SCRIPT_NAME so Symfony computes
// the correct base path (/subfolder instead of /subfolder/public).
if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/public/index.php')) {
    $baseDir = str_replace('/public/index.php', '', $_SERVER['SCRIPT_NAME']);
    $_SERVER['SCRIPT_NAME'] = $baseDir . '/index.php';
    $_SERVER['PHP_SELF']    = str_replace('/public/index.php', '/index.php', $_SERVER['PHP_SELF'] ?? '');

    // Also normalize REQUEST_URI if it contains /public/ (e.g. legacy SNS webhook URLs
    // or APP_URL misconfigured with /public suffix). Strip the extra /public segment so
    // Laravel sees /webhook/ses instead of /public/webhook/ses.
    $pubPrefix = $baseDir . '/public/';
    if (isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], $pubPrefix)) {
        $_SERVER['REQUEST_URI'] = $baseDir . '/' . substr($_SERVER['REQUEST_URI'], strlen($pubPrefix));
    }
}

// Run installer if not yet installed (works on any PHP host including Herd/Valet)
if (!file_exists(__DIR__ . '/install/.installed')) {
    require __DIR__ . '/install/_wizard.php';
    exit;
}

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
