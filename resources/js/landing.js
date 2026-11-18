/* ==============================================================================
   landing.js - KoperasiKu Interaktivitas Storefront & Pemesanan Anggota
   - Manajemen keranjang belanja (Offcanvas Drawer)
   - Filter pencarian langsung (Live Client-Side Search & Category Filter)
   - Alur checkout mandiri & penerbitan Kode Pickup unik
   - Fitur Salin Kode Pickup ke clipboard
   - Navigasi responsif
   ============================================================================== */

// State Keranjang Belanja di SessionStorage
let cart = [];

try {
    const savedCart = sessionStorage.getItem('koperasiku_cart');
    if (savedCart) cart = JSON.parse(savedCart);
} catch (e) {
    cart = [];
}

function saveCart() {
    try {
        sessionStorage.setItem('koperasiku_cart', JSON.stringify(cart));
    } catch (e) {}
    updateCartUI();
}

function updateCartUI() {
    const desktopBadge = document.getElementById('desktopCartBadge');
    const mobileBadge = document.getElementById('mobileCartBadge');
    const itemList = document.getElementById('cartItemList');
    const summaryBox = document.getElementById('cartSummaryBox');
    const emptyState = document.getElementById('cartEmptyState');
    const totalCountSpan = document.getElementById('cartTotalItemsCount');
    const totalPriceSpan = document.getElementById('cartTotalPriceDisplay');

    const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
    const totalPrice = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

    // Update Badges
    [desktopBadge, mobileBadge].forEach(badge => {
        if (!badge) return;
        if (totalQty > 0) {
            badge.textContent = totalQty;
            badge.classList.remove('d-none');
        } else {
            badge.classList.add('d-none');
        }
    });

    // Mobile Sticky Floating Cart Bar (Tokopedia Style)
    const floatingCart = document.getElementById('mobileStickyCartBar');
    const floatingCount = document.getElementById('floatingCartItemsCount');
    const floatingBadge = document.getElementById('floatingCartCountBadge');
    const floatingPrice = document.getElementById('floatingCartPriceDisplay');
    if (floatingCart) {
        if (totalQty > 0) {
            floatingCart.classList.remove('d-none');
            if (floatingCount) floatingCount.textContent = `${totalQty} item`;
            if (floatingBadge) floatingBadge.textContent = totalQty;
            if (floatingPrice) floatingPrice.textContent = `Rp ${totalPrice.toLocaleString('id-ID')}`;
        } else {
            floatingCart.classList.add('d-none');
        }
    }

    if (!itemList) return;

    if (cart.length === 0) {
        if (emptyState) emptyState.classList.remove('d-none');
        if (summaryBox) summaryBox.classList.add('d-none');
        // Clear items
        const itemRows = itemList.querySelectorAll('.cart-item-row');
        itemRows.forEach(row => row.remove());
        return;
    }

    if (emptyState) emptyState.classList.add('d-none');
    if (summaryBox) summaryBox.classList.remove('d-none');

    if (totalCountSpan) totalCountSpan.textContent = `${totalQty} item`;
    if (totalPriceSpan) totalPriceSpan.textContent = `Rp ${totalPrice.toLocaleString('id-ID')}`;

    // Render items with accessible steppers
    let html = '';
    cart.forEach(item => {
        const itemSubtotal = item.price * item.qty;
        html += `
            <div class="cart-item-row d-flex align-items-center justify-content-between p-2.5 rounded-2 border bg-white mb-2 shadow-xs">
                <div class="d-flex align-items-center gap-2" style="max-width: 55%;">
                    <div class="p-2 rounded-2 bg-light text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bi ${item.icon || 'bi-box'} fs-5" aria-hidden="true"></i>
                    </div>
                    <div class="lh-sm text-truncate">
                        <div class="fw-semibold text-dark small text-truncate" title="${item.name}">${item.name}</div>
                        <small class="text-muted" style="font-size: 0.72rem;">Rp ${item.price.toLocaleString('id-ID')} / ${item.unit}</small>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="btn-group btn-group-sm border rounded-1">
                        <button type="button" class="btn btn-light btn-sm px-2 py-0.5 text-secondary" onclick="updateCartQty('${item.id}', -1)" aria-label="Kurangi ${item.name}">
                            ${item.qty === 1 ? '<i class="bi bi-trash text-danger" style="font-size: 0.75rem;"></i>' : '-'}
                        </button>
                        <span class="px-2 py-0.5 bg-white small fw-bold text-dark d-flex align-items-center">${item.qty}</span>
                        <button type="button" class="btn btn-light btn-sm px-2 py-0.5 text-secondary" onclick="updateCartQty('${item.id}', 1)" aria-label="Tambah ${item.name}">
                            +
                        </button>
                    </div>
                    <div class="fw-bold text-primary small text-nowrap" style="min-width: 65px; text-align: right;">
                        Rp ${itemSubtotal.toLocaleString('id-ID')}
                    </div>
                </div>
            </div>
        `;
    });

    itemList.innerHTML = html;
}

