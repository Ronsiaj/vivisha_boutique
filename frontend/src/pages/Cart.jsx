import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCoupons } from '../context/CouponContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import CartAbandonmentModal from '../components/CartAbandonmentModal';
import CouponDrawer from '../components/CouponDrawer.jsx';
import AddressDrawer from '../components/AddressDrawer.jsx';

const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const Cart = () => {
  const navigate = useNavigate();
  const { isAuthenticated, token } = useAuth();
  const {
    cartItems,
    cartCount,
    subtotal,
    totalOriginal,
    totalSavings,
    deliveryFee,
    updateQuantity,
    removeFromCart,
    isLoading,
    updatingItemIds
  } = useCart();

  const { addToWishlist } = useWishlist();
  const {
    appliedCoupon,
    applyCouponToCart,
    removeAppliedCoupon,
    calculateDiscount,
    birthdayState,
    firstOrderState,
    festivalState
  } = useCoupons();

  const [showAbandonModal, setShowAbandonModal] = useState(false);
  const [isCouponDrawerOpen, setIsCouponDrawerOpen] = useState(false);
  const [isAddressDrawerOpen, setIsAddressDrawerOpen] = useState(false);
  const [couponCodeInput, setCouponCodeInput] = useState('');
  const [couponMsg, setCouponMsg] = useState('');
  const [toastMessage, setToastMessage] = useState('');
  const [movingItemIds, setMovingItemIds] = useState(new Set());

  // Delivery Addresses State in Cart
  const [savedAddresses, setSavedAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [isAddressLoading, setIsAddressLoading] = useState(false);

  const fetchAddresses = async () => {
    if (!token) {
      setSavedAddresses([]);
      setSelectedAddressId(null);
      return;
    }
    setIsAddressLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/address/list.php`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && Array.isArray(data.data.addresses)) {
        const addresses = data.data.addresses;
        setSavedAddresses(addresses);
        if (addresses.length > 0) {
          const defaultAddr = addresses.find((a) => a.is_default === 1);
          if (defaultAddr) {
            setSelectedAddressId(defaultAddr.id);
          } else if (!selectedAddressId || !addresses.some((a) => a.id === selectedAddressId)) {
            setSelectedAddressId(addresses[0].id);
          }
        } else {
          setSelectedAddressId(null);
        }
      } else {
        setSavedAddresses([]);
        setSelectedAddressId(null);
      }
    } catch (err) {
      console.error('Error fetching addresses in Cart:', err);
    } finally {
      setIsAddressLoading(false);
    }
  };

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAddresses();
    } else {
      setSavedAddresses([]);
      setSelectedAddressId(null);
    }
  }, [isAuthenticated, token]);

  const selectedAddress = savedAddresses.find((addr) => addr.id === selectedAddressId) ||
    (savedAddresses.length > 0 ? (savedAddresses.find((a) => a.is_default === 1) || savedAddresses[0]) : null);
  const hasDeliveryAddress = Boolean(isAuthenticated && selectedAddress && selectedAddress.id);

  // If address is explicitly removed and user has zero addresses, clear applied coupon
  useEffect(() => {
    if (!isAddressLoading && !hasDeliveryAddress && appliedCoupon && savedAddresses.length === 0) {
      removeAppliedCoupon();
    }
  }, [hasDeliveryAddress, isAddressLoading, appliedCoupon, removeAppliedCoupon, savedAddresses.length]);

  const showToast = (msg) => {
    setToastMessage(msg);
    setTimeout(() => {
      setToastMessage('');
    }, 3500);
  };

  // Calculate live coupon discount strictly when delivery address is present
  const couponDiscountAmount = (hasDeliveryAddress && appliedCoupon) ? calculateDiscount(appliedCoupon, subtotal) : 0;
  const finalTotalAmount = Math.max(0, subtotal - couponDiscountAmount);

  // Check if any items in the cart are out of stock
  const hasOutOfStockItems = cartItems.some((item) => {
    const stockStatus = item.variant?.stock?.stock_status;
    const availableQty = item.variant?.stock?.available_quantity;
    return stockStatus === 'out_of_stock' || (typeof availableQty === 'number' && availableQty <= 0);
  });

  const handleOpenAddressDrawer = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: '/cart' } });
    } else {
      setIsAddressDrawerOpen(true);
    }
  };

  const handleAddressSaved = async (newOrUpdatedId) => {
    await fetchAddresses();
    if (newOrUpdatedId) {
      setSelectedAddressId(newOrUpdatedId);
    }
    setCouponMsg('');
    showToast('Delivery address confirmed!');
  };

  const handleAddressDeleted = async (deletedId) => {
    const remaining = savedAddresses.filter((a) => a.id !== deletedId);
    setSavedAddresses(remaining);
    if (remaining.length > 0) {
      const nextSelected = remaining.find((a) => a.is_default === 1) || remaining[0];
      setSelectedAddressId(nextSelected.id);
    } else {
      setSelectedAddressId(null);
      if (appliedCoupon) {
        removeAppliedCoupon();
      }
      setCouponMsg('Please add or select an address before applying a coupon.');
    }
    await fetchAddresses();
    showToast('Address removed.');
  };

  const handleSelectAddress = (id) => {
    setSelectedAddressId(id);
    setCouponMsg('');
    showToast('Delivery address updated!');
  };

  const handleProceedToCheckout = () => {
    if (cartItems.length === 0) return;
    if (hasOutOfStockItems) {
      showToast('Please remove out-of-stock items before proceeding to checkout.');
      return;
    }
    if (!isAuthenticated) {
      navigate('/login', { state: { from: '/checkout' } });
    } else {
      navigate('/checkout');
    }
  };

  const handleQuantityChange = async (item, delta) => {
    const currentQty = Number(item.quantity) || 1;
    const newQty = currentQty + delta;
    const maxStock = item.variant?.stock?.available_quantity ?? 100;

    if (newQty < 1) {
      handleRemoveItem(item);
      return;
    }

    if (newQty > 100) {
      showToast('Maximum allowed quantity is 100 per item.');
      return;
    }

    if (typeof maxStock === 'number' && newQty > maxStock) {
      showToast(`Only ${maxStock} items available in stock.`);
      return;
    }

    const res = await updateQuantity(item.cart_item_id, newQty);
    if (!res.success) {
      showToast(res.message || 'Could not update quantity.');
    }
  };

  const handleRemoveItem = async (item) => {
    const res = await removeFromCart(item.cart_item_id);
    if (res.success) {
      showToast(`Removed "${item.product.name}" from your cart.`);
    } else {
      showToast(res.message || 'Failed to remove item.');
    }
  };

  const handleMoveToWishlist = async (item) => {
    setMovingItemIds((prev) => new Set(prev).add(item.cart_item_id));
    try {
      const wishRes = await addToWishlist(item.variant_id);
      if (wishRes.success) {
        await removeFromCart(item.cart_item_id);
        showToast(`Moved "${item.product.name}" to your wishlist!`);
      } else {
        showToast(wishRes.message || 'Could not move item to wishlist.');
      }
    } catch (err) {
      console.error('Error moving item to wishlist:', err);
      showToast('Error moving item to wishlist.');
    } finally {
      setMovingItemIds((prev) => {
        const next = new Set(prev);
        next.delete(item.cart_item_id);
        return next;
      });
    }
  };

  const handleApplyCouponFromDrawer = (coupon) => {
    if (!hasDeliveryAddress) {
      setCouponMsg('Please add or select an address before applying a coupon.');
      setIsCouponDrawerOpen(false);
      handleOpenAddressDrawer();
      return;
    }
    applyCouponToCart(coupon);
    showToast(`Coupon "${coupon.code || coupon.coupon_code}" applied successfully!`);
    setCouponMsg('');
  };

  const handleManualQuickApply = (e) => {
    e.preventDefault();
    if (!hasDeliveryAddress) {
      setCouponMsg('Please add or select an address before applying a coupon.');
      handleOpenAddressDrawer();
      return;
    }
    const code = couponCodeInput.trim().toUpperCase();
    if (!code) {
      setCouponMsg('Please enter a coupon code.');
      return;
    }
    // Open drawer to show matched results or allow manual application
    setIsCouponDrawerOpen(true);
  };

  const handleRemoveCoupon = () => {
    removeAppliedCoupon();
    showToast('Coupon removed.');
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
            {cartCount > 0 && (
              <span className="wishlist-count-badge">
                {cartCount} {cartCount === 1 ? 'Item' : 'Items'}
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Toast Feedback Notification */}
      {toastMessage && (
        <div style={{
          position: 'fixed',
          bottom: '80px',
          right: '24px',
          backgroundColor: '#333333',
          color: '#ffffff',
          padding: '12px 24px',
          borderRadius: '8px',
          boxShadow: '0 4px 14px rgba(0,0,0,0.2)',
          zIndex: 9999,
          fontSize: '0.9rem',
          display: 'flex',
          alignItems: 'center',
          gap: '8px',
          animation: 'fadeInPopover 0.2s ease-out'
        }}>
          <span>✨</span>
          <span>{toastMessage}</span>
        </div>
      )}

      {isLoading ? (
        <div className="empty-cart-container" style={{ padding: '60px 0' }}>
          <div className="cart-spinner" style={{
            width: '40px',
            height: '40px',
            border: '3px solid #f3e8ff',
            borderTop: '3px solid var(--primary-color)',
            borderRadius: '50%',
            margin: '0 auto 16px',
            animation: 'spin 0.8s linear infinite'
          }}></div>
          <p style={{ color: '#666', fontWeight: '500' }}>Loading your boutique bag...</p>
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
        <div className="cart-wrapper" style={{ maxWidth: '1240px', margin: '0 auto', padding: '0 1rem 3rem' }}>
          {/* Left Column: Cart Items & Missed Something Section */}
          <div className="cart-items-section">
            {/* Out-of-Stock Alert Banner */}
            {hasOutOfStockItems && (
              <div style={{
                background: '#fff4f2',
                border: '1px solid #ffccc7',
                borderRadius: '8px',
                padding: '12px 16px',
                marginBottom: '1.25rem',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                color: '#d4380d',
                fontSize: '0.9rem'
              }}>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <line x1="12" y1="8" x2="12" y2="12"></line>
                  <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span>Some items in your cart are currently out of stock. Please remove them or move to wishlist to checkout.</span>
              </div>
            )}

            {/* List of Cart Items */}
            <div className="cart-list-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h3 style={{ margin: 0, fontSize: '1.15rem', color: '#222' }}>
                Cart Items ({cartItems.length} {cartItems.length === 1 ? 'Product' : 'Products'})
              </h3>
            </div>

            {cartItems.map((item) => {
              const imgUrl = item.variant?.primary_image?.image 
                ? (item.variant.primary_image.image.startsWith('http') 
                    ? item.variant.primary_image.image 
                    : ASSET_BASE_URL + item.variant.primary_image.image)
                : '';

              const isUpdating = updatingItemIds.has(item.cart_item_id);
              const isMoving = movingItemIds.has(item.cart_item_id);

              const stockStatus = item.variant?.stock?.stock_status;
              const availableQty = item.variant?.stock?.available_quantity;
              const isOutOfStock = stockStatus === 'out_of_stock' || (typeof availableQty === 'number' && availableQty <= 0);
              const isLowStock = stockStatus === 'low_stock';

              return (
                <div key={item.cart_item_id} className="cart-item-card" style={{ opacity: (isUpdating || isMoving) ? 0.7 : 1 }}>
                  {/* Product Thumbnail */}
                  {imgUrl ? (
                    <img
                      src={imgUrl}
                      alt={item.product?.name || 'Product'}
                      className="cart-item-img"
                      onClick={() => navigate(`/product/${item.variant_id}`)}
                      style={{ cursor: 'pointer' }}
                    />
                  ) : (
                    <div
                      className="cart-item-img"
                      style={{ backgroundColor: '#f0f0f0', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#999', fontSize: '12px' }}
                      onClick={() => navigate(`/product/${item.variant_id}`)}
                    >
                      No Image
                    </div>
                  )}

                  {/* Product Details */}
                  <div className="cart-item-info">
                    {item.category?.name && (
                      <span className="cart-item-category">{item.category.name}</span>
                    )}

                    <h3
                      style={{ cursor: 'pointer', margin: '4px 0 6px', fontSize: '1.05rem', lineHeight: '1.3' }}
                      onClick={() => navigate(`/product/${item.variant_id}`)}
                    >
                      {item.product?.name}
                      {item.variant?.color?.name ? ` - ${item.variant.color.name}` : ''}
                    </h3>

                    {item.variant?.variant_name && item.variant.variant_name.trim() !== '' && (
                      <div style={{ fontSize: '13px', color: '#666', marginBottom: '4px' }}>
                        Variant: <strong style={{ color: '#333' }}>{item.variant.variant_name.trim()}</strong>
                      </div>
                    )}

                    {item.variant?.size?.name && (
                      <div style={{ fontSize: '13px', color: '#666', marginBottom: '8px' }}>
                        Size: <strong style={{ color: '#333' }}>{item.variant.size.name}</strong>
                      </div>
                    )}

                    {/* Stock Status Badges */}
                    {isOutOfStock ? (
                      <div style={{ display: 'inline-block', background: '#fff1f0', color: '#cf1322', border: '1px solid #ffa39e', fontSize: '11px', fontWeight: 700, padding: '2px 8px', borderRadius: '4px', marginBottom: '8px' }}>
                        Out of Stock
                      </div>
                    ) : isLowStock ? (
                      <div style={{ display: 'inline-block', background: '#fffbe6', color: '#d48806', border: '1px solid #ffe58f', fontSize: '11px', fontWeight: 600, padding: '2px 8px', borderRadius: '4px', marginBottom: '8px' }}>
                        Only {availableQty} left in stock
                      </div>
                    ) : null}

                    {/* Pricing */}
                    <div className="price-tag" style={{ display: 'flex', alignItems: 'center', gap: '10px', flexWrap: 'wrap' }}>
                      <span className="current-price" style={{ fontSize: '1.1rem', fontWeight: 700, color: 'var(--primary-color)' }}>
                        ₹{parseFloat(item.unit_price || 0).toLocaleString('en-IN')}
                      </span>
                      {parseFloat(item.variant?.pricing?.original_price || 0) > parseFloat(item.unit_price || 0) && (
                        <span className="original-price" style={{ fontSize: '0.9rem', color: '#999', textDecoration: 'line-through' }}>
                          ₹{parseFloat(item.variant.pricing.original_price).toLocaleString('en-IN')}
                        </span>
                      )}
                      <span className="item-subtotal-tag" style={{ fontSize: '0.85rem', color: '#555', background: '#f9f9f9', padding: '2px 8px', borderRadius: '4px', border: '1px solid #eee' }}>
                        Subtotal: ₹{parseFloat(item.line_total || 0).toLocaleString('en-IN')}
                      </span>
                    </div>

                    {/* Quantity Stepper & Move-to-Wishlist Actions */}
                    <div style={{ display: 'flex', alignItems: 'center', gap: '16px', marginTop: '12px', flexWrap: 'wrap' }}>
                      <div style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        border: '1px solid #ddd',
                        borderRadius: '6px',
                        background: '#ffffff',
                        overflow: 'hidden',
                        height: '32px'
                      }}>
                        <button
                          type="button"
                          onClick={() => handleQuantityChange(item, -1)}
                          disabled={isUpdating || isOutOfStock}
                          style={{
                            width: '32px',
                            height: '100%',
                            border: 'none',
                            background: '#f9f9f9',
                            cursor: (isUpdating || isOutOfStock) ? 'not-allowed' : 'pointer',
                            fontSize: '16px',
                            fontWeight: 'bold',
                            color: 'var(--primary-color)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'background 0.2s ease'
                          }}
                          title="Decrease quantity"
                        >
                          -
                        </button>
                        <span style={{
                          minWidth: '36px',
                          textAlign: 'center',
                          fontSize: '13px',
                          fontWeight: '600',
                          color: '#333'
                        }}>
                          {isUpdating ? '...' : item.quantity}
                        </span>
                        <button
                          type="button"
                          onClick={() => handleQuantityChange(item, 1)}
                          disabled={isUpdating || isOutOfStock || (typeof availableQty === 'number' && item.quantity >= availableQty) || item.quantity >= 100}
                          style={{
                            width: '32px',
                            height: '100%',
                            border: 'none',
                            background: '#f9f9f9',
                            cursor: (isUpdating || isOutOfStock || (typeof availableQty === 'number' && item.quantity >= availableQty) || item.quantity >= 100) ? 'not-allowed' : 'pointer',
                            fontSize: '16px',
                            fontWeight: 'bold',
                            color: 'var(--primary-color)',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                            transition: 'background 0.2s ease'
                          }}
                          title="Increase quantity"
                        >
                          +
                        </button>
                      </div>

                      {/* Move to Wishlist Button */}
                      <button
                        type="button"
                        onClick={() => handleMoveToWishlist(item)}
                        disabled={isMoving}
                        style={{
                          background: 'none',
                          border: 'none',
                          color: 'var(--primary-color)',
                          fontSize: '0.85rem',
                          fontWeight: 600,
                          cursor: isMoving ? 'not-allowed' : 'pointer',
                          display: 'inline-flex',
                          alignItems: 'center',
                          gap: '5px',
                          padding: '4px 8px',
                          borderRadius: '4px',
                          textDecoration: 'underline'
                        }}
                      >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        {isMoving ? 'Moving...' : 'Move to Wishlist'}
                      </button>
                    </div>
                  </div>

                  {/* Remove Item Button */}
                  <button
                    type="button"
                    className="btn-remove-item"
                    onClick={() => handleRemoveItem(item)}
                    title="Remove item"
                    style={{ position: 'absolute', top: '12px', right: '12px' }}
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
                type="button"
                className="btn-abandon-order"
                onClick={handleAttemptAbandon}
              >
                Cancel Order Process
              </button>
            </div>
          </div>

          {/* Right Column: Coupons & Order Summary */}
          <div className="cart-sidebar-section">
            {/* Dedicated Coupons & Offers Section */}
            <div className="coupons-card-box">
              <div className="coupons-card-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                  </svg>
                  <h3 style={{ margin: 0, fontSize: '1.05rem' }}>Coupons & Offers</h3>
                </div>
                <button
                  type="button"
                  onClick={() => setIsCouponDrawerOpen(true)}
                  style={{
                    background: 'none',
                    border: 'none',
                    color: 'var(--primary-color)',
                    fontWeight: '700',
                    fontSize: '0.85rem',
                    cursor: 'pointer',
                    textDecoration: 'underline'
                  }}
                >
                  View All Offers →
                </button>
              </div>

              {/* Delivery Address Requirement or Confirmed Delivery Address Pill */}
              {hasDeliveryAddress ? (
                <div style={{
                  background: '#f8fafc',
                  border: '1px solid #e2e8f0',
                  borderRadius: '8px',
                  padding: '10px 14px',
                  marginTop: '12px',
                  marginBottom: '12px',
                  display: 'flex',
                  alignItems: 'flex-start',
                  justifyContent: 'space-between',
                  gap: '10px'
                }}>
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '8px', flex: 1, minWidth: 0 }}>
                    <span style={{ fontSize: '1.1rem', flexShrink: 0, lineHeight: 1.2 }}>📍</span>
                    <div style={{
                      fontSize: '0.84rem',
                      color: '#334155',
                      lineHeight: '1.45',
                      wordBreak: 'break-word',
                      overflowWrap: 'break-word'
                    }}>
                      <span style={{ fontWeight: '700', color: '#0f172a' }}>Deliver to: </span>
                      <span>
                        {[
                          selectedAddress.door_no,
                          selectedAddress.area,
                          selectedAddress.landmark,
                          selectedAddress.city,
                          selectedAddress.district,
                          selectedAddress.state
                        ].filter(Boolean).join(', ')}
                        {selectedAddress.pincode ? ` - ${selectedAddress.pincode}` : ''}
                      </span>
                    </div>
                  </div>
                  <button
                    type="button"
                    onClick={handleOpenAddressDrawer}
                    style={{
                      background: 'none',
                      border: 'none',
                      color: 'var(--primary-color, #A049A3)',
                      fontSize: '0.82rem',
                      fontWeight: '700',
                      cursor: 'pointer',
                      textDecoration: 'underline',
                      flexShrink: 0,
                      padding: 0,
                      marginTop: '2px'
                    }}
                  >
                    Change
                  </button>
                </div>
              ) : (
                <div style={{
                  background: '#fff7ed',
                  border: '1px solid #fed7aa',
                  borderRadius: '8px',
                  padding: '12px 14px',
                  marginTop: '12px',
                  marginBottom: '12px'
                }}>
                  <div style={{
                    display: 'flex',
                    alignItems: 'flex-start',
                    gap: '8px',
                    color: '#c2410c',
                    fontSize: '0.85rem',
                    fontWeight: '600',
                    marginBottom: '8px'
                  }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '1px' }}>
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Please add or select an address before applying a coupon.</span>
                  </div>
                  <button
                    type="button"
                    onClick={handleOpenAddressDrawer}
                    style={{
                      width: '100%',
                      background: 'var(--primary-color)',
                      color: '#ffffff',
                      border: 'none',
                      borderRadius: '6px',
                      padding: '8px 12px',
                      fontSize: '0.85rem',
                      fontWeight: '700',
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '6px',
                      transition: 'opacity 0.2s'
                    }}
                  >
                    <span>+</span> {isAuthenticated ? 'Add / Select Delivery Address' : 'Log In to Add Delivery Address'}
                  </button>
                </div>
              )}

              {/* Applied Coupon Banner */}
              {appliedCoupon && hasDeliveryAddress ? (
                <div style={{
                  background: '#f0fdf4',
                  border: '1.5px dashed #86efac',
                  borderRadius: '8px',
                  padding: '12px 14px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  margin: '12px 0'
                }}>
                  <div>
                    <span style={{ fontSize: '11px', fontWeight: '700', color: '#166534', display: 'block', textTransform: 'uppercase' }}>
                      Coupon Applied
                    </span>
                    <strong style={{ color: 'var(--primary-color)', fontSize: '0.95rem' }}>
                      {appliedCoupon.code || appliedCoupon.coupon_code}
                    </strong>
                    {couponDiscountAmount > 0 && (
                      <span style={{ fontSize: '0.85rem', color: '#166534', marginLeft: '6px' }}>
                        (Saved ₹{couponDiscountAmount.toLocaleString('en-IN')})
                      </span>
                    )}
                  </div>
                  <button
                    type="button"
                    onClick={handleRemoveCoupon}
                    style={{
                      background: '#fee2e2',
                      border: '1px solid #fca5a5',
                      color: '#991b1b',
                      borderRadius: '4px',
                      padding: '4px 8px',
                      fontSize: '12px',
                      fontWeight: '700',
                      cursor: 'pointer'
                    }}
                  >
                    Remove
                  </button>
                </div>
              ) : (
                <form onSubmit={handleManualQuickApply} className="coupon-input-form" style={{ marginTop: hasDeliveryAddress ? '12px' : '4px' }}>
                  <input
                    type="text"
                    placeholder={hasDeliveryAddress ? 'Enter Coupon / Referral Code' : 'Add address to unlock coupons'}
                    value={couponCodeInput}
                    onChange={(e) => setCouponCodeInput(e.target.value.toUpperCase())}
                    className="coupon-input"
                    disabled={!hasDeliveryAddress}
                    style={{
                      backgroundColor: !hasDeliveryAddress ? '#f8fafc' : '#ffffff',
                      cursor: !hasDeliveryAddress ? 'not-allowed' : 'text'
                    }}
                  />
                  <button
                    type="submit"
                    className="btn-apply-coupon"
                    disabled={!hasDeliveryAddress}
                    style={{
                      opacity: !hasDeliveryAddress ? 0.6 : 1,
                      cursor: !hasDeliveryAddress ? 'not-allowed' : 'pointer'
                    }}
                  >
                    Apply
                  </button>
                </form>
              )}

              {couponMsg && (
                <p className="coupon-info-msg" style={{ marginTop: '8px', color: !hasDeliveryAddress ? '#c2410c' : undefined }}>
                  {couponMsg}
                </p>
              )}

              {/* Quick Available Offers List preview */}
              <div className="available-offers-list" style={{ marginTop: '12px' }}>
                {birthdayState.eligible && birthdayState.coupons?.[0] && (
                  <div
                    className="offer-tag-item"
                    onClick={() => {
                      if (!hasDeliveryAddress) {
                        setCouponMsg('Please add or select an address before applying a coupon.');
                        handleOpenAddressDrawer();
                      } else {
                        setIsCouponDrawerOpen(true);
                      }
                    }}
                    style={{ cursor: 'pointer', borderLeft: '3px solid #16a34a' }}
                  >
                    <span className="offer-code" style={{ color: '#16a34a' }}>🎂 {birthdayState.coupons[0].coupon_code}</span>
                    <span className="offer-desc">{birthdayState.coupons[0].title || 'Birthday Month Discount'}</span>
                  </div>
                )}

                {firstOrderState.eligible && firstOrderState.coupons?.[0] && (
                  <div
                    className="offer-tag-item"
                    onClick={() => {
                      if (!hasDeliveryAddress) {
                        setCouponMsg('Please add or select an address before applying a coupon.');
                        handleOpenAddressDrawer();
                      } else {
                        setIsCouponDrawerOpen(true);
                      }
                    }}
                    style={{ cursor: 'pointer', borderLeft: '3px solid #16a34a' }}
                  >
                    <span className="offer-code" style={{ color: '#16a34a' }}>🎉 {firstOrderState.coupons[0].coupon_code}</span>
                    <span className="offer-desc">First Order Special Gift</span>
                  </div>
                )}

                {festivalState.coupons?.[0] && (
                  <div
                    className="offer-tag-item"
                    onClick={() => {
                      if (!hasDeliveryAddress) {
                        setCouponMsg('Please add or select an address before applying a coupon.');
                        handleOpenAddressDrawer();
                      } else {
                        setIsCouponDrawerOpen(true);
                      }
                    }}
                    style={{ cursor: 'pointer', borderLeft: '3px solid #6d28d9' }}
                  >
                    <span className="offer-code">🪔 {festivalState.coupons[0].coupon_code}</span>
                    <span className="offer-desc">{festivalState.coupons[0].festival_name || festivalState.coupons[0].title}</span>
                  </div>
                )}
              </div>
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

                {couponDiscountAmount > 0 && (
                  <div className="pricing-row savings-row" style={{ color: '#16a34a' }}>
                    <span>Coupon Savings ({appliedCoupon?.code || appliedCoupon?.coupon_code})</span>
                    <span className="savings-amount" style={{ color: '#16a34a' }}>
                      -₹{couponDiscountAmount.toLocaleString('en-IN')}.00
                    </span>
                  </div>
                )}

                <div className="pricing-row">
                  <span>Standard Delivery</span>
                  <span className="free-shipping">FREE</span>
                </div>

                <div className="pricing-row total-row">
                  <span>Total Amount</span>
                  <span className="total-amount">₹{finalTotalAmount.toLocaleString('en-IN')}.00</span>
                </div>
              </div>

              <button
                type="button"
                className="btn-proceed-checkout"
                onClick={handleProceedToCheckout}
                disabled={hasOutOfStockItems}
                style={{
                  opacity: hasOutOfStockItems ? 0.6 : 1,
                  cursor: hasOutOfStockItems ? 'not-allowed' : 'pointer'
                }}
              >
                {hasOutOfStockItems
                  ? 'Remove Out-of-Stock Items'
                  : `Proceed to Checkout (₹${finalTotalAmount.toLocaleString('en-IN')}.00)`
                }
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

      {/* Coupon Drawer */}
      <CouponDrawer
        isOpen={isCouponDrawerOpen}
        onClose={() => setIsCouponDrawerOpen(false)}
        currentSubtotal={subtotal}
        appliedCouponCode={appliedCoupon?.code || appliedCoupon?.coupon_code}
        hasDeliveryAddress={hasDeliveryAddress}
        onRequireAddress={() => {
          setIsCouponDrawerOpen(false);
          handleOpenAddressDrawer();
        }}
        onApplyCoupon={handleApplyCouponFromDrawer}
      />

      {/* Address Drawer */}
      <AddressDrawer
        isOpen={isAddressDrawerOpen}
        onClose={() => setIsAddressDrawerOpen(false)}
        savedAddresses={savedAddresses}
        selectedAddressId={selectedAddressId}
        onSelectAddress={handleSelectAddress}
        onAddressSaved={handleAddressSaved}
        onAddressDeleted={handleAddressDeleted}
      />
    </div>
  );
};

export default Cart;
