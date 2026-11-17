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

// Global Cart Actions
window.addToCart = function (id, name, price, unit, icon) {
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

    // Generate kode pickup unik (PKP-XXXXX)
    const randomCode = 'PKP-' + Math.floor(10000 + Math.random() * 90000);

    setTimeout(() => {
        // Reset tombol
        if (submitBtn) {
            submitBtn.disabled = false;
            const spinner = submitBtn.querySelector('.spinner-border');
            if (spinner) spinner.classList.add('d-none');
        }

        // Tutup modal checkout
        const checkoutModalEl = document.getElementById('checkoutModal');
        if (checkoutModalEl && window.bootstrap) {
            const bsCheckoutModal = window.bootstrap.Modal.getInstance(checkoutModalEl);
            if (bsCheckoutModal) bsCheckoutModal.hide();
        }

        // Tampilkan Kode Pickup di Modal Sukses
        const codeDisplay = document.getElementById('displayPickupCode');
        const methodDisplay = document.getElementById('displayPaymentMethodName');

        if (codeDisplay) codeDisplay.textContent = randomCode;
        if (methodDisplay) methodDisplay.textContent = methodNames[selectedMethod] || selectedMethod;

        // Render Barcode Code-128 di Boarding Pass Tiket
        if (window.JsBarcode) {
            try {
                window.JsBarcode("#checkoutPickupBarcodeSvg", randomCode, {
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
    }, 500);
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

// Buka Modal Fullscreen Barcode (Kecerahan Maksimal untuk Scanner Kasir)
window.openFullscreenPickupModal = function (code) {
    if (!code) code = 'PKP-84920';
    const badgeEl = document.getElementById('fsPickupCodeBadge');
    const textEl = document.getElementById('fsPickupCodeText');
    if (badgeEl) badgeEl.textContent = code;
    if (textEl) textEl.textContent = code;

    // Render Barcode Code-128 via JsBarcode
    if (window.JsBarcode) {
        try {
            window.JsBarcode("#fsPickupBarcodeSvg", code, {
                format: "CODE128",
                lineColor: "#000000",
                width: 2.8,
                height: 80,
                displayValue: false,
                margin: 4
            });
        } catch (e) {
            console.error("Barcode render error:", e);
        }
    }

    // Render QR Code via QRCode
    if (window.QRCode) {
        const canvas = document.getElementById('fsPickupQrCanvas');
        if (canvas) {
            window.QRCode.toCanvas(canvas, code, {
                width: 220,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function (error) {
                if (error) console.error("QR render error:", error);
            });
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
        } else {
            col.classList.add('d-none');
        }
    });
};

window.filterStorefrontCategory = function (categoryName) {
    const query = (categoryName || '').toLowerCase().trim();
    filterStorefrontSearch(query);
    scrollToFirstProduct();
};

window.resetStorefrontFilter = function () {
    filterStorefrontSearch('');
};

window.scrollToFirstProduct = function () {
    const target = document.querySelector('.product-item-col:not(.d-none)') || document.getElementById('best-deals');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

// Initial setup on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    updateCartUI();
});
