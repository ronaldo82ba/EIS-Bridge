import api from './api';

export const logService = {
    system: (params) => api.get('/logs/system', { params }),
    transmission: (params) => api.get('/logs/transmission', { params }),
    webhooks: (params) => api.get('/logs/webhooks', { params }),
    audit: (params) => api.get('/logs/audit', { params }),
    export: (params) =>
        api.get('/logs/export', {
            params,
            responseType: 'blob',
        }),
};
