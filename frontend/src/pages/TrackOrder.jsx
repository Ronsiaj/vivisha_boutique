import React from 'react';
import { Link } from 'react-router-dom';
import PageHero from '../components/PageHero.jsx';

const TrackOrder = () => {
  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="Order Tracking"
        breadcrumb="Order Tracking"
        description="Information regarding shipment delivery tracking and courier partner updates."
      />

      <div className="boutique-page-container track-order-container" style={{ padding: '0 16px 60px' }}>
        <div style={{ maxWidth: '680px', margin: '0 auto' }}>
          <div
            className="track-order-info-card"
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
                padding: '28px 24px',
                color: '#ffffff',
                textAlign: 'center'
              }}
            >
              <div
                style={{
                  width: '56px',
                  height: '56px',
                  margin: '0 auto 12px',
                  borderRadius: '50%',
                  background: 'rgba(255, 255, 255, 0.2)',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  backdropFilter: 'blur(4px)'
                }}
              >
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="1" y="3" width="15" height="13"></rect>
                  <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                  <circle cx="5.5" cy="18.5" r="2.5"></circle>
                  <circle cx="18.5" cy="18.5" r="2.5"></circle>
                </svg>
              </div>
              <h2 style={{ margin: '0 0 6px 0', fontSize: '1.5rem', color: '#ffffff', fontWeight: '600' }}>
                How to Track Your Shipment
              </h2>
              <span
                style={{
                  display: 'inline-block',
                  background: 'rgba(255, 255, 255, 0.25)',
                  padding: '4px 14px',
                  borderRadius: '20px',
                  fontSize: '0.8rem',
                  letterSpacing: '0.5px',
                  fontWeight: '600',
                  textTransform: 'uppercase'
                }}
              >
                Third-Party Courier Tracking
              </span>
            </div>

            {/* Informational Content */}
            <div style={{ padding: '32px 28px' }}>
              <div
                style={{
                  display: 'flex',
                  flexDirection: 'column',
                  gap: '20px',
                  marginBottom: '32px'
                }}
              >
                {/* Step 1: Dispatch & WhatsApp Notification */}
                <div
                  style={{
                    display: 'flex',
                    gap: '14px',
                    alignItems: 'flex-start',
                    background: '#FDF7FD',
                    border: '1px solid #f0daf2',
                    borderRadius: '12px',
                    padding: '18px 16px'
                  }}
                >
                  <div
                    style={{
                      width: '38px',
                      height: '38px',
                      borderRadius: '50%',
                      background: '#f3e8ff',
                      color: 'var(--primary-color, #A049A3)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 6px 0', fontSize: '0.98rem', color: '#1f2937', fontWeight: '600' }}>
                      Tracking Details via WhatsApp
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.88rem', color: '#4b5563', lineHeight: '1.55' }}>
                      Once your order has been prepared and dispatched, our admin team will share your <strong>Tracking ID / Shipment ID</strong> along with the <strong>dedicated third-party courier website</strong> directly with you through WhatsApp.
                    </p>
                  </div>
                </div>

                {/* Step 2: Third-Party Courier Tracking Portal */}
                <div
                  style={{
                    display: 'flex',
                    gap: '14px',
                    alignItems: 'flex-start',
                    background: '#f8fafc',
                    border: '1px solid #e2e8f0',
                    borderRadius: '12px',
                    padding: '18px 16px'
                  }}
                >
                  <div
                    style={{
                      width: '38px',
                      height: '38px',
                      borderRadius: '50%',
                      background: '#e0f2fe',
                      color: '#0284c7',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="2" y1="12" x2="22" y2="12"></line>
                      <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 6px 0', fontSize: '0.98rem', color: '#1e293b', fontWeight: '600' }}>
                      Track on the Dedicated Courier Website
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.88rem', color: '#475569', lineHeight: '1.55' }}>
                      Order shipment tracking is handled through the courier partner&apos;s tracking portal. Use the Tracking ID shared by our admin team on that specific courier website to check real-time transit status and delivery updates.
                    </p>
                  </div>
                </div>

                {/* Step 3: Track from Individual Orders */}
                <div
                  style={{
                    display: 'flex',
                    gap: '14px',
                    alignItems: 'flex-start',
                    background: '#fdfbf7',
                    border: '1px solid #fef3c7',
                    borderRadius: '12px',
                    padding: '18px 16px'
                  }}
                >
                  <div
                    style={{
                      width: '38px',
                      height: '38px',
                      borderRadius: '50%',
                      background: '#fef3c7',
                      color: '#d97706',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                      <polyline points="14 2 14 8 20 8"></polyline>
                      <line x1="16" y1="13" x2="8" y2="13"></line>
                      <line x1="16" y1="17" x2="8" y2="17"></line>
                      <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 6px 0', fontSize: '0.98rem', color: '#1f2937', fontWeight: '600' }}>
                      Tracking Available from Individual Orders
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.88rem', color: '#4b5563', lineHeight: '1.55' }}>
                      Shipment tracking and order information can be accessed directly from your individual order details. Visit your{' '}
                      <Link to="/orders" style={{ color: 'var(--primary-color, #A049A3)', fontWeight: '600', textDecoration: 'underline' }}>
                        My Orders
                      </Link>{' '}
                      page to view order status, invoice details, and courier tracking actions.
                    </p>
                  </div>
                </div>

                {/* Step 4: Awaiting Tracking ID */}
                <div
                  style={{
                    display: 'flex',
                    gap: '14px',
                    alignItems: 'flex-start',
                    background: '#f9fafb',
                    border: '1px solid #f3f4f6',
                    borderRadius: '12px',
                    padding: '18px 16px'
                  }}
                >
                  <div
                    style={{
                      width: '38px',
                      height: '38px',
                      borderRadius: '50%',
                      background: '#f3f4f6',
                      color: '#6b7280',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0
                    }}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                  </div>
                  <div>
                    <h4 style={{ margin: '0 0 6px 0', fontSize: '0.98rem', color: '#1f2937', fontWeight: '600' }}>
                      Haven&apos;t received your Tracking ID yet?
                    </h4>
                    <p style={{ margin: 0, fontSize: '0.88rem', color: '#6b7280', lineHeight: '1.55' }}>
                      Orders are prepared and dispatched according to the order fulfillment timeline. As soon as your order is dispatched and tracking is active, the admin team will share the Tracking ID with you via WhatsApp.
                    </p>
                  </div>
                </div>
              </div>

              {/* Action Buttons */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <Link
                  to="/orders"
                  style={{
                    width: '100%',
                    padding: '14px 20px',
                    background: 'var(--primary-color, #A049A3)',
                    color: '#ffffff',
                    borderRadius: '10px',
                    fontSize: '0.95rem',
                    fontWeight: '600',
                    textDecoration: 'none',
                    textAlign: 'center',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '8px',
                    boxShadow: '0 4px 14px rgba(160, 73, 163, 0.25)',
                    transition: 'all 0.2s ease',
                    boxSizing: 'border-box'
                  }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                  </svg>
                  <span>Go to My Orders</span>
                </Link>

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
        </div>
      </div>
    </div>
  );
};

export default TrackOrder;
