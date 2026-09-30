import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../src/context/AuthContext.jsx';

export const PublicRoute = () => {
    const { isAuthenticated, isAuthChecking } = useAuth();
    if (isAuthChecking) {
        return null;
    }
    return isAuthenticated ? <Navigate to="/profile" replace /> : <Outlet />;
};