import React from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from '../src/context/AuthContext.jsx';

export const PrivateRoute = ({ allowedRoles }) => {
    const { isAuthenticated, user, isAuthChecking } = useAuth();
    const location = useLocation();
    
    if (isAuthChecking) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '60vh' }}>
                <span className="btn-spinner" style={{ width: '28px', height: '28px', border: '3px solid #f3f3f3', borderTop: '3px solid #be185d', borderRadius: '50%', display: 'inline-block', animation: 'spin 1s linear infinite' }}></span>
            </div>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" state={{ from: location.pathname }} replace />;
    }

    if (allowedRoles && !allowedRoles.includes(user?.role)) {
        return <Navigate to="/" replace />;
    }

    return <Outlet />;
};