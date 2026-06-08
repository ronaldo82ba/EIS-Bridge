import api from './api';

export const queueService = {
    status: () => api.get('/queues'),
    failedJobs: (params) => api.get('/jobs/failed', { params }),
    retryJob: (id) => api.post(`/jobs/${id}/retry`),
    deleteJob: (id) => api.delete(`/jobs/${id}`),
};
