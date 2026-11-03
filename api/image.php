<?php
/**
 * Miniecake - Google Drive Image Streamer & Cache Proxy
 * Solves Google Drive hotlinking blocks (429/403) and serves images reliably on Vercel & local
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/GoogleClient.php';

// Extract file ID from query parameter: ?id=XYZ or ?url=...
$fileId = trim($_GET['id'] ?? '');

if (empty($fileId) && !empty($_GET['url'])) {
    $url = trim($_GET['url']);
    if (preg_match('/(?:drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)|lh3\.googleusercontent\.com\/d\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        $fileId = $matches[1];
    }
}

if (empty($fileId)) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing Google Drive file ID';
    exit;
}

// Check ETag for 304 Not Modified
$etag = '"' . md5('drive_' . $fileId) . '"';
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

try {
    $client = new GoogleClient();
    $token = $client->getAccessToken();

    if (!$token) {
        // Fallback redirect to direct thumbnail if no token
        header("Location: https://drive.google.com/thumbnail?id={$fileId}&sz=w1200");
        exit;
    }

    // Stream image binary from Google Drive API v3
    $apiUrl = "https://www.googleapis.com/drive/v3/files/{$fileId}?alt=media";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Accept: */*'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200 && $data !== false) {
        // Default to image/jpeg if generic octet-stream
        if (!$contentType || str_contains($contentType, 'application/octet-stream') || str_contains($contentType, 'application/json')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($data);
            if ($detected && (str_starts_with($detected, 'image/') || str_starts_with($detected, 'video/'))) {
                $contentType = $detected;
            } else {
                $contentType = 'image/jpeg';
            }
        }

        // Cache headers (Browser cache 24h, CDN/Vercel Edge cache 7 days)
        header("Content-Type: {$contentType}");
        header('Cache-Control: public, max-age=86400, s-maxage=604800, stale-while-revalidate=86400');
        header('ETag: ' . $etag);
        header('Content-Length: ' . strlen($data));
        echo $data;
        exit;
    }

    // If API returned 404/403, fallback to public thumbnail
    header("Location: https://drive.google.com/thumbnail?id={$fileId}&sz=w1200");
    exit;

} catch (Exception $e) {
    header("Location: https://drive.google.com/thumbnail?id={$fileId}&sz=w1200");
    exit;
}

