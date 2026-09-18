import React, { useState, useRef, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Sidebar from './Sidebar';
import logo from '../../assets/images/boutique_logo.png';
import banner1 from '../../assets/images/banner1.png';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = 'http://localhost/vivisha_boutique/backend/';

const Header = () => {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isAccountDropdownOpen, setIsAccountDropdownOpen] = useState(false);
  
  // Search State
  const [searchQuery, setSearchQuery] = useState('');
  const [categories, setCategories] = useState([]);
  const [matchingCategories, setMatchingCategories] = useState([]);
  const [matchingProducts, setMatchingProducts] = useState([]);
  const [isSearching, setIsSearching] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);

  const location = useLocation();
  const navigate = useNavigate();
  const { wishlistCount } = useWishlist();
  const { cartCount } = useCart();
  const { user, isAuthenticated, logout } = useAuth();

  const dropdownRef = useRef(null);
  const accountIconRef = useRef(null);
  const searchInputRef = useRef(null);
  const searchOverlayRef = useRef(null);

  // Close dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (e) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(e.target) &&
        accountIconRef.current &&
        !accountIconRef.current.contains(e.target)
      ) {
        setIsAccountDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Close dropdown and search on route change
  useEffect(() => {
    setIsAccountDropdownOpen(false);
    setIsSearchOpen(false);
  }, [location.pathname]);

  // Fetch active categories for suggestions
  const fetchActiveCategories = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/category/list.php?limit=100`);
      const data = await response.json();
      if (data.status && data.data && data.data.categories) {
        const activeOnly = data.data.categories.filter((c) => c.status === 'active');
        setCategories(activeOnly);
      }
    } catch (err) {
      console.error('Failed to fetch categories for search:', err);
    }
  };

  // Open search handler
  const handleToggleSearch = () => {
    setIsSearchOpen((prev) => {
      const nextState = !prev;
      if (nextState) {
        fetchActiveCategories();
      }
      return nextState;
    });
  };

  // Auto-focus input and manage body scroll
  useEffect(() => {
    if (isSearchOpen) {
      fetchActiveCategories();
      const timer = setTimeout(() => {
        searchInputRef.current?.focus();
      }, 100);
      document.body.style.overflow = 'hidden';
      return () => {
        clearTimeout(timer);
        document.body.style.overflow = '';
      };
    } else {
      document.body.style.overflow = '';
    }
  }, [isSearchOpen]);

  // Close search on Escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape' && isSearchOpen) {
        setIsSearchOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isSearchOpen]);

  // Debounced search logic for live suggestions
  useEffect(() => {
    const trimmed = searchQuery.trim();
    if (!trimmed) {
      setMatchingCategories([]);
      setMatchingProducts([]);
      setIsSearching(false);
      setHasSearched(false);
      return;
    }

    setIsSearching(true);
    setHasSearched(true);

    // Match active categories synchronously
    const queryLower = trimmed.toLowerCase();
    const matchedCats = categories.filter(
      (cat) =>
        cat.status === 'active' &&
        (cat.name.toLowerCase().includes(queryLower) ||
          (cat.slug && cat.slug.toLowerCase().includes(queryLower)) ||
          (cat.description && cat.description.toLowerCase().includes(queryLower)))
    );
    setMatchingCategories(matchedCats);

    // Debounce product variants API call
    const timer = setTimeout(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/varient/list.php?q=${encodeURIComponent(trimmed)}&limit=8`);
        const data = await res.json();
        if (data.status && data.data && data.data.variants) {
          setMatchingProducts(data.data.variants);
        } else {
          setMatchingProducts([]);
        }
      } catch (err) {
        console.error('Error fetching search products:', err);
        setMatchingProducts([]);
      } finally {
        setIsSearching(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchQuery, categories]);

  const getProductImageUrl = (variant) => {
    if (variant.primary_image && variant.primary_image.image) {
      const imgPath = variant.primary_image.image;
      if (imgPath.startsWith('http')) return imgPath;
      return `${ASSET_BASE_URL}${imgPath}`;
    }
    return banner1;
  };

  const handleCategoryClick = (categoryId) => {
    setIsSearchOpen(false);
    setSearchQuery('');
    navigate(`/collections?category_id=${categoryId}`);
  };

  const handleProductClick = (variantId) => {
    setIsSearchOpen(false);
    setSearchQuery('');
    navigate(`/product/${variantId}`);
  };

  const handleSearchSubmit = (e) => {
    if (e) e.preventDefault();
    const trimmed = searchQuery.trim();
    if (trimmed) {
      setIsSearchOpen(false);
      navigate(`/collections?q=${encodeURIComponent(trimmed)}`);
    }
  };

  const handleClearSearch = () => {
    setSearchQuery('');
    setMatchingCategories([]);
    setMatchingProducts([]);
    setHasSearched(false);
    searchInputRef.current?.focus();
  };

  const handleCloseSearch = () => {
    setIsSearchOpen(false);
    setSearchQuery('');
    setMatchingCategories([]);
    setMatchingProducts([]);
    setHasSearched(false);
  };

  const isNavActive = (path) => {
    if (path === '/') {
      return location.pathname === '/';
    }
    if (path === '/collections') {
      return location.pathname === '/collections' || location.pathname.startsWith('/product');
    }
    return location.pathname === path || location.pathname.startsWith(path);
  };

  const handleAccountIconClick = (e) => {
    if (isAuthenticated) {
      e.preventDefault();
      setIsAccountDropdownOpen((prev) => !prev);
    }
  };

  const handleLogout = () => {
    setIsAccountDropdownOpen(false);
    logout();
    navigate('/');
  };

  const firstName = user?.name ? user.name.split(' ')[0] : 'User';
  const avatarInitial = user?.name ? user.name.charAt(0).toUpperCase() : 'U';

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
          <button
            className={`header-icon search-toggle-btn ${isSearchOpen ? 'active' : ''}`}
            aria-label="Search"
            onClick={handleToggleSearch}
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <circle cx="11" cy="11" r="8"></circle>
              <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
          </button>

          {/* Account Icon + Dropdown */}
          <div className="header-account-wrapper hidden-on-mobile">
            <button
              ref={accountIconRef}
              className={`header-icon user-circle-icon ${isNavActive('/profile') || isNavActive('/dashboard') || isNavActive('/login') ? 'active' : ''}`}
              aria-label="Account"
              title="My Account"
              onClick={isAuthenticated ? handleAccountIconClick : () => navigate('/login')}
            >
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
            </button>

            {/* Account Dropdown — Desktop Only */}
            {isAuthenticated && isAccountDropdownOpen && (
              <div className="account-dropdown" ref={dropdownRef}>
                <div className="account-dropdown-header">
                  <div className="account-dropdown-avatar">{avatarInitial}</div>
                  <div className="account-dropdown-greeting">
                    <span className="greeting-hello">Hello, {firstName}</span>
                    <span className="greeting-email">{user?.email || ''}</span>
                  </div>
                </div>

                <div className="account-dropdown-divider"></div>

                <nav className="account-dropdown-menu">
                  <Link to="/profile" className="account-dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>My Profile</span>
                  </Link>
                  <Link to="/orders" className="account-dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                      <line x1="3" y1="6" x2="21" y2="6"></line>
                      <path d="M16 10a4 4 0 0 1-8 0"></path>
                    </svg>
                    <span>My Orders</span>
                  </Link>
                  <Link to="/wishlist" className="account-dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span>Wishlist</span>
                  </Link>
                  <Link to="/addresses" className="account-dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span>Saved Addresses</span>
                  </Link>
                </nav>

                <div className="account-dropdown-divider"></div>

                <button className="account-dropdown-item account-dropdown-logout" onClick={handleLogout}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  <span>Logout</span>
                </button>
              </div>
            )}
          </div>

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

        {/* Search Dialog Overlay & Live Suggestions Dropdown */}
        {isSearchOpen && (
          <div className="search-dialog-backdrop" onClick={handleCloseSearch}>
            <div className="search-dialog-overlay" onClick={(e) => e.stopPropagation()} ref={searchOverlayRef}>
              <form className="search-input-container" onSubmit={handleSearchSubmit}>
                <button type="submit" className="search-submit-btn" aria-label="Submit Search">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                  </svg>
                </button>
                <input
                  ref={searchInputRef}
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search categories, products (e.g. Kurti, Sarees)..."
                  aria-label="Search"
                />
                {searchQuery.length > 0 && (
                  <button type="button" className="search-clear-btn" onClick={handleClearSearch} aria-label="Clear input">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <line x1="18" y1="6" x2="6" y2="18"></line>
                      <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                  </button>
                )}
                <button type="button" className="search-close-btn" onClick={handleCloseSearch} aria-label="Close search">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </form>

              {/* Suggestions Dropdown Content */}
              <div className="search-dropdown-content">
                {/* Default State: When user hasn't typed anything */}
                {!searchQuery.trim() && (
                  <div className="trending-searches">
                    <div className="search-section-header">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                      </svg>
                      <h4>Explore Categories</h4>
                    </div>
                    <div className="trending-tags">
                      {categories.map((cat) => (
                        <button
                          key={cat.id}
                          type="button"
                          className="trending-tag"
                          onClick={() => handleCategoryClick(cat.id)}
                        >
                          {cat.name}
                        </button>
                      ))}
                    </div>
                  </div>
                )}

                {/* User is typing */}
                {searchQuery.trim() && (
                  <>
                    {/* Searching Loader */}
                    {isSearching && (
                      <div className="search-loading-state">
                        <div className="search-spinner"></div>
                        <span>Searching products & categories...</span>
                      </div>
                    )}

                    {/* No Results Found State */}
                    {!isSearching && hasSearched && matchingCategories.length === 0 && matchingProducts.length === 0 && (
                      <div className="search-no-results">
                        <div className="no-results-icon">
                          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            <line x1="8" y1="11" x2="14" y2="11"></line>
                          </svg>
                        </div>
                        <h5>No results found for "{searchQuery}"</h5>
                        <p>We couldn't find any products or categories matching your search.</p>
                        {categories.length > 0 && (
                          <div className="search-no-results-suggestions">
                            <span>Browse available categories:</span>
                            <div className="trending-tags">
                              {categories.map((cat) => (
                                <button
                                  key={cat.id}
                                  type="button"
                                  className="trending-tag"
                                  onClick={() => handleCategoryClick(cat.id)}
                                >
                                  {cat.name}
                                </button>
                              ))}
                            </div>
                          </div>
                        )}
                      </div>
                    )}

                    {/* Search Matches: Categories & Products Separately */}
                    {!isSearching && (matchingCategories.length > 0 || matchingProducts.length > 0) && (
                      <div className="search-results-grid">
                        {/* Matching Categories */}
                        {matchingCategories.length > 0 && (
                          <div className="search-category-section">
                            <div className="search-section-header">
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                              </svg>
                              <h4>Categories ({matchingCategories.length})</h4>
                            </div>
                            <div className="search-category-list">
                              {matchingCategories.map((cat) => (
                                <div
                                  key={cat.id}
                                  className="search-category-card"
                                  onClick={() => handleCategoryClick(cat.id)}
                                >
                                  <div className="category-card-text">
                                    <span className="category-card-name">{cat.name}</span>
                                    {cat.description && (
                                      <span className="category-card-desc">{cat.description}</span>
                                    )}
                                  </div>
                                  <span className="category-card-arrow">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                      <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                  </span>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* Matching Products */}
                        {matchingProducts.length > 0 && (
                          <div className="search-product-section">
                            <div className="search-section-header">
                              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                              </svg>
                              <h4>Products ({matchingProducts.length})</h4>
                            </div>
                            <div className="search-product-list">
                              {matchingProducts.map((variant) => {
                                const imgUrl = getProductImageUrl(variant);
                                const sellPrice = parseFloat(variant.pricing?.selling_price || 0);
                                const origPrice = parseFloat(variant.pricing?.original_price || 0);
                                const hasDiscount = origPrice > sellPrice;
                                const discountPercent = hasDiscount
                                  ? Math.round(((origPrice - sellPrice) / origPrice) * 100)
                                  : 0;

                                return (
                                  <div
                                    key={variant.id}
                                    className="search-product-card"
                                    onClick={() => handleProductClick(variant.id)}
                                  >
                                    <div className="search-product-img-wrap">
                                      <img src={imgUrl} alt={variant.product?.name || 'Product'} />
                                    </div>
                                    <div className="search-product-info">
                                      {variant.category?.name && (
                                        <span className="search-product-category">{variant.category.name}</span>
                                      )}
                                      <h5 className="search-product-name">
                                        {variant.product?.name}
                                        {variant.variant_name ? ` - ${variant.variant_name}` : ''}
                                      </h5>
                                      <div className="search-product-price-row">
                                        <span className="search-selling-price">₹{sellPrice.toLocaleString('en-IN')}</span>
                                        {hasDiscount && (
                                          <>
                                            <span className="search-original-price">₹{origPrice.toLocaleString('en-IN')}</span>
                                            <span className="search-discount-badge">{discountPercent}% OFF</span>
                                          </>
                                        )}
                                      </div>
                                    </div>
                                    <div className="search-product-arrow">
                                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                      </svg>
                                    </div>
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        )}
                      </div>
                    )}

                    {/* View all in Shop button */}
                    {!isSearching && (matchingCategories.length > 0 || matchingProducts.length > 0) && (
                      <div className="search-view-all-wrapper">
                        <button
                          type="button"
                          className="btn-view-all-search"
                          onClick={handleSearchSubmit}
                        >
                          <span>View all search results in Shop</span>
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                          </svg>
                        </button>
                      </div>
                    )}
                  </>
                )}
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
