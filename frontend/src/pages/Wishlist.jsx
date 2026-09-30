import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

const Wishlist = () => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { wishlistItems, wishlistCount, removeFromWishlist, isLoading } = useWishlist();
  const { addToCart } = useCart();
  const [actionLoadingId, setActionLoadingId] = useState(null);

  const handleRemoveFromWishlist = async (item, e) => {
    if (e) e.stopPropagation();
    setActionLoadingId(item.id);
    await removeFromWishlist(item.id);
    setActionLoadingId(null);
  };

  const handleAddToCart = async (item, e) => {
    if (e) e.stopPropagation();
    const sellPrice = parseFloat(item.variant?.pricing?.selling_price || item.variant?.selling_price || 0);
    const primaryImg = item.variant?.primary_image?.image || (item.variant?.images && item.variant.images[0]?.image);
    
    const cartItem = {
      id: item.product.id,
      name: item.product.name,
      price: sellPrice,
      image: primaryImg ? (primaryImg.startsWith('http') ? primaryImg : ASSET_BASE_URL + primaryImg) : '',
      variantId: item.variant.id
    };

    await addToCart(cartItem, 1);
  };

  const handleBuyNow = (item, e) => {
    if (e) e.stopPropagation();
    navigate(`/product/${item.variant.id}`);
  };

  const handleNavigateToCollections = () => {
    navigate('/collections');
  };

  return (
    <div className="wishlist-page-container">

      {/* Page Header Bar */}
      <div className="wishlist-header-banner">
        <div className="container">
          <div className="wishlist-breadcrumb">
            <Link to="/">Home</Link> &nbsp;/&nbsp; <span>Wishlist</span>
          </div>
          <div className="wishlist-title-row">
            <h1 className="wishlist-main-heading">
              My Wishlist {wishlistCount > 0 && <span style={{ fontSize: '0.85em', opacity: 0.85 }}>({wishlistCount})</span>}
            </h1>
          </div>
        </div>
      </div>

      <div className="container wishlist-body-content">
        {isLoading ? (
          <div className="wishlist-products-section">
            <div className="wishlist-grid">
              {[1, 2, 3, 4].map((n) => (
                <div key={n} className="wishlist-product-card skeleton-loading" style={{ minHeight: '380px' }}>
                  <div style={{ height: '240px', background: '#f5eff6', borderRadius: '8px 8px 0 0' }}></div>
                  <div style={{ padding: '16px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                    <div style={{ height: '14px', width: '40%', background: '#eee', borderRadius: '4px' }}></div>
                    <div style={{ height: '18px', width: '80%', background: '#eee', borderRadius: '4px' }}></div>
                    <div style={{ height: '18px', width: '50%', background: '#eee', borderRadius: '4px' }}></div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : wishlistItems.length === 0 ? (
          <div className="empty-wishlist-box">
            <div className="empty-wishlist-icon-wrapper">
              <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </div>
            <h2 className="empty-wishlist-title">Your Wishlist is Empty</h2>
            <p className="empty-wishlist-subtext">
              Explore our handcrafted boutique collections and save your favorite outfits here.
            </p>
            <button
              type="button"
              className="btn-add-now-wishlist"
              onClick={handleNavigateToCollections}
            >
              Explore Collections
            </button>
          </div>
        ) : (
          <div className="wishlist-products-section">
            <div className="wishlist-grid">
              {wishlistItems.map((item) => {
                const pricing = item.variant?.pricing || {};
                const sellPrice = parseFloat(pricing.selling_price || item.variant?.selling_price || 0);
                const origPrice = parseFloat(pricing.original_price || item.variant?.original_price || 0);
                const discountPercentage = parseFloat(pricing.effective_discount_percentage || 0);
                const hasDiscount = origPrice > sellPrice || discountPercentage > 0;

                const primaryImg = item.variant?.primary_image?.image || (item.variant?.images && item.variant.images[0]?.image);
                const imgUrl = primaryImg ? (primaryImg.startsWith('http') ? primaryImg : ASSET_BASE_URL + primaryImg) : '';

                const stockStatus = item.variant?.stock?.stock_status || 'in_stock';
                const isOutOfStock = stockStatus === 'out_of_stock' || item.variant?.is_available === 0;
                const isLowStock = stockStatus === 'low_stock';

                return (
                  <div key={item.id} className="wishlist-product-card">
                    {/* Card Image Container */}
                    <div
                      className="wishlist-img-wrapper"
                      onClick={() => navigate(`/product/${item.variant.id}`)}
                    >
                      {imgUrl ? (
                        <img src={imgUrl} alt={item.product?.name || 'Product'} className="wishlist-product-img" />
                      ) : (
                        <div className="product-img-placeholder" style={{ height: '100%', minHeight: '260px', backgroundColor: '#f9f5fa', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#888' }}>
                          Vivisha Boutique
                        </div>
                      )}

                      {/* Stock Badge Overlay */}
                      {isOutOfStock ? (
                        <span className="product-status-tag out-of-stock" style={{ position: 'absolute', top: '10px', left: '10px', background: '#dc2626', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold' }}>
                          Out of Stock
                        </span>
                      ) : isLowStock ? (
                        <span className="product-status-tag low-stock" style={{ position: 'absolute', top: '10px', left: '10px', background: '#ea580c', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold' }}>
                          Low Stock
                        </span>
                      ) : null}

                      {/* Discount Tag Overlay */}
                      {hasDiscount && !isOutOfStock && (
                        <span className="discount-tag" style={{ position: 'absolute', top: '10px', left: '10px', background: 'var(--primary-color, #A049A3)', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold' }}>
                          {discountPercentage > 0 ? `${Math.round(discountPercentage)}% OFF` : `${Math.round(((origPrice - sellPrice) / origPrice) * 100)}% OFF`}
                        </span>
                      )}

                      {/* Remove Button */}
                      <button
                        type="button"
                        className="wishlist-remove-btn"
                        aria-label="Remove item"
                        title="Remove from wishlist"
                        disabled={actionLoadingId === item.id}
                        onClick={(e) => handleRemoveFromWishlist(item, e)}
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                          <line x1="10" y1="11" x2="10" y2="17"></line>
                          <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                      </button>
                    </div>

                    {/* Card Details */}
                    <div className="wishlist-product-info">
                      {item.category?.name && (
                        <span className="wishlist-category-tag">{item.category.name}</span>
                      )}
                      
                      <h3
                        className="wishlist-product-title"
                        onClick={() => navigate(`/product/${item.variant.id}`)}
                      >
                        {item.product?.name} {item.variant?.color?.name ? `(${item.variant.color.name})` : ''}
                      </h3>
                      
                      {item.variant?.variant_name && item.variant.variant_name.trim() !== '' && (
                        <div style={{ fontSize: '12px', color: '#666', marginTop: '3px' }}>
                          Variant: <strong style={{ color: '#333' }}>{item.variant.variant_name.trim()}</strong>
                        </div>
                      )}

                      {item.variant?.size?.name && (
                        <div style={{ fontSize: '12px', color: '#666', marginTop: '3px' }}>
                          Size: <strong>{item.variant.size.name}</strong>
                        </div>
                      )}

                      <div className="wishlist-product-pricing">
                        <span className="wishlist-price">
                          Rs. {sellPrice.toLocaleString('en-IN')}.00
                        </span>
                        {hasDiscount && (
                          <span className="wishlist-original-price">
                            Rs. {origPrice.toLocaleString('en-IN')}.00
                          </span>
                        )}
                      </div>

                      {/* Card Action Buttons */}
                      <div className="wishlist-card-actions">
                        <button
                          type="button"
                          className="btn-wishlist-add-cart"
                          onClick={(e) => handleAddToCart(item, e)}
                          disabled={isOutOfStock}
                        >
                          {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                        </button>
                        <button
                          type="button"
                          className="btn-wishlist-buy-now"
                          onClick={(e) => handleBuyNow(item, e)}
                          disabled={isOutOfStock}
                        >
                          View Details
                        </button>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Wishlist;

