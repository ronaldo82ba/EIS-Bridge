/** @typedef {{ id: string, name: string, sku: string, category: string, brand: string, price: number, inStock: boolean, image?: string }} Product */

/** @type {Product[]} */
export const PRODUCTS = [
    { id: 'p1', name: 'Thermal Receipt Printer', sku: 'TRP-200', category: 'Hardware', brand: 'PrintPro', price: 8499, inStock: true },
    { id: 'p2', name: 'Barcode Scanner USB', sku: 'BCS-110', category: 'Hardware', brand: 'ScanTech', price: 3299, inStock: true },
    { id: 'p3', name: 'Cash Drawer 16"', sku: 'CDR-016', category: 'Hardware', brand: 'PrintPro', price: 4599, inStock: false },
    { id: 'p4', name: 'POS Terminal Bundle', sku: 'POS-BND-01', category: 'Bundles', brand: 'EIS Bridge', price: 24999, inStock: true },
    { id: 'p5', name: 'Merchant License — Starter', sku: 'LIC-M-ST', category: 'Licenses', brand: 'EIS Bridge', price: 4999, inStock: true },
    { id: 'p6', name: 'Merchant License — Enterprise', sku: 'LIC-M-EN', category: 'Licenses', brand: 'EIS Bridge', price: 19999, inStock: true },
    { id: 'p7', name: 'Vendor White-Label License', sku: 'LIC-V-WL', category: 'Licenses', brand: 'EIS Bridge', price: 99999, inStock: true },
    { id: 'p8', name: 'EIS Integration Toolkit', sku: 'KIT-EIS-01', category: 'Software', brand: 'EIS Bridge', price: 1499, inStock: true },
    { id: 'p9', name: 'JSON Schema Validator CLI', sku: 'SW-VAL-01', category: 'Software', brand: 'DevTools', price: 999, inStock: true },
    { id: 'p10', name: 'Postman Collection Pro', sku: 'SW-PM-01', category: 'Software', brand: 'DevTools', price: 499, inStock: false },
    { id: 'p11', name: 'Tablet Stand Adjustable', sku: 'ACC-TS-01', category: 'Accessories', brand: 'RetailGear', price: 1299, inStock: true },
    { id: 'p12', name: 'USB-C Hub 7-Port', sku: 'ACC-HUB-07', category: 'Accessories', brand: 'RetailGear', price: 1899, inStock: true },
    { id: 'p13', name: 'Label Printer 58mm', sku: 'LBP-058', category: 'Hardware', brand: 'PrintPro', price: 5799, inStock: true },
    { id: 'p14', name: 'SaaS Compliance Plan', sku: 'LIC-SAAS-M', category: 'Licenses', brand: 'EIS Bridge', price: 999, inStock: true },
    { id: 'p15', name: 'Onboarding Support Pack', sku: 'SVC-ONB-01', category: 'Services', brand: 'EIS Bridge', price: 7500, inStock: true },
    { id: 'p16', name: 'Certificate Management Add-on', sku: 'SW-CERT-01', category: 'Software', brand: 'EIS Bridge', price: 2499, inStock: true },
];

export const CATEGORIES = [...new Set(PRODUCTS.map((p) => p.category))].sort();
export const BRANDS = [...new Set(PRODUCTS.map((p) => p.brand))].sort();
