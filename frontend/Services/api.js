import axios from 'axios';
import { store } from '../store';
import { logoutSuccess } from '../store/authslice';

// Predefined Base URL (can be read from environment variables)
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://api.ammachi.com/v1';

const apiClient = axios.create({
    baseURL: BASE_URL,
    timeout: 15000,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Request Interceptor: Inject token automatically from Redux state
apiClient.interceptors.request.use(
    (config) => {
        const state = store.getState();
        const token = state.auth?.token || localStorage.getItem('token');

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
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

        // Auto logout if token is expired or unauthorized (401)
        if (customError.status === 401) {
            store.dispatch(logoutSuccess());
            localStorage.removeItem('token');
            // Redirect to login if browser context is available
            if (typeof window !== 'undefined') {
                window.location.href = '/login';
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
