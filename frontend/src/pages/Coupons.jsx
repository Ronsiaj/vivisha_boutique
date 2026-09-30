import React, { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useCoupons } from '../context/CouponContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import { useCart } from '../context/CartContext.jsx';

const Coupons = () => {
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { subtotal } = useCart();
  const {
    birthdayState,
    firstOrderState,
    festivalState,
    isLoading,
    applyCouponToCart,
    fetchAllCoupons
  } = useCoupons();

  const [activeFilter, setActiveFilter] = useState('all');
  const [copiedCode, setCopiedCode] = useState('');
  const [toastMessage, setToastMessage] = useState('');

  useEffect(() => {
    fetchAllCoupons();
  }, [isAuthenticated]);

  const showToast = (msg) => {
    setToastMessage(msg);
    setTimeout(() => setToastMessage(''), 3000);
  };

  const handleCopyCode = (code) => {
    navigator.clipboard.writeText(code);
    setCopiedCode(code);
    showToast(`Coupon code "${code}" copied to clipboard!`);
    setTimeout(() => setCopiedCode(''), 2500);
  };

  const handleClaimAndShop = (coupon, type) => {
    applyCouponToCart({
      ...coupon,
      coupon_type: type,
      code: coupon.coupon_code || coupon.referral_code
    });
    showToast(`Applied "${coupon.coupon_code || coupon.referral_code}" to your bag!`);
    setTimeout(() => {
      navigate('/cart');
    }, 1000);
  };

  // Format reason codes into clear, friendly explanations
  const formatBirthdayReason = (reason) => {
    switch (reason) {
      case 'NOT_BIRTHDAY_MONTH':
        return 'Birthday coupons are unlocked exclusively during your birthday month.';
      case 'BIRTHDAY_COUPON_ALREADY_USED_THIS_YEAR':
        return 'You have already redeemed your birthday gift coupon for this calendar year.';
      case 'DATE_OF_BIRTH_NOT_AVAILABLE':
        return 'Please add your Date of Birth in your Profile to unlock your annual birthday discount.';
      case 'UNAUTHENTICATED':
        return 'Log in to your boutique account to check your birthday eligibility.';
      default:
        return 'Currently not eligible for birthday discount.';
    }
  };

  const formatFirstOrderReason = (reason) => {
    switch (reason) {
      case 'USER_ALREADY_HAS_ORDER':
        return 'This welcome gift is reserved for first-time customers on their first purchase.';
      case 'UNAUTHENTICATED':
        return 'Log in to check if your account is eligible for first-order discounts.';
      default:
        return 'Not eligible for first order coupon.';
    }
  };

  return (
    <div className="coupons-page-container">
      {/* Page Header Bar */}
      <div className="wishlist-header-banner">
        <div className="container">
          <div className="wishlist-breadcrumb">
            <Link to="/">Home</Link> &nbsp;/&nbsp; <span>Coupons & Offers</span>
          </div>
          <div className="wishlist-title-row">
            <h1 className="wishlist-main-heading">Coupons & Special Offers</h1>
          </div>
          <p style={{ color: '#666', marginTop: '8px', fontSize: '0.95rem' }}>
            Exclusive discounts, birthday gifts, first-order welcome offers & festive specials
          </p>
        </div>
      </div>

      {/* Toast Notification */}
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

      <div className="container" style={{ maxWidth: '1100px', margin: '0 auto', padding: '0 1rem 4rem' }}>
        {/* Filter Tabs */}
        <div className="coupons-filter-nav" style={{
          display: 'flex',
          gap: '10px',
          justifyContent: 'center',
          flexWrap: 'wrap',
          marginBottom: '2.5rem'
        }}>
          {[
            { id: 'all', label: 'All Coupons' },
            { id: 'birthday', label: '🎂 Birthday Gifts' },
            { id: 'first_order', label: '🎉 First Order' },
            { id: 'festival', label: '🪔 Festive Offers' },
            { id: 'referral', label: '🤝 Referral Program' }
          ].map((tab) => (
            <button
              key={tab.id}
              onClick={() => setActiveFilter(tab.id)}
              style={{
                padding: '10px 20px',
                borderRadius: '30px',
                border: activeFilter === tab.id ? '2px solid var(--primary-color)' : '1px solid #ddd',
                background: activeFilter === tab.id ? 'var(--primary-color)' : '#fff',
                color: activeFilter === tab.id ? '#fff' : '#444',
                fontWeight: '600',
                fontSize: '0.9rem',
                cursor: 'pointer',
                transition: 'all 0.2s ease'
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {isLoading ? (
          <div style={{ textAlign: 'center', padding: '60px 0' }}>
            <div className="cart-spinner" style={{
              width: '40px',
              height: '40px',
              border: '3px solid #f3e8ff',
              borderTop: '3px solid var(--primary-color)',
              borderRadius: '50%',
              margin: '0 auto 16px',
              animation: 'spin 0.8s linear infinite'
            }}></div>
            <p style={{ color: '#666' }}>Fetching your personalized boutique coupon privileges...</p>
          </div>
        ) : (
          <div className="coupons-grid-wrapper" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '24px' }}>
            
            {/* 1. BIRTHDAY COUPONS */}
            {(activeFilter === 'all' || activeFilter === 'birthday') && (
              birthdayState.eligible && birthdayState.coupons?.length > 0 ? (
                birthdayState.coupons.map((c) => (
                  <div key={c.id || c.coupon_code} className="coupon-presentation-card eligible" style={cardStyle('eligible')}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                      <span style={badgeStyle('green')}>🟢 Eligible (Birthday Month)</span>
                      <span style={{ fontSize: '1.2rem' }}>🎂</span>
                    </div>
                    <h3 style={{ margin: '0 0 6px', fontSize: '1.2rem', color: '#111' }}>{c.title || 'Birthday Special Discount'}</h3>
                    <p style={{ color: '#666', fontSize: '0.9rem', margin: '0 0 14px', lineHeight: '1.4' }}>
                      {c.description || 'Happy Birthday from Vivisha Boutique! Enjoy special discounts on your order.'}
                    </p>
                    <div style={codeBoxStyle}>
                      <span style={{ fontWeight: '700', letterSpacing: '1px', fontSize: '1.05rem', color: 'var(--primary-color)' }}>
                        {c.coupon_code}
                      </span>
                      <button onClick={() => handleCopyCode(c.coupon_code)} style={copyBtnStyle}>
                        {copiedCode === c.coupon_code ? 'Copied ✓' : 'Copy'}
                      </button>
                    </div>
                    <div style={{ fontSize: '0.85rem', color: '#777', margin: '12px 0 16px' }}>
                      <span>Min Order: <strong>₹{Number(c.min_order_amount || 0).toLocaleString('en-IN')}</strong></span>
                      {c.discount?.max_discount_amount && (
                        <span> • Max Disc: <strong>₹{Number(c.discount.max_discount_amount).toLocaleString('en-IN')}</strong></span>
                      )}
                    </div>
                    <button
                      onClick={() => handleClaimAndShop(c, 'birthday')}
                      style={applyBtnStyle}
                    >
                      Apply & Shop Now
                    </button>
                  </div>
                ))
              ) : (
                <div className="coupon-presentation-card ineligible" style={cardStyle('ineligible')}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                    <span style={badgeStyle('red')}>🔴 Not Eligible</span>
                    <span style={{ fontSize: '1.2rem' }}>🎂</span>
                  </div>
                  <h3 style={{ margin: '0 0 6px', fontSize: '1.15rem', color: '#333' }}>Birthday Special Discount</h3>
                  <p style={{ color: '#666', fontSize: '0.88rem', margin: '0 0 16px', lineHeight: '1.4' }}>
                    {formatBirthdayReason(birthdayState.reason)}
                  </p>
                  {birthdayState.reason === 'DATE_OF_BIRTH_NOT_AVAILABLE' && (
                    <Link to="/profile" style={{ color: 'var(--primary-color)', fontWeight: '600', fontSize: '0.9rem', textDecoration: 'underline' }}>
                      Add Date of Birth in Profile →
                    </Link>
                  )}
                </div>
              )
            )}

            {/* 2. FIRST ORDER COUPONS */}
            {(activeFilter === 'all' || activeFilter === 'first_order') && (
              firstOrderState.eligible && firstOrderState.coupons?.length > 0 ? (
                firstOrderState.coupons.map((c) => (
                  <div key={c.id || c.coupon_code} className="coupon-presentation-card eligible" style={cardStyle('eligible')}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                      <span style={badgeStyle('green')}>🟢 Eligible (First Order)</span>
                      <span style={{ fontSize: '1.2rem' }}>🎉</span>
                    </div>
                    <h3 style={{ margin: '0 0 6px', fontSize: '1.2rem', color: '#111' }}>{c.title || 'New User Welcome Gift'}</h3>
                    <p style={{ color: '#666', fontSize: '0.9rem', margin: '0 0 14px', lineHeight: '1.4' }}>
                      {c.description || 'Welcome to Vivisha Boutique! Enjoy flat discounts on your inaugural purchase.'}
                    </p>
                    <div style={codeBoxStyle}>
                      <span style={{ fontWeight: '700', letterSpacing: '1px', fontSize: '1.05rem', color: 'var(--primary-color)' }}>
                        {c.coupon_code}
                      </span>
                      <button onClick={() => handleCopyCode(c.coupon_code)} style={copyBtnStyle}>
                        {copiedCode === c.coupon_code ? 'Copied ✓' : 'Copy'}
                      </button>
                    </div>
                    <div style={{ fontSize: '0.85rem', color: '#777', margin: '12px 0 16px' }}>
                      <span>Min Order: <strong>₹{Number(c.min_order_amount || 0).toLocaleString('en-IN')}</strong></span>
                      {c.max_discount_amount && (
                        <span> • Max Disc: <strong>₹{Number(c.max_discount_amount).toLocaleString('en-IN')}</strong></span>
                      )}
                    </div>
                    <button
                      onClick={() => handleClaimAndShop(c, 'first_order')}
                      style={applyBtnStyle}
                    >
                      Apply & Shop Now
                    </button>
                  </div>
                ))
              ) : (
                <div className="coupon-presentation-card ineligible" style={cardStyle('ineligible')}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                    <span style={badgeStyle('red')}>🔴 Not Eligible</span>
                    <span style={{ fontSize: '1.2rem' }}>🎉</span>
                  </div>
                  <h3 style={{ margin: '0 0 6px', fontSize: '1.15rem', color: '#333' }}>First Order Welcome Offer</h3>
                  <p style={{ color: '#666', fontSize: '0.88rem', margin: '0 0 16px', lineHeight: '1.4' }}>
                    {formatFirstOrderReason(firstOrderState.reason)}
                  </p>
                </div>
              )
            )}

            {/* 3. FESTIVAL COUPONS */}
            {(activeFilter === 'all' || activeFilter === 'festival') && (
              festivalState.coupons?.length > 0 ? (
                festivalState.coupons.map((c) => (
                  <div key={c.id || c.coupon_code} className="coupon-presentation-card eligible" style={cardStyle('eligible')}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                      <span style={badgeStyle('green')}>🟢 Active Festive Offer</span>
                      <span style={{ fontSize: '1.2rem' }}>🪔</span>
                    </div>
                    <h3 style={{ margin: '0 0 6px', fontSize: '1.2rem', color: '#111' }}>{c.festival_name ? `${c.festival_name} - ${c.title}` : c.title}</h3>
                    <p style={{ color: '#666', fontSize: '0.9rem', margin: '0 0 14px', lineHeight: '1.4' }}>
                      {c.description || 'Celebrate festive seasons with exclusive ethnic wear discounts.'}
                    </p>
                    <div style={codeBoxStyle}>
                      <span style={{ fontWeight: '700', letterSpacing: '1px', fontSize: '1.05rem', color: 'var(--primary-color)' }}>
                        {c.coupon_code}
                      </span>
                      <button onClick={() => handleCopyCode(c.coupon_code)} style={copyBtnStyle}>
                        {copiedCode === c.coupon_code ? 'Copied ✓' : 'Copy'}
                      </button>
                    </div>
                    <div style={{ fontSize: '0.85rem', color: '#777', margin: '12px 0 16px' }}>
                      <span>Min Order: <strong>₹{Number(c.min_order_amount || 0).toLocaleString('en-IN')}</strong></span>
                      {c.end_at && (
                        <span> • Valid till: <strong>{new Date(c.end_at).toLocaleDateString()}</strong></span>
                      )}
                    </div>
                    <button
                      onClick={() => handleClaimAndShop(c, 'festival')}
                      style={applyBtnStyle}
                    >
                      Apply & Shop Now
                    </button>
                  </div>
                ))
              ) : (
                <div className="coupon-presentation-card neutral" style={cardStyle('neutral')}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                    <span style={badgeStyle('gray')}>Upcoming</span>
                    <span style={{ fontSize: '1.2rem' }}>🪔</span>
                  </div>
                  <h3 style={{ margin: '0 0 6px', fontSize: '1.15rem', color: '#333' }}>Festive Celebrations</h3>
                  <p style={{ color: '#666', fontSize: '0.88rem', margin: '0', lineHeight: '1.4' }}>
                    Stay tuned! Special festive promotions and discount codes will be announced during major celebrations.
                  </p>
                </div>
              )
            )}

            {/* 4. REFERRAL PROGRAM */}
            {(activeFilter === 'all' || activeFilter === 'referral') && (
              <div className="coupon-presentation-card referral" style={cardStyle('referral')}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
                  <span style={badgeStyle('purple')}>🤝 Referral Rewards</span>
                  <span style={{ fontSize: '1.2rem' }}>🎁</span>
                </div>
                <h3 style={{ margin: '0 0 6px', fontSize: '1.2rem', color: '#111' }}>Friend Referral Code</h3>
                <p style={{ color: '#666', fontSize: '0.9rem', margin: '0 0 14px', lineHeight: '1.4' }}>
                  Have a friend's referral code? Apply it directly at checkout or in your cart for instant discounts on qualified orders.
                </p>
                <div style={{ background: '#f8f4fb', padding: '12px 16px', borderRadius: '8px', border: '1px dashed #d8b4fe', marginBottom: '16px' }}>
                  <p style={{ margin: 0, fontSize: '0.85rem', color: '#581c87' }}>
                    💡 <strong>Tip:</strong> You can enter any referral code into the coupon drawer during checkout!
                  </p>
                </div>
                <Link to="/collections" style={{ ...applyBtnStyle, display: 'block', textAlign: 'center', textDecoration: 'none' }}>
                  Explore Collections
                </Link>
              </div>
            )}

          </div>
        )}
      </div>
    </div>
  );
};

// Styles helper functions
const cardStyle = (type) => ({
  background: '#ffffff',
  borderRadius: '12px',
  border: type === 'eligible' 
    ? '1.5px solid #86efac' 
    : type === 'ineligible' 
      ? '1.5px solid #fecaca' 
      : '1.5px solid #e5e7eb',
  padding: '20px',
  boxShadow: '0 4px 14px rgba(0,0,0,0.04)',
  display: 'flex',
  flexDirection: 'column',
  justifyContent: 'space-between'
});

const badgeStyle = (color) => {
  if (color === 'green') {
    return {
      background: '#f0fdf4',
      color: '#166534',
      border: '1px solid #bbf7d0',
      fontSize: '0.78rem',
      fontWeight: '700',
      padding: '4px 10px',
      borderRadius: '20px'
    };
  }
  if (color === 'red') {
    return {
      background: '#fef2f2',
      color: '#991b1b',
      border: '1px solid #fecaca',
      fontSize: '0.78rem',
      fontWeight: '700',
      padding: '4px 10px',
      borderRadius: '20px'
    };
  }
  if (color === 'purple') {
    return {
      background: '#faf5ff',
      color: '#6b21a8',
      border: '1px solid #e9d5ff',
      fontSize: '0.78rem',
      fontWeight: '700',
      padding: '4px 10px',
      borderRadius: '20px'
    };
  }
  return {
    background: '#f3f4f6',
    color: '#4b5563',
    border: '1px solid #e5e7eb',
    fontSize: '0.78rem',
    fontWeight: '700',
    padding: '4px 10px',
    borderRadius: '20px'
  };
};

const codeBoxStyle = {
  background: '#fcf6fc',
  border: '1.5px dashed var(--primary-color)',
  borderRadius: '8px',
  padding: '8px 14px',
  display: 'flex',
  justifyContent: 'space-between',
  alignItems: 'center'
};

const copyBtnStyle = {
  background: 'transparent',
  border: '1px solid var(--primary-color)',
  color: 'var(--primary-color)',
  fontSize: '0.8rem',
  fontWeight: '600',
  borderRadius: '4px',
  padding: '4px 10px',
  cursor: 'pointer'
};

const applyBtnStyle = {
  width: '100%',
  padding: '10px',
  background: 'var(--primary-color)',
  color: '#ffffff',
  border: 'none',
  borderRadius: '6px',
  fontWeight: '700',
  fontSize: '0.9rem',
  cursor: 'pointer',
  transition: 'background 0.2s ease'
};

export default Coupons;
