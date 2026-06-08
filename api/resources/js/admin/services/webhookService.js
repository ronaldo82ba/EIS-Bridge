import api from './api';

export const webhookService = {
    index: () => api.get('/webhooks'),
    get: (vendorId) => api.get(`/webhooks/${vendorId}`),
    update: (vendorId, data) => api.patch(`/webhooks/${vendorId}`, data),
    test: (vendorId) => api.post(`/webhooks/${vendorId}/test`),
    deliveries: (vendorId, params) => api.get(`/webhooks/${vendorId}/deliveries`, { params }),
    listDeliveries: (params) => api.get('/logs/webhooks', { params }),
};
