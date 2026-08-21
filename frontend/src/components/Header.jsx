import React, { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Sidebar from './Sidebar';
import logo from '../../assets/images/boutique_logo.png';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const Header = () => {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const location = useLocation();
  const { wishlistCount } = useWishlist();
  const { cartCount } = useCart();
  const { isAuthenticated, logout } = useAuth();

  const profileDestination = isAuthenticated ? '/profile' : '/login';

  const isNavActive = (path) => {
    if (path === '/') {
      return location.pathname === '/';
    }
    if (path === '/collections') {
      return location.pathname === '/collections' || location.pathname.startsWith('/product');
    }
    return location.pathname === path || location.pathname.startsWith(path);
  };

  return (
    <>
      <header className="boutique-header">
        <div className="header-left">
          <button
            className="header-menu-btn mobile-tablet-only"
            aria-label="Menu"
            onClick={() => setIsSidebarOpen(true)}
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="3" y1="6" x2="21" y2="6"></line>
              <line x1="3" y1="12" x2="21" y2="12"></line>
              <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
          </button>

          <Link to="/" className="header-logo">
            <img src={logo} alt="Vivisha Boutique Logo" />
          </Link>
        </div>

        <nav className="header-tablet-nav tablet-only">
          <Link to="/" className={isNavActive('/') ? 'active' : ''}>HOME</Link>
          <Link to="/about" className={isNavActive('/about') ? 'active' : ''}>ABOUT US</Link>
          <Link to="/contact" className={isNavActive('/contact') ? 'active' : ''}>CONTACT US</Link>
        </nav>

        <nav className="header-desktop-nav desktop-only">
          <Link to="/" className={isNavActive('/') ? 'active' : ''}>HOME</Link>
          <Link to="/collections" className={isNavActive('/collections') ? 'active' : ''}>SHOP NOW</Link>
          <Link to="/notifications" className={isNavActive('/notifications') ? 'active' : ''}>NOTIFICATIONS</Link>
          <Link to="/about" className={isNavActive('/about') ? 'active' : ''}>ABOUT US</Link>
          <Link to="/contact" className={isNavActive('/contact') ? 'active' : ''}>CONTACT US</Link>
        </nav>

        <div className="header-right">
          {/* Search Icon */}
          <button className="header-icon search-toggle-btn" aria-label="Search" onClick={() => setIsSearchOpen(!isSearchOpen)}>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>

          {/* Account Icon */}
          <Link to={profileDestination} className={`header-icon user-circle-icon hidden-on-mobile ${isNavActive('/profile') || isNavActive('/dashboard') || isNavActive('/login') ? 'active' : ''}`} aria-label="Account" title="My Account">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          </Link>

          {/* Logout Icon */}
          {isAuthenticated && (
            <button 
              className="header-icon" 
              onClick={() => logout()} 
              aria-label="Logout" 
              title="Logout"
              style={{ border: 'none', background: 'transparent', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
            >
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
            </button>
          )}

          {/* Wishlist Icon */}
          <Link to="/wishlist" className={`header-icon header-icon-with-badge ${isNavActive('/wishlist') ? 'active' : ''}`} aria-label="Wishlist">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            {wishlistCount > 0 && (
              <span className="header-badge-count">{wishlistCount}</span>
            )}
          </Link>

          {/* Cart Icon */}
          <Link to="/cart" className={`header-icon ${isNavActive('/cart') ? 'active' : ''}`} aria-label="Cart">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            {cartCount > 0 && (
              <span className="header-badge-count">{cartCount}</span>
            )}
          </Link>
        </div>

        {/* Search Dialog Overlay */}
        {isSearchOpen && (
          <div className="search-dialog-overlay">
            <div className="search-input-container">
              <input type="text" placeholder="Search For Straight Cut Suit" autoFocus />
              <button className="search-submit-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="11" cy="11" r="8"></circle>
                  <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
              </button>
              <button className="search-close-btn" onClick={() => setIsSearchOpen(false)}>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            <div className="trending-searches">
              <h4>Trending Searches</h4>
              <div className="trending-tags">
                <span className="trending-tag">Sale</span>
                <span className="trending-tag">Anarkali Suit</span>
                <span className="trending-tag">Kurti</span>
                <span className="trending-tag">Kurti Set</span>
                <span className="trending-tag">Straight Cut Suits</span>
              </div>
            </div>
          </div>
        )}
      </header>

      <Sidebar
        isOpen={isSidebarOpen}
        onClose={() => setIsSidebarOpen(false)}
      />
    </>
  );
};

export default Header;
