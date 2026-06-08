import api from './api';

export const storeService = {
    getInventory: (vendorId) => api.get(`/vendors/${vendorId}/store-inventory`),
    previewProducts: (vendorId) => api.get(`/vendors/${vendorId}/store-inventory/preview`),
    createInventoryItem: (vendorId, data) => api.post(`/vendors/${vendorId}/store-inventory`, data),
    updateInventoryItem: (vendorId, itemId, data) =>
        api.patch(`/vendors/${vendorId}/store-inventory/${itemId}`, data),
    deleteInventoryItem: (vendorId, itemId) =>
        api.delete(`/vendors/${vendorId}/store-inventory/${itemId}`),
    updateSettings: (vendorId, data) => api.patch(`/vendors/${vendorId}/store-settings`, data),
};