// Global Cart Actions & Login Interceptors
window.pendingActionAfterLogin = null;

window.showLoginRequiredModal = function (productName, onLoginSuccess) {
    window.pendingActionAfterLogin = onLoginSuccess;
    const nameEl = document.getElementById('loginRequiredProductName');
    if (nameEl) nameEl.textContent = productName || 'Produk';

    // Periksa apakah perangkat ini sudah pernah login sebelumnya
    let remembered = null;
    try {
        const saved = localStorage.getItem('koperasiku_remembered_member');
        if (saved) remembered = JSON.parse(saved);
    } catch (e) {}

    const rememberedBox = document.getElementById('modalRememberedUserBox');
    const loginForm = document.getElementById('modalLoginForm');
    const alertError = document.getElementById('modalLoginAlertError');
    if (alertError) alertError.classList.add('d-none');

    if (remembered && remembered.name) {
        // Tampilkan opsi Masuk Cepat HANYA karena SUDAH PERNAH LOGIN sebelumnya
        if (rememberedBox) {
            rememberedBox.classList.remove('d-none');
            const nameSpan = document.getElementById('rememberedMemberName');
            const idSpan = document.getElementById('rememberedMemberId');
            const btnNameSpan = document.getElementById('btnRememberedMemberName');
            const avatarSpan = document.getElementById('rememberedMemberAvatar');

            if (nameSpan) nameSpan.textContent = remembered.name;
            if (idSpan) idSpan.textContent = (remembered.id || 'EMP-001') + ' • Anggota Terdaftar';
            if (btnNameSpan) btnNameSpan.textContent = remembered.name;
            if (avatarSpan) avatarSpan.textContent = remembered.name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
        }
        if (loginForm) loginForm.classList.add('d-none');
    } else {
        // Pengguna baru / belum pernah login: WAJIB MENGISI FORM LOGIN (tanpa tombol masuk cepat)
        if (rememberedBox) rememberedBox.classList.add('d-none');
        if (loginForm) loginForm.classList.remove('d-none');
    }

    const modalEl = document.getElementById('loginRequiredModal');
    if (modalEl && window.bootstrap) {
        const bsModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    } else {
        window.location.href = '/login';
    }
};

window.showOtherLoginForm = function () {
    const rememberedBox = document.getElementById('modalRememberedUserBox');
    const loginForm = document.getElementById('modalLoginForm');
    if (rememberedBox) rememberedBox.classList.add('d-none');
    if (loginForm) {
        loginForm.classList.remove('d-none');
        const usernameInput = document.getElementById('modalLoginUsername');
        if (usernameInput) usernameInput.focus();
    }
};

window.forgetRememberedMember = function () {
    try {
        localStorage.removeItem('koperasiku_remembered_member');
    } catch (e) {}
    window.showOtherLoginForm();
};

window.handleModalLoginFormSubmit = function (event) {
    event.preventDefault();

    const usernameInput = document.getElementById('modalLoginUsername');
    const passwordInput = document.getElementById('modalLoginPassword');
    const submitBtn = document.getElementById('btnModalLoginSubmit');
    const alertError = document.getElementById('modalLoginAlertError');

    const username = (usernameInput?.value || '').trim();
    const password = (passwordInput?.value || '').trim();

    if (!username || !password) {
        if (alertError) {
            alertError.textContent = 'Nomor Anggota dan Kata Sandi wajib diisi.';
            alertError.classList.remove('d-none');
        }
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memverifikasi...';
    }
    if (alertError) alertError.classList.add('d-none');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            username: username,
            password: password,
            role: 'MEMBER'
        })
    })
    .then(async res => {
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Gagal masuk akun.');
        return data;
    })
    .then(data => {
        window.isUserLoggedIn = true;
        window.currentUser = data.user;

        // SIMPAN KE LOCALSTORAGE BAHWA PERANGKAT INI SUDAH PERNAH LOGIN
        try {
            localStorage.setItem('koperasiku_remembered_member', JSON.stringify({
                id: data.user.id || username,
                name: data.user.name || (username === 'EMP-001' ? 'Ahmad Fauzi' : username)
            }));
        } catch (e) {}

        // Tutup modal
        const modalEl = document.getElementById('loginRequiredModal');
        if (modalEl && window.bootstrap) {
            const bsModal = window.bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();
        }

        // Jalankan aksi tertunda (misal addToCart)
        if (typeof window.pendingActionAfterLogin === 'function') {
            try {
                window.pendingActionAfterLogin();
            } catch (e) {
                console.warn('Error pending action:', e);
            }
            window.pendingActionAfterLogin = null;
        }

        try {
            sessionStorage.setItem('koperasiku_open_cart_after_login', '1');
        } catch (e) {}

        window.location.reload();
    })
    .catch(err => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-1"></i> Masuk & Lanjutkan Belanja';
        }
        if (alertError) {
            alertError.textContent = err.message || 'Gagal masuk. Periksa kembali Nomor Anggota dan Sandi.';
            alertError.classList.remove('d-none');
        }
    });
};

