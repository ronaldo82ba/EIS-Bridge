import api from './api';

export const billingService = {
    summary: () => api.get('/billing/summary'),
    plans: (params) => api.get('/license-plans', { params }),
    createPlan: (payload) => api.post('/license-plans', payload),
    invoices: (params) => api.get('/billing/invoices', { params }),
    invoice: (id) => api.get(`/billing/invoices/${id}`),
    generateInvoices: (payload) => api.post('/billing/generate', payload),
    vendorLicenses: (vendorId) => api.get(`/vendors/${vendorId}/licenses`),
    assignVendorLicense: (vendorId, payload) => api.post(`/vendors/${vendorId}/licenses`, payload),
    merchantLicenses: (merchantId) => api.get(`/merchants/${merchantId}/licenses`),
    assignMerchantLicense: (merchantId, payload) => api.post(`/merchants/${merchantId}/licenses`, payload),
};
