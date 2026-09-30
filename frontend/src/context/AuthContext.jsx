import React, { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { useDispatch } from 'react-redux';
import { loginSuccess, logoutSuccess } from '../../store/authslice.js';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

/**
 * Storage Keys for explicit customer and admin session isolation
 */
export const STORAGE_KEYS = {
  CUSTOMER_TOKEN: 'vivisha_user_token',
  CUSTOMER_USER: 'vivisha_user_user',
  ADMIN_TOKEN: 'vivisha_admin_token',
  ADMIN_USER: 'vivisha_admin_user'
};

/**
 * Safely decodes a JWT token string into its JSON payload.
 * Returns null if the token is invalid or malformed.
 */
export const parseJwt = (token) => {
  if (!token || typeof token !== 'string') return null;
  try {
    const parts = token.split('.');
    if (parts.length !== 3) return null;
    const base64Url = parts[1];
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const jsonPayload = decodeURIComponent(
      atob(base64)
        .split('')
        .map((c) => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2))
        .join('')
    );
    return JSON.parse(jsonPayload);
  } catch (e) {
    return null;
  }
};

/**
 * Checks whether a given JWT token is expired or will expire within bufferSeconds.
 */
export const isTokenExpired = (token, bufferSeconds = 0) => {
  if (!token) return true;
  const decoded = parseJwt(token);
  if (!decoded || !decoded.exp) return true;
  const currentTime = Math.floor(Date.now() / 1000);
  return decoded.exp <= (currentTime + bufferSeconds);
};

/**
 * Legacy Storage Keys from previous architecture that must be purged
 */
export const LEGACY_STORAGE_KEYS = [
  'vivisha_auth_token',
  'vivisha_auth_user',
  'token',
  'admin_token',
  'auth_token',
  'user'
];

/**
 * Permanently purges all obsolete legacy storage keys from browser LocalStorage
 */
export const cleanupLegacyAuthStorage = () => {
  if (typeof window === 'undefined' || !window.localStorage) return;
  try {
    LEGACY_STORAGE_KEYS.forEach((key) => {
      localStorage.removeItem(key);
    });
  } catch (e) {
    // Gracefully handle browser storage quota or privacy mode errors
  }
};

// Immediately execute cleanup when the bundle is evaluated
cleanupLegacyAuthStorage();

// Storage Helpers
export const getStoredCustomerUser = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEYS.CUSTOMER_USER);
    return saved ? JSON.parse(saved) : null;
  } catch (e) {
    return null;
  }
};

export const getStoredCustomerToken = () => {
  return localStorage.getItem(STORAGE_KEYS.CUSTOMER_TOKEN) || null;
};

export const setStoredCustomerSession = (user, token) => {
  cleanupLegacyAuthStorage();
  if (user) localStorage.setItem(STORAGE_KEYS.CUSTOMER_USER, JSON.stringify(user));
  if (token) localStorage.setItem(STORAGE_KEYS.CUSTOMER_TOKEN, token);
};

export const clearStoredCustomerSession = () => {
  cleanupLegacyAuthStorage();
  localStorage.removeItem(STORAGE_KEYS.CUSTOMER_USER);
  localStorage.removeItem(STORAGE_KEYS.CUSTOMER_TOKEN);
};

export const getStoredAdminUser = () => {
  try {
    const saved = localStorage.getItem(STORAGE_KEYS.ADMIN_USER);
    return saved ? JSON.parse(saved) : null;
  } catch (e) {
    return null;
  }
};

export const getStoredAdminToken = () => {
  return localStorage.getItem(STORAGE_KEYS.ADMIN_TOKEN) || null;
};

export const setStoredAdminSession = (user, token) => {
  cleanupLegacyAuthStorage();
  if (user) localStorage.setItem(STORAGE_KEYS.ADMIN_USER, JSON.stringify(user));
  if (token) localStorage.setItem(STORAGE_KEYS.ADMIN_TOKEN, token);
};

export const clearStoredAdminSession = () => {
  cleanupLegacyAuthStorage();
  localStorage.removeItem(STORAGE_KEYS.ADMIN_USER);
  localStorage.removeItem(STORAGE_KEYS.ADMIN_TOKEN);
};

const AuthContext = createContext(null);

