const STORAGE_KEY = 'eis-bridge-store-cart';

/**
 * @typedef {{ productId: string, quantity: number }} CartItem
 */

export function createCartStore(storage = globalThis.localStorage) {
    /** @type {CartItem[]} */
    let items = loadItems(storage);

    function loadItems(store) {
        if (!store) {
            return [];
        }

        try {
            const raw = store.getItem(STORAGE_KEY);
            if (!raw) {
                return [];
            }

            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    }

    function persist() {
        if (storage) {
            storage.setItem(STORAGE_KEY, JSON.stringify(items));
        }
    }

    return {
        getItems() {
            return items.map((item) => ({ ...item }));
        },

        getCount() {
            return items.reduce((sum, item) => sum + item.quantity, 0);
        },

        /**
         * @param {string} productId
         * @param {number} [quantity=1]
         */
        addItem(productId, quantity = 1) {
            const qty = Math.max(1, Math.floor(quantity));
            const existing = items.find((item) => item.productId === productId);

            if (existing) {
                existing.quantity += qty;
            } else {
                items.push({ productId, quantity: qty });
            }

            persist();
            return this.getCount();
        },

        clear() {
            items = [];
            persist();
        },

        reload() {
            items = loadItems(storage);
        },
    };
}
