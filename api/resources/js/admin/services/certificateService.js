import api from './api';

export const certificateService = {
    list: (params) => api.get('/certificates', { params }),
    get: (id) => api.get(`/certificates/${id}`),
    create: (formData) =>
        api.post('/certificates', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        }),
    uploadForMerchant: (merchantId, formData) => {
        if (!formData.has('merchant_id')) {
            formData.append('merchant_id', merchantId);
        }

        return api.post('/certificates', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
    },
    testSigning: (id) => api.post(`/certificates/${id}/test-signing`),
};
