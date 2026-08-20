import React from 'react';
import { Link } from 'react-router-dom';
import boutiqueLogo from '../../assets/images/boutique_logo.png';

const PageHero = ({ title, breadcrumb, description }) => {
  return (
    <section className="boutique-hero-container">
      <div className="boutique-hero-inner">
        {/* Breadcrumb */}
        <div className="hero-breadcrumb">
          <Link to="/" className="breadcrumb-link">Home</Link>
          <span className="breadcrumb-separator">&gt;</span>
          <span className="breadcrumb-current">{breadcrumb || title}</span>
        </div>

        {/* Page Title */}
        <h1 className="hero-page-title">{title}</h1>

        {/* Decorative Divider */}
        <div className="hero-divider">
          <span className="divider-line"></span>
          <span className="divider-icon">
            <img src={boutiqueLogo} alt="Vivisha Boutique Logo" className="hero-divider-logo" />
          </span>
          <span className="divider-line"></span>
        </div>

        {/* Page Description */}
        {description && (
          <p className="hero-description">{description}</p>
        )}
      </div>

      {/* Hero Bottom Curved Wave with Center Badge */}
      <div className="hero-wave-wrapper">
        <svg className="hero-wave-svg" viewBox="0 0 1440 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
          {/* Subtle Accent Shadow Curve */}
          <path
            d="M0,75 C360,20 1080,20 1440,75 L1440,100 L0,100 Z"
            fill="#C86395"
            opacity="0.22"
          />
          {/* Main White Wave Cutout */}
          <path
            d="M0,80 C360,25 1080,25 1440,80 L1440,100 L0,100 Z"
            fill="#FFFFFF"
          />
          {/* Curved Accent Line */}
          <path
            d="M0,76 C360,21 1080,21 1440,76"
            fill="none"
            stroke="#C86395"
            strokeWidth="3"
            opacity="0.65"
          />
        </svg>
        <div className="hero-badge-circle">
          <img src={boutiqueLogo} alt="Vivisha Boutique Logo" className="hero-badge-logo" />
        </div>
      </div>
    </section>
  );
};

export default PageHero;
