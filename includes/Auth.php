<?php
/**
 * Admin Authentication Manager
 */

require_once __DIR__ . '/../config/app.php';

class Auth {
    private static function sessionKey() {
        return env('ADMIN_SESSION_NAME', 'miniecake_admin_session');
    }

    public static function check() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        return !empty($_SESSION[self::sessionKey()]);
    }

    public static function login($username, $password) {
        $envUser = env('ADMIN_USERNAME', 'admin');
        $envPass = env('ADMIN_PASSWORD', 'password123');

        if ($username === $envUser && $password === $envPass) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION[self::sessionKey()] = [
                'username' => $username,
                'logged_at' => time()
            ];
            return true;
        }

        return false;
    }

    public static function requireAuth() {
        if (!self::check()) {
            $loginUrl = base_url('admin/login');
            header("Location: $loginUrl");
            exit;
        }
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        unset($_SESSION[self::sessionKey()]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function user() {
        if (self::check()) {
            return $_SESSION[self::sessionKey()];
        }
        return null;
    }
}
