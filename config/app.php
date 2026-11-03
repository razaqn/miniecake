<?php
/**
 * Miniecake App Helper Configuration
 */

require_once __DIR__ . '/env.php';

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Start session if not started
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

/**
 * Format currency to Indonesian Rupiah
 */
function format_rupiah($number) {
    if (!is_numeric($number)) {
        return 'Rp 0';
    }
    return 'Rp ' . number_format((float)$number, 0, ',', '.');
}

/**
 * Sanitize text output for HTML
 */
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

/**
 * Get Base URL dynamically
 */
function base_url($path = '') {
    $configured = env('APP_URL');
    if ($configured) {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim($protocol . $host . $scriptDir, '/');
    
    // Normalize path
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

/**
 * Build WhatsApp click to chat URL with ready-check message
 */
function build_whatsapp_url($phone, $productName, $price = '', $imageUrl = '', $productUrl = '') {
    // Sanitize phone number (remove +, spaces, hyphens)
    $cleanPhone = preg_replace('/[^0-9]/', '', (string)$phone);
    if (str_starts_with($cleanPhone, '0')) {
        $cleanPhone = '62' . substr($cleanPhone, 1);
    }

    $message = "Halo Miniecake! ✨\n\n";
    $message .= "Saya tertarik dengan kue:\n";
    $message .= "*{$productName}*\n";
    if (!empty($price)) {
        $message .= "Harga: " . format_rupiah($price) . "\n";
    }
    if (!empty($imageUrl)) {
        $message .= "Foto Produk: {$imageUrl}\n";
    }
    if (!empty($productUrl)) {
        $message .= "Link Katalog: {$productUrl}\n";
    }
    $message .= "\nApakah produk ini ready? Mohon info detail pemesanannya ya. Terima kasih! 💕";

    return 'https://wa.me/' . $cleanPhone . '?text=' . urlencode($message);
}

/**
 * Format image URL: Converts Google Drive links/IDs to our streaming proxy api/image.php?id=...
 * to prevent Google hotlink blocks and 429 errors.
 */
function format_image_url($url) {
    if (empty($url)) {
        return 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=800&q=80';
    }

    $url = trim($url);

    // If it is already an api/image.php link, ensure full base_url
    if (str_contains($url, 'api/image.php')) {
        return str_starts_with($url, 'http') ? $url : base_url($url);
    }

    // Extract Google Drive File ID if present
    $drivePatterns = [
        '/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)([a-zA-Z0-9_-]+)/',
        '/lh3\.googleusercontent\.com\/d\/([a-zA-Z0-9_-]+)/',
        '/drive\.usercontent\.google\.com\/download\?id=([a-zA-Z0-9_-]+)/'
    ];

    foreach ($drivePatterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return base_url('api/image.php?id=' . $matches[1]);
        }
    }

    // If relative path
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//')) {
        return base_url(ltrim($url, '/'));
    }

    return $url;
}

/**
 * Extract Google Drive file ID from a URL, image proxy link, or bare ID
 */
function extract_drive_file_id($urlOrId) {
    if (empty($urlOrId)) {
        return null;
    }

    $trimmed = trim((string)$urlOrId);

    // 1. Check for api/image.php?id=XXXXX or ../api/image.php?id=XXXXX
    if (preg_match('/api\/image\.php\?id=([a-zA-Z0-9_-]+)/i', $trimmed, $matches)) {
        return $matches[1];
    }

    // 2. Check standard Google Drive URLs
    $patterns = [
        '/(?:drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)|lh3\.googleusercontent\.com\/d\/|drive\.usercontent\.google\.com\/download\?id=)([a-zA-Z0-9_-]+)/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $trimmed, $matches)) {
            return $matches[1];
        }
    }

    // 3. Raw Drive ID (alphanumeric with - and _, usually 25 to 50 chars)
    if (preg_match('/^[a-zA-Z0-9_-]{25,50}$/', $trimmed)) {
        return $trimmed;
    }

    return null;
}

/**
 * Delete a stored file from Google Drive (or local uploads directory)
 */
function delete_stored_file($urlOrId) {
    if (empty($urlOrId)) {
        return false;
    }

    $deleted = false;

    // 1. Delete from Google Drive if it contains a Drive File ID
    $driveId = extract_drive_file_id($urlOrId);
    if ($driveId) {
        try {
            require_once __DIR__ . '/../includes/GoogleClient.php';
            $client = new GoogleClient();
            if ($client->isDriveConfigured()) {
                $deleted = $client->deleteDriveFile($driveId);
            }
        } catch (Exception $e) {
            error_log("Failed deleting Drive file ({$driveId}): " . $e->getMessage());
        }
    }

    // 2. Delete local file if it resides in uploads/
    if (str_contains($urlOrId, 'uploads/')) {
        $cleanPath = parse_url($urlOrId, PHP_URL_PATH);
        $filename = basename($cleanPath);
        // Prevent path traversal
        $filename = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $filename);
        $localFile = __DIR__ . '/../uploads/' . $filename;
        if (file_exists($localFile) && is_file($localFile)) {
            @unlink($localFile);
            $deleted = true;
        }
    }

    return $deleted;
}

/**
 * Generate unique product ID
 */
function generate_product_id() {
    return 'PROD-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}
