import React, { useState } from 'react';
import { useCoupons } from '../context/CouponContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import { Link } from 'react-router-dom';

const CouponDrawer = ({
  isOpen,
  onClose,
  currentSubtotal = 0,
  onApplyCoupon,
  appliedCouponCode = null,
  hasDeliveryAddress = true,
  onRequireAddress = null
}) => {
  const { isAuthenticated } = useAuth();
  const {
    birthdayState,
    firstOrderState,
    festivalState,
    isLoading,
    calculateDiscount,
    fetchAllCoupons
  } = useCoupons();

  const [activeTab, setActiveTab] = useState('all');
  const [manualCode, setManualCode] = useState('');
  const [manualError, setManualError] = useState('');
  const [manualSuccess, setManualSuccess] = useState('');

  if (!isOpen) return null;

  const subtotal = Number(currentSubtotal) || 0;

  // Format reasons into user-friendly messages
  const formatBirthdayReason = (reason) => {
    switch (reason) {
      case 'NOT_BIRTHDAY_MONTH':
        return 'Available only during your birthday month.';
      case 'BIRTHDAY_COUPON_ALREADY_USED_THIS_YEAR':
        return 'You have already used your birthday coupon this year.';
      case 'DATE_OF_BIRTH_NOT_AVAILABLE':
        return 'Please add your Date of Birth in your Profile to claim birthday offers.';
      case 'UNAUTHENTICATED':
        return 'Please log in to check your birthday coupon eligibility.';
      default:
        return 'Not currently eligible for birthday coupon.';
    }
  };

  const formatFirstOrderReason = (reason) => {
    switch (reason) {
      case 'USER_ALREADY_HAS_ORDER':
        return 'Valid only for first-time customers on their first purchase.';
      case 'UNAUTHENTICATED':
        return 'Please log in to check first-order offer eligibility.';
      default:
        return 'Not eligible for first order coupon.';
    }
  };

  const handleApply = (coupon, type) => {
    if (!hasDeliveryAddress) {
      setManualError('Please select delivery address before applying coupon.');
      if (onRequireAddress) onRequireAddress();
      return;
    }

    const minOrder = Number(coupon.min_order_amount || 0);
    if (subtotal > 0 && subtotal < minOrder) {
      setManualError(`Cart subtotal must be at least ₹${minOrder.toLocaleString('en-IN')} to use this coupon.`);
      setTimeout(() => setManualError(''), 4000);
      return;
    }

    const discountAmount = calculateDiscount(coupon, subtotal);
    const couponToApply = {
      ...coupon,
      coupon_type: type,
      code: coupon.coupon_code || coupon.referral_code,
      discount_amount: discountAmount
    };

    if (onApplyCoupon) {
      onApplyCoupon(couponToApply);
    }
    onClose();
  };

  const handleManualApply = (e) => {
    e.preventDefault();
    if (!hasDeliveryAddress) {
      setManualError('Please select delivery address before applying coupon.');
      if (onRequireAddress) onRequireAddress();
      return;
    }

    const code = manualCode.trim().toUpperCase();
    if (!code) {
      setManualError('Please enter a coupon code.');
      return;
    }

    // Search across birthday coupons
    const bCoupon = birthdayState.coupons?.find((c) => c.coupon_code?.toUpperCase() === code);
    if (bCoupon) {
      if (!birthdayState.eligible) {
        setManualError(formatBirthdayReason(birthdayState.reason));
        return;
      }
      handleApply(bCoupon, 'birthday');
      return;
    }

    // Search across first order coupons
    const foCoupon = firstOrderState.coupons?.find((c) => c.coupon_code?.toUpperCase() === code);
    if (foCoupon) {
      if (!firstOrderState.eligible) {
        setManualError(formatFirstOrderReason(firstOrderState.reason));
        return;
      }
      handleApply(foCoupon, 'first_order');
      return;
    }

    // Search across festival coupons
    const festCoupon = festivalState.coupons?.find((c) => c.coupon_code?.toUpperCase() === code);
    if (festCoupon) {
      handleApply(festCoupon, 'festival');
      return;
    }

    // If not found in loaded lists, assume referral code or custom code
    if (/^[A-Z0-9_-]+$/.test(code)) {
      const referralCoupon = {
        coupon_code: code,
        referral_code: code,
        coupon_type: 'referral',
        title: 'Referral Coupon',
        description: `Referral code ${code}`,
        discount_type: 'flat',
        discount_value: '100.00',
        min_order_amount: '500.00'
      };
      handleApply(referralCoupon, 'referral');
      return;
    }

    setManualError('Invalid coupon code.');
  };

  return (
    <div className="coupon-drawer-backdrop" onClick={onClose}>
      <div className="coupon-drawer" onClick={(e) => e.stopPropagation()}>
        {/* Drawer Header */}
        <div className="coupon-drawer-header">
          <div className="coupon-header-title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--primary-color)" strokeWidth="2">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
              <line x1="7" y1="7" x2="7.01" y2="7"></line>
            </svg>
            <h3>Coupons & Offers</h3>
          </div>
          <button className="coupon-drawer-close-btn" onClick={onClose}>✕</button>
        </div>

        {!hasDeliveryAddress && (
          <div style={{
            background: '#fff7ed',
            border: '1px solid #ffedd5',
            borderRadius: '8px',
            padding: '10px 14px',
            margin: '1rem 1.5rem 0.5rem 1.5rem',
            fontSize: '0.85rem',
            color: '#c2410c',
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
            fontWeight: '600'
          }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span>Please select delivery address before applying coupon.</span>
          </div>
        )}

        {/* Manual Coupon Input Form */}
        <div className="coupon-drawer-input-box">
          <form onSubmit={handleManualApply} className="coupon-form-row">
            <input
              type="text"
              placeholder="Enter coupon or referral code"
              value={manualCode}
              onChange={(e) => {
                setManualCode(e.target.value.toUpperCase());
                setManualError('');
              }}
              className="coupon-drawer-input"
            />
            <button type="submit" className="btn-coupon-apply-action">
              Apply
            </button>
          </form>
          {manualError && (
            <p className="coupon-drawer-alert error">{manualError}</p>
          )}
          {manualSuccess && (
            <p className="coupon-drawer-alert success">{manualSuccess}</p>
          )}
        </div>

        {/* Category Tabs */}
        <div className="coupon-tabs-bar">
          <button
            className={`coupon-tab-btn ${activeTab === 'all' ? 'active' : ''}`}
            onClick={() => setActiveTab('all')}
          >
            All Offers
          </button>
          <button
            className={`coupon-tab-btn ${activeTab === 'birthday' ? 'active' : ''}`}
            onClick={() => setActiveTab('birthday')}
          >
            🎂 Birthday
          </button>
          <button
            className={`coupon-tab-btn ${activeTab === 'first_order' ? 'active' : ''}`}
            onClick={() => setActiveTab('first_order')}
          >
            🎉 First Order
          </button>
          <button
            className={`coupon-tab-btn ${activeTab === 'festival' ? 'active' : ''}`}
            onClick={() => setActiveTab('festival')}
          >
            🪔 Festive
          </button>
        </div>

        {/* Drawer Body with Coupons List */}
        <div className="coupon-drawer-body">
          {isLoading ? (
            <div className="coupon-loading-state">
              <div className="cart-spinner" style={{ width: '32px', height: '32px', margin: '20px auto' }}></div>
              <p>Checking available coupons & eligibility...</p>
            </div>
          ) : !isAuthenticated ? (
            <div className="coupon-auth-prompt">
              <p>Please log in to view and claim personalized birthday & first-order discounts.</p>
              <Link to="/login" className="btn-primary-purple" onClick={onClose}>
                Log In to Claim Offers
              </Link>
            </div>
          ) : (
            <div className="coupon-cards-list">
              {/* 1. BIRTHDAY COUPON SECTION */}
              {(activeTab === 'all' || activeTab === 'birthday') && (
                <div className="coupon-type-group">
                  <div className="coupon-group-title">
                    <span>🎂 Birthday Specials</span>
                  </div>

                  {birthdayState.eligible && birthdayState.coupons?.length > 0 ? (
                    birthdayState.coupons.map((c) => {
                      const isApplied = appliedCouponCode === c.coupon_code;
                      const minOrder = Number(c.min_order_amount || 0);
                      const isOrderEligible = subtotal === 0 || subtotal >= minOrder;

                      return (
                        <div key={c.id || c.coupon_code} className="coupon-card eligible">
                          <div className="coupon-card-header">
                            <span className="coupon-badge green">🟢 Available to Use</span>
                            <span className="coupon-code-pill">{c.coupon_code}</span>
                          </div>
                          <h4 className="coupon-title">{c.title || 'Birthday Special Discount'}</h4>
                          {c.description && <p className="coupon-desc">{c.description}</p>}
                          <div className="coupon-terms">
                            <span>Min Order: ₹{minOrder.toLocaleString('en-IN')}</span>
                            {c.discount?.max_discount_amount && (
                              <span>• Max Discount: ₹{Number(c.discount.max_discount_amount).toLocaleString('en-IN')}</span>
                            )}
                          </div>
                          <div className="coupon-card-footer">
                            <span className="coupon-discount-text">
                              {c.discount?.type === 'percentage'
                                ? `${c.discount.value}% OFF`
                                : `₹${c.discount?.value} OFF`}
                            </span>
                            <button
                              type="button"
                              className={`btn-apply-coupon-card ${isApplied ? 'applied' : ''}`}
                              onClick={() => handleApply(c, 'birthday')}
                              disabled={isApplied || !isOrderEligible}
                            >
                              {isApplied ? 'Applied ✓' : !isOrderEligible ? `Add ₹${(minOrder - subtotal).toLocaleString('en-IN')} more` : 'Apply'}
                            </button>
                          </div>
                        </div>
                      );
                    })
                  ) : (
                    <div className="coupon-card ineligible">
                      <div className="coupon-card-header">
                        <span className="coupon-badge red">🔴 Not Eligible</span>
                      </div>
                      <h4 className="coupon-title">Birthday Month Discount</h4>
                      <p className="coupon-desc">{formatBirthdayReason(birthdayState.reason)}</p>
                      {birthdayState.reason === 'DATE_OF_BIRTH_NOT_AVAILABLE' && (
                        <Link to="/profile" className="coupon-inline-link" onClick={onClose}>
                          Set Birthday in Profile →
                        </Link>
                      )}
                    </div>
                  )}
                </div>
              )}

              {/* 2. FIRST ORDER COUPON SECTION */}
              {(activeTab === 'all' || activeTab === 'first_order') && (
                <div className="coupon-type-group">
                  <div className="coupon-group-title">
                    <span>🎉 First Order Welcome Offer</span>
                  </div>

                  {firstOrderState.eligible && firstOrderState.coupons?.length > 0 ? (
                    firstOrderState.coupons.map((c) => {
                      const isApplied = appliedCouponCode === c.coupon_code;
                      const minOrder = Number(c.min_order_amount || 0);
                      const isOrderEligible = subtotal === 0 || subtotal >= minOrder;

                      return (
                        <div key={c.id || c.coupon_code} className="coupon-card eligible">
                          <div className="coupon-card-header">
                            <span className="coupon-badge green">🟢 Eligible for First Order</span>
                            <span className="coupon-code-pill">{c.coupon_code}</span>
                          </div>
                          <h4 className="coupon-title">{c.title || 'Welcome Discount'}</h4>
                          {c.description && <p className="coupon-desc">{c.description}</p>}
                          <div className="coupon-terms">
                            <span>Min Order: ₹{minOrder.toLocaleString('en-IN')}</span>
                            {c.max_discount_amount && (
                              <span>• Max Discount: ₹{Number(c.max_discount_amount).toLocaleString('en-IN')}</span>
                            )}
                          </div>
                          <div className="coupon-card-footer">
                            <span className="coupon-discount-text">
                              {c.discount_type === 'percentage'
                                ? `${c.discount_value}% OFF`
                                : `₹${c.discount_value} OFF`}
                            </span>
                            <button
                              type="button"
                              className={`btn-apply-coupon-card ${isApplied ? 'applied' : ''}`}
                              onClick={() => handleApply(c, 'first_order')}
                              disabled={isApplied || !isOrderEligible}
                            >
                              {isApplied ? 'Applied ✓' : !isOrderEligible ? `Add ₹${(minOrder - subtotal).toLocaleString('en-IN')} more` : 'Apply'}
                            </button>
                          </div>
                        </div>
                      );
                    })
                  ) : (
                    <div className="coupon-card ineligible">
                      <div className="coupon-card-header">
                        <span className="coupon-badge red">🔴 Not Eligible</span>
                      </div>
                      <h4 className="coupon-title">New User Welcome Offer</h4>
                      <p className="coupon-desc">{formatFirstOrderReason(firstOrderState.reason)}</p>
                    </div>
                  )}
                </div>
              )}

              {/* 3. FESTIVAL COUPONS SECTION */}
              {(activeTab === 'all' || activeTab === 'festival') && (
                <div className="coupon-type-group">
                  <div className="coupon-group-title">
                    <span>🪔 Festive & Seasonal Offers</span>
                  </div>

                  {festivalState.coupons?.length > 0 ? (
                    festivalState.coupons.map((c) => {
                      const isApplied = appliedCouponCode === c.coupon_code;
                      const minOrder = Number(c.min_order_amount || 0);
                      const isOrderEligible = subtotal === 0 || subtotal >= minOrder;

                      return (
                        <div key={c.id || c.coupon_code} className="coupon-card eligible">
                          <div className="coupon-card-header">
                            <span className="coupon-badge green">🟢 Active Festival Offer</span>
                            <span className="coupon-code-pill">{c.coupon_code}</span>
                          </div>
                          <h4 className="coupon-title">{c.festival_name ? `${c.festival_name} - ${c.title}` : c.title}</h4>
                          {c.description && <p className="coupon-desc">{c.description}</p>}
                          <div className="coupon-terms">
                            <span>Min Order: ₹{minOrder.toLocaleString('en-IN')}</span>
                            {c.end_at && (
                              <span>• Valid till {new Date(c.end_at).toLocaleDateString()}</span>
                            )}
                          </div>
                          <div className="coupon-card-footer">
                            <span className="coupon-discount-text">
                              {c.discount_type === 'percentage'
                                ? `${c.discount_value}% OFF`
                                : `₹${c.discount_value} OFF`}
                            </span>
                            <button
                              type="button"
                              className={`btn-apply-coupon-card ${isApplied ? 'applied' : ''}`}
                              onClick={() => handleApply(c, 'festival')}
                              disabled={isApplied || !isOrderEligible}
                            >
                              {isApplied ? 'Applied ✓' : !isOrderEligible ? `Add ₹${(minOrder - subtotal).toLocaleString('en-IN')} more` : 'Apply'}
                            </button>
                          </div>
                        </div>
                      );
                    })
                  ) : (
                    <div className="coupon-card neutral">
                      <h4 className="coupon-title">No Active Festival Offers</h4>
                      <p className="coupon-desc">Stay tuned! Special festive promotions will appear here during festive seasons.</p>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CouponDrawer;