window.quickLoginAsRememberedMember = function () {
    let remembered = null;
    try {
        const saved = localStorage.getItem('koperasiku_remembered_member');
        if (saved) remembered = JSON.parse(saved);
    } catch (e) {}

    const username = remembered?.id || 'EMP-001';
    const btn = document.getElementById('btnQuickLoginRemembered');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sedang Masuk...';
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            username: username,
            password: 'password',
            role: 'MEMBER'
        })
    })
    .then(res => res.json())
    .then(data => {
        window.isUserLoggedIn = true;
        window.currentUser = data.user;

        // Tutup modal login
        const modalEl = document.getElementById('loginRequiredModal');
        if (modalEl && window.bootstrap) {
            const bsModal = window.bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();
        }

        // Jalankan aksi tertunda
        if (typeof window.pendingActionAfterLogin === 'function') {
            try {
                window.pendingActionAfterLogin();
            } catch (e) {
                console.warn('Error pending action:', e);
            }
            window.pendingActionAfterLogin = null;
        }

        try {
            sessionStorage.setItem('koperasiku_open_cart_after_login', '1');
        } catch (e) {}

        window.location.reload();
    })
    .catch(err => {
        console.error('Login error:', err);
        window.location.href = '/login';
    });
};

window.openLoginModalFromCart = function () {
    const drawerEl = document.getElementById('cartDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getInstance(drawerEl);
        if (bsDrawer) bsDrawer.hide();
    }
    setTimeout(() => {
        window.showLoginRequiredModal('Keranjang Belanja', () => {
            const cartDrawerEl = document.getElementById('cartDrawer');
            if (cartDrawerEl && window.bootstrap) {
                const bs = window.bootstrap.Offcanvas.getOrCreateInstance(cartDrawerEl);
                bs.show();
            }
        });
    }, 300);
};

window.openLoginModalFromProfile = function () {
    const drawerEl = document.getElementById('memberProfileDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getInstance(drawerEl);
        if (bsDrawer) bsDrawer.hide();
    }
    setTimeout(() => {
        window.showLoginRequiredModal('Profil Anggota', () => {
            const profileDrawerEl = document.getElementById('memberProfileDrawer');
            if (profileDrawerEl && window.bootstrap) {
                const bs = window.bootstrap.Offcanvas.getOrCreateInstance(profileDrawerEl);
                bs.show();
            }
        });
    }, 300);
};

window.addToCart = function (id, name, price, unit, icon) {
    if (!window.isUserLoggedIn) {
        window.showLoginRequiredModal(name, () => {
            window.addToCart(id, name, price, unit, icon);
        });
        return;
    }

    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.push({ id, name, price, unit, icon, qty: 1 });
    }
    saveCart();

    // Buka drawer keranjang
    const drawerEl = document.getElementById('cartDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        bsDrawer.show();
    }
};

window.openCartDrawer = function () {
    const drawerEl = document.getElementById('cartDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        bsDrawer.show();
    }
};

// ==============================================================================
// Product Detail Modal (Tokopedia / Modern Marketplace UX)
// ==============================================================================
let activeModalProduct = null;
let modalCurrentQty = 1;

