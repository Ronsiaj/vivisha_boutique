import React from 'react';
import { useLocation, Link } from 'react-router-dom';

const routeTitleMap = {
  '/admin/dashboard': 'Dashboard',
  '/admin/reports': 'Reports & Analytics',
  '/admin/products': 'Products Catalogue',
  '/admin/categories': 'Product Categories',
  '/admin/banners-coupons': 'Banners & Coupons',
  '/admin/suppliers': 'Suppliers & Vendors',
  '/admin/purchases': 'Purchase Orders',
  '/admin/pos': 'POS Sale (In-Store)',
  '/admin/orders': 'Customer Orders',
  '/admin/customers': 'Customers Directory',
  '/admin/payments': 'Payments & Transactions',
  '/admin/returns': 'Returns & Refunds',
  '/admin/whatsapp': 'WhatsApp Engagement',
};

const AdminHeader = ({ onToggleSidebar, onToggleMobileSidebar, isCollapsed }) => {
  const location = useLocation();
  const currentTitle = routeTitleMap[location.pathname] || 'Admin Panel';

  return (
    <header className="admin-header">
      <div className="admin-header-left">
        {/* Toggle Button for Desktop */}
        <button
          className="admin-sidebar-toggle desktop-toggle"
          onClick={onToggleSidebar}
          aria-label="Toggle Sidebar"
          title="Toggle Sidebar"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>

        {/* Toggle Button for Mobile */}
        <button
          className="admin-sidebar-toggle mobile-toggle"
          onClick={onToggleMobileSidebar}
          aria-label="Toggle Mobile Menu"
          title="Toggle Mobile Menu"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>

        {/* <h2 className="admin-header-page-title">{currentTitle}</h2> */}
      </div>

      <div className="admin-header-right">
        {/* View Store Button */}
        <Link
          to="/"
          target="_blank"
          rel="noopener noreferrer"
          className="admin-header-btn store-link-btn"
          title="Open Customer Website"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="2" y1="12" x2="22" y2="12"></line>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
          </svg>
          <span>View Website</span>
        </Link>

        {/* Quick Notifications */}
        <button className="admin-header-icon-btn" title="Notifications">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
          </svg>
          <span className="notification-dot"></span>
        </button>

        {/* User Profile Pill */}
        <div className="admin-user-profile">
          <div className="admin-avatar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </div>
          <div className="admin-user-info">
            <span className="user-name">Vivisha Admin</span>
            <span className="user-role">Super Admin</span>
          </div>
        </div>
      </div>
    </header>
  );
};

export default AdminHeader;
