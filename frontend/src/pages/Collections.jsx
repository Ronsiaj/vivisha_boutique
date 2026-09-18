import React, { useState, useEffect, useRef } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import collectionsBanner from '../../assets/images/collections_banner.png';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = 'http://localhost/vivisha_boutique/backend/';

const sortOptions = [
  { id: 'newest', label: 'Newest' },
  { id: 'price-low', label: 'Price: Low to High' },
  { id: 'price-high', label: 'Price: High to Low' }
];

const availableSizesList = [
  { id: 1, name: 'S' },
  { id: 2, name: 'M' },
  { id: 3, name: 'L' },
  { id: 4, name: 'XL' },
  { id: 5, name: 'XXL' },
  { id: 10, name: 'Free Size' }
];

const Collections = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist: toggleWishlistContext, isInWishlist } = useWishlist();

  // API State
  const [variants, setVariants] = useState([]);
  const [categoriesList, setCategoriesList] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  
  // Pagination State
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalRecords, setTotalRecords] = useState(0);

  // Active Applied Filters State
  const [selectedCategory, setSelectedCategory] = useState(null);
  const [selectedSize, setSelectedSize] = useState(null);
  const [inStockOnly, setInStockOnly] = useState(false);
  const [maxPrice, setMaxPrice] = useState(3000);
  const [sortBy, setSortBy] = useState('newest');
  const [searchKeyword, setSearchKeyword] = useState('');
  
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
  const [draftCategory, setDraftCategory] = useState(null);
  const [draftSize, setDraftSize] = useState(null);
  const [draftInStock, setDraftInStock] = useState(false);
  const [draftMaxPrice, setDraftMaxPrice] = useState(3000);

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

  // Fetch Categories
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const res = await fetch(`${API_BASE_URL}/category/list.php?limit=100`);
        const data = await res.json();
        if (data.status && data.data && data.data.categories) {
          setCategoriesList(data.data.categories);
        }
      } catch (err) {
        console.error('Failed to fetch categories:', err);
      }
    };
    fetchCategories();
  }, []);

  // Parse Category & Search Query from URL Query Parameters
  useEffect(() => {
    const params = new URLSearchParams(location.search);
    const catId = params.get('category_id') || params.get('cat');
    if (catId) {
      const parsedId = parseInt(catId, 10);
      if (!isNaN(parsedId)) {
        setSelectedCategory(parsedId);
      }
    } else {
      setSelectedCategory(null);
    }
    const q = params.get('q') || params.get('search');
    if (q) {
      setSearchKeyword(q.trim());
    } else {
      setSearchKeyword('');
    }
    setCurrentPage(1);
  }, [location.search]);

  // Fetch Variants
  useEffect(() => {
    const fetchVariants = async () => {
      setIsLoading(true);
      try {
        const query = new URLSearchParams();
        query.append('page', currentPage);
        query.append('limit', 12);
        query.append('max_price', maxPrice);
        
        if (selectedCategory) query.append('category_id', selectedCategory);
        if (selectedSize) query.append('size_id', selectedSize);
        if (inStockOnly) query.append('stock_status', 'in_stock');
        if (searchKeyword) query.append('q', searchKeyword);
        
        if (sortBy === 'price-low') {
          query.append('sort_by', 'selling_price');
          query.append('sort_order', 'asc');
        } else if (sortBy === 'price-high') {
          query.append('sort_by', 'selling_price');
          query.append('sort_order', 'desc');
        } else {
          query.append('sort_by', 'created_at');
          query.append('sort_order', 'desc');
        }

        const res = await fetch(`${API_BASE_URL}/varient/list.php?${query.toString()}`);
        const data = await res.json();
        if (data.status && data.data) {
          setVariants(data.data.variants || []);
          if (data.data.pagination) {
            setTotalPages(data.data.pagination.total_pages);
            setTotalRecords(data.data.pagination.total_records);
          }
        }
      } catch (err) {
        console.error('Failed to fetch variants:', err);
      } finally {
        setIsLoading(false);
      }
    };
    fetchVariants();
  }, [currentPage, selectedCategory, selectedSize, inStockOnly, maxPrice, sortBy, searchKeyword]);

  const toggleAccordion = (key) => {
    setOpenAccordions(prev => ({
      ...prev,
      [key]: !prev[key]
    }));
  };

  const toggleWishlist = (variant, e) => {
    e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlistContext(variant);
  };

  const handleAddToCart = (variant, e) => {
    e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    const cartItem = {
      id: variant.product.id,
      name: variant.product.name,
      price: parseFloat(variant.pricing.selling_price),
      image: variant.primary_image ? ASSET_BASE_URL + variant.primary_image.image : '',
      variantId: variant.id
    };
    addToCart(cartItem, 1);
  };

  const handleBuyNow = (variant, e) => {
    e.stopPropagation();
    handleAddToCart(variant, e);
    if (isAuthenticated) {
      navigate('/cart');
    }
  };

  const handleCategoryToggle = (categoryId, isDraft = false) => {
    if (isDraft) {
      setDraftCategory(draftCategory === categoryId ? null : categoryId);
    } else {
      setSelectedCategory(selectedCategory === categoryId ? null : categoryId);
      setCurrentPage(1);
    }
  };

  const handleSizeToggle = (sizeId, isDraft = false) => {
    if (isDraft) {
      setDraftSize(draftSize === sizeId ? null : sizeId);
    } else {
      setSelectedSize(selectedSize === sizeId ? null : sizeId);
      setCurrentPage(1);
    }
  };

  const openMobileDrawer = () => {
    setDraftCategory(selectedCategory);
    setDraftSize(selectedSize);
    setDraftInStock(inStockOnly);
    setDraftMaxPrice(maxPrice);
    setIsMobileDrawerOpen(true);
  };

  const applyMobileDrawer = () => {
    setSelectedCategory(draftCategory);
    setSelectedSize(draftSize);
    setInStockOnly(draftInStock);
    setMaxPrice(draftMaxPrice);
    setCurrentPage(1);
    setIsMobileDrawerOpen(false);
  };

  const clearMobileDrawer = () => {
    setDraftCategory(null);
    setDraftSize(null);
    setDraftInStock(false);
    setDraftMaxPrice(3000);
  };

  const resetAllFilters = () => {
    setSelectedCategory(null);
    setSelectedSize(null);
    setInStockOnly(false);
    setMaxPrice(3000);
    setSearchKeyword('');
    setCurrentPage(1);
    navigate('/collections', { replace: true });
  };

  const handleSelectSort = (optionId) => {
    setSortBy(optionId);
    setCurrentPage(1);
    setIsSortDropdownOpen(false);
  };

  const handlePageChange = (newPage) => {
    if (newPage > 0 && newPage <= totalPages) {
      setCurrentPage(newPage);
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };

  const currentSortLabel = sortOptions.find(o => o.id === sortBy)?.label || 'Newest';

  const activeFiltersCount =
    (selectedCategory !== null ? 1 : 0) +
    (selectedSize !== null ? 1 : 0) +
    (inStockOnly ? 1 : 0) +
    (maxPrice < 3000 ? 1 : 0) +
    (searchKeyword ? 1 : 0);

  const handleProductClick = (variantId) => {
    navigate(`/product/${variantId}`);
  };

  return (
    <div className="collections-page">
      <div className="collections-banner-wrapper">
        <img src={collectionsBanner} alt="Vivisha Boutique Collections" className="collections-banner-img" />
      </div>

      <div className="container collections-main-layout">
        <aside className="collections-sidebar">
          <div className="sidebar-filter-header">
            <h3 className="sidebar-main-title">Filters</h3>
            {activeFiltersCount > 0 && (
              <button className="desktop-reset-btn" onClick={resetAllFilters}>
                Clear All
              </button>
            )}
          </div>

          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('categories')}>
              <span className="accordion-title">Categories</span>
              <span className="accordion-icon">{openAccordions.categories ? '−' : '+'}</span>
            </div>
            {openAccordions.categories && (
              <div className="accordion-content">
                <button
                  className={`cat-filter-btn ${selectedCategory === null ? 'active' : ''}`}
                  onClick={() => { setSelectedCategory(null); setCurrentPage(1); }}
                >
                  All Categories
                </button>
                <ul className="category-checkbox-list">
                  {categoriesList.map((cat) => (
                    <li key={cat.id}>
                      <label className="checkbox-label" style={{ cursor: 'pointer' }}>
                        <input
                          type="radio"
                          name="desktop_category"
                          checked={selectedCategory === cat.id}
                          onChange={() => handleCategoryToggle(cat.id, false)}
                        />
                        <span className={selectedCategory === cat.id ? 'label-text active' : 'label-text'}>
                          {cat.name}
                        </span>
                      </label>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

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
                    onChange={(e) => { setInStockOnly(e.target.checked); setCurrentPage(1); }}
                  />
                  <span>In Stock</span>
                </label>
              </div>
            )}
          </div>

          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('price')}>
              <span className="accordion-title">Max Price</span>
              <span className="accordion-icon">{openAccordions.price ? '−' : '+'}</span>
            </div>
            {openAccordions.price && (
              <div className="accordion-content">
                <div className="price-slider-box">
                  <div className="price-values">
                    <span>₹0</span>
                    <span style={{ padding: '0 8px' }}>-</span>
                    <span>₹{maxPrice.toLocaleString('en-IN')}.00</span>
                  </div>
                  <input
                    type="range"
                    min="0"
                    max="10000"
                    step="500"
                    value={maxPrice}
                    onChange={(e) => { setMaxPrice(Number(e.target.value)); setCurrentPage(1); }}
                    className="price-range-input"
                  />
                </div>
              </div>
            )}
          </div>

          <div className="filter-block accordion-block">
            <div className="accordion-header" onClick={() => toggleAccordion('size')}>
              <span className="accordion-title">Size</span>
              <span className="accordion-icon">{openAccordions.size ? '−' : '+'}</span>
            </div>
            {openAccordions.size && (
              <div className="accordion-content">
                <div className="size-pills-grid">
                  {availableSizesList.map((size) => (
                    <button
                      key={size.id}
                      className={`size-pill-btn ${selectedSize === size.id ? 'active' : ''}`}
                      onClick={() => handleSizeToggle(size.id, false)}
                    >
                      {size.name}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </aside>

        <main className="collections-content">
          <div className="collections-toolbar-bar">
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

            <div className="results-count-text">
              Showing {totalRecords} results in total
            </div>

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

          <div className="products-grid collections-grid">
            {isLoading ? (
              <div className="loading-spinner-container" style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '40px' }}>
                Loading products...
              </div>
            ) : variants.length === 0 ? (
              <div className="no-products-found" style={{ gridColumn: '1 / -1' }}>
                <p>No products found matching your selected filters.</p>
                <button className="btn-reset-empty" onClick={resetAllFilters}>
                  Clear All Filters
                </button>
              </div>
            ) : (
              variants.map((variant) => {
                const sellPrice = parseFloat(variant.pricing.selling_price);
                const origPrice = parseFloat(variant.pricing.original_price);
                const hasDiscount = origPrice > sellPrice;
                const imgUrl = variant.primary_image ? ASSET_BASE_URL + variant.primary_image.image : '';
                const isWishlisted = isInWishlist(variant.id);
                
                return (
                  <div key={variant.id} className="product-card">
                    <div
                      className="product-image-wrapper"
                      onClick={() => handleProductClick(variant.id)}
                    >
                      {imgUrl ? (
                        <img src={imgUrl} alt={variant.product.name} className="product-img" />
                      ) : (
                        <div className="product-img-placeholder" style={{height: '100%', backgroundColor: '#f0f0f0'}}></div>
                      )}

                      <button
                        className={`wishlist-heart-btn ${isWishlisted ? 'active' : ''}`}
                        onClick={(e) => toggleWishlist(variant, e)}
                        aria-label="Add to Wishlist"
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                      </button>

                      {hasDiscount && (
                        <span className="discount-tag">
                          {Math.round(((origPrice - sellPrice) / origPrice) * 100)}% OFF
                        </span>
                      )}
                    </div>

                    <div className="product-info">
                      <h4
                        className="product-title"
                        onClick={() => handleProductClick(variant.id)}
                      >
                        {variant.product.name} {variant.color ? ` - ${variant.color.name}` : ''}
                      </h4>

                      <div className="product-pricing">
                        <span className="price">Rs. {sellPrice.toLocaleString('en-IN')}.00</span>
                        {hasDiscount && (
                          <span className="original-price">Rs. {origPrice.toLocaleString('en-IN')}.00</span>
                        )}
                      </div>

                      <div className="product-card-actions">
                        <button
                          className="btn-add-cart"
                          onClick={(e) => handleAddToCart(variant, e)}
                          disabled={variant.stock.stock_status === 'out_of_stock'}
                        >
                          {variant.stock.stock_status === 'out_of_stock' ? 'Out of Stock' : 'Add to Cart'}
                        </button>
                        <button
                          className="btn-buy-now"
                          onClick={(e) => handleBuyNow(variant, e)}
                          disabled={variant.stock.stock_status === 'out_of_stock'}
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
          
          {totalPages > 1 && (
            <div className="pagination-container" style={{ display: 'flex', justifyContent: 'center', marginTop: '30px', gap: '10px' }}>
              <button 
                disabled={currentPage === 1} 
                onClick={() => handlePageChange(currentPage - 1)}
                style={{ padding: '8px 16px', border: '1px solid #ddd', background: '#fff', borderRadius: '4px', cursor: currentPage === 1 ? 'not-allowed' : 'pointer' }}
              >
                Previous
              </button>
              <span style={{ padding: '8px 16px', background: '#F6EDF6', color: '#A049A3', borderRadius: '4px', fontWeight: 'bold' }}>
                Page {currentPage} of {totalPages}
              </span>
              <button 
                disabled={currentPage === totalPages} 
                onClick={() => handlePageChange(currentPage + 1)}
                style={{ padding: '8px 16px', border: '1px solid #ddd', background: '#fff', borderRadius: '4px', cursor: currentPage === totalPages ? 'not-allowed' : 'pointer' }}
              >
                Next
              </button>
            </div>
          )}
        </main>
      </div>

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
                {categoriesList.map((cat) => (
                  <li key={cat.id}>
                    <label className="drawer-checkbox-label" style={{ cursor: 'pointer' }}>
                      <input
                        type="radio"
                        name="mobile_category"
                        checked={draftCategory === cat.id}
                        onChange={() => handleCategoryToggle(cat.id, true)}
                      />
                      <span>{cat.name}</span>
                    </label>
                  </li>
                ))}
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
                <span>In Stock</span>
              </label>
            </div>

            <div className="drawer-section">
              <h4 className="drawer-section-title">Max Price</h4>
              <div className="drawer-price-box">
                <div className="price-inputs-row">
                  <div className="price-badge">₹0</div>
                  <span className="dash">-</span>
                  <div className="price-badge">₹{draftMaxPrice.toLocaleString('en-IN')}.00</div>
                </div>
                <input
                  type="range"
                  min="0"
                  max="10000"
                  step="500"
                  value={draftMaxPrice}
                  onChange={(e) => setDraftMaxPrice(Number(e.target.value))}
                  className="modal-range-slider"
                />
              </div>
            </div>

            <div className="drawer-section">
              <h4 className="drawer-section-title">Size</h4>
              <div className="size-pills-grid">
                {availableSizesList.map((size) => (
                  <button
                    key={size.id}
                    className={`size-pill-btn ${draftSize === size.id ? 'active' : ''}`}
                    onClick={() => handleSizeToggle(size.id, true)}
                  >
                    {size.name}
                  </button>
                ))}
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
