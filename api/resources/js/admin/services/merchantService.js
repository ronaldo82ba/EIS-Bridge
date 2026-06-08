import api from './api';

export const merchantService = {
    list: (params) => api.get('/merchants', { params }),
    get: (id) => api.get(`/merchants/${id}`),
    getData: async (id) => {
        const response = await api.get(`/merchants/${id}`);
        return response.data?.data ?? response.data;
    },
    create: (data) => api.post('/merchants', data),
    update: (id, data) => api.patch(`/merchants/${id}`, data),
    getReadiness: (id) => api.get(`/merchants/${id}/readiness`),
    getActivity: async (id, params) => {
        const response = await api.get(`/merchants/${id}/activity`, { params });
        return response.data;
    },
    getAnalytics: (id, range = '7d') => api.get(`/merchants/${id}/analytics`, { params: { range } }),
    getHealth: (id, range = '30d') => api.get(`/merchants/${id}/health`, { params: { range } }),
    uploadCertificate: (id, formData) =>
        api.post(`/merchants/${id}/certificate`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    upsertPtt: (id, data) => api.put(`/merchants/${id}/ptt`, data),
};
