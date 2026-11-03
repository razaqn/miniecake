<?php
/**
 * Miniecake - Admin Dashboard & Product Manager
 * Includes Product CRUD with Video & Copy Product, Category Manager, Banner Manager, Logo & Contact Settings
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/ProductRepository.php';
require_once __DIR__ . '/../includes/SettingsRepository.php';
require_once __DIR__ . '/../includes/GoogleClient.php';

Auth::requireAuth();

$repo = new ProductRepository();
$settingsRepo = new SettingsRepository();
$googleClient = new GoogleClient();

$products = $repo->getAll();
$categories = $repo->getCategories();
$settings = $settingsRepo->getAll();
$banners = $settingsRepo->getBanners();
$sourceStatus = $repo->getSourceStatus();

// Stats calculation
$totalProducts = count($products);
$totalCategories = count($categories);
$readyCount = 0;
$poCount = 0;

foreach ($products as $p) {
    $st = strtolower($p['status'] ?? '');
    if (str_contains($st, 'ready')) {
        $readyCount++;
    } elseif (str_contains($st, 'pre-order') || str_contains($st, 'po')) {
        $poCount++;
    }
}

$isSheetsConfigured = $googleClient->isSheetsConfigured();
$isDriveConfigured = $googleClient->isDriveConfigured();
$logoUrl = !empty($settings['logo_url']) ? $settings['logo_url'] : '../assets/images/logo.svg';
if (!str_starts_with($logoUrl, 'http') && !str_starts_with($logoUrl, '../')) {
    $logoUrl = '../' . $logoUrl;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <title>Admin Dashboard — Miniecake</title>
  <link rel="icon" type="image/svg+xml" href="<?= e($logoUrl) ?>">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

  <!-- Top Admin Navbar -->
  <header class="admin-navbar">
    <div class="admin-nav-left">
      <a href="./">
        <img src="<?= e($logoUrl) ?>" alt="Miniecake" class="admin-logo" id="adminNavbarLogo">
      </a>
      <span class="admin-badge">Admin Dashboard</span>
    </div>

    <div class="admin-nav-right">
      <a href="../" target="_blank" class="btn-secondary-link" title="Buka website katalog publik">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
        <span>Lihat Web Katalog</span>
      </a>

      <button type="button" class="btn-outline-soft" id="btnOpenGuide" title="Panduan API Google">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        <span>Panduan Google API</span>
      </button>

      <a href="logout" class="btn-logout" title="Keluar dari akun admin">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        <span>Logout</span>
      </a>
    </div>
  </header>

  <!-- Admin Main Container -->
  <main class="admin-container">
    
    <!-- Navigation Tabs Bar -->
    <nav class="admin-tabs-bar" aria-label="Admin Sections">
      <button type="button" class="admin-tab-btn active" data-tab="tabProducts">
        <span>🎂 Katalog Produk</span>
      </button>
      <button type="button" class="admin-tab-btn" data-tab="tabCategories">
        <span>🏷️ Manajemen Kategori</span>
      </button>
      <button type="button" class="admin-tab-btn" data-tab="tabBanners">
        <span>🖼️ Banner Promo</span>
      </button>
      <button type="button" class="admin-tab-btn" data-tab="tabSettings">
        <span>⚙️ Kontak &amp; Logo Web</span>
      </button>
    </nav>

    <!-- ==========================================
         TAB 1: KATALOG PRODUK
         ========================================== -->
    <section class="admin-tab-content active" id="tabProducts">
      <!-- Title & Top Action Row -->
      <div class="admin-header-row">
        <div class="admin-page-title">
          <h1>Kelola Produk &amp; Katalog</h1>
          <p>Atur stok kue, update harga, upload foto &amp; video produk, dan gunakan fitur Copy Produk untuk input cepat.</p>
        </div>

        <div class="admin-header-actions">
          <?php if ($isSheetsConfigured): ?>
            <button type="button" class="btn-outline-soft" id="btnSyncSheet" title="Kirim data lokal ke Google Sheet">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
              <span>Ekspor ke Sheet</span>
            </button>
          <?php endif; ?>

          <button type="button" class="btn-primary" id="btnOpenAddModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Tambah Produk Baru</span>
          </button>
        </div>
      </div>

      <!-- Connection Status Banner -->
      <div class="connection-banner">
        <div class="connection-status-group">
          <div class="connection-item">
            <span class="status-dot <?= $isSheetsConfigured ? 'active' : 'pending' ?>"></span>
            <span>Google Sheets: <?= $isSheetsConfigured ? 'Terkonfigurasi' : 'Penyimpanan Lokal (Isian .env masih kosong)' ?></span>
          </div>
          <div class="connection-item">
            <span class="status-dot <?= $isDriveConfigured ? 'active' : 'pending' ?>"></span>
            <span>Google Drive: <?= $isDriveConfigured ? 'Terkonfigurasi' : 'Upload Lokal (Isian .env masih kosong)' ?></span>
          </div>
        </div>
        <div>
          <button type="button" class="btn-outline-soft" id="btnTestConnection" style="padding: 6px 12px; font-size: 0.8rem;">
            ⚡ Test Koneksi Google
          </button>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">🎂</div>
          <div class="stat-info">
            <span class="stat-value" id="statTotal"><?= $totalProducts ?></span>
            <span class="stat-label">Total Produk</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">🏷️</div>
          <div class="stat-info">
            <span class="stat-value" id="statCategories"><?= $totalCategories ?></span>
            <span class="stat-label">Kategori Kue</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">✨</div>
          <div class="stat-info">
            <span class="stat-value" id="statReady"><?= $readyCount ?></span>
            <span class="stat-label">Ready Stock</span>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon">⏳</div>
          <div class="stat-info">
            <span class="stat-value" id="statPo"><?= $poCount ?></span>
            <span class="stat-label">Pre-Order (PO)</span>
          </div>
        </div>
      </div>

      <!-- Products Table Card -->
      <div class="table-card">
        <div class="table-toolbar">
          <div class="table-search">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" id="adminTableSearch" placeholder="Cari nama, ID, atau kategori..." autocomplete="off">
          </div>

          <select id="adminCategoryFilter" class="table-filter-category">
            <option value="all">Semua Kategori</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Harga</th>
                <th>Status</th>
                <th>Media</th>
                <th style="text-align: right;">Aksi</th>
              </tr>
            </thead>
            <tbody id="productTableBody">
              <!-- Rendered by admin.js -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ==========================================
         TAB 2: MANAJEMEN KATEGORI
         ========================================== -->
    <section class="admin-tab-content" id="tabCategories">
      <div class="admin-header-row">
        <div class="admin-page-title">
          <h1>Manajemen Kategori Produk</h1>
          <p>Tambah kategori baru, ubah nama kategori, atau hapus kategori dari katalog.</p>
        </div>
      </div>

      <div class="category-manager-grid">
        <!-- Form Tambah Kategori -->
        <div class="cat-form-card">
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 12px; color: var(--text-main);">+ Tambah Kategori Baru</h3>
          <form id="addCategoryForm">
            <div class="form-group">
              <label class="form-label" for="newCategoryName">Nama Kategori *</label>
              <input type="text" id="newCategoryName" class="form-control" placeholder="Contoh: Chiffon Cake" required>
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; justify-content: center;">
              Simpan Kategori
            </button>
          </form>
        </div>

        <!-- Daftar Kategori -->
        <div class="cat-list-card">
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 8px; color: var(--text-main);">Daftar Kategori Aktif</h3>
          <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
            Mengubah nama kategori akan secara otomatis memperbarui kategori pada semua kue yang menggunakan kategori tersebut.
          </p>

          <div class="cat-items-list" id="categoryItemsList">
            <!-- Rendered by admin.js -->
          </div>
        </div>
      </div>
    </section>

    <!-- ==========================================
         TAB 3: BANNER PROMO
         ========================================== -->
    <section class="admin-tab-content" id="tabBanners">
      <div class="admin-header-row">
        <div class="admin-page-title">
          <h1>Manajemen Banner Promosi</h1>
          <p>Atur banner slider di halaman utama untuk mengumumkan diskon, koleksi baru, atau pre-order spesial.</p>
        </div>

        <div class="admin-header-actions">
          <button type="button" class="btn-primary" id="btnOpenAddBannerModal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            <span>Tambah Banner Baru</span>
          </button>
        </div>
      </div>

      <div class="banners-grid" id="bannersGrid">
        <!-- Rendered by admin.js -->
      </div>
    </section>

    <!-- ==========================================
         TAB 4: KONTAK & LOGO WEB
         ========================================== -->
    <section class="admin-tab-content" id="tabSettings">
      <div class="admin-header-row">
        <div class="admin-page-title">
          <h1>Pengaturan Kontak &amp; Logo Website</h1>
          <p>Ubah logo website, nomor WhatsApp untuk auto-chat katalog, tautan media sosial, serta alamat toko.</p>
        </div>
      </div>

      <div class="settings-section-card">
        <form id="generalSettingsForm">
          
          <!-- Logo Management -->
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 14px; color: #ec4899;">1. Logo Website</h3>
          <div class="logo-preview-box">
            <img src="<?= e($logoUrl) ?>" alt="Logo Preview" id="settingsLogoPreview" class="current-logo-img">
            <div>
              <input type="file" id="logoFileInput" accept="image/*" style="display: none;">
              <button type="button" class="btn-outline-soft" id="btnUploadLogo" style="margin-right: 8px;">
                📷 Upload Logo Baru
              </button>
              <button type="button" class="btn-outline-soft" id="btnResetLogo" style="color: #6b7280;">
                Kembalikan Logo Bawaan
              </button>
              <div style="font-size: 0.78rem; color: #6b7280; margin-top: 6px;">Format yang didukung: PNG transparan, SVG, JPG, WEBP.</div>
            </div>
          </div>
          <input type="hidden" id="formLogoUrl" name="logo_url" value="<?= e($settings['logo_url'] ?? 'assets/images/logo.svg') ?>">

          <!-- Nama & Tagline -->
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-top: 24px; margin-bottom: 14px; color: #ec4899;">2. Identitas Toko</h3>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="settingAppName">Nama Toko / Brand *</label>
              <input type="text" id="settingAppName" name="app_name" class="form-control" value="<?= e($settings['app_name'] ?? 'miniecake') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="settingAppTagline">Tagline</label>
              <input type="text" id="settingAppTagline" name="app_tagline" class="form-control" value="<?= e($settings['app_tagline'] ?? 'Sweet Moments in Every Bite') ?>">
            </div>
          </div>

          <!-- Kontak & WhatsApp Auto-chat -->
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-top: 24px; margin-bottom: 14px; color: #ec4899;">3. Kontak &amp; Media Sosial (Tombol Sticky)</h3>
          <div class="form-group">
            <label class="form-label" for="settingWhatsapp">Nomor WhatsApp Auto-Chat * (Wajib kode negara tanpa tanda +, contoh: 6281234567890)</label>
            <input type="text" id="settingWhatsapp" name="whatsapp_number" class="form-control" value="<?= e($settings['whatsapp_number'] ?? '6281234567890') ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="settingInstagram">Instagram URL</label>
              <input type="url" id="settingInstagram" name="instagram_url" class="form-control" value="<?= e($settings['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/miniecake">
            </div>
            <div class="form-group">
              <label class="form-label" for="settingFacebook">Facebook URL</label>
              <input type="url" id="settingFacebook" name="facebook_url" class="form-control" value="<?= e($settings['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/miniecake">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="settingTiktok">TikTok URL</label>
            <input type="url" id="settingTiktok" name="tiktok_url" class="form-control" value="<?= e($settings['tiktok_url'] ?? '') ?>" placeholder="https://tiktok.com/@miniecake">
          </div>

          <!-- Alamat & Jam -->
          <h3 style="font-size: 1.1rem; font-weight: 800; margin-top: 24px; margin-bottom: 14px; color: #ec4899;">4. Informasi Tambahan</h3>
          <div class="form-group">
            <label class="form-label" for="settingAddress">Alamat Toko</label>
            <input type="text" id="settingAddress" name="address" class="form-control" value="<?= e($settings['address'] ?? '') ?>" placeholder="Contoh: Jl. Melati No. 12, Jakarta">
          </div>
          <div class="form-group">
            <label class="form-label" for="settingOpeningHours">Jam Operasional</label>
            <input type="text" id="settingOpeningHours" name="opening_hours" class="form-control" value="<?= e($settings['opening_hours'] ?? '') ?>" placeholder="Contoh: Setiap Hari 08:00 - 20:00 WIB">
          </div>

          <div style="margin-top: 24px; text-align: right;">
            <button type="submit" class="btn-primary" id="btnSaveSettings">
              💾 Simpan Perubahan Kontak &amp; Logo
            </button>
          </div>
        </form>
      </div>
    </section>

  </main>

  <!-- ==========================================
       MODAL: TAMBAH / EDIT / DUPLIKAT PRODUK
       ========================================== -->
  <div class="admin-modal-overlay" id="productFormModal">
    <div class="admin-modal-card">
      <div class="admin-modal-header">
        <h3 id="modalFormTitle">Tambah Produk Baru</h3>
        <button type="button" class="btn-icon" id="btnCloseFormModal" style="border: none;">&times;</button>
      </div>

      <form id="productForm" class="admin-modal-body">
        <input type="hidden" id="formProductId" name="id" value="">

        <div class="form-group">
          <label class="form-label" for="formName">Nama Produk *</label>
          <input type="text" id="formName" name="name" class="form-control" placeholder="Contoh: Strawberry Bento Cake" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="formCategory">Kategori *</label>
            <select id="formCategory" name="category" class="form-control" required>
              <!-- Filled dynamically by admin.js from Category list -->
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="formPrice">Harga (Rp) *</label>
            <input type="number" id="formPrice" name="price" class="form-control" placeholder="85000" min="0" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="formStatus">Status Produk</label>
          <select id="formStatus" name="status" class="form-control">
            <option value="Ready Stock">Ready Stock</option>
            <option value="Pre-Order H-1">Pre-Order H-1</option>
            <option value="Pre-Order H-2">Pre-Order H-2</option>
            <option value="Pre-Order H-3">Pre-Order H-3</option>
            <option value="Sold Out">Sold Out</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" for="formShortDesc">Deskripsi Singkat (Tampil di kartu katalog)</label>
          <input type="text" id="formShortDesc" name="short_desc" class="form-control" placeholder="Ringkasan singkat 1-2 kalimat...">
        </div>

        <div class="form-group">
          <label class="form-label" for="formFullDesc">Deskripsi Lengkap (Tampil di modal detail)</label>
          <textarea id="formFullDesc" name="full_desc" class="form-control" rows="3" placeholder="Informasi ukuran, bahan, base cake, dan kelengkapan..."></textarea>
        </div>

        <!-- Video Support for Product -->
        <div class="form-group" style="background: #fff8fa; border: 1.5px dashed #fbcfe8; border-radius: 12px; padding: 14px;">
          <label class="form-label" style="color: #ec4899; display: flex; align-items: center; gap: 6px;">
            <span>🎥 Video Produk (Opsional)</span>
          </label>
          <div style="font-size: 0.8rem; color: #6b7280; margin-bottom: 8px;">
            Upload video kue (MP4 / WebM) atau tempel link video Google Drive / URL langsung.
          </div>
          
          <input type="file" id="videoFileInput" accept="video/*" style="display: none;">
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn-outline-soft" id="btnUploadVideo" style="font-size: 0.82rem;">
              📁 Pilih File Video
            </button>
            <input type="url" id="formVideoUrl" name="video" class="form-control" style="flex: 1; min-width: 220px;" placeholder="Atau tempel URL video MP4 / WebM di sini...">
          </div>

          <!-- Video Preview Player -->
          <div class="video-preview-wrapper" id="videoPreviewWrapper" style="display: none; margin-top: 10px;">
            <video id="formVideoPlayer" controls playsinline></video>
            <button type="button" class="remove-video-btn" id="btnRemoveVideo">Hapus Video &times;</button>
          </div>
        </div>

        <!-- Image Upload to Drive / URLs -->
        <div class="form-group">
          <label class="form-label">Foto Produk (Google Drive / URL)</label>
          
          <div class="uploader-box" id="dropArea">
            <input type="file" id="fileInput" accept="image/*" style="display: none;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ec4899; margin: 0 auto 6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            <div style="font-weight: 600; font-size: 0.85rem; color: #1f2937;">Klik untuk upload foto kue</div>
            <div style="font-size: 0.75rem; color: #6b7280; margin-top: 2px;">
              <?= $isDriveConfigured ? 'Otomatis diupload ke Google Drive &amp; dibuatkan link' : 'Tersimpan ke folder lokal (Drive belum diset)' ?>
            </div>
          </div>

          <div style="margin-top: 10px; display: flex; gap: 8px;">
            <input type="url" id="manualUrlInput" class="form-control" placeholder="Atau tempel URL gambar / Link Drive di sini...">
            <button type="button" class="btn-outline-soft" id="btnAddUrlBtn" style="white-space: nowrap;">+ Tambah URL</button>
          </div>

          <!-- Preview of images attached to this product -->
          <div class="preview-images-grid" id="previewImagesContainer">
            <!-- Populated dynamically -->
          </div>
        </div>
      </form>

      <div class="admin-modal-footer">
        <button type="button" class="btn-outline-soft" id="btnCancelForm">Batal</button>
        <button type="submit" form="productForm" class="btn-primary" id="btnSaveProduct">Simpan Produk</button>
      </div>
    </div>
  </div>

  <!-- ==========================================
       MODAL: TAMBAH / EDIT BANNER PROMO
       ========================================== -->
  <div class="admin-modal-overlay" id="bannerModal">
    <div class="admin-modal-card">
      <div class="admin-modal-header">
        <h3 id="bannerModalTitle">Tambah Banner Baru</h3>
        <button type="button" class="btn-icon" id="btnCloseBannerModal" style="border: none;">&times;</button>
      </div>

      <form id="bannerForm" class="admin-modal-body">
        <input type="hidden" id="formBannerId" name="id" value="">

        <div class="form-group">
          <label class="form-label" for="formBannerTitle">Judul Banner *</label>
          <input type="text" id="formBannerTitle" name="title" class="form-control" placeholder="Contoh: Diskon Akhir Pekan 20% ✨" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="formBannerSubtitle">Subjudul / Keterangan Singkat</label>
          <textarea id="formBannerSubtitle" name="subtitle" class="form-control" rows="2" placeholder="Keterangan promo menarik untuk pengunjung katalog..."></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="formBannerBtnText">Teks Tombol</label>
            <input type="text" id="formBannerBtnText" name="button_text" class="form-control" value="Lihat Menu Kue">
          </div>
          <div class="form-group">
            <label class="form-label" for="formBannerLink">Tautan Tombol (URL / #katalog)</label>
            <input type="text" id="formBannerLink" name="link" class="form-control" value="#katalog">
          </div>
        </div>

        <!-- Banner Image Uploader -->
        <div class="form-group">
          <label class="form-label">Foto / Gambar Banner *</label>
          <input type="file" id="bannerFileInput" accept="image/*" style="display: none;">
          <div style="display: flex; gap: 8px;">
            <button type="button" class="btn-outline-soft" id="btnUploadBannerImg">📷 Upload Gambar</button>
            <input type="url" id="formBannerImage" name="image" class="form-control" placeholder="Atau masukkan URL gambar banner..." required>
          </div>
          <div id="bannerImgPreviewWrapper" style="margin-top: 10px; border-radius: 8px; overflow: hidden; max-height: 140px; display: none;">
            <img id="bannerImgPreview" src="" alt="Banner Preview" style="width: 100%; height: 140px; object-fit: cover;">
          </div>
        </div>

        <div class="form-group" style="display: flex; align-items: center; gap: 10px; margin-top: 12px;">
          <input type="checkbox" id="formBannerActive" name="active" checked style="width: 18px; height: 18px;">
          <label for="formBannerActive" style="font-weight: 700; font-size: 0.9rem; cursor: pointer;">Aktifkan Banner (Tampilkan di halaman utama)</label>
        </div>
      </form>

      <div class="admin-modal-footer">
        <button type="button" class="btn-outline-soft" id="btnCancelBannerModal">Batal</button>
        <button type="submit" form="bannerForm" class="btn-primary" id="btnSaveBanner">Simpan Banner</button>
      </div>
    </div>
  </div>

  <!-- ==========================================
       MODAL: PANDUAN GOOGLE API (TANPA SERVICE ACCOUNT)
       ========================================== -->
  <div class="admin-modal-overlay" id="guideModal">
    <div class="admin-modal-card" style="max-width: 720px;">
      <div class="admin-modal-header">
        <h3>Panduan Menghubungkan Google Sheets &amp; Drive</h3>
        <button type="button" class="btn-icon" id="btnCloseGuideModal" style="border: none;">&times;</button>
      </div>

      <div class="admin-modal-body" style="font-size: 0.88rem; line-height: 1.6;">
        <div style="background: #fff0f5; padding: 14px; border-radius: 8px; border-left: 4px solid #ec4899; margin-bottom: 16px;">
          <strong>💡 Bebas Service Account!</strong><br>
          Aplikasi Miniecake mendukung autentikasi Google OAuth 2.0 standar. Anda cukup mengisi variabel di file <code>.env</code> di root project.
        </div>

        <h4 style="color: #ec4899; margin-top: 14px; margin-bottom: 6px;">1. Spreadsheet ID &amp; Drive Folder ID</h4>
        <ul>
          <li><strong>ID Spreadsheet:</strong> Buka Google Sheet Anda di browser, salin kode dari URL: <br>
            <code>https://docs.google.com/spreadsheets/d/<b>[SALIN_KODE_INI]</b>/edit</code> &rarr; Masukkan ke <code>GOOGLE_SPREADSHEET_ID</code>.
          </li>
          <li><strong>ID Folder Drive:</strong> Buat folder di Google Drive (misal: "Miniecake Photos"), salin kode dari URL: <br>
            <code>https://drive.google.com/drive/folders/<b>[SALIN_KODE_INI]</b></code> &rarr; Masukkan ke <code>GOOGLE_DRIVE_FOLDER_ID</code>.
          </li>
        </ul>

        <h4 style="color: #ec4899; margin-top: 14px; margin-bottom: 6px;">2. Mendapatkan OAuth2 Token (Client ID, Secret, &amp; Refresh Token)</h4>
        <ol style="padding-left: 20px;">
          <li>Buka <a href="https://console.cloud.google.com/" target="_blank" style="color: #ec4899; text-decoration: underline;">Google Cloud Console</a>, buat project baru.</li>
          <li>Aktifkan <strong>Google Sheets API</strong> dan <strong>Google Drive API</strong> di menu <em>APIs &amp; Services &gt; Library</em>.</li>
          <li>Masuk ke <em>Credentials &gt; Create Credentials &gt; OAuth client ID</em> (tipe Web application).</li>
          <li>Tambahkan Redirect URI: <code>https://developers.google.com/oauthplayground</code></li>
          <li>Salin <strong>Client ID</strong> &amp; <strong>Client Secret</strong> ke <code>GOOGLE_CLIENT_ID</code> dan <code>GOOGLE_CLIENT_SECRET</code> di <code>.env</code>.</li>
          <li>Buka <a href="https://developers.google.com/oauthplayground" target="_blank" style="color: #ec4899; text-decoration: underline;">Google OAuth2 Playground</a>:
            <ul>
              <li>Klik ikon roda gigi (Settings) di pojok kanan atas, centang <em>"Use your own OAuth credentials"</em>, isi Client ID &amp; Secret Anda.</li>
              <li>Pilih scope: <code>https://www.googleapis.com/auth/spreadsheets</code> dan <code>https://www.googleapis.com/auth/drive</code>.</li>
              <li>Klik <em>Authorize APIs</em>, login akun Google Anda.</li>
              <li>Klik <em>Exchange authorization code for tokens</em>.</li>
              <li>Salin <strong>Refresh Token</strong> ke <code>GOOGLE_REFRESH_TOKEN</code> di file <code>.env</code>.</li>
            </ul>
          </li>
        </ol>

        <h4 style="color: #ec4899; margin-top: 14px; margin-bottom: 6px;">3. Cara Cepat: Direct Access Token</h4>
        <p>Jika hanya untuk testing cepat, Anda juga bisa langsung menempelkan Access Token sementara dari OAuth Playground ke <code>GOOGLE_ACCESS_TOKEN</code> di file <code>.env</code>.</p>
      </div>

      <div class="admin-modal-footer">
        <button type="button" class="btn-primary" id="btnCloseGuideModalBtn">Mengerti</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div class="admin-toast" id="adminToast">
    <span id="toastMessage">Pesan</span>
  </div>

  <!-- Pass Initial Data to Admin JS -->
  <script>
    window.INITIAL_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.INITIAL_CATEGORIES = <?= json_encode($categories, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.INITIAL_BANNERS = <?= json_encode($banners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.INITIAL_SETTINGS = <?= json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.ADMIN_TOKEN = <?= json_encode(Auth::getToken()) ?>;
    window.BASE_URL = <?= json_encode(base_url()) ?>;
  </script>
  <script src="../assets/js/admin.js"></script>
</body>
</html>
