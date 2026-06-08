/**
 * @param {import('./products.js').Product[]} products
 * @param {{
 *   search?: string,
 *   category?: string,
 *   priceMin?: string | number,
 *   priceMax?: string | number,
 *   brand?: string,
 *   inStock?: boolean,
 *   outOfStock?: boolean,
 * }} filters
 */
export function filterProducts(products, filters = {}) {
    const search = (filters.search ?? '').trim().toLowerCase();
    const category = (filters.category ?? '').trim();
    const brand = (filters.brand ?? '').trim();
    const priceMin = parsePrice(filters.priceMin);
    const priceMax = parsePrice(filters.priceMax);
    const inStock = Boolean(filters.inStock);
    const outOfStock = Boolean(filters.outOfStock);
    const availabilityFilter = inStock || outOfStock;

    return products.filter((product) => {
        if (search) {
            const haystack = `${product.name} ${product.sku} ${product.category}`.toLowerCase();
            if (!haystack.includes(search)) {
                return false;
            }
        }

        if (category && product.category !== category) {
            return false;
        }

        if (brand && product.brand !== brand) {
            return false;
        }

        if (priceMin !== null && product.price < priceMin) {
            return false;
        }

        if (priceMax !== null && product.price > priceMax) {
            return false;
        }

        if (availabilityFilter) {
            if (inStock && !product.inStock) {
                return false;
            }
            if (outOfStock && product.inStock) {
                return false;
            }
        }

        return true;
    });
}

/**
 * @param {string | number | null | undefined} value
 * @returns {number | null}
 */
function parsePrice(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}
