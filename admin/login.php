<?php
/**
 * Miniecake - Admin Login Page
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';

if (Auth::check()) {
    header('Location: ./');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (Auth::login($username, $password)) {
        header('Location: ./');
        exit;
    } else {
        $error = 'Username atau password yang Anda masukkan salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Admin — Miniecake</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card">
      <a href="../" title="Kembali ke Katalog">
        <img src="../assets/images/logo.svg" alt="Miniecake" class="login-logo">
      </a>
      
      <h2 class="login-title">Panel Pengelola</h2>
      <p class="login-desc">Masuk untuk mengelola produk dan katalog kue Miniecake.</p>

      <?php if ($error): ?>
        <div class="login-alert">
          <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login" class="login-form">
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <input 
            type="text" 
            id="username" 
            name="username" 
            class="form-control" 
            placeholder="admin" 
            required 
            autocomplete="username"
            autofocus
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            class="form-control" 
            placeholder="••••••••" 
            required 
            autocomplete="current-password"
          >
        </div>

        <button type="submit" class="btn-login-submit">Masuk ke Dashboard</button>
      </form>

      <div style="margin-top: 24px; font-size: 0.8rem; color: #9ca3af;">
        <p>Default Login: <code>admin</code> / <code>password123</code></p>
        <p style="margin-top: 4px;"><a href="../" style="color: #ec4899; font-weight: 600;">&larr; Kembali ke Katalog Utama</a></p>
      </div>
    </div>
  </div>
</body>
</html>

