<?php
/**
 * Test Connection Endpoint
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/GoogleClient.php';

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$client = new GoogleClient();
$status = $client->testConnection();

echo json_encode([
    'success' => true,
    'data' => $status
]);

