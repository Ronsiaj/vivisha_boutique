import React, { useEffect } from 'react';

/**
 * CartAbandonmentModal Component
 * 
 * Props:
 * - isOpen (boolean): Whether the abandonment sheet/dialog is visible
 * - onContinue (function): Action when user clicks "Continue Shopping" (resumes cart/checkout)
 * - onCancel (function): Action when user confirms leaving/canceling the order process
 */
const CartAbandonmentModal = ({ isOpen, onContinue, onCancel }) => {
  // Prevent scrolling when modal is open
  useEffect(() => {
    if (isOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="abandonment-overlay" onClick={onContinue}>
      <div 
        className="abandonment-modal-container"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Mobile Drag/Grab Handle */}
        <div className="abandonment-grab-handle mobile-only"></div>

        {/* Top Urgency Badge */}
        <div className="abandonment-badge">
          <span className="fire-icon">🔥</span> Almost Gone!
        </div>

        {/* Header Title */}
        <h2 className="abandonment-title">
          Hey! Don’t miss out 👀
        </h2>

        {/* Subtitle / Body text */}
        <p className="abandonment-subtitle">
          Your cart items are selling fast, and only a few pieces are left!
        </p>

        {/* Urgency Highlight Banner */}
        <div className="abandonment-urgency-banner">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <circle cx="12" cy="12" r="10"></circle>
            <polyline points="12 6 12 12 16 14"></polyline>
          </svg>
          <span>Hurry and complete your order before they’re gone.</span>
        </div>

        {/* Action Buttons */}
        <div className="abandonment-actions">
          <button 
            type="button" 
            className="btn-abandonment-continue"
            onClick={onContinue}
          >
            Continue Shopping
          </button>
          <button 
            type="button" 
            className="btn-abandonment-cancel"
            onClick={onCancel}
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  );
};

export default CartAbandonmentModal;
