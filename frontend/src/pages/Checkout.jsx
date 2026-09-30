import React, { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useCart } from '../context/CartContext.jsx';
import { useCoupons } from '../context/CouponContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import CartAbandonmentModal from '../components/CartAbandonmentModal';
import CouponDrawer from '../components/CouponDrawer.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

const getImageUrl = (imagePath) => {
  if (!imagePath) return '';
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
    return imagePath;
  }
  const cleanPath = imagePath.startsWith('/') ? imagePath.slice(1) : imagePath;
  const base = ASSET_BASE_URL.endsWith('/') ? ASSET_BASE_URL : `${ASSET_BASE_URL}/`;
  return `${base}${cleanPath}`;
};

// Utility function to dynamically load Razorpay Checkout SDK
const loadRazorpayScript = () => {
  return new Promise((resolve) => {
    if (window.Razorpay) {
      resolve(true);
      return;
    }
    const existingScript = document.querySelector('script[src="https://checkout.razorpay.com/v1/checkout.js"]');
    if (existingScript) {
      existingScript.onload = () => resolve(true);
      existingScript.onerror = () => resolve(false);
      return;
    }
    const script = document.createElement('script');
    script.src = 'https://checkout.razorpay.com/v1/checkout.js';
    script.async = true;
    script.onload = () => resolve(true);
    script.onerror = () => resolve(false);
    document.body.appendChild(script);
  });
};

