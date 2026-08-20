import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import CartAbandonmentModal from '../components/CartAbandonmentModal';

const Checkout = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated } = useAuth();
  const {
    cartItems,
    cartCount,
    subtotal,
    totalSavings,
    totalAmount,
    clearCart
  } = useCart();

  const [showAbandonModal, setShowAbandonModal] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState('upi');
  const [orderConfirmed, setOrderConfirmed] = useState(false);
  const [placedOrderId, setPlacedOrderId] = useState('');

  // Coupon state placeholder
  const [appliedCouponCode, setAppliedCouponCode] = useState('');
  const [showCouponModal, setShowCouponModal] = useState(false);
  const [couponInput, setCouponInput] = useState('');
  const [couponMsg, setCouponMsg] = useState('');

  // Unauthenticated users are redirected to dedicated Login/Register page
  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: '/checkout' } });
    }
  }, [isAuthenticated, navigate]);

  // Saved Addresses State
  const [savedAddresses, setSavedAddresses] = useState([
    {
      id: 1,
      name: user?.name || 'felix',
      phone: user?.phone || '9443219395',
      street: '4/625 1st cross street  sathiyamoorthy nagar ',
      city: 'Sivaganga',
      state: 'Tamil Nadu',
      pincode: '630003'
    },
    {
      id: 2,
      name: 'jacob',
      phone: '9443218374',
      street: '2/432 2nd cross street periyakottai',
      city: 'Sivaganga',
      state: 'Tamil Nadu',
      pincode: '630003'
    }
  ]);

  const [selectedAddressId, setSelectedAddressId] = useState(1);
  const [isAddressDrawerOpen, setIsAddressDrawerOpen] = useState(false);
  const [isAddingNewAddress, setIsAddingNewAddress] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState(null);

  // Address Form State
  const [addressForm, setAddressForm] = useState({
    name: '',
    phone: '',
    street: '',
    city: '',
    state: '',
    pincode: ''
  });

  const selectedAddress = savedAddresses.find((addr) => addr.id === selectedAddressId) || savedAddresses[0];

  const handleOpenAddNew = () => {
    setAddressForm({
      name: user?.name || '',
      phone: user?.phone || '',
      street: '',
      city: '',
      state: '',
      pincode: ''
    });
    setEditingAddressId(null);
    setIsAddingNewAddress(true);
  };

  const handleEditAddress = (addr, e) => {
    e.stopPropagation();
    setAddressForm({
      name: addr.name,
      phone: addr.phone,
      street: addr.street,
      city: addr.city,
      state: addr.state,
      pincode: addr.pincode
    });
    setEditingAddressId(addr.id);
    setIsAddingNewAddress(true);
  };

  const handleSaveAddressSubmit = (e) => {
    e.preventDefault();
    if (!addressForm.name || !addressForm.phone || !addressForm.street || !addressForm.city) {
      alert('Please fill in all required address fields.');
      return;
    }

    if (editingAddressId) {
      setSavedAddresses((prev) =>
        prev.map((a) => (a.id === editingAddressId ? { ...a, ...addressForm } : a))
      );
    } else {
      const newId = Date.now();
      const newAddr = { id: newId, ...addressForm };
      setSavedAddresses((prev) => [newAddr, ...prev]);
      setSelectedAddressId(newId);
    }
    setIsAddingNewAddress(false);
    setEditingAddressId(null);
  };

  const handleAttemptAbandon = () => {
    setShowAbandonModal(true);
  };

  const handleContinueOrder = () => {
    setShowAbandonModal(false);
  };

  const handleConfirmCancelOrder = () => {
    setShowAbandonModal(false);
    navigate('/cart');
  };

  const handleApplyCouponSubmit = (e) => {
    e.preventDefault();
    if (!couponInput.trim()) {
      setCouponMsg('Please enter a valid coupon code.');
      return;
    }
    if (couponInput.toUpperCase() === 'VIVISHA10') {
      setAppliedCouponCode('VIVISHA10');
      setCouponMsg('Success! Coupon VIVISHA10 applied.');
    } else {
      setCouponMsg('⚡ Coupon code will be validated with backend API upon deployment.');
    }
  };

  const handlePlaceOrder = () => {
    if (!selectedAddress) {
      alert('Please select or add a delivery address.');
      setIsAddressDrawerOpen(true);
      return;
    }

    const generatedOrderId = `VB-${Math.floor(100000 + Math.random() * 900000)}`;
    setPlacedOrderId(generatedOrderId);
    setOrderConfirmed(true);

    /**
     * API INTEGRATION POINT:
     * await ApiCall.post('/orders/create.php', {
     *   orderId: generatedOrderId,
     *   items: cartItems,
     *   shippingAddress: selectedAddress,
     *   paymentMethod: selectedPayment,
     *   totalAmount
     * });
     */
    clearCart();
  };

  // Order Confirmation Success View
  if (orderConfirmed) {
    return (
      <div className="checkout-page-container">
        <div className="order-confirmation-box">
          <div className="confirmation-icon-wrapper">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#2e7d32" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <h1 className="confirmation-title">Order Confirmed!</h1>
          <p className="confirmation-subtitle">
            Thank you for shopping at Vivisha Boutique. Your order <strong>#{placedOrderId}</strong> has been placed successfully and is being processed.
          </p>

          <div className="confirmation-actions">
            <Link to="/orders" className="btn-primary-purple">
              View Order History & Tracking
            </Link>
            <Link to="/collections" className="btn-secondary-outline">
              Continue Shopping
            </Link>
          </div>
        </div>
      </div>
    );
  }

  if (cartItems.length === 0) {
    return (
      <div className="checkout-page-container">
        <div className="empty-cart-container">
          <div className="empty-cart-icon-box">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="1.5">
              <circle cx="9" cy="21" r="1"></circle>
              <circle cx="20" cy="21" r="1"></circle>
              <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
          </div>
          <h2>No Active Order</h2>
          <p>Your shopping bag is empty. Add products to proceed with checkout.</p>
          <Link to="/collections" className="btn-primary-cart">
            Browse Collections
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="checkout-page-container">


      <div className="checkout-wrapper">
        <div className="checkout-header-row">
          <div>
            <h1 className="checkout-heading">Checkout & Payment</h1>
            <p className="checkout-subheading">
              Complete your order ({cartCount} {cartCount === 1 ? 'item' : 'items'} in your cart)
            </p>
          </div>
          <button
            type="button"
            className="btn-cancel-checkout"
            onClick={handleAttemptAbandon}
          >
            ✕ Cancel Order
          </button>
        </div>

        <div className="checkout-grid">
          {/* Left Column: Delivery Address & Payment Method */}
          <div className="checkout-main-section">
            {/* Delivery Address Card */}
            <div className="checkout-card-box address-card-box">
              <div className="section-label-sm">DELIVERY ADDRESS</div>

              {selectedAddress ? (
                <div
                  className="selected-address-card"
                  onClick={() => {
                    setIsAddingNewAddress(false);
                    setIsAddressDrawerOpen(true);
                  }}
                >
                  <div className="address-pin-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                      <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                  </div>
                  <div className="address-details-content">
                    <h4 className="address-user-name">{selectedAddress.name}</h4>
                    <p className="address-full-text">
                      {selectedAddress.street}, {selectedAddress.city}, {selectedAddress.state}, {selectedAddress.pincode}
                    </p>
                    <p className="address-phone-text">+{selectedAddress.phone.startsWith('91') ? '' : '91-'}{selectedAddress.phone}</p>
                  </div>
                  <div className="address-change-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </div>
                </div>
              ) : (
                <button
                  type="button"
                  className="btn-add-first-address"
                  onClick={handleOpenAddNew}
                >
                  + Add Delivery Address
                </button>
              )}
            </div>

            {/* Payment Options */}
            <div className="checkout-card-box payment-section-box">
              <h2 className="payment-options-heading">Payment Options</h2>

              {/* Offers Section */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">OFFERS</div>
                <div
                  className="coupon-trigger-row"
                  onClick={() => setShowCouponModal(!showCouponModal)}
                >
                  <div className="coupon-left-info">
                    <div className="coupon-icon-box">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                        <line x1="19" y1="5" x2="5" y2="19"></line>
                        <circle cx="6.5" cy="6.5" r="2.5"></circle>
                        <circle cx="17.5" cy="17.5" r="2.5"></circle>
                      </svg>
                    </div>
                    <div>
                      <strong>Apply Coupon Code / Discount Offers</strong>
                      <span>{appliedCouponCode ? `Applied: ${appliedCouponCode}` : 'You can apply coupons'}</span>
                    </div>
                  </div>
                  <div className="arrow-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </div>
                </div>

                {/* Expanded Coupon Input Box */}
                {showCouponModal && (
                  <form onSubmit={handleApplyCouponSubmit} className="checkout-coupon-form">
                    <div className="input-btn-inline">
                      <input
                        type="text"
                        placeholder="Enter coupon code (e.g. VIVISHA10)"
                        value={couponInput}
                        onChange={(e) => setCouponInput(e.target.value)}
                        className="coupon-text-input"
                      />
                      <button type="submit" className="btn-apply-coupon">
                        Apply
                      </button>
                    </div>
                    {couponMsg && <p className="coupon-status-msg">{couponMsg}</p>}
                  </form>
                )}
              </div>

              {/* Suggested / UPI Payment Methods */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">SUGGESTED</div>

                <div
                  className={`payment-option-card ${selectedPayment === 'upi' ? 'active' : ''}`}
                  onClick={() => setSelectedPayment('upi')}
                >
                  <div className="payment-radio-col">
                    <input
                      type="radio"
                      name="paymentOption"
                      checked={selectedPayment === 'upi'}
                      onChange={() => setSelectedPayment('upi')}
                    />
                  </div>
                  <div className="payment-content-col">
                    <div className="payment-header-line">
                      <strong className="payment-title">UPI</strong>
                      <span className="payment-amount-tag">₹{totalAmount}</span>
                    </div>
                    <p className="payment-sub-text">PhonePe, Google Pay, Paytm, BHIM & More</p>

                    {/* UPI Brand Badges */}
                    <div className="upi-badges-row">
                      <span className="upi-pill phonepe">PhonePe</span>
                      <span className="upi-pill gpay">Google Pay</span>
                      <span className="upi-pill paytm">Paytm</span>
                      <span className="upi-pill bhim">UPI</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Credit / Debit Card */}
              <div className="payment-group-block">
                <div
                  className={`payment-option-card ${selectedPayment === 'card' ? 'active' : ''}`}
                  onClick={() => setSelectedPayment('card')}
                >
                  <div className="payment-radio-col">
                    <input
                      type="radio"
                      name="paymentOption"
                      checked={selectedPayment === 'card'}
                      onChange={() => setSelectedPayment('card')}
                    />
                  </div>
                  <div className="payment-content-col">
                    <div className="payment-header-line">
                      <strong className="payment-title">Credit / Debit Card</strong>
                      <span className="payment-amount-tag">₹{totalAmount}</span>
                    </div>
                    <p className="payment-sub-text">RuPay, Visa, MasterCard, Amex</p>
                  </div>
                </div>
              </div>

              {/* Cash On Delivery */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">CASH</div>
                <div
                  className={`payment-option-card ${selectedPayment === 'cod' ? 'active' : ''}`}
                  onClick={() => setSelectedPayment('cod')}
                >
                  <div className="payment-radio-col">
                    <input
                      type="radio"
                      name="paymentOption"
                      checked={selectedPayment === 'cod'}
                      onChange={() => setSelectedPayment('cod')}
                    />
                  </div>
                  <div className="payment-content-col">
                    <div className="payment-header-line">
                      <strong className="payment-title">Cash On Delivery</strong>
                      <span className="payment-amount-tag">₹{totalAmount}</span>
                    </div>
                    <p className="payment-sub-text">Pay cash upon delivery at your doorstep</p>
                  </div>
                </div>
              </div>

              {/* User Profile Section */}
              <div className="payment-group-block user-profile-checkout-card">
                <div className="payment-group-sublabel">USER PROFILE</div>
                <Link to="/profile" className="profile-row-link">
                  <div className="user-avatar-badge">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                  </div>
                  <div className="user-profile-info">
                    <strong>{user?.name || 'Ronsia Pathees'}</strong>
                    <span>Manage your account here</span>
                  </div>
                  <div className="arrow-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </div>
                </Link>
              </div>
            </div>
          </div>

          {/* Right Column: Order Summary & Place Order */}
          <div className="checkout-summary-section">
            <div className="checkout-card-box summary-card">
              <h3>Order Summary</h3>

              <div className="summary-items-preview-box">
                {cartItems.map((item) => (
                  <div key={item.id} className="checkout-item-preview">
                    <img src={item.image} alt={item.name} />
                    <div className="item-details">
                      <h4>{item.name}</h4>
                      <p>Qty: {item.quantity} × ₹{item.price}</p>
                      <span className="item-price">₹{item.price * item.quantity}</span>
                    </div>
                  </div>
                ))}
              </div>

              <div className="summary-pricing">
                <div className="pricing-row">
                  <span>Subtotal ({cartCount} items)</span>
                  <span>₹{subtotal}</span>
                </div>

                {totalSavings > 0 && (
                  <div className="pricing-row savings-row">
                    <span>Total Discount</span>
                    <span className="savings-amount">-₹{totalSavings}</span>
                  </div>
                )}

                <div className="pricing-row">
                  <span>Delivery Charges</span>
                  <span className="free-shipping">FREE</span>
                </div>

                <div className="pricing-row total-row">
                  <span>Grand Total</span>
                  <span className="total-amount">₹{totalAmount}</span>
                </div>
              </div>

              <button
                type="button"
                className="btn-complete-payment"
                onClick={handlePlaceOrder}
              >
                Place Order (₹{totalAmount})
              </button>

              <button
                type="button"
                className="btn-cancel-checkout-link"
                onClick={handleAttemptAbandon}
              >
                Return to Cart
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* SELECT / ADD DELIVERY ADDRESS SIDE DRAWER / BOTTOM SHEET */}
      <div
        className={`address-drawer-overlay ${isAddressDrawerOpen ? 'open' : ''}`}
        onClick={() => setIsAddressDrawerOpen(false)}
      >
        <div
          className={`address-drawer ${isAddressDrawerOpen ? 'open' : ''}`}
          onClick={(e) => e.stopPropagation()}
        >
          <div className="drawer-header">
            <div className="drawer-title-box">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
              </svg>
              <h3>{isAddingNewAddress ? (editingAddressId ? 'Edit Address' : 'Add New Address') : 'Select Delivery Address'}</h3>
            </div>
            <button
              className="drawer-close-btn"
              onClick={() => setIsAddressDrawerOpen(false)}
            >
              ✕
            </button>
          </div>

          <div className="drawer-body">
            {!isAddingNewAddress ? (
              <>
                {/* List of Saved Addresses */}
                <div className="drawer-addresses-list">
                  {savedAddresses.map((addr) => {
                    const isSelected = addr.id === selectedAddressId;
                    return (
                      <div
                        key={addr.id}
                        className={`drawer-address-item ${isSelected ? 'selected' : ''}`}
                        onClick={() => setSelectedAddressId(addr.id)}
                      >
                        <div className="radio-col">
                          <input
                            type="radio"
                            name="drawerAddressSelect"
                            checked={isSelected}
                            onChange={() => setSelectedAddressId(addr.id)}
                          />
                        </div>
                        <div className="info-col">
                          <strong className="user-name">{addr.name}</strong>
                          <p className="full-address">
                            {addr.street}, {addr.city}, {addr.state}, {addr.pincode}
                          </p>
                          <span className="phone-num">+{addr.phone.startsWith('91') ? '' : '91-'}{addr.phone}</span>
                        </div>
                        <button
                          className="edit-pencil-btn"
                          onClick={(e) => handleEditAddress(addr, e)}
                          title="Edit Address"
                        >
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                          </svg>
                        </button>
                      </div>
                    );
                  })}
                </div>

                {/* Add New Address Button */}
                <button
                  type="button"
                  className="btn-add-new-address-trigger"
                  onClick={handleOpenAddNew}
                >
                  <span className="plus-icon">+</span> ADD NEW ADDRESS
                </button>
              </>
            ) : (
              /* Add/Edit Address Form */
              <form onSubmit={handleSaveAddressSubmit} className="drawer-address-form">
                <div className="form-group">
                  <label htmlFor="drawerName">Full Name *</label>
                  <input
                    type="text"
                    id="drawerName"
                    value={addressForm.name}
                    onChange={(e) => setAddressForm({ ...addressForm, name: e.target.value })}
                    placeholder="Enter full name"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerPhone">Mobile Number *</label>
                  <input
                    type="text"
                    id="drawerPhone"
                    value={addressForm.phone}
                    onChange={(e) => setAddressForm({ ...addressForm, phone: e.target.value })}
                    placeholder="Enter 10-digit mobile number"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerStreet">Street Address / House No. *</label>
                  <input
                    type="text"
                    id="drawerStreet"
                    value={addressForm.street}
                    onChange={(e) => setAddressForm({ ...addressForm, street: e.target.value })}
                    placeholder="House No., Street Name, Area"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerCity">City *</label>
                  <input
                    type="text"
                    id="drawerCity"
                    value={addressForm.city}
                    onChange={(e) => setAddressForm({ ...addressForm, city: e.target.value })}
                    placeholder="City"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerState">State *</label>
                  <input
                    type="text"
                    id="drawerState"
                    value={addressForm.state}
                    onChange={(e) => setAddressForm({ ...addressForm, state: e.target.value })}
                    placeholder="State"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerPincode">Pincode *</label>
                  <input
                    type="text"
                    id="drawerPincode"
                    value={addressForm.pincode}
                    onChange={(e) => setAddressForm({ ...addressForm, pincode: e.target.value })}
                    placeholder="6-digit pincode"
                    required
                  />
                </div>

                <div className="form-buttons-row">
                  <button
                    type="button"
                    className="btn-cancel-form"
                    onClick={() => setIsAddingNewAddress(false)}
                  >
                    Cancel
                  </button>
                  <button type="submit" className="btn-save-address">
                    Save Address
                  </button>
                </div>
              </form>
            )}
          </div>

          <div className="drawer-footer">
            {!isAddingNewAddress && (
              <button
                type="button"
                className="btn-drawer-done"
                onClick={() => setIsAddressDrawerOpen(false)}
              >
                DONE
              </button>
            )}

          </div>
        </div>
      </div>

      {/* Cart Abandonment Modal / Bottom Sheet */}
      <CartAbandonmentModal
        isOpen={showAbandonModal}
        onContinue={handleContinueOrder}
        onCancel={handleConfirmCancelOrder}
      />
    </div>
  );
};

export default Checkout;
