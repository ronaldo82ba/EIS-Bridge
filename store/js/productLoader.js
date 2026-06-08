import { DEFAULT_STORE_INVENTORY, resolveProductCatalog } from './productCatalog.js';
import { MAIN_PRODUCTS } from './products.js';
import { getStoreConfig, readLocalStoreInventory, readLocalStoreSettings } from './storeConfig.js';

/**
 * @typedef {import('./products.js').Product} Product
 * @typedef {{ products: Product[], source: 'main' | 'inventory', useMainList: boolean }} ProductLoadResult
 */

/**
 * @returns {Promise<ProductLoadResult>}
 */
export async function loadStoreProducts() {
    const config = getStoreConfig();
    const apiResult = await fetchProductsFromApi(config);

    if (apiResult) {
        return apiResult;
    }

    const localSettings = readLocalStoreSettings(config.vendorId);
    const inventory = readLocalStoreInventory(config.vendorId) ?? DEFAULT_STORE_INVENTORY;
    const products = resolveProductCatalog(localSettings.useMainList, inventory, MAIN_PRODUCTS);

    return {
        products,
        source: localSettings.useMainList ? 'main' : 'inventory',
        useMainList: localSettings.useMainList,
    };
}

/**
 * @param {{ vendorId: string | null, apiBase: string }} config
 * @returns {Promise<ProductLoadResult | null>}
 */
async function fetchProductsFromApi(config) {
    const url = new URL(`${config.apiBase.replace(/\/$/, '')}/store/products`);

    if (config.vendorId) {
        url.searchParams.set('vendor_id', config.vendorId);
    }

    try {
        const response = await fetch(url.toString(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return null;
        }

        const payload = await response.json();
        const products = Array.isArray(payload.data) ? payload.data : [];
        const useMainList = payload.meta?.use_main_online_store_product_list !== false;
        const source = payload.meta?.source === 'inventory' ? 'inventory' : 'main';

        return {
            products,
            source,
            useMainList,
        };
    } catch {
        return null;
    }
}
