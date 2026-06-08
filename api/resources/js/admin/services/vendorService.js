import api from './api';

export const vendorService = {
    list: (params) => api.get('/vendors', { params }),
    get: (id) => api.get(`/vendors/${id}`),
    create: (data) => api.post('/vendors', data),
    update: (id, data) => api.patch(`/vendors/${id}`, data),
    rotateApiKey: (id) => api.post(`/vendors/${id}/rotate-api-key`),
    regenerateKey: (id) => api.post(`/vendors/${id}/rotate-api-key`),
    suspend: (id) => api.post(`/vendors/${id}/suspend`),
    updateWebhook: (id, data) => api.patch(`/webhooks/${id}`, data),
    listIpWhitelist: (id) => api.get(`/vendors/${id}/ip-whitelist`),
    addIpWhitelist: (id, data) => api.post(`/vendors/${id}/ip-whitelist`, data),
    removeIpWhitelist: (id, entryId) => api.delete(`/vendors/${id}/ip-whitelist/${entryId}`),
    getAnalytics: (id, range = '30d') => api.get(`/vendors/${id}/analytics`, { params: { range } }),
    getHealth: (id, range = '30d') => api.get(`/vendors/${id}/health`, { params: { range } }),
};