const Checkout = () => {
  const navigate = useNavigate();
  const { user, isAuthenticated, token } = useAuth();
  const {
    cartItems,
    cartCount,
    subtotal,
    totalSavings,
    clearCart
  } = useCart();

  const {
    appliedCoupon,
    applyCouponToCart,
    removeAppliedCoupon,
    calculateDiscount,
    applyCouponToOrder
  } = useCoupons();

  const [showAbandonModal, setShowAbandonModal] = useState(false);
  const [isCouponDrawerOpen, setIsCouponDrawerOpen] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState('upi');
  const [customerNote, setCustomerNote] = useState('');
  const [orderConfirmed, setOrderConfirmed] = useState(false);
  const [placedOrderId, setPlacedOrderId] = useState('');
  const [placedOrderData, setPlacedOrderData] = useState(null);
  const [placedPaymentData, setPlacedPaymentData] = useState(null);
  const [isPlacingOrder, setIsPlacingOrder] = useState(false);
  const [isVerifyingPayment, setIsVerifyingPayment] = useState(false);

  // Active Draft Order State (persisted in session to prevent duplicate order / duplicate coupon calls on retry)
  const [activeDraftOrder, setActiveDraftOrder] = useState(() => {
    try {
      const saved = sessionStorage.getItem('vivisha_active_draft_order');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const updateActiveDraftOrder = (order) => {
    setActiveDraftOrder(order);
    if (order) {
      try {
        sessionStorage.setItem('vivisha_active_draft_order', JSON.stringify(order));
      } catch (err) {
        console.error('Failed to save draft order:', err);
      }
    } else {
      try {
        sessionStorage.removeItem('vivisha_active_draft_order');
      } catch (err) {
        console.error('Failed to clear draft order:', err);
      }
    }
  };

  const [orderError, setOrderError] = useState('');
  const [paymentNotice, setPaymentNotice] = useState('');

  // Saved Addresses State
  const [savedAddresses, setSavedAddresses] = useState([]);
  const [selectedAddressId, setSelectedAddressId] = useState(null);
  const [isAddressDrawerOpen, setIsAddressDrawerOpen] = useState(false);
  const [isAddingNewAddress, setIsAddingNewAddress] = useState(false);
  const [editingAddressId, setEditingAddressId] = useState(null);
  const [addressError, setAddressError] = useState('');

  const selectedAddress = savedAddresses.find((addr) => addr.id === selectedAddressId) || (savedAddresses.length > 0 ? savedAddresses[0] : null);
  const hasDeliveryAddress = Boolean(selectedAddress && selectedAddress.id);

  // COD additional handling charge rule (₹50 in UI preview, ₹0 for prepaid online)
  const codHandlingCharge = selectedPayment === 'cod' ? 50 : 0;

  // Coupon application and discount calculation
  const isCouponApplicable = hasDeliveryAddress && Boolean(appliedCoupon);
  const couponDiscountAmount = isCouponApplicable ? calculateDiscount(appliedCoupon, subtotal) : 0;
  const grandTotalAmount = Math.max(0, subtotal - couponDiscountAmount + codHandlingCharge);

  // Preload Razorpay Checkout SDK on mount
  useEffect(() => {
    loadRazorpayScript();
  }, []);

  // Unauthenticated users are redirected to dedicated Login/Register page
  useEffect(() => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: '/checkout' } });
    }
  }, [isAuthenticated, navigate]);

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
    if (!token) return;
    try {
      const response = await fetch(`${API_BASE_URL}/address/list.php`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && Array.isArray(data.data.addresses)) {
        setSavedAddresses(data.data.addresses);
        const defaultAddr = data.data.addresses.find((a) => a.is_default === 1);
        if (defaultAddr) {
          setSelectedAddressId(defaultAddr.id);
        } else if (data.data.addresses.length > 0) {
          setSelectedAddressId(data.data.addresses[0].id);
        }
      }
    } catch (err) {
      console.error('Error fetching addresses:', err);
    }
  };

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAddresses();
    }
  }, [isAuthenticated, token]);

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
      is_default: savedAddresses.length === 0 ? 1 : 0
    });
    setEditingAddressId(null);
    setAddressError('');
    setIsAddingNewAddress(true);
    setIsAddressDrawerOpen(true);
  };

  const handleEditAddress = (addr, e) => {
    if (e) e.stopPropagation();
    setAddressForm({
      address_type: addr.address_type || 'home',
      door_no: addr.door_no || '',
      street: addr.street || '',
      area: addr.area || '',
      city: addr.city || '',
      district: addr.district || '',
      state: addr.state || '',
      pincode: addr.pincode || '',
      landmark: addr.landmark || '',
      is_default: addr.is_default || 0
    });
    setEditingAddressId(addr.id);
    setAddressError('');
    setIsAddingNewAddress(true);
  };

  const handleSaveAddressSubmit = async (e) => {
    e.preventDefault();
    setAddressError('');

    const doorNo = addressForm.door_no ? addressForm.door_no.trim() : '';
    const street = addressForm.street ? addressForm.street.trim() : '';
    const area = addressForm.area ? addressForm.area.trim() : '';
    const city = addressForm.city ? addressForm.city.trim() : '';
    const district = addressForm.district ? addressForm.district.trim() : '';
    const state = addressForm.state ? addressForm.state.trim() : '';
    const pincode = addressForm.pincode ? addressForm.pincode.trim() : '';
    const landmark = addressForm.landmark ? addressForm.landmark.trim() : '';

    if (!doorNo) {
      setAddressError('Door number is required.');
      return;
    }
    if (doorNo.length < 1 || doorNo.length > 100) {
      setAddressError('Door number must be between 1 and 100 characters.');
      return;
    }

    if (!street) {
      setAddressError('Street is required.');
      return;
    }
    if (street.length < 2 || street.length > 150) {
      setAddressError('Street must be between 2 and 150 characters.');
      return;
    }

    if (!area) {
      setAddressError('Area is required.');
      return;
    }
    if (area.length < 2 || area.length > 150) {
      setAddressError('Area must be between 2 and 150 characters.');
      return;
    }

    if (!city) {
      setAddressError('City is required.');
      return;
    }
    if (city.length < 2 || city.length > 100) {
      setAddressError('City must be between 2 and 100 characters.');
      return;
    }

    if (district && district.length > 100) {
      setAddressError('District must not exceed 100 characters.');
      return;
    }

    if (!state) {
      setAddressError('State is required.');
      return;
    }
    if (state.length < 2 || state.length > 100) {
      setAddressError('State must be between 2 and 100 characters.');
      return;
    }

    if (!pincode) {
      setAddressError('Pincode is required.');
      return;
    }
    if (!/^[1-9][0-9]{5}$/.test(pincode)) {
      setAddressError('Please enter a valid 6-digit Indian pincode.');
      return;
    }

    if (landmark && landmark.length > 150) {
      setAddressError('Landmark must not exceed 150 characters.');
      return;
    }

    const isUpdate = Boolean(editingAddressId);
    const endpoint = isUpdate ? `${API_BASE_URL}/address/update.php` : `${API_BASE_URL}/address/create.php`;
    const httpMethod = isUpdate ? 'PUT' : 'POST';

    const payload = {
      address_type: addressForm.address_type,
      door_no: doorNo,
      street,
      area,
      city,
      district: district || null,
      state,
      pincode,
      landmark: landmark || null,
      is_default: addressForm.is_default ? 1 : 0
    };

    if (isUpdate) {
      payload.address_id = editingAddressId;
    }

    try {
      const response = await fetch(endpoint, {
        method: httpMethod,
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
        await fetchAddresses();
        if (data.data?.address?.id) {
          setSelectedAddressId(data.data.address.id);
        }
      } else {
        setAddressError(data.message || 'Error saving address');
      }
    } catch (err) {
      console.error('Error saving address', err);
      setAddressError('Network error saving address');
    }
  };

  const handleSelectAddress = async (id) => {
    setSelectedAddressId(id);
    setOrderError('');
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
      }
    } catch (err) {
      console.error('Error setting default address', err);
    }
  };

  const handleDeleteAddress = async (id, e) => {
    if (e) e.stopPropagation();
    if (!window.confirm('Are you sure you want to remove this address?')) return;
    try {
      const response = await fetch(`${API_BASE_URL}/address/update.php`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          address_id: id,
          status: 'inactive'
        })
      });
      const data = await response.json();
      if (data.status) {
        const remaining = savedAddresses.filter((a) => a.id !== id);
        if (remaining.length > 0) {
          setSelectedAddressId(remaining[0].id);
        } else {
          setSelectedAddressId(null);
          if (appliedCoupon) {
            removeAppliedCoupon();
          }
          setOrderError('Please select delivery address before applying coupon.');
        }
        fetchAddresses();
      }
    } catch (err) {
      console.error('Error deleting address:', err);
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

  /**
   * Complete End-to-End Order & Razorpay Payment Flow:
   * 1. Create or Reuse Draft Order (orders/create.php)
   * 2. Assign Delivery Address (orders/select_address.php)
   * 3. Attach Applied Coupon ONCE (Situation A: do NOT re-validate / re-apply if already applied)
   * 4. Initiate Payment via payments/create.php
   * 5. Open Razorpay Checkout modal (online) or complete COD
   * 6. Verify Payment via payments/verify.php
   * 7. Confirm Order upon verified payment
   */
  const handlePlaceOrder = async () => {
    if (!hasDeliveryAddress || !selectedAddress) {
      setOrderError('Please select delivery address before proceeding to payment.');
      setIsAddressDrawerOpen(true);
      return;
    }

    if (!cartItems || cartItems.length === 0) {
      setOrderError('Your shopping cart is empty.');
      return;
    }

    setIsPlacingOrder(true);
    setOrderError('');
    setPaymentNotice('');

    try {
      let currentOrder = activeDraftOrder;
      let orderId = currentOrder?.id;
      let orderNumber = currentOrder?.order_number;

      // 1. Create Draft Order using orders/create.php if not already created in this checkout session
      if (!orderId) {
        const orderItemsPayload = cartItems.map((item) => ({
          product_id: parseInt(item.product_id || item.product?.id, 10),
          variant_id: parseInt(item.variant_id || item.variant?.id, 10),
          quantity: parseInt(item.quantity, 10)
        }));

        const createResponse = await fetch(`${API_BASE_URL}/orders/create.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            items: orderItemsPayload,
            customer_note: customerNote.trim() ? customerNote.trim() : null
          })
        });

        const createData = await createResponse.json();

        if (!createData.status || !createData.data?.order) {
          throw new Error(createData.message || 'Failed to initialize order.');
        }

        orderId = createData.data.order.id;
        orderNumber = createData.data.order.order_number;
        currentOrder = {
          id: orderId,
          order_number: orderNumber,
          address_id: null,
          coupon_attached: false,
          coupon_code: null
        };
        updateActiveDraftOrder(currentOrder);
      }

      // 2. Assign delivery address for order via orders/select_address.php (if not already assigned)
      if (currentOrder.address_id !== selectedAddress.id) {
        const addrResponse = await fetch(`${API_BASE_URL}/orders/select_address.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
          },
          body: JSON.stringify({
            order_id: orderId,
            address_id: selectedAddress.id
          })
        });

        const addrData = await addrResponse.json();
        if (!addrData.status) {
          throw new Error(addrData.message || 'Failed to select delivery address for order.');
        }
        currentOrder.address_id = selectedAddress.id;
        updateActiveDraftOrder(currentOrder);
      }

      // 3. Coupon Handling (Situation A vs Situation B)
      // If the coupon is ALREADY established/applied to this order, DO NOT call coupon application/validation again!
      if (hasDeliveryAddress && appliedCoupon) {
        const couponKey = appliedCoupon.id || appliedCoupon.coupon_id || appliedCoupon.code || appliedCoupon.coupon_code;
        const couponCode = appliedCoupon.code || appliedCoupon.coupon_code;

        // Check if coupon is already attached to this draft order in DB
        const isAlreadyAttached = currentOrder.coupon_attached === true || currentOrder.coupon_code === couponCode;

        if (!isAlreadyAttached) {
          try {
            const couponResult = await applyCouponToOrder(
              appliedCoupon.type || appliedCoupon.coupon_type || 'first_order',
              orderId,
              couponKey
            );

            if (couponResult.success || couponResult.already_applied) {
              currentOrder.coupon_attached = true;
              currentOrder.coupon_code = couponCode;
              updateActiveDraftOrder(currentOrder);
            } else {
              // If backend indicates coupon was already applied or processed, mark attached to avoid duplicate loop
              console.warn('Coupon application notice:', couponResult.message);
              currentOrder.coupon_attached = true;
              updateActiveDraftOrder(currentOrder);
            }
          } catch (couponErr) {
            console.warn('Could not attach coupon to order:', couponErr);
            currentOrder.coupon_attached = true;
            updateActiveDraftOrder(currentOrder);
          }
        }
      }

      // 4. Initiate Payment via payments/create.php
      const paymentResponse = await fetch(`${API_BASE_URL}/payments/create.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          order_id: orderId,
          payment_method: selectedPayment
        })
      });

      const paymentData = await paymentResponse.json();

      if (!paymentData.status) {
        throw new Error(paymentData.message || 'Failed to initiate payment.');
      }

      // 5A. Handle Cash On Delivery (COD)
      if (selectedPayment === 'cod') {
        await clearCart();
        removeAppliedCoupon();
        updateActiveDraftOrder(null);
        setPlacedOrderId(orderNumber);
        setPlacedOrderData(paymentData.data?.order || { id: orderId, order_number: orderNumber });
        setPlacedPaymentData(paymentData.data?.payment || { provider: 'cod', status: 'pending', payment_method: 'cod' });
        setOrderConfirmed(true);
        setIsPlacingOrder(false);
        return;
      }

      // 5B. Handle Online Payments via Razorpay Checkout
      const razorpayInfo = paymentData.data?.razorpay;
      const paymentRecord = paymentData.data?.payment;
      const customerInfo = paymentData.data?.customer;

      if (!razorpayInfo || !razorpayInfo.order_id) {
        throw new Error('Razorpay order details not received from server.');
      }

      const scriptLoaded = await loadRazorpayScript();
      if (!scriptLoaded) {
        throw new Error('Could not load Razorpay payment gateway SDK. Please check your internet connection.');
      }

      const razorpayKey = razorpayInfo.key_id || import.meta.env.VITE_RAZORPAY_KEY_ID;
      if (!razorpayKey) {
        throw new Error('Razorpay Public Key is missing in server response.');
      }

      const razorpayOptions = {
        key: razorpayKey,
        amount: razorpayInfo.amount,
        currency: razorpayInfo.currency || 'INR',
        name: 'Vivisha Boutique',
        description: `Order #${orderNumber}`,
        order_id: razorpayInfo.order_id,
        prefill: {
          name: customerInfo?.name || user?.name || '',
          email: customerInfo?.email || user?.email || '',
          contact: customerInfo?.mobile || user?.phone || ''
        },
        theme: {
          color: '#6b21a8'
        },
        modal: {
          ondismiss: function () {
            setIsPlacingOrder(false);
            setIsVerifyingPayment(false);
            setPaymentNotice('Payment was cancelled. Your order remains saved — click "Pay with Razorpay" below to retry.');
          }
        },
        handler: async function (razorpayResponse) {
          setIsVerifyingPayment(true);
          setIsPlacingOrder(true);
          setPaymentNotice('Verifying your payment with Razorpay & the bank...');

          try {
            // Step 6: Verify payment with existing backend verify API
            const verifyResponse = await fetch(`${API_BASE_URL}/payments/verify.php`, {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
              },
              body: JSON.stringify({
                order_id: orderId,
                payment_id: paymentRecord?.id,
                razorpay_order_id: razorpayResponse.razorpay_order_id,
                razorpay_payment_id: razorpayResponse.razorpay_payment_id,
                razorpay_signature: razorpayResponse.razorpay_signature
              })
            });

            const verifyData = await verifyResponse.json();

            if (verifyData.status) {
              // Verified successfully! Clear user's active cart and draft order state
              await clearCart();
              removeAppliedCoupon();
              updateActiveDraftOrder(null);
              setPlacedOrderId(orderNumber);
              setPlacedOrderData(verifyData.data?.order || paymentData.data?.order);
              setPlacedPaymentData(verifyData.data?.payment);
              setOrderConfirmed(true);
            } else {
              setOrderError(verifyData.message || 'Payment verification could not be confirmed. If the amount was debited, your order will be updated shortly.');
            }
          } catch (verifyErr) {
            console.error('Payment verification error:', verifyErr);
            setOrderError('Network error while verifying payment with server. Please check your order history or contact support.');
          } finally {
            setIsVerifyingPayment(false);
            setIsPlacingOrder(false);
          }
        }
      };

      const rzpInstance = new window.Razorpay(razorpayOptions);

      rzpInstance.on('payment.failed', function (failureResponse) {
        console.error('Razorpay payment failed:', failureResponse);
        setIsPlacingOrder(false);
        setIsVerifyingPayment(false);
        const failMsg = failureResponse.error?.description || failureResponse.error?.reason || 'Transaction could not be completed. Please try another card or payment method.';
        setOrderError(`Payment Failed: ${failMsg}`);
      });

      rzpInstance.open();

    } catch (err) {
      console.error('Checkout error:', err);
      setOrderError(err.message || 'An unexpected error occurred while processing your checkout. Please try again.');
      setIsPlacingOrder(false);
      setIsVerifyingPayment(false);
    }
  };

  // Order Confirmation Success View
  if (orderConfirmed) {
    const paymentStatusBadge = placedPaymentData?.status === 'paid' ? 'PAID' : 'PENDING';
    const displayGrandTotal = placedOrderData?.amounts?.grand_total || placedOrderData?.grand_total || grandTotalAmount;

    return (
      <div className="checkout-page-container">
        <div className="order-confirmation-box" style={{ maxWidth: '650px', margin: '0 auto', textAlign: 'center', padding: '40px 24px' }}>
          <div className="confirmation-icon-wrapper" style={{ marginBottom: '20px' }}>
            <svg width="68" height="68" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <h1 className="confirmation-title" style={{ fontSize: '1.8rem', fontWeight: '800', color: 'var(--text-color, #1f2937)', marginBottom: '8px' }}>
            Order Confirmed!
          </h1>
          <p className="confirmation-subtitle" style={{ color: '#4b5563', fontSize: '1.05rem', lineHeight: '1.6', marginBottom: '24px' }}>
            Thank you for shopping with us! Your order <strong style={{ color: 'var(--primary-color, #6b21a8)' }}>#{placedOrderId}</strong> has been successfully placed.
          </p>

          <div style={{
            background: '#faf5ff',
            border: '1px solid #e9d5ff',
            borderRadius: '12px',
            padding: '20px',
            textAlign: 'left',
            marginBottom: '30px'
          }}>
            <h4 style={{ margin: '0 0 12px 0', fontSize: '1rem', color: 'var(--primary-color, #6b21a8)', display: 'flex', alignItems: 'center', gap: '8px' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                <line x1="1" y1="10" x2="23" y2="10"></line>
              </svg>
              Payment & Delivery Summary
            </h4>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '14px', fontSize: '0.9rem' }}>
              <div>
                <span style={{ color: '#6b7280', display: 'block', fontSize: '0.8rem' }}>Payment Method</span>
                <strong style={{ textTransform: 'uppercase' }}>
                  {selectedPayment === 'cod' ? 'Cash on Delivery (COD)' : `Razorpay (${(placedPaymentData?.payment_method || selectedPayment).toUpperCase()})`}
                </strong>
              </div>
              <div>
                <span style={{ color: '#6b7280', display: 'block', fontSize: '0.8rem' }}>Payment Status</span>
                <span style={{
                  display: 'inline-block',
                  fontSize: '0.78rem',
                  fontWeight: '700',
                  padding: '2px 8px',
                  borderRadius: '4px',
                  background: paymentStatusBadge === 'PAID' ? '#dcfce7' : '#fef3c7',
                  color: paymentStatusBadge === 'PAID' ? '#166534' : '#92400e'
                }}>
                  {paymentStatusBadge}
                </span>
              </div>
              {placedPaymentData?.razorpay_payment_id && (
                <div>
                  <span style={{ color: '#6b7280', display: 'block', fontSize: '0.8rem' }}>Transaction ID</span>
                  <strong style={{ fontSize: '0.85rem', fontFamily: 'monospace', color: '#374151' }}>
                    {placedPaymentData.razorpay_payment_id}
                  </strong>
                </div>
              )}
              <div>
                <span style={{ color: '#6b7280', display: 'block', fontSize: '0.8rem' }}>Total Amount</span>
                <strong style={{ color: 'var(--primary-color, #6b21a8)', fontSize: '1.05rem' }}>
                  ₹{parseFloat(displayGrandTotal).toLocaleString('en-IN')}.00
                </strong>
              </div>
              {selectedAddress && (
                <div style={{ gridColumn: '1 / -1' }}>
                  <span style={{ color: '#6b7280', display: 'block', fontSize: '0.8rem' }}>Delivery To</span>
                  <strong>{selectedAddress.user_name || user?.name}</strong>: {selectedAddress.door_no}, {selectedAddress.street}, {selectedAddress.area}, {selectedAddress.city}, {selectedAddress.state} - {selectedAddress.pincode}
                </div>
              )}
            </div>
          </div>

          <div className="confirmation-actions" style={{ display: 'flex', gap: '14px', justifyContent: 'center', flexWrap: 'wrap' }}>
            <Link to="/orders" className="btn-primary-purple" style={{ padding: '12px 24px', borderRadius: '8px', textDecoration: 'none', fontWeight: '600' }}>
              View My Orders & Tracking
            </Link>
            <Link to="/collections" className="btn-secondary-outline" style={{ padding: '12px 24px', borderRadius: '8px', textDecoration: 'none', fontWeight: '600' }}>
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
      {/* Verifying Payment Overlay */}
      {isVerifyingPayment && (
        <div style={{
          position: 'fixed',
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          background: 'rgba(0, 0, 0, 0.7)',
          backdropFilter: 'blur(4px)',
          zIndex: 9999,
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          color: '#ffffff',
          padding: '20px',
          textAlign: 'center'
        }}>
          <div style={{
            width: '56px',
            height: '56px',
            border: '4px solid rgba(255,255,255,0.3)',
            borderTop: '4px solid #a855f7',
            borderRadius: '50%',
            animation: 'spin 1s linear infinite',
            marginBottom: '20px'
          }} />
          <h2 style={{ fontSize: '1.4rem', fontWeight: '700', marginBottom: '8px' }}>Verifying Payment...</h2>
          <p style={{ maxWidth: '420px', color: '#e2e8f0', fontSize: '0.95rem', lineHeight: '1.5' }}>
            Securing transaction with Razorpay and the bank. Please do not refresh or close this window.
          </p>
        </div>
      )}

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

        {orderError && (
          <div style={{
            background: '#fee2e2',
            border: '1px solid #fca5a5',
            color: '#991b1b',
            padding: '12px 16px',
            borderRadius: '8px',
            marginBottom: '20px',
            fontSize: '0.9rem',
            display: 'flex',
            alignItems: 'center',
            gap: '10px'
          }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div style={{ flexGrow: 1 }}>{orderError}</div>
            <button
              type="button"
              onClick={() => setOrderError('')}
              style={{ background: 'none', border: 'none', color: '#991b1b', cursor: 'pointer', fontWeight: 'bold' }}
            >
              ✕
            </button>
          </div>
        )}

        {paymentNotice && !orderError && (
          <div style={{
            background: '#fef3c7',
            border: '1px solid #fde68a',
            color: '#92400e',
            padding: '12px 16px',
            borderRadius: '8px',
            marginBottom: '20px',
            fontSize: '0.9rem',
            display: 'flex',
            alignItems: 'center',
            gap: '10px'
          }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <div style={{ flexGrow: 1 }}>{paymentNotice}</div>
            <button
              type="button"
              onClick={() => setPaymentNotice('')}
              style={{ background: 'none', border: 'none', color: '#92400e', cursor: 'pointer', fontWeight: 'bold' }}
            >
              ✕
            </button>
          </div>
        )}

        <div className="checkout-grid">
          <div className="checkout-main-section">
            {/* Delivery Address Box */}
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
                    <h4 className="address-user-name">{selectedAddress.user_name || user?.name}</h4>
                    <p className="address-full-text">
                      {selectedAddress.door_no}, {selectedAddress.street}, {selectedAddress.area}, {selectedAddress.city}, {selectedAddress.state} - {selectedAddress.pincode}
                    </p>
                    {selectedAddress.landmark && (
                      <p style={{ fontSize: '0.8rem', color: '#6b7280', margin: '2px 0 0 0' }}>
                        Landmark: {selectedAddress.landmark}
                      </p>
                    )}
                    <p className="address-phone-text">+{selectedAddress.user_mobile || user?.phone}</p>
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

            {/* Customer Note / Delivery Instructions */}
            <div className="checkout-card-box delivery-instructions-card" style={{ padding: '20px', boxSizing: 'border-box' }}>
              <div className="section-label-sm" style={{ marginBottom: '8px' }}>DELIVERY INSTRUCTIONS (OPTIONAL)</div>
              <textarea
                value={customerNote}
                onChange={(e) => setCustomerNote(e.target.value)}
                maxLength={500}
                placeholder="Add special instructions for delivery, landmark tips, or order notes (max 500 characters)..."
                rows={2}
                className="delivery-instruction-textarea"
                style={{
                  width: '100%',
                  maxWidth: '100%',
                  boxSizing: 'border-box',
                  display: 'block',
                  padding: '10px 14px',
                  borderRadius: '8px',
                  border: '1px solid #d1d5db',
                  fontSize: '0.9rem',
                  fontFamily: 'inherit',
                  resize: 'vertical'
                }}
              />
              <div style={{ textAlign: 'right', fontSize: '0.75rem', color: '#9ca3af', marginTop: '4px' }}>
                {customerNote.length}/500 characters
              </div>
            </div>

            {/* Payment Options */}
            <div className="checkout-card-box payment-section-box">
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '16px' }}>
                <h2 className="payment-options-heading" style={{ margin: 0 }}>Payment Options</h2>
                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.78rem', color: '#16a34a', fontWeight: '600' }}>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                  </svg>
                  <span>100% Razorpay Secure</span>
                </div>
              </div>

              {/* Coupons & Offers Trigger */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">OFFERS & PROMOTIONS</div>
                <div
                  className="coupon-trigger-row"
                  onClick={() => {
                    if (!hasDeliveryAddress) {
                      setOrderError('Please select delivery address before applying coupon.');
                      setIsAddressDrawerOpen(true);
                      return;
                    }
                    setIsCouponDrawerOpen(true);
                  }}
                  style={{
                    cursor: 'pointer',
                    opacity: hasDeliveryAddress ? 1 : 0.85
                  }}
                >
                  <div className="coupon-left-info">
                    <div className="coupon-icon-box" style={{ background: !hasDeliveryAddress ? '#ffedd5' : undefined, color: !hasDeliveryAddress ? '#c2410c' : undefined }}>
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <line x1="19" y1="5" x2="5" y2="19"></line>
                        <circle cx="6.5" cy="6.5" r="2.5"></circle>
                        <circle cx="17.5" cy="17.5" r="2.5"></circle>
                      </svg>
                    </div>
                    <div>
                      <strong>Apply Coupon Code / Discount Offers</strong>
                      <span style={{ color: !hasDeliveryAddress ? '#c2410c' : undefined, fontWeight: !hasDeliveryAddress ? '500' : 'normal' }}>
                        {!hasDeliveryAddress
                          ? 'Select delivery address first to apply coupon'
                          : appliedCoupon
                          ? `Applied: ${appliedCoupon.code || appliedCoupon.coupon_code}`
                          : 'View available discounts & offers'}
                      </span>
                    </div>
                  </div>
                  <div className="arrow-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </div>
                </div>

                {/* Credit Card Offer Banner */}
                <div style={{
                  marginTop: '10px',
                  background: '#f0fdf4',
                  border: '1px solid #bbf7d0',
                  borderRadius: '8px',
                  padding: '10px 14px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'space-between',
                  gap: '12px'
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <div style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: '6px',
                      background: '#dcfce7',
                      color: '#15803d',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}>
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                        <line x1="1" y1="10" x2="23" y2="10"></line>
                      </svg>
                    </div>
                    <div>
                      <div style={{ fontSize: '0.85rem', fontWeight: '700', color: '#166534' }}>
                        Credit Card Offer
                      </div>
                      <div style={{ fontSize: '0.78rem', color: '#15803d' }}>
                        Get 5% OFF on eligible Credit Card payments
                      </div>
                    </div>
                  </div>
                  <span style={{ fontSize: '0.72rem', background: '#dcfce7', color: '#166534', padding: '2px 8px', borderRadius: '4px', fontWeight: '700', whiteSpace: 'nowrap' }}>
                    SPECIAL OFFER
                  </span>
                </div>

                {/* Warning message when delivery address is not yet selected */}
                {!hasDeliveryAddress && (
                  <div style={{
                    marginTop: '8px',
                    fontSize: '0.82rem',
                    color: '#c2410c',
                    background: '#fff7ed',
                    border: '1px solid #ffedd5',
                    borderRadius: '6px',
                    padding: '8px 12px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '6px',
                    fontWeight: '500'
                  }}>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="8" x2="12" y2="12"></line>
                      <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>Please select delivery address before applying coupon.</span>
                  </div>
                )}

                {/* Applied Coupon Info Box (only active once delivery address is selected) */}
                {appliedCoupon && hasDeliveryAddress && (
                  <div style={{
                    background: '#f0fdf4',
                    border: '1px solid #86efac',
                    borderRadius: '8px',
                    padding: '10px 14px',
                    marginTop: '10px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between'
                  }}>
                    <div>
                      <span style={{ fontSize: '11px', fontWeight: '700', color: '#166534', display: 'block' }}>COUPON APPLIED</span>
                      <strong style={{ color: 'var(--primary-color)' }}>{appliedCoupon.code || appliedCoupon.coupon_code}</strong>
                      {couponDiscountAmount > 0 && (
                        <span style={{ fontSize: '12px', color: '#166534', marginLeft: '6px' }}>
                          (-₹{couponDiscountAmount.toLocaleString('en-IN')}.00)
                        </span>
                      )}
                    </div>
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        removeAppliedCoupon();
                        if (activeDraftOrder) {
                          updateActiveDraftOrder({
                            ...activeDraftOrder,
                            coupon_attached: false,
                            coupon_code: null
                          });
                        }
                      }}
                      style={{
                        background: '#fee2e2',
                        border: '1px solid #fca5a5',
                        color: '#991b1b',
                        padding: '4px 8px',
                        borderRadius: '4px',
                        fontSize: '11px',
                        fontWeight: '700',
                        cursor: 'pointer'
                      }}
                    >
                      Remove
                    </button>
                  </div>
                )}
              </div>

              {/* UPI */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">ONLINE INSTANT PAYMENT (RAZORPAY)</div>
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
                      <strong className="payment-title">UPI / Instant Pay (FREE Delivery)</strong>
                      <span className="payment-amount-tag">₹{Math.max(0, subtotal - couponDiscountAmount).toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">PhonePe, Google Pay, Paytm, BHIM & UPI QR</p>
                    <div className="upi-badges-row">
                      <span className="upi-pill phonepe">PhonePe</span>
                      <span className="upi-pill gpay">Google Pay</span>
                      <span className="upi-pill paytm">Paytm</span>
                      <span className="upi-pill bhim">UPI</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Cards */}
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
                      <strong className="payment-title">Credit / Debit Card (FREE Delivery)</strong>
                      <span className="payment-amount-tag">₹{Math.max(0, subtotal - couponDiscountAmount).toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">RuPay, Visa, MasterCard, Amex</p>
                    <div style={{
                      marginTop: '6px',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '6px',
                      background: '#eff6ff',
                      border: '1px solid #bfdbfe',
                      padding: '3px 8px',
                      borderRadius: '4px',
                      fontSize: '0.78rem',
                      color: '#1e40af',
                      fontWeight: '600'
                    }}>
                      <span>💳 Credit Card Offer: Get 5% OFF on eligible Credit Card payments</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Netbanking */}
              <div className="payment-group-block">
                <div
                  className={`payment-option-card ${selectedPayment === 'netbanking' ? 'active' : ''}`}
                  onClick={() => setSelectedPayment('netbanking')}
                >
                  <div className="payment-radio-col">
                    <input
                      type="radio"
                      name="paymentOption"
                      checked={selectedPayment === 'netbanking'}
                      onChange={() => setSelectedPayment('netbanking')}
                    />
                  </div>
                  <div className="payment-content-col">
                    <div className="payment-header-line">
                      <strong className="payment-title">Netbanking (FREE Delivery)</strong>
                      <span className="payment-amount-tag">₹{Math.max(0, subtotal - couponDiscountAmount).toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">HDFC, SBI, ICICI, Axis, Kotak & 50+ Indian banks</p>
                  </div>
                </div>
              </div>

              {/* Wallets & Online Modes */}
              <div className="payment-group-block">
                <div
                  className={`payment-option-card ${selectedPayment === 'wallet' ? 'active' : ''}`}
                  onClick={() => setSelectedPayment('wallet')}
                >
                  <div className="payment-radio-col">
                    <input
                      type="radio"
                      name="paymentOption"
                      checked={selectedPayment === 'wallet'}
                      onChange={() => setSelectedPayment('wallet')}
                    />
                  </div>
                  <div className="payment-content-col">
                    <div className="payment-header-line">
                      <strong className="payment-title">Wallets & Pay Later (FREE Delivery)</strong>
                      <span className="payment-amount-tag">₹{Math.max(0, subtotal - couponDiscountAmount).toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">Paytm Wallet, Mobikwik, Airtel Money, EMI & PayLater</p>
                  </div>
                </div>
              </div>

              {/* Cash On Delivery (COD) */}
              <div className="payment-group-block">
                <div className="payment-group-sublabel">CASH ON DELIVERY</div>
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
                      <span className="payment-amount-tag">₹{(Math.max(0, subtotal - couponDiscountAmount) + 50).toLocaleString('en-IN')}.00</span>
                    </div>
                    <p className="payment-sub-text">
                      Pay cash upon doorstep delivery (+₹50 handling charge applies for Cash on Delivery)
                    </p>
                    <div style={{ marginTop: '6px' }}>
                      <span style={{ fontSize: '0.75rem', background: '#fef3c7', color: '#92400e', padding: '2px 8px', borderRadius: '4px', fontWeight: '600' }}>
                        ₹50 COD fee added
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              {/* User Profile Checkout shortcut */}
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
                    <strong>{user?.name || 'Customer'}</strong>
                    <span>Manage your addresses and profile</span>
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

          {/* Order Summary Sidebar */}
          <div className="checkout-summary-section">
            <div className="checkout-card-box summary-card">
              <h3>Order Summary</h3>

              <div className="summary-items-preview-box">
                {cartItems.map((item) => (
                  <div key={item.cart_item_id || item.id} className="checkout-item-preview">
                    {item.variant?.primary_image && (
                      <img src={getImageUrl(item.variant.primary_image.image)} alt={item.product?.name} />
                    )}
                    <div className="item-details">
                      <h4>{item.product?.name}</h4>
                      <p>
                        {item.variant?.variant_name && item.variant.variant_name.trim() !== '' && (
                          <span>Variant: {item.variant.variant_name.trim()} • </span>
                        )}
                        Qty: {item.quantity} × ₹{item.unit_price}
                      </p>
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

                {couponDiscountAmount > 0 && (
                  <div className="pricing-row savings-row" style={{ color: '#16a34a' }}>
                    <span>Coupon Savings ({appliedCoupon?.code || appliedCoupon?.coupon_code})</span>
                    <span className="savings-amount" style={{ color: '#16a34a' }}>
                      -₹{couponDiscountAmount.toLocaleString('en-IN')}.00
                    </span>
                  </div>
                )}

                <div className="pricing-row">
                  <span>Delivery Charges</span>
                  <span className="free-shipping">FREE</span>
                </div>

                {selectedPayment === 'cod' && (
                  <div className="pricing-row" style={{ color: '#b45309' }}>
                    <span>COD Handling Fee</span>
                    <span style={{ fontWeight: '600' }}>+₹50.00</span>
                  </div>
                )}

                <div className="pricing-row total-row">
                  <span>Grand Total</span>
                  <span className="total-amount">₹{grandTotalAmount.toLocaleString('en-IN')}.00</span>
                </div>
              </div>

              <button
                type="button"
                className="btn-complete-payment"
                onClick={handlePlaceOrder}
                disabled={isPlacingOrder || isVerifyingPayment}
                style={{
                  opacity: (isPlacingOrder || isVerifyingPayment) ? 0.7 : 1,
                  cursor: (isPlacingOrder || isVerifyingPayment) ? 'not-allowed' : 'pointer'
                }}
              >
                {isVerifyingPayment
                  ? 'Verifying Payment with Bank...'
                  : isPlacingOrder
                  ? 'Connecting to Razorpay...'
                  : selectedPayment === 'cod'
                  ? `Place Order (₹${grandTotalAmount.toLocaleString('en-IN')}.00)`
                  : `Pay with Razorpay (₹${grandTotalAmount.toLocaleString('en-IN')}.00)`}
              </button>

              <button
                type="button"
                className="btn-cancel-checkout-link"
                onClick={handleAttemptAbandon}
                disabled={isPlacingOrder || isVerifyingPayment}
              >
                Cancel and return to shopping bag
              </button>

              {/* Razorpay Trust Seal */}
              <div style={{
                marginTop: '16px',
                paddingTop: '14px',
                borderTop: '1px solid #f3f4f6',
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                gap: '6px',
                textAlign: 'center'
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '0.78rem', color: '#4b5563' }}>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                  <span>256-bit Bank Grade SSL Encryption</span>
                </div>
                <div style={{ fontSize: '0.72rem', color: '#9ca3af' }}>
                  Payments processed securely via Razorpay Gateway
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Address Side Drawer */}
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
                          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                            <strong className="user-name">{addr.user_name || user?.name}</strong>
                            <span style={{ fontSize: '0.7rem', padding: '2px 6px', borderRadius: '4px', background: 'var(--border-color, #f0e6f6)', fontWeight: '600' }}>
                              {(addr.address_type || 'home').toUpperCase()}
                            </span>
                            {addr.is_default === 1 && (
                              <span style={{ fontSize: '0.7rem', padding: '2px 6px', borderRadius: '4px', background: '#dcfce7', color: '#166534', fontWeight: 'bold' }}>
                                DEFAULT
                              </span>
                            )}
                          </div>
                          <p className="full-address">
                            {addr.door_no}, {addr.street}, {addr.area}, {addr.city}{addr.district ? `, ${addr.district}` : ''}, {addr.state} - {addr.pincode}
                          </p>
                          {addr.landmark && (
                            <p style={{ fontSize: '0.8rem', color: '#666', marginTop: '2px' }}>Landmark: {addr.landmark}</p>
                          )}
                        </div>
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                          <button
                            type="button"
                            className="edit-pencil-btn"
                            onClick={(e) => handleEditAddress(addr, e)}
                            title="Edit Address"
                          >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <path d="M12 20h9"></path>
                              <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                            </svg>
                          </button>
                          <button
                            type="button"
                            style={{ background: 'none', border: 'none', color: '#dc2626', cursor: 'pointer', padding: '4px' }}
                            onClick={(e) => handleDeleteAddress(addr.id, e)}
                            title="Delete Address"
                          >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                              <polyline points="3 6 5 6 21 6"></polyline>
                              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                          </button>
                        </div>
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
                {addressError && (
                  <div style={{ padding: '10px 14px', background: '#fee2e2', color: '#991b1b', borderRadius: '6px', fontSize: '0.85rem', marginBottom: '14px' }}>
                    {addressError}
                  </div>
                )}
                <div className="form-group">
                  <label htmlFor="drawerAddressType">Address Type *</label>
                  <select
                    id="drawerAddressType"
                    value={addressForm.address_type}
                    onChange={(e) => setAddressForm({ ...addressForm, address_type: e.target.value })}
                    className="form-select"
                    style={{
                      padding: '10px 14px',
                      border: '1px solid #d1d5db',
                      borderRadius: '6px',
                      fontSize: '0.9rem',
                      background: '#fff',
                      color: '#1f2937',
                      cursor: 'pointer',
                      outline: 'none',
                      width: '100%'
                    }}
                  >
                    <option value="home">Home (Delivery all day)</option>
                    <option value="work">Work (Delivery between 10 AM - 6 PM)</option>
                    <option value="other">Other</option>
                  </select>
                </div>
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
                  <label htmlFor="drawerDistrict">District</label>
                  <input
                    type="text"
                    id="drawerDistrict"
                    value={addressForm.district}
                    onChange={(e) => setAddressForm({ ...addressForm, district: e.target.value })}
                    placeholder="District (Optional)"
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
                <div className="form-group">
                  <label htmlFor="drawerLandmark">Landmark</label>
                  <input
                    type="text"
                    id="drawerLandmark"
                    value={addressForm.landmark}
                    onChange={(e) => setAddressForm({ ...addressForm, landmark: e.target.value })}
                    placeholder="Nearby landmark (Optional)"
                  />
                </div>
                <div className="checkbox-inline-row" style={{ display: 'flex', flexDirection: 'row', alignItems: 'center', gap: '10px', marginTop: '6px', marginBottom: '8px' }}>
                  <input
                    type="checkbox"
                    id="drawerIsDefault"
                    checked={addressForm.is_default === 1}
                    onChange={(e) => setAddressForm({ ...addressForm, is_default: e.target.checked ? 1 : 0 })}
                    style={{ width: '18px', height: '18px', cursor: 'pointer', margin: 0, padding: 0, flexShrink: 0 }}
                  />
                  <label htmlFor="drawerIsDefault" style={{ cursor: 'pointer', margin: 0, fontSize: '0.88rem', fontWeight: '500', color: '#374151', display: 'inline' }}>
                    Set as default delivery address
                  </label>
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

      <CouponDrawer
        isOpen={isCouponDrawerOpen}
        onClose={() => setIsCouponDrawerOpen(false)}
        currentSubtotal={subtotal}
        hasDeliveryAddress={hasDeliveryAddress}
        onRequireAddress={() => {
          setIsCouponDrawerOpen(false);
          setIsAddressDrawerOpen(true);
          setOrderError('Please select delivery address before applying coupon.');
        }}
        appliedCouponCode={appliedCoupon?.code || appliedCoupon?.coupon_code}
        onApplyCoupon={(coupon) => {
          if (!hasDeliveryAddress) {
            setOrderError('Please select delivery address before applying coupon.');
            setIsAddressDrawerOpen(true);
            return;
          }
          applyCouponToCart(coupon);
        }}
      />
    </div>
  );
};

export default Checkout;
