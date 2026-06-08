import api from './api';

export const certificateAlertService = {
    list: (params = {}) => api.get('/certificate-alerts', { params }),
};
