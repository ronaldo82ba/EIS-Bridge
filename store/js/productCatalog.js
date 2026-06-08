import { MAIN_PRODUCTS } from './products.js';

/** @type {import('./products.js').Product[]} */
export const DEFAULT_STORE_INVENTORY = [
    { id: 'inv1', name: 'Store POS Kit', sku: 'ST-POS-01', category: 'Bundles', brand: 'EIS Bridge', price: 18999, inStock: true },
    { id: 'inv2', name: 'Store Receipt Paper (50 rolls)', sku: 'ST-RP-50', category: 'Accessories', brand: 'PrintPro', price: 899, inStock: true },
    { id: 'inv3', name: 'Local Support Package', sku: 'ST-SUP-01', category: 'Services', brand: 'EIS Bridge', price: 5500, inStock: true },
];

/**
 * @param {boolean} useMainList
 * @param {import('./products.js').Product[]} inventory
 * @param {import('./products.js').Product[]} mainProducts
 * @returns {import('./products.js').Product[]}
 */
export function resolveProductCatalog(useMainList, inventory, mainProducts = MAIN_PRODUCTS) {
    if (useMainList) {
        return mainProducts;
    }

    return inventory;
}

/**
 * @param {import('./products.js').Product[]} products
 */
export function deriveFilterOptions(products) {
    return {
        categories: [...new Set(products.map((product) => product.category))].sort(),
        brands: [...new Set(products.map((product) => product.brand))].sort(),
    };
}
