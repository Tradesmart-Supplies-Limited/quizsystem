<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';


// XAMPP-friendly sub-folder install: when the project folder itself is the web folder
// (e.g. htdocs/quizsystem with the root .htaccess forwarding requests into /public),
// tell Laravel the app lives at /quizsystem rather than /quizsystem/public so routes
// and generated URLs are correct. Has no effect when /public is the document root.
if (isset($_SERVER['SCRIPT_NAME'], $_SERVER['REQUEST_URI'])) {
    $publicDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $uri = $_SERVER['REQUEST_URI'];
    if (str_ends_with($publicDir, '/public') && ! str_starts_with($uri, $publicDir.'/') && $uri !== $publicDir) {
        $root = substr($publicDir, 0, -7);
        if ($root === '' || $uri === $root || str_starts_with($uri, $root.'/') || str_starts_with($uri, $root.'?')) {
            $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $root.'/index.php';
        }
    }
    unset($publicDir, $uri, $root);
}

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
