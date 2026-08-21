import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';

const ASSET_BASE_URL = 'http://localhost/vivisha_boutique/backend/';

const Wishlist = () => {
  const navigate = useNavigate();
  const { wishlistItems, removeFromWishlist, isLoading } = useWishlist();
  const { addToCart } = useCart();
  const [notification, setNotification] = useState('');

  const showNotification = (msg) => {
    setNotification(msg);
    setTimeout(() => setNotification(''), 3000);
  };

  const handleRemoveFromWishlist = async (item) => {
    const success = await removeFromWishlist(item.id);
    if (success) {
      showNotification(`Removed "${item.product.name}" from wishlist.`);
    }
  };

  const handleAddToCart = (item) => {
    const sellPrice = parseFloat(item.variant.pricing.selling_price);
    const cartItem = {
      id: item.product.id,
      name: item.product.name,
      price: sellPrice,
      image: item.variant.primary_image ? ASSET_BASE_URL + item.variant.primary_image.image : '',
      variantId: item.variant.id
    };
    addToCart(cartItem, 1);
    showNotification(`Added "${item.product.name}" to cart!`);
  };

  const handleBuyNow = (item) => {
    navigate(`/product/${item.variant.id}`);
  };

  const handleNavigateToCollections = () => {
    navigate('/collections');
  };

  return (
    <div className="wishlist-page-container">
      {/* Toast Notification */}
      {notification && (
        <div className="wishlist-toast-notification">
          <span>{notification}</span>
        </div>
      )}

      {/* Page Header Bar */}
      <div className="wishlist-header-banner">
        <div className="container">
          <div className="wishlist-breadcrumb">
            <Link to="/">Home</Link> &nbsp;/&nbsp; <span>Wishlist</span>
          </div>
          <div className="wishlist-title-row">
            <h1 className="wishlist-main-heading">My Wishlist</h1>
          </div>
        </div>
      </div>

      <div className="container wishlist-body-content">
        {isLoading ? (
          <div className="empty-wishlist-box">
             <p>Loading your wishlist...</p>
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
              className="btn-add-now-wishlist"
              onClick={handleNavigateToCollections}
            >
              Add Now
            </button>
          </div>
        ) : (
          <div className="wishlist-products-section">
            <div className="wishlist-grid">
              {wishlistItems.map((item) => {
                const sellPrice = parseFloat(item.variant.pricing.selling_price);
                const origPrice = parseFloat(item.variant.pricing.original_price);
                const hasDiscount = origPrice > sellPrice;
                const imgUrl = item.variant.primary_image ? ASSET_BASE_URL + item.variant.primary_image.image : '';

                return (
                  <div key={item.id} className="wishlist-product-card">
                    {/* Card Image Container */}
                    <div
                      className="wishlist-img-wrapper"
                      onClick={() => navigate(`/product/${item.variant.id}`)}
                    >
                      {imgUrl ? (
                         <img src={imgUrl} alt={item.product.name} className="wishlist-product-img" />
                      ) : (
                         <div className="product-img-placeholder" style={{height: '100%', backgroundColor: '#f0f0f0'}}></div>
                      )}

                      {/* Remove Button */}
                      <button
                        className="wishlist-remove-btn"
                        aria-label="Remove item"
                        title="Remove from wishlist"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleRemoveFromWishlist(item);
                        }}
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
                      <span className="wishlist-category-tag">{item.category.name}</span>
                      <h3
                        className="wishlist-product-title"
                        onClick={() => navigate(`/product/${item.variant.id}`)}
                      >
                        {item.product.name} {item.variant.color ? ` - ${item.variant.color.name}` : ''}
                      </h3>
                      
                      {item.variant.size && (
                         <div style={{fontSize: '13px', color: '#666', marginTop: '4px'}}>
                            Size: {item.variant.size.name}
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
                          className="btn-wishlist-add-cart"
                          onClick={() => handleAddToCart(item)}
                          disabled={item.variant.stock.stock_status === 'out_of_stock'}
                        >
                          {item.variant.stock.stock_status === 'out_of_stock' ? 'Out of Stock' : 'Add to Cart'}
                        </button>
                        <button
                          className="btn-wishlist-buy-now"
                          onClick={() => handleBuyNow(item)}
                          disabled={item.variant.stock.stock_status === 'out_of_stock'}
                        >
                          Buy Now
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
