import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';
import collectionsBanner from '../../assets/images/collections_banner.png';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const initialProducts = [
  {
    id: 1,
    name: 'Peacock Blue Salwar Set (3 Piece Suit) - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner1,
    inStock: true,
    sizes: ['S', 'M', 'L', 'XL']
  },
  {
    id: 2,
    name: 'Avocado Green Salwar Suit - Slub Silk',
    category: 'Salwar Sets',
    price: 1799,
    originalPrice: 2299,
    image: banner2,
    inStock: true,
    sizes: ['M', 'L', 'XL']
  },
  {
    id: 3,
    name: 'Mustard 3 Piece Set - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner3,
    inStock: true,
    sizes: ['S', 'M', 'L']
  },
  {
    id: 4,
    name: 'Royal Magenta Anarkali Suit Set with Dupatta',
    category: 'Anarkali Suits',
    price: 2499,
    originalPrice: 3199,
    image: banner1,
    inStock: true,
    sizes: ['L', 'XL', 'XXL']
  },
  {
    id: 5,
    name: 'Handcrafted Festive Silk Kurti Set',
    category: 'Slub Silk',
    price: 1999,
    originalPrice: 2599,
    image: banner2,
    inStock: true,
    sizes: ['M', 'L', 'XL']
  },
  {
    id: 6,
    name: 'Elegance Emerald Green Salwar Suit',
    category: 'Salwar Sets',
    price: 1899,
    originalPrice: 2399,
    image: banner3,
    inStock: true,
    sizes: ['S', 'M', 'L', 'XXL']
  }
];

const availableSizesList = ['S', 'M', 'L', 'XL', 'XXL'];

const sortOptions = [
  { id: 'featured', label: 'Featured' },
  { id: 'newest', label: 'Newest' },
  { id: 'price-low', label: 'Price: Low to High' },
  { id: 'price-high', label: 'Price: High to Low' }
];

