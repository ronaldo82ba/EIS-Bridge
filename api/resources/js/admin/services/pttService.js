import api from './api';

export const pttService = {
    create: (data) => api.post('/ptts', data),
    upsert: (merchantId, data) => api.put(`/merchants/${merchantId}/ptt`, data),
};
