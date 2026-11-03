<?php
/**
 * Lightweight Router for PHP Built-in Server (php -S localhost:8000 router.php)
 * Emulates Apache .htaccess clean URLs locally.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $uri;

// Serve existing static assets directly
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

// If directory has index.php (e.g. /admin/)
if (is_dir($file) && file_exists(rtrim($file, '/') . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '/index.php';
    require rtrim($file, '/') . '/index.php';
    return true;
}

// If $uri.php exists
if (file_exists($file . '.php')) {
    $_SERVER['SCRIPT_NAME'] = $uri . '.php';
    require $file . '.php';
    return true;
}

// Fallback to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';

