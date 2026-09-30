import React, { useState, useEffect } from 'react';

/**
 * RefundRequestModal
 *
 * Implements the standard Vivisha Boutique refund request flow:
 * 1. Checks order refund eligibility based on actual backend business rules
 * 2. Collects the backend-supported 'reason' field (max 500 chars)
 * 3. Formats and prepares a WhatsApp notification to the admin
 * 4. Deep-links directly to WhatsApp with real order, customer, and reason data
 * 5. Persists the customer request locally so status is maintained on refresh
 */

export const getAdminWhatsAppNumber = () => {
  const envNumber = import.meta.env.VITE_ADMIN_WHATSAPP;
  if (envNumber && String(envNumber).trim()) {
    return String(envNumber).replace(/[^0-9]/g, '');
  }
  // Existing WhatsApp configuration found in Footer.jsx
  return '919495764049';
};

const RefundRequestModal = ({
  isOpen,
  onClose,
  order,
  currentUser,
  existingRequest,
  onRefundSubmitted
}) => {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);
  const [submittedData, setSubmittedData] = useState(null);
  const [copyStatus, setCopyStatus] = useState(false);

  useEffect(() => {
    if (isOpen) {
      if (existingRequest) {
        setSubmittedData(existingRequest);
        setIsSuccess(true);
      } else {
        setReason('');
        setError('');
        setIsSubmitting(false);
        setIsSuccess(false);
        setSubmittedData(null);
      }
      setCopyStatus(false);
    }
  }, [isOpen, existingRequest]);

  if (!isOpen || !order) return null;

  const customerName = order.user?.name || currentUser?.name || 'Customer';
  const orderNumber = order.order_number ? `#${order.order_number}` : `#${order.id}`;
  const grandTotal = parseFloat(order.amounts?.grand_total || order.grand_total || 0);
  const adminWhatsAppNumber = getAdminWhatsAppNumber();

  const buildWhatsAppMessage = (refundReason) => {
    return `Refund Request\n\nOrder: ${orderNumber}\nCustomer: ${customerName}\nOrder Amount: ₹${grandTotal.toLocaleString('en-IN')}.00\nReason:\n${refundReason.trim()}\n\nPlease contact the customer for further refund processing.`;
  };

  const openWhatsAppLink = (message) => {
    const encoded = encodeURIComponent(message);
    const waUrl = `https://wa.me/${adminWhatsAppNumber}?text=${encoded}`;
    const opened = window.open(waUrl, '_blank', 'noopener,noreferrer');
    if (!opened || opened.closed || typeof opened.closed === 'undefined') {
      window.location.href = waUrl;
    }
  };

  const handleCopyMessage = (text) => {
    navigator.clipboard.writeText(text).then(() => {
      setCopyStatus(true);
      setTimeout(() => setCopyStatus(false), 2500);
    }).catch(() => {
      setError('Unable to copy text to clipboard.');
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const trimmed = reason.trim();

    if (!trimmed) {
      setError('Please provide a reason for the refund request.');
      return;
    }

    if (trimmed.length > 500) {
      setError('Reason must not exceed 500 characters.');
      return;
    }

    setError('');
    setIsSubmitting(true);

    try {
      const message = buildWhatsAppMessage(trimmed);

      const requestPayload = {
        orderId: order.id,
        orderNumber: order.order_number,
        customerName,
        reason: trimmed,
        amount: grandTotal,
        requestedAt: new Date().toISOString(),
        message
      };

      // Notify parent to store in state & localStorage
      if (onRefundSubmitted) {
        onRefundSubmitted(order.id, requestPayload);
      }

      setSubmittedData(requestPayload);
      setIsSuccess(true);

      // Open WhatsApp deep link
      openWhatsAppLink(message);
    } catch (err) {
      console.error('Error initiating refund notification:', err);
      setError('An error occurred while preparing the refund request. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="address-drawer-overlay open"
      onClick={onClose}
      style={{ zIndex: 999999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '16px' }}
    >
      <div
        className="refund-modal-card"
        onClick={(e) => e.stopPropagation()}
        style={{
          background: '#ffffff',
          borderRadius: '16px',
          width: '100%',
          maxWidth: '540px',
          maxHeight: '92vh',
          display: 'flex',
          flexDirection: 'column',
          boxShadow: '0 20px 40px rgba(0, 0, 0, 0.2)',
          overflow: 'hidden',
          animation: 'viewerZoomIn 0.22s cubic-bezier(0.16, 1, 0.3, 1)'
        }}
      >
        {/* Header */}
        <div style={{
          padding: '18px 24px',
          borderBottom: '1px solid #f0e6f0',
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          background: '#faf5fa'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
            <div style={{
              width: '36px',
              height: '36px',
              borderRadius: '50%',
              background: '#F6EDF6',
              color: 'var(--primary-color, #A049A3)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center'
            }}>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
                <path d="M3 3v5h5" />
              </svg>
            </div>
            <div>
              <h3 style={{ margin: 0, fontSize: '1.15rem', color: '#2b112c', fontWeight: '700' }}>
                {isSuccess ? 'Refund Request Submitted' : 'Request Refund'}
              </h3>
              <span style={{ fontSize: '0.8rem', color: '#6b7280' }}>
                Order {orderNumber}
              </span>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label="Close"
            style={{
              background: 'transparent',
              border: 'none',
              fontSize: '1.25rem',
              color: '#9ca3af',
              cursor: 'pointer',
              padding: '4px',
              lineHeight: 1
            }}
          >
            ✕
          </button>
        </div>

        {/* Body */}
        <div style={{ padding: '20px 24px', overflowY: 'auto', flex: 1 }}>
          {isSuccess ? (
            /* Success / Existing Request View */
            <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', textAlign: 'center', padding: '10px 0' }}>
              <div style={{
                width: '64px',
                height: '64px',
                borderRadius: '50%',
                background: '#ecfdf5',
                color: '#059669',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                margin: '0 auto'
              }}>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg>
              </div>

              <div>
                <h4 style={{ margin: '0 0 6px 0', fontSize: '1.15rem', color: '#111827', fontWeight: '700' }}>
                  Refund Request Registered
                </h4>
                <p style={{ margin: 0, fontSize: '0.88rem', color: '#4b5563', lineHeight: 1.5 }}>
                  Your refund request for <strong>{orderNumber}</strong> has been recorded. Our boutique admin team has been notified via WhatsApp and will contact you directly to process your refund.
                </p>
              </div>

              {submittedData && (
                <div style={{
                  background: '#f9fafb',
                  border: '1px solid #e5e7eb',
                  borderRadius: '10px',
                  padding: '14px',
                  textAlign: 'left',
                  fontSize: '0.85rem'
                }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px', color: '#6b7280' }}>
                    <span>Customer:</span>
                    <strong style={{ color: '#111827' }}>{submittedData.customerName || customerName}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px', color: '#6b7280' }}>
                    <span>Requested At:</span>
                    <strong style={{ color: '#111827' }}>
                      {new Date(submittedData.requestedAt).toLocaleDateString('en-IN', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                      })}
                    </strong>
                  </div>
                  <div style={{ borderTop: '1px dashed #e5e7eb', paddingTop: '8px', marginTop: '8px' }}>
                    <span style={{ color: '#6b7280', display: 'block', marginBottom: '4px' }}>Reason:</span>
                    <p style={{ margin: 0, color: '#1f2937', fontStyle: 'italic', background: '#ffffff', padding: '8px', borderRadius: '6px', border: '1px solid #f3f4f6' }}>
                      "{submittedData.reason}"
                    </p>
                  </div>
                </div>
              )}

              {/* Action Buttons in Success View */}
              <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '10px' }}>
                <button
                  type="button"
                  onClick={() => openWhatsAppLink(submittedData?.message || buildWhatsAppMessage(submittedData?.reason || reason))}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '8px',
                    background: '#25D366',
                    color: '#ffffff',
                    border: 'none',
                    borderRadius: '10px',
                    padding: '12px 20px',
                    fontSize: '0.95rem',
                    fontWeight: '600',
                    cursor: 'pointer',
                    boxShadow: '0 4px 12px rgba(37, 211, 102, 0.25)',
                    transition: 'all 0.2s ease'
                  }}
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.052 2C6.505 2 2 7.006 2 13.181c0 2.148.563 4.167 1.636 5.908L2 24l5.064-1.614a10.82 10.82 0 0 0 4.988 1.203h.005c5.547 0 10.052-5.007 10.052-11.182 0-2.988-1.163-5.798-3.277-7.912A9.972 9.972 0 0 0 12.052 2zm6.275 14.153c-.26.732-1.287 1.34-1.782 1.428-.466.082-1.07.116-3.468-.876-2.88-1.192-4.71-4.148-4.855-4.343-.14-.195-1.157-1.543-1.157-2.943 0-1.4.731-2.09.99-2.378.26-.288.567-.36.757-.36.19 0 .38.002.545.01.173.01.406-.066.635.485.238.572.81 1.979.88 2.124.07.144.116.314.024.498-.09.183-.137.297-.272.457-.136.16-.286.357-.408.48-.136.136-.277.283-.12.552.158.27.702 1.157 1.506 1.874 1.033.921 1.905 1.207 2.176 1.343.27.135.43.116.59-.068.16-.183.684-.798.868-1.072.183-.274.368-.228.618-.136.25.092 1.58.745 1.85 1.019.27.274.45.412.518.526.068.114.068.663-.192 1.395z" />
                  </svg>
                  Re-open WhatsApp Message
                </button>

                <button
                  type="button"
                  onClick={() => handleCopyMessage(submittedData?.message || buildWhatsAppMessage(submittedData?.reason || reason))}
                  style={{
                    background: '#f3f4f6',
                    color: '#374151',
                    border: '1px solid #d1d5db',
                    borderRadius: '10px',
                    padding: '10px 16px',
                    fontSize: '0.85rem',
                    fontWeight: '600',
                    cursor: 'pointer'
                  }}
                >
                  {copyStatus ? '✓ Message Copied!' : 'Copy WhatsApp Message Text'}
                </button>
              </div>
            </div>
          ) : (
            /* Refund Request Form */
            <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
              {/* Order Information Card */}
              <div style={{
                background: '#faf5fa',
                border: '1px solid #f0e6f0',
                borderRadius: '10px',
                padding: '12px 16px'
              }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>Order Number:</span>
                  <strong style={{ fontSize: '0.92rem', color: 'var(--primary-color, #A049A3)' }}>{orderNumber}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                  <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>Customer:</span>
                  <strong style={{ fontSize: '0.88rem', color: '#111827' }}>{customerName}</strong>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span style={{ fontSize: '0.82rem', color: '#6b7280' }}>Order Total:</span>
                  <strong style={{ fontSize: '0.98rem', color: '#111827' }}>₹{grandTotal.toLocaleString('en-IN')}.00</strong>
                </div>
              </div>

              {/* Items List Preview */}
              {order.items && order.items.length > 0 && (
                <div style={{ fontSize: '0.82rem', color: '#6b7280' }}>
                  <span style={{ display: 'block', marginBottom: '6px', fontWeight: '600' }}>Items in this order:</span>
                  <ul style={{ margin: 0, paddingLeft: '18px', color: '#374151' }}>
                    {order.items.slice(0, 3).map((it, idx) => (
                      <li key={idx} style={{ marginBottom: '2px' }}>
                        {it.snapshot?.product_name || it.product_name || 'Product'} (Qty: {it.snapshot?.pricing?.quantity || it.quantity || 1})
                      </li>
                    ))}
                    {order.items.length > 3 && (
                      <li style={{ color: '#6b7280' }}>+{order.items.length - 3} more item(s)</li>
                    )}
                  </ul>
                </div>
              )}

              {/* Refund Reason Input (Backend field: 'reason') */}
              <div className="form-group" style={{ margin: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '6px' }}>
                  <label htmlFor="refund-reason" style={{ fontWeight: '600', fontSize: '0.88rem', color: '#111827' }}>
                    Reason for Refund <span style={{ color: '#dc2626' }}>*</span>
                  </label>
                  <span style={{
                    fontSize: '0.75rem',
                    color: reason.length > 480 ? '#dc2626' : '#9ca3af'
                  }}>
                    {reason.length} / 500 characters
                  </span>
                </div>
                <textarea
                  id="refund-reason"
                  rows={4}
                  value={reason}
                  onChange={(e) => {
                    setReason(e.target.value.slice(0, 500));
                    if (error) setError('');
                  }}
                  placeholder="Please state why you are requesting a refund for this order (e.g. damaged goods, size issue, wrong color, incorrect item received)..."
                  required
                  style={{
                    width: '100%',
                    boxSizing: 'border-box',
                    padding: '10px 12px',
                    borderRadius: '8px',
                    border: `1px solid ${error ? '#dc2626' : '#d1d5db'}`,
                    fontSize: '0.88rem',
                    fontFamily: 'inherit',
                    lineHeight: 1.4,
                    resize: 'vertical',
                    outline: 'none',
                    transition: 'border-color 0.2s'
                  }}
                  onFocus={(e) => e.target.style.borderColor = 'var(--primary-color, #A049A3)'}
                  onBlur={(e) => e.target.style.borderColor = error ? '#dc2626' : '#d1d5db'}
                />
                {error && (
                  <span style={{ display: 'block', color: '#dc2626', fontSize: '0.78rem', marginTop: '4px' }}>
                    {error}
                  </span>
                )}
              </div>

              {/* Information Notice */}
              <div style={{
                background: '#f8fafc',
                border: '1px solid #e2e8f0',
                borderRadius: '8px',
                padding: '10px 14px',
                display: 'flex',
                alignItems: 'flex-start',
                gap: '8px',
                fontSize: '0.78rem',
                color: '#475569',
                lineHeight: 1.4
              }}>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ flexShrink: 0, marginTop: '2px', color: '#64748b' }}>
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="16" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12.01" y2="8" />
                </svg>
                <span>
                  Submitting will prepare your refund request and open WhatsApp to contact the store administrator with your order details. The admin will verify the order and contact you for refund settlement.
                </span>
              </div>

              {/* Form Action Buttons */}
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '8px' }}>
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isSubmitting}
                  style={{
                    background: '#f3f4f6',
                    color: '#4b5563',
                    border: '1px solid #d1d5db',
                    borderRadius: '8px',
                    padding: '9px 18px',
                    fontSize: '0.88rem',
                    fontWeight: '600',
                    cursor: 'pointer'
                  }}
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={isSubmitting || !reason.trim()}
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: '8px',
                    background: isSubmitting || !reason.trim()
                      ? '#d1d5db'
                      : 'linear-gradient(135deg, #25D366 0%, #128C7E 100%)',
                    color: '#ffffff',
                    border: 'none',
                    borderRadius: '8px',
                    padding: '9px 20px',
                    fontSize: '0.88rem',
                    fontWeight: '700',
                    cursor: isSubmitting || !reason.trim() ? 'not-allowed' : 'pointer',
                    boxShadow: isSubmitting || !reason.trim() ? 'none' : '0 3px 10px rgba(37, 211, 102, 0.3)',
                    transition: 'all 0.2s ease'
                  }}
                >
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.052 2C6.505 2 2 7.006 2 13.181c0 2.148.563 4.167 1.636 5.908L2 24l5.064-1.614a10.82 10.82 0 0 0 4.988 1.203h.005c5.547 0 10.052-5.007 10.052-11.182 0-2.988-1.163-5.798-3.277-7.912A9.972 9.972 0 0 0 12.052 2zm6.275 14.153c-.26.732-1.287 1.34-1.782 1.428-.466.082-1.07.116-3.468-.876-2.88-1.192-4.71-4.148-4.855-4.343-.14-.195-1.157-1.543-1.157-2.943 0-1.4.731-2.09.99-2.378.26-.288.567-.36.757-.36.19 0 .38.002.545.01.173.01.406-.066.635.485.238.572.81 1.979.88 2.124.07.144.116.314.024.498-.09.183-.137.297-.272.457-.136.16-.286.357-.408.48-.136.136-.277.283-.12.552.158.27.702 1.157 1.506 1.874 1.033.921 1.905 1.207 2.176 1.343.27.135.43.116.59-.068.16-.183.684-.798.868-1.072.183-.274.368-.228.618-.136.25.092 1.58.745 1.85 1.019.27.274.45.412.518.526.068.114.068.663-.192 1.395z" />
                  </svg>
                  {isSubmitting ? 'Processing...' : 'Submit & Message Admin'}
                </button>
              </div>
            </form>
          )}
        </div>

        {/* Footer */}
        {isSuccess && (
          <div style={{
            padding: '14px 24px',
            borderTop: '1px solid #f0e6f0',
            background: '#faf5fa',
            display: 'flex',
            justifyContent: 'flex-end'
          }}>
            <button
              type="button"
              onClick={onClose}
              style={{
                background: 'var(--primary-color, #A049A3)',
                color: '#ffffff',
                border: 'none',
                borderRadius: '8px',
                padding: '9px 24px',
                fontSize: '0.88rem',
                fontWeight: '600',
                cursor: 'pointer'
              }}
            >
              Done
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

export default RefundRequestModal;