window.openProductDetailModal = function (productId) {
    if (!window.allProducts || !window.allProducts[productId]) {
        console.warn(`Produk dengan ID ${productId} tidak ditemukan.`);
        return;
    }

    const product = window.allProducts[productId];
    activeModalProduct = product;
    modalCurrentQty = 1;

    // Elements
    const catEl = document.getElementById('modalProductCategory');
    const skuEl = document.getElementById('modalProductSku');
    const titleEl = document.getElementById('modalProductTitle');
    const imgEl = document.getElementById('modalProductImage');
    const fallbackBox = document.getElementById('modalImageFallback');
    const fallbackIcon = document.getElementById('modalFallbackIcon');
    const badgeEl = document.getElementById('modalProductBadge');
    const ratingEl = document.getElementById('modalProductRating');
    const reviewsEl = document.getElementById('modalProductReviews');
    const soldEl = document.getElementById('modalProductSold');
    const priceEl = document.getElementById('modalProductPrice');
    const origPriceEl = document.getElementById('modalProductOriginalPrice');
    const discountEl = document.getElementById('modalProductDiscount');
    const unitEl = document.getElementById('modalProductUnit');
    const stockBadgeEl = document.getElementById('modalProductStockBadge');
    const specsBody = document.getElementById('modalProductSpecsBody');
    const descEl = document.getElementById('modalProductDescription');
    const qtyDisplay = document.getElementById('modalQtyDisplay');
    const subtotalDisplay = document.getElementById('modalSubtotalDisplay');

    // Populate Category, SKU, Title
    if (catEl) catEl.textContent = product.category || 'Komoditas';
    if (skuEl) skuEl.textContent = `SKU: ${product.sku || product.id}`;
    if (titleEl) titleEl.textContent = product.name;

    // Populate Image & Fallback
    if (imgEl) {
        imgEl.classList.remove('d-none');
        imgEl.src = product.image || '';
        imgEl.alt = product.name;
    }
    if (fallbackBox) fallbackBox.classList.add('d-none');
    if (fallbackIcon) fallbackIcon.className = `bi ${product.icon || 'bi-box-seam'}`;

    // Badge
    if (badgeEl) {
        if (product.badge) {
            badgeEl.textContent = product.badge;
            badgeEl.classList.remove('d-none');
        } else {
            badgeEl.classList.add('d-none');
        }
    }

    // Rating & Review
    if (ratingEl) ratingEl.textContent = product.rating || '4.9';
    if (reviewsEl) reviewsEl.textContent = `${product.reviews_count || 28} ulasan`;
    if (soldEl) soldEl.textContent = product.sold_count || '250+ terjual';

    // Pricing
    const currentPrice = Number(product.price || 0);
    if (priceEl) priceEl.textContent = `Rp ${currentPrice.toLocaleString('id-ID')}`;
    if (unitEl) unitEl.textContent = `/ ${product.unit || 'Unit'}`;

    if (product.original_price && product.original_price > currentPrice) {
        if (origPriceEl) {
            origPriceEl.textContent = `Rp ${Number(product.original_price).toLocaleString('id-ID')}`;
            origPriceEl.classList.remove('d-none');
        }
        if (discountEl) {
            const discountPct = Math.round(((product.original_price - currentPrice) / product.original_price) * 100);
            discountEl.textContent = `Hemat ${discountPct}%`;
            discountEl.classList.remove('d-none');
        }
    } else {
        if (origPriceEl) origPriceEl.classList.add('d-none');
        if (discountEl) discountEl.classList.add('d-none');
    }

    // Stock Badge
    if (stockBadgeEl) {
        stockBadgeEl.innerHTML = `<i class="bi bi-box-seam me-1"></i> Tersedia: ${product.available_stock || 10} ${product.unit || 'unit'}`;
    }

    // Specifications Table
    if (specsBody) {
        specsBody.innerHTML = '';
        if (product.specs && Object.keys(product.specs).length > 0) {
            Object.entries(product.specs).forEach(([k, v]) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td class="bg-light text-secondary fw-semibold text-nowrap" style="width: 38%;">${k}</td><td class="text-dark">${v}</td>`;
                specsBody.appendChild(tr);
            });
        } else {
            const fallbackRows = [
                ['Kategori', product.category || 'Sembako'],
                ['Satuan', product.unit || 'Unit'],
                ['Ketersediaan', `${product.available_stock || 10} ${product.unit || 'unit'} di gerai`],
                ['Sertifikasi', 'Resmi Koperasi Konsumen Pegawai POLMAN Bandung']
            ];
            fallbackRows.forEach(([k, v]) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td class="bg-light text-secondary fw-semibold text-nowrap" style="width: 38%;">${k}</td><td class="text-dark">${v}</td>`;
                specsBody.appendChild(tr);
            });
        }
    }

    // Description
    if (descEl) {
        descEl.textContent = product.description || 'Komoditas kebutuhan harian berkualitas jaminan resmi Koperasi Konsumen Pegawai POLMAN Bandung. Pengambilan langsung di gerai.';
    }

    // Stepper & Subtotal Initial
    if (qtyDisplay) qtyDisplay.textContent = '1';
    if (subtotalDisplay) subtotalDisplay.textContent = `Rp ${currentPrice.toLocaleString('id-ID')}`;

    // Reset Tabs to Specifications
    const firstTabBtn = document.getElementById('specs-tab');
    if (firstTabBtn && window.bootstrap) {
        const tabTrigger = new window.bootstrap.Tab(firstTabBtn);
        tabTrigger.show();
    }

    // Show Detail Modal
    const modalEl = document.getElementById('productDetailModal');
    if (modalEl && window.bootstrap) {
        const bsModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }
};

