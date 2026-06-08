import api from './api';

export const invoiceService = {
    list: (params) => api.get('/invoices', { params }),
    search: (params) => api.get('/invoices/search', { params }),
    getAnalytics: (range, params = {}) => api.get('/invoices/analytics', { params: { range, ...params } }),
    get: (id) => api.get(`/invoices/${id}`),
    retry: (id) => api.post(`/invoices/${id}/retry`),
    bulk: (action, ids) => api.post('/invoices/bulk', { action, ids }),
    getAnalytics: (range, params = {}) => api.get('/invoices/analytics', { params: { range, ...params } }),
};
