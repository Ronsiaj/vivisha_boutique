import axios from 'axios';
import { store } from '../store';
import { logoutSuccess } from '../store/authslice';

// Predefined Base URL (can be read from environment variables)
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const apiClient = axios.create({
    baseURL: BASE_URL,
    timeout: 15000,
    withCredentials: true,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request Interceptor: Inject token automatically from Redux state or namespaced localStorage
apiClient.interceptors.request.use(
    (config) => {
        if (!config.headers.Authorization) {
            const isAdminRequest = config.accountType === 'admin' || 
                config.headers?.['X-Account-Type'] === 'admin' || 
                (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin'));
            
            const adminToken = localStorage.getItem('vivisha_admin_token');
            const customerToken = localStorage.getItem('vivisha_user_token');
            const token = isAdminRequest ? adminToken : customerToken;

            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response Interceptor: Predefined error and token expiration handling
apiClient.interceptors.response.use(
    (response) => {
        return {
            success: true,
            data: response.data,
            status: response.status,
            error: null,
        };
    },
    async (error) => {
        const customError = {
            success: false,
            data: null,
            status: error.response?.status || 500,
            error: error.response?.data?.message || error.message || 'Something went wrong',
        };

        // Clear stale credentials if unauthorized (401)
        if (customError.status === 401) {
            const isAdmin = error.config?.accountType === 'admin' || 
                error.config?.headers?.['X-Account-Type'] === 'admin' || 
                (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin'));

            if (isAdmin) {
                localStorage.removeItem('vivisha_admin_user');
                localStorage.removeItem('vivisha_admin_token');
            } else {
                store.dispatch(logoutSuccess());
                localStorage.removeItem('vivisha_user_user');
                localStorage.removeItem('vivisha_user_token');
            }
        }

        return Promise.resolve(customError); // Resolve instead of reject so calls don't crash components
    }
);

// Simple Wrapper APIs
export const api = {
    get: (url, config = {}) => apiClient.get(url, config),
    post: (url, data = {}, config = {}) => apiClient.post(url, data, config),
    put: (url, data = {}, config = {}) => apiClient.put(url, data, config),
    delete: (url, config = {}) => apiClient.delete(url, config),
    patch: (url, data = {}, config = {}) => apiClient.patch(url, data, config),
};

export default api;
