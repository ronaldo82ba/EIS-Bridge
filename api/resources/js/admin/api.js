import axios from 'axios';
import { useAuthStore } from './store/authStore';

const api = axios.create({
    baseURL: '/api/admin',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
    withXSRFToken: true,
});

api.interceptors.request.use((config) => {
    const token = useAuthStore.getState().token ?? localStorage.getItem('token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            useAuthStore.getState().logout();
            window.location.href = '/admin/login';
        }

        if (error.response?.status === 403) {
            error.friendlyMessage =
                error.response?.data?.message ??
                'You do not have permission to perform this action.';
        }

        return Promise.reject(error);
    },
);

export default api;
