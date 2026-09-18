import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import CartAbandonmentModal from '../components/CartAbandonmentModal';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';

const Checkout = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated, token } = useAuth();
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
  const [savedAddresses, setSavedAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [isAddressDrawerOpen, setIsAddressDrawerOpen] = useState(false);
  const [isAddingNewAddress, setIsAddingNewAddress] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState(null);

  // Address Form State
  const [addressForm, setAddressForm] = useState({
    address_type: 'home',
    door_no: '',
    street: '',
    area: '',
    city: '',
    district: '',
    state: '',
    pincode: '',
    landmark: '',
    is_default: 0
  });

  const fetchAddresses = async () => {
    try {
      const response = await fetch(`${API_BASE_URL}/address/list.php`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status) {
        setSavedAddresses(data.data.addresses);
        const defaultAddr = data.data.addresses.find((a) => a.is_default === 1);
        if (defaultAddr) setSelectedAddressId(defaultAddr.id);
        else if (data.data.addresses.length > 0) setSelectedAddressId(data.data.addresses[0].id);
      }
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAddresses();
    }
  }, [isAuthenticated, token]);

  const selectedAddress = savedAddresses.find((addr) => addr.id === selectedAddressId) || savedAddresses[0];

  const handleOpenAddNew = () => {
    setAddressForm({
      address_type: 'home',
      door_no: '',
      street: '',
      area: '',
      city: '',
      district: '',
      state: '',
      pincode: '',
      landmark: '',
      is_default: 0
    });
    setEditingAddressId(null);
    setIsAddingNewAddress(true);
    setIsAddressDrawerOpen(true);
  };

  const handleEditAddress = (addr, e) => {
    e.stopPropagation();
    setAddressForm({
      address_type: addr.address_type,
      door_no: addr.door_no,
      street: addr.street,
      area: addr.area,
      city: addr.city,
      district: addr.district || '',
      state: addr.state,
      pincode: addr.pincode,
      landmark: addr.landmark || '',
      is_default: addr.is_default || 0
    });
    setEditingAddressId(addr.id);
    setIsAddingNewAddress(true);
  };

  const handleSaveAddressSubmit = async (e) => {
    e.preventDefault();

    const doorNo = addressForm.door_no ? addressForm.door_no.trim() : '';
    const street = addressForm.street ? addressForm.street.trim() : '';
    const area = addressForm.area ? addressForm.area.trim() : '';
    const city = addressForm.city ? addressForm.city.trim() : '';
    const district = addressForm.district ? addressForm.district.trim() : '';
    const state = addressForm.state ? addressForm.state.trim() : '';
    const pincode = addressForm.pincode ? addressForm.pincode.trim() : '';
    const landmark = addressForm.landmark ? addressForm.landmark.trim() : '';

    if (!doorNo) {
      alert('Door number is required.');
      return;
    }
    if (doorNo.length < 1 || doorNo.length > 100) {
      alert('Door number must be between 1 and 100 characters.');
      return;
    }

    if (!street) {
      alert('Street is required.');
      return;
    }
    if (street.length < 2 || street.length > 150) {
      alert('Street must be between 2 and 150 characters.');
      return;
    }

    if (!area) {
      alert('Area is required.');
      return;
    }
    if (area.length < 2 || area.length > 150) {
      alert('Area must be between 2 and 150 characters.');
      return;
    }

    if (!city) {
      alert('City is required.');
      return;
    }
    if (city.length < 2 || city.length > 100) {
      alert('City must be between 2 and 100 characters.');
      return;
    }

    if (district && district.length > 100) {
      alert('District must not exceed 100 characters.');
      return;
    }

    if (!state) {
      alert('State is required.');
      return;
    }
    if (state.length < 2 || state.length > 100) {
      alert('State must be between 2 and 100 characters.');
      return;
    }

    if (!pincode) {
      alert('Pincode is required.');
      return;
    }
    if (!/^[1-9][0-9]{5}$/.test(pincode)) {
      alert('Please enter a valid 6-digit Indian pincode.');
      return;
    }

    if (landmark && landmark.length > 150) {
      alert('Landmark must not exceed 150 characters.');
      return;
    }

    const endpoint = editingAddressId ? '/address/update.php' : '/address/create.php';
    const payload = {
      ...addressForm,
      door_no: doorNo,
      street,
      area,
      city,
      district,
      state,
      pincode,
      landmark
    };
    if (editingAddressId) {
      payload.address_id = editingAddressId;
    }

    try {
      const response = await fetch(`${API_BASE_URL}${endpoint}`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (data.status) {
        setIsAddingNewAddress(false);
        setEditingAddressId(null);
        fetchAddresses();
      } else {
        alert(data.message || 'Error saving address');
      }
    } catch (err) {
      console.error('Error saving address', err);
      alert('Network error saving address');
    }
  };

  const handleSelectAddress = async (id) => {
    setSelectedAddressId(id);
    try {
      const response = await fetch(`${API_BASE_URL}/address/select_address.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ address_id: id })
      });
      const data = await response.json();
      if (data.status) {
        fetchAddresses();
      } else {
        alert(data.message);
      }
    } catch (err) {
      console.error('Error setting default address', err);
    }
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
     * await ApiCall.post('/orders/create.php', { ... })
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
          <div className="checkout-main-section">
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
                    <h4 className="address-user-name">{selectedAddress.user_name}</h4>
                    <p className="address-full-text">
                      {selectedAddress.door_no}, {selectedAddress.street}, {selectedAddress.area}, {selectedAddress.city}, {selectedAddress.state} - {selectedAddress.pincode}
                    </p>
                    <p className="address-phone-text">+{selectedAddress.user_mobile}</p>
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

            <div className="checkout-card-box payment-section-box">
              <h2 className="payment-options-heading">Payment Options</h2>

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
                      <span className="payment-amount-tag">₹{totalAmount.toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">PhonePe, Google Pay, Paytm, BHIM & More</p>
                    <div className="upi-badges-row">
                      <span className="upi-pill phonepe">PhonePe</span>
                      <span className="upi-pill gpay">Google Pay</span>
                      <span className="upi-pill paytm">Paytm</span>
                      <span className="upi-pill bhim">UPI</span>
                    </div>
                  </div>
                </div>
              </div>

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
                      <span className="payment-amount-tag">₹{totalAmount.toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">RuPay, Visa, MasterCard, Amex</p>
                  </div>
                </div>
              </div>

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
                      <span className="payment-amount-tag">₹{totalAmount.toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">Pay cash upon delivery at your doorstep</p>
                  </div>
                </div>
              </div>

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

          <div className="checkout-summary-section">
            <div className="checkout-card-box summary-card">
              <h3>Order Summary</h3>

              <div className="summary-items-preview-box">
                {cartItems.map((item) => (
                  <div key={item.cart_item_id} className="checkout-item-preview">
                    {item.variant.primary_image && (
                      <img src={'http://localhost/vivisha_boutique/backend/' + item.variant.primary_image.image} alt={item.product.name} />
                    )}
                    <div className="item-details">
                      <h4>{item.product.name}</h4>
                      <p>Qty: {item.quantity} × ₹{item.unit_price}</p>
                      <span className="item-price">₹{item.line_total}</span>
                    </div>
                  </div>
                ))}
              </div>

              <div className="summary-pricing">
                <div className="pricing-row">
                  <span>Subtotal ({cartCount} items)</span>
                  <span>₹{subtotal.toLocaleString('en-IN')}.00</span>
                </div>

                {totalSavings > 0 && (
                  <div className="pricing-row savings-row">
                    <span>Total Discount</span>
                    <span className="savings-amount">-₹{totalSavings.toLocaleString('en-IN')}.00</span>
                  </div>
                )}

                <div className="pricing-row">
                  <span>Delivery Charges</span>
                  <span className="free-shipping">FREE</span>
                </div>

                <div className="pricing-row total-row">
                  <span>Grand Total</span>
                  <span className="total-amount">₹{totalAmount.toLocaleString('en-IN')}.00</span>
                </div>
              </div>

              <button
                type="button"
                className="btn-complete-payment"
                onClick={handlePlaceOrder}
              >
                Place Order (₹{totalAmount.toLocaleString('en-IN')}.00)
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
                <div className="drawer-addresses-list">
                  {savedAddresses.map((addr) => {
                    const isSelected = addr.id === selectedAddressId;
                    return (
                      <div
                        key={addr.id}
                        className={`drawer-address-item ${isSelected ? 'selected' : ''}`}
                        onClick={() => handleSelectAddress(addr.id)}
                      >
                        <div className="radio-col">
                          <input
                            type="radio"
                            name="drawerAddressSelect"
                            checked={isSelected}
                            onChange={() => handleSelectAddress(addr.id)}
                          />
                        </div>
                        <div className="info-col">
                          <strong className="user-name">{addr.user_name}</strong>
                          <p className="full-address">
                            {addr.door_no}, {addr.street}, {addr.area}, {addr.city}, {addr.state} - {addr.pincode}
                          </p>

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

                <button
                  type="button"
                  className="btn-add-new-address-trigger"
                  onClick={handleOpenAddNew}
                >
                  <span className="plus-icon">+</span> ADD NEW ADDRESS
                </button>
              </>
            ) : (
              <form onSubmit={handleSaveAddressSubmit} className="drawer-address-form">
                <div className="form-group">
                  <label htmlFor="drawerDoorNo">Door No. *</label>
                  <input
                    type="text"
                    id="drawerDoorNo"
                    value={addressForm.door_no}
                    onChange={(e) => setAddressForm({ ...addressForm, door_no: e.target.value })}
                    placeholder="House / Door No."
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerStreet">Street Name *</label>
                  <input
                    type="text"
                    id="drawerStreet"
                    value={addressForm.street}
                    onChange={(e) => setAddressForm({ ...addressForm, street: e.target.value })}
                    placeholder="Street Name"
                    required
                  />
                </div>
                <div className="form-group">
                  <label htmlFor="drawerArea">Area / Locality *</label>
                  <input
                    type="text"
                    id="drawerArea"
                    value={addressForm.area}
                    onChange={(e) => setAddressForm({ ...addressForm, area: e.target.value })}
                    placeholder="Area / Locality"
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

      <CartAbandonmentModal
        isOpen={showAbandonModal}
        onContinue={handleContinueOrder}
        onCancel={handleConfirmCancelOrder}
      />
    </div>
  );
};

export default Checkout;
