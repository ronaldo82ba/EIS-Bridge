import test from 'node:test';
import assert from 'node:assert/strict';
import { filterProducts } from '../js/productFilters.js';
import { createCartStore } from '../js/cartStore.js';
import { DEFAULT_STORE_INVENTORY, deriveFilterOptions, resolveProductCatalog } from '../js/productCatalog.js';
import { MAIN_PRODUCTS, PRODUCTS } from '../js/products.js';

class MemoryStorage {
    constructor() {
        /** @type {Map<string, string>} */
        this.store = new Map();
    }

    getItem(key) {
        return this.store.get(key) ?? null;
    }

    setItem(key, value) {
        this.store.set(key, value);
    }

    removeItem(key) {
        this.store.delete(key);
    }
}

test('search filters by product name', () => {
    const results = filterProducts(PRODUCTS, { search: 'thermal' });
    assert.ok(results.length >= 1);
    assert.ok(results.every((p) => p.name.toLowerCase().includes('thermal') || p.sku.toLowerCase().includes('thermal') || p.category.toLowerCase().includes('thermal')));
});

test('search filters by SKU', () => {
    const results = filterProducts(PRODUCTS, { search: 'BCS-110' });
    assert.equal(results.length, 1);
    assert.equal(results[0].sku, 'BCS-110');
});

test('search filters by category keyword', () => {
    const results = filterProducts(PRODUCTS, { search: 'licenses' });
    assert.ok(results.length >= 1);
    assert.ok(results.every((p) => `${p.name} ${p.sku} ${p.category}`.toLowerCase().includes('licenses')));
});

test('advanced category filter works', () => {
    const results = filterProducts(PRODUCTS, { category: 'Hardware' });
    assert.ok(results.length >= 1);
    assert.ok(results.every((p) => p.category === 'Hardware'));
});

test('advanced price range filter works', () => {
    const results = filterProducts(PRODUCTS, { priceMin: 5000, priceMax: 10000 });
    assert.ok(results.every((p) => p.price >= 5000 && p.price <= 10000));
});

test('advanced brand filter works', () => {
    const results = filterProducts(PRODUCTS, { brand: 'PrintPro' });
    assert.ok(results.length >= 1);
    assert.ok(results.every((p) => p.brand === 'PrintPro'));
});

test('availability in-stock filter works', () => {
    const results = filterProducts(PRODUCTS, { inStock: true });
    assert.ok(results.every((p) => p.inStock));
});

test('availability out-of-stock filter works', () => {
    const results = filterProducts(PRODUCTS, { outOfStock: true });
    assert.ok(results.every((p) => !p.inStock));
});

test('combined filters return no results when nothing matches', () => {
    const results = filterProducts(PRODUCTS, {
        search: 'printer',
        category: 'Licenses',
    });
    assert.equal(results.length, 0);
});

test('add to cart updates count', () => {
    const storage = new MemoryStorage();
    const cart = createCartStore(storage);

    assert.equal(cart.getCount(), 0);
    cart.addItem('p1', 2);
    assert.equal(cart.getCount(), 2);
    cart.addItem('p1', 1);
    assert.equal(cart.getCount(), 3);
});

test('cart persists on reload', () => {
    const storage = new MemoryStorage();
    const cart = createCartStore(storage);
    cart.addItem('p2', 1);
    cart.addItem('p4', 2);

    const reloaded = createCartStore(storage);
    assert.equal(reloaded.getCount(), 3);
    assert.deepEqual(reloaded.getItems(), [
        { productId: 'p2', quantity: 1 },
        { productId: 'p4', quantity: 2 },
    ]);
});

test('responsive grid CSS defines mobile, tablet, and desktop breakpoints', async () => {
    const { readFile } = await import('node:fs/promises');
    const { fileURLToPath } = await import('node:url');
    const { dirname, join } = await import('node:path');

    const cssPath = join(dirname(fileURLToPath(import.meta.url)), '../styles/store.css');
    const css = await readFile(cssPath, 'utf8');

    assert.match(css, /grid-template-columns:\s*repeat\(2,/);
    assert.match(css, /@media \(min-width:\s*640px\)/);
    assert.match(css, /@media \(min-width:\s*1024px\)/);
    assert.match(css, /gap:\s*20px/);
});

test('resolveProductCatalog uses main list when toggle is on', () => {
    const products = resolveProductCatalog(true, DEFAULT_STORE_INVENTORY, MAIN_PRODUCTS);
    assert.deepEqual(products, MAIN_PRODUCTS);
});

test('resolveProductCatalog uses store inventory when toggle is off', () => {
    const products = resolveProductCatalog(false, DEFAULT_STORE_INVENTORY, MAIN_PRODUCTS);
    assert.deepEqual(products, DEFAULT_STORE_INVENTORY);
    assert.ok(products.every((product) => !MAIN_PRODUCTS.some((main) => main.id === product.id)));
});

test('deriveFilterOptions builds categories and brands from active catalog', () => {
    const options = deriveFilterOptions(DEFAULT_STORE_INVENTORY);
    assert.deepEqual(options.categories, ['Accessories', 'Bundles', 'Services']);
    assert.deepEqual(options.brands, ['EIS Bridge', 'PrintPro']);
});
