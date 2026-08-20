import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useWishlist } from '../context/WishlistContext.jsx';

const Wishlist = () => {
  const navigate = useNavigate();
  const { wishlistItems, removeFromWishlist, clearWishlist } = useWishlist();
  const [notification, setNotification] = useState('');

  const showNotification = (msg) => {
    setNotification(msg);
    setTimeout(() => setNotification(''), 3000);
  };

  const handleRemoveFromWishlist = (id, name) => {
    removeFromWishlist(id);
    showNotification(`Removed "${name}" from wishlist.`);
  };

  const handleClearWishlist = () => {
    clearWishlist();
    showNotification('Wishlist cleared.');
  };

  const handleAddToCart = (product) => {
    showNotification(`Added "${product.name}" to cart!`);
  };

  const handleBuyNow = (product) => {
    navigate(`/product/${product.id}`);
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
        {wishlistItems.length === 0 ? (
          /* ---------------------------------------------------- */
          /* Empty Wishlist State                                  */
          /* ---------------------------------------------------- */
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
          /* ---------------------------------------------------- */
          /* Wishlist Grid with Products                          */
          /* ---------------------------------------------------- */
          <div className="wishlist-products-section">


            <div className="wishlist-grid">
              {wishlistItems.map((product) => {
                const discount = Math.round(
                  ((product.originalPrice - product.price) / product.originalPrice) * 100
                );

                return (
                  <div key={product.id} className="wishlist-product-card">
                    {/* Card Image Container */}
                    <div
                      className="wishlist-img-wrapper"
                      onClick={() => navigate(`/product/${product.id}`)}
                    >
                      <img src={product.image} alt={product.name} className="wishlist-product-img" />



                      {/* Remove Button */}
                      <button
                        className="wishlist-remove-btn"
                        aria-label="Remove item"
                        title="Remove from wishlist"
                        onClick={(e) => {
                          e.stopPropagation();
                          handleRemoveFromWishlist(product.id, product.name);
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
                      <span className="wishlist-category-tag">{product.category}</span>
                      <h3
                        className="wishlist-product-title"
                        onClick={() => navigate(`/product/${product.id}`)}
                      >
                        {product.name}
                      </h3>

                      <div className="wishlist-product-pricing">
                        <span className="wishlist-price">
                          Rs. {product.price.toLocaleString('en-IN')}.00
                        </span>
                        {product.originalPrice > product.price && (
                          <span className="wishlist-original-price">
                            Rs. {product.originalPrice.toLocaleString('en-IN')}.00
                          </span>
                        )}
                      </div>

                      {/* Card Action Buttons */}
                      <div className="wishlist-card-actions">
                        <button
                          className="btn-wishlist-add-cart"
                          onClick={() => handleAddToCart(product)}
                        >
                          Add to Cart
                        </button>
                        <button
                          className="btn-wishlist-buy-now"
                          onClick={() => handleBuyNow(product)}
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
