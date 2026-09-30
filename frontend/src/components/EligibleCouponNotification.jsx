import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';
import { useCoupons } from '../context/CouponContext.jsx';

const EligibleCouponNotification = () => {
  const { user, isAuthenticated } = useAuth();
  const { birthdayState, firstOrderState, festivalState } = useCoupons();
  const navigate = useNavigate();

  const [visibleCoupon, setVisibleCoupon] = useState(null);
  const [totalEligibleCount, setTotalEligibleCount] = useState(0);
  const [isOpen, setIsOpen] = useState(false);
  const [isCopied, setIsCopied] = useState(false);

  useEffect(() => {
    // Only proceed for authenticated logged-in users with a valid user id
    if (!isAuthenticated || !user?.id) {
      setIsOpen(false);
      setVisibleCoupon(null);
      return;
    }

    // Check if notification has already been shown in this browser session
    const sessionKey = `vivisha_coupon_notified_${user.id}`;
    if (sessionStorage.getItem(sessionKey)) {
      return;
    }

    // Wait until coupon data has been fetched from the backend
    if (!birthdayState.loaded || !firstOrderState.loaded || !festivalState.loaded) {
      return;
    }

    const eligibleList = [];

    // 1. Birthday Coupons (confirmed by backend eligible: true)
    if (birthdayState.eligible && Array.isArray(birthdayState.coupons) && birthdayState.coupons.length > 0) {
      birthdayState.coupons.forEach((c) => {
        eligibleList.push({
          ...c,
          categoryType: 'birthday',
          categoryLabel: '🎂 Birthday Gift Offer',
          priority: 1
        });
      });
    }

    // 2. First Order Coupons (confirmed by backend eligible: true)
    if (firstOrderState.eligible && Array.isArray(firstOrderState.coupons) && firstOrderState.coupons.length > 0) {
      firstOrderState.coupons.forEach((c) => {
        eligibleList.push({
          ...c,
          categoryType: 'first_order',
          categoryLabel: '✨ Welcome Offer (First Order)',
          priority: 2
        });
      });
    }

    // 3. Active Festival / Special Coupons (backend already filters active date and user_remaining > 0)
    if (Array.isArray(festivalState.coupons) && festivalState.coupons.length > 0) {
      festivalState.coupons.forEach((c) => {
        eligibleList.push({
          ...c,
          categoryType: 'festival',
          categoryLabel: c.festival_name ? `🎉 ${c.festival_name} Special` : '🎉 Festive Special Offer',
          priority: 3
        });
      });
    }

    if (eligibleList.length > 0) {
      // Sort by priority (Birthday first, then First Order, then Festival)
      eligibleList.sort((a, b) => a.priority - b.priority);

      const topCoupon = eligibleList[0];
      setVisibleCoupon(topCoupon);
      setTotalEligibleCount(eligibleList.length);
      setIsOpen(true);

      // Mark session as notified so it does not pop up again on page transitions
      try {
        sessionStorage.setItem(sessionKey, 'true');
      } catch (e) {
        console.warn('Could not set session storage for coupon notification:', e);
      }
    }
  }, [
    isAuthenticated,
    user?.id,
    birthdayState.loaded,
    birthdayState.eligible,
    birthdayState.coupons,
    firstOrderState.loaded,
    firstOrderState.eligible,
    firstOrderState.coupons,
    festivalState.loaded,
    festivalState.coupons
  ]);

  if (!isOpen || !visibleCoupon) {
    return null;
  }

  const handleCopyCode = (e) => {
    e.stopPropagation();
    const code = visibleCoupon.coupon_code || visibleCoupon.code || '';
    if (code) {
      navigator.clipboard.writeText(code);
      setIsCopied(true);
      setTimeout(() => setIsCopied(false), 2500);
    }
  };

  const handleDismiss = (e) => {
    e.stopPropagation();
    setIsOpen(false);
  };

  const handleViewOffers = () => {
    setIsOpen(false);
    navigate('/coupons');
  };

  // Helper to format discount representation
  const formatDiscountText = () => {
    const type = visibleCoupon.discount?.type || visibleCoupon.discount_type;
    const value = visibleCoupon.discount?.value ?? visibleCoupon.discount_value;
    const maxDiscount = Number(visibleCoupon.discount?.max_discount_amount || visibleCoupon.max_discount_amount || 0);

    if (type === 'percentage') {
      return `${value}% OFF${maxDiscount > 0 ? ` (Up to ₹${maxDiscount.toLocaleString('en-IN')})` : ''}`;
    }
    if (type === 'flat') {
      return `Flat ₹${Number(value).toLocaleString('en-IN')} OFF`;
    }
    if (type === 'free_shipping') {
      return 'Free Shipping';
    }
    return 'Special Discount';
  };

  const minOrderAmount = Number(visibleCoupon.min_order_amount || 0);
  const couponCode = visibleCoupon.coupon_code || visibleCoupon.code || '';

  return (
    <aside
      className="eligible-coupon-toast-container"
      aria-live="polite"
      role="status"
      style={{
        position: 'fixed',
        bottom: '88px',
        right: '24px',
        zIndex: 9995,
        maxWidth: '380px',
        width: 'calc(100vw - 32px)',
        backgroundColor: '#1c1917',
        color: '#ffffff',
        borderRadius: '16px',
        boxShadow: '0 20px 35px -8px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(212, 175, 55, 0.35)',
        border: '1.5px solid #d4af37',
        padding: '16px 18px',
        fontFamily: 'inherit',
        animation: 'couponToastSlideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1)',
        overflow: 'hidden'
      }}
    >
      {/* Decorative subtle gold glow overlay */}
      <div
        style={{
          position: 'absolute',
          top: '-40px',
          right: '-40px',
          width: '100px',
          height: '100px',
          background: 'radial-gradient(circle, rgba(212, 175, 55, 0.22) 0%, rgba(212, 175, 55, 0) 70%)',
          borderRadius: '50%',
          pointerEvents: 'none'
        }}
      />

      {/* Header Row: Category Badge & Close Button */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginBottom: '8px',
          gap: '8px'
        }}
      >
        <span
          style={{
            fontSize: '0.75rem',
            fontWeight: '700',
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
            color: '#d4af37',
            background: 'rgba(212, 175, 55, 0.12)',
            padding: '3px 9px',
            borderRadius: '20px',
            border: '1px solid rgba(212, 175, 55, 0.25)',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '4px'
          }}
        >
          {visibleCoupon.categoryLabel || '🎁 Eligible Offer'}
        </span>

        <button
          onClick={handleDismiss}
          aria-label="Dismiss coupon notification"
          style={{
            background: 'transparent',
            border: 'none',
            color: '#a8a29e',
            cursor: 'pointer',
            padding: '4px',
            lineHeight: 1,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            borderRadius: '50%',
            transition: 'color 0.2s ease, background-color 0.2s ease'
          }}
          onMouseEnter={(e) => {
            e.currentTarget.style.color = '#ffffff';
            e.currentTarget.style.backgroundColor = 'rgba(255,255,255,0.1)';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.color = '#a8a29e';
            e.currentTarget.style.backgroundColor = 'transparent';
          }}
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>

      {/* Offer Summary Headline */}
      <div style={{ marginBottom: '10px' }}>
        <h4
          style={{
            margin: '0 0 4px 0',
            fontSize: '1rem',
            fontWeight: '700',
            color: '#f5f5f4',
            letterSpacing: '-0.2px'
          }}
        >
          {formatDiscountText()}
        </h4>
        <p
          style={{
            margin: 0,
            fontSize: '0.82rem',
            color: '#d6d3d1',
            lineHeight: 1.35
          }}
        >
          {visibleCoupon.title || 'Available for your next order.'}
          {minOrderAmount > 0 && ` on orders above ₹${minOrderAmount.toLocaleString('en-IN')}`}
        </p>
      </div>

      {/* Coupon Code Pill & Copy Button */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          background: 'rgba(255, 255, 255, 0.06)',
          border: '1px dashed #d4af37',
          borderRadius: '10px',
          padding: '7px 12px',
          marginBottom: '12px'
        }}
      >
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <span style={{ fontSize: '0.68rem', color: '#a8a29e', textTransform: 'uppercase', letterSpacing: '0.5px' }}>
            Coupon Code
          </span>
          <span style={{ fontSize: '0.92rem', fontWeight: '800', color: '#fef08a', letterSpacing: '1px' }}>
            {couponCode}
          </span>
        </div>

        <button
          onClick={handleCopyCode}
          style={{
            background: isCopied ? '#15803d' : '#d4af37',
            color: isCopied ? '#ffffff' : '#1c1917',
            border: 'none',
            borderRadius: '6px',
            padding: '6px 12px',
            fontSize: '0.78rem',
            fontWeight: '700',
            cursor: 'pointer',
            transition: 'all 0.2s ease',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '4px'
          }}
        >
          {isCopied ? (
            <>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
              Copied!
            </>
          ) : (
            <>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
              </svg>
              Copy
            </>
          )}
        </button>
      </div>

      {/* Footer Actions: Explore all coupons & View offers */}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          paddingTop: '4px',
          fontSize: '0.78rem'
        }}
      >
        <span style={{ color: '#a8a29e' }}>
          {totalEligibleCount > 1 ? `+${totalEligibleCount - 1} more coupon available` : 'Ready to use at checkout'}
        </span>

        <button
          onClick={handleViewOffers}
          style={{
            background: 'transparent',
            border: 'none',
            color: '#fbbf24',
            fontWeight: '700',
            cursor: 'pointer',
            padding: 0,
            textDecoration: 'underline',
            textUnderlineOffset: '3px',
            display: 'inline-flex',
            alignItems: 'center',
            gap: '3px'
          }}
        >
          View All Offers &rarr;
        </button>
      </div>

      {/* Animation keyframe */}
      <style>{`
        @keyframes couponToastSlideUp {
          0% {
            opacity: 0;
            transform: translateY(20px) scale(0.97);
          }
          100% {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }
        @media (max-width: 768px) {
          .eligible-coupon-toast-container {
            bottom: 74px !important;
            right: 12px !important;
            left: 12px !important;
            width: auto !important;
            max-width: none !important;
          }
        }
      `}</style>
    </aside>
  );
};

export default EligibleCouponNotification;
