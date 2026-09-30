import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const CouponContext = createContext();

export const CouponProvider = ({ children }) => {
  const { isAuthenticated, token } = useAuth();

  const [birthdayState, setBirthdayState] = useState({
    eligible: false,
    reason: null,
    date_of_birth: null,
    birthday: null,
    coupons: [],
    loaded: false
  });

  const [firstOrderState, setFirstOrderState] = useState({
    eligible: false,
    reason: null,
    order_count: null,
    coupons: [],
    loaded: false
  });

  const [festivalState, setFestivalState] = useState({
    coupons: [],
    loaded: false
  });

  const [isLoading, setIsLoading] = useState(false);
  const [appliedCoupon, setAppliedCoupon] = useState(() => {
    try {
      const saved = sessionStorage.getItem('vivisha_applied_coupon');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchAllCoupons();
    } else {
      setBirthdayState({ eligible: false, reason: 'UNAUTHENTICATED', date_of_birth: null, birthday: null, coupons: [], loaded: false });
      setFirstOrderState({ eligible: false, reason: 'UNAUTHENTICATED', order_count: null, coupons: [], loaded: false });
      setFestivalState({ coupons: [], loaded: false });
    }
  }, [isAuthenticated, token]);

  const fetchBirthdayCoupons = async () => {
    if (!token) return null;
    try {
      const response = await fetch(`${API_BASE_URL}/coupons/birthday_user/display_coupon.php?limit=100`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status && data.data) {
        const state = {
          eligible: Boolean(data.data.eligible),
          reason: data.data.reason || null,
          date_of_birth: data.data.date_of_birth || null,
          birthday: data.data.birthday || null,
          coupons: Array.isArray(data.data.coupons) ? data.data.coupons : [],
          loaded: true
        };
        setBirthdayState(state);
        return state;
      }
    } catch (err) {
      console.error('Error fetching birthday coupons:', err);
    }
    return null;
  };

  const fetchFirstOrderCoupons = async () => {
    if (!token) return null;
    try {
      const response = await fetch(`${API_BASE_URL}/coupons/first_order_user/display_coupon.php?limit=100`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status && data.data) {
        const state = {
          eligible: Boolean(data.data.eligible),
          reason: data.data.reason || null,
          order_count: data.data.order_count ?? null,
          coupons: Array.isArray(data.data.coupons) ? data.data.coupons : [],
          loaded: true
        };
        setFirstOrderState(state);
        return state;
      }
    } catch (err) {
      console.error('Error fetching first order coupons:', err);
    }
    return null;
  };

  const fetchFestivalCoupons = async () => {
    if (!token) return null;
    try {
      const response = await fetch(`${API_BASE_URL}/coupons/festival_user/display_coupon.php?limit=100`, {
        method: 'GET',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      const data = await response.json();
      if (data.status && data.data) {
        const state = {
          coupons: Array.isArray(data.data.coupons) ? data.data.coupons : [],
          loaded: true
        };
        setFestivalState(state);
        return state;
      }
    } catch (err) {
      console.error('Error fetching festival coupons:', err);
    }
    return null;
  };

  const fetchAllCoupons = async () => {
    if (!token) return;
    setIsLoading(true);
    try {
      await Promise.allSettled([
        fetchBirthdayCoupons(),
        fetchFirstOrderCoupons(),
        fetchFestivalCoupons()
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  const applyCouponToCart = (coupon) => {
    if (!coupon) {
      removeAppliedCoupon();
      return;
    }
    const enrichedCoupon = {
      ...coupon,
      applied: true,
      is_applied: true,
      eligible: true
    };
    setAppliedCoupon(enrichedCoupon);
    try {
      sessionStorage.setItem('vivisha_applied_coupon', JSON.stringify(enrichedCoupon));
    } catch (err) {
      console.error('Failed to save applied coupon:', err);
    }
  };

  const removeAppliedCoupon = () => {
    setAppliedCoupon(null);
    try {
      sessionStorage.removeItem('vivisha_applied_coupon');
    } catch (err) {
      console.error('Failed to remove applied coupon:', err);
    }
  };

  const applyCouponToOrder = async (arg1, arg2, arg3) => {
    if (!token) {
      return { success: false, message: 'Please log in to apply coupons.' };
    }

    let couponType;
    let orderId;
    let couponIdOrCode;

    // Support both (orderId, couponIdOrCode, couponType) and (couponType, orderId, couponIdOrCode)
    if (typeof arg1 === 'number' || (typeof arg1 === 'string' && /^\d+$/.test(arg1))) {
      orderId = Number(arg1);
      couponIdOrCode = arg2;
      couponType = arg3;
    } else {
      couponType = arg1;
      orderId = Number(arg2);
      couponIdOrCode = arg3;
    }

    let endpoint = '';
    let payload = { order_id: orderId };
    const normalizedType = String(couponType || '').toLowerCase().trim();

    if (normalizedType === 'birthday') {
      endpoint = `${API_BASE_URL}/coupons/birthday_user/add_coupon.php`;
      payload.coupon_id = Number(couponIdOrCode);
    } else if (normalizedType === 'first_order' || normalizedType === 'firstorder') {
      endpoint = `${API_BASE_URL}/coupons/first_order_user/add_coupon.php`;
      payload.coupon_id = Number(couponIdOrCode);
    } else if (normalizedType === 'festival') {
      endpoint = `${API_BASE_URL}/coupons/festival_user/add_coupon.php`;
      payload.coupon_id = Number(couponIdOrCode);
    } else if (normalizedType === 'referral') {
      endpoint = `${API_BASE_URL}/coupons/referral_user/add_coupon.php`;
      payload.referral_code = String(couponIdOrCode).toUpperCase().trim();
    } else {
      return { success: false, message: 'Invalid coupon type specified.' };
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(payload)
      });
      const data = await response.json();
      if (data.status) {
        return {
          success: true,
          message: data.message || 'Coupon applied successfully!',
          data: data.data
        };
      } else {
        const isAlreadyApplied = Boolean(
          data.message?.toLowerCase().includes('already been applied') ||
          data.message?.toLowerCase().includes('already applied') ||
          (response.status === 409 && data.data?.coupon_code)
        );
        return {
          success: isAlreadyApplied,
          already_applied: isAlreadyApplied,
          message: data.message || 'Failed to apply coupon.',
          data: data.data
        };
      }
    } catch (err) {
      console.error('Error applying coupon to order:', err);
      return { success: false, message: 'Network error applying coupon to order.' };
    }
  };

  // Helper to calculate estimated coupon discount for a given subtotal
  const calculateDiscount = (coupon, currentSubtotal) => {
    if (!coupon || !currentSubtotal) return 0;
    const subtotalNum = Number(currentSubtotal) || 0;
    const minOrder = Number(coupon.min_order_amount || 0);

    if (subtotalNum < minOrder) return 0;

    const discountType = coupon.discount_type || coupon.discount?.type;
    const discountVal = Number(coupon.discount_value || coupon.discount?.value || 0);
    const maxDiscount = Number(coupon.max_discount_amount || coupon.discount?.max_discount_amount || 0);

    let calculated = 0;
    if (discountType === 'percentage') {
      calculated = (subtotalNum * discountVal) / 100;
      if (maxDiscount > 0) {
        calculated = Math.min(calculated, maxDiscount);
      }
    } else if (discountType === 'flat') {
      calculated = Math.min(discountVal, subtotalNum);
    } else if (discountType === 'free_shipping') {
      calculated = 0; // Free delivery
    }

    return Math.round(calculated * 100) / 100;
  };

  return (
    <CouponContext.Provider
      value={{
        birthdayState,
        firstOrderState,
        festivalState,
        isLoading,
        appliedCoupon,
        applyCouponToCart,
        removeAppliedCoupon,
        applyCouponToOrder,
        calculateDiscount,
        fetchAllCoupons,
        fetchBirthdayCoupons,
        fetchFirstOrderCoupons,
        fetchFestivalCoupons
      }}
    >
      {children}
    </CouponContext.Provider>
  );
};

export const useCoupons = () => {
  const context = useContext(CouponContext);
  if (!context) {
    throw new Error('useCoupons must be used within a CouponProvider');
  }
  return context;
};
