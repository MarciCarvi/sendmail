<?php

// Shared hosting subfolder fix: when Apache rewrites /subfolder/path to /subfolder/public/index.php,
// Symfony sees SCRIPT_NAME=/subfolder/public/index.php but REQUEST_URI=/subfolder/path
// and cannot compute the correct base path. We strip /public from SCRIPT_NAME so Symfony
// calculates the base as /subfolder instead of /subfolder/public.
if (isset($_SERVER['SCRIPT_NAME']) && str_contains($_SERVER['SCRIPT_NAME'], '/public/index.php')) {
    $_SERVER['SCRIPT_NAME'] = str_replace('/public/index.php', '/index.php', $_SERVER['SCRIPT_NAME']);
    $_SERVER['PHP_SELF']    = str_replace('/public/index.php', '/index.php', $_SERVER['PHP_SELF'] ?? '');
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
