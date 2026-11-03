<?php
/**
 * Vercel Serverless Entry Point & Front Controller
 * Dispatches requests to the appropriate PHP application file on Vercel
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH);
$cleanUri = rtrim($uri, '/');

// 1. Admin Authentication: Login
if ($cleanUri === '/admin/login' || $uri === '/admin/login.php') {
    require __DIR__ . '/../admin/login.php';
    exit;
}

// 2. Admin Authentication: Logout
if ($cleanUri === '/admin/logout' || $uri === '/admin/logout.php') {
    require __DIR__ . '/../admin/logout.php';
    exit;
}

// 3. Admin Dashboard
if ($cleanUri === '/admin' || $uri === '/admin/index.php') {
    require __DIR__ . '/../admin/index.php';
    exit;
}

// 4. Fallback to main catalog
require __DIR__ . '/../index.php';

