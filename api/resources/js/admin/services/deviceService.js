import api from './api';

export const deviceService = {
    list: (params) => api.get('/devices', { params }),
    get: (id) => api.get(`/devices/${id}`),
    create: (data) =>
        api.post('/devices', {
            ...data,
            name: data.name ?? data.pos_device_id,
        }),
    createForBranch: (branchId, data) =>
        api.post(`/branches/${branchId}/devices`, {
            ...data,
            branch_id: branchId,
            name: data.name ?? data.pos_device_id,
        }),
    update: (id, data) => api.patch(`/devices/${id}`, data),
};
