import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../src/context/AuthContext.jsx';

export const PrivateRoute = ({ allowedRoles }) => {
    const { token, isAuthenticated, user } = useAuth();
    const location = useLocation();
    
    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location.pathname }} replace />;
    }

    if (allowedRoles && !allowedRoles.includes(user?.role)) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
};