window.changeModalQty = function (delta) {
    if (!activeModalProduct) return;
    const maxStock = Math.min(activeModalProduct.available_stock || 10, 10);
    modalCurrentQty = Math.max(1, Math.min(maxStock, modalCurrentQty + delta));

    const qtyDisplay = document.getElementById('modalQtyDisplay');
    const subtotalDisplay = document.getElementById('modalSubtotalDisplay');

    if (qtyDisplay) qtyDisplay.textContent = modalCurrentQty;
    if (subtotalDisplay) {
        const subtotal = activeModalProduct.price * modalCurrentQty;
        subtotalDisplay.textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
    }
};

window.modalAddToCart = function () {
    if (!activeModalProduct) return;

    if (!window.isUserLoggedIn) {
        const prod = activeModalProduct;
        window.showLoginRequiredModal(prod ? prod.name : 'Produk', () => {
            window.modalAddToCart();
        });
        return;
    }

    const existing = cart.find(item => item.id === activeModalProduct.id);
    if (existing) {
        existing.qty += modalCurrentQty;
    } else {
        cart.push({
            id: activeModalProduct.id,
            name: activeModalProduct.name,
            price: activeModalProduct.price,
            unit: activeModalProduct.unit,
            icon: activeModalProduct.icon,
            qty: modalCurrentQty
        });
    }
    saveCart();

    // Close Detail Modal
    const modalEl = document.getElementById('productDetailModal');
    if (modalEl && window.bootstrap) {
        const bsModal = window.bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }

    // Open Cart Drawer
    const drawerEl = document.getElementById('cartDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        bsDrawer.show();
    }
};

window.modalBuyNow = function () {
    if (!activeModalProduct) return;

    if (!window.isUserLoggedIn) {
        const prod = activeModalProduct;
        window.showLoginRequiredModal(prod ? prod.name : 'Produk', () => {
            window.modalBuyNow();
        });
        return;
    }

    // Tambahkan item yang dipilih ke keranjang
    const existing = cart.find(item => item.id === activeModalProduct.id);
    if (existing) {
        existing.qty += modalCurrentQty;
    } else {
        cart.push({
            id: activeModalProduct.id,
            name: activeModalProduct.name,
            price: activeModalProduct.price,
            unit: activeModalProduct.unit,
            icon: activeModalProduct.icon,
            qty: modalCurrentQty
        });
    }
    saveCart();

    // Close Detail Modal
    const modalEl = document.getElementById('productDetailModal');
    if (modalEl && window.bootstrap) {
        const bsModal = window.bootstrap.Modal.getInstance(modalEl);
        if (bsModal) bsModal.hide();
    }

    // Open Checkout Modal directly
    setTimeout(() => {
        window.openCheckoutModal();
    }, 350);
};

window.updateCartQty = function (id, delta) {
    const index = cart.findIndex(item => item.id === id);
    if (index !== -1) {
        cart[index].qty += delta;
        if (cart[index].qty <= 0) {
            cart.splice(index, 1);
        }
        saveCart();
    }
};

