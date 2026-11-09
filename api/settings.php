<?php
/**
 * REST API for Miniecake Settings, Categories, Contact & Banners
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/SettingsRepository.php';
require_once __DIR__ . '/../includes/Auth.php';

$settingsRepo = new SettingsRepository();
$method = $_SERVER['REQUEST_METHOD'];

function sendSettingsResponse($status, $data = [], $message = '') {
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
        $type = $_GET['type'] ?? 'all';

        if ($type === 'categories') {
            sendSettingsResponse(200, $settingsRepo->getCategories());
        } elseif ($type === 'banners') {
            $banners = $settingsRepo->getBanners();
            // If requested by public, only return active banners
            if (!Auth::check()) {
                $banners = array_values(array_filter($banners, fn($b) => !empty($b['active'])));
            }
            sendSettingsResponse(200, $banners);
        } elseif ($type === 'branches') {
            sendSettingsResponse(200, $settingsRepo->getBranches());
        } else {
            sendSettingsResponse(200, $settingsRepo->getAll());
        }
    }

    // Write actions require Admin Auth
    if (!Auth::check()) {
        sendSettingsResponse(401, null, 'Akses ditolak. Anda harus login sebagai admin.');
    }

    // 2. POST actions
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $action = $input['_action'] ?? $_GET['_action'] ?? '';

        // General settings (Logo & Contact info)
        if ($action === 'update_general') {
            $updated = $settingsRepo->updateGeneral($input);
            sendSettingsResponse(200, $updated, 'Pengaturan kontak & logo berhasil diperbarui.');
        }

        // Category Management
        elseif ($action === 'add_category') {
            $name = $input['name'] ?? '';
            $cats = $settingsRepo->addCategory($name);
            sendSettingsResponse(201, $cats, "Kategori '$name' berhasil ditambahkan.");
        } elseif ($action === 'update_category') {
            $oldName = $input['old_name'] ?? '';
            $newName = $input['new_name'] ?? '';
            $cats = $settingsRepo->updateCategory($oldName, $newName);
            sendSettingsResponse(200, $cats, "Kategori '$oldName' berhasil diubah menjadi '$newName'.");
        } elseif ($action === 'delete_category') {
            $name = $input['name'] ?? '';
            $cats = $settingsRepo->deleteCategory($name);
            sendSettingsResponse(200, $cats, "Kategori '$name' berhasil dihapus.");
        }

        // Banner Management
        elseif ($action === 'save_banner') {
            $banners = $settingsRepo->saveBanner($input);
            sendSettingsResponse(200, $banners, 'Banner promosi berhasil disimpan.');
        } elseif ($action === 'delete_banner') {
            $id = $input['id'] ?? '';
            $banners = $settingsRepo->deleteBanner($id);
            sendSettingsResponse(200, $banners, 'Banner berhasil dihapus.');
        }

        // Branch / Lokasi Cabang Management
        elseif ($action === 'save_branch') {
            $branches = $settingsRepo->saveBranch($input);
            sendSettingsResponse(200, $branches, 'Data cabang berhasil disimpan.');
        } elseif ($action === 'delete_branch') {
            $id = $input['id'] ?? '';
            $branches = $settingsRepo->deleteBranch($id);
            sendSettingsResponse(200, $branches, 'Cabang berhasil dihapus.');
        } else {
            sendSettingsResponse(400, null, 'Aksi tidak dikenal.');
        }
    }

    sendSettingsResponse(405, null, 'Metode HTTP tidak didukung.');
} catch (Exception $e) {
    sendSettingsResponse(500, null, 'Terjadi kesalahan: ' . $e->getMessage());
}