// Module-level locks to prevent concurrent/duplicated refresh requests
let inFlightCustomerRefreshPromise = null;
let inFlightAdminRefreshPromise = null;

export const AuthProvider = ({ children }) => {
  // Ensure legacy keys are cleaned on provider mount
  cleanupLegacyAuthStorage();

  const reduxDispatch = useDispatch();

  // Initial Customer State
  const initialCustomerToken = getStoredCustomerToken();
  const initialCustomerUser = getStoredCustomerUser();
  const isInitialCustomerTokenValid = initialCustomerToken ? !isTokenExpired(initialCustomerToken) : false;

  const [customerUser, setCustomerUser] = useState(isInitialCustomerTokenValid ? initialCustomerUser : null);
  const [customerToken, setCustomerToken] = useState(isInitialCustomerTokenValid ? initialCustomerToken : null);
  const [isCustomerAuthChecking, setIsCustomerAuthChecking] = useState(Boolean(initialCustomerToken && !isInitialCustomerTokenValid));

  // Initial Admin State
  const initialAdminToken = getStoredAdminToken();
  const initialAdminUser = getStoredAdminUser();
  const isInitialAdminTokenValid = initialAdminToken ? !isTokenExpired(initialAdminToken) : false;

  const [adminUser, setAdminUser] = useState(isInitialAdminTokenValid ? initialAdminUser : null);
  const [adminToken, setAdminToken] = useState(isInitialAdminTokenValid ? initialAdminToken : null);
  const [isAdminAuthChecking, setIsAdminAuthChecking] = useState(Boolean(initialAdminToken && !isInitialAdminTokenValid));

  // Sync Redux with customer session
  useEffect(() => {
    if (customerUser && customerToken && !isTokenExpired(customerToken)) {
      reduxDispatch(loginSuccess({ user: customerUser, token: customerToken }));
    } else if (!isCustomerAuthChecking) {
      reduxDispatch(logoutSuccess());
    }
  }, [customerUser, customerToken, isCustomerAuthChecking, reduxDispatch]);

  /**
   * Refreshes Customer Access Token using `account_type: 'user'` and HttpOnly cookie
   */
  const refreshCustomerAccessToken = useCallback(async () => {
    if (inFlightCustomerRefreshPromise) {
      return inFlightCustomerRefreshPromise;
    }

    inFlightCustomerRefreshPromise = (async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/refresh.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: 'include',
          body: JSON.stringify({ account_type: 'user' })
        });

        const data = await response.json();

        if (data.status && data.data?.token) {
          const { account, token: newToken } = data.data;

          const updatedUser = {
            id: account.id,
            name: account.name,
            email: account.email,
            mobile: account.mobile,
            phone: account.mobile,
            date_of_birth: account.date_of_birth || null,
            role: 'user',
            status: account.status,
            last_login: account.last_login
          };

          setCustomerUser(updatedUser);
          setCustomerToken(newToken);
          setStoredCustomerSession(updatedUser, newToken);
          reduxDispatch(loginSuccess({ user: updatedUser, token: newToken }));

          return newToken;
        } else {
          // Refresh authentication failed
          setCustomerUser(null);
          setCustomerToken(null);
          clearStoredCustomerSession();
          sessionStorage.removeItem('vivisha_buynow_pending');
          reduxDispatch(logoutSuccess());
          return null;
        }
      } catch (err) {
        console.warn('Customer refresh access token request failed:', err);
        const currentToken = getStoredCustomerToken();
        if (currentToken && isTokenExpired(currentToken)) {
          setCustomerUser(null);
          setCustomerToken(null);
          clearStoredCustomerSession();
          reduxDispatch(logoutSuccess());
        }
        return null;
      } finally {
        inFlightCustomerRefreshPromise = null;
      }
    })();

    return inFlightCustomerRefreshPromise;
  }, [reduxDispatch]);

  /**
   * Refreshes Admin Access Token using `account_type: 'admin'` and HttpOnly cookie
   */
  const refreshAdminAccessToken = useCallback(async () => {
    if (inFlightAdminRefreshPromise) {
      return inFlightAdminRefreshPromise;
    }

    inFlightAdminRefreshPromise = (async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/auth/refresh.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          credentials: 'include',
          body: JSON.stringify({ account_type: 'admin' })
        });

        const data = await response.json();

        if (data.status && data.data?.token) {
          const { account, token: newToken, account_type } = data.data;

          const updatedAdmin = {
            id: account.id,
            name: account.name,
            email: account.email,
            mobile: account.mobile,
            phone: account.mobile,
            role: account_type || 'admin',
            status: account.status,
            last_login: account.last_login
          };

          setAdminUser(updatedAdmin);
          setAdminToken(newToken);
          setStoredAdminSession(updatedAdmin, newToken);

          return newToken;
        } else {
          // Admin refresh failed
          setAdminUser(null);
          setAdminToken(null);
          clearStoredAdminSession();
          return null;
        }
      } catch (err) {
        console.warn('Admin refresh access token request failed:', err);
        const currentToken = getStoredAdminToken();
        if (currentToken && isTokenExpired(currentToken)) {
          setAdminUser(null);
          setAdminToken(null);
          clearStoredAdminSession();
        }
        return null;
      } finally {
        inFlightAdminRefreshPromise = null;
      }
    })();

    return inFlightAdminRefreshPromise;
  }, []);

  // Customer startup verification
  useEffect(() => {
    const initCustomerAuth = async () => {
      const storedToken = getStoredCustomerToken();
      const storedUser = getStoredCustomerUser();

      if (!storedToken) {
        clearStoredCustomerSession();
        setCustomerUser(null);
        setCustomerToken(null);
        setIsCustomerAuthChecking(false);
        return;
      }

      if (!isTokenExpired(storedToken)) {
        if (storedUser) {
          setCustomerUser(storedUser);
          setCustomerToken(storedToken);
          reduxDispatch(loginSuccess({ user: storedUser, token: storedToken }));
        }
        setIsCustomerAuthChecking(false);
      } else {
        setIsCustomerAuthChecking(true);
        const refreshedToken = await refreshCustomerAccessToken();
        if (!refreshedToken) {
          setCustomerUser(null);
          setCustomerToken(null);
        }
        setIsCustomerAuthChecking(false);
      }
    };

    initCustomerAuth();
  }, [refreshCustomerAccessToken, reduxDispatch]);

  // Admin startup verification
  useEffect(() => {
    const initAdminAuth = async () => {
      const storedToken = getStoredAdminToken();
      const storedUser = getStoredAdminUser();

      if (!storedToken) {
        clearStoredAdminSession();
        setAdminUser(null);
        setAdminToken(null);
        setIsAdminAuthChecking(false);
        return;
      }

      if (!isTokenExpired(storedToken)) {
        if (storedUser) {
          setAdminUser(storedUser);
          setAdminToken(storedToken);
        }
        setIsAdminAuthChecking(false);
      } else {
        setIsAdminAuthChecking(true);
        const refreshedToken = await refreshAdminAccessToken();
        if (!refreshedToken) {
          setAdminUser(null);
          setAdminToken(null);
        }
        setIsAdminAuthChecking(false);
      }
    };

    initAdminAuth();
  }, [refreshAdminAccessToken]);

  // Customer proactive renewal timer
  const customerRefreshTimerRef = useRef(null);
  useEffect(() => {
    if (customerRefreshTimerRef.current) {
      clearTimeout(customerRefreshTimerRef.current);
      customerRefreshTimerRef.current = null;
    }

    if (!customerToken || !customerUser) return;

    const decoded = parseJwt(customerToken);
    if (!decoded || !decoded.exp) return;

    const currentTime = Math.floor(Date.now() / 1000);
    const secondsRemaining = decoded.exp - currentTime;
    const refreshDelayMs = Math.max(5, secondsRemaining - 60) * 1000;

    customerRefreshTimerRef.current = setTimeout(() => {
      refreshCustomerAccessToken();
    }, refreshDelayMs);

    return () => {
      if (customerRefreshTimerRef.current) {
        clearTimeout(customerRefreshTimerRef.current);
      }
    };
  }, [customerToken, customerUser, refreshCustomerAccessToken]);

  // Admin proactive renewal timer
  const adminRefreshTimerRef = useRef(null);
  useEffect(() => {
    if (adminRefreshTimerRef.current) {
      clearTimeout(adminRefreshTimerRef.current);
      adminRefreshTimerRef.current = null;
    }

    if (!adminToken || !adminUser) return;

    const decoded = parseJwt(adminToken);
    if (!decoded || !decoded.exp) return;

    const currentTime = Math.floor(Date.now() / 1000);
    const secondsRemaining = decoded.exp - currentTime;
    const refreshDelayMs = Math.max(5, secondsRemaining - 60) * 1000;

    adminRefreshTimerRef.current = setTimeout(() => {
      refreshAdminAccessToken();
    }, refreshDelayMs);

    return () => {
      if (adminRefreshTimerRef.current) {
        clearTimeout(adminRefreshTimerRef.current);
      }
    };
  }, [adminToken, adminUser, refreshAdminAccessToken]);

  /**
   * Customer Login (does NOT touch admin storage)
   */
  const customerLogin = async ({ email, password }) => {
    try {
      const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ email: email.trim(), password })
      });

      const data = await response.json();

      if (data.status && data.data) {
        const { account_type, account, token: accessToken } = data.data;

        // If user logged in via customer login
        const loggedInUser = {
          id: account.id,
          name: account.name,
          email: account.email,
          mobile: account.mobile,
          phone: account.mobile,
          date_of_birth: account.date_of_birth || null,
          role: account_type,
          status: account.status,
          last_login: account.last_login
        };

        if (account_type === 'user') {
          setCustomerUser(loggedInUser);
          setCustomerToken(accessToken);
          setStoredCustomerSession(loggedInUser, accessToken);
          reduxDispatch(loginSuccess({ user: loggedInUser, token: accessToken }));
        } else if (account_type === 'admin') {
          // If an admin logs in on the customer login page, record admin session as well
          setAdminUser(loggedInUser);
          setAdminToken(accessToken);
          setStoredAdminSession(loggedInUser, accessToken);
        }

        return {
          success: true,
          user: loggedInUser,
          token: accessToken,
          accountType: account_type,
          message: data.message
        };
      } else {
        return {
          success: false,
          message: data.message || 'Invalid email or password.'
        };
      }
    } catch (err) {
      console.error('Customer Login API Error:', err);
      return {
        success: false,
        message: err.message || 'Network error connecting to authentication service.'
      };
    }
  };

  /**
   * Admin Login (does NOT touch customer storage)
   */
  const adminLogin = async ({ email, password }) => {
    try {
      const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify({ email: email.trim(), password })
      });

      const data = await response.json();

      if (data.status && data.data) {
        const { account_type, account, token: accessToken } = data.data;

        if (account_type !== 'admin') {
          return {
            success: false,
            message: 'Access denied. This account does not have administrator privileges.'
          };
        }

        const loggedInAdmin = {
          id: account.id,
          name: account.name,
          email: account.email,
          mobile: account.mobile,
          phone: account.mobile,
          role: 'admin',
          status: account.status,
          last_login: account.last_login
        };

        setAdminUser(loggedInAdmin);
        setAdminToken(accessToken);
        setStoredAdminSession(loggedInAdmin, accessToken);

        return {
          success: true,
          user: loggedInAdmin,
          token: accessToken,
          accountType: 'admin',
          message: data.message
        };
      } else {
        return {
          success: false,
          message: data.message || 'Invalid admin credentials.'
        };
      }
    } catch (err) {
      console.error('Admin Login API Error:', err);
      return {
        success: false,
        message: err.message || 'Network error connecting to admin authentication service.'
      };
    }
  };

  /**
   * Customer Registration
   */
  const register = async ({ name, mobile, email, date_of_birth, password }) => {
    try {
      const payload = {
        name: name.trim(),
        mobile: mobile.trim(),
        email: email.trim().toLowerCase(),
        password: password,
        date_of_birth: date_of_birth
      };

      const response = await fetch(`${API_BASE_URL}/auth/user_register.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        credentials: 'include',
        body: JSON.stringify(payload)
      });

      const data = await response.json();

      if (data.status && data.data) {
        const { account, token: accessToken } = data.data;
        const registeredUser = {
          id: account.id,
          name: account.name,
          email: account.email,
          mobile: account.mobile,
          phone: account.mobile,
          date_of_birth: account.date_of_birth,
          role: 'user',
          status: account.status,
          last_login: account.last_login
        };

        setCustomerUser(registeredUser);
        setCustomerToken(accessToken);
        setStoredCustomerSession(registeredUser, accessToken);
        reduxDispatch(loginSuccess({ user: registeredUser, token: accessToken }));

        return {
          success: true,
          user: registeredUser,
          token: accessToken,
          message: data.message || 'Registration successful.'
        };
      } else {
        return {
          success: false,
          message: data.message || 'Registration failed.'
        };
      }
    } catch (err) {
      console.error('Registration API Error:', err);
      return {
        success: false,
        message: err.message || 'Network error during registration.'
      };
    }
  };

  /**
   * Customer Logout (strictly clears customer session only)
   */
  const customerLogout = async () => {
    const currentToken = customerToken || getStoredCustomerToken();

    setCustomerUser(null);
    setCustomerToken(null);
    clearStoredCustomerSession();
    sessionStorage.removeItem('vivisha_buynow_pending');
    reduxDispatch(logoutSuccess());

    try {
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      };
      if (currentToken) {
        headers['Authorization'] = `Bearer ${currentToken}`;
      }
      await fetch(`${API_BASE_URL}/auth/logout.php`, {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify({ account_type: 'user' })
      });
    } catch (err) {
      console.warn('Customer backend logout notification error:', err);
    }

    return { success: true };
  };

  /**
   * Admin Logout (strictly clears admin session only)
   */
  const adminLogout = async () => {
    const currentToken = adminToken || getStoredAdminToken();

    setAdminUser(null);
    setAdminToken(null);
    clearStoredAdminSession();

    try {
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      };
      if (currentToken) {
        headers['Authorization'] = `Bearer ${currentToken}`;
      }
      await fetch(`${API_BASE_URL}/auth/logout.php`, {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify({ account_type: 'admin' })
      });
    } catch (err) {
      console.warn('Admin backend logout notification error:', err);
    }

    return { success: true };
  };

  /**
   * Updates customer profile in state and storage
   */
  const updateCustomerUser = (updatedFields) => {
    const newUser = { ...customerUser, ...updatedFields };
    setCustomerUser(newUser);
    localStorage.setItem(STORAGE_KEYS.CUSTOMER_USER, JSON.stringify(newUser));
    reduxDispatch(loginSuccess({ user: newUser, token: customerToken }));
  };

  const isCustomerAuthenticated = Boolean(customerUser && customerToken && !isTokenExpired(customerToken));
  const isAdminAuthenticated = Boolean(adminUser && adminToken && !isTokenExpired(adminToken));

  const contextValue = {
    // Customer session & default aliases for storefront
    user: customerUser,
    token: customerToken,
    isAuthenticated: isCustomerAuthenticated,
    isAuthChecking: isCustomerAuthChecking,
    customerUser,
    customerToken,
    isCustomerAuthenticated,
    isCustomerAuthChecking,

    // Customer operations
    login: customerLogin,
    customerLogin,
    logout: customerLogout,
    customerLogout,
    register,
    updateUser: updateCustomerUser,
    updateCustomerUser,
    refreshAccessToken: refreshCustomerAccessToken,
    refreshCustomerAccessToken,

    // Admin session & operations
    adminUser,
    adminToken,
    isAdminAuthenticated,
    isAdminAuthChecking,
    adminLogin,
    adminLogout,
    refreshAdminAccessToken
  };

  return (
    <AuthContext.Provider value={contextValue}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

/**
 * Dedicated hook for admin layout & modules
 */
export const useAdminAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAdminAuth must be used within an AuthProvider');
  }
  return {
    user: context.adminUser,
    token: context.adminToken,
    isAuthenticated: context.isAdminAuthenticated,
    isAuthChecking: context.isAdminAuthChecking,
    adminUser: context.adminUser,
    adminToken: context.adminToken,
    isAdminAuthenticated: context.isAdminAuthenticated,
    isAdminAuthChecking: context.isAdminAuthChecking,
    login: context.adminLogin,
    adminLogin: context.adminLogin,
    logout: context.adminLogout,
    adminLogout: context.adminLogout,
    refreshAccessToken: context.refreshAdminAccessToken,
    refreshAdminAccessToken: context.refreshAdminAccessToken
  };
};

export default AuthContext;
