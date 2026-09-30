import React, { useState } from 'react';
import PageHero from '../components/PageHero.jsx';

const ContactUs = () => {
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    phone: '',
    message: ''
  });

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const phone = formData.phone ? formData.phone.trim() : 'Not provided';
    const text = `*New Inquiry - Vivisha Boutique*\n\n` +
      `👤 *Name:* ${formData.name.trim()}\n` +
      `📧 *Email:* ${formData.email.trim()}\n` +
      `📞 *Phone:* ${phone}\n\n` +
      `💬 *Message:*\n${formData.message.trim()}`;

    const whatsappUrl = `https://wa.me/919597773774?text=${encodeURIComponent(text)}`;
    window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
    setFormData({ name: '', email: '', phone: '', message: '' });
  };

  return (
    <div className="boutique-page-wrapper">
      <PageHero
        title="Contact Us"
        breadcrumb="Contact Us"
        description="We would love to hear from you. Get in touch with our team for orders, styling, or inquiries."
      />

      <div className="boutique-page-container contact-page-container">
        <section className="contact-wrapper">
          {/* Contact Information */}
          <div className="contact-info-card">
            <h2 className="section-title">Get In Touch</h2>
            <p className="section-text">
              Whether you have a question about our collections, need styling advice, or require assistance with your order, our dedicated team is here to help.
            </p>

            <div className="info-details">
              <div className="info-item">
                <span className="info-icon">📍</span>
                <div className="info-text">
                  <strong>Visit Us</strong>
                  <p>No 8 (1), 1st Street, Anandha Nagar<br />Karaikkudi - 630001.</p>
                </div>
              </div>

              <div className="info-item">
                <span className="info-icon">📞</span>
                <div className="info-text">
                  <strong>Call Us</strong>
                  <p>+91 9597773774</p>
                </div>
              </div>

              <div className="info-item">
                <span className="info-icon">✉️</span>
                <div className="info-text">
                  <strong>Email Us</strong>
                  <p>vivishaboutique1131@gmail.com</p>
                </div>
              </div>

              <div className="info-item">
                <span className="info-icon">🕒</span>
                <div className="info-text">
                  <strong>Store Hours</strong>
                  <p>Mon - Sat: 10:00 AM - 8:00 PM<br />Sunday: Closed</p>
                </div>
              </div>
            </div>
          </div>

          {/* Contact Form */}
          <div className="contact-form-card">
            <form className="boutique-form" onSubmit={handleSubmit}>
              <h3 className="form-title">Send us a Message</h3>

              <div className="form-group">
                <label htmlFor="name">Full Name *</label>
                <input
                  type="text"
                  id="name"
                  name="name"
                  value={formData.name}
                  onChange={handleChange}
                  required
                  placeholder="Jane Doe"
                />
              </div>

              <div className="form-group">
                <label htmlFor="email">Email Address *</label>
                <input
                  type="email"
                  id="email"
                  name="email"
                  value={formData.email}
                  onChange={handleChange}
                  required
                  placeholder="jane@example.com"
                />
              </div>

              <div className="form-group">
                <label htmlFor="phone">Phone Number</label>
                <input
                  type="tel"
                  id="phone"
                  name="phone"
                  value={formData.phone}
                  onChange={handleChange}
                  placeholder="+91 XXXXX XXXXX"
                />
              </div>

              <div className="form-group">
                <label htmlFor="message">Message *</label>
                <textarea
                  id="message"
                  name="message"
                  rows="5"
                  value={formData.message}
                  onChange={handleChange}
                  required
                  placeholder="How can we help you?"
                ></textarea>
              </div>

              <button type="submit" className="btn-primary">Send Message</button>
            </form>
          </div>
        </section>
      </div>
    </div>
  );
};

export default ContactUs;
