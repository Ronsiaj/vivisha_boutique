import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import logo from '../../assets/images/boutique_logo.png';

const Footer = () => {
  const [openMobileSections, setOpenMobileSections] = useState({
    quickLinks: false,
    policies: false,
    contactUs: false,
  });

  const toggleMobileSection = (section) => {
    setOpenMobileSections((prev) => ({
      ...prev,
      [section]: !prev[section],
    }));
  };

  return (
    <footer className="vivisha-footer-redesign">
      {/* ==================================================== */}
      {/* DESKTOP FOOTER DESIGN (Without Dropdowns) */}
      {/* ==================================================== */}
      <div className="desktop-footer-wrapper">
        <div className="desktop-footer-container">
          {/* Column 1: Brand Info */}
          <div className="desktop-col-brand">
            <Link to="/" className="footer-logo-brand">
              <img src={logo} alt="Vivisha Boutique Logo" />
            </Link>
            <p className="footer-brand-tagline">
              Timeless styles. Modern elegance.<br />
              Woven for you with love.
            </p>
            <div className="footer-contact-details-desktop">
              <p><strong>Vivisha Boutique</strong></p>
              <p>[Placeholder Address Line 1]</p>
              <p>[Placeholder City, ZIP]</p>
              <p>[Placeholder Contact Number]</p>
            </div>

          </div>

          {/* Column 2: Quick Links */}
          <div className="desktop-col-links">
            <h4 className="desktop-col-title">Quick Links</h4>
            <ul className="desktop-links-list">
              <li><Link to="/about">About Us</Link></li>
              <li><Link to="/contact">Contact Us</Link></li>
            </ul>
          </div>

          {/* Column 3: Policies */}
          <div className="desktop-col-policies">
            <h4 className="desktop-col-title">Policies</h4>
            <ul className="desktop-links-list">
              <li><Link to="/privacy-policy">Privacy Policy</Link></li>
              <li><Link to="/terms">Terms & Conditions</Link></li>
            </ul>
          </div>

          {/* Column 4: Follow us on*/}
          <div className="desktop-col-contact">
            <h4 className="desktop-col-title">Follow us on</h4>
            <div className="desktop-contact-info">
              <div className="desktop-social-circles">
                <a href="#" target="_blank" rel="noopener noreferrer" className="social-circle-link" aria-label="Facebook">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
                </a>
                <a href="#" target="_blank" rel="noopener noreferrer" className="social-circle-link" aria-label="Instagram">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                </a>
              </div>
            </div>
          </div>
        </div>

        {/* Desktop Copyright Bar */}
        <div className="desktop-copyright-bar">
          <p>&copy; {new Date().getFullYear()} Vivisha Boutique. All rights reserved.</p>
        </div>
      </div>

      {/* ==================================================== */}
      {/* MOBILE FOOTER DESIGN (Exact Match to Uploaded Image with Dropdowns) */}
      {/* ==================================================== */}
      <div className="mobile-footer-aham-style">
        {/* Top Brand Section */}
        <div className="mobile-aham-top-brand">
          <Link to="/" className="mobile-aham-logo">
            <img src={logo} alt="Vivisha Boutique Logo" />
          </Link>
          <div className="mobile-aham-address">
            <p className="aham-company-name">Vivisha Boutique Pvt. Ltd.</p>
            <p>[Placeholder Address Line 1],</p>
            <p>[Placeholder City, ZIP]</p>
            <p>[Placeholder Contact Number]</p>
          </div>
          {/* Circular Outlined Social Icons */}
          <div className="mobile-aham-social-row">
            <a href="#" target="_blank" rel="noopener noreferrer" className="aham-social-circle" aria-label="Facebook">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path></svg>
            </a>
            <a href="#" target="_blank" rel="noopener noreferrer" className="aham-social-circle" aria-label="Instagram">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </a>
            <a href="#" target="_blank" rel="noopener noreferrer" className="aham-social-circle" aria-label="Email">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
            </a>
          </div>
        </div>

        {/* Accordion Dropdown List */}
        <div className="mobile-aham-accordions">
          {/* Quick Links Accordion */}
          <div className="aham-accordion-item">
            <button
              className="aham-accordion-btn"
              onClick={() => toggleMobileSection('quickLinks')}
            >
              <span>Quick Links</span>
              <span className="aham-plus-minus">{openMobileSections.quickLinks ? '−' : '+'}</span>
            </button>
            {openMobileSections.quickLinks && (
              <div className="aham-accordion-content">
                <Link to="/">Home</Link>
                <Link to="/about">About Us</Link>
                <Link to="/contact">Contact Us</Link>
              </div>
            )}
          </div>

          {/* Policies Accordion */}
          <div className="aham-accordion-item">
            <button
              className="aham-accordion-btn"
              onClick={() => toggleMobileSection('policies')}
            >
              <span>Policies</span>
              <span className="aham-plus-minus">{openMobileSections.policies ? '−' : '+'}</span>
            </button>
            {openMobileSections.policies && (
              <div className="aham-accordion-content">
                <Link to="/privacy-policy">Privacy Policy</Link>
                <Link to="/terms">Terms & Conditions</Link>
              </div>
            )}
          </div>



        </div>

        {/* Mobile Copyright Line */}
        <div className="mobile-aham-copyright">
          <p>&copy; {new Date().getFullYear()} Vivisha Boutique. All rights reserved.</p>
        </div>
      </div>

      {/* Floating WhatsApp Button */}
      {/* <a
        href="https://wa.me/919495764049"
        target="_blank"
        rel="noopener noreferrer"
        className="floating-whatsapp-btn"
        aria-label="Chat on WhatsApp"
      >
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path fillRule="evenodd" clipRule="evenodd" d="M18.403 5.586A8.93 8.93 0 0 0 12.052 3c-4.948 0-8.976 4.027-8.978 8.977 0 1.58.412 3.122 1.194 4.478L3 21l4.633-1.216a8.948 8.948 0 0 0 4.417 1.169h.004c4.947 0 8.975-4.027 8.977-8.977a8.922 8.922 0 0 0-2.628-6.39zM12.052 19.381h-.003a7.442 7.442 0 0 1-3.791-1.042l-.272-.162-2.818.739.752-2.747-.177-.282a7.447 7.447 0 0 1-1.141-3.911c.002-4.111 3.348-7.457 7.46-7.457 1.99 0 3.86.777 5.266 2.185a7.408 7.408 0 0 1 2.18 5.268c-.002 4.111-3.347 7.456-7.456 7.456zm4.091-5.583c-.225-.113-1.327-.655-1.533-.73-.205-.075-.355-.112-.504.113-.15.224-.58.73-.711.879-.131.15-.262.169-.487.056-.225-.113-.949-.349-1.808-1.115-.668-.596-1.119-1.332-1.25-1.557-.132-.225-.014-.347.099-.459.102-.101.225-.262.338-.393.112-.131.15-.225.225-.375.075-.15.037-.281-.019-.393-.056-.113-.505-1.217-.692-1.666-.182-.437-.367-.378-.504-.385a3.02 3.02 0 0 0-.43-.008c-.15 0-.393.056-.599.281-.206.225-.786.768-.786 1.873 0 1.105.805 2.171.917 2.321.113.15 1.583 2.418 3.837 3.39 1.57.678 1.96.657 2.709.546.495-.074 1.533-.626 1.748-1.23.215-.604.215-1.123.15-1.235-.066-.113-.216-.188-.441-.3z" fill="#FFFFFF"/>
        </svg>
      </a> */}
    </footer>
  );
};

export default Footer;
