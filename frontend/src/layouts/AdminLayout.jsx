import React, { useState } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import AdminSidebar from '../components/admin/AdminSidebar';
import AdminHeader from '../components/admin/AdminHeader';
import { useAuth } from '../context/AuthContext.jsx';

const AdminLayout = () => {
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [isMobileOpen, setIsMobileOpen] = useState(false);
  const { user, isAuthenticated } = useAuth();

  // Role Protection: Redirect non-admins or unauthenticated users to common /login page
  if (!isAuthenticated || user?.role !== 'admin') {
    return <Navigate to="/login" replace />;
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