// Checkout Modal Actions
window.openCheckoutModal = function () {
    if (!window.isUserLoggedIn) {
        window.showLoginRequiredModal('Pesanan Anda', () => {
            window.openCheckoutModal();
        });
        return;
    }

    const totalQty = cart.reduce((sum, item) => sum + item.qty, 0);
    const totalPrice = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);

    const countEl = document.getElementById('modalCheckoutTotalItems');
    const priceEl = document.getElementById('modalCheckoutTotalPrice');

    if (countEl) countEl.textContent = `${totalQty} Barang`;
    if (priceEl) priceEl.textContent = `Rp ${totalPrice.toLocaleString('id-ID')}`;

    // Tutup Drawer, Buka Modal Checkout
    const drawerEl = document.getElementById('cartDrawer');
    if (drawerEl && window.bootstrap) {
        const bsDrawer = window.bootstrap.Offcanvas.getInstance(drawerEl);
        if (bsDrawer) bsDrawer.hide();
    }

    const modalEl = document.getElementById('checkoutModal');
    if (modalEl && window.bootstrap) {
        const bsModal = new window.bootstrap.Modal(modalEl);
        bsModal.show();
    }
};

window.handleCheckoutSubmit = function (event) {
    event.preventDefault();

    const submitBtn = document.getElementById('btnConfirmCheckout');
    if (submitBtn) {
        submitBtn.disabled = true;
        const spinner = submitBtn.querySelector('.spinner-border');
        if (spinner) spinner.classList.remove('d-none');
    }

    // Ambil metode pembayaran yang dipilih
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 'CASH';
    const methodNames = {
        'CASH': 'Tunai di Kasir saat Ambil',
        'BANK_TRANSFER': 'Transfer Bank / QRIS',
        'CREDIT': 'Piutang Anggota (Tempo 7 Hari)'
    };

    const orderItems = (cart || []).map(item => {
        const meta = (window.allProducts && window.allProducts[item.id]) ? window.allProducts[item.id] : null;
        return {
            product_id: meta?.sku || item.sku || item.id,
            quantity: item.qty
        };
    });

    if (orderItems.length === 0) {
        alert('Keranjang belanja masih kosong.');
        if (submitBtn) {
            submitBtn.disabled = false;
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.add('d-none');
        }
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Kirim request checkout langsung ke backend Laravel TransactionService
    fetch('/checkout', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({
            payment_choice: selectedMethod,
            channel: 'ONLINE_PICKUP',
            items: orderItems,
            idempotency_key: 'checkout-' + Date.now()
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.disabled = false;
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.add('d-none');
        }

        if (!data.success) {
            alert('Gagal memproses pesanan: ' + (data.message || 'Terjadi kesalahan validasi di server.'));
            return;
        }

        const realPickupCode = data.data?.pickup_code || ('PKP-' + Math.floor(10000 + Math.random() * 90000));

        // Tutup modal checkout
        const checkoutModalEl = document.getElementById('checkoutModal');
        if (checkoutModalEl && window.bootstrap) {
            const bsCheckoutModal = window.bootstrap.Modal.getInstance(checkoutModalEl);
            if (bsCheckoutModal) bsCheckoutModal.hide();
        }

        // Tampilkan Kode Pickup dari backend di Modal Sukses
        const codeDisplay = document.getElementById('displayPickupCode');
        const methodDisplay = document.getElementById('displayPaymentMethodName');

        if (codeDisplay) codeDisplay.textContent = realPickupCode;
        if (methodDisplay) methodDisplay.textContent = methodNames[selectedMethod] || selectedMethod;

        // Render Barcode Code-128 di Boarding Pass Tiket
        if (window.JsBarcode) {
            try {
                window.JsBarcode("#checkoutPickupBarcodeSvg", realPickupCode, {
                    format: "CODE128",
                    lineColor: "#000000",
                    width: 2.2,
                    height: 60,
                    displayValue: false,
                    margin: 4
                });
            } catch (e) {
                console.error("JsBarcode error in checkout success:", e);
            }
        }

        // Kosongkan keranjang
        cart = [];
        saveCart();

        // Buka modal sukses
        const successModalEl = document.getElementById('pickupSuccessModal');
        if (successModalEl && window.bootstrap) {
            const bsSuccessModal = new window.bootstrap.Modal(successModalEl);
            bsSuccessModal.show();
        }
    })
    .catch(err => {
        console.error('Checkout fetch error:', err);
        if (submitBtn) {
            submitBtn.disabled = false;
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.add('d-none');
        }
        alert('Terjadi kendala jaringan saat menghubungi backend KoperasiKu.');
    });
};

// Fitur Salin Kode Pickup ke Clipboard
window.copyPickupCode = function () {
    const codeEl = document.getElementById('displayPickupCode');
    const copyText = document.getElementById('copyBtnText');
    const copyIcon = document.getElementById('copyIcon');

    if (!codeEl) return;
    const textToCopy = codeEl.textContent.trim();

    navigator.clipboard.writeText(textToCopy).then(() => {
        if (copyText) copyText.textContent = 'Tersalin!';
        if (copyIcon) {
            copyIcon.classList.remove('bi-clipboard');
            copyIcon.classList.add('bi-clipboard-check');
        }

        setTimeout(() => {
            if (copyText) copyText.textContent = 'Salin Kode';
            if (copyIcon) {
                copyIcon.classList.remove('bi-clipboard-check');
                copyIcon.classList.add('bi-clipboard');
            }
        }, 2000);
    }).catch(() => {
        alert('Kode Pickup: ' + textToCopy);
    });
};

// Salin Kode Pengambilan Anggota dari Drawer / Profil
window.copyMemberPickupCode = function (text, btn) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> <span>Tersalin!</span>';
        setTimeout(() => {
            btn.innerHTML = originalHtml;
        }, 2000);
    }).catch(() => {
        alert('Kode Pickup: ' + text);
    });
};

