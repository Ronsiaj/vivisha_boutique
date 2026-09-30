import React from 'react';

/**
 * AdminKpiCard - Modern, high-contrast gradient KPI card inspired by reference design
 * Color Variants: 'blue' | 'pink' | 'purple' | 'orange' | 'teal'
 */
const AdminKpiCard = ({
  title,
  value,
  subtitle,
  icon,
  badgeText,
  variant = 'purple',
  footerLeft,
  footerRight,
  onClick,
  style = {}
}) => {
  const themes = {
    blue: {
      gradient: 'linear-gradient(135deg, #4fa8df 0%, #2982be 100%)',
      shadow: '0 10px 24px -4px rgba(41, 130, 190, 0.38)',
      badgeBg: 'rgba(255, 255, 255, 0.22)'
    },
    pink: {
      gradient: 'linear-gradient(135deg, #e85d88 0%, #d43b6d 100%)',
      shadow: '0 10px 24px -4px rgba(212, 59, 109, 0.38)',
      badgeBg: 'rgba(255, 255, 255, 0.22)'
    },
    purple: {
      gradient: 'linear-gradient(135deg, #7e6ef2 0%, #6350e6 100%)',
      shadow: '0 10px 24px -4px rgba(99, 80, 230, 0.38)',
      badgeBg: 'rgba(255, 255, 255, 0.22)'
    },
    orange: {
      gradient: 'linear-gradient(135deg, #f76b61 0%, #df483b 100%)',
      shadow: '0 10px 24px -4px rgba(223, 72, 59, 0.38)',
      badgeBg: 'rgba(255, 255, 255, 0.22)'
    },
    teal: {
      gradient: 'linear-gradient(135deg, #2dd4bf 0%, #0d9488 100%)',
      shadow: '0 10px 24px -4px rgba(13, 148, 136, 0.38)',
      badgeBg: 'rgba(255, 255, 255, 0.22)'
    }
  };

  const theme = themes[variant] || themes.purple;

  return (
    <div
      className="admin-kpi-modern-card"
      onClick={onClick}
      style={{
        background: theme.gradient,
        boxShadow: theme.shadow,
        borderRadius: '20px',
        padding: '22px 24px',
        color: '#ffffff',
        position: 'relative',
        overflow: 'hidden',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'space-between',
        minHeight: '160px',
        cursor: onClick ? 'pointer' : 'default',
        transition: 'transform 0.2s ease, box-shadow 0.2s ease',
        ...style
      }}
    >
      {/* Decorative concentric arcs in the background corner (matching reference design) */}
      <svg
        style={{
          position: 'absolute',
          right: '-25px',
          bottom: '-35px',
          width: '170px',
          height: '170px',
          pointerEvents: 'none',
          opacity: 0.18,
          color: '#ffffff'
        }}
        viewBox="0 0 160 160"
        fill="none"
        aria-hidden="true"
      >
        <circle cx="120" cy="120" r="40" stroke="currentColor" strokeWidth="12" />
        <circle cx="120" cy="120" r="70" stroke="currentColor" strokeWidth="12" />
        <circle cx="120" cy="120" r="100" stroke="currentColor" strokeWidth="12" />
      </svg>

      {/* Top Header Row: Icon + Title + Optional Badge */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', zIndex: 2, position: 'relative' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          {icon && (
            <span style={{
              display: 'inline-flex',
              alignItems: 'center',
              justifyContent: 'center',
              width: '32px',
              height: '32px',
              borderRadius: '10px',
              background: 'rgba(255, 255, 255, 0.2)',
              color: '#ffffff',
              flexShrink: 0
            }}>
              {icon}
            </span>
          )}
          <span style={{
            fontSize: '1.02rem',
            fontWeight: '700',
            color: '#ffffff',
            letterSpacing: '-0.01em',
            textShadow: '0 1px 2px rgba(0,0,0,0.1)'
          }}>
            {title}
          </span>
        </div>

        {badgeText && (
          <span style={{
            background: theme.badgeBg,
            backdropFilter: 'blur(4px)',
            WebkitBackdropFilter: 'blur(4px)',
            color: '#ffffff',
            fontSize: '0.74rem',
            fontWeight: '700',
            padding: '3px 10px',
            borderRadius: '20px',
            border: '1px solid rgba(255, 255, 255, 0.25)',
            letterSpacing: '0.02em',
            whiteSpace: 'nowrap'
          }}>
            {badgeText}
          </span>
        )}
      </div>

      {/* Hero Metric Value */}
      <div style={{ zIndex: 2, position: 'relative', margin: '14px 0 10px' }}>
        <div style={{
          fontSize: '1.95rem',
          fontWeight: '800',
          color: '#ffffff',
          letterSpacing: '-0.02em',
          lineHeight: '1.2',
          textShadow: '0 2px 4px rgba(0,0,0,0.12)'
        }}>
          {value}
        </div>
        {subtitle && (
          <div style={{
            fontSize: '0.82rem',
            color: 'rgba(255, 255, 255, 0.9)',
            fontWeight: '500',
            marginTop: '3px'
          }}>
            {subtitle}
          </div>
        )}
      </div>

      {/* Footer Row: Details or Sub-metrics */}
      {(footerLeft || footerRight) && (
        <div style={{
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
          paddingTop: '10px',
          borderTop: '1px solid rgba(255, 255, 255, 0.2)',
          fontSize: '0.8rem',
          color: 'rgba(255, 255, 255, 0.95)',
          zIndex: 2,
          position: 'relative',
          flexWrap: 'wrap',
          gap: '6px'
        }}>
          <div>{footerLeft}</div>
          <div>{footerRight}</div>
        </div>
      )}
    </div>
  );
};

export default AdminKpiCard;
