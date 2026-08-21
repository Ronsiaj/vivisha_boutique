import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import banner1 from '../../assets/images/banner1.png';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = 'http://localhost/vivisha_boutique/backend/';

const ProductDetails = () => {
  const { id } = useParams(); // This is the variant_id!
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isInWishlist } = useWishlist();

  const [variant, setVariant] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedImgIndex, setSelectedImgIndex] = useState(0);
  const [quantity, setQuantity] = useState(1);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);
  const [lightboxImg, setLightboxImg] = useState(null);
  const [activeTab, setActiveTab] = useState('description');

  useEffect(() => {
    const fetchVariantDetails = async () => {
      setIsLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/varient/view.php?id=${id}`);
        const data = await response.json();
        if (data.status && data.data && data.data.variant) {
          setVariant(data.data.variant);
          setSelectedImgIndex(0);
        } else {
          setVariant(null);
        }
      } catch (err) {
        console.error("Error fetching variant details:", err);
        setVariant(null);
      } finally {
        setIsLoading(false);
      }
    };
    
    fetchVariantDetails();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [id]);

  if (isLoading) {
    return (
      <div className="product-details-page" style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <h2>Loading Product...</h2>
      </div>
    );
  }

  if (!variant) {
    return (
      <div className="product-not-found-container">
        <div className="container text-center py-5">
          <h2>Product Not Found</h2>
          <p>Sorry, the product you are looking for does not exist or has been removed.</p>
          <button className="btn-primary-purple" onClick={() => navigate('/collections')}>
            Back to Collections
          </button>
        </div>
      </div>
    );
  }

  const isWishlisted = isInWishlist(variant.id);
  
  const sellPrice = parseFloat(variant.pricing.selling_price);
  const origPrice = parseFloat(variant.pricing.original_price);
  const hasDiscount = origPrice > sellPrice;
  const discountPercent = hasDiscount
    ? Math.round(((origPrice - sellPrice) / origPrice) * 100)
    : 0;

  const handleQuantityChange = (type) => {
    if (type === 'decrease' && quantity > 1) {
      setQuantity(prev => prev - 1);
    } else if (type === 'increase' && quantity < variant.stock.available_quantity) {
      setQuantity(prev => prev + 1);
    }
  };

  const handleAddToCart = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    const cartItem = {
      id: variant.product.id,
      name: variant.product.name,
      price: sellPrice,
      image: variant.primary_image ? ASSET_BASE_URL + variant.primary_image.image : '',
      variantId: variant.id
    };
    addToCart(cartItem, quantity);
  };

  const handleBuyNow = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    handleAddToCart();
    navigate('/cart');
  };

  const handleToggleWishlist = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlist(variant);
  };

  const openLightbox = (imgUrl, idx = null) => {
    if (idx !== null) {
      setSelectedImgIndex(idx);
    }
    setLightboxImg(imgUrl);
    setIsLightboxOpen(true);
  };

  const getImageUrl = (img) => {
    if (!img) return banner1; // Fallback
    return ASSET_BASE_URL + img.image;
  };
  
  const displayImages = variant.images && variant.images.length > 0 ? variant.images : [variant.primary_image].filter(Boolean);

  return (
    <div className="product-details-page">
      <div className="container">
        <nav className="product-breadcrumb">
          <Link to="/">Home</Link>
          <span className="separator">/</span>
          <Link to="/collections">Collections</Link>
          <span className="separator">/</span>
          <span className="current">{variant.product.name}</span>
        </nav>
      </div>

      <div className="container product-details-layout">
        <div className="product-gallery-container">
          <div
            className="main-image-wrapper"
            onClick={() => openLightbox(displayImages[selectedImgIndex] ? getImageUrl(displayImages[selectedImgIndex]) : banner1)}
          >
            <img
              src={displayImages[selectedImgIndex] ? getImageUrl(displayImages[selectedImgIndex]) : banner1}
              alt={variant.product.name}
              className="main-product-img"
            />
            {discountPercent > 0 && (
              <span className="detail-discount-tag">{discountPercent}% OFF</span>
            )}
            <div className="zoom-hint">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                <line x1="11" y1="8" x2="11" y2="14"></line>
                <line x1="8" y1="11" x2="14" y2="11"></line>
              </svg>
              <span>Click to enlarge</span>
            </div>
          </div>

          {displayImages.length > 1 && (
            <div className="thumbnails-strip">
              {displayImages.map((img, idx) => (
                <button
                  key={img.id || idx}
                  className={`thumbnail-btn ${selectedImgIndex === idx ? 'active' : ''}`}
                  onClick={() => setSelectedImgIndex(idx)}
                  title="Click to view"
                >
                  <img src={getImageUrl(img)} alt={`${variant.product.name} view ${idx + 1}`} />
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="product-info-container">
          {variant.category && (
            <span className="product-category-badge">{variant.category.name}</span>
          )}
          
          <h1 className="product-main-title">
            {variant.product.name} {variant.color ? ` - ${variant.color.name}` : ''}
          </h1>

          <div className="product-meta-row">
            <span className="product-sku">SKU: {variant.sku}</span>
            <span className={`stock-status ${variant.stock.stock_status === 'out_of_stock' ? 'out-of-stock' : 'in-stock'}`}>
              <span className="status-dot"></span> 
              {variant.stock.stock_status === 'out_of_stock' ? 'Out of Stock' : 'In Stock'}
            </span>
          </div>

          <div className="product-price-box">
            <span className="current-price">Rs. {sellPrice.toLocaleString('en-IN')}.00</span>
            {hasDiscount && (
              <span className="strikethrough-price">Rs. {origPrice.toLocaleString('en-IN')}.00</span>
            )}
            {hasDiscount && (
              <span className="savings-badge">Save ₹{(origPrice - sellPrice).toLocaleString('en-IN')}</span>
            )}
          </div>

          <p className="short-description">{variant.product.description}</p>

          {variant.size && (
            <div className="size-selector-section">
              <div className="size-header">
                <span className="size-label">Size:</span>
              </div>
              <div className="size-options-row">
                <button className="size-btn active">
                  {variant.size.name}
                </button>
              </div>
            </div>
          )}

          <div className="quantity-section">
            <span className="quantity-label">Quantity:</span>
            <div className="quantity-stepper">
              <button 
                onClick={() => handleQuantityChange('decrease')} 
                disabled={quantity <= 1 || variant.stock.stock_status === 'out_of_stock'}
              >-</button>
              <span>{quantity}</span>
              <button 
                onClick={() => handleQuantityChange('increase')} 
                disabled={quantity >= variant.stock.available_quantity || variant.stock.stock_status === 'out_of_stock'}
              >+</button>
            </div>
            {variant.stock.stock_status !== 'out_of_stock' && (
               <span style={{marginLeft: '15px', fontSize: '13px', color: '#666'}}>
                 {variant.stock.available_quantity} available
               </span>
            )}
          </div>

          <div className="product-action-buttons">
            <button 
              className="btn-add-to-cart" 
              onClick={handleAddToCart}
              disabled={variant.stock.stock_status === 'out_of_stock'}
            >
              {variant.stock.stock_status === 'out_of_stock' ? 'Out of Stock' : 'Add to Cart'}
            </button>
            <button 
              className="btn-buy-now-detail" 
              onClick={handleBuyNow}
              disabled={variant.stock.stock_status === 'out_of_stock'}
            >
              Buy Now
            </button>
            <button
              className={`wishlist-toggle-btn ${isWishlisted ? 'active' : ''}`}
              onClick={handleToggleWishlist}
              aria-label="Wishlist"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </button>
          </div>

          <div className="product-tabs-container">
            <div className="tabs-header">
              <button
                className={`tab-btn ${activeTab === 'description' ? 'active' : ''}`}
                onClick={() => setActiveTab('description')}
              >
                Product Details
              </button>
            </div>
            <div className="tab-content">
              {activeTab === 'description' && (
                <div className="tab-pane">
                  <p>{variant.product.description}</p>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {isLightboxOpen && (
        <div className="lightbox-modal-overlay" onClick={() => setIsLightboxOpen(false)}>
          <div className="lightbox-modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="lightbox-close-btn" onClick={() => setIsLightboxOpen(false)} aria-label="Close Lightbox">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
            <img
              src={lightboxImg || getImageUrl(displayImages[selectedImgIndex])}
              alt={variant.product.name}
              className="lightbox-img"
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetails;
