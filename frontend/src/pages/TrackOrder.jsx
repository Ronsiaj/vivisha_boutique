import React, { useState, useEffect } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import PageHero from '../components/PageHero.jsx';
import { validateTrackingId, resolveTrackingDestination } from '../utils/orderTracking.js';

const TrackOrder = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const navigate = useNavigate();

  // URL query parameter support: ?id=... or ?tracking_id=...
  const queryTrackingId = searchParams.get('id') || searchParams.get('tracking_id') || '';

  const [trackingInput, setTrackingInput] = useState(queryTrackingId);
  const [errorMessage, setErrorMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [copied, setCopied] = useState(false);

  // Sync state if URL query changes (e.g., user presses browser back/forward)
  useEffect(() => {
    setTrackingInput(queryTrackingId);
    if (!queryTrackingId) {
      setErrorMessage('');
    }
  }, [queryTrackingId]);

  // Handle tracking form submission
  const handleTrackSubmit = (e) => {
    e.preventDefault();
    setErrorMessage('');

    const validation = validateTrackingId(trackingInput);
    if (!validation.isValid) {
      setErrorMessage(validation.error);
      return;
    }

    setIsSubmitting(true);

    // Simulate navigation step to temporary destination
    setTimeout(() => {
      setIsSubmitting(false);
      // Navigate by updating query parameter so that browser history, refresh, and back work naturally
      navigate(`/track-order?id=${encodeURIComponent(validation.trackingId)}`);
    }, 350);
  };

  // Clear query and return to input view
  const handleTrackAnother = () => {
    setErrorMessage('');
    setTrackingInput('');
    navigate('/track-order');
  };

  // Copy tracking ID helper
  const handleCopyId = (id) => {
    navigator.clipboard?.writeText(id).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  };

  // Check if current view is the temporary destination view
  const currentDestination = queryTrackingId ? resolveTrackingDestination(queryTrackingId) : null;
  const isDestinationView = currentDestination && currentDestination.isValid;

  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="Order Tracking"
        breadcrumb="Order Tracking"
        description="Track your shipment journey using the unique tracking ID shared by our team on WhatsApp."
      />

      <div className="boutique-page-container track-order-container" style={{ padding: '0 16px 60px' }}>
        <div style={{ maxWidth: '680px', margin: '0 auto' }}>

          {/* ========================================================= */}
          {/* VIEW A: TEMPORARY SIMULATION PLACEHOLDER DESTINATION       */}
          {/* ========================================================= */}
          {isDestinationView ? (
            <div
              className="tracking-destination-card"
              style={{
                background: '#ffffff',
                borderRadius: '16px',
                border: '1px solid #e9d5eb',
                boxShadow: '0 8px 30px rgba(160, 73, 163, 0.08)',
                overflow: 'hidden',
                transition: 'all 0.3s ease'
              }}
            >
              {/* Header Banner */}
              <div
                style={{
                  background: 'linear-gradient(135deg, #A049A3 0%, #C86395 100%)',
                  padding: '24px 20px',
                  color: '#ffffff',
                  textAlign: 'center'
                }}
              >
                <div
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    width: '48px',
                    height: '48px',
                    borderRadius: '50%',
                    background: 'rgba(255, 255, 255, 0.2)',
                    marginBottom: '10px'
                  }}
                >
                  <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ffffff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                  </svg>
                </div>
                <h2 style={{ margin: '0 0 6px 0', fontSize: '1.4rem', color: '#ffffff', fontWeight: '600' }}>
                  Order Tracking Flow Reached
                </h2>
                <span
                  style={{
                    display: 'inline-block',
                    background: 'rgba(255, 255, 255, 0.25)',
                    padding: '4px 12px',
                    borderRadius: '20px',
                    fontSize: '0.78rem',
                    letterSpacing: '0.5px',
                    fontWeight: '600',
                    textTransform: 'uppercase'
                  }}
                >
                  Demonstration Destination
                </span>
              </div>

              {/* Main Card Body */}
              <div style={{ padding: '28px 24px' }}>
                {/* Tracking ID Badge Display */}
                <div
                  style={{
                    background: '#FDF7FD',
                    border: '1px dashed #A049A3',
                    borderRadius: '12px',
                    padding: '16px 20px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    flexWrap: 'wrap',
                    gap: '12px',
                    marginBottom: '24px'
                  }}
                >
                  <div>
                    <span style={{ fontSize: '0.75rem', textTransform: 'uppercase', color: '#6b4d6d', letterSpacing: '0.5px', fontWeight: '700', display: 'block' }}>
                      Verified Tracking ID
                    </span>
                    <strong style={{ fontSize: '1.25rem', color: 'var(--primary-color, #A049A3)', letterSpacing: '1px', wordBreak: 'break-all' }}>
                      {currentDestination.trackingId}
                    </strong>
                  </div>

                  <button
                    type="button"
                    onClick={() => handleCopyId(currentDestination.trackingId)}
                    style={{
                      background: copied ? '#10b981' : '#ffffff',
                      color: copied ? '#ffffff' : 'var(--primary-color, #A049A3)',
                      border: '1px solid #A049A3',
                      borderRadius: '8px',
                      padding: '8px 14px',
                      fontSize: '0.8rem',
                      fontWeight: '600',
                      cursor: 'pointer',
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '6px',
                      transition: 'all 0.2s ease'
                    }}
                    title="Copy tracking ID"
                  >
                    {copied ? (
                      <>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5">
                          <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Copied!</span>
                      </>
                    ) : (
                      <>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span>Copy ID</span>
                      </>
                    )}
                  </button>
                </div>

                {/* Third-Party Courier Integration Placeholder Notice */}
                <div
                  style={{
                    background: '#f8fafc',
                    border: '1px solid #e2e8f0',
                    borderRadius: '12px',
                    padding: '20px',
                    marginBottom: '28px'
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'flex-start', gap: '12px' }}>
                    <div
                      style={{
                        width: '36px',
                        height: '36px',
                        borderRadius: '8px',
                        background: '#e0f2fe',
                        color: '#0284c7',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0
                      }}
                    >
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                      </svg>
                    </div>
                    <div>
                      <h4 style={{ margin: '0 0 6px 0', fontSize: '0.98rem', color: '#1e293b', fontWeight: '600' }}>
                        Temporary Destination Placeholder
                      </h4>
                      <p style={{ margin: '0 0 10px 0', fontSize: '0.88rem', color: '#475569', lineHeight: '1.55' }}>
                        The tracking ID has been successfully verified. In the next release, this navigation step will automatically forward the customer to our third-party courier partner tracking portal (e.g., Shiprocket, Delhivery, India Post).
                      </p>
                      <div
                        style={{
                          fontSize: '0.8rem',
                          background: '#ffffff',
                          border: '1px solid #cbd5e1',
                          padding: '10px 12px',
                          borderRadius: '8px',
                          color: '#334155',
                          fontFamily: 'monospace'
                        }}
                      >
                        Target Pipeline: Tracking ID ({currentDestination.trackingId}) &rarr; Courier API / Portal &rarr; Live Status
                      </div>
                    </div>
                  </div>
                </div>

                {/* Primary Action Buttons */}
                <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                  <button
                    type="button"
                    onClick={handleTrackAnother}
                    style={{
                      width: '100%',
                      padding: '13px 20px',
                      background: 'var(--primary-color, #A049A3)',
                      color: '#ffffff',
                      border: 'none',
                      borderRadius: '10px',
                      fontSize: '0.95rem',
                      fontWeight: '600',
                      cursor: 'pointer',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      gap: '8px',
                      boxShadow: '0 4px 14px rgba(160, 73, 163, 0.25)',
                      transition: 'transform 0.15s ease, background 0.2s ease'
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                      <line x1="19" y1="12" x2="5" y2="12"></line>
                      <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    <span>Track Another Order</span>
                  </button>

                  <Link
                    to="/collections"
                    style={{
                      width: '100%',
                      padding: '12px 20px',
                      background: '#ffffff',
                      color: '#4b5563',
                      border: '1px solid #d1d5db',
                      borderRadius: '10px',
                      fontSize: '0.92rem',
                      fontWeight: '600',
                      textDecoration: 'none',
                      textAlign: 'center',
                      display: 'block',
                      boxSizing: 'border-box'
                    }}
                  >
                    Continue Shopping
                  </Link>
                </div>
              </div>
            </div>
          ) : (
            /* ========================================================= */
            /* VIEW B: ORDER TRACKING INPUT FORM (Initial View)          */
            /* ========================================================= */
            <div
              className="track-order-card"
              style={{
                background: '#ffffff',
                borderRadius: '16px',
                border: '1px solid #e9d5eb',
                boxShadow: '0 8px 30px rgba(160, 73, 163, 0.08)',
                padding: '32px 28px'
              }}
            >
              {/* Form Title & Icon */}
              <div style={{ textAlign: 'center', marginBottom: '28px' }}>
                <div
                  style={{
                    width: '64px',
                    height: '64px',
                    margin: '0 auto 16px',
                    borderRadius: '50%',
                    background: '#FDF7FD',
                    border: '2px solid #e9d5eb',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    color: 'var(--primary-color, #A049A3)'
                  }}
                >
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="1" y="3" width="15" height="13"></rect>
                    <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                    <circle cx="5.5" cy="18.5" r="2.5"></circle>
                    <circle cx="18.5" cy="18.5" r="2.5"></circle>
                  </svg>
                </div>
                <h2 style={{ margin: '0 0 8px 0', fontSize: '1.6rem', color: 'var(--primary-color, #A049A3)', fontWeight: '600' }}>
                  Track Your Order
                </h2>
                <p style={{ margin: 0, color: '#6b7280', fontSize: '0.92rem', lineHeight: '1.5' }}>
                  Please enter the unique tracking ID sent to you via WhatsApp.
                </p>
              </div>

              {/* Form */}
              <form onSubmit={handleTrackSubmit} noValidate>
                <div style={{ marginBottom: '20px' }}>
                  <label
                    htmlFor="tracking-id-input"
                    style={{
                      display: 'block',
                      marginBottom: '8px',
                      fontSize: '0.88rem',
                      fontWeight: '600',
                      color: '#374151'
                    }}
                  >
                    Tracking ID <span style={{ color: '#ef4444' }}>*</span>
                  </label>

                  <div
                    style={{
                      position: 'relative',
                      display: 'flex',
                      alignItems: 'center'
                    }}
                  >
                    <span
                      style={{
                        position: 'absolute',
                        left: '14px',
                        color: errorMessage ? '#ef4444' : '#9ca3af',
                        display: 'flex',
                        alignItems: 'center'
                      }}
                    >
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="16.5" y1="9.4" x2="7.55" y2="4.24"></line>
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.29 7 12 12 20.71 7"></polyline>
                        <line x1="12" y1="22" x2="12" y2="12"></line>
                      </svg>
                    </span>

                    <input
                      id="tracking-id-input"
                      type="text"
                      value={trackingInput}
                      onChange={(e) => {
                        setTrackingInput(e.target.value);
                        if (errorMessage) setErrorMessage('');
                      }}
                      placeholder="e.g. VB-TRK-987654"
                      autoFocus
                      aria-invalid={!!errorMessage}
                      aria-describedby={errorMessage ? "tracking-error-msg" : undefined}
                      style={{
                        width: '100%',
                        padding: '13px 14px 13px 44px',
                        borderRadius: '10px',
                        border: errorMessage ? '2px solid #ef4444' : '1.5px solid #d1d5db',
                        fontSize: '0.98rem',
                        color: '#111827',
                        outline: 'none',
                        background: '#ffffff',
                        transition: 'border-color 0.2s ease, box-shadow 0.2s ease'
                      }}
                    />
                  </div>

                  {/* Error Message */}
                  {errorMessage && (
                    <div
                      id="tracking-error-msg"
                      role="alert"
                      style={{
                        marginTop: '8px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '6px',
                        color: '#dc2626',
                        fontSize: '0.85rem'
                      }}
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                      </svg>
                      <span>{errorMessage}</span>
                    </div>
                  )}
                </div>

                {/* Submit Button */}
                <button
                  id="track-order-submit-btn"
                  type="submit"
                  disabled={isSubmitting}
                  style={{
                    width: '100%',
                    padding: '14px 24px',
                    background: 'var(--primary-color, #A049A3)',
                    color: '#ffffff',
                    border: 'none',
                    borderRadius: '10px',
                    fontSize: '1rem',
                    fontWeight: '600',
                    cursor: isSubmitting ? 'not-allowed' : 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '8px',
                    boxShadow: '0 4px 14px rgba(160, 73, 163, 0.25)',
                    transition: 'all 0.2s ease',
                    opacity: isSubmitting ? 0.8 : 1
                  }}
                >
                  {isSubmitting ? (
                    <>
                      <div
                        style={{
                          width: '18px',
                          height: '18px',
                          border: '2px solid #ffffff',
                          borderTopColor: 'transparent',
                          borderRadius: '50%',
                          animation: 'spin 0.8s linear infinite'
                        }}
                      ></div>
                      <span>Verifying Tracking ID...</span>
                    </>
                  ) : (
                    <>
                      <span>Track Order</span>
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                      </svg>
                    </>
                  )}
                </button>
              </form>

              {/* Informational Guidance Cards */}
              <div
                style={{
                  marginTop: '32px',
                  paddingTop: '24px',
                  borderTop: '1px solid #f3f4f6',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '16px'
                }}
              >
                <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-start' }}>
                  <div
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: '50%',
                      background: '#f3e8ff',
                      color: 'var(--primary-color, #A049A3)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 4px 0', fontSize: '0.9rem', color: '#1f2937', fontWeight: '600' }}>
                      Where do I find my Tracking ID?
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.82rem', color: '#6b7280', lineHeight: '1.5' }}>
                      Once your boutique order is handpicked, packed, and dispatched, our admin team shares your courier tracking ID directly to your WhatsApp.
                    </p>
                  </div>
                </div>

                <div style={{ display: 'flex', gap: '12px', alignItems: 'flex-start' }}>
                  <div
                    style={{
                      width: '32px',
                      height: '32px',
                      borderRadius: '50%',
                      background: '#fef3c7',
                      color: '#d97706',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 4px 0', fontSize: '0.9rem', color: '#1f2937', fontWeight: '600' }}>
                      Haven't received a tracking ID yet?
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.82rem', color: '#6b7280', lineHeight: '1.5' }}>
                      Orders are prepared within 24-48 hours. If you need any assistance, reach out to Vivisha Boutique support on WhatsApp or visit your{' '}
                      <Link to="/orders" style={{ color: 'var(--primary-color, #A049A3)', fontWeight: '600', textDecoration: 'underline' }}>
                        My Orders
                      </Link>{' '}
                      page.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default TrackOrder;
