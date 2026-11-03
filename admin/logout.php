<?php
/**
 * Miniecake - Admin Logout
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::logout();
header('Location: ' . base_url('admin/login'));
exit;

