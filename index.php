<?php
/**
 * Miniecake - E-Catalog Main Page
 * Dynamic with Banner Slider, Favorites (Love), Video Support, Categories & Contact Settings
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/ProductRepository.php';
require_once __DIR__ . '/includes/SettingsRepository.php';

$repo = new ProductRepository();
$settingsRepo = new SettingsRepository();

$settings = $settingsRepo->getAll();
$products = $repo->getAll();
$categories = $repo->getCategories();
$branches = $settingsRepo->getBranches();

$appName = $settings['app_name'] ?? env('APP_NAME', 'miniecake');
$appTagline = $settings['app_tagline'] ?? env('APP_TAGLINE', 'Sweet Moments in Every Bite');
$logoUrl = !empty($settings['logo_url']) ? $settings['logo_url'] : 'assets/images/logo.svg';
$waNumber = $settings['whatsapp_number'] ?? env('WHATSAPP_NUMBER', '6281234567890');

// Find default branch for fallback
$defaultBranch = null;
foreach ($branches as $b) {
    if (!empty($b['is_default'])) {
        $defaultBranch = $b;
        break;
    }
}
if (!$defaultBranch && !empty($branches)) {
    $defaultBranch = $branches[0];
}
if ($defaultBranch && !empty($defaultBranch['whatsapp_number'])) {
    $waNumber = $defaultBranch['whatsapp_number'];
}
$igUrl = $settings['instagram_url'] ?? env('INSTAGRAM_URL', 'https://instagram.com/miniecake');
$fbUrl = $settings['facebook_url'] ?? env('FACEBOOK_URL', 'https://facebook.com/miniecake');
$tiktokUrl = $settings['tiktok_url'] ?? env('TIKTOK_URL', 'https://tiktok.com/@miniecake');
$address = $settings['address'] ?? '';
$openingHours = $settings['opening_hours'] ?? '';

// Active Banners
$allBanners = $settings['banners'] ?? [];
$activeBanners = array_values(array_filter($allBanners, fn($b) => !empty($b['active'])));

// Floating WhatsApp URL
$cleanWa = preg_replace('/[^0-9]/', '', $waNumber);
if (str_starts_with($cleanWa, '0')) {
    $cleanWa = '62' . substr($cleanWa, 1);
}
$floatingWaUrl = "https://wa.me/{$cleanWa}?text=" . urlencode("Halo Miniecake! Saya ingin bertanya seputar katalog kue Anda ✨");

// Check for deep-linked product for dynamic Open Graph & rich link previews
$selectedProduct = null;
$reqProductId = $_GET['product'] ?? $_GET['id'] ?? null;
if ($reqProductId) {
    foreach ($products as $p) {
        if ($p['id'] === $reqProductId) {
            $selectedProduct = $p;
            break;
        }
    }
}

if ($selectedProduct) {
    $pageTitle = $selectedProduct['name'] . " — " . ucfirst($appName);
    $pageDesc = !empty($selectedProduct['short_desc']) ? $selectedProduct['short_desc'] : (!empty($selectedProduct['full_desc']) ? substr(strip_tags($selectedProduct['full_desc']), 0, 160) : $appTagline);
    $ogImg = !empty($selectedProduct['images'][0]) ? format_image_url($selectedProduct['images'][0]) : (!empty($selectedProduct['image']) ? format_image_url($selectedProduct['image']) : format_image_url($logoUrl));
    $canonicalUrl = base_url('?product=' . urlencode($selectedProduct['id']));
} else {
    $pageTitle = ucfirst($appName) . " — " . $appTagline;
    $pageDesc = "Katalog kue dan pastry cantik, lezat, dan minimalis dari " . $appName . ". Pesan langsung melalui WhatsApp.";
    $ogImg = format_image_url($logoUrl);
    $canonicalUrl = base_url();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="no-referrer">
  <title><?= e($pageTitle) ?></title>
  <meta name="description" content="<?= e($pageDesc) ?>">

  <!-- Open Graph Meta Tags (for WhatsApp Link Preview) -->
  <meta property="og:type" content="product">
  <meta property="og:title" content="<?= e($pageTitle) ?>">
  <meta property="og:description" content="<?= e($pageDesc) ?>">
  <meta property="og:image" content="<?= e($ogImg) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta property="og:site_name" content="<?= e($appName) ?>">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= e($pageTitle) ?>">
  <meta name="twitter:description" content="<?= e($pageDesc) ?>">
  <meta name="twitter:image" content="<?= e($ogImg) ?>">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= e(format_image_url($logoUrl)) ?>">
  
  <!-- CSS Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">

  <!-- Immediate Theme Detector (Prevents FOUC) -->
  <script>
    (function() {
      try {
        var stored = localStorage.getItem('miniecake_theme');
        var theme = stored ? stored : (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
      } catch (e) {}
    })();
  </script>
</head>
<body>

  <!-- ==========================================
       SITE HEADER / NAVBAR
       ========================================== -->
  <header class="site-header">
    <div class="container navbar">
      <!-- Brand Logo -->
      <a href="./" class="brand-link" title="Beranda Miniecake">
        <img src="<?= e($logoUrl) ?>" alt="Miniecake Logo" class="brand-logo">
      </a>

      <!-- Live Search Box -->
      <div class="header-search">
        <div class="search-input-wrapper">
          <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input 
            type="text" 
            id="searchInput" 
            class="search-input" 
            placeholder="Cari kue, bento cake, dessert box..." 
            autocomplete="off"
            aria-label="Cari Produk"
          >
          <button type="button" id="searchClear" class="search-clear" title="Hapus pencarian">&times;</button>
        </div>
      </div>

      <!-- Nav Actions -->
      <div class="nav-actions">
        <!-- Love / Favorites Nav Pill -->
        <button type="button" class="btn-fav-nav" id="btnFavNav" title="Lihat kue yang kamu sukai">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
          <span class="nav-btn-text">Favorit</span>
          <span id="favCountBadge" class="fav-badge-count">0</span>
        </button>

        <!-- Shopping Cart Nav Pill -->
        <button type="button" class="btn-cart-nav" id="btnCartNav" title="Buka Keranjang Belanja">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
          <span class="nav-btn-text">Keranjang</span>
          <span id="cartCountBadge" class="cart-badge-count">0</span>
        </button>

        <!-- Theme Toggle Button (Light / Dark) -->
        <button type="button" class="btn-theme-nav" id="btnThemeToggle" title="Ganti Mode Gelap/Terang" aria-label="Ganti Tema">
          <svg class="icon-sun" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          <svg class="icon-moon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          <span class="theme-label nav-btn-text">Mode</span>
        </button>
      </div>
    </div>
  </header>

  <!-- ==========================================
       PROMOTIONAL BANNER SECTION
       ========================================== -->
  <section class="banner-carousel-section">
    <div class="container">
      <?php if (!empty($activeBanners)): ?>
        <!-- Dynamic Promo Banner Slider -->
        <div class="banner-slider-container" id="bannerSlider">
          <div class="banner-track">
            <?php foreach ($activeBanners as $b): ?>
              <div class="banner-slide">
                <?php if (!empty($b['image'])): ?>
                  <img src="<?= e($b['image']) ?>" alt="<?= e($b['title']) ?>" class="banner-bg-img" loading="lazy">
                <?php endif; ?>
                <div class="banner-overlay-gradient"></div>
                <div class="banner-content">
                  <div class="banner-pill">✨ Promo Spesial</div>
                  <h2 class="banner-title"><?= e($b['title']) ?></h2>
                  <p class="banner-subtitle"><?= e($b['subtitle']) ?></p>
                  <a href="<?= e($b['link'] ?? '#katalog') ?>" class="banner-cta-btn">
                    <span><?= e(!empty($b['button_text']) ? $b['button_text'] : 'Lihat Menu Kue') ?></span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (count($activeBanners) > 1): ?>
            <!-- Slider Controls -->
            <button class="banner-arrow-btn prev" aria-label="Banner Sebelumnya">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
            </button>
            <button class="banner-arrow-btn next" aria-label="Banner Berikutnya">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </button>

            <!-- Indicators -->
            <div class="banner-indicators">
              <?php foreach ($activeBanners as $idx => $b): ?>
                <span class="banner-dot <?= $idx === 0 ? 'active' : '' ?>"></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <!-- Fallback Minimalist Hero -->
        <div style="background: linear-gradient(180deg, #fff5f8 0%, #ffffff 100%); padding: 36px 20px; border-radius: 20px; text-align: center; border: 1px dashed #fbcfe8;">
          <div style="display: inline-block; background: #fff0f5; border: 1px solid #fbcfe8; border-radius: 9999px; padding: 4px 14px; font-size: 0.8rem; font-weight: 700; color: #be185d; margin-bottom: 8px;">
            ✨ Handcrafted with Love &amp; Butter
          </div>
          <h1 style="font-family: 'Playfair Display', serif; font-size: 2.3rem; margin-bottom: 8px; color: #1f2937;">
            Sweet Moments in <span style="color: #ec4899;">Every Bite</span>
          </h1>
          <p style="color: #6b7280; max-width: 540px; margin: 0 auto; font-size: 0.95rem;">
            Temukan pilihan cake estetis, bento cake Korea, cupcake manis, dan dessert segar favoritmu untuk setiap momen berharga.
          </p>
        </div>
      <?php endif; ?>

      
    </div>
  </section>

  <!-- ==========================================
       MAIN CATALOG CONTENT
       ========================================== -->
  <main class="container" id="katalog">
    <!-- Category Filter Pills -->
    <div class="catalog-controls">
      <div class="category-scroll-container" id="categoryScroll">
        <button class="category-btn active" data-category="all">✨ Semua Kue</button>
        <?php foreach ($categories as $cat): ?>
          <button class="category-btn" data-category="<?= e($cat) ?>"><?= e($cat) ?></button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Active Favorites Banner Notice -->
    <div class="favorites-mode-banner" id="favoritesBanner" style="display: none;">
      <div>
        <span>❤️ Menampilkan <strong class="fav-active-count">0</strong> kue favorit yang kamu sukai</span>
      </div>
      <button type="button" class="btn-reset-filter" id="btnResetFavFilter">Lihat Semua Kue</button>
    </div>

    <!-- Catalog Status Bar -->
    <div class="catalog-info-bar">
      <div class="catalog-count">
        Menampilkan <span id="resultCount"><?= count($products) ?></span> pilihan produk
      </div>
      <div class="catalog-hint">
        <small>💡 Klik gambar / kartu untuk melihat detail, tonton video &amp; pesan ke WhatsApp</small>
      </div>
    </div>

    <!-- Product Grid -->
    <div class="product-grid" id="productGrid">
      <!-- Dynamic Products populated by JavaScript -->
    </div>
  </main>

  <!-- ==========================================
       PRODUCT DETAIL MODAL (With Photos & Video Support)
       ========================================== -->
  <div class="modal-overlay" id="productModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
      <button class="modal-close-btn" id="modalCloseBtn" aria-label="Tutup Detail Produk">&times;</button>
      
      <div class="modal-content-grid">
        <!-- Left: Image & Video Gallery -->
        <div class="modal-gallery">
          <div class="modal-main-img-box">
            <img src="" alt="Detail Produk Miniecake" id="modalMainImg" class="modal-main-img">
            <video id="modalMainVideo" class="modal-main-video" controls loop playsinline></video>
          </div>
          <div class="modal-thumbs" id="modalThumbs">
            <!-- Thumbnails rendered by JS (Photos & Video) -->
          </div>
        </div>

        <!-- Right: Product Info & Order CTA -->
        <div class="modal-info">
          <div class="modal-top-bar">
            <div class="modal-tags">
              <span class="badge badge-category" id="modalCategoryBadge">Category</span>
              <span class="badge badge-status" id="modalStatusBadge">Status</span>
            </div>
            <!-- Love / Favorite button inside modal -->
            <button type="button" class="modal-fav-toggle-btn" id="modalFavBtn" title="Favoritkan Produk">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
              <span>Suka Kue Ini</span>
            </button>
          </div>

          <h2 class="modal-product-title" id="modalTitle">Nama Produk</h2>

          <div class="modal-price-box">
            <div class="modal-price-label">Harga</div>
            <div class="modal-price-value" id="modalPrice">Rp 0</div>
          </div>

          <div class="modal-section-title">Deskripsi Lengkap</div>
          <p class="modal-desc-text" id="modalDesc">Deskripsi produk...</p>

          <div class="order-notes-box">
            <strong>Catatan Pemesanan:</strong>
            <ul>
              <li>Pemesanan pre-order disarankan H-2 sebelum acara.</li>
              <li>Free lilin spiral dan sendok kayu mini untuk Bento Cake.</li>
              <li>Bisa request ucapan / custom nama di atas kue.</li>
            </ul>
          </div>

          <!-- Modal CTA Actions (Add to Cart & Direct WhatsApp) -->
          <div class="modal-cta-group">
            <button type="button" class="btn-add-to-cart" id="modalAddToCartBtn">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
              <span>+ Tambah ke Keranjang</span>
            </button>

            <!-- Direct WhatsApp Order Button -->
            <a href="#" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-order" id="modalWaBtn">
              <svg viewBox="0 0 24 24">
                <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086.159.058 1.011.477 1.184.564.173.086.289.13.332.202.043.072.043.419-.101.824z"/>
                <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.98-1.306A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.614 0-3.118-.456-4.402-1.246l-.316-.194-2.964.777.791-2.89-.208-.33A7.962 7.962 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
              </svg>
              <span>Tanya Ketersediaan di WhatsApp</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ==========================================
       STICKY FLOATING BUTTONS
       (Cart, WhatsApp, Instagram, Facebook, TikTok)
       ========================================== -->
  <aside class="sticky-social-bar" aria-label="Social Media Miniecake">
    <!-- Floating Cart Button -->
    <button type="button" class="social-float-btn social-cart" id="btnFloatingCart" data-tooltip="Keranjang Belanja" aria-label="Keranjang">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
      <span class="floating-cart-badge" id="floatingCartCount">0</span>
    </button>

    <a href="<?= e($floatingWaUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-float-btn social-wa" data-tooltip="Chat WhatsApp" aria-label="WhatsApp">
      <svg viewBox="0 0 24 24">
        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086.159.058 1.011.477 1.184.564.173.086.289.13.332.202.043.072.043.419-.101.824z"/>
        <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.98-1.306A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.614 0-3.118-.456-4.402-1.246l-.316-.194-2.964.777.791-2.89-.208-.33A7.962 7.962 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
      </svg>
    </a>

    <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-float-btn social-ig" data-tooltip="Instagram" aria-label="Instagram">
      <svg viewBox="0 0 24 24">
        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
      </svg>
    </a>

    <a href="<?= e($fbUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-float-btn social-fb" data-tooltip="Facebook" aria-label="Facebook">
      <svg viewBox="0 0 24 24">
        <path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.65 5H18V0h-3.808C10.597 0 9 1.583 9 4.615V8z"/>
      </svg>
    </a>

    <a href="<?= e($tiktokUrl) ?>" target="_blank" rel="noopener noreferrer" class="social-float-btn social-tiktok" data-tooltip="TikTok" aria-label="TikTok">
      <svg viewBox="0 0 24 24">
        <path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-2.883 2.884 2.884 2.884 0 0 1-2.884-2.884 2.884 2.884 0 0 1 2.884-2.883c.28 0 .548.04.802.115V9.453a6.328 6.328 0 0 0-.802-.053 6.329 6.329 0 0 0-6.328 6.328 6.329 6.329 0 0 0 6.328 6.329 6.329 6.329 0 0 0 6.328-6.329V8.847c1.472.937 3.22 1.478 5.093 1.503V6.892a4.819 4.819 0 0 1-1.323-.206z"/>
      </svg>
    </a>
  </aside>

  <!-- ==========================================
       SITE FOOTER
       ========================================== -->
  <footer class="site-footer">
    <div class="container footer-content">
      <img src="<?= e($logoUrl) ?>" alt="Miniecake Logo" class="footer-logo">
      <p>Kelezatan cake buatan tangan berkualitas tinggi untuk hari-hari istimewamu.</p>
      
      <?php if (!empty($address) || !empty($openingHours)): ?>
        <div class="footer-info-details">
          <?php if (!empty($address)): ?>
            <div>📍 <?= e($address) ?></div>
          <?php endif; ?>
          <?php if (!empty($openingHours)): ?>
            <div>⏰ <?= e($openingHours) ?></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="footer-social-links">
        <a href="<?= e($floatingWaUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-icon" title="WhatsApp">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086.159.058 1.011.477 1.184.564.173.086.289.13.332.202.043.072.043.419-.101.824z"/></svg>
        </a>
        <a href="<?= e($igUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-icon" title="Instagram">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/></svg>
        </a>
        <a href="<?= e($fbUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-icon" title="Facebook">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.374 14.5 5 15.65 5H18V0h-3.808C10.597 0 9 1.583 9 4.615V8z"/></svg>
        </a>
        <a href="<?= e($tiktokUrl) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-icon" title="TikTok">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.245V2h-3.445v13.672a2.896 2.896 0 0 1-2.883 2.884 2.884 2.884 0 0 1-2.884-2.884 2.884 2.884 0 0 1 2.884-2.883c.28 0 .548.04.802.115V9.453a6.328 6.328 0 0 0-.802-.053 6.329 6.329 0 0 0-6.328 6.328 6.329 6.329 0 0 0 6.328 6.329 6.329 6.329 0 0 0 6.328-6.329V8.847c1.472.937 3.22 1.478 5.093 1.503V6.892a4.819 4.819 0 0 1-1.323-.206z"/></svg>
        </a>
      </div>
    </div>
    
    <div class="footer-bottom">
      &copy; <?= date('Y') ?> <?= e($appName) ?>. All rights reserved. Minimalist Pink &amp; White Edition.
    </div>
  </footer>

  <!-- ==========================================
       SHOPPING CART (KERANJANG BELANJA) DRAWER
       ========================================== -->
  <div class="cart-drawer-overlay" id="cartDrawerOverlay">
    <div class="cart-drawer" id="cartDrawer">
      <div class="cart-drawer-header">
        <div class="cart-drawer-title-wrap">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
          <h3 class="cart-drawer-title">Keranjang Belanja</h3>
          <span class="cart-total-badge" id="cartDrawerBadge">0</span>
        </div>
        <button type="button" class="btn-close-drawer" id="btnCloseCartDrawer" aria-label="Tutup Keranjang">&times;</button>
      </div>

      <!-- Select All Toolbar -->
      <div class="cart-toolbar" id="cartToolbar" style="display: none;">
        <label class="cart-checkbox-wrap">
          <input type="checkbox" id="cartSelectAllCheckbox" checked>
          <span>Pilih Semua (<span id="cartSelectedCountText">0</span>)</span>
        </label>
        <button type="button" class="btn-clear-cart" id="btnClearSelectedCart">Hapus Terpilih</button>
      </div>

      <!-- Cart Items List Container -->
      <div class="cart-drawer-body" id="cartDrawerBody">
        <!-- Dynamic via JS -->
      </div>

      <!-- Cart Footer / WhatsApp Checkout -->
      <div class="cart-drawer-footer" id="cartDrawerFooter" style="display: none;">
        <div class="cart-summary-row">
          <span class="summary-label">Total Dipilih:</span>
          <span class="summary-price" id="cartTotalPrice">Rp 0</span>
        </div>
        <button type="button" class="btn-cart-whatsapp" id="btnCartWhatsApp">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766 0-3.18-2.587-5.771-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.006c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086.159.058 1.011.477 1.184.564.173.086.289.13.332.202.043.072.043.419-.101.824z"/>
            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.98-1.306A9.957 9.957 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18c-1.614 0-3.118-.456-4.402-1.246l-.316-.194-2.964.777.791-2.89-.208-.33A7.962 7.962 0 0 1 4 12c0-4.411 3.589-8 8-8s8 3.589 8 8-3.589 8-8 8z"/>
          </svg>
          <span id="cartWhatsAppLabel">Tanya Admin via WhatsApp (0 Produk)</span>
        </button>
      </div>
    </div>
  </div>

  <!-- ==========================================
       BRANCH SELECTOR MODAL (PILIH CABANG WA)
       ========================================== -->
  <div class="branch-modal-overlay" id="branchSelectorModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="branch-modal-card">
      <div class="branch-modal-header">
        <div class="branch-modal-title-wrap">
          <span class="branch-modal-icon">📍</span>
          <div>
            <h3 class="branch-modal-title">Pilih Cabang Miniecake</h3>
            <p class="branch-modal-subtitle">Pilih cabang terdekat untuk terhubung langsung dengan admin WhatsApp kami:</p>
          </div>
        </div>
        <button type="button" class="btn-close-branch-modal" id="btnCloseBranchModal" aria-label="Tutup Pilihan Cabang">&times;</button>
      </div>

      <div class="branch-modal-body" id="branchSelectorList">
        <!-- Rendered dynamically by app.js -->
      </div>
    </div>
  </div>

  <!-- Pass Data to JavaScript -->
  <script>
    window.INITIAL_PRODUCTS = <?= json_encode($products, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    window.APP_CONFIG = {
      whatsappNumber: "<?= e($waNumber) ?>",
      appName: "<?= e($appName) ?>",
      baseUrl: "<?= e(base_url()) ?>",
      branches: <?= json_encode($branches, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <script src="assets/js/app.js"></script>
</body>
</html>
