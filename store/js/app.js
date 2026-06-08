import { PRODUCTS, CATEGORIES, BRANDS } from './products.js';
import { filterProducts } from './productFilters.js';
import { createCartStore } from './cartStore.js';

const cart = createCartStore(window.localStorage);

const els = {
    searchInput: document.getElementById('search-input'),
    searchForm: document.getElementById('search-form'),
    advancedToggle: document.getElementById('advanced-toggle'),
    advancedPanel: document.getElementById('advanced-panel'),
    filterForm: document.getElementById('filter-form'),
    category: document.getElementById('filter-category'),
    priceMin: document.getElementById('filter-price-min'),
    priceMax: document.getElementById('filter-price-max'),
    brand: document.getElementById('filter-brand'),
    inStock: document.getElementById('filter-in-stock'),
    outOfStock: document.getElementById('filter-out-of-stock'),
    productGrid: document.getElementById('product-grid'),
    emptyState: document.getElementById('empty-state'),
    cartCount: document.getElementById('cart-count'),
    toast: document.getElementById('toast'),
    quantityModal: document.getElementById('quantity-modal'),
    quantityInput: document.getElementById('quantity-input'),
    quantityConfirm: document.getElementById('quantity-confirm'),
    quantityCancel: document.getElementById('quantity-cancel'),
    quantityProductName: document.getElementById('quantity-product-name'),
};

/** @type {{ search: string, category: string, priceMin: string, priceMax: string, brand: string, inStock: boolean, outOfStock: boolean }} */
let activeFilters = {
    search: '',
    category: '',
    priceMin: '',
    priceMax: '',
    brand: '',
    inStock: false,
    outOfStock: false,
};

/** @type {string | null} */
let pendingProductId = null;

/** @type {Set<string>} */
const disabledButtons = new Set();

let toastTimer = null;

function formatPrice(value) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        maximumFractionDigits: 0,
    }).format(value);
}

function updateCartCount() {
    els.cartCount.textContent = String(cart.getCount());
    els.cartCount.hidden = cart.getCount() === 0;
}

function showToast(message) {
    els.toast.textContent = message;
    els.toast.classList.add('toast--visible');

    if (toastTimer) {
        window.clearTimeout(toastTimer);
    }

    toastTimer = window.setTimeout(() => {
        els.toast.classList.remove('toast--visible');
    }, 2800);
}

function getFilterValues() {
    return {
        search: els.searchInput.value,
        category: els.category.value,
        priceMin: els.priceMin.value,
        priceMax: els.priceMax.value,
        brand: els.brand.value,
        inStock: els.inStock.checked,
        outOfStock: els.outOfStock.checked,
    };
}

function renderProducts() {
    const filtered = filterProducts(PRODUCTS, activeFilters);
    els.productGrid.innerHTML = '';

    if (filtered.length === 0) {
        els.emptyState.hidden = false;
        return;
    }

    els.emptyState.hidden = true;

    filtered.forEach((product) => {
        const card = document.createElement('article');
        card.className = 'product-card';
        card.dataset.productId = product.id;

        const availability = product.inStock
            ? '<span class="product-card__badge product-card__badge--in">In stock</span>'
            : '<span class="product-card__badge product-card__badge--out">Out of stock</span>';

        card.innerHTML = `
            <div class="product-card__image" aria-hidden="true">${product.name.charAt(0)}</div>
            <p class="product-card__category">${product.category}</p>
            <h2 class="product-card__name">${product.name}</h2>
            <p class="product-card__meta">SKU: ${product.sku} · ${product.brand}</p>
            ${availability}
            <p class="product-card__price">${formatPrice(product.price)}</p>
            <button type="button" class="add-to-cart-btn" data-product-id="${product.id}" ${product.inStock ? '' : 'disabled'}>
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Add to Cart
            </button>
        `;

        els.productGrid.appendChild(card);
    });
}

function populateSelectOptions() {
    CATEGORIES.forEach((category) => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = category;
        els.category.appendChild(option);
    });

    BRANDS.forEach((brand) => {
        const option = document.createElement('option');
        option.value = brand;
        option.textContent = brand;
        els.brand.appendChild(option);
    });
}

function applyFilters() {
    activeFilters = getFilterValues();
    renderProducts();
}

function openQuantityModal(productId, productName) {
    pendingProductId = productId;
    els.quantityProductName.textContent = productName;
    els.quantityInput.value = '1';
    els.quantityModal.hidden = false;
    els.quantityInput.focus();
}

function closeQuantityModal() {
    pendingProductId = null;
    els.quantityModal.hidden = true;
}

function addProductToCart(productId, quantity = 1) {
    if (disabledButtons.has(productId)) {
        return;
    }

    disabledButtons.add(productId);
    const button = els.productGrid.querySelector(`button[data-product-id="${productId}"]`);
    if (button) {
        button.disabled = true;
        button.classList.add('add-to-cart-btn--loading');
    }

    cart.addItem(productId, quantity);
    updateCartCount();
    showToast('Item added to cart.');

    window.setTimeout(() => {
        disabledButtons.delete(productId);
        if (button && PRODUCTS.find((p) => p.id === productId)?.inStock) {
            button.disabled = false;
            button.classList.remove('add-to-cart-btn--loading');
        }
    }, 800);
}

function bindEvents() {
    els.searchForm.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters();
    });

    els.filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        applyFilters();
    });

    els.advancedToggle.addEventListener('click', () => {
        const expanded = els.advancedToggle.getAttribute('aria-expanded') === 'true';
        els.advancedToggle.setAttribute('aria-expanded', String(!expanded));
        els.advancedPanel.hidden = expanded;
    });

    els.productGrid.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const button = target.closest('.add-to-cart-btn');
        if (!button || button.hasAttribute('disabled')) {
            return;
        }

        const productId = button.getAttribute('data-product-id');
        if (!productId) {
            return;
        }

        const product = PRODUCTS.find((item) => item.id === productId);
        if (!product || !product.inStock) {
            return;
        }

        if (event.shiftKey) {
            openQuantityModal(productId, product.name);
            return;
        }

        addProductToCart(productId, 1);
    });

    els.quantityConfirm.addEventListener('click', () => {
        if (!pendingProductId) {
            return;
        }

        const quantity = Number(els.quantityInput.value);
        addProductToCart(pendingProductId, Number.isFinite(quantity) ? quantity : 1);
        closeQuantityModal();
    });

    els.quantityCancel.addEventListener('click', closeQuantityModal);

    els.quantityModal.addEventListener('click', (event) => {
        if (event.target === els.quantityModal) {
            closeQuantityModal();
        }
    });
}

populateSelectOptions();
bindEvents();
updateCartCount();
renderProducts();