const Collections = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist: toggleWishlistContext, isInWishlist } = useWishlist();

  // State Management
  const [products, setProducts] = useState(initialProducts);

  // Active Applied Filters State
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [selectedSizes, setSelectedSizes] = useState([]);
  const [inStockOnly, setInStockOnly] = useState(false);
  const [maxPrice, setMaxPrice] = useState(3000);
  const [sortBy, setSortBy] = useState('featured');
  const [wishlist, setWishlist] = useState([]);

  // Desktop Accordions Open/Closed State
  const [openAccordions, setOpenAccordions] = useState({
    categories: true,
    availability: true,
    price: true,
    size: true
  });

  // Mobile Side Drawer State
  const [isMobileDrawerOpen, setIsMobileDrawerOpen] = useState(false);

  // Sort Dropdown Button Open State
  const [isSortDropdownOpen, setIsSortDropdownOpen] = useState(false);
  const sortRef = useRef(null);

  // Temporary State for Mobile Drawer Draft Changes
  const [draftCategories, setDraftCategories] = useState([]);
  const [draftSizes, setDraftSizes] = useState([]);
  const [draftInStock, setDraftInStock] = useState(false);
  const [draftMaxPrice, setDraftMaxPrice] = useState(3000);

  // Close sort dropdown on outside click
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (sortRef.current && !sortRef.current.contains(event.target)) {
        setIsSortDropdownOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  // Prevent background scrolling when mobile drawer is open
  useEffect(() => {
    if (isMobileDrawerOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isMobileDrawerOpen]);

  // Derived Dynamic Categories
  const categoriesList = Array.from(new Set(products.map(p => p.category)));

  const toggleAccordion = (key) => {
    setOpenAccordions(prev => ({
      ...prev,
      [key]: !prev[key]
    }));
  };

  const toggleWishlist = (product, e) => {
    e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlistContext(product);
  };

  const handleAddToCart = (product, e) => {
    e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    addToCart(product, 1);
  };

  const handleBuyNow = (product, e) => {
    e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    addToCart(product, 1);
    navigate('/cart');
  };

  const handleCategoryToggle = (categoryName, isDraft = false) => {
    if (isDraft) {
      setDraftCategories(prev =>
        prev.includes(categoryName)
          ? prev.filter(c => c !== categoryName)
          : [...prev, categoryName]
      );
    } else {
      setSelectedCategories(prev =>
        prev.includes(categoryName)
          ? prev.filter(c => c !== categoryName)
          : [...prev, categoryName]
      );
    }
  };

  const handleSizeToggle = (size, isDraft = false) => {
    if (isDraft) {
      setDraftSizes(prev =>
        prev.includes(size) ? prev.filter(s => s !== size) : [...prev, size]
      );
    } else {
      setSelectedSizes(prev =>
        prev.includes(size) ? prev.filter(s => s !== size) : [...prev, size]
      );
    }
  };

  const openMobileDrawer = () => {
    setDraftCategories([...selectedCategories]);
    setDraftSizes([...selectedSizes]);
    setDraftInStock(inStockOnly);
    setDraftMaxPrice(maxPrice);
    setIsMobileDrawerOpen(true);
  };

  const applyMobileDrawer = () => {
    setSelectedCategories(draftCategories);
    setSelectedSizes(draftSizes);
    setInStockOnly(draftInStock);
    setMaxPrice(draftMaxPrice);
    setIsMobileDrawerOpen(false);
  };

  const clearMobileDrawer = () => {
    setDraftCategories([]);
    setDraftSizes([]);
    setDraftInStock(false);
    setDraftMaxPrice(3000);
  };

  const resetAllFilters = () => {
    setSelectedCategories([]);
    setSelectedSizes([]);
    setInStockOnly(false);
    setMaxPrice(3000);
  };

  const handleSelectSort = (optionId) => {
    setSortBy(optionId);
    setIsSortDropdownOpen(false);
  };

  const currentSortLabel = sortOptions.find(o => o.id === sortBy)?.label || 'Featured';

  // Compute Active Filters Count
  const activeFiltersCount =
    selectedCategories.length +
    selectedSizes.length +
    (inStockOnly ? 1 : 0) +
    (maxPrice < 3000 ? 1 : 0);

  // Filter & Sort Logic
  const filteredProducts = products.filter(p => {
    if (selectedCategories.length > 0 && !selectedCategories.includes(p.category)) return false;
    if (inStockOnly && !p.inStock) return false;
    if (p.price > maxPrice) return false;
    if (selectedSizes.length > 0 && !p.sizes.some(s => selectedSizes.includes(s))) return false;
    return true;
  });

  const sortedProducts = [...filteredProducts].sort((a, b) => {
    if (sortBy === 'price-low') return a.price - b.price;
    if (sortBy === 'price-high') return b.price - a.price;
    if (sortBy === 'newest') return b.id - a.id;
    return 0; // Featured default
  });

  const handleProductClick = (productId) => {
    navigate(`/product/${productId}`);
  };

  return (
    <div className="collections-page">
      {/* Collections Banner */}
      <div className="collections-banner-wrapper">
        <img
          src={collectionsBanner}
          alt="Vivisha Boutique Collections Banner"
          className="collections-banner-img"
        />
      </div>

      <div className="container collections-main-layout">
        {/* DESKTOP SIDEBAR FILTERS */}
        <aside className="collections-sidebar">
          <div className="sidebar-filter-header">
            <h3 className="sidebar-main-title">Filters</h3>
            {activeFiltersCount > 0 && (
              <button className="desktop-reset-btn" onClick={resetAllFilters}>
                Clear All
              </button>
            )}
          </div>

          {/* 1. Categories Accordion */}
          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('categories')}>
              <span className="accordion-title">Categories</span>
              <span className="accordion-icon">{openAccordions.categories ? '−' : '+'}</span>
            </div>
            {openAccordions.categories && (
              <div className="accordion-content">
                <button
                  className={`cat-filter-btn ${selectedCategories.length === 0 ? 'active' : ''}`}
                  onClick={() => setSelectedCategories([])}
                >
                  All Products ({products.length})
                </button>
                <ul className="category-checkbox-list">
                  {categoriesList.map((cat) => {
                    const count = products.filter(p => p.category === cat).length;
                    const isChecked = selectedCategories.includes(cat);
                    return (
                      <li key={cat}>
                        <label className="checkbox-label">
                          <input
                            type="checkbox"
                            checked={isChecked}
                            onChange={() => handleCategoryToggle(cat, false)}
                          />
                          <span className={isChecked ? 'label-text active' : 'label-text'}>
                            {cat} ({count})
                          </span>
                        </label>
                      </li>
                    );
                  })}
                </ul>
              </div>
            )}
          </div>

          {/* 2. Availability Accordion */}
          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('availability')}>
              <span className="accordion-title">Availability</span>
              <span className="accordion-icon">{openAccordions.availability ? '−' : '+'}</span>
            </div>
            {openAccordions.availability && (
              <div className="accordion-content">
                <label className="checkbox-label">
                  <input
                    type="checkbox"
                    checked={inStockOnly}
                    onChange={(e) => setInStockOnly(e.target.checked)}
                  />
                  <span>In Stock ({products.filter(p => p.inStock).length})</span>
                </label>
                <label className="checkbox-label disabled">
                  <input type="checkbox" disabled />
                  <span>Out of Stock (0)</span>
                </label>
              </div>
            )}
          </div>

          {/* 3. Price Accordion */}
          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('price')}>
              <span className="accordion-title">Price</span>
              <span className="accordion-icon">{openAccordions.price ? '−' : '+'}</span>
            </div>
            {openAccordions.price && (
              <div className="accordion-content">
                <div className="price-slider-box">
                  <div className="price-values">
                    <span>₹1,000</span>
                    <span>₹{maxPrice.toLocaleString('en-IN')}.00</span>
                  </div>
                  <input
                    type="range"
                    min="1000"
                    max="3000"
                    step="100"
                    value={maxPrice}
                    onChange={(e) => setMaxPrice(Number(e.target.value))}
                    className="price-range-input"
                  />
                </div>
              </div>
            )}
          </div>

          {/* 4. Size Accordion */}
          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('size')}>
              <span className="accordion-title">Size</span>
              <span className="accordion-icon">{openAccordions.size ? '−' : '+'}</span>
            </div>
            {openAccordions.size && (
              <div className="accordion-content">
                <div className="size-pills-grid">
                  {availableSizesList.map((size) => {
                    const isSelected = selectedSizes.includes(size);
                    return (
                      <button
                        key={size}
                        className={`size-pill-btn ${isSelected ? 'active' : ''}`}
                        onClick={() => handleSizeToggle(size, false)}
                      >
                        {size}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}
          </div>
        </aside>

        {/* MAIN PRODUCT GRID & TOOLBAR */}
        <main className="collections-content">
          {/* Toolbar */}
          <div className="collections-toolbar-bar">
            {/* Mobile Filter Button */}
            <button className="mobile-filter-trigger-btn" onClick={openMobileDrawer}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
              </svg>
              <span>Filter {activeFiltersCount > 0 && `(${activeFiltersCount})`}</span>
            </button>

            {/* Results Count Text (Hidden on mobile via CSS) */}
            <div className="results-count-text">
              Showing {sortedProducts.length} results in total
            </div>

            {/* Sort By Dropdown Button & Popover Menu */}
            <div className="sort-button-wrapper" ref={sortRef}>
              <button
                className="sort-trigger-btn"
                onClick={() => setIsSortDropdownOpen(!isSortDropdownOpen)}
              >
                <span>Sort by: <strong>{currentSortLabel}</strong></span>
                <svg className={`sort-arrow ${isSortDropdownOpen ? 'open' : ''}`} width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
              </button>

              {isSortDropdownOpen && (
                <div className="sort-dropdown-popover">
                  {sortOptions.map((opt) => (
                    <button
                      key={opt.id}
                      className={`sort-popover-item ${sortBy === opt.id ? 'active' : ''}`}
                      onClick={() => handleSelectSort(opt.id)}
                    >
                      {opt.label}
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Product Grid */}
          <div className="products-grid collections-grid">
            {sortedProducts.length === 0 ? (
              <div className="no-products-found">
                <p>No products found matching your selected filters.</p>
                <button className="btn-reset-empty" onClick={resetAllFilters}>
                  Clear All Filters
                </button>
              </div>
            ) : (
              sortedProducts.map((product) => {
                const isWishlisted = isInWishlist(product.id);
                return (
                  <div key={product.id} className="product-card">
                    <div
                      className="product-image-wrapper"
                      onClick={() => handleProductClick(product.id)}
                    >
                      <img src={product.image} alt={product.name} className="product-img" />

                      <button
                        className={`wishlist-heart-btn ${isWishlisted ? 'active' : ''}`}
                        onClick={(e) => toggleWishlist(product, e)}
                        aria-label="Add to Wishlist"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                      </button>

                      {product.originalPrice > product.price && (
                        <span className="discount-tag">
                          {Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100)}% OFF
                        </span>
                      )}
                    </div>

                    <div className="product-info">
                      <h4
                        className="product-title"
                        onClick={() => handleProductClick(product.id)}
                      >
                        {product.name}
                      </h4>

                      <div className="product-pricing">
                        <span className="price">Rs. {product.price.toLocaleString('en-IN')}.00</span>
                        {product.originalPrice > product.price && (
                          <span className="original-price">Rs. {product.originalPrice.toLocaleString('en-IN')}.00</span>
                        )}
                      </div>

                      <div className="product-card-actions">
                        <button
                          className="btn-add-cart"
                          onClick={(e) => handleAddToCart(product, e)}
                        >
                          Add to Cart
                        </button>
                        <button
                          className="btn-buy-now"
                          onClick={(e) => handleBuyNow(product, e)}
                        >
                          Buy Now
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        </main>
      </div>

      {/* MOBILE FILTER SIDE DRAWER */}
      <div
        className={`mobile-filter-drawer-overlay ${isMobileDrawerOpen ? 'open' : ''}`}
        onClick={() => setIsMobileDrawerOpen(false)}
      >
        <div
          className={`mobile-filter-drawer ${isMobileDrawerOpen ? 'open' : ''}`}
          onClick={(e) => e.stopPropagation()}
        >
          <div className="drawer-header">
            <h3 className="drawer-title">Filter</h3>
            <button className="drawer-close-btn" onClick={() => setIsMobileDrawerOpen(false)} aria-label="Close Filters">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
          </div>

          <div className="drawer-body">
            <div className="drawer-section">
              <h4 className="drawer-section-title">Products Category</h4>
              <ul className="drawer-checkbox-list">
                {categoriesList.map((cat) => {
                  const count = products.filter(p => p.category === cat).length;
                  const isChecked = draftCategories.includes(cat);
                  return (
                    <li key={cat}>
                      <label className="drawer-checkbox-label">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => handleCategoryToggle(cat, true)}
                        />
                        <span>{cat} ({count})</span>
                      </label>
                    </li>
                  );
                })}
              </ul>
            </div>

            <div className="drawer-section">
              <h4 className="drawer-section-title">Availability</h4>
              <label className="drawer-checkbox-label">
                <input
                  type="checkbox"
                  checked={draftInStock}
                  onChange={(e) => setDraftInStock(e.target.checked)}
                />
                <span>In Stock ({products.filter(p => p.inStock).length})</span>
              </label>
              <label className="drawer-checkbox-label disabled">
                <input type="checkbox" disabled />
                <span>Out of Stock (0)</span>
              </label>
            </div>

            <div className="drawer-section">
              <h4 className="drawer-section-title">Price Range</h4>
              <div className="drawer-price-box">
                <div className="price-inputs-row">
                  <div className="price-badge">₹1,000</div>
                  <span className="dash">-</span>
                  <div className="price-badge">₹{draftMaxPrice.toLocaleString('en-IN')}.00</div>
                </div>
                <input
                  type="range"
                  min="1000"
                  max="3000"
                  step="100"
                  value={draftMaxPrice}
                  onChange={(e) => setDraftMaxPrice(Number(e.target.value))}
                  className="modal-range-slider"
                />
              </div>
            </div>

            <div className="drawer-section">
              <h4 className="drawer-section-title">Size</h4>
              <div className="size-pills-grid">
                {availableSizesList.map((size) => {
                  const isSelected = draftSizes.includes(size);
                  return (
                    <button
                      key={size}
                      className={`size-pill-btn ${isSelected ? 'active' : ''}`}
                      onClick={() => handleSizeToggle(size, true)}
                    >
                      {size}
                    </button>
                  );
                })}
              </div>
            </div>
          </div>

          <div className="drawer-footer">
            <button className="btn-clear-all" onClick={clearMobileDrawer}>
              Clear All
            </button>
            <button className="btn-apply-filters" onClick={applyMobileDrawer}>
              Apply Filters
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Collections;
