import React, { useState } from 'react';
import PageHero from '../components/PageHero.jsx';

const PrivacyPolicy = () => {
  const [accepted, setAccepted] = useState(false);

  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="Privacy Policy"
        breadcrumb="Privacy Policy"
        description="Your privacy matters to us. Learn how we collect, protect, and handle your personal information."
      />

      <div className="boutique-page-container policy-page-container">
        <div className="policy-content-card">
          <p className="section-text policy-intro-lead">
            At Boutique, we respect your privacy and are committed to protecting your personal information.
          </p>

          <div className="policy-section-block">
            <h2 className="section-title">Information We Collect</h2>
            <p className="section-text">
              We may collect information such as your name, phone number, email address, billing address, shipping address, and payment details when you place an order or create an account.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">How We Use Your Information</h2>
            <p className="section-text">
              Your information may be used to:
            </p>
            <ul className="policy-list section-text">
              <li>Process and deliver your orders efficiently</li>
              <li>Provide responsive customer support</li>
              <li>Send order updates and important notifications</li>
              <li>Improve our products, services, and website experience</li>
              <li>Share relevant offers and promotions, where permitted</li>
            </ul>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Payment Security</h2>
            <p className="section-text">
              Payments are processed through secure payment gateways. We do not directly store your complete card or banking information.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Cookies</h2>
            <p className="section-text">
              Our website may use cookies to improve your browsing experience, remember preferences, and understand how visitors use our website.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Sharing of Information</h2>
            <p className="section-text">
              We do not sell or rent your personal information. We may share necessary information with trusted payment, delivery, technology, and service partners solely to fulfill your orders and operate our boutique.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Data Security</h2>
            <p className="section-text">
              We take reasonable measures to protect your personal information against unauthorized access, misuse, or disclosure.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Your Rights</h2>
            <p className="section-text">
              You may request access to, correction of, or deletion of your personal information, subject to applicable laws and business requirements.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Updates to This Policy</h2>
            <p className="section-text">
              We may update this Privacy Policy from time to time. Any changes will be posted on this page.
            </p>
          </div>

          <div className="policy-section-block">
            <h2 className="section-title">Contact Us</h2>
            <p className="section-text">
              For privacy-related questions, please contact us at <strong>Boutique@gmail.com</strong>.
            </p>
          </div>

          <div className="policy-acceptance">
            <label className="checkbox-label">
              <input 
                type="checkbox" 
                checked={accepted} 
                onChange={(e) => setAccepted(e.target.checked)} 
              />
              <span className="checkmark"></span>
              Accept our Privacy policy conditions <span className="required">*</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  );
};

export default PrivacyPolicy;
