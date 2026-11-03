<?php
/**
 * Admin Authentication Manager for Miniecake
 * Supports stateless signed cookie token for multi-container / serverless platforms (Vercel)
 * and standard PHP session fallback.
 */

require_once __DIR__ . '/../config/app.php';

class Auth {
    private static function sessionKey() {
        return env('ADMIN_SESSION_NAME', 'miniecake_admin_session');
    }

    private static function cookieName() {
        return env('ADMIN_COOKIE_NAME', 'miniecake_admin_token');
    }

    /**
     * Get or generate a cryptographic secret key for signing tokens
     */
    private static function getSecretKey() {
        $secret = env('ADMIN_SESSION_SECRET');
        if (!empty($secret)) {
            return $secret;
        }
        $pwd = env('ADMIN_PASSWORD', 'password123');
        $salt = env('GOOGLE_CLIENT_SECRET', 'miniecake_auth_salt_secure_2026');
        return hash('sha256', $pwd . '::' . $salt);
    }

    /**
     * Generate a signed token (Payload + HMAC-SHA256 signature)
     */
    public static function createToken($username) {
        $now = time();
        $expires = $now + (30 * 86400); // 30 days validity
        $payload = [
            'u' => $username,
            't' => $now,
            'e' => $expires
        ];
        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payloadB64, self::getSecretKey());
        return $payloadB64 . '.' . $sig;
    }

    /**
     * Verify and decode a signed token
     */
    public static function verifyToken($token) {
        if (empty($token) || !is_string($token) || !str_contains($token, '.')) {
            return null;
        }

        [$payloadB64, $sig] = explode('.', $token, 2);
        if (empty($payloadB64) || empty($sig)) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $payloadB64, self::getSecretKey());
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }

        $json = base64_decode(strtr($payloadB64, '-_', '+/'));
        if (!$json) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['u']) || empty($data['e'])) {
            return null;
        }

        // Check expiration
        if ((int)$data['e'] < time()) {
            return null;
        }

        // Check valid admin username
        $expectedUser = env('ADMIN_USERNAME', 'admin');
        if ($data['u'] !== $expectedUser) {
            return null;
        }

        return $data;
    }

    /**
     * Check if current user is logged in
     */
    public static function check() {
        // 1. Check existing PHP session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (!empty($_SESSION[self::sessionKey()])) {
            return true;
        }

        // 2. Check signed cookie token (Stateless, survives Vercel container restarts & separate lambdas)
        $cookieToken = $_COOKIE[self::cookieName()] ?? null;
        if ($cookieToken) {
            $verified = self::verifyToken($cookieToken);
            if ($verified) {
                $_SESSION[self::sessionKey()] = [
                    'username' => $verified['u'],
                    'logged_at' => $verified['t']
                ];
                return true;
            }
        }

        // 3. Check Authorization Bearer header if passed (API requests)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (str_starts_with($authHeader, 'Bearer ')) {
            $bearerToken = trim(substr($authHeader, 7));
            $verified = self::verifyToken($bearerToken);
            if ($verified) {
                $_SESSION[self::sessionKey()] = [
                    'username' => $verified['u'],
                    'logged_at' => $verified['t']
                ];
                return true;
            }
        }

        return false;
    }

    /**
     * Authenticate admin user
     */
    public static function login($username, $password) {
        $envUser = env('ADMIN_USERNAME', 'admin');
        $envPass = env('ADMIN_PASSWORD', 'password123');

        if ($username === $envUser && $password === $envPass) {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }
            $_SESSION[self::sessionKey()] = [
                'username' => $username,
                'logged_at' => time()
            ];

            // Set signed cookie for serverless persistence across multiple Lambda instances
            $token = self::createToken($username);
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

            if (!headers_sent()) {
                setcookie(self::cookieName(), $token, [
                    'expires' => time() + (30 * 86400),
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            $_COOKIE[self::cookieName()] = $token;

            return true;
        }

        return false;
    }

    /**
     * Alias for login
     */
    public static function attempt($username, $password) {
        return self::login($username, $password);
    }

    /**
     * Get active token string
     */
    public static function getToken() {
        if (!self::check()) {
            return null;
        }
        $existing = $_COOKIE[self::cookieName()] ?? null;
        if ($existing && self::verifyToken($existing)) {
            return $existing;
        }
        $user = self::user();
        return self::createToken($user['username'] ?? env('ADMIN_USERNAME', 'admin'));
    }

    /**
     * Require authentication or redirect to login
     */
    public static function requireAuth() {
        if (!self::check()) {
            $loginUrl = base_url('admin/login');
            header("Location: $loginUrl");
            exit;
        }
    }

    /**
     * Alias for requireAuth
     */
    public static function requireLogin() {
        self::requireAuth();
    }

    /**
     * Logout and invalidate session & cookie
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        unset($_SESSION[self::sessionKey()]);
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_destroy();
        }

        if (!headers_sent()) {
            setcookie(self::cookieName(), '', [
                'expires' => time() - 86400,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        unset($_COOKIE[self::cookieName()]);
    }

    /**
     * Get logged-in user data
     */
    public static function user() {
        if (self::check()) {
            return $_SESSION[self::sessionKey()];
        }
        return null;
    }
}
