import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';

const MobileBottomNav = () => {
  const location = useLocation();
  const { wishlistCount } = useWishlist();
  const { cartCount } = useCart();

  const navItems = [
    {
      path: '/',
      label: 'Home',
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
          <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
      )
    },
    {
      path: '/collections',
      label: 'Shop',
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <rect x="2" y="3" width="7" height="7"></rect>
          <rect x="15" y="3" width="7" height="7"></rect>
          <rect x="15" y="15" width="7" height="7"></rect>
          <rect x="2" y="15" width="7" height="7"></rect>
        </svg>
      )
    },
    {
      path: '/wishlist',
      label: 'Wishlist',
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
      )
    },
    {
      path: '/cart',
      label: 'Cart',
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="9" cy="21" r="1"></circle>
          <circle cx="20" cy="21" r="1"></circle>
          <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
      )
    },
    {
      path: '/login',
      label: 'Account',
      icon: (
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
      )
    }
  ];

  return (
    <nav className="mobile-bottom-nav">
      {navItems.map((item) => {
        const isActive = location.pathname === item.path || (item.path === '/collections' && location.pathname.startsWith('/collections'));
        return (
          <Link
            key={item.path}
            to={item.path}
            className={`mobile-bottom-nav-item ${isActive ? 'active' : ''}`}
          >
            <span className="bottom-nav-icon">
              {item.icon}
              {item.path === '/wishlist' && wishlistCount > 0 && (
                <span className="header-badge-count" style={{ top: '-6px', right: '-12px', zIndex: 99 }}>{wishlistCount}</span>
              )}
              {item.path === '/cart' && cartCount > 0 && (
                <span className="header-badge-count" style={{ top: '-6px', right: '-12px', zIndex: 99 }}>{cartCount}</span>
              )}
            </span>
            <span className="bottom-nav-label">{item.label}</span>
          </Link>
        );
      })}
    </nav>
  );
};

export default MobileBottomNav;
