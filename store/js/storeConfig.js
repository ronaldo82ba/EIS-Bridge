const SETTINGS_PREFIX = 'eis-store-settings';

/**
 * @returns {{ vendorId: string | null, apiBase: string }}
 */
export function getStoreConfig() {
    const params = new URLSearchParams(window.location.search);
    const meta = document.querySelector('meta[name="eis-api-base"]');
    const apiBase = meta?.getAttribute('content')?.trim() || detectApiBase();

    return {
        vendorId: params.get('vendor') || params.get('vendor_id') || null,
        apiBase,
    };
}

function detectApiBase() {
    const { origin, pathname } = window.location;

    if (pathname.includes('/store')) {
        return `${origin}/v1`;
    }

    return '/v1';
}

/**
 * @param {string | null} vendorId
 * @returns {{ useMainList: boolean }}
 */
export function readLocalStoreSettings(vendorId) {
    const key = `${SETTINGS_PREFIX}:${vendorId ?? 'default'}`;

    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) {
            return { useMainList: true };
        }

        const parsed = JSON.parse(raw);
        return {
            useMainList: parsed.use_main_online_store_product_list !== false,
        };
    } catch {
        return { useMainList: true };
    }
}

/**
 * @param {string | null} vendorId
 * @param {boolean} useMainList
 */
export function writeLocalStoreSettings(vendorId, useMainList) {
    const key = `${SETTINGS_PREFIX}:${vendorId ?? 'default'}`;
    window.localStorage.setItem(
        key,
        JSON.stringify({ use_main_online_store_product_list: useMainList }),
    );
}

/**
 * @param {string | null} vendorId
 * @returns {import('./products.js').Product[] | null}
 */
export function readLocalStoreInventory(vendorId) {
    const key = `eis-store-inventory:${vendorId ?? 'default'}`;

    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        return Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
}
