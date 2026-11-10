<?php
/**
 * Google Client for Sheets API v4 and Drive API v3
 * Uses OAuth2 User Credentials (No Service Account required)
 */

require_once __DIR__ . '/../config/app.php';

class GoogleClient {
    private static $inMemoryToken = null;
    private static $inMemoryExpiresAt = 0;

    private $clientId;
    private $clientSecret;
    private $refreshToken;
    private $directAccessToken;
    private $spreadsheetId;
    private $sheetName;
    private $driveFolderId;
    private $tokenCacheFile;
    private $sheetIds = [];
    private $lastOAuthError = null;

    public function __construct() {
        $this->clientId = env('GOOGLE_CLIENT_ID');
        $this->clientSecret = env('GOOGLE_CLIENT_SECRET');
        $this->refreshToken = env('GOOGLE_REFRESH_TOKEN');
        $this->directAccessToken = env('GOOGLE_ACCESS_TOKEN');
        $this->spreadsheetId = env('GOOGLE_SPREADSHEET_ID');
        $this->sheetName = env('GOOGLE_SPREADSHEET_SHEET_NAME', 'Products');
        $this->driveFolderId = env('GOOGLE_DRIVE_FOLDER_ID');

        // On serverless environments (like Vercel), the project directory is read-only.
        // Fallback to sys_get_temp_dir() (/tmp) when data/ directory is not writable.
        $dataDir = __DIR__ . '/../data';
        if (is_dir($dataDir) && is_writable($dataDir)) {
            $this->tokenCacheFile = $dataDir . '/google_token_cache.json';
        } else {
            $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
            $cacheKey = md5(($this->clientId ?? '') . ($this->refreshToken ?? 'miniecake'));
            $this->tokenCacheFile = $tempDir . '/google_token_cache_' . $cacheKey . '.json';
        }
    }

    /**
     * Check if Google Sheets API credentials are set
     */
    public function isSheetsConfigured() {
        $hasSpreadsheet = !empty($this->spreadsheetId);
        $hasAuth = !empty($this->directAccessToken) || (!empty($this->clientId) && !empty($this->clientSecret) && !empty($this->refreshToken));
        return $hasSpreadsheet && $hasAuth;
    }

    /**
     * Check if Google Drive API credentials are set
     */
    public function isDriveConfigured() {
        $hasAuth = !empty($this->directAccessToken) || (!empty($this->clientId) && !empty($this->clientSecret) && !empty($this->refreshToken));
        return $hasAuth;
    }

    /**
     * Get valid Access Token (either direct token or refreshed via refresh_token)
     */
    public function getAccessToken() {
        // 1. Check in-memory static cache first
        if (self::$inMemoryToken !== null && self::$inMemoryExpiresAt > time() + 60) {
            return self::$inMemoryToken;
        }

        // 2. Check cached token on filesystem (/tmp or data/)
        if ($this->tokenCacheFile && @file_exists($this->tokenCacheFile)) {
            $content = @file_get_contents($this->tokenCacheFile);
            if ($content) {
                $cache = json_decode($content, true);
                if (!empty($cache['access_token']) && !empty($cache['expires_at'])) {
                    if ($cache['expires_at'] > time() + 60) {
                        self::$inMemoryToken = $cache['access_token'];
                        self::$inMemoryExpiresAt = (int)$cache['expires_at'];
                        return $cache['access_token'];
                    }
                }
            }
        }

        // 3. If we have refresh token + client creds, refresh it
        if (!empty($this->refreshToken) && !empty($this->clientId) && !empty($this->clientSecret)) {
            $newToken = $this->refreshOAuthToken();
            if ($newToken) {
                return $newToken;
            }
            if ($this->lastOAuthError) {
                throw new Exception($this->lastOAuthError);
            }
        }

        // 4. Fallback to direct access token if provided and no refresh token defined
        if (!empty($this->directAccessToken) && empty($this->refreshToken)) {
            return $this->directAccessToken;
        }

        throw new Exception("Google Access Token tidak tersedia. Silakan lengkapi GOOGLE_REFRESH_TOKEN di .env / Vercel.");
    }

