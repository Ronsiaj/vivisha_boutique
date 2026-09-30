import React, { useState, useRef, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Sidebar from './Sidebar';
import AnnouncementBar from './AnnouncementBar';
import logo from '../../assets/images/boutique_logo.png';
import banner1 from '../../assets/images/banner1.png';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import { groupVariantsByProduct, formatImageUrl } from '../utils/productGrouping.js';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

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

  // Group matching products and prioritize the exact variant that matched the SKU/query
  const processedMatchingProducts = React.useMemo(() => {
    if (!matchingProducts || matchingProducts.length === 0) return [];

    const queryLower = searchQuery.trim().toLowerCase();
    const grouped = groupVariantsByProduct(matchingProducts);

    return grouped.map((group) => {
      // Find if a specific variant in this group matched the SKU or name
      const matchingVariant = group.variants.find((v) => {
        const skuMatch = v.sku && v.sku.toLowerCase().includes(queryLower);
        const nameMatch = v.variant_name && v.variant_name.toLowerCase().includes(queryLower);
        return skuMatch || nameMatch;
      });

      const targetVariant = matchingVariant || group.defaultVariant || group.variants[0] || {};
      const targetSku = targetVariant.sku || group.variants.find((v) => v.sku)?.sku || '';

      const sellPrice = parseFloat(targetVariant.pricing?.selling_price || group.minSellingPrice || 0);
      const origPrice = parseFloat(targetVariant.pricing?.original_price || group.minOriginalPrice || 0);
      const hasDiscount = origPrice > sellPrice;
      const discountPercent = hasDiscount && origPrice > 0 ? Math.round(((origPrice - sellPrice) / origPrice) * 100) : 0;

      return {
        ...group,
        targetVariant,
        targetSku,
        sellPrice,
        origPrice,
        hasDiscount,
        discountPercent
      };
    });
  }, [matchingProducts, searchQuery]);

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

  // Fetch active categories on mount for navigation and suggestions
  useEffect(() => {
    fetchActiveCategories();
  }, []);

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

    // Debounce product variants API call (supports SKU, product name, category, etc.)
    const timer = setTimeout(async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/varient/list.php?q=${encodeURIComponent(trimmed)}&limit=12`);
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
      <AnnouncementBar />
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
                  <Link to="/track-order" className="account-dropdown-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <rect x="1" y="3" width="15" height="13"></rect>
                      <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                      <circle cx="5.5" cy="18.5" r="2.5"></circle>
                      <circle cx="18.5" cy="18.5" r="2.5"></circle>
                    </svg>
                    <span>Track Order</span>
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
                <button type="button" className="search-close-btn" onClick={handleCloseSearch} aria-label="Close search">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                  </svg>
                </button>
              </form>

              {/* Suggestions Dropdown Content (Single Vertical Panel, Only when typing) */}
              {searchQuery.trim().length > 0 && (
                <div className="search-dropdown-content">
                  {/* Searching Loader */}
                  {isSearching && (
                    <div className="search-loading-state">
                      <div className="search-spinner"></div>
                      <span>Searching...</span>
                    </div>
                  )}

                  {/* No Results Found State */}
                  {!isSearching && hasSearched && matchingCategories.length === 0 && processedMatchingProducts.length === 0 && (
                    <div className="search-no-results">
                      <p className="no-results-title">No results found for "{searchQuery}"</p>
                      <span className="no-results-hint">Try checking your spelling or searching by another keyword or SKU.</span>
                    </div>
                  )}

                  {/* Search Matches: Single Vertical Autocomplete Layout */}
                  {!isSearching && (matchingCategories.length > 0 || processedMatchingProducts.length > 0) && (
                    <div className="search-vertical-results">
                      {/* 1. Matching Categories */}
                      {matchingCategories.length > 0 && (
                        <div className="search-group-section">
                          <span className="search-group-heading">Categories</span>
                          <div className="search-category-list">
                            {matchingCategories.slice(0, 3).map((cat) => (
                              <div
                                key={cat.id}
                                className="search-category-row"
                                onClick={() => handleCategoryClick(cat.id)}
                              >
                                <div className="search-category-info">
                                  <span className="search-category-title">{cat.name}</span>
                                  {cat.description && (
                                    <span className="search-category-subtitle">{cat.description}</span>
                                  )}
                                </div>
                                <svg className="search-row-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                  <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* 2. Matching Products */}
                      {processedMatchingProducts.length > 0 && (
                        <div className="search-group-section">
                          <span className="search-group-heading">Products</span>
                          <div className="search-product-list">
                            {processedMatchingProducts.slice(0, 6).map((product) => {
                              const imgUrl = formatImageUrl(
                                product.targetVariant?.primary_image || product.primaryImageUrl,
                                banner1
                              );

                              return (
                                <div
                                  key={product.id || product.targetVariant?.id}
                                  className="search-product-row"
                                  onClick={() => handleProductClick(product.targetVariant?.id || product.id)}
                                >
                                  <div className="search-product-image">
                                    <img
                                      src={imgUrl}
                                      alt={product.name || 'Product'}
                                      onError={(e) => {
                                        e.target.onerror = null;
                                        e.target.src = banner1;
                                      }}
                                    />
                                  </div>
                                  <div className="search-product-details">
                                    <span className="search-product-title">{product.name}</span>
                                    {product.targetSku && (
                                      <span className="search-product-sku">SKU: {product.targetSku}</span>
                                    )}
                                    <div className="search-product-price-wrapper">
                                      <span className="search-price-current">
                                        ₹{product.sellPrice.toLocaleString('en-IN')}
                                      </span>
                                      {product.hasDiscount && (
                                        <>
                                          <span className="search-price-original">₹{product.origPrice.toLocaleString('en-IN')}</span>
                                          <span className="search-price-discount">{product.discountPercent}% OFF</span>
                                        </>
                                      )}
                                    </div>
                                  </div>
                                  <svg className="search-row-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                  </svg>
                                </div>
                              );
                            })}
                          </div>
                        </div>
                      )}

                      {/* View all in Shop option */}
                      <div className="search-dropdown-footer">
                        <button
                          type="button"
                          className="search-view-all-link"
                          onClick={handleSearchSubmit}
                        >
                          View all results for "{searchQuery}" &rarr;
                        </button>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </div>
        )}
      </header>

      <Sidebar
        isOpen={isSidebarOpen}
        onClose={() => setIsSidebarOpen(false)}
        initialCategories={categories}
      />
    </>
  );
};

export default Header;
