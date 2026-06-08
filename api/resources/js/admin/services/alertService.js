import api from './api';

export const alertService = {
    list: (params) => api.get('/alerts', { params }),
    summary: () => api.get('/alerts/summary'),
    acknowledge: (id) => api.post(`/alerts/${id}/acknowledge`),
    resolve: (id) => api.post(`/alerts/${id}/resolve`),
};