    /**
     * Refresh OAuth2 access token
     */
    private function refreshOAuthToken() {
        $this->lastOAuthError = null;
        $url = 'https://oauth2.googleapis.com/token';
        $postData = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->lastOAuthError = "Gagal menghubungi server Google OAuth: {$curlError}";
            error_log($this->lastOAuthError);
            return null;
        }

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['access_token'])) {
                $expiresIn = $data['expires_in'] ?? 3500;
                $expiresAt = time() + $expiresIn;

                self::$inMemoryToken = $data['access_token'];
                self::$inMemoryExpiresAt = $expiresAt;

                $cacheData = [
                    'access_token' => $data['access_token'],
                    'expires_at' => $expiresAt
                ];
                if ($this->tokenCacheFile) {
                    @file_put_contents($this->tokenCacheFile, json_encode($cacheData));
                }
                return $data['access_token'];
            }
        }

        if ($response) {
            $data = json_decode($response, true);
            if (!empty($data['error'])) {
                $err = $data['error'];
                $errDesc = $data['error_description'] ?? '';
                if ($err === 'invalid_grant') {
                    $this->lastOAuthError = "GOOGLE_REFRESH_TOKEN telah kedaluwarsa atau dicabut ('invalid_grant: {$errDesc}'). Google otomatis membatalkan token tiap 7 hari jika status OAuth Consent Screen masih 'Testing'. Silakan buat Refresh Token baru di OAuth Playground dan ubah status Publishing ke 'In Production'.";
                } elseif ($err === 'invalid_client') {
                    $this->lastOAuthError = "GOOGLE_CLIENT_ID atau GOOGLE_CLIENT_SECRET tidak valid ('invalid_client: {$errDesc}').";
                } else {
                    $this->lastOAuthError = "Google OAuth error: {$err} ({$errDesc})";
                }
                error_log($this->lastOAuthError);
            }
        }

        return null;
    }

    /**
     * Helper for authorized Google API requests
     */
    public function apiRequest($url, $method = 'GET', $body = null, $extraHeaders = []) {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new Exception("Google Access Token tidak tersedia. Silakan lengkapi .env");
        }

        $headers = array_merge([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ], $extraHeaders);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                $payload = (is_array($body) || is_object($body)) ? json_encode($body) : $body;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($body !== null) {
                $payload = (is_array($body) || is_object($body)) ? json_encode($body) : $body;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error: " . $error);
        }

        // HTTP 204 No Content (standard for DELETE)
        if ($httpCode === 204 || empty($response)) {
            return [];
        }

        $json = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = $json['error']['message'] ?? "Google API Error (HTTP $httpCode): " . $response;
            throw new Exception($msg);
        }

        return $json;
    }

    // ==========================================
    // GOOGLE SHEETS API V4 METHODS
    // ==========================================

    /**
     * Fetch all rows from Google Sheet (Columns A to K)
     */
    public function getSheetRows() {
        if (!$this->isSheetsConfigured()) {
            return [];
        }

        $range = rawurlencode($this->sheetName . '!A1:K');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}";

        try {
            $data = $this->apiRequest($url);
            return $data['values'] ?? [];
        } catch (Exception $e) {
            error_log("Error reading Google Sheet: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Append a row to the Google Sheet (Columns A:K)
     */
    public function appendSheetRow($rowValues) {
        $range = rawurlencode($this->sheetName . '!A:K');
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS";

        $body = [
            'values' => [$rowValues]
        ];

        return $this->apiRequest($url, 'POST', $body);
    }

    /**
     * Update an entire row in Google Sheet by 1-based row number (Columns A to K)
     */
    public function updateSheetRow($rowIndex, $rowValues) {
        $range = rawurlencode($this->sheetName . "!A{$rowIndex}:K{$rowIndex}");
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED";

        $body = [
            'values' => [$rowValues]
        ];

        return $this->apiRequest($url, 'PUT', $body);
    }

    /**
     * Get numeric sheetId (gid) by sheet tab title
     */
    public function getSheetIdByTitle($sheetTitle) {
        if (isset($this->sheetIds[$sheetTitle])) {
            return $this->sheetIds[$sheetTitle];
        }

        if (!$this->isSheetsConfigured()) {
            return null;
        }

        try {
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}?fields=sheets.properties";
            $data = $this->apiRequest($url);
            if (!empty($data['sheets'])) {
                foreach ($data['sheets'] as $sheet) {
                    $title = $sheet['properties']['title'] ?? '';
                    $id = $sheet['properties']['sheetId'] ?? 0;
                    $this->sheetIds[$title] = $id;
                }
            }
        } catch (Exception $e) {
            error_log("Failed to fetch sheet properties: " . $e->getMessage());
        }

        return $this->sheetIds[$sheetTitle] ?? null;
    }

    /**
     * Permanently delete a row from a Google Sheet tab by 1-based row index
     * Uses deleteDimension to physically remove the row, with fallback to :clear
     */
    public function deleteSheetRow($sheetTitle, $rowIndex) {
        if (!$this->isSheetsConfigured() || $rowIndex < 1) {
            return false;
        }

        $sheetId = $this->getSheetIdByTitle($sheetTitle);
        if ($sheetId !== null) {
            $batchUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate";
            $body = [
                'requests' => [
                    [
                        'deleteDimension' => [
                            'range' => [
                                'sheetId' => (int)$sheetId,
                                'dimension' => 'ROWS',
                                'startIndex' => $rowIndex - 1,
                                'endIndex' => $rowIndex
                            ]
                        ]
                    ]
                ]
            ];

            try {
                return $this->apiRequest($batchUrl, 'POST', $body);
            } catch (Exception $e) {
                error_log("deleteDimension failed for {$sheetTitle} row {$rowIndex}: " . $e->getMessage());
            }
        }

        // Fallback: Clear cells using "{}" object
        $range = rawurlencode("{$sheetTitle}!A{$rowIndex}:Z{$rowIndex}");
        $clearUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}:clear";
        return $this->apiRequest($clearUrl, 'POST', "{}");
    }

    /**
     * Clear / Delete a row in Google Sheet by 1-based row number
     */
    public function clearSheetRow($rowIndex, $sheetName = null) {
        $targetSheet = $sheetName ?: $this->sheetName;
        return $this->deleteSheetRow($targetSheet, $rowIndex);
    }

    /**
     * Ensure a sheet tab exists, if not create it with initial headers
     */
    public function ensureSheetExists($sheetTitle, $headers = []) {
        if (!$this->isSheetsConfigured()) {
            return false;
        }

        $sheetId = $this->getSheetIdByTitle($sheetTitle);
        if ($sheetId !== null) {
            return true;
        }

        try {
            $batchUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate";
            $body = [
                'requests' => [
                    [
                        'addSheet' => [
                            'properties' => [
                                'title' => $sheetTitle
                            ]
                        ]
                    ]
                ]
            ];
            $res = $this->apiRequest($batchUrl, 'POST', $body);
            if (!empty($res['replies'][0]['addSheet']['properties']['sheetId'])) {
                $this->sheetIds[$sheetTitle] = $res['replies'][0]['addSheet']['properties']['sheetId'];
            }

            if (!empty($headers)) {
                $range = rawurlencode($sheetTitle . '!A1');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED";
                $this->apiRequest($url, 'PUT', ['values' => [$headers]]);
            }
            return true;
        } catch (Exception $e) {
            error_log("Failed to create sheet tab '{$sheetTitle}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize Sheet with Header if empty or ensure Video column exists
     */
    public function initializeSheetHeader() {
        $rows = $this->getSheetRows();
        if (empty($rows)) {
            $headers = ['ID', 'Name', 'Category', 'Price', 'Status', 'Short_Desc', 'Full_Desc', 'Images', 'Created_At', 'Updated_At', 'Video'];
            $range = rawurlencode($this->sheetName . '!A1:K1');
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED";
            $this->apiRequest($url, 'PUT', ['values' => [$headers]]);
        } else {
            $header = array_map('strtolower', array_map('trim', $rows[0]));
            if (!in_array('video', $header)) {
                $range = rawurlencode($this->sheetName . '!K1');
                $url = "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/{$range}?valueInputOption=USER_ENTERED";
                try {
                    $this->apiRequest($url, 'PUT', ['values' => [['Video']]]);
                } catch (Exception $e) {
                    error_log("Could not update Video header: " . $e->getMessage());
                }
            }
        }
    }

    // ==========================================
    // GOOGLE DRIVE API V3 METHODS
    // ==========================================

    /**
     * Upload an image or video file directly to Google Drive
     */
    public function uploadImageToDrive($localFilePath, $originalFileName, $mimeType = 'image/jpeg') {
        $token = $this->getAccessToken();
        if (!$token) {
            throw new Exception("Google Access Token tidak tersedia untuk upload ke Drive.");
        }

        $prefix = str_starts_with($mimeType, 'video/') ? 'miniecake_vid_' : 'miniecake_';
        $metadata = [
            'name' => $prefix . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalFileName),
            'mimeType' => $mimeType
        ];

        if (!empty($this->driveFolderId)) {
            $metadata['parents'] = [$this->driveFolderId];
        }

        $boundary = '-------314159265358979323846';
        $delimiter = "\r\n--" . $boundary . "\r\n";
        $closeDelimiter = "\r\n--" . $boundary . "--";

        $fileData = file_get_contents($localFilePath);
        if ($fileData === false) {
            throw new Exception("Gagal membaca file lokal untuk upload.");
        }

        $body = $delimiter
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($metadata)
            . $delimiter
            . "Content-Type: " . $mimeType . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($fileData))
            . $closeDelimiter;

        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink,webContentLink';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("Drive Upload cURL error: " . $error);
        }

        $result = json_decode($response, true);
        if ($httpCode >= 400 || empty($result['id'])) {
            $msg = $result['error']['message'] ?? ("HTTP " . $httpCode . ": " . $response);
            throw new Exception("Google Drive upload error: " . $msg);
        }

        $fileId = $result['id'];
        $this->makeDriveFilePublic($fileId);

        $directUrl = base_url("api/image.php?id={$fileId}");
        $driveViewUrl = "https://drive.google.com/file/d/{$fileId}/view";

        return [
            'file_id' => $fileId,
            'name' => $result['name'] ?? $originalFileName,
            'direct_url' => $directUrl,
            'view_url' => $driveViewUrl
        ];
    }

    /**
     * Set file permission to public reader
     */
    public function makeDriveFilePublic($fileId) {
        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}/permissions";
        $body = [
            'role' => 'reader',
            'type' => 'anyone'
        ];

        try {
            $this->apiRequest($url, 'POST', $body);
            return true;
        } catch (Exception $e) {
            error_log("Warning setting Drive permissions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete an image or video file permanently from Google Drive
     */
    public function deleteDriveFile($fileIdOrUrl) {
        $fileId = extract_drive_file_id($fileIdOrUrl);
        if (!$fileId) {
            return false;
        }

        if (!$this->isDriveConfigured()) {
            return false;
        }

        $url = "https://www.googleapis.com/drive/v3/files/{$fileId}";
        try {
            $this->apiRequest($url, 'DELETE');
            return true;
        } catch (Exception $e) {
            // If already deleted / not found, treat as succeeded
            if (str_contains($e->getMessage(), 'File not found') || str_contains($e->getMessage(), '404')) {
                return true;
            }
            error_log("Google Drive delete file error ({$fileId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test connection to Google APIs
     */
    public function testConnection() {
        $status = [
            'sheets_configured' => $this->isSheetsConfigured(),
            'drive_configured' => $this->isDriveConfigured(),
            'sheets_connected' => false,
            'drive_connected' => false,
            'error' => null
        ];

        try {
            if ($this->isSheetsConfigured()) {
                $this->getSheetRows();
                $status['sheets_connected'] = true;
            }
        } catch (Exception $e) {
            $status['error'] = 'Sheets: ' . $e->getMessage();
        }

        try {
            if ($this->isDriveConfigured()) {
                $this->apiRequest('https://www.googleapis.com/drive/v3/about?fields=user');
                $status['drive_connected'] = true;
            }
        } catch (Exception $e) {
            if (empty($status['error'])) {
                $status['error'] = 'Drive: ' . $e->getMessage();
            }
        }

        return $status;
    }
}
