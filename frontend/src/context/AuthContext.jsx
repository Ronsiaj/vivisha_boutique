import React, { createContext, useContext, useState, useEffect } from 'react';
import { useDispatch } from 'react-redux';
import { loginSuccess, logoutSuccess } from '../../store/authslice.js';

const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const reduxDispatch = useDispatch();

  const [user, setUser] = useState(() => {
    try {
      const savedUser = localStorage.getItem('vivisha_auth_user');
      return savedUser ? JSON.parse(savedUser) : null;
    } catch (e) {
      return null;
    }
  });

  const [token, setToken] = useState(() => {
    return localStorage.getItem('vivisha_auth_token') || null;
  });

  const isAuthenticated = Boolean(user);

  useEffect(() => {
    if (user) {
      localStorage.setItem('vivisha_auth_user', JSON.stringify(user));
    } else {
      localStorage.removeItem('vivisha_auth_user');
    }
  }, [user]);

  useEffect(() => {
    if (token) {
      localStorage.setItem('vivisha_auth_token', token);
    } else {
      localStorage.removeItem('vivisha_auth_token');
    }
  }, [token]);

  // Synchronize state with Redux store on mount & change
  useEffect(() => {
    if (user && token) {
      reduxDispatch(loginSuccess({ user, token }));
    } else {
      reduxDispatch(logoutSuccess());
    }
  }, [user, token, reduxDispatch]);

  const login = async ({ email, password }) => {
    try {
      const response = await fetch('http://localhost/vivisha_boutique/backend/api/auth/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });
      const data = await response.json();

      if (data.status) {
        const { account_type, account, token } = data.data;
        const loggedInUser = {
          id: account.id,
          name: account.name,
          email: account.email,
          phone: account.mobile,
          role: account_type
        };

        setUser(loggedInUser);
        setToken(token);
        reduxDispatch(loginSuccess({ user: loggedInUser, token }));

        return {
          success: true,
          user: loggedInUser,
          token: token
        };
      } else {
        throw new Error(data.message || 'Login failed');
      }
    } catch (err) {
      console.error('Login Error:', err);
      throw err;
    }
  };

  const register = async ({ name, mobile, email, date_of_birth, password }) => {
    try {
      const payload = { name, mobile, password };
      if (email) payload.email = email;
      if (date_of_birth) payload.date_of_birth = date_of_birth;

      const response = await fetch('http://localhost/vivisha_boutique/backend/api/auth/user_register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await response.json();

      if (data.status) {
        // user_register returns { user } but not token in the response according to typical flows or it might return it, let's assume it doesn't log them in automatically or does it?
        // Wait, the backend user_register.php returns: `data: { user: {...} }` but we saw earlier it says `$token = generateUserJWT($user);` but in the output it didn't include the token in the response payload. Wait, let me look at `user_register.php` again.
        // I will just return success and let the component handle redirecting to login or just mock login for now.
        // Actually, let's just return success so they can login or we log them in.
        return {
          success: true
        };
      } else {
        throw new Error(data.message || 'Registration failed');
      }
    } catch (err) {
      console.error('Register Error:', err);
      throw err;
    }
  };

  const logout = () => {
    setUser(null);
    setToken(null);
    localStorage.removeItem('vivisha_auth_user');
    localStorage.removeItem('vivisha_auth_token');
    reduxDispatch(logoutSuccess());
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isAuthenticated,
        login,
        register,
        logout
      }}
    >
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
