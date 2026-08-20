import React, { useState } from 'react';
import PageHero from '../components/PageHero.jsx';

const TermsConditions = () => {
  const [accepted, setAccepted] = useState(false);

  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="Terms & Conditions"
        breadcrumb="Terms & Conditions"
        description="Please read these terms and conditions carefully before using our website and services."
      />

      <div className="boutique-page-container policy-page-container">
        <div className="policy-content-card">
          <div className="policy-section-block">
            <p className="section-text">
              By accessing and using our Boutiqueofficial, you agree to comply with our terms and conditions, including providing accurate account information, maintaining the confidentiality of your account, and using the platform for lawful purposes only.
            </p>
          </div>

          <div className="policy-section-block">
            <p className="section-text">
              All orders are subject to product availability and confirmation, and prices may change without prior notice. Payments must be completed through secure methods before order processing. Delivery times are estimates and may vary based on location and external factors.
            </p>
          </div>

          <div className="policy-section-block">
            <p className="section-text">
              Users are responsible for reviewing their orders, and any issues such as cancellations, refunds, or incorrect items must be reported within the specified time frame.
            </p>
          </div>

          <div className="policy-section-block">
            <p className="section-text">
              Boutiqueofficial reserves the right to update these terms at any time, and continued use of the platform indicates your acceptance of any changes.
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
              Accept our Terms & conditions apply<span className="required">*</span>
            </label>
          </div>
        </div>
      </div>
    </div>
  );
};

export default TermsConditions;
