/**
 * Miniecake - Frontend App JavaScript
 * Handles dynamic product filtering, image sliders, modal details, WhatsApp auto-chat,
 * Promotional Banner slider, Product Video, and LocalStorage Favorites (Love Product).
 */

document.addEventListener('DOMContentLoaded', () => {
  // State
  let products = window.INITIAL_PRODUCTS || [];
  let currentCategory = 'all';
  let searchQuery = '';
  let showFavoritesOnly = false;
  let activeModalProduct = null;
  let activeModalImageIndex = 0;
  let isViewingVideo = false;

  // Favorites Storage Helper
  const FAV_STORAGE_KEY = 'miniecake_favorites';

  function getFavorites() {
    try {
      const data = localStorage.getItem(FAV_STORAGE_KEY);
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  function saveFavorites(favs) {
    try {
      localStorage.setItem(FAV_STORAGE_KEY, JSON.stringify(favs));
    } catch (e) {}
  }

  function isFavorite(id) {
    return getFavorites().includes(id);
  }

  function toggleFavorite(id) {
    let favs = getFavorites();
    const index = favs.indexOf(id);
    if (index > -1) {
      favs.splice(index, 1);
    } else {
      favs.push(id);
    }
    saveFavorites(favs);
    updateFavoriteUI();
    if (showFavoritesOnly) {
      filterAndRender();
    }
  }

  // Cart Storage Helper
  const CART_STORAGE_KEY = 'miniecake_cart';

  function getCart() {
    try {
      const data = localStorage.getItem(CART_STORAGE_KEY);
      return data ? JSON.parse(data) : [];
    } catch (e) {
      return [];
    }
  }

  function saveCart(cartItems) {
    try {
      localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cartItems));
    } catch (e) {}
  }

  function updateCartBadge() {
    const items = getCart();
    const totalQty = items.reduce((sum, item) => sum + (item.qty || 1), 0);
    if (cartCountBadge) cartCountBadge.textContent = totalQty;
    if (floatingCartCount) floatingCartCount.textContent = totalQty;
    if (cartDrawerBadge) cartDrawerBadge.textContent = `${totalQty} Item`;
  }

  function showToast(message) {
    let toast = document.querySelector('.catalog-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'catalog-toast';
      document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3200);
  }

  // DOM Elements
  const productGrid = document.getElementById('productGrid');
  const searchInput = document.getElementById('searchInput');
  const searchClear = document.getElementById('searchClear');
  const categoryScroll = document.getElementById('categoryScroll');
  const resultCount = document.getElementById('resultCount');
  const btnFavNav = document.getElementById('btnFavNav');
  const favCountBadge = document.getElementById('favCountBadge');
  const favoritesBanner = document.getElementById('favoritesBanner');
  const btnResetFavFilter = document.getElementById('btnResetFavFilter');

  // Cart Elements
  const btnCartNav = document.getElementById('btnCartNav');
  const cartCountBadge = document.getElementById('cartCountBadge');
  const btnFloatingCart = document.getElementById('btnFloatingCart');
  const floatingCartCount = document.getElementById('floatingCartCount');
  const cartDrawerOverlay = document.getElementById('cartDrawerOverlay');
  const cartDrawer = document.getElementById('cartDrawer');
  const btnCloseCartDrawer = document.getElementById('btnCloseCartDrawer');
  const cartDrawerBadge = document.getElementById('cartDrawerBadge');
  const cartToolbar = document.getElementById('cartToolbar');
  const cartSelectAllCheckbox = document.getElementById('cartSelectAllCheckbox');
  const cartSelectedCountText = document.getElementById('cartSelectedCountText');
  const btnClearSelectedCart = document.getElementById('btnClearSelectedCart');
  const cartDrawerBody = document.getElementById('cartDrawerBody');
  const cartDrawerFooter = document.getElementById('cartDrawerFooter');
  const cartTotalPrice = document.getElementById('cartTotalPrice');
  const btnCartWhatsApp = document.getElementById('btnCartWhatsApp');
  const cartWhatsAppLabel = document.getElementById('cartWhatsAppLabel');
  
  // Modal Elements
  const productModal = document.getElementById('productModal');
  const modalCloseBtn = document.getElementById('modalCloseBtn');
  const modalMainImg = document.getElementById('modalMainImg');
  const modalMainVideo = document.getElementById('modalMainVideo');
  const modalThumbs = document.getElementById('modalThumbs');
  const modalCategoryBadge = document.getElementById('modalCategoryBadge');
  const modalStatusBadge = document.getElementById('modalStatusBadge');
  const modalTitle = document.getElementById('modalTitle');
  const modalPrice = document.getElementById('modalPrice');
  const modalDesc = document.getElementById('modalDesc');
  const modalWaBtn = document.getElementById('modalWaBtn');
  const modalAddToCartBtn = document.getElementById('modalAddToCartBtn');
  const modalFavBtn = document.getElementById('modalFavBtn');

  // WhatsApp Config
  const whatsappNumber = window.APP_CONFIG?.whatsappNumber || '6281234567890';

  /**
   * Format number as IDR Currency
   */
  function formatRupiah(number) {
    return 'Rp ' + Number(number || 0).toLocaleString('id-ID');
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /**
   * Convert Google Drive image links to our proxy api/image.php?id=...
   * to bypass Google hotlink protection and 429 rate limit
   */
  function formatImageUrl(url) {
    if (!url) return '';
    const trimmed = String(url).trim();
    if (trimmed.includes('api/image.php')) return trimmed;
    const driveMatch = trimmed.match(/(?:drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)|lh3\.googleusercontent\.com\/d\/)([a-zA-Z0-9_-]+)/);
    if (driveMatch) {
      return 'api/image.php?id=' + driveMatch[1];
    }
    return trimmed;
  }

  /**
   * Update all favorite buttons and counts across the page
   */
  function updateFavoriteUI() {
    const favs = getFavorites();
    if (favCountBadge) {
      favCountBadge.textContent = favs.length;
    }

    if (btnFavNav) {
      btnFavNav.classList.toggle('active', showFavoritesOnly);
    }

    if (favoritesBanner) {
      favoritesBanner.style.display = showFavoritesOnly ? 'flex' : 'none';
      const countEl = favoritesBanner.querySelector('.fav-active-count');
      if (countEl) countEl.textContent = favs.length;
    }

    // Update heart icons on cards
    document.querySelectorAll('.card-fav-btn').forEach(btn => {
      const id = btn.getAttribute('data-id');
      const active = favs.includes(id);
      btn.classList.toggle('active', active);
      btn.innerHTML = active 
        ? `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`;
    });

    // Update modal heart icon if open
    if (activeModalProduct && modalFavBtn) {
      const active = favs.includes(activeModalProduct.id);
      modalFavBtn.classList.toggle('active', active);
      modalFavBtn.innerHTML = active
        ? `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg> <span>Tersimpan di Favorit</span>`
        : `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> <span>Suka Kue Ini</span>`;
    }
  }

  /**
   * Filter and render products
   */
  function filterAndRender() {
    const query = searchQuery.trim().toLowerCase();
    const favs = getFavorites();

    const filtered = products.filter(item => {
      // Favorite filter
      if (showFavoritesOnly && !favs.includes(item.id)) {
        return false;
      }

      // Category match
      const matchCategory = (currentCategory === 'all') || 
        (item.category && item.category.toLowerCase() === currentCategory.toLowerCase());

      // Search match
      const name = (item.name || '').toLowerCase();
      const shortDesc = (item.short_desc || '').toLowerCase();
      const fullDesc = (item.full_desc || '').toLowerCase();
      const category = (item.category || '').toLowerCase();

      const matchSearch = !query || 
        name.includes(query) || 
        shortDesc.includes(query) || 
        fullDesc.includes(query) || 
        category.includes(query);

      return matchCategory && matchSearch;
    });

    renderProductGrid(filtered);
    if (resultCount) {
      resultCount.textContent = filtered.length;
    }
  }

  /**
   * Render cards into the grid
   */
  function renderProductGrid(items) {
    if (!productGrid) return;

    if (items.length === 0) {
      if (showFavoritesOnly) {
        productGrid.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">💖</div>
            <h3>Belum ada kue favorit</h3>
            <p>Klik tombol hati (love) pada kue kesukaanmu untuk menyimpannya di sini tanpa perlu login!</p>
          </div>
        `;
      } else {
        productGrid.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">🎂</div>
            <h3>Tidak ada kue yang ditemukan</h3>
            <p>Coba gunakan kata kunci lain atau pilih kategori yang berbeda.</p>
          </div>
        `;
      }
      return;
    }

    productGrid.innerHTML = items.map(product => {
      const rawImages = (Array.isArray(product.images) && product.images.length > 0)
        ? product.images 
        : ['https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=800&q=80'];
      const images = rawImages.map(formatImageUrl);

      const isMultiImage = images.length > 1;
      const isPO = (product.status || '').toLowerCase().includes('pre-order') || (product.status || '').toLowerCase().includes('po');
      const hasVideo = !emptyString(product.video);
      const isLoved = isFavorite(product.id);

      // Carousel slides HTML
      const slidesHtml = images.map(img => `
        <div class="card-carousel-slide">
          <img src="${escapeHtml(img)}" alt="${escapeHtml(product.name)}" referrerpolicy="no-referrer" loading="lazy" />
        </div>
      `).join('');

      // Carousel dots HTML
      const dotsHtml = isMultiImage ? `
        <div class="carousel-dots">
          ${images.map((_, i) => `<span class="carousel-dot ${i === 0 ? 'active' : ''}"></span>`).join('')}
        </div>
      ` : '';

      // Carousel controls
      const controlsHtml = isMultiImage ? `
        <button class="carousel-btn prev" data-card-action="prev" title="Foto Sebelumnya" aria-label="Previous">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
        </button>
        <button class="carousel-btn next" data-card-action="next" title="Foto Berikutnya" aria-label="Next">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      ` : '';

      return `
        <article class="product-card" data-id="${escapeHtml(product.id)}">
          <div class="card-carousel-wrapper" data-slide-index="0" data-total-slides="${images.length}">
            <div class="card-badges">
              <span class="badge badge-category">${escapeHtml(product.category || 'Cake')}</span>
              <span class="badge badge-status ${isPO ? 'po' : ''}">${escapeHtml(product.status || 'Ready')}</span>
              ${hasVideo ? '<span class="badge badge-video">▶ Video</span>' : ''}
            </div>

            <!-- Love / Favorite Button -->
            <button class="card-fav-btn ${isLoved ? 'active' : ''}" data-id="${escapeHtml(product.id)}" data-card-action="favorite" title="Simpan ke Favorit" aria-label="Favorit">
              ${isLoved 
                ? `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>`
                : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`
              }
            </button>

            <div class="card-carousel-track">
              ${slidesHtml}
            </div>
            ${controlsHtml}
            ${dotsHtml}
          </div>
          <div class="card-body">
            <h3 class="product-title">${escapeHtml(product.name)}</h3>
            <p class="product-short-desc">${escapeHtml(product.short_desc || '')}</p>
            <div class="card-footer">
              <span class="product-price">${formatRupiah(product.price)}</span>
              <span class="btn-detail-pill">Lihat Detail &rarr;</span>
            </div>
          </div>
        </article>
      `;
    }).join('');

    attachCardListeners();
  }

  function emptyString(str) {
    return !str || str.trim() === '';
  }

  /**
   * Attach card click & slider listeners
   */
  function attachCardListeners() {
    const cards = productGrid.querySelectorAll('.product-card');

    cards.forEach(card => {
      const productId = card.getAttribute('data-id');
      const wrapper = card.querySelector('.card-carousel-wrapper');
      const track = card.querySelector('.card-carousel-track');
      const dots = card.querySelectorAll('.carousel-dot');
      const totalSlides = parseInt(wrapper.getAttribute('data-total-slides') || '1', 10);

      // Card click opens modal
      card.addEventListener('click', (e) => {
        // Handle favorite click
        const favBtn = e.target.closest('[data-card-action="favorite"]');
        if (favBtn) {
          e.stopPropagation();
          toggleFavorite(productId);
          return;
        }

        // If clicking carousel controls, don't open modal
        if (e.target.closest('[data-card-action]')) {
          return;
        }

        openProductModal(productId);
      });

      // Carousel controls
      if (totalSlides > 1) {
        const prevBtn = card.querySelector('.carousel-btn.prev');
        const nextBtn = card.querySelector('.carousel-btn.next');

        const updateSlide = (newIndex) => {
          let targetIndex = newIndex;
          if (targetIndex < 0) targetIndex = totalSlides - 1;
          if (targetIndex >= totalSlides) targetIndex = 0;

          wrapper.setAttribute('data-slide-index', targetIndex);
          track.style.transform = `translateX(-${targetIndex * 100}%)`;

          dots.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === targetIndex);
          });
        };

        if (prevBtn) {
          prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const current = parseInt(wrapper.getAttribute('data-slide-index') || '0', 10);
            updateSlide(current - 1);
          });
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const current = parseInt(wrapper.getAttribute('data-slide-index') || '0', 10);
            updateSlide(current + 1);
          });
        }
      }
    });
  }

  /**
   * Open Product Detail Modal (supporting Photos & Video)
   */
  function openProductModal(productId) {
    const product = products.find(p => p.id === productId);
    if (!product || !productModal) return;

    activeModalProduct = product;
    activeModalImageIndex = 0;
    isViewingVideo = false;

    const rawImages = (Array.isArray(product.images) && product.images.length > 0)
      ? product.images 
      : ['https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=800&q=80'];
    const images = rawImages.map(formatImageUrl);

    const hasVideo = !emptyString(product.video);

    // Fill Modal Data
    modalCategoryBadge.textContent = product.category || 'Cake';
    modalStatusBadge.textContent = product.status || 'Ready Stock';
    const isPO = (product.status || '').toLowerCase().includes('pre-order') || (product.status || '').toLowerCase().includes('po');
    modalStatusBadge.className = `badge badge-status ${isPO ? 'po' : ''}`;

    modalTitle.textContent = product.name;
    modalPrice.textContent = formatRupiah(product.price);
    modalDesc.textContent = product.full_desc || product.short_desc || 'Tidak ada deskripsi detail.';

    // Main media: Image or Video
    modalMainImg.style.display = 'block';
    modalMainImg.src = images[0];
    modalMainImg.alt = product.name;
    modalMainImg.setAttribute('referrerpolicy', 'no-referrer');

    if (modalMainVideo) {
      modalMainVideo.style.display = 'none';
      modalMainVideo.pause();
      modalMainVideo.src = '';
    }

    // Render thumbnails (Photos + Video)
    let thumbsHtml = images.map((img, i) => `
      <div class="modal-thumb ${i === 0 ? 'active' : ''}" data-type="image" data-index="${i}">
        <img src="${escapeHtml(img)}" alt="${escapeHtml(product.name)} thumb ${i+1}" referrerpolicy="no-referrer">
      </div>
    `).join('');

    if (hasVideo) {
      thumbsHtml += `
        <div class="modal-thumb video-thumb" data-type="video" data-video-url="${escapeHtml(product.video)}" title="Tonton Video">
          <img src="${escapeHtml(images[0])}" alt="Video Thumbnail">
        </div>
      `;
    }

    if (images.length > 1 || hasVideo) {
      modalThumbs.style.display = 'flex';
      modalThumbs.innerHTML = thumbsHtml;

      modalThumbs.querySelectorAll('.modal-thumb').forEach(thumb => {
        thumb.addEventListener('click', () => {
          modalThumbs.querySelectorAll('.modal-thumb').forEach(t => t.classList.remove('active'));
          thumb.classList.add('active');

          const type = thumb.getAttribute('data-type');
          if (type === 'video') {
            isViewingVideo = true;
            modalMainImg.style.display = 'none';
            if (modalMainVideo) {
              modalMainVideo.style.display = 'block';
              modalMainVideo.src = thumb.getAttribute('data-video-url');
              modalMainVideo.play().catch(() => {});
            }
          } else {
            isViewingVideo = false;
            const idx = parseInt(thumb.getAttribute('data-index'), 10);
            activeModalImageIndex = idx;
            if (modalMainVideo) {
              modalMainVideo.pause();
              modalMainVideo.style.display = 'none';
            }
            modalMainImg.style.display = 'block';
            modalMainImg.src = images[idx];
            updateWhatsAppButton();
          }
        });
      });
    } else {
      modalThumbs.style.display = 'none';
      modalThumbs.innerHTML = '';
    }

    // Update Favorite Button & WhatsApp CTA
    updateFavoriteUI();
    updateWhatsAppButton();

    // Show Modal
    productModal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Update URL to ?product=ID for instant Open Graph unfurl & direct sharing
    history.replaceState(null, null, `?product=${encodeURIComponent(product.id)}`);
  }

  /**
   * Update WhatsApp button URL with clean Line 1 Product Link (No raw image URL)
   */
  function updateWhatsAppButton() {
    if (!activeModalProduct || !modalWaBtn) return;

    const product = activeModalProduct;
    // Line 1 contains the product URL so WhatsApp generates the thumbnail preview card!
    const currentProductUrl = `${window.location.origin}${window.location.pathname}?product=${encodeURIComponent(product.id)}`;

    let message = `${currentProductUrl}\n\n`;
    message += `Halo Miniecake! ✨🍰\n\n`;
    message += `Saya tertarik dengan kue:\n`;
    message += `*${product.name}*\n`;
    message += `• Kategori: ${product.category || 'Cake'}\n`;
    message += `• Harga: ${formatRupiah(product.price)}\n\n`;
    message += `Apakah kue ini ready stock atau bisa dipesan? Mohon informasinya ya. Terima kasih! 💕`;

    let cleanPhone = (whatsappNumber || '').replace(/[^0-9]/g, '');
    if (cleanPhone.startsWith('0')) {
      cleanPhone = '62' + cleanPhone.substring(1);
    }

    const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
    modalWaBtn.href = waUrl;
  }

  /**
   * Close Product Modal
   */
  function closeModal() {
    if (!productModal) return;
    if (modalMainVideo) {
      modalMainVideo.pause();
      modalMainVideo.src = '';
    }
    productModal.classList.remove('active');
    document.body.style.overflow = '';
    activeModalProduct = null;

    // Reset URL to clean path
    history.replaceState(null, null, window.location.pathname);
  }

  // ==========================================
  // SHOPPING CART (KERANJANG BELANJA) ENGINE
  // ==========================================

  function addItemToCart(product, qty = 1) {
    let cart = getCart();
    const existingIndex = cart.findIndex(c => c.productId === product.id);

    const images = Array.isArray(product.images) && product.images.length > 0 ? product.images : [];
    const cover = images[0] || '';

    if (existingIndex > -1) {
      cart[existingIndex].qty += qty;
    } else {
      cart.push({
        id: `cart_${product.id}`,
        productId: product.id,
        name: product.name,
        category: product.category || 'Cake',
        price: product.price || 0,
        image: cover,
        qty: qty,
        selected: true
      });
    }

    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
  }

  function removeCartItem(itemId) {
    let cart = getCart();
    cart = cart.filter(c => c.id !== itemId);
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
  }

  function updateCartItemQty(itemId, delta) {
    let cart = getCart();
    const item = cart.find(c => c.id === itemId);
    if (!item) return;

    item.qty = Math.max(1, (item.qty || 1) + delta);
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
  }

  function toggleCartItemSelect(itemId, isSelected) {
    let cart = getCart();
    const item = cart.find(c => c.id === itemId);
    if (!item) return;

    item.selected = isSelected;
    saveCart(cart);
    renderCartDrawer();
  }

  function toggleSelectAll(isSelected) {
    let cart = getCart();
    cart.forEach(item => { item.selected = isSelected; });
    saveCart(cart);
    renderCartDrawer();
  }

  function clearSelectedCartItems() {
    let cart = getCart();
    const selectedCount = cart.filter(c => c.selected).length;
    if (selectedCount === 0) return;
    if (!confirm(`Hapus ${selectedCount} produk terpilih dari keranjang?`)) return;

    cart = cart.filter(c => !c.selected);
    saveCart(cart);
    updateCartBadge();
    renderCartDrawer();
    showToast('Produk terpilih dihapus dari keranjang.');
  }

  function renderCartDrawer() {
    if (!cartDrawerBody) return;
    const cart = getCart();

    if (cart.length === 0) {
      cartDrawerBody.innerHTML = `
        <div class="cart-empty-state">
          <div class="cart-empty-icon">🛒</div>
          <p>Keranjang belanja kamu masih kosong.</p>
          <button type="button" class="btn-primary" id="btnCartExplore" style="margin-top: 15px; padding: 8px 18px; border-radius: 9999px;">Jelajahi Kue</button>
        </div>
      `;
      if (cartToolbar) cartToolbar.style.display = 'none';
      if (cartDrawerFooter) cartDrawerFooter.style.display = 'none';

      const exploreBtn = document.getElementById('btnCartExplore');
      if (exploreBtn) {
        exploreBtn.addEventListener('click', () => {
          closeCartDrawer();
          const catSection = document.getElementById('katalog');
          if (catSection) catSection.scrollIntoView({ behavior: 'smooth' });
        });
      }
      return;
    }

    if (cartToolbar) cartToolbar.style.display = 'flex';
    if (cartDrawerFooter) cartDrawerFooter.style.display = 'flex';

    const allSelected = cart.every(c => c.selected);
    const selectedItems = cart.filter(c => c.selected);
    const selectedCount = selectedItems.length;

    if (cartSelectAllCheckbox) cartSelectAllCheckbox.checked = allSelected;
    if (cartSelectedCountText) cartSelectedCountText.textContent = `${selectedCount}/${cart.length}`;

    // Total price
    const totalAmount = selectedItems.reduce((sum, item) => sum + (Number(item.price || 0) * (item.qty || 1)), 0);
    if (cartTotalPrice) cartTotalPrice.textContent = formatRupiah(totalAmount);

    if (cartWhatsAppLabel) {
      cartWhatsAppLabel.textContent = selectedCount > 0 
        ? `Tanya Admin via WhatsApp (${selectedCount} Produk Dipilih)` 
        : 'Pilih Produk Terlebih Dahulu';
    }

    if (btnCartWhatsApp) {
      if (selectedCount > 0) {
        btnCartWhatsApp.style.opacity = '1';
        btnCartWhatsApp.style.pointerEvents = 'auto';
      } else {
        btnCartWhatsApp.style.opacity = '0.5';
        btnCartWhatsApp.style.pointerEvents = 'none';
      }
    }

    cartDrawerBody.innerHTML = cart.map(item => `
      <div class="cart-item-row" data-id="${escapeHtml(item.id)}">
        <input type="checkbox" class="cart-item-checkbox" data-id="${escapeHtml(item.id)}" ${item.selected ? 'checked' : ''} aria-label="Pilih ${escapeHtml(item.name)}">
        
        <img src="${escapeHtml(formatImageUrl(item.image))}" alt="${escapeHtml(item.name)}" class="cart-item-thumb">

        <div class="cart-item-info">
          <div class="cart-item-name" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
          <div class="cart-item-meta">${escapeHtml(item.category || 'Cake')}</div>
          <div class="cart-item-price">${formatRupiah(item.price)}</div>
        </div>

        <div class="cart-item-controls">
          <button type="button" class="btn-cart-remove" data-id="${escapeHtml(item.id)}" title="Hapus dari keranjang">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
          </button>

          <div class="cart-qty-box">
            <button type="button" class="btn-qty btn-qty-minus" data-id="${escapeHtml(item.id)}" aria-label="Kurang">-</button>
            <span class="cart-qty-value">${item.qty || 1}</span>
            <button type="button" class="btn-qty btn-qty-plus" data-id="${escapeHtml(item.id)}" aria-label="Tambah">+</button>
          </div>
        </div>
      </div>
    `).join('');

    // Attach Cart Items Events
    cartDrawerBody.querySelectorAll('.cart-item-checkbox').forEach(chk => {
      chk.addEventListener('change', (e) => {
        toggleCartItemSelect(chk.getAttribute('data-id'), e.target.checked);
      });
    });

    cartDrawerBody.querySelectorAll('.btn-qty-minus').forEach(btn => {
      btn.addEventListener('click', () => {
        updateCartItemQty(btn.getAttribute('data-id'), -1);
      });
    });

    cartDrawerBody.querySelectorAll('.btn-qty-plus').forEach(btn => {
      btn.addEventListener('click', () => {
        updateCartItemQty(btn.getAttribute('data-id'), 1);
      });
    });

    cartDrawerBody.querySelectorAll('.btn-cart-remove').forEach(btn => {
      btn.addEventListener('click', () => {
        removeCartItem(btn.getAttribute('data-id'));
      });
    });
  }

  function openCartDrawer() {
    renderCartDrawer();
    if (cartDrawerOverlay) cartDrawerOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeCartDrawer() {
    if (cartDrawerOverlay) cartDrawerOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Cart Triggers
  if (btnCartNav) btnCartNav.addEventListener('click', openCartDrawer);
  if (btnFloatingCart) btnFloatingCart.addEventListener('click', openCartDrawer);
  if (btnCloseCartDrawer) btnCloseCartDrawer.addEventListener('click', closeCartDrawer);

  if (cartDrawerOverlay) {
    cartDrawerOverlay.addEventListener('click', (e) => {
      if (e.target === cartDrawerOverlay) closeCartDrawer();
    });
  }

  if (cartSelectAllCheckbox) {
    cartSelectAllCheckbox.addEventListener('change', (e) => {
      toggleSelectAll(e.target.checked);
    });
  }

  if (btnClearSelectedCart) {
    btnClearSelectedCart.addEventListener('click', clearSelectedCartItems);
  }

  // Add To Cart from Modal
  if (modalAddToCartBtn) {
    modalAddToCartBtn.addEventListener('click', () => {
      if (!activeModalProduct) return;
      addItemToCart(activeModalProduct, 1);
      showToast(`Berhasil menambahkan "${activeModalProduct.name}" ke keranjang! 🛒`);
      closeModal();
      openCartDrawer();
    });
  }

  // Bulk Checkout WhatsApp Button
  if (btnCartWhatsApp) {
    btnCartWhatsApp.addEventListener('click', () => {
      const cart = getCart();
      const selectedItems = cart.filter(c => c.selected);
      if (selectedItems.length === 0) {
        showToast('Pilih setidaknya 1 kue di keranjang!');
        return;
      }

      let cleanPhone = (whatsappNumber || '').replace(/[^0-9]/g, '');
      if (cleanPhone.startsWith('0')) {
        cleanPhone = '62' + cleanPhone.substring(1);
      }

      const catalogUrl = `${window.location.origin}${window.location.pathname}`;

      // Line 1: Catalog root link for WhatsApp rich unfurling
      let msg = `${catalogUrl}\n\n`;
      msg += `Halo Miniecake! ✨🍰\n\n`;
      msg += `Saya tertarik untuk menanyakan ketersediaan beberapa produk dari keranjang belanja saya:\n\n`;

      let totalEst = 0;
      let totalQty = 0;

      selectedItems.forEach((item, index) => {
        const itemUrl = `${catalogUrl}?product=${encodeURIComponent(item.productId)}`;
        const subtotal = Number(item.price || 0) * (item.qty || 1);
        totalEst += subtotal;
        totalQty += (item.qty || 1);

        msg += `${index + 1}. *${item.name}*\n`;
        msg += `   • Link: ${itemUrl}\n`;
        msg += `   • Kategori: ${item.category || 'Cake'}\n`;
        msg += `   • Jumlah: ${item.qty} pcs (${formatRupiah(item.price)}/pcs)\n`;
        msg += `   • Subtotal: ${formatRupiah(subtotal)}\n\n`;
      });

      msg += `*Total Produk Dipilih*: ${selectedItems.length} menu (${totalQty} pcs)\n`;
      msg += `*Estimasi Total*: ${formatRupiah(totalEst)}\n\n`;
      msg += `Apakah pesanan kue di atas ready stock atau bisa dipesan? Mohon informasinya ya min. Terima kasih! 💕`;

      const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(msg)}`;
      window.open(waUrl, '_blank');
    });
  }

  // Close modal events
  if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
  if (productModal) {
    productModal.addEventListener('click', (e) => {
      if (e.target === productModal) closeModal();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && productModal && productModal.classList.contains('active')) {
      closeModal();
    }
  });

  // Modal Favorite Button click
  if (modalFavBtn) {
    modalFavBtn.addEventListener('click', () => {
      if (activeModalProduct) {
        toggleFavorite(activeModalProduct.id);
      }
    });
  }

  // Header Favorites Button click
  if (btnFavNav) {
    btnFavNav.addEventListener('click', () => {
      showFavoritesOnly = !showFavoritesOnly;
      updateFavoriteUI();
      filterAndRender();
      // Scroll to catalog section
      const catSection = document.getElementById('katalog');
      if (catSection) catSection.scrollIntoView({ behavior: 'smooth' });
    });
  }

  // Reset favorites banner button
  if (btnResetFavFilter) {
    btnResetFavFilter.addEventListener('click', () => {
      showFavoritesOnly = false;
      updateFavoriteUI();
      filterAndRender();
    });
  }

  // Search input events
  if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      searchQuery = e.target.value;
      if (searchClear) {
        searchClear.style.display = searchQuery ? 'flex' : 'none';
      }
      debounceTimer = setTimeout(() => {
        filterAndRender();
      }, 200);
    });
  }

  if (searchClear) {
    searchClear.addEventListener('click', () => {
      searchInput.value = '';
      searchQuery = '';
      searchClear.style.display = 'none';
      filterAndRender();
      searchInput.focus();
    });
  }

  // Category filter click event
  if (categoryScroll) {
    categoryScroll.addEventListener('click', (e) => {
      const btn = e.target.closest('.category-btn');
      if (!btn) return;

      categoryScroll.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      currentCategory = btn.getAttribute('data-category') || 'all';
      filterAndRender();
    });
  }

  // ==========================================
  // PROMOTIONAL BANNER SLIDER
  // ==========================================
  const bannerSlider = document.getElementById('bannerSlider');
  if (bannerSlider) {
    const track = bannerSlider.querySelector('.banner-track');
    const slides = bannerSlider.querySelectorAll('.banner-slide');
    const dots = bannerSlider.querySelectorAll('.banner-dot');
    const prevBtn = bannerSlider.querySelector('.banner-arrow-btn.prev');
    const nextBtn = bannerSlider.querySelector('.banner-arrow-btn.next');
    let currentSlide = 0;
    const totalSlides = slides.length;
    let autoSlideTimer = null;

    function goToSlide(index) {
      if (totalSlides <= 1) return;
      currentSlide = (index + totalSlides) % totalSlides;
      if (track) track.style.transform = `translateX(-${currentSlide * 100}%)`;
      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentSlide);
      });
    }

    function startAutoSlide() {
      if (totalSlides <= 1) return;
      stopAutoSlide();
      autoSlideTimer = setInterval(() => {
        goToSlide(currentSlide + 1);
      }, 5000);
    }

    function stopAutoSlide() {
      if (autoSlideTimer) clearInterval(autoSlideTimer);
    }

    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        goToSlide(currentSlide - 1);
        startAutoSlide();
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        goToSlide(currentSlide + 1);
        startAutoSlide();
      });
    }

    dots.forEach((dot, idx) => {
      dot.addEventListener('click', () => {
        goToSlide(idx);
        startAutoSlide();
      });
    });

    bannerSlider.addEventListener('mouseenter', stopAutoSlide);
    bannerSlider.addEventListener('mouseleave', startAutoSlide);

    startAutoSlide();
  }

  // ==========================================
  // DARK / LIGHT THEME ENGINE (DEVICE & MANUAL)
  // ==========================================
  const THEME_STORAGE_KEY = 'miniecake_theme';
  const btnThemeToggle = document.getElementById('btnThemeToggle');

  function getSystemTheme() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function applyTheme(theme, save = true) {
    document.documentElement.setAttribute('data-theme', theme);
    document.body.setAttribute('data-theme', theme);
    if (save) {
      try {
        localStorage.setItem(THEME_STORAGE_KEY, theme);
      } catch (e) {}
    }
  }

  // Initialize theme from storage or system device mode
  const initialTheme = localStorage.getItem(THEME_STORAGE_KEY) || getSystemTheme();
  applyTheme(initialTheme, false);

  // Listen for device theme change (if not overridden by user)
  if (window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
      if (!localStorage.getItem(THEME_STORAGE_KEY)) {
        applyTheme(e.matches ? 'dark' : 'light', false);
      }
    });
  }

  if (btnThemeToggle) {
    btnThemeToggle.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      applyTheme(newTheme, true);
      showToast(newTheme === 'dark' ? 'Mode Gelap (Dark Mode) aktif 🌙' : 'Mode Terang (Light Mode) aktif ☀️');
    });
  }

  // Initial render & Badges
  updateFavoriteUI();
  updateCartBadge();
  filterAndRender();

  // Check URL parameter or Hash for direct product modal open (e.g. ?product=PROD-01 or #PROD-01)
  const urlParams = new URLSearchParams(window.location.search);
  const targetProdId = urlParams.get('product') || urlParams.get('id') || (window.location.hash ? window.location.hash.substring(1) : null);
  if (targetProdId && targetProdId !== 'katalog') {
    setTimeout(() => {
      openProductModal(targetProdId);
    }, 150);
  }
});
