<?php
/**
 * Settings & Content Repository for Miniecake
 * FULL GOOGLE SHEETS API IMPLEMENTATION (Vercel Serverless Ready)
 * Handles Categories, Contact/Socials, Logo, and Promo Banners directly in Google Sheets.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/GoogleClient.php';

class SettingsRepository {
    private $filePath;
    private $googleClient;
    private $useSheets = false;
    private $spreadsheetId;

    public function __construct() {
        $this->filePath = __DIR__ . '/../data/settings.json';
        $this->googleClient = new GoogleClient();
        $this->useSheets = $this->googleClient->isSheetsConfigured();
        $this->spreadsheetId = env('GOOGLE_SPREADSHEET_ID');
    }

    /**
     * Get all settings including categories and banners
     */
    public function getAll() {
        $defaults = [
            'app_name' => env('APP_NAME', 'miniecake'),
            'app_tagline' => env('APP_TAGLINE', 'Sweet Moments in Every Bite'),
            'logo_url' => 'assets/images/logo.svg',
            'whatsapp_number' => env('WHATSAPP_NUMBER', '6281234567890'),
            'instagram_url' => env('INSTAGRAM_URL', 'https://instagram.com/miniecake'),
            'facebook_url' => env('FACEBOOK_URL', 'https://facebook.com/miniecake'),
            'tiktok_url' => env('TIKTOK_URL', 'https://tiktok.com/@miniecake'),
            'address' => 'Jl. Melati No. 12, Jakarta Selatan',
            'opening_hours' => 'Setiap Hari 08:00 - 20:00 WIB',
            'categories' => ['Bento Cake', 'Whole Cake', 'Cupcakes', 'Dessert Box', 'Pastry'],
            'banners' => []
        ];

        if ($this->useSheets) {
            try {
                $sheetSettings = $this->getSettingsFromSheet();
                $merged = array_merge($defaults, $sheetSettings);
                $merged['logo_url'] = format_image_url($merged['logo_url'] ?? 'assets/images/logo.svg');
                $merged['categories'] = $this->getCategories();
                $merged['banners'] = $this->getBanners();
                return $merged;
            } catch (Exception $e) {
                error_log("Failed to load settings from Google Sheet: " . $e->getMessage());
            }
        }

        // Fallback to local JSON
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            $local = json_decode($content, true);
            if (is_array($local)) {
                return array_merge($defaults, $local);
            }
        }

        return $defaults;
    }

    public function get($key, $default = null) {
        $all = $this->getAll();
        return $all[$key] ?? $default;
    }

    /**
     * Read key-value settings from 'Settings' sheet tab
     */
    private function getSettingsFromSheet() {
        $range = rawurlencode('Settings!A1:B30');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
        $data = $this->googleClient->apiRequest($url);
        $rows = $data['values'] ?? [];

        $settings = [];
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (empty($row) || empty($row[0])) continue;
            $key = trim($row[0]);
            $val = trim($row[1] ?? '');
            $settings[$key] = $val;
        }

        return $settings;
    }

    /**
     * Update general settings in Google Sheet 'Settings' tab
     */
    public function updateGeneral($input) {
        $current = $this->getAll();
        $oldLogo = $current['logo_url'] ?? '';

        $keys = ['app_name', 'app_tagline', 'logo_url', 'whatsapp_number', 'instagram_url', 'facebook_url', 'tiktok_url', 'address', 'opening_hours'];
        foreach ($keys as $k) {
            if (isset($input[$k])) {
                $current[$k] = trim($input[$k]);
            }
        }

        // If logo was changed, remove old uploaded logo from storage if not default asset
        if (!empty($oldLogo) && isset($input['logo_url']) && $input['logo_url'] !== $oldLogo) {
            if (!str_contains($oldLogo, 'assets/images/logo.svg')) {
                delete_stored_file($oldLogo);
            }
        }

        if ($this->useSheets) {
            try {
                $rows = [["Key", "Value"]];
                foreach ($keys as $k) {
                    $rows[] = [$k, (string)($current[$k] ?? '')];
                }

                $range = rawurlencode('Settings!A1:B' . count($rows));
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED";
                $this->googleClient->apiRequest($url, 'PUT', ['values' => $rows]);
            } catch (Exception $e) {
                error_log("Failed to update Settings tab in Google Sheet: " . $e->getMessage());
            }
        }

        // Also save to local JSON for local dev
        @file_put_contents($this->filePath, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $current;
    }

    // ==========================================
    // CATEGORY MANAGEMENT (GOOGLE SHEET 'Categories')
    // ==========================================

    public function getCategories() {
        if ($this->useSheets) {
            try {
                $range = rawurlencode('Categories!A2:A100');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                $res = $this->googleClient->apiRequest($url);
                $rows = $res['values'] ?? [];
                $cats = [];
                foreach ($rows as $row) {
                    if (!empty($row[0]) && trim($row[0]) !== '') {
                        $cats[] = trim($row[0]);
                    }
                }
                if (!empty($cats)) {
                    return array_values(array_unique($cats));
                }
            } catch (Exception $e) {
                error_log("Error reading Categories from Sheet: " . $e->getMessage());
            }
        }

        // Fallback
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            if (!empty($data['categories']) && is_array($data['categories'])) {
                return $data['categories'];
            }
        }

        return ['Bento Cake', 'Whole Cake', 'Cupcakes', 'Dessert Box', 'Pastry'];
    }

    public function addCategory($name) {
        $name = trim($name);
        if (empty($name)) {
            throw new Exception("Nama kategori tidak boleh kosong.");
        }

        $cats = $this->getCategories();
        foreach ($cats as $c) {
            if (strcasecmp($c, $name) === 0) {
                return $cats; // Already exists
            }
        }

        if ($this->useSheets) {
            try {
                $range = rawurlencode('Categories!A:A');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";
                $this->googleClient->apiRequest($url, 'POST', ['values' => [[$name]]]);
            } catch (Exception $e) {
                error_log("Failed to append Category to Google Sheet: " . $e->getMessage());
            }
        }

        $cats[] = $name;
        $this->syncLocalCategories($cats);
        return $cats;
    }

    public function updateCategory($oldName, $newName) {
        $oldName = trim($oldName);
        $newName = trim($newName);
        if (empty($newName)) {
            throw new Exception("Nama kategori baru tidak boleh kosong.");
        }

        if ($this->useSheets) {
            try {
                $range = rawurlencode('Categories!A1:A100');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                $res = $this->googleClient->apiRequest($url);
                $rows = $res['values'] ?? [];

                $foundRow = null;
                foreach ($rows as $idx => $r) {
                    if (!empty($r[0]) && strcasecmp(trim($r[0]), $oldName) === 0) {
                        $foundRow = $idx + 1; // 1-based index
                        break;
                    }
                }

                if ($foundRow) {
                    $updateRange = rawurlencode("Categories!A{$foundRow}");
                    $updateUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$updateRange}?valueInputOption=USER_ENTERED";
                    $this->googleClient->apiRequest($updateUrl, 'PUT', ['values' => [[$newName]]]);
                }
            } catch (Exception $e) {
                error_log("Failed to update Category in Google Sheet: " . $e->getMessage());
            }
        }

        // Also update products with old category
        require_once __DIR__ . '/ProductRepository.php';
        $prodRepo = new ProductRepository();
        $products = $prodRepo->getAll();
        foreach ($products as $p) {
            if (strcasecmp($p['category'] ?? '', $oldName) === 0) {
                $prodRepo->update($p['id'], ['category' => $newName]);
            }
        }

        $cats = $this->getCategories();
        $this->syncLocalCategories($cats);
        return $cats;
    }

    public function deleteCategory($name) {
        $name = trim($name);

        if ($this->useSheets) {
            try {
                $range = rawurlencode('Categories!A1:A100');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                $res = $this->googleClient->apiRequest($url);
                $rows = $res['values'] ?? [];

                $foundRow = null;
                foreach ($rows as $idx => $r) {
                    if (!empty($r[0]) && strcasecmp(trim($r[0]), $name) === 0) {
                        $foundRow = $idx + 1;
                        break;
                    }
                }

                if ($foundRow) {
                    $this->googleClient->deleteSheetRow('Categories', $foundRow);
                }
            } catch (Exception $e) {
                error_log("Failed to delete Category from Google Sheet: " . $e->getMessage());
            }
        }

        $cats = array_values(array_filter($this->getCategories(), fn($c) => strcasecmp($c, $name) !== 0));
        $this->syncLocalCategories($cats);
        return $cats;
    }

    private function syncLocalCategories($cats) {
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true) ?: [];
            $data['categories'] = array_values(array_unique($cats));
            @file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    // ==========================================
    // BANNER MANAGEMENT (GOOGLE SHEET 'Banners')
    // ==========================================

    public function getBanners() {
        if ($this->useSheets) {
            try {
                $range = rawurlencode('Banners!A1:G50');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                $res = $this->googleClient->apiRequest($url);
                $rows = $res['values'] ?? [];

                $banners = [];
                for ($i = 1; $i < count($rows); $i++) {
                    $r = $rows[$i];
                    if (empty($r) || empty(trim($r[0] ?? ''))) continue;

                    $banners[] = [
                        'id' => trim($r[0] ?? ''),
                        'title' => trim($r[1] ?? ''),
                        'subtitle' => trim($r[2] ?? ''),
                        'image' => format_image_url(trim($r[3] ?? '')),
                        'link' => trim($r[4] ?? '#katalog'),
                        'button_text' => trim($r[5] ?? 'Lihat Detail'),
                        'active' => strtolower(trim($r[6] ?? 'true')) === 'true' || trim($r[6] ?? '') === '1'
                    ];
                }

                if (!empty($banners)) {
                    return $banners;
                }
            } catch (Exception $e) {
                error_log("Error reading Banners from Sheet: " . $e->getMessage());
            }
        }

        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            if (!empty($data['banners']) && is_array($data['banners'])) {
                return $data['banners'];
            }
        }

        return [];
    }

    public function saveBanner($bannerData) {
        $id = $bannerData['id'] ?? null;
        $isNew = empty($id);
        if ($isNew) {
            $id = 'BAN-' . strtoupper(substr(md5(uniqid()), 0, 6));
        }

        $title = trim($bannerData['title'] ?? '');
        $subtitle = trim($bannerData['subtitle'] ?? '');
        $image = trim($bannerData['image'] ?? '');
        $link = trim($bannerData['link'] ?? '#katalog');
        $buttonText = trim($bannerData['button_text'] ?? 'Lihat Menu Kue');
        $active = isset($bannerData['active']) ? ($bannerData['active'] ? 'true' : 'false') : 'true';

        // Check if updating an existing banner and replacing its image
        if (!$isNew) {
            $existingBanners = $this->getBanners();
            foreach ($existingBanners as $b) {
                if ($b['id'] === $id) {
                    $oldImg = $b['image'] ?? '';
                    if (!empty($oldImg) && !empty($image) && $oldImg !== $image) {
                        delete_stored_file($oldImg);
                    }
                    break;
                }
            }
        }

        $bannerRow = [$id, $title, $subtitle, $image, $link, $buttonText, $active];

        if ($this->useSheets) {
            try {
                if ($isNew) {
                    $range = rawurlencode('Banners!A:G');
                    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";
                    $this->googleClient->apiRequest($url, 'POST', ['values' => [$bannerRow]]);
                } else {
                    $range = rawurlencode('Banners!A1:A50');
                    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                    $res = $this->googleClient->apiRequest($url);
                    $rows = $res['values'] ?? [];

                    $foundRow = null;
                    foreach ($rows as $idx => $r) {
                        if (!empty($r[0]) && trim($r[0]) === $id) {
                            $foundRow = $idx + 1;
                            break;
                        }
                    }

                    if ($foundRow) {
                        $updateRange = rawurlencode("Banners!A{$foundRow}:G{$foundRow}");
                        $updateUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$updateRange}?valueInputOption=USER_ENTERED";
                        $this->googleClient->apiRequest($updateUrl, 'PUT', ['values' => [$bannerRow]]);
                    } else {
                        // Append if not found
                        $appendRange = rawurlencode('Banners!A:G');
                        $appendUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$appendRange}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";
                        $this->googleClient->apiRequest($appendUrl, 'POST', ['values' => [$bannerRow]]);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to save Banner in Google Sheet: " . $e->getMessage());
            }
        }

        $allBanners = $this->getBanners();
        $this->syncLocalBanners($allBanners);
        return $allBanners;
    }

    public function deleteBanner($id) {
        $id = trim($id);

        // Delete banner image from Drive or local storage
        $existingBanners = $this->getBanners();
        foreach ($existingBanners as $b) {
            if ($b['id'] === $id) {
                if (!empty($b['image'])) {
                    delete_stored_file($b['image']);
                }
                break;
            }
        }

        if ($this->useSheets) {
            try {
                $range = rawurlencode('Banners!A1:A50');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";
                $res = $this->googleClient->apiRequest($url);
                $rows = $res['values'] ?? [];

                $foundRow = null;
                foreach ($rows as $idx => $r) {
                    if (!empty($r[0]) && trim($r[0]) === $id) {
                        $foundRow = $idx + 1;
                        break;
                    }
                }

                if ($foundRow) {
                    $this->googleClient->deleteSheetRow('Banners', $foundRow);
                }
            } catch (Exception $e) {
                error_log("Failed to delete Banner from Google Sheet: " . $e->getMessage());
            }
        }

        $allBanners = array_values(array_filter($this->getBanners(), fn($b) => $b['id'] !== $id));
        $this->syncLocalBanners($allBanners);
        return $allBanners;
    }

    private function syncLocalBanners($banners) {
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true) ?: [];
            $data['banners'] = $banners;
            @file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }
}
