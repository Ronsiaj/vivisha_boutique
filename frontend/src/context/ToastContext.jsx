import React, { createContext, useContext, useState, useRef, useCallback } from 'react';

const ToastContext = createContext();

export const ToastProvider = ({ children }) => {
  const [toast, setToast] = useState(null);
  const timerRef = useRef(null);

  const hideToast = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
    setToast(null);
  }, []);

  const showToast = useCallback(({ message, type = 'success', duration = 3000 }) => {
    if (timerRef.current) {
      clearTimeout(timerRef.current);
    }

    // Set new toast with unique ID to avoid duplication/stale state
    const toastId = Date.now();
    setToast({
      id: toastId,
      message,
      type
    });

    timerRef.current = setTimeout(() => {
      setToast(prev => (prev?.id === toastId ? null : prev));
      timerRef.current = null;
    }, duration);
  }, []);

  return (
    <ToastContext.Provider value={{ showToast, hideToast }}>
      {children}

      {/* Global Toast Render */}
      {toast && (
        <div
          className="vivisha-global-toast"
          role="status"
          aria-live="polite"
          onClick={hideToast}
          style={{
            position: 'fixed',
            bottom: '28px',
            right: '28px',
            zIndex: 99999,
            display: 'flex',
            alignItems: 'center',
            gap: '12px',
            background: '#ffffff',
            color: '#2b112c',
            padding: '14px 20px',
            borderRadius: '12px',
            boxShadow: '0 10px 30px rgba(0, 0, 0, 0.15), 0 2px 8px rgba(160, 73, 163, 0.12)',
            border: '1px solid rgba(160, 73, 163, 0.2)',
            fontSize: '0.92rem',
            fontWeight: '600',
            fontFamily: 'inherit',
            maxWidth: '380px',
            minWidth: '280px',
            cursor: 'pointer',
            animation: 'vivishaToastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1)',
            transition: 'all 0.2s ease',
            pointerEvents: 'auto'
          }}
        >
          {/* Icon based on toast type */}
          <div
            style={{
              width: '32px',
              height: '32px',
              borderRadius: '50%',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: 0,
              background:
                toast.type === 'wishlist'
                  ? 'rgba(160, 73, 163, 0.12)'
                  : toast.type === 'cart'
                  ? 'rgba(34, 197, 94, 0.12)'
                  : toast.type === 'error'
                  ? 'rgba(239, 68, 68, 0.12)'
                  : 'rgba(160, 73, 163, 0.12)'
            }}
          >
            {toast.type === 'wishlist' ? (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="#A049A3" stroke="#A049A3" strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            ) : toast.type === 'cart' ? (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16a34a" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
              </svg>
            ) : toast.type === 'error' ? (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
              </svg>
            ) : (
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#A049A3" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
              </svg>
            )}
          </div>

          <div style={{ flex: 1, lineHeight: '1.4' }}>
            <span>{toast.message}</span>
          </div>

          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              hideToast();
            }}
            aria-label="Close notification"
            style={{
              background: 'transparent',
              border: 'none',
              color: '#888888',
              cursor: 'pointer',
              padding: '4px',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              borderRadius: '4px'
            }}
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
        </div>
      )}

      <style>{`
        @keyframes vivishaToastIn {
          from {
            opacity: 0;
            transform: translateY(16px) scale(0.96);
          }
          to {
            opacity: 1;
            transform: translateY(0) scale(1);
          }
        }

        @media (max-width: 768px) {
          .vivisha-global-toast {
            bottom: 84px !important;
            right: auto !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            width: calc(100% - 32px) !important;
            max-width: 380px !important;
          }
        }
      `}</style>
    </ToastContext.Provider>
  );
};

export const useToast = () => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};
