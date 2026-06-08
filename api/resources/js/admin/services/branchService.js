import api from './api';

export const branchService = {
    list: (params) => api.get('/branches', { params }),
    get: (id) => api.get(`/branches/${id}`),
    create: (data) => api.post('/branches', data),
    update: (id, data) => api.patch(`/branches/${id}`, data),
    createDevice: (branchId, data) => api.post(`/branches/${branchId}/devices`, data),
    updateDevice: (deviceId, data) => api.patch(`/devices/${deviceId}`, data),
};
