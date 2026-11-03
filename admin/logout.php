<?php
/**
 * Miniecake - Admin Logout
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::logout();
header('Location: login');
exit;

