import React from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const Sidebar = ({ isOpen, onClose }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, isAuthenticated, logout } = useAuth();

  const handleLogout = () => {
    logout();
    onClose();
    navigate('/');
  };

  const menuItems = [
    { path: '/', label: 'Home', hasArrow: false },
    { path: '/collections', label: 'Collections', hasArrow: true },
    { path: '/about', label: 'About Us', hasArrow: false },
    { path: '/contact', label: 'Contact Us', hasArrow: false },
    { path: '/privacy-policy', label: 'Privacy Policy', hasArrow: false },
    { path: '/terms', label: 'Terms and Conditions', hasArrow: false }
  ];

  return (
    <>
      {/* Overlay Backdrop */}
      <div
        className={`sidebar-overlay ${isOpen ? 'open' : ''}`}
        onClick={onClose}
      ></div>

      {/* Mobile Sidebar Drawer Panel (Sharp Corners) */}
      <div className={`boutique-sidebar ${isOpen ? 'open' : ''}`}>
        {/* Header Bar using Consistent Vivisha Purple (#A049A3) */}
        <div className="sidebar-header">
          <div className="sidebar-title-area">
            <span className="sidebar-menu-title">Menu</span>
            <button className="sidebar-close-btn" onClick={onClose} aria-label="Close Menu">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>

          {/* Show username & mobile ONLY after login */}
          {isAuthenticated && (
            <div className="sidebar-user-row">
              <div className="sidebar-user-info-text">
                <h3 className="user-name">{user?.name || 'User Profile'}</h3>
                <p className="user-mobile">{user?.phone || ''}</p>
              </div>
              <div className="sidebar-user-profile-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
              </div>
            </div>
          )}
        </div>

        {/* Navigation List — Site navigation only */}
        <div className="sidebar-menu-body">
          <div className="sidebar-nav-list">
            {menuItems.map((item) => {
              const isActive = location.pathname === item.path;
              return (
                <Link
                  key={item.path}
                  to={item.path}
                  onClick={onClose}
                  className={`sidebar-nav-item ${isActive ? 'active' : ''}`}
                >
                  <span className="sidebar-nav-text">{item.label}</span>
                  {item.hasArrow && (
                    <span className="sidebar-nav-arrow">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </span>
                  )}
                </Link>
              );
            })}

            {/* Login / Register or Logout placed cleanly in menu list */}
            {isAuthenticated ? (
              <button onClick={handleLogout} className="sidebar-nav-item auth-item logout-link">
                <span className="auth-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                </span>
                <span className="sidebar-nav-text">Logout</span>
              </button>
            ) : (
              <Link to="/login" onClick={onClose} className="sidebar-nav-item auth-item login-link">
                <span className="auth-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </span>
                <span className="sidebar-nav-text">Login / Register</span>
              </Link>
            )}
          </div>
        </div>
      </div>
    </>
  );
};

export default Sidebar;
