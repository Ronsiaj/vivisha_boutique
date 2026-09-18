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
  const [isShareOpen, setIsShareOpen] = useState(false);
  const [isCopied, setIsCopied] = useState(false);

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

  useEffect(() => {
    if (isLightboxOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isLightboxOpen]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsLightboxOpen(false);
        setIsShareOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

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

  const maxAllowedQty = Math.min(variant.stock?.available_quantity ?? 0, 100);

  const handleQuantityChange = (type) => {
    if (type === 'decrease' && quantity > 1) {
      setQuantity(prev => prev - 1);
    } else if (type === 'increase') {
      if (quantity < maxAllowedQty) {
        setQuantity(prev => prev + 1);
      } else {
        if (maxAllowedQty >= 100 && (variant.stock?.available_quantity ?? 0) >= 100) {
          alert('Quantity cannot exceed 100 per cart item.');
        } else {
          alert(`Only ${maxAllowedQty} items available in stock.`);
        }
      }
    }
  };

  const handleAddToCart = async () => {
    if (!variant || !variant.id) {
      alert('Please select a valid product variant.');
      return;
    }

    if ((variant.stock?.available_quantity ?? 0) <= 0 || variant.stock?.stock_status === 'out_of_stock') {
      alert('This item is currently out of stock.');
      return;
    }

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
    await addToCart(cartItem, quantity);
  };

  const handleBuyNow = async () => {
    if (!variant || !variant.id) {
      alert('Please select a valid product variant.');
      return;
    }

    if ((variant.stock?.available_quantity ?? 0) <= 0 || variant.stock?.stock_status === 'out_of_stock') {
      alert('This item is currently out of stock.');
      return;
    }

    const cartItem = {
      id: variant.product.id,
      name: variant.product.name,
      price: sellPrice,
      image: variant.primary_image ? ASSET_BASE_URL + variant.primary_image.image : '',
      variantId: variant.id
    };

    if (!isAuthenticated) {
      const buyNowData = {
        buyNow: true,
        variantId: variant.id,
        quantity: quantity,
        product: cartItem
      };
      sessionStorage.setItem('vivisha_buynow_pending', JSON.stringify(buyNowData));
      navigate('/login', {
        state: {
          from: location.pathname,
          buyNow: true,
          buyNowItem: buyNowData
        }
      });
      return;
    }

    const success = await addToCart(cartItem, quantity);
    if (success) {
      navigate('/cart');
    }
  };

  const handleToggleWishlist = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlist(variant);
  };

  const handleShareWhatsApp = () => {
    const currentUrl = window.location.href;
    const productName = variant?.product?.name || 'Handcrafted Ethnic Wear';
    const priceStr = sellPrice ? `Rs. ${sellPrice.toLocaleString('en-IN')}.00` : '';
    const message = `Check out "${productName}" ${priceStr ? `(Price: ${priceStr})` : ''} at Vivisha Boutique:\n${currentUrl}`;
    const whatsappUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(message)}`;
    window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
  };

  const handleCopyLink = () => {
    const currentUrl = window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(currentUrl).then(() => {
        setIsCopied(true);
        setTimeout(() => setIsCopied(false), 2500);
      }).catch(() => {
        fallbackCopyTextToClipboard(currentUrl);
      });
    } else {
      fallbackCopyTextToClipboard(currentUrl);
    }
  };

  const fallbackCopyTextToClipboard = (text) => {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand('copy');
      setIsCopied(true);
      setTimeout(() => setIsCopied(false), 2500);
    } catch (err) {
      console.error('Fallback copy failed', err);
    }
    document.body.removeChild(textArea);
  };

  const getImageUrl = (img) => {
    if (!img) return banner1;
    let path = '';
    if (typeof img === 'string') {
      path = img;
    } else if (typeof img === 'object' && img !== null) {
      path = img.image || img.url || img.path || img.src || '';
    }
    
    if (!path) return banner1;
    
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:') || path.startsWith('/src') || path.startsWith('static/')) {
      return path;
    }
    
    const cleanPath = path.startsWith('/') ? path.slice(1) : path;
    return ASSET_BASE_URL + cleanPath;
  };

  const openLightbox = (imgItem, idx = null) => {
    if (idx !== null) {
      setSelectedImgIndex(idx);
    }
    const targetItem = imgItem || displayImages[selectedImgIndex] || banner1;
    const resolvedUrl = getImageUrl(targetItem);
    setLightboxImg(resolvedUrl);
    setIsLightboxOpen(true);
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
            onClick={() => openLightbox(displayImages[selectedImgIndex] || banner1)}
            title="Click to enlarge image"
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
                disabled={quantity >= maxAllowedQty || variant.stock.stock_status === 'out_of_stock'}
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
              title="Add to Wishlist"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </button>
            <button
              className="share-toggle-btn"
              onClick={() => setIsShareOpen(true)}
              aria-label="Share Product"
              title="Share Product"
            >
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#333333" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
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

      {/* Full Screen Image Preview Lightbox */}
      {isLightboxOpen && (
        <div className="lightbox-modal-overlay" onClick={() => setIsLightboxOpen(false)}>
          <div className="lightbox-modal-content" onClick={(e) => e.stopPropagation()}>
            <img
              src={lightboxImg || getImageUrl(displayImages[selectedImgIndex])}
              alt={variant?.product?.name || 'Full Product View'}
              className="lightbox-img"
            />
            <button 
              className="lightbox-bottom-close-btn" 
              onClick={() => setIsLightboxOpen(false)} 
              aria-label="Close Preview"
              title="Close (Esc)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
              <span>Close</span>
            </button>
          </div>
        </div>
      )}

      {/* Share Modal Overlay */}
      {isShareOpen && (
        <div className="share-modal-overlay" onClick={() => setIsShareOpen(false)}>
          <div className="share-modal-card" onClick={(e) => e.stopPropagation()}>
            <div className="share-modal-header">
              <h3 className="share-modal-title">Share Product</h3>
              <button className="share-modal-close-btn" onClick={() => setIsShareOpen(false)} aria-label="Close Share Modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>

            <div className="share-product-summary">
              <div className="share-product-name">{variant.product.name}</div>
              <div className="share-product-price">Rs. {sellPrice.toLocaleString('en-IN')}.00</div>
            </div>

            <div className="share-actions-list">
              <button className="share-action-btn share-btn-whatsapp" onClick={handleShareWhatsApp}>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.964 9.964 0 001.333 4.993L2 22l5.233-1.237a9.96 9.96 0 004.779 1.221h.004c5.505 0 9.988-4.478 9.99-9.984A9.997 9.997 0 0012.012 2zm5.827 14.19c-.244.688-1.237 1.31-1.71 1.352-.472.042-1.077.202-3.642-.857-3.033-1.251-4.965-4.348-5.116-4.549-.151-.202-1.226-1.632-1.226-3.111 0-1.48.772-2.207 1.047-2.508.275-.302.602-.378.802-.378.201 0 .402.003.577.012.187.01.439-.071.687.524.256.611.874 2.13.949 2.281.075.151.125.327.025.528-.1.201-.151.327-.302.503-.151.176-.317.393-.453.528-.151.151-.31.315-.133.617.176.302.784 1.294 1.684 2.096 1.157 1.031 2.133 1.351 2.435 1.502.302.151.478.126.654-.075.176-.201.754-.88.955-1.181.201-.302.402-.251.678-.151.276.101 1.758.829 2.06 0.98.302.151.503.226.578.352.075.126.075.729-.169 1.417z" />
                </svg>
                Share on WhatsApp
              </button>

              <button className="share-action-btn share-btn-copy" onClick={handleCopyLink}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                  <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                </svg>
                {isCopied ? 'Link Copied!' : 'Copy Link'}
              </button>
            </div>

            {isCopied && (
              <div className="share-copied-toast">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                  <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Link copied to clipboard!
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetails;
