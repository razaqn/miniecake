<?php
/**
 * REST API for Miniecake Products
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/ProductRepository.php';
require_once __DIR__ . '/../includes/Auth.php';

$repo = new ProductRepository();
$method = $_SERVER['REQUEST_METHOD'];

// Helper to send JSON responses
function sendResponse($status, $data = [], $message = '') {
    http_response_code($status);
    echo json_encode([
        'success' => $status >= 200 && $status < 300,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. GET requests (Public)
    if ($method === 'GET') {
        $id = $_GET['id'] ?? null;

        if ($id) {
            $product = $repo->getById($id);
            if (!$product) {
                sendResponse(404, null, 'Produk tidak ditemukan');
            }
            sendResponse(200, $product, 'Detail produk berhasil diambil');
        }

        // List & filter
        $products = $repo->getAll();
        $category = $_GET['category'] ?? 'all';
        $query = strtolower(trim($_GET['q'] ?? ''));

        if ($category !== 'all' && !empty($category)) {
            $products = array_values(array_filter($products, function($p) use ($category) {
                return strcasecmp($p['category'], $category) === 0;
            }));
        }

        if (!empty($query)) {
            $products = array_values(array_filter($products, function($p) use ($query) {
                $nameMatch = str_contains(strtolower($p['name'] ?? ''), $query);
                $descMatch = str_contains(strtolower($p['short_desc'] ?? ''), $query);
                $fullMatch = str_contains(strtolower($p['full_desc'] ?? ''), $query);
                $catMatch = str_contains(strtolower($p['category'] ?? ''), $query);
                return $nameMatch || $descMatch || $fullMatch || $catMatch;
            }));
        }

        sendResponse(200, [
            'products' => $products,
            'categories' => $repo->getCategories(),
            'total' => count($products),
            'source_info' => $repo->getSourceStatus()
        ], 'Daftar produk berhasil dimuat');
    }

    // Write actions require Admin Auth
    if (!Auth::check()) {
        sendResponse(401, null, 'Akses ditolak. Anda harus login sebagai admin.');
    }

    // 2. CREATE or UPDATE via POST
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['_action'] ?? $_GET['_action'] ?? 'create';

        if ($action === 'create') {
            if (empty($input['name'])) {
                sendResponse(422, null, 'Nama produk wajib diisi.');
            }
            $created = $repo->create($input);
            sendResponse(201, $created, 'Produk berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                sendResponse(422, null, 'ID produk wajib disertakan untuk update.');
            }
            $updated = $repo->update($id, $input);
            sendResponse(200, $updated, 'Produk berhasil diperbarui.');
        } elseif ($action === 'delete') {
            $id = $input['id'] ?? $_GET['id'] ?? null;
            if (!$id) {
                sendResponse(422, null, 'ID produk wajib disertakan untuk hapus.');
            }
            $repo->delete($id);
            sendResponse(200, ['id' => $id], 'Produk berhasil dihapus.');
        } elseif ($action === 'sync_sheet') {
            $count = $repo->syncAllToSheet();
            sendResponse(200, ['synced' => $count], "Berhasil mengekspor {$count} produk ke Google Sheet.");
        } else {
            sendResponse(400, null, 'Aksi tidak dikenal.');
        }
    }

    // 3. PUT requests
    if ($method === 'PUT') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(422, null, 'ID produk wajib disertakan.');
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $updated = $repo->update($id, $input);
        sendResponse(200, $updated, 'Produk berhasil diperbarui.');
    }

    // 4. DELETE requests
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            sendResponse(422, null, 'ID produk wajib disertakan.');
        }
        $repo->delete($id);
        sendResponse(200, ['id' => $id], 'Produk berhasil dihapus.');
    }

    sendResponse(405, null, 'Metode HTTP tidak didukung.');

} catch (Exception $e) {
    sendResponse(500, null, 'Terjadi kesalahan server: ' . $e->getMessage());
}

