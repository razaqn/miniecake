<?php
/**
 * Image & Video Upload Endpoint for Miniecake
 * Handles upload of Photos, Videos & Logos to Google Drive API v3 with local storage fallback
 */

@ini_set('upload_max_filesize', '256M');
@ini_set('post_max_size', '260M');
@ini_set('memory_limit', '512M');
@ini_set('max_execution_time', '300');

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/GoogleClient.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Silakan login sebagai admin.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Hanya metode POST yang diizinkan.'
    ]);
    exit;
}

// Check uploaded file parameter (could be 'file', 'image', or 'video')
$fileField = null;
if (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $fileField = $_FILES['file'];
} elseif (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileField = $_FILES['image'];
} elseif (!empty($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
    $fileField = $_FILES['video'];
}

if (!$fileField) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Tidak ada file yang diunggah atau ukuran file melebihi batas upload server.'
    ]);
    exit;
}

$fileName = basename($fileField['name']);
$tmpPath = $fileField['tmp_name'];
$fileSize = $fileField['size'];
$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Check video type and raise limit to 250MB for video, 30MB for photos
$videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v', 'quicktime'];
$isVideoUpload = in_array($ext, $videoExts) || (!empty($_POST['type']) && $_POST['type'] === 'video') || isset($_FILES['video']);
$maxBytes = $isVideoUpload ? (250 * 1024 * 1024) : (30 * 1024 * 1024);
$maxLabel = $isVideoUpload ? '250MB' : '30MB';

if ($fileSize > $maxBytes) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => "Ukuran file melebihi batas maksimal {$maxLabel}."
    ]);
    exit;
}

// Detect MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

// Fallback for SVG or WebM if finfo returns text/plain or octet-stream
if ($ext === 'svg' && str_contains($mimeType, 'text')) {
    $mimeType = 'image/svg+xml';
} elseif ($ext === 'webm' && !str_starts_with($mimeType, 'video/')) {
    $mimeType = 'video/webm';
}

$allowedMimes = [
    // Images
    'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
    // Videos
    'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo'
];

if (!in_array($mimeType, $allowedMimes)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Format file tidak didukung (' . $mimeType . '). Format yang diizinkan: JPG, PNG, WEBP, SVG, GIF, MP4, WEBM.'
    ]);
    exit;
}

$isVideo = str_starts_with($mimeType, 'video/');
$isLogo = ($_POST['type'] ?? '') === 'logo' || str_contains(strtolower($fileName), 'logo');

$googleClient = new GoogleClient();

// If Drive API configured, upload to Google Drive
if ($googleClient->isDriveConfigured()) {
    try {
        $result = $googleClient->uploadImageToDrive($tmpPath, $fileName, $mimeType);
        echo json_encode([
            'success' => true,
            'message' => $isVideo ? 'Video berhasil diunggah ke Google Drive! 🎥' : 'File berhasil diunggah ke Google Drive! ☁️',
            'data' => [
                'storage' => 'google_drive',
                'is_video' => $isVideo,
                'url' => $result['direct_url'],
                'drive_url' => $result['view_url'],
                'file_id' => $result['file_id']
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Google Drive upload failed: " . $e->getMessage());
        // Fallback to local
    }
}

// Local Fallback Storage
$prefix = $isLogo ? 'logo_' : ($isVideo ? 'video_' : 'cake_');
$safeName = $prefix . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
$targetDir = __DIR__ . '/../uploads/';

if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}

$targetPath = $targetDir . $safeName;

if (@move_uploaded_file($tmpPath, $targetPath)) {
    $fileUrl = base_url('uploads/' . $safeName);
    echo json_encode([
        'success' => true,
        'message' => $isVideo ? 'Video tersimpan di folder lokal.' : 'File tersimpan secara lokal.',
        'data' => [
            'storage' => 'local',
            'is_video' => $isVideo,
            'url' => $fileUrl,
            'filename' => $safeName
        ]
    ]);
} else {
    http_response_code(500);
    $errorMsg = 'Gagal memindahkan file yang diunggah ke folder server.';
    if (!$googleClient->isDriveConfigured()) {
        $errorMsg = 'Penyimpanan lokal tidak tersedia di lingkungan serverless Vercel (read-only filesystem). Pastikan kredensial Google Drive sudah diatur di Environment Variables untuk upload foto/video.';
    }
    echo json_encode([
        'success' => false,
        'message' => $errorMsg
    ]);
}