// Buka Modal Fullscreen Barcode 1D (Kecerahan Maksimal untuk Scanner Kasir)
window.openFullscreenPickupModal = function (code) {
    if (!code) code = 'PKP-84920';
    const badgeEl = document.getElementById('fsPickupCodeBadge');
    const textEl = document.getElementById('fsPickupCodeText');
    if (badgeEl) badgeEl.textContent = code;
    if (textEl) textEl.textContent = code;

    // Otomatis tutup modal kode kecil jika sedang terbuka
    ['pickupBarcodeModal', 'checkoutModal'].forEach(function (modalId) {
        const modalEl = document.getElementById(modalId);
        if (modalEl && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modalEl);
            if (instance) {
                instance.hide();
            }
        }
    });

    // Render Barcode 1D Code-128 via JsBarcode
    if (window.JsBarcode) {
        try {
            window.JsBarcode("#fsPickupBarcodeSvg", code, {
                format: "CODE128",
                lineColor: "#000000",
                width: 3.0,
                height: 95,
                displayValue: false,
                margin: 4
            });
        } catch (e) {
            console.error("Barcode render error:", e);
        }
    }

    const fsModalEl = document.getElementById('fullscreenBarcodeModal');
    if (fsModalEl && window.bootstrap) {
        const bsModal = window.bootstrap.Modal.getOrCreateInstance(fsModalEl);
        bsModal.show();
    }
};

// Dropdown Rekomendasi Pencarian Interaktif (Tokopedia Style)
window.openSearchSuggestions = function () {
    const dropdown = document.getElementById('searchSuggestionDropdown');
    if (dropdown) dropdown.classList.remove('d-none');
};

window.closeSearchSuggestions = function () {
    setTimeout(() => {
        const dropdown = document.getElementById('searchSuggestionDropdown');
        if (dropdown) dropdown.classList.add('d-none');
    }, 250);
};

window.openMobileSearchSuggestions = function () {
    const dropdown = document.getElementById('mobileSearchSuggestionDropdown');
    if (dropdown) dropdown.classList.remove('d-none');
};

window.closeMobileSearchSuggestions = function () {
    setTimeout(() => {
        const dropdown = document.getElementById('mobileSearchSuggestionDropdown');
        if (dropdown) dropdown.classList.add('d-none');
    }, 250);
};

window.applySearchChip = function (query) {
    const desktopInput = document.getElementById('storefrontSearchInput');
    const mobileInput = document.getElementById('mobileStorefrontSearchInput');
    if (desktopInput) desktopInput.value = query;
    if (mobileInput) mobileInput.value = query;
    filterStorefrontSearch(query);
    const dDropdown = document.getElementById('searchSuggestionDropdown');
    const mDropdown = document.getElementById('mobileSearchSuggestionDropdown');
    if (dDropdown) dDropdown.classList.add('d-none');
    if (mDropdown) mDropdown.classList.add('d-none');
    scrollToFirstProduct();
};

