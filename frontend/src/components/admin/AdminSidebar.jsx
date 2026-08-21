import React from 'react';
import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import logo from '../../../assets/images/boutique_logo.png';

const AdminSidebar = ({ isCollapsed, isMobileOpen, onCloseMobile }) => {
  const location = useLocation();
  const navigate = useNavigate();

  const handleLogout = (e) => {
    e.preventDefault();
    if (onCloseMobile) onCloseMobile();
    navigate('/admin/login');
  };

  const navSections = [
    {
      title: 'CATALOGUE',
      items: [
        {
          label: 'Products',
          path: '/admin/products',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
          )
        },
        {
          label: 'Variants (Stock & Price)',
          path: '/admin/variants',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="8" y1="6" x2="21" y2="6"></line>
              <line x1="8" y1="12" x2="21" y2="12"></line>
              <line x1="8" y1="18" x2="21" y2="18"></line>
              <line x1="3" y1="6" x2="3.01" y2="6"></line>
              <line x1="3" y1="12" x2="3.01" y2="12"></line>
              <line x1="3" y1="18" x2="3.01" y2="18"></line>
            </svg>
          )
        },
        {
          label: 'Categories',
          path: '/admin/categories',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
            </svg>
          )
        }
      ]
    },
    {
      title: 'SALES',
      items: [
        {
          label: 'Customers',
          path: '/admin/customers',
          icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
              <circle cx="9" cy="7" r="4"></circle>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
          )
        }
      ]
    }
  ];

  return (
    <>
      {/* Mobile Drawer Overlay */}
      {isMobileOpen && (
        <div className="admin-sidebar-overlay" onClick={onCloseMobile}></div>
      )}

      <aside
        className={`admin-sidebar ${isCollapsed ? 'collapsed' : ''} ${isMobileOpen ? 'mobile-open' : ''}`}
      >
        {/* Sidebar Header */}
        <div className="admin-sidebar-header">
          <div className="brand-badge-logo">
            <img src={logo} alt="Vivisha Boutique Logo" />
          </div>
        </div>

        {/* Navigation List */}
        <div className="admin-sidebar-nav">
          {navSections.map((section, idx) => (
            <div key={idx} className="nav-group">
              {section.title && (
                <div className="nav-section-title">{section.title}</div>
              )}
              {section.items.map((item) => {
                const isActive = location.pathname === item.path;
                return (
                  <NavLink
                    key={item.path}
                    to={item.path}
                    onClick={onCloseMobile}
                    className={`admin-nav-item ${isActive ? 'active' : ''}`}
                    title={item.label}
                  >
                    <span className="nav-icon">{item.icon}</span>
                    <span className="nav-label">{item.label}</span>
                  </NavLink>
                );
              })}
            </div>
          ))}
        </div>

        {/* Footer Logout Action */}
        <div className="admin-sidebar-footer">
          <a href="#logout" className="admin-nav-item logout-btn" onClick={handleLogout} title="Logout">
            <span className="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
            </span>
            <span className="nav-label">Logout</span>
          </a>
        </div>
      </aside>
    </>
  );
};

export default AdminSidebar;
