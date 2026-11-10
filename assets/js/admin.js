/**
 * Miniecake - Admin Dashboard JavaScript
 * Handles Product CRUD with Video & Copy Product, Category Manager, Banner Manager, Logo & Contact Settings
 */

document.addEventListener('DOMContentLoaded', () => {
  // State
  let products = window.INITIAL_PRODUCTS || [];
  let categories = window.INITIAL_CATEGORIES || [];
  let banners = window.INITIAL_BANNERS || [];
  let branches = window.INITIAL_BRANCHES || [];
  let settings = window.INITIAL_SETTINGS || {};

  let formImages = [];
  let editingProductId = null;
  let editingBannerId = null;
  let editingBranchId = null;

  // Helper for authenticated API fetch (passes Authorization header + credentials)
  async function authFetch(url, options = {}) {
    const opts = { ...options };
    opts.credentials = opts.credentials || 'same-origin';
    const headers = new Headers(opts.headers || {});
    if (window.ADMIN_TOKEN && !headers.has('Authorization')) {
      headers.set('Authorization', 'Bearer ' + window.ADMIN_TOKEN);
    }
    opts.headers = headers;
    const response = await window.fetch(url, opts);
    if (response.status === 401) {
      showToast('Sesi login telah berakhir. Silakan login kembali.', 'error');
    }
    return response;
  }

  // DOM Elements - Tabs
  const tabButtons = document.querySelectorAll('.admin-tab-btn');
  const tabContents = document.querySelectorAll('.admin-tab-content');

  // DOM Elements - Products
  const productTableBody = document.getElementById('productTableBody');
  const adminTableSearch = document.getElementById('adminTableSearch');
  const adminCategoryFilter = document.getElementById('adminCategoryFilter');
  
  // Stats
  const statTotal = document.getElementById('statTotal');
  const statCategories = document.getElementById('statCategories');
  const statReady = document.getElementById('statReady');
  const statPo = document.getElementById('statPo');

  // Modals - Product
  const productFormModal = document.getElementById('productFormModal');
  const modalFormTitle = document.getElementById('modalFormTitle');
  const productForm = document.getElementById('productForm');
  const btnOpenAddModal = document.getElementById('btnOpenAddModal');
  const btnCloseFormModal = document.getElementById('btnCloseFormModal');
  const btnCancelForm = document.getElementById('btnCancelForm');
  const btnSaveProduct = document.getElementById('btnSaveProduct');

  // Form Inputs - Product
  const formProductId = document.getElementById('formProductId');
  const formName = document.getElementById('formName');
  const formCategory = document.getElementById('formCategory');
  const formPrice = document.getElementById('formPrice');
  const formStatus = document.getElementById('formStatus');
  const formShortDesc = document.getElementById('formShortDesc');
  const formFullDesc = document.getElementById('formFullDesc');
  const formVideoUrl = document.getElementById('formVideoUrl');
  const videoFileInput = document.getElementById('videoFileInput');
  const btnUploadVideo = document.getElementById('btnUploadVideo');
  const videoPreviewWrapper = document.getElementById('videoPreviewWrapper');
  const formVideoPlayer = document.getElementById('formVideoPlayer');
  const btnRemoveVideo = document.getElementById('btnRemoveVideo');

  // Images in Product Form
  const dropArea = document.getElementById('dropArea');
  const fileInput = document.getElementById('fileInput');
  const manualUrlInput = document.getElementById('manualUrlInput');
  const btnAddUrlBtn = document.getElementById('btnAddUrlBtn');
  const previewImagesContainer = document.getElementById('previewImagesContainer');

  // Cropper DOM Elements
  const cropperModal = document.getElementById('cropperModal');
  const btnCloseCropperModal = document.getElementById('btnCloseCropperModal');
  const btnCancelCropper = document.getElementById('btnCancelCropper');
  const btnApplyCropper = document.getElementById('btnApplyCropper');
  const cropperImageElement = document.getElementById('cropperImageElement');
  const btnCropperRotateLeft = document.getElementById('btnCropperRotateLeft');
  const btnCropperRotateRight = document.getElementById('btnCropperRotateRight');
  const btnCropperReset = document.getElementById('btnCropperReset');
  const ratioButtons = document.querySelectorAll('.btn-crop-ratio');

  let cropper = null;
  let activeCroppingIndex = null;
  let draggedImageIndex = null;

  // DOM Elements - Categories
  const categoryItemsList = document.getElementById('categoryItemsList');
  const addCategoryForm = document.getElementById('addCategoryForm');
  const newCategoryName = document.getElementById('newCategoryName');

  // DOM Elements - Banners
  const bannersGrid = document.getElementById('bannersGrid');
  const btnOpenAddBannerModal = document.getElementById('btnOpenAddBannerModal');
  const bannerModal = document.getElementById('bannerModal');
  const bannerModalTitle = document.getElementById('bannerModalTitle');
  const bannerForm = document.getElementById('bannerForm');
  const btnCloseBannerModal = document.getElementById('btnCloseBannerModal');
  const btnCancelBannerModal = document.getElementById('btnCancelBannerModal');
  const formBannerId = document.getElementById('formBannerId');
  const formBannerTitle = document.getElementById('formBannerTitle');
  const formBannerSubtitle = document.getElementById('formBannerSubtitle');
  const formBannerBtnText = document.getElementById('formBannerBtnText');
  const formBannerLink = document.getElementById('formBannerLink');
  const formBannerImage = document.getElementById('formBannerImage');
  const formBannerActive = document.getElementById('formBannerActive');
  const bannerFileInput = document.getElementById('bannerFileInput');
  const btnUploadBannerImg = document.getElementById('btnUploadBannerImg');
  const bannerImgPreviewWrapper = document.getElementById('bannerImgPreviewWrapper');
  const bannerImgPreview = document.getElementById('bannerImgPreview');

  // DOM Elements - Branches
  const branchForm = document.getElementById('branchForm');
  const branchFormTitle = document.getElementById('branchFormTitle');
  const formBranchId = document.getElementById('formBranchId');
  const branchName = document.getElementById('branchName');
  const branchPhone = document.getElementById('branchPhone');
  const branchAddress = document.getElementById('branchAddress');
  const branchIsDefault = document.getElementById('branchIsDefault');
  const btnSubmitBranch = document.getElementById('btnSubmitBranch');
  const btnCancelBranchEdit = document.getElementById('btnCancelBranchEdit');
  const branchItemsList = document.getElementById('branchItemsList');

  // DOM Elements - Settings (Contact & Logo)
  const generalSettingsForm = document.getElementById('generalSettingsForm');
  const logoFileInput = document.getElementById('logoFileInput');
  const btnUploadLogo = document.getElementById('btnUploadLogo');
  const btnResetLogo = document.getElementById('btnResetLogo');
  const settingsLogoPreview = document.getElementById('settingsLogoPreview');
  const adminNavbarLogo = document.getElementById('adminNavbarLogo');
  const formLogoUrl = document.getElementById('formLogoUrl');

  // Guide Modal & Test Connection
  const guideModal = document.getElementById('guideModal');
  const btnOpenGuide = document.getElementById('btnOpenGuide');
  const btnCloseGuideModal = document.getElementById('btnCloseGuideModal');
  const btnCloseGuideModalBtn = document.getElementById('btnCloseGuideModalBtn');
  const btnSyncSheet = document.getElementById('btnSyncSheet');
  const btnTestConnection = document.getElementById('btnTestConnection');
  const adminToast = document.getElementById('adminToast');
  const toastMessage = document.getElementById('toastMessage');

  /**
   * Show Toast Notification
   */
  function showToast(message, type = 'success') {
    if (!adminToast || !toastMessage) return;
    toastMessage.textContent = message;
    adminToast.className = `admin-toast show ${type}`;
    setTimeout(() => {
      adminToast.classList.remove('show');
    }, 3500);
  }

  function formatRupiah(num) {
    return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatImageUrl(url) {
    if (!url) return '';
    const trimmed = String(url).trim();
    if (trimmed.includes('api/image.php')) return trimmed;
    const driveMatch = trimmed.match(/(?:drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?id=)|lh3\.googleusercontent\.com\/d\/)([a-zA-Z0-9_-]+)/);
    if (driveMatch) {
      return '../api/image.php?id=' + driveMatch[1];
    }
    return trimmed;
  }

  // ==========================================
  // TAB NAVIGATION
  // ==========================================
  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-tab');
      tabButtons.forEach(b => b.classList.remove('active'));
      tabContents.forEach(c => c.classList.remove('active'));

      btn.classList.add('active');
      const targetContent = document.getElementById(targetId);
      if (targetContent) targetContent.classList.add('active');
    });
  });

  // ==========================================
  // STATS & CATEGORY DROPDOWN UPDATES
  // ==========================================
  function updateStats() {
    if (statTotal) statTotal.textContent = products.length;
    if (statCategories) statCategories.textContent = categories.length;

    let ready = 0;
    let po = 0;
    products.forEach(p => {
      const st = (p.status || '').toLowerCase();
      if (st.includes('ready')) ready++;
      else if (st.includes('pre-order') || st.includes('po')) po++;
    });

    if (statReady) statReady.textContent = ready;
    if (statPo) statPo.textContent = po;
  }

  function updateCategoryDropdowns() {
    // Product form category select
    if (formCategory) {
      formCategory.innerHTML = categories.map(c => `
        <option value="${escapeHtml(c)}">${escapeHtml(c)}</option>
      `).join('');
    }

    // Table filter category select
    if (adminCategoryFilter) {
      const currentVal = adminCategoryFilter.value;
      adminCategoryFilter.innerHTML = `
        <option value="all">Semua Kategori</option>
        ${categories.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('')}
      `;
      adminCategoryFilter.value = currentVal || 'all';
    }
  }

  // ==========================================
  // PRODUCTS TABLE & CRUD
  // ==========================================
  function renderTable() {
    if (!productTableBody) return;

    const query = (adminTableSearch?.value || '').toLowerCase().trim();
    const cat = adminCategoryFilter?.value || 'all';

    const filtered = products.filter(p => {
      const matchCat = (cat === 'all') || (p.category && p.category.toLowerCase() === cat.toLowerCase());
      const name = (p.name || '').toLowerCase();
      const id = (p.id || '').toLowerCase();
      const category = (p.category || '').toLowerCase();
      const matchQuery = !query || name.includes(query) || id.includes(query) || category.includes(query);
      return matchCat && matchQuery;
    });

    if (filtered.length === 0) {
      productTableBody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align: center; padding: 40px; color: #9ca3af;">
            Tidak ada produk yang sesuai dengan filter pencarian.
          </td>
        </tr>
      `;
      return;
    }

    productTableBody.innerHTML = filtered.map(p => {
      const images = Array.isArray(p.images) ? p.images : [];
      const rawThumb = images[0] || 'https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=150&q=80';
      const thumb = formatImageUrl(rawThumb);
      const isPO = (p.status || '').toLowerCase().includes('pre-order') || (p.status || '').toLowerCase().includes('po');
      const hasVideo = p.video && p.video.trim() !== '';

      return `
        <tr data-id="${escapeHtml(p.id)}">
          <td>
            <div class="product-cell">
              <img src="${escapeHtml(thumb)}" alt="${escapeHtml(p.name)}" class="product-thumb" referrerpolicy="no-referrer">
              <div>
                <div class="product-name-txt">${escapeHtml(p.name)}</div>
                <div class="product-id-txt">${escapeHtml(p.id)}</div>
              </div>
            </div>
          </td>
          <td>
            <span class="admin-badge">${escapeHtml(p.category || 'General')}</span>
          </td>
          <td>
            <span class="price-txt">${formatRupiah(p.price)}</span>
          </td>
          <td>
            <span class="badge badge-status ${isPO ? 'po' : ''}">${escapeHtml(p.status || 'Ready')}</span>
          </td>
          <td>
            <div style="font-weight: 600; color: #6b7280; font-size: 0.8rem;">
              <div>📷 ${images.length} Foto</div>
              ${hasVideo ? '<div style="color: #ec4899; margin-top: 2px;">🎥 Ada Video</div>' : ''}
            </div>
          </td>
          <td style="text-align: right;">
            <div class="action-btns" style="justify-content: flex-end;">
              <!-- View on Web -->
              <a href="../index.php#${escapeHtml(p.id)}" target="_blank" class="btn-icon" title="Lihat di Web">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
              </a>

              <!-- Copy / Duplicate Product -->
              <button type="button" class="btn-icon copy btn-copy" data-id="${escapeHtml(p.id)}" title="Duplikat / Copy Produk">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
              </button>

              <!-- Edit Product -->
              <button type="button" class="btn-icon btn-edit" data-id="${escapeHtml(p.id)}" title="Edit Produk">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
              </button>

              <!-- Delete Product -->
              <button type="button" class="btn-icon delete btn-delete" data-id="${escapeHtml(p.id)}" data-name="${escapeHtml(p.name)}" title="Hapus Produk">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');

    attachTableEvents();
  }

  function attachTableEvents() {
    // Edit
    productTableBody.querySelectorAll('.btn-edit').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openEditModal(id);
      });
    });

    // Copy / Duplicate Product
    productTableBody.querySelectorAll('.btn-copy').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        duplicateProduct(id);
      });
    });

    // Delete
    productTableBody.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name');
        handleDeleteProduct(id, name);
      });
    });
  }

  function moveImageLeft(index) {
    if (index <= 0 || index >= formImages.length) return;
    const temp = formImages[index];
    formImages[index] = formImages[index - 1];
    formImages[index - 1] = temp;
    renderFormImages();
  }

  function moveImageRight(index) {
    if (index < 0 || index >= formImages.length - 1) return;
    const temp = formImages[index];
    formImages[index] = formImages[index + 1];
    formImages[index + 1] = temp;
    renderFormImages();
  }

  function renderFormImages() {
    if (!previewImagesContainer) return;

    if (formImages.length === 0) {
      previewImagesContainer.innerHTML = '';
      return;
    }

    const lastIdx = formImages.length - 1;

    previewImagesContainer.innerHTML = formImages.map((url, idx) => {
      const isFront = (idx === lastIdx);
      return `
        <div class="preview-img-item ${isFront ? 'is-front' : ''}" data-index="${idx}" draggable="true" title="Klik foto untuk memotong (crop)">
          ${isFront 
            ? `<span class="badge-cover-front">⭐ Terdepan (Sampul)</span>` 
            : `<span class="badge-img-order">#${idx + 1}</span>`
          }
          <span class="remove-img-btn" data-action="remove-img" data-index="${idx}" title="Hapus Foto">&times;</span>
          <img src="${escapeHtml(formatImageUrl(url))}" alt="Preview ${idx+1}" referrerpolicy="no-referrer">
          <span class="crop-hint-badge">✂️ Klik untuk Crop</span>
          <div class="img-shift-bar" onclick="event.stopPropagation()">
            <button type="button" class="btn-shift-img" data-action="shift-left" data-index="${idx}" ${idx === 0 ? 'disabled' : ''} title="Geser ke Kiri">◀</button>
            <button type="button" class="btn-shift-img" data-action="shift-right" data-index="${idx}" ${idx === lastIdx ? 'disabled' : ''} title="Geser ke Kanan (Lebih Depan)">▶</button>
          </div>
        </div>
      `;
    }).join('');

    // Remove buttons
    previewImagesContainer.querySelectorAll('[data-action="remove-img"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        formImages.splice(index, 1);
        renderFormImages();
      });
    });

    // Shift left buttons
    previewImagesContainer.querySelectorAll('[data-action="shift-left"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        moveImageLeft(index);
      });
    });

    // Shift right buttons
    previewImagesContainer.querySelectorAll('[data-action="shift-right"]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        moveImageRight(index);
      });
    });

    // Click item to Crop & Drag & Drop
    previewImagesContainer.querySelectorAll('.preview-img-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('.remove-img-btn') || e.target.closest('.img-shift-bar')) return;
        const index = parseInt(item.getAttribute('data-index'), 10);
        openCropper(index);
      });

      item.addEventListener('dragstart', (e) => {
        draggedImageIndex = parseInt(item.getAttribute('data-index'), 10);
        e.dataTransfer.effectAllowed = 'move';
        item.style.opacity = '0.4';
      });

      item.addEventListener('dragend', () => {
        item.style.opacity = '1';
        draggedImageIndex = null;
      });

      item.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });

      item.addEventListener('drop', (e) => {
        e.preventDefault();
        const targetIndex = parseInt(item.getAttribute('data-index'), 10);
        if (draggedImageIndex !== null && draggedImageIndex !== targetIndex) {
          const moved = formImages.splice(draggedImageIndex, 1)[0];
          formImages.splice(targetIndex, 0, moved);
          renderFormImages();
        }
      });
    });
  }

  // ==========================================
  // IMAGE CROPPER LOGIC
  // ==========================================
  function openCropper(index) {
    if (index < 0 || index >= formImages.length) return;
    activeCroppingIndex = index;
    const imgUrl = formImages[index];

    const cleanUrl = formatImageUrl(imgUrl);
    cropperImageElement.src = cleanUrl;

    if (cropperModal) cropperModal.classList.add('active');

    if (cropper) {
      cropper.destroy();
      cropper = null;
    }

    const initCropper = () => {
      if (typeof Cropper !== 'undefined') {
        cropper = new Cropper(cropperImageElement, {
          aspectRatio: 1, // 1:1 default
          viewMode: 1,
          autoCropArea: 0.9,
          responsive: true,
          restore: false,
          checkCrossOrigin: false,
        });
      }
    };

    if (cropperImageElement.complete) {
      initCropper();
    } else {
      cropperImageElement.onload = () => {
        initCropper();
        cropperImageElement.onload = null;
      };
    }
  }

  function closeCropper() {
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    activeCroppingIndex = null;
    if (cropperModal) cropperModal.classList.remove('active');
  }

  if (btnCloseCropperModal) btnCloseCropperModal.addEventListener('click', closeCropper);
  if (btnCancelCropper) btnCancelCropper.addEventListener('click', closeCropper);
  if (cropperModal) {
    cropperModal.addEventListener('click', (e) => {
      if (e.target === cropperModal) closeCropper();
    });
  }

  ratioButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      ratioButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const ratio = parseFloat(btn.getAttribute('data-ratio'));
      if (cropper) {
        cropper.setAspectRatio(isNaN(ratio) ? NaN : ratio);
      }
    });
  });

  if (btnCropperRotateLeft) {
    btnCropperRotateLeft.addEventListener('click', () => {
      if (cropper) cropper.rotate(-90);
    });
  }

  if (btnCropperRotateRight) {
    btnCropperRotateRight.addEventListener('click', () => {
      if (cropper) cropper.rotate(90);
    });
  }

  if (btnCropperReset) {
    btnCropperReset.addEventListener('click', () => {
      if (cropper) cropper.reset();
    });
  }

  if (btnApplyCropper) {
    btnApplyCropper.addEventListener('click', async () => {
      if (!cropper || activeCroppingIndex === null) return;

      btnApplyCropper.disabled = true;
      btnApplyCropper.textContent = 'Memproses...';

      try {
        const canvas = cropper.getCroppedCanvas({
          maxWidth: 1600,
          maxHeight: 1600,
          fillColor: '#fff',
          imageSmoothingEnabled: true,
          imageSmoothingQuality: 'high',
        });

        if (!canvas) {
          throw new Error('Gagal merender kanvas potongan gambar.');
        }

        canvas.toBlob(async (blob) => {
          if (!blob) {
            btnApplyCropper.disabled = false;
            btnApplyCropper.textContent = '✂️ Terapkan & Simpan Potongan';
            showToast('Gagal memproses potongan foto.', 'error');
            return;
          }

          const file = new File([blob], `cropped_${Date.now()}.jpg`, { type: 'image/jpeg' });
          const formData = new FormData();
          formData.append('image', file);
          showToast('Mengunggah hasil crop...', 'success');

          try {
            const response = await authFetch('../api/upload.php', { method: 'POST', body: formData });
            const res = await response.json();
            if (res.success && res.data?.url) {
              formImages[activeCroppingIndex] = res.data.url;
              renderFormImages();
              closeCropper();
              showToast('Foto berhasil dipotong (crop) dan diperbarui! ✨', 'success');
            } else {
              showToast(res.message || 'Gagal menyimpan hasil crop.', 'error');
            }
          } catch (err) {
            showToast('Kesalahan upload crop: ' + err.message, 'error');
          } finally {
            btnApplyCropper.disabled = false;
            btnApplyCropper.textContent = '✂️ Terapkan & Simpan Potongan';
          }
        }, 'image/jpeg', 0.92);
      } catch (err) {
        btnApplyCropper.disabled = false;
        btnApplyCropper.textContent = '✂️ Terapkan & Simpan Potongan';
        showToast('Gagal memproses crop: ' + err.message, 'error');
      }
    });
  }

  function updateVideoPreview(url) {
    if (!videoPreviewWrapper || !formVideoPlayer) return;
    if (url && url.trim() !== '') {
      formVideoPlayer.src = url;
      videoPreviewWrapper.style.display = 'block';
    } else {
      formVideoPlayer.pause();
      formVideoPlayer.src = '';
      videoPreviewWrapper.style.display = 'none';
    }
  }

  function openAddModal() {
    editingProductId = null;
    modalFormTitle.textContent = 'Tambah Produk Baru';
    productForm.reset();
    formProductId.value = '';
    formImages = [];
    formVideoUrl.value = '';
    updateVideoPreview('');
    renderFormImages();
    productFormModal.classList.add('active');
  }

  function openEditModal(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;

    editingProductId = productId;
    modalFormTitle.textContent = 'Edit Produk: ' + product.name;
    
    formProductId.value = product.id;
    formName.value = product.name || '';
    formCategory.value = product.category || categories[0] || '';
    formPrice.value = product.price || 0;
    formStatus.value = product.status || 'Ready Stock';
    formShortDesc.value = product.short_desc || '';
    formFullDesc.value = product.full_desc || '';

    formVideoUrl.value = product.video || '';
    updateVideoPreview(product.video || '');

    // Reverse images so rightmost is the front cover in admin
    formImages = Array.isArray(product.images) ? [...product.images].reverse() : [];
    renderFormImages();

    productFormModal.classList.add('active');
  }

  /**
   * Duplicate / Copy Product
   * Clones all details, clears ID so it creates new, appends "(Copy)" to name
   */
  function duplicateProduct(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;

    editingProductId = null; // Create mode!
    modalFormTitle.textContent = '📋 Duplikat Produk: ' + product.name;

    formProductId.value = ''; // Generate fresh ID
    formName.value = product.name + ' (Copy)';
    formCategory.value = product.category || categories[0] || '';
    formPrice.value = product.price || 0;
    formStatus.value = product.status || 'Ready Stock';
    formShortDesc.value = product.short_desc || '';
    formFullDesc.value = product.full_desc || '';

    formVideoUrl.value = product.video || '';
    updateVideoPreview(product.video || '');

    // Copy images reversed for admin display
    formImages = Array.isArray(product.images) ? [...product.images].reverse() : [];
    renderFormImages();

    productFormModal.classList.add('active');
    formName.focus();
    formName.select();

    showToast('Produk diduplikat! Silakan ganti nama dan foto sesuai keinginan.', 'success');
  }

  function closeFormModal() {
    productFormModal.classList.remove('active');
    editingProductId = null;
    formImages = [];
    updateVideoPreview('');
  }

  // Upload Product Photo
  if (dropArea && fileInput) {
    dropArea.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', async () => {
      const files = fileInput.files;
      if (!files || files.length === 0) return;

      showToast(`Mengunggah ${files.length} foto...`, 'success');

      for (let i = 0; i < files.length; i++) {
        const formData = new FormData();
        formData.append('image', files[i]);

        try {
          const response = await authFetch('../api/upload.php', { method: 'POST', body: formData });
          const res = await response.json();
          if (res.success && res.data?.url) {
            formImages.push(res.data.url);
            renderFormImages();
          } else {
            showToast(res.message || 'Gagal mengunggah salah satu foto.', 'error');
          }
        } catch (err) {
          showToast('Kesalahan upload: ' + err.message, 'error');
        }
      }

      fileInput.value = '';
      showToast('Foto berhasil ditambahkan!', 'success');
    });
  }

  if (btnAddUrlBtn && manualUrlInput) {
    btnAddUrlBtn.addEventListener('click', () => {
      const url = manualUrlInput.value.trim();
      if (!url) return;
      formImages.push(url);
      renderFormImages();
      manualUrlInput.value = '';
      showToast('URL foto ditambahkan!');
    });
  }

  // Upload Product Video
  if (btnUploadVideo && videoFileInput) {
    btnUploadVideo.addEventListener('click', () => videoFileInput.click());

    videoFileInput.addEventListener('change', async () => {
      const file = videoFileInput.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('video', file);
      showToast('Mengunggah video produk...', 'success');

      try {
        const response = await authFetch('../api/upload.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.success && res.data?.url) {
          formVideoUrl.value = res.data.url;
          updateVideoPreview(res.data.url);
          showToast(res.message || 'Video berhasil diunggah!', 'success');
        } else {
          showToast(res.message || 'Gagal mengunggah video.', 'error');
        }
      } catch (err) {
        showToast('Kesalahan upload video: ' + err.message, 'error');
      } finally {
        videoFileInput.value = '';
      }
    });
  }

  if (formVideoUrl) {
    formVideoUrl.addEventListener('input', () => {
      updateVideoPreview(formVideoUrl.value.trim());
    });
  }

  if (btnRemoveVideo) {
    btnRemoveVideo.addEventListener('click', () => {
      formVideoUrl.value = '';
      updateVideoPreview('');
      showToast('Video produk dihapus.');
    });
  }

  // Submit Product Form
  if (productForm) {
    productForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        id: formProductId.value,
        name: formName.value.trim(),
        category: formCategory.value.trim(),
        price: parseFloat(formPrice.value) || 0,
        status: formStatus.value,
        short_desc: formShortDesc.value.trim(),
        full_desc: formFullDesc.value.trim(),
        video: formVideoUrl.value.trim(),
        images: formImages.length > 0 ? [...formImages].reverse() : [
          'https://images.unsplash.com/photo-1535141192574-5d4897c13136?auto=format&fit=crop&w=800&q=80'
        ]
      };

      const isUpdate = !!editingProductId;
      payload._action = isUpdate ? 'update' : 'create';

      btnSaveProduct.disabled = true;
      btnSaveProduct.textContent = 'Menyimpan...';

      try {
        const response = await authFetch('../api/products.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        const res = await response.json();
        if (res.success && res.data) {
          if (isUpdate) {
            const index = products.findIndex(p => p.id === editingProductId);
            if (index !== -1) products[index] = res.data;
            showToast('Produk berhasil diperbarui! 🎉');
          } else {
            products.unshift(res.data);
            showToast('Produk baru berhasil disimpan! 🎉');
          }

          // Register category if new
          if (!categories.includes(payload.category)) {
            categories.push(payload.category);
            updateCategoryDropdowns();
            renderCategoriesList();
          }

          updateStats();
          renderTable();
          closeFormModal();
        } else {
          showToast(res.message || 'Gagal menyimpan produk.', 'error');
        }
      } catch (err) {
        showToast('Terjadi kesalahan: ' + err.message, 'error');
      } finally {
        btnSaveProduct.disabled = false;
        btnSaveProduct.textContent = 'Simpan Produk';
      }
    });
  }

  // Delete Product
  async function handleDeleteProduct(productId, productName) {
    const confirmDelete = confirm(`Apakah Anda yakin ingin menghapus produk "${productName}"?`);
    if (!confirmDelete) return;

    try {
      const response = await authFetch('../api/products.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _action: 'delete', id: productId })
      });

      const res = await response.json();
      if (res.success) {
        products = products.filter(p => p.id !== productId);
        updateStats();
        renderTable();
        renderCategoriesList();
        showToast('Produk berhasil dihapus.', 'success');
      } else {
        showToast(res.message || 'Gagal menghapus produk.', 'error');
      }
    } catch (err) {
      showToast('Terjadi kesalahan: ' + err.message, 'error');
    }
  }

  // ==========================================
  // CATEGORY MANAGEMENT
  // ==========================================
  const catFormTitle = document.getElementById('catFormTitle');
  const editCategoryOldName = document.getElementById('editCategoryOldName');
  const btnSubmitCategory = document.getElementById('btnSubmitCategory');
  const btnCancelCategoryEdit = document.getElementById('btnCancelCategoryEdit');

  function resetCategoryForm() {
    if (addCategoryForm) addCategoryForm.reset();
    if (editCategoryOldName) editCategoryOldName.value = '';
    if (catFormTitle) catFormTitle.textContent = '+ Tambah Kategori Baru';
    if (btnSubmitCategory) btnSubmitCategory.querySelector('span').textContent = 'Simpan Kategori';
    if (btnCancelCategoryEdit) btnCancelCategoryEdit.style.display = 'none';
  }

  if (btnCancelCategoryEdit) {
    btnCancelCategoryEdit.addEventListener('click', resetCategoryForm);
  }

  function renderCategoriesList() {
    if (!categoryItemsList) return;

    categoryItemsList.innerHTML = categories.map(cat => {
      const count = products.filter(p => (p.category || '').toLowerCase() === cat.toLowerCase()).length;
      return `
        <div class="cat-item-row" data-category="${escapeHtml(cat)}">
          <div class="cat-item-name">
            <span>🍰 ${escapeHtml(cat)}</span>
            <span class="cat-item-count">${count} Produk</span>
          </div>
          <div class="action-btns">
            <button type="button" class="btn-icon btn-rename-cat" data-name="${escapeHtml(cat)}" title="Ubah Nama Kategori">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            </button>
            <button type="button" class="btn-icon delete btn-delete-cat" data-name="${escapeHtml(cat)}" title="Hapus Kategori">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            </button>
          </div>
        </div>
      `;
    }).join('');

    // Attach Rename / Edit Event
    categoryItemsList.querySelectorAll('.btn-rename-cat').forEach(btn => {
      btn.addEventListener('click', () => {
        const oldName = btn.getAttribute('data-name');
        if (newCategoryName) newCategoryName.value = oldName;
        if (editCategoryOldName) editCategoryOldName.value = oldName;
        if (catFormTitle) catFormTitle.textContent = `Edit Kategori: "${oldName}"`;
        if (btnSubmitCategory) btnSubmitCategory.querySelector('span').textContent = 'Perbarui Kategori';
        if (btnCancelCategoryEdit) btnCancelCategoryEdit.style.display = 'block';

        if (newCategoryName) {
          newCategoryName.focus();
          newCategoryName.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    });

    // Attach Delete Event
    categoryItemsList.querySelectorAll('.btn-delete-cat').forEach(btn => {
      btn.addEventListener('click', async () => {
        const name = btn.getAttribute('data-name');
        const confirmDel = confirm(`Hapus kategori "${name}"? Produk yang menggunakan kategori ini tidak akan terhapus.`);
        if (!confirmDel) return;

        try {
          const response = await authFetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _action: 'delete_category', name: name })
          });
          const res = await response.json();
          if (res.success) {
            categories = res.data;
            if (editCategoryOldName && editCategoryOldName.value === name) {
              resetCategoryForm();
            }
            updateCategoryDropdowns();
            renderCategoriesList();
            showToast(`Kategori "${name}" berhasil dihapus.`);
          } else {
            showToast(res.message || 'Gagal menghapus kategori.', 'error');
          }
        } catch (err) {
          showToast('Error: ' + err.message, 'error');
        }
      });
    });
  }

  // Add / Update Category Submit
  if (addCategoryForm) {
    addCategoryForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const name = newCategoryName.value.trim();
      const oldName = editCategoryOldName ? editCategoryOldName.value.trim() : '';
      if (!name) return;

      const isEditing = !!oldName;
      const payload = isEditing 
        ? { _action: 'update_category', old_name: oldName, new_name: name }
        : { _action: 'add_category', name: name };

      try {
        const response = await authFetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
          categories = res.data;
          
          if (isEditing) {
            // Update local products categories too
            products.forEach(p => {
              if ((p.category || '').toLowerCase() === oldName.toLowerCase()) {
                p.category = name;
              }
            });
            renderTable();
          }

          resetCategoryForm();
          updateCategoryDropdowns();
          renderCategoriesList();
          updateStats();
          showToast(isEditing ? `Kategori "${oldName}" berhasil diubah menjadi "${name}".` : `Kategori "${name}" berhasil ditambahkan! 🎉`);
        } else {
          showToast(res.message || 'Gagal menyimpan kategori.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      }
    });
  }

  // ==========================================
  // BANNER PROMO MANAGEMENT
  // ==========================================
  function renderBannersGrid() {
    if (!bannersGrid) return;

    if (banners.length === 0) {
      bannersGrid.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: #fff; border-radius: 14px; border: 1.5px dashed #fbcfe8;">
          <div style="font-size: 2.5rem; margin-bottom: 8px;">🖼️</div>
          <h3 style="font-weight: 700; color: #1f2937;">Belum ada banner promosi</h3>
          <p style="color: #6b7280; font-size: 0.9rem;">Klik tombol "Tambah Banner Baru" di atas untuk menambahkan banner ke halaman utama.</p>
        </div>
      `;
      return;
    }

    bannersGrid.innerHTML = banners.map(b => {
      const active = !!b.active;
      return `
        <div class="banner-admin-card" data-id="${escapeHtml(b.id)}">
          <div class="banner-admin-preview">
            <img src="${escapeHtml(formatImageUrl(b.image || 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=800&q=80'))}" alt="${escapeHtml(b.title)}" referrerpolicy="no-referrer">
          </div>
          <div class="banner-admin-body">
            <div style="margin-bottom: 6px;">
              <span class="badge ${active ? 'badge-status' : 'badge-status po'}">${active ? 'Aktif' : 'Non-Aktif'}</span>
            </div>
            <h4 class="banner-admin-title">${escapeHtml(b.title)}</h4>
            <p class="banner-admin-desc">${escapeHtml(b.subtitle || '')}</p>
            <div class="banner-admin-footer">
              <span style="font-size: 0.8rem; color: #6b7280;">Tombol: <strong>${escapeHtml(b.button_text || 'Lihat')}</strong></span>
              <div class="action-btns">
                <button type="button" class="btn-icon btn-edit-banner" data-id="${escapeHtml(b.id)}" title="Edit Banner">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                </button>
                <button type="button" class="btn-icon delete btn-delete-banner" data-id="${escapeHtml(b.id)}" title="Hapus Banner">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Attach Banner Edit
    bannersGrid.querySelectorAll('.btn-edit-banner').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        openEditBannerModal(id);
      });
    });

    // Attach Banner Delete
    bannersGrid.querySelectorAll('.btn-delete-banner').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        if (!confirm('Apakah Anda yakin ingin menghapus banner ini?')) return;

        try {
          const response = await authFetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _action: 'delete_banner', id: id })
          });
          const res = await response.json();
          if (res.success) {
            banners = res.data;
            renderBannersGrid();
            showToast('Banner berhasil dihapus.');
          } else {
            showToast(res.message || 'Gagal menghapus banner.', 'error');
          }
        } catch (err) {
          showToast('Error: ' + err.message, 'error');
        }
      });
    });
  }

  function openAddBannerModal() {
    editingBannerId = null;
    bannerModalTitle.textContent = 'Tambah Banner Baru';
    bannerForm.reset();
    formBannerId.value = '';
    formBannerActive.checked = true;
    bannerImgPreviewWrapper.style.display = 'none';
    bannerModal.classList.add('active');
  }

  function openEditBannerModal(id) {
    const b = banners.find(item => item.id === id);
    if (!b) return;

    editingBannerId = id;
    bannerModalTitle.textContent = 'Edit Banner: ' + b.title;
    formBannerId.value = b.id;
    formBannerTitle.value = b.title || '';
    formBannerSubtitle.value = b.subtitle || '';
    formBannerBtnText.value = b.button_text || 'Lihat Menu Kue';
    formBannerLink.value = b.link || '#katalog';
    formBannerImage.value = b.image || '';
    formBannerActive.checked = !!b.active;

    if (b.image) {
      bannerImgPreview.src = b.image;
      bannerImgPreviewWrapper.style.display = 'block';
    } else {
      bannerImgPreviewWrapper.style.display = 'none';
    }

    bannerModal.classList.add('active');
  }

  function closeBannerModal() {
    bannerModal.classList.remove('active');
    editingBannerId = null;
  }

  if (btnOpenAddBannerModal) btnOpenAddBannerModal.addEventListener('click', openAddBannerModal);
  if (btnCloseBannerModal) btnCloseBannerModal.addEventListener('click', closeBannerModal);
  if (btnCancelBannerModal) btnCancelBannerModal.addEventListener('click', closeBannerModal);

  // Upload Banner Image
  if (btnUploadBannerImg && bannerFileInput) {
    btnUploadBannerImg.addEventListener('click', () => bannerFileInput.click());

    bannerFileInput.addEventListener('change', async () => {
      const file = bannerFileInput.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('image', file);
      showToast('Mengunggah gambar banner...', 'success');

      try {
        const response = await authFetch('../api/upload.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.success && res.data?.url) {
          formBannerImage.value = res.data.url;
          bannerImgPreview.src = res.data.url;
          bannerImgPreviewWrapper.style.display = 'block';
          showToast('Gambar banner berhasil diunggah!');
        } else {
          showToast(res.message || 'Gagal mengunggah gambar banner.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      } finally {
        bannerFileInput.value = '';
      }
    });
  }

  if (formBannerImage) {
    formBannerImage.addEventListener('input', () => {
      const url = formBannerImage.value.trim();
      if (url) {
        bannerImgPreview.src = url;
        bannerImgPreviewWrapper.style.display = 'block';
      } else {
        bannerImgPreviewWrapper.style.display = 'none';
      }
    });
  }

  // Submit Banner Form
  if (bannerForm) {
    bannerForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        _action: 'save_banner',
        id: formBannerId.value,
        title: formBannerTitle.value.trim(),
        subtitle: formBannerSubtitle.value.trim(),
        button_text: formBannerBtnText.value.trim(),
        link: formBannerLink.value.trim(),
        image: formBannerImage.value.trim(),
        active: formBannerActive.checked
      };

      try {
        const response = await authFetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
          banners = res.data;
          renderBannersGrid();
          closeBannerModal();
          showToast('Banner berhasil disimpan! ✨');
        } else {
          showToast(res.message || 'Gagal menyimpan banner.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      }
    });
  }

  // ==========================================
  // BRANCH / CABANG & WHATSAPP MANAGEMENT
  // ==========================================
  function resetBranchForm() {
    editingBranchId = null;
    if (branchForm) branchForm.reset();
    if (formBranchId) formBranchId.value = '';
    if (branchFormTitle) branchFormTitle.textContent = '+ Tambah Cabang Baru';
    if (btnSubmitBranch) btnSubmitBranch.innerHTML = '<span>Simpan Cabang</span>';
    if (btnCancelBranchEdit) btnCancelBranchEdit.style.display = 'none';
  }

  function renderBranchesList() {
    if (!branchItemsList) return;

    if (branches.length === 0) {
      branchItemsList.innerHTML = `
        <div style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 0.9rem;">
          Belum ada cabang terdaftar. Tambahkan cabang pertama menggunakan form di sebelah kiri.
        </div>
      `;
      return;
    }

    branchItemsList.innerHTML = branches.map(b => {
      const isDef = !!b.is_default;
      let cleanPhone = (b.whatsapp_number || '').replace(/[^0-9]/g, '');
      if (cleanPhone.startsWith('0')) cleanPhone = '62' + cleanPhone.substring(1);

      return `
        <div class="branch-admin-card ${isDef ? 'is-default' : ''}" data-id="${escapeHtml(b.id)}">
          <div class="branch-card-header">
            <div class="branch-card-title">
              <span>${escapeHtml(b.name)}</span>
              ${isDef ? '<span class="branch-default-badge">★ Cabang Utama</span>' : ''}
            </div>
            <div class="action-btns">
              <button type="button" class="btn-icon btn-edit-branch" data-id="${escapeHtml(b.id)}" title="Edit Cabang">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
              </button>
              ${branches.length > 1 ? `
                <button type="button" class="btn-icon delete btn-delete-branch" data-id="${escapeHtml(b.id)}" title="Hapus Cabang">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                </button>
              ` : ''}
            </div>
          </div>

          <div class="branch-card-info">
            <div class="branch-info-row">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              <span>${escapeHtml(b.address || '-')}</span>
            </div>
            <div class="branch-info-row">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              <a href="https://wa.me/${cleanPhone}" target="_blank" rel="noopener noreferrer" class="branch-phone-link">
                +${cleanPhone} ↗ (Tes Chat)
              </a>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Attach Edit Handlers
    branchItemsList.querySelectorAll('.btn-edit-branch').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const branch = branches.find(b => b.id === id);
        if (!branch) return;

        editingBranchId = id;
        formBranchId.value = branch.id;
        branchName.value = branch.name || '';
        branchPhone.value = branch.whatsapp_number || '';
        branchAddress.value = branch.address || '';
        branchIsDefault.checked = !!branch.is_default;

        branchFormTitle.textContent = '✏️ Edit Cabang: ' + branch.name;
        btnSubmitBranch.innerHTML = '<span>Simpan Perubahan Cabang</span>';
        btnCancelBranchEdit.style.display = 'inline-flex';

        branchName.focus();
        branchForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
    });

    // Attach Delete Handlers
    branchItemsList.querySelectorAll('.btn-delete-branch').forEach(btn => {
      btn.addEventListener('click', async () => {
        const id = btn.getAttribute('data-id');
        const branch = branches.find(b => b.id === id);
        if (!branch) return;

        if (!confirm(`Apakah Anda yakin ingin menghapus "${branch.name}"?`)) return;

        try {
          const response = await authFetch('../api/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _action: 'delete_branch', id: id })
          });
          const res = await response.json();
          if (res.success) {
            branches = res.data;
            if (editingBranchId === id) resetBranchForm();
            renderBranchesList();
            showToast('Cabang berhasil dihapus.');
          } else {
            showToast(res.message || 'Gagal menghapus cabang.', 'error');
          }
        } catch (err) {
          showToast('Error: ' + err.message, 'error');
        }
      });
    });
  }

  // Branch Form Submit
  if (branchForm) {
    branchForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        _action: 'save_branch',
        id: formBranchId.value.trim(),
        name: branchName.value.trim(),
        whatsapp_number: branchPhone.value.trim(),
        address: branchAddress.value.trim(),
        is_default: branchIsDefault.checked
      };

      try {
        const response = await authFetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
          branches = res.data;
          resetBranchForm();
          renderBranchesList();
          showToast('Data cabang berhasil disimpan! 📍');
        } else {
          showToast(res.message || 'Gagal menyimpan cabang.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      }
    });
  }

  if (btnCancelBranchEdit) {
    btnCancelBranchEdit.addEventListener('click', resetBranchForm);
  }

  // ==========================================
  // LOGO & CONTACT SETTINGS
  // ==========================================
  if (btnUploadLogo && logoFileInput) {
    btnUploadLogo.addEventListener('click', () => logoFileInput.click());

    logoFileInput.addEventListener('change', async () => {
      const file = logoFileInput.files[0];
      if (!file) return;

      const formData = new FormData();
      formData.append('image', file);
      formData.append('type', 'logo');
      showToast('Mengunggah logo...', 'success');

      try {
        const response = await authFetch('../api/upload.php', { method: 'POST', body: formData });
        const res = await response.json();
        if (res.success && res.data?.url) {
          formLogoUrl.value = res.data.url;
          if (settingsLogoPreview) settingsLogoPreview.src = res.data.url;
          if (adminNavbarLogo) adminNavbarLogo.src = res.data.url;
          showToast('Logo berhasil diunggah! Jangan lupa klik Simpan Pengaturan.', 'success');
        } else {
          showToast(res.message || 'Gagal mengunggah logo.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      } finally {
        logoFileInput.value = '';
      }
    });
  }

  if (btnResetLogo) {
    btnResetLogo.addEventListener('click', () => {
      formLogoUrl.value = 'assets/images/logo.svg';
      if (settingsLogoPreview) settingsLogoPreview.src = '../assets/images/logo.svg';
      if (adminNavbarLogo) adminNavbarLogo.src = '../assets/images/logo.svg';
      showToast('Logo dikembalikan ke logo bawaan. Klik Simpan Pengaturan.');
    });
  }

  if (generalSettingsForm) {
    generalSettingsForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const payload = {
        _action: 'update_general',
        app_name: document.getElementById('settingAppName').value.trim(),
        app_tagline: document.getElementById('settingAppTagline').value.trim(),
        logo_url: formLogoUrl.value.trim(),
        whatsapp_number: document.getElementById('settingWhatsapp').value.trim(),
        instagram_url: document.getElementById('settingInstagram').value.trim(),
        facebook_url: document.getElementById('settingFacebook').value.trim(),
        tiktok_url: document.getElementById('settingTiktok').value.trim(),
        address: document.getElementById('settingAddress').value.trim(),
        opening_hours: document.getElementById('settingOpeningHours').value.trim()
      };

      try {
        const response = await authFetch('../api/settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const res = await response.json();
        if (res.success) {
          settings = res.data;
          showToast('Pengaturan kontak & logo website berhasil disimpan! 🎉');
        } else {
          showToast(res.message || 'Gagal menyimpan pengaturan.', 'error');
        }
      } catch (err) {
        showToast('Error: ' + err.message, 'error');
      }
    });
  }

  // ==========================================
  // SYNC & TEST CONNECTION
  // ==========================================
  if (btnSyncSheet) {
    btnSyncSheet.addEventListener('click', async () => {
      btnSyncSheet.disabled = true;
      btnSyncSheet.textContent = 'Sinkronisasi...';

      try {
        const response = await authFetch('../api/products.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ _action: 'sync_sheet' })
        });
        const res = await response.json();
        if (res.success) {
          showToast(res.message || 'Berhasil sinkronisasi ke Google Sheet!', 'success');
        } else {
          showToast(res.message || 'Gagal sinkronisasi.', 'error');
        }
      } catch (err) {
        showToast('Gagal: ' + err.message, 'error');
      } finally {
        btnSyncSheet.disabled = false;
        btnSyncSheet.innerHTML = `
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          <span>Ekspor ke Sheet</span>
        `;
      }
    });
  }

  if (btnTestConnection) {
    btnTestConnection.addEventListener('click', async () => {
      btnTestConnection.textContent = 'Memeriksa...';
      try {
        const response = await authFetch('../api/check-connection.php');
        const res = await response.json();
        if (res.success) {
          const d = res.data;
          let msg = `Sheets: ${d.sheets_connected ? '✅ Terhubung' : '⚠️ ' + (d.sheets_configured ? 'Koneksi Gagal' : 'Belum Dikonfigurasi di .env')}\n` +
                    `Drive: ${d.drive_connected ? '✅ Terhubung' : '⚠️ ' + (d.drive_configured ? 'Koneksi Gagal' : 'Belum Dikonfigurasi di .env')}`;
          if (d.error) msg += `\n\nDetail error: ${d.error}`;
          alert(msg);
        }
      } catch (err) {
        alert('Gagal mengecek koneksi: ' + err.message);
      } finally {
        btnTestConnection.textContent = '⚡ Test Koneksi Google';
      }
    });
  }

  // Guide Modal Events
  if (btnOpenGuide) btnOpenGuide.addEventListener('click', () => guideModal.classList.add('active'));
  if (btnCloseGuideModal) btnCloseGuideModal.addEventListener('click', () => guideModal.classList.remove('active'));
  if (btnCloseGuideModalBtn) btnCloseGuideModalBtn.addEventListener('click', () => guideModal.classList.remove('active'));

  // Modals close on background click
  [productFormModal, bannerModal, guideModal].forEach(m => {
    if (m) {
      m.addEventListener('click', (e) => {
        if (e.target === m) m.classList.remove('active');
      });
    }
  });

  if (btnOpenAddModal) btnOpenAddModal.addEventListener('click', openAddModal);
  if (btnCloseFormModal) btnCloseFormModal.addEventListener('click', closeFormModal);
  if (btnCancelForm) btnCancelForm.addEventListener('click', closeFormModal);

  // Search & Filter Events in Product Table
  if (adminTableSearch) adminTableSearch.addEventListener('input', () => renderTable());
  if (adminCategoryFilter) adminCategoryFilter.addEventListener('change', () => renderTable());

  // Initial renders
  updateCategoryDropdowns();
  renderCategoriesList();
  renderBannersGrid();
  renderBranchesList();
  renderTable();
  updateStats();
});
