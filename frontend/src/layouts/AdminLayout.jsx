import React, { useState } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import AdminSidebar from '../components/admin/AdminSidebar';
import AdminHeader from '../components/admin/AdminHeader';
import { useAdminAuth } from '../context/AuthContext.jsx';

const AdminLayout = () => {
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isMobileOpen, setIsMobileOpen] = useState(false);
  const { user, isAuthenticated, isAuthChecking } = useAdminAuth();

  // If startup auth verification is in progress, wait before redirecting
  if (isAuthChecking) {
    return (
      <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '100vh', background: '#f8fafc' }}>
        <span className="btn-spinner" style={{ width: '36px', height: '36px', border: '3px solid #e2e8f0', borderTop: '3px solid #0f172a', borderRadius: '50%', display: 'inline-block', animation: 'spin 1s linear infinite' }}></span>
      </div>
    );
  }

  // Role Protection: Redirect non-admins or unauthenticated users to /admin/login page
  if (!isAuthenticated || user?.role !== 'admin') {
    return <Navigate to="/admin/login" replace />;
  }

  const toggleSidebar = () => {
    setIsCollapsed(prev => !prev);
  };

  const toggleMobileSidebar = () => {
    setIsMobileOpen(prev => !prev);
  };

  const closeMobileSidebar = () => {
    setIsMobileOpen(false);
  };

  return (
    <div className={`admin-layout ${isCollapsed ? 'sidebar-collapsed' : ''}`}>
      {/* Sidebar Navigation */}
      <AdminSidebar 
        isCollapsed={isCollapsed}
        isMobileOpen={isMobileOpen}
        onCloseMobile={closeMobileSidebar}
      />

      {/* Main Right Section */}
      <div className="admin-wrapper">
        {/* Top Header Bar */}
        <AdminHeader 
          onToggleSidebar={toggleSidebar}
          onToggleMobileSidebar={toggleMobileSidebar}
          isCollapsed={isCollapsed}
        />

        {/* Dynamic Page Content */}
        <main className="admin-main-content">
          <Outlet />
        </main>
      </div>
    </div>
  );
};

export default AdminLayout;