// Live Storefront Search & Filter
window.filterStorefrontSearch = function (rawQuery) {
    const query = (rawQuery || '').toLowerCase().trim();
    const productCols = document.querySelectorAll('.product-item-col');
    const emptyState = document.getElementById('noProductsFoundState');
    let visibleCount = 0;

    // Sync input desktop dan mobile
    const desktopInput = document.getElementById('storefrontSearchInput');
    const mobileInput = document.getElementById('mobileStorefrontSearchInput');

    if (desktopInput && desktopInput.value !== rawQuery) desktopInput.value = rawQuery;
    if (mobileInput && mobileInput.value !== rawQuery) mobileInput.value = rawQuery;

    productCols.forEach(col => {
        const name = col.getAttribute('data-name') || '';
        const category = col.getAttribute('data-category') || '';

        if (!query || name.includes(query) || category.includes(query)) {
            col.classList.remove('d-none');
            visibleCount++;
        } else {
            col.classList.add('d-none');
        }
    });

    if (emptyState) {
        if (visibleCount === 0) {
            emptyState.classList.remove('d-none');
        } else {
            emptyState.classList.add('d-none');
        }
    }
};

window.filterStorefrontCategory = function (categoryName) {
    const query = (categoryName || '').toLowerCase().trim();
    filterStorefrontSearch(query);

    // Sync category pills state if matching
    const pills = document.querySelectorAll('.category-pill');
    pills.forEach(pill => {
        const pillText = (pill.textContent || '').toLowerCase().trim();
        if ((!query && pillText.includes('semua')) || (query && pillText.includes(query))) {
            pill.classList.remove('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
            pill.classList.add('btn-primary', 'text-white', 'active');
        } else {
            pill.classList.remove('btn-primary', 'text-white', 'active');
            pill.classList.add('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
        }
    });

    scrollToFirstProduct();
};

window.filterByPill = function (pillEl, categoryName) {
    const pills = document.querySelectorAll('.category-pill');
    pills.forEach(p => {
        p.classList.remove('btn-primary', 'text-white', 'active');
        p.classList.add('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
    });

    if (pillEl) {
        pillEl.classList.remove('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
        pillEl.classList.add('btn-primary', 'text-white', 'active');
    }

    const query = (categoryName || '').toLowerCase().trim();
    filterStorefrontSearch(query);
    scrollToFirstProduct();
};

window.resetStorefrontFilter = function () {
    window.showAllProducts();
};

window.showAllProducts = function (event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    // 1. Reset input pencarian desktop & mobile
    const desktopInput = document.getElementById('storefrontSearchInput');
    const mobileInput = document.getElementById('mobileStorefrontSearchInput');
    if (desktopInput) desktopInput.value = '';
    if (mobileInput) mobileInput.value = '';

    // 2. Reset category pills (pilih Semua)
    const pills = document.querySelectorAll('.category-pill');
    pills.forEach((p, idx) => {
        if (idx === 0) {
            p.classList.remove('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
            p.classList.add('btn-primary', 'text-white', 'active');
        } else {
            p.classList.remove('btn-primary', 'text-white', 'active');
            p.classList.add('btn-outline-secondary', 'bg-white', 'text-dark', 'border');
        }
    });

    // 3. Munculkan semua kartu produk tanpa filter
    const productCols = document.querySelectorAll('.product-item-col');
    productCols.forEach(col => col.classList.remove('d-none'));

    const emptyState = document.getElementById('noProductsFoundState');
    if (emptyState) emptyState.classList.add('d-none');

    // 4. Tutup dropdown rekomendasi pencarian
    const dDropdown = document.getElementById('searchSuggestionDropdown');
    const mDropdown = document.getElementById('mobileSearchSuggestionDropdown');
    if (dDropdown) dDropdown.classList.add('d-none');
    if (mDropdown) mDropdown.classList.add('d-none');

    // 5. Smooth scroll ke bagian Semua Produk
    const target = document.getElementById('allProductsSection') || document.getElementById('best-deals');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

window.scrollToFirstProduct = function () {
    const target = document.querySelector('.product-item-col:not(.d-none)') || document.getElementById('allProductsSection');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Initial setup on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Jika belum login, pastikan keranjang di reset kosong
    if (typeof window.isUserLoggedIn !== 'undefined' && !window.isUserLoggedIn) {
        cart = [];
        try {
            sessionStorage.removeItem('koperasiku_cart');
        } catch (e) {}
    }

    updateCartUI();

    // Buka keranjang otomatis jika pengguna baru saja login setelah klik keranjang
    try {
        if (sessionStorage.getItem('koperasiku_open_cart_after_login') === '1') {
            sessionStorage.removeItem('koperasiku_open_cart_after_login');
            setTimeout(() => {
                const drawerEl = document.getElementById('cartDrawer');
                if (drawerEl && window.bootstrap) {
                    const bsDrawer = window.bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
                    bsDrawer.show();
                }
            }, 300);
        }
    } catch (e) {}
});
