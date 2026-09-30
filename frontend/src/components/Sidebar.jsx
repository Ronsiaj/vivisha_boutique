import React, { useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const Sidebar = ({ isOpen, onClose, initialCategories = [] }) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, isAuthenticated, logout } = useAuth();

  const [categories, setCategories] = useState(
    Array.isArray(initialCategories) && initialCategories.length > 0 ? initialCategories : []
  );
  const [isCategoriesExpanded, setIsCategoriesExpanded] = useState(false);

  // Sync initialCategories if passed or updated from parent
  useEffect(() => {
    if (Array.isArray(initialCategories) && initialCategories.length > 0) {
      setCategories(initialCategories);
    }
  }, [initialCategories]);

  // Fetch active categories if not passed or empty
  useEffect(() => {
    let isMounted = true;
    const fetchCategories = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/category/list.php?limit=100`);
        const data = await response.json();
        if (isMounted && data.status && data.data?.categories) {
          const activeOnly = data.data.categories.filter((c) => c.status === 'active');
          if (activeOnly.length > 0) {
            setCategories(activeOnly);
          }
        }
      } catch (err) {
        console.error('Failed to fetch categories for mobile sidebar:', err);
      }
    };

    if (categories.length === 0) {
      fetchCategories();
    }
    return () => {
      isMounted = false;
    };
  }, [categories.length]);

  const handleLogout = () => {
    logout();
    onClose();
    navigate('/');
  };

  const toggleCategories = () => {
    setIsCategoriesExpanded((prev) => !prev);
  };

  const menuItems = [
    { id: 'home', path: '/', label: 'Home', hasArrow: false },
    { id: 'categories', path: '/collections', label: 'Categories', hasArrow: true },
    { id: 'new_arrivals', path: '/collections?is_new_arrival=1', label: 'New Arrivals', hasArrow: false },
    { id: 'top_selling', path: '/collections?is_best_seller=1', label: 'Top Selling', hasArrow: false },
    { id: 'track-order', path: '/track-order', label: 'Order Tracking', hasArrow: false },
    { id: 'about', path: '/about', label: 'About Us', hasArrow: false },
    { id: 'contact', path: '/contact', label: 'Contact Us', hasArrow: false },
    { id: 'privacy-policy', path: '/privacy-policy', label: 'Privacy Policy', hasArrow: false },
    { id: 'terms', path: '/terms', label: 'Terms and Conditions', hasArrow: false }
  ];

  return (
    <>
      {/* Overlay Backdrop */}
      <div
        className={`sidebar-overlay ${isOpen ? 'open' : ''}`}
        onClick={onClose}
      ></div>

      {/* Mobile Sidebar Drawer Panel */}
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

        {/* Navigation List — Site navigation */}
        <div className="sidebar-menu-body">
          <div className="sidebar-nav-list">
            {menuItems.map((item) => {
              const isActive =
                !item.hasArrow &&
                (item.path.includes('?')
                  ? location.pathname + location.search === item.path
                  : location.pathname === item.path && !location.search);

              if (item.hasArrow) {
                return (
                  <React.Fragment key={item.id}>
                    <button
                      type="button"
                      className={`sidebar-nav-item-row has-submenu ${isCategoriesExpanded ? 'expanded' : ''}`}
                      onClick={toggleCategories}
                      aria-expanded={isCategoriesExpanded}
                      aria-label="Categories menu"
                    >
                      <span className="sidebar-nav-text">{item.label}</span>
                      <span className={`sidebar-nav-arrow ${isCategoriesExpanded ? 'expanded' : ''}`}>
                        <svg
                          width="18"
                          height="18"
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="2.4"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          className="sidebar-arrow-icon"
                        >
                          <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                      </span>
                    </button>

                    {/* Categories Submenu Dropdown */}
                    {isCategoriesExpanded && (
                      <div className="sidebar-categories-dropdown">
                        <Link
                          to="/collections"
                          onClick={onClose}
                          className={`sidebar-category-subitem ${location.pathname === '/collections' && !location.search ? 'active' : ''}`}
                        >
                          <span className="category-subtext">All Categories</span>
                        </Link>

                        {categories.map((cat) => {
                          const isCatActive =
                            location.pathname === '/collections' &&
                            location.search === `?category_id=${cat.id}`;

                          return (
                            <Link
                              key={`cat-${cat.id}`}
                              to={`/collections?category_id=${cat.id}`}
                              onClick={onClose}
                              className={`sidebar-category-subitem ${isCatActive ? 'active' : ''}`}
                            >
                              <span className="category-subtext">{cat.name}</span>
                            </Link>
                          );
                        })}
                      </div>
                    )}
                  </React.Fragment>
                );
              }

              return (
                <div key={item.id} className={`sidebar-nav-item-row ${isActive ? 'active' : ''}`}>
                  <Link
                    to={item.path}
                    onClick={onClose}
                    className="sidebar-nav-item-link"
                  >
                    <span className="sidebar-nav-text">{item.label}</span>
                  </Link>
                </div>
              );
            })}

            {/* Login / Register or Logout */}
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
