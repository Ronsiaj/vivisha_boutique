import axios from 'axios';
import { deleteAllCookies, getCookie } from './Utils';

const ApiCall = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api',
    timeout: 20000,
    withCredentials: true,
    headers: { 'Content-Type': 'application/json' }
});

ApiCall.interceptors.request.use(
    (config) => {
        if (!config.headers.Authorization) {
            const isAdminRequest = config.accountType === 'admin' || 
                config.headers?.['X-Account-Type'] === 'admin' || 
                (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin'));
            
            const adminToken = localStorage.getItem('vivisha_admin_token');
            const customerToken = localStorage.getItem('vivisha_user_token');
            const token = isAdminRequest ? (adminToken || getCookie('admin_token')) : (customerToken || getCookie('token'));

            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
        }

        // Automatically handle FormData requests to let browser set boundary
        if (config.data instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

ApiCall.interceptors.response.use(
    (response) => {
        return response.data;
    },
    (error) => {
        if (error?.response?.status === 401) {
            const isAdmin = error.config?.accountType === 'admin' || 
                error.config?.headers?.['X-Account-Type'] === 'admin' || 
                (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin'));

            if (isAdmin) {
                localStorage.removeItem('vivisha_admin_user');
                localStorage.removeItem('vivisha_admin_token');
            } else {
                localStorage.removeItem('vivisha_user_user');
                localStorage.removeItem('vivisha_user_token');
            }
        }
        return Promise.reject(error);
    }
);

export default ApiCall;
