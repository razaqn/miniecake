<?php
/**
 * Product Repository for Miniecake
 * Handles data from Google Sheets API v4 with seamless Local JSON fallback.
 * Includes support for multiple photos and video.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/GoogleClient.php';
require_once __DIR__ . '/SettingsRepository.php';

class ProductRepository {
    private $googleClient;
    private $jsonFilePath;
    private $useSheets = false;
    private $settingsRepo;
    private $sheetName;

    public function __construct() {
        $this->googleClient = new GoogleClient();
        $this->jsonFilePath = __DIR__ . '/../data/products.json';
        $this->useSheets = $this->googleClient->isSheetsConfigured();
        $this->settingsRepo = new SettingsRepository();
        $this->sheetName = env('GOOGLE_SPREADSHEET_SHEET_NAME', 'Products');
    }

    /**
     * Check if currently using Google Sheets
     */
    public function isUsingGoogleSheets() {
        return $this->useSheets;
    }

    /**
     * Get data source name for display in Admin
     */
    public function getSourceStatus() {
        if ($this->useSheets) {
            try {
                $rows = $this->googleClient->getSheetRows();
                return [
                    'source' => 'Google Sheets',
                    'active' => true,
                    'message' => 'Terhubung ke Google Spreadsheet (' . count($rows) . ' baris)'
                ];
            } catch (Exception $e) {
                return [
                    'source' => 'Google Sheets (Error)',
                    'active' => false,
                    'message' => 'Gagal terhubung: ' . $e->getMessage() . '. Menggunakan fallback JSON lokal.'
                ];
            }
        }

        return [
            'source' => 'Penyimpanan Lokal (JSON)',
            'active' => true,
            'message' => 'Token Google Sheets di .env masih kosong. Menggunakan penyimpanan data lokal.'
        ];
    }

    /**
     * Get all products
     */
    public function getAll() {
        if ($this->useSheets) {
            try {
                $rows = $this->googleClient->getSheetRows();
                if (!empty($rows)) {
                    return $this->parseSheetRows($rows);
                }
            } catch (Exception $e) {
                error_log("Fallback to local JSON due to Sheet error: " . $e->getMessage());
            }
        }

        return $this->getLocalJsonProducts();
    }

    /**
     * Find single product by ID
     */
    public function getById($id) {
        $products = $this->getAll();
        foreach ($products as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }
        return null;
    }

    /**
     * Create a new product
     */
    public function create($data) {
        $id = !empty($data['id']) ? $data['id'] : generate_product_id();
        $now = date('Y-m-d H:i:s');

        $images = is_array($data['images'] ?? null) ? $data['images'] : [];
        if (is_string($data['images'] ?? null)) {
            $images = array_filter(array_map('trim', explode(',', $data['images'])));
        }

        $video = trim($data['video'] ?? '');

        $newProduct = [
            'id' => $id,
            'name' => trim($data['name'] ?? 'Kue Tanpa Nama'),
            'category' => trim($data['category'] ?? 'General'),
            'price' => (float)($data['price'] ?? 0),
            'status' => trim($data['status'] ?? 'Ready Stock'),
            'short_desc' => trim($data['short_desc'] ?? ''),
            'full_desc' => trim($data['full_desc'] ?? ''),
            'images' => array_values($images),
            'video' => $video,
            'created_at' => $now,
            'updated_at' => $now
        ];

        // Ensure category is registered in settings
        if (!empty($newProduct['category'])) {
            try {
                $this->settingsRepo->addCategory($newProduct['category']);
            } catch (Exception $e) {
                // Category already registered
            }
        }

        // If Google Sheets is configured, append to Sheet
        if ($this->useSheets) {
            try {
                $this->googleClient->initializeSheetHeader();
                $row = [
                    $newProduct['id'],
                    $newProduct['name'],
                    $newProduct['category'],
                    $newProduct['price'],
                    $newProduct['status'],
                    $newProduct['short_desc'],
                    $newProduct['full_desc'],
                    json_encode($newProduct['images']),
                    $newProduct['created_at'],
                    $newProduct['updated_at'],
                    $newProduct['video']
                ];
                $this->googleClient->appendSheetRow($row);
            } catch (Exception $e) {
                error_log("Failed to append to Google Sheet: " . $e->getMessage());
                $this->saveToLocalJson($newProduct, 'create');
                return $newProduct;
            }
        }

        $this->saveToLocalJson($newProduct, 'create');
        return $newProduct;
    }

    /**
     * Update an existing product
     */
    public function update($id, $data) {
        $now = date('Y-m-d H:i:s');
        $existing = $this->getById($id);
        if (!$existing) {
            throw new Exception("Produk dengan ID $id tidak ditemukan.");
        }

        $images = $existing['images'];
        if (isset($data['images'])) {
            if (is_array($data['images'])) {
                $images = $data['images'];
            } elseif (is_string($data['images'])) {
                $images = array_filter(array_map('trim', explode(',', $data['images'])));
            }
        }

        $video = isset($data['video']) ? trim($data['video']) : ($existing['video'] ?? '');

        $updatedProduct = [
            'id' => $id,
            'name' => trim($data['name'] ?? $existing['name']),
            'category' => trim($data['category'] ?? $existing['category']),
            'price' => isset($data['price']) ? (float)$data['price'] : $existing['price'],
            'status' => trim($data['status'] ?? $existing['status']),
            'short_desc' => trim($data['short_desc'] ?? $existing['short_desc']),
            'full_desc' => trim($data['full_desc'] ?? $existing['full_desc']),
            'images' => array_values($images),
            'video' => $video,
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now
        ];

        // Identify removed images and delete them from Google Drive or local storage
        if (!empty($existing['images']) && is_array($existing['images'])) {
            $oldDriveIds = [];
            foreach ($existing['images'] as $oldImg) {
                $fid = extract_drive_file_id($oldImg);
                if ($fid) $oldDriveIds[$fid] = $oldImg;
            }

            $newDriveIds = [];
            foreach ($updatedProduct['images'] as $newImg) {
                $fid = extract_drive_file_id($newImg);
                if ($fid) $newDriveIds[$fid] = $newImg;
            }

            // Delete Drive files that were removed
            foreach ($oldDriveIds as $fid => $oldImg) {
                if (!isset($newDriveIds[$fid])) {
                    delete_stored_file($oldImg);
                }
            }

            // Delete local uploads that were removed
            foreach ($existing['images'] as $oldImg) {
                if (str_contains($oldImg, 'uploads/') && !in_array($oldImg, $updatedProduct['images'])) {
                    delete_stored_file($oldImg);
                }
            }
        }

        // Delete previous video if removed or replaced
        $oldVid = $existing['video'] ?? '';
        $newVid = $updatedProduct['video'] ?? '';
        if (!empty($oldVid) && $oldVid !== $newVid) {
            $oldVidId = extract_drive_file_id($oldVid);
            $newVidId = extract_drive_file_id($newVid);
            if ($oldVidId && $oldVidId !== $newVidId) {
                delete_stored_file($oldVid);
            } elseif (str_contains($oldVid, 'uploads/')) {
                delete_stored_file($oldVid);
            }
        }

        // Ensure category is registered in settings
        if (!empty($updatedProduct['category'])) {
            try {
                $this->settingsRepo->addCategory($updatedProduct['category']);
            } catch (Exception $e) {
                // Category already registered
            }
        }

        if ($this->useSheets) {
            try {
                $this->googleClient->initializeSheetHeader();
                $rows = $this->googleClient->getSheetRows();
                $foundRowIndex = null;
                foreach ($rows as $index => $row) {
                    if (isset($row[0]) && trim($row[0]) === $id) {
                        $foundRowIndex = $index + 1;
                        break;
                    }
                }

                if ($foundRowIndex) {
                    $sheetRow = [
                        $updatedProduct['id'],
                        $updatedProduct['name'],
                        $updatedProduct['category'],
                        $updatedProduct['price'],
                        $updatedProduct['status'],
                        $updatedProduct['short_desc'],
                        $updatedProduct['full_desc'],
                        json_encode($updatedProduct['images']),
                        $updatedProduct['created_at'],
                        $updatedProduct['updated_at'],
                        $updatedProduct['video']
                    ];
                    $this->googleClient->updateSheetRow($foundRowIndex, $sheetRow);
                }
            } catch (Exception $e) {
                error_log("Failed to update Google Sheet: " . $e->getMessage());
            }
        }

        $this->saveToLocalJson($updatedProduct, 'update');
        return $updatedProduct;
    }

    /**
     * Delete a product
     */
    public function delete($id) {
        // Delete all images and video associated with the product from Drive or storage
        $existing = $this->getById($id);
        if ($existing) {
            if (!empty($existing['images']) && is_array($existing['images'])) {
                foreach ($existing['images'] as $img) {
                    delete_stored_file($img);
                }
            }
            if (!empty($existing['video'])) {
                delete_stored_file($existing['video']);
            }
        }

        if ($this->useSheets) {
            try {
                $rows = $this->googleClient->getSheetRows();
                $foundRowIndex = null;
                foreach ($rows as $index => $row) {
                    if (isset($row[0]) && trim($row[0]) === $id) {
                        $foundRowIndex = $index + 1;
                        break;
                    }
                }
                if ($foundRowIndex) {
                    $this->googleClient->deleteSheetRow($this->sheetName, $foundRowIndex);
                }
            } catch (Exception $e) {
                error_log("Failed to delete Google Sheet row: " . $e->getMessage());
            }
        }

        $this->saveToLocalJson(['id' => $id], 'delete');
        return true;
    }

    /**
     * Get distinct categories (merging settings with product categories)
     */
    public function getCategories() {
        $settingCats = $this->settingsRepo->getCategories();
        $products = $this->getAll();
        $categories = $settingCats;

        foreach ($products as $p) {
            if (!empty($p['category']) && !in_array($p['category'], $categories)) {
                $categories[] = $p['category'];
            }
        }
        sort($categories);
        return array_values(array_unique($categories));
    }

    /**
     * Parse rows from Google Sheets dynamically by column header
     */
    private function parseSheetRows($rows) {
        if (empty($rows)) {
            return [];
        }

        $header = array_map('strtolower', array_map('trim', $rows[0] ?? []));

        $idIdx = array_search('id', $header) !== false ? array_search('id', $header) : 0;
        $nameIdx = array_search('name', $header) !== false ? array_search('name', $header) : 1;
        $catIdx = array_search('category', $header) !== false ? array_search('category', $header) : 2;
        $priceIdx = array_search('price', $header) !== false ? array_search('price', $header) : 3;
        $statusIdx = array_search('status', $header) !== false ? array_search('status', $header) : 4;
        $shortIdx = array_search('short_desc', $header) !== false ? array_search('short_desc', $header) : 5;
        $fullIdx = array_search('full_desc', $header) !== false ? array_search('full_desc', $header) : 6;
        $imgIdx = array_search('images', $header) !== false ? array_search('images', $header) : 7;
        $createdIdx = array_search('created_at', $header) !== false ? array_search('created_at', $header) : 8;
        $updatedIdx = array_search('updated_at', $header) !== false ? array_search('updated_at', $header) : 9;
        $videoIdx = array_search('video', $header);

        $products = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || empty(trim($row[$idIdx] ?? ''))) {
                continue;
            }

            $id = trim($row[$idIdx] ?? '');
            $name = trim($row[$nameIdx] ?? 'Untitled');
            $category = trim($row[$catIdx] ?? 'General');
            $price = (float)str_replace(['Rp', '.', ' ', ','], ['', '', '', '.'], $row[$priceIdx] ?? 0);
            $status = trim($row[$statusIdx] ?? 'Ready Stock');
            $shortDesc = trim($row[$shortIdx] ?? '');
            $fullDesc = trim($row[$fullIdx] ?? '');
            $rawImages = trim($row[$imgIdx] ?? '');
            $createdAt = trim($row[$createdIdx] ?? date('Y-m-d H:i:s'));
            $updatedAt = trim($row[$updatedIdx] ?? date('Y-m-d H:i:s'));
            $video = ($videoIdx !== false) ? trim($row[$videoIdx] ?? '') : '';

            $images = [];
            if (!empty($rawImages)) {
                $decoded = json_decode($rawImages, true);
                if (is_array($decoded)) {
                    $images = $decoded;
                } else {
                    $images = array_filter(array_map('trim', explode(',', $rawImages)));
                }
            }

            if (empty($images)) {
                $images = ['https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=800&q=80'];
            }

            $formattedImages = array_map('format_image_url', $images);
            $formattedVideo = !empty($video) ? format_image_url($video) : '';

            $products[] = [
                'id' => $id,
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'status' => $status,
                'short_desc' => $shortDesc,
                'full_desc' => $fullDesc,
                'images' => array_values($formattedImages),
                'video' => $formattedVideo,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt
            ];
        }

        return $products;
    }

    /**
     * Read local JSON products
     */
    private function getLocalJsonProducts() {
        if (!file_exists($this->jsonFilePath)) {
            return [];
        }
        $content = file_get_contents($this->jsonFilePath);
        $products = json_decode($content, true);
        return is_array($products) ? $products : [];
    }

    /**
     * Sync mutations with local JSON
     */
    private function saveToLocalJson($product, $action) {
        $products = $this->getLocalJsonProducts();

        if ($action === 'create') {
            array_unshift($products, $product);
        } elseif ($action === 'update') {
            foreach ($products as $key => $p) {
                if ($p['id'] === $product['id']) {
                    $products[$key] = array_merge($p, $product);
                    break;
                }
            }
        } elseif ($action === 'delete') {
            $products = array_values(array_filter($products, function($p) use ($product) {
                return $p['id'] !== $product['id'];
            }));
        }

        file_put_contents($this->jsonFilePath, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Push all local products into Google Sheet
     */
    public function syncAllToSheet() {
        if (!$this->useSheets) {
            throw new Exception("Google Sheets belum terkonfigurasi di .env.");
        }

        $headers = ['ID', 'Name', 'Category', 'Price', 'Status', 'Short_Desc', 'Full_Desc', 'Images', 'Created_At', 'Updated_At', 'Video'];
        $range = rawurlencode(env('GOOGLE_SPREADSHEET_SHEET_NAME', 'Products') . '!A1:K1');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/" . env('GOOGLE_SPREADSHEET_ID') . "/values/{$range}?valueInputOption=USER_ENTERED";
        $this->googleClient->apiRequest($url, 'PUT', ['values' => [$headers]]);

        $products = $this->getLocalJsonProducts();

        foreach ($products as $p) {
            $row = [
                $p['id'],
                $p['name'],
                $p['category'],
                $p['price'],
                $p['status'],
                $p['short_desc'],
                $p['full_desc'],
                json_encode($p['images']),
                $p['created_at'],
                $p['updated_at'],
                $p['video'] ?? ''
            ];
            $this->googleClient->appendSheetRow($row);
        }

        return count($products);
    }
}
