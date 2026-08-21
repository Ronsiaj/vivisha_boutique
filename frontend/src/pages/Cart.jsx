import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import CartAbandonmentModal from '../components/CartAbandonmentModal';

const ASSET_BASE_URL = 'http://localhost/vivisha_boutique/backend/';

const Cart = () => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const {
    cartItems,
    cartCount,
    subtotal,
    totalOriginal,
    totalSavings,
    deliveryFee,
    totalAmount,
    removeFromCart,
    appliedCoupon,
    setAppliedCoupon,
    isLoading
  } = useCart();

  const [showAbandonModal, setShowAbandonModal] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [couponMsg, setCouponMsg] = useState('');

  const handleProceedToCheckout = () => {
    if (cartItems.length === 0) return;
    if (!isAuthenticated) {
      navigate('/login', { state: { from: '/checkout' } });
    } else {
      navigate('/checkout');
    }
  };

  const handleApplyCoupon = (e) => {
    e.preventDefault();
    if (!couponCode.trim()) {
      setCouponMsg('Please enter a coupon code.');
      return;
    }
    // Placeholder logic for backend integration
    setCouponMsg('⚡ Coupon system will be validated with backend API upon deployment.');
  };

  const handleAttemptAbandon = () => {
    setShowAbandonModal(true);
  };

  const handleContinueShopping = () => {
    setShowAbandonModal(false);
  };

  const handleConfirmCancel = () => {
    setShowAbandonModal(false);
    navigate('/collections');
  };

  return (
    <div className="cart-page-container">
      {/* Page Header Bar */}
      <div className="wishlist-header-banner">
        <div className="container">
          <div className="wishlist-breadcrumb">
            <Link to="/">Home</Link> &nbsp;/&nbsp; <span>Cart</span>
          </div>
          <div className="wishlist-title-row">
            <h1 className="wishlist-main-heading">My Cart</h1>
          </div>
        </div>
      </div>

      {isLoading ? (
        <div className="empty-cart-container" style={{ padding: '40px 0' }}>
          <p>Loading your cart...</p>
        </div>
      ) : cartItems.length === 0 ? (
        <div className="empty-cart-container">
          <div className="empty-cart-icon-box">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
          </div>
          <h2>Your Cart is Empty</h2>
          <p>Explore our exclusive ethnic collections and add your favorite suits.</p>
          <Link to="/collections" className="btn-primary-cart">
            Start Shopping
          </Link>
        </div>
      ) : (
        <div className="cart-wrapper">
          {/* Left Column: Cart Items & Missed Something Section */}
          <div className="cart-items-section">
            {/* List of Cart Items */}
            <div className="cart-list-header">
              <h3>Cart Items ({cartItems.length} Products)</h3>
            </div>

            {cartItems.map((item) => {
              const imgUrl = item.variant.primary_image ? ASSET_BASE_URL + item.variant.primary_image.image : '';
              return (
                <div key={item.cart_item_id} className="cart-item-card">
                  {imgUrl ? (
                    <img src={imgUrl} alt={item.product.name} className="cart-item-img" onClick={() => navigate(`/product/${item.variant_id}`)} style={{cursor: 'pointer'}} />
                  ) : (
                    <div className="cart-item-img" style={{backgroundColor: '#f0f0f0', cursor: 'pointer'}} onClick={() => navigate(`/product/${item.variant_id}`)}></div>
                  )}
                  <div className="cart-item-info">
                    <span className="cart-item-category">{item.category.name}</span>
                    <h3 style={{cursor: 'pointer'}} onClick={() => navigate(`/product/${item.variant_id}`)}>
                      {item.product.name} {item.variant.color ? ` - ${item.variant.color.name}` : ''}
                    </h3>
                    {item.variant.size && (
                      <div style={{fontSize: '13px', color: '#666', marginTop: '4px', marginBottom: '8px'}}>
                        Size: {item.variant.size.name}
                      </div>
                    )}
                    <div className="price-tag">
                      <span className="current-price">₹{item.unit_price}</span>
                      {parseFloat(item.variant.pricing.original_price) > parseFloat(item.unit_price) && (
                        <span className="original-price">₹{item.variant.pricing.original_price}</span>
                      )}
                      <span className="item-subtotal-tag">
                        Item Total: ₹{item.line_total}
                      </span>
                    </div>
                    <div className="qty-controls" style={{ border: 'none', background: 'transparent', padding: '0', display: 'flex', alignItems: 'center' }}>
                      <span style={{ fontWeight: '500', fontSize: '14px', color: '#555', background: '#f5f5f5', padding: '4px 12px', borderRadius: '4px' }}>
                        Qty: {item.quantity}
                      </span>
                    </div>
                  </div>
                  <button
                    className="btn-remove-item"
                    onClick={() => removeFromCart(item.cart_item_id)}
                    title="Remove item"
                  >
                    ✕
                  </button>
                </div>
              );
            })}

            {/* "Missed Something?" Section */}
            <div className="missed-something-box">
              <div className="missed-something-content">
                <div className="missed-icon">✨</div>
                <div>
                  <h4 className="missed-title">Missed Something?</h4>
                  <p className="missed-text">
                    Explore our latest ethnic wear collections & add more styles to your order!
                  </p>
                </div>
              </div>
              <Link to="/collections" className="btn-add-more-items">
                + Add More Items
              </Link>
            </div>

            {/* Cancel Order Action Row */}
            <div className="cart-actions-row">
              <button
                className="btn-abandon-order"
                onClick={handleAttemptAbandon}
              >
                Cancel Order Process
              </button>
            </div>
          </div>

          {/* Right Column: Coupons & Order Summary */}
          <div className="cart-sidebar-section">
            {/* Dedicated Coupons & Offers Section (Placeholder for Backend) */}
            <div className="coupons-card-box">
              <div className="coupons-card-header">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                  <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                  <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                <h3>Coupons & Offers</h3>
              </div>
              <form onSubmit={handleApplyCoupon} className="coupon-input-form">
                <input
                  type="text"
                  placeholder="Enter Coupon Code"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value.toUpperCase())}
                  className="coupon-input"
                />
                <button type="submit" className="btn-apply-coupon">
                  Apply
                </button>
              </form>
              {couponMsg && (
                <p className="coupon-info-msg">{couponMsg}</p>
              )}

              {/* Available Offer Placeholders */}
              <div className="available-offers-list">
                <div className="offer-tag-item">
                  <span className="offer-code">BOUTIQUE10</span>
                  <span className="offer-desc">10% OFF on Orders above ₹2,999</span>
                </div>
                <div className="offer-tag-item">
                  <span className="offer-code">FIRST500</span>
                  <span className="offer-desc">Flat ₹500 OFF for New Boutique Members</span>
                </div>
              </div>
              <span className="backend-note-badge">
                Backend coupon API ready
              </span>
            </div>

            {/* Dynamic Order Summary Section */}
            <div className="cart-summary-card">
              <h2>Order Summary</h2>
              <div className="summary-pricing">
                <div className="pricing-row">
                  <span>Total Items ({cartCount} {cartCount === 1 ? 'pc' : 'pcs'})</span>
                  <span>₹{totalOriginal.toLocaleString('en-IN')}.00</span>
                </div>

                {totalSavings > 0 && (
                  <div className="pricing-row savings-row">
                    <span>Boutique Discount</span>
                    <span className="savings-amount">-₹{totalSavings.toLocaleString('en-IN')}.00</span>
                  </div>
                )}

                <div className="pricing-row">
                  <span>Standard Delivery</span>
                  <span className="free-shipping">FREE</span>
                </div>

                <div className="pricing-row total-row">
                  <span>Total Amount</span>
                  <span className="total-amount">₹{totalAmount.toLocaleString('en-IN')}.00</span>
                </div>
              </div>

              <button
                className="btn-proceed-checkout"
                onClick={handleProceedToCheckout}
              >
                Proceed to Checkout (₹{totalAmount.toLocaleString('en-IN')}.00)
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Cart Abandonment Modal / Bottom Sheet */}
      <CartAbandonmentModal
        isOpen={showAbandonModal}
        onContinue={handleContinueShopping}
        onCancel={handleConfirmCancel}
      />
    </div>
  );
};

export default Cart;
