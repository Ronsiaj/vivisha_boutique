import React, { useState, useEffect } from 'react';

const promiseFeatures = [
  {
    id: 'premium-quality',
    title: 'Premium Quality',
    description: 'Made with care, crafted to last.',
    icon: (
      <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        {/* Ribbon Badge */}
        <circle cx="18" cy="15" r="9.5" stroke="currentColor" strokeWidth="2" fill="none" />
        <circle cx="18" cy="15" r="7.5" stroke="currentColor" strokeWidth="1" strokeDasharray="2 2" fill="none" />
        {/* Central Star */}
        <polygon points="18,10.5 19.3,13.5 22.5,13.8 20,16 20.8,19.2 18,17.5 15.2,19.2 16,16 13.5,13.8 16.7,13.5" fill="currentColor" />
        {/* Ribbons at bottom */}
        <path d="M14 23.5L12 30L15.5 28L18 29.5L18 24.5" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" fill="none" />
        <path d="M22 23.5L24 30L20.5 28L18 29.5" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" fill="none" />
        {/* Top Right Sparkle Star */}
        <path d="M29 5L29.6 7.4L32 8L29.6 8.6L29 11L28.4 8.6L26 8L28.4 7.4Z" fill="currentColor" />
      </svg>
    )
  },
  {
    id: 'luxury-fabrics',
    title: 'Luxury Fabrics',
    description: 'Soft, breathable fabrics you’ll love wearing.',
    icon: (
      <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        {/* Fabric Roll Cylinder Top */}
        <ellipse cx="23" cy="10" rx="3.5" ry="2.2" stroke="currentColor" strokeWidth="1.8" fill="none" />
        <circle cx="23" cy="10" r="1" fill="currentColor" />
        {/* Rolled Cylinder Body */}
        <line x1="19.5" y1="10" x2="19.5" y2="25" stroke="currentColor" strokeWidth="1.8" />
        <line x1="26.5" y1="10" x2="26.5" y2="25" stroke="currentColor" strokeWidth="1.8" />
        <ellipse cx="23" cy="25" rx="3.5" ry="2.2" stroke="currentColor" strokeWidth="1.8" fill="none" />
        {/* Draped Fabric Fold on Left */}
        <rect x="9.5" y="14" width="10" height="11" rx="1.5" stroke="currentColor" strokeWidth="1.8" fill="none" />
        {/* Diagonal Woven Fabric Pattern */}
        <line x1="11.5" y1="16.5" x2="17.5" y2="22.5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
        <line x1="11.5" y1="20" x2="16.5" y2="25" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
        <line x1="13.5" y1="15" x2="18" y2="19.5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
        {/* Sparkle Star at Top Left */}
        <path d="M10 5L10.6 7.4L13 8L10.6 8.6L10 11L9.4 8.6L7 8L9.4 7.4Z" fill="currentColor" />
      </svg>
    )
  },
  {
    id: 'skin-friendly',
    title: 'Skin Friendly',
    description: 'Comfort-first fabrics that feel good on your skin.',
    icon: (
      <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        {/* T-shirt Silhouette */}
        <path d="M12 11C13.5 13 16 13.5 18 13.5C20 13.5 22.5 13 24 11L28 14L25.5 18L23 16.5V27H13V16.5L10.5 18L8 14L12 11Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" fill="none" />
        {/* Organic Leaf on Bottom Right */}
        <path d="M21 21C21 21 26.5 20.5 28 25C26 27.5 22 27 21 26C20.5 24 21 21 21 21Z" stroke="currentColor" strokeWidth="1.6" fill="currentColor" fillOpacity="0.15" />
        <path d="M21 26C23.5 24.5 26 23.5 28 25" stroke="currentColor" strokeWidth="1.4" />
        {/* Sparkle Star at Top Left */}
        <path d="M8 5L8.5 6.9L10.5 7.5L8.5 8.1L8 10L7.5 8.1L5.5 7.5L7.5 6.9Z" fill="currentColor" />
      </svg>
    )
  },
  {
    id: 'loved-by-women',
    title: 'Loved By Women',
    description: 'Made for women who value style and comfort.',
    icon: (
      <svg width="40" height="40" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        {/* Heart Silhouette */}
        <path d="M18 13C16.5 10 12.5 8.5 9.5 11C6 14 7 19 11.5 23L18 29.5L24.5 23C29 19 30 14 26.5 11C23.5 8.5 19.5 10 18 13Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" fill="none" />
        {/* Clasped Hands Silhouette inside Heart */}
        <path d="M12.5 18.5L15.5 16C16.2 15.4 17.2 15.6 17.8 16.3L18 16.5L18.2 16.3C18.8 15.6 19.8 15.4 20.5 16L23.5 18.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        <path d="M14 18L16.5 21L18 20L19.5 21L22 18" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" />
        {/* Sparkle Star at Top Left */}
        <path d="M6 5L6.5 6.9L8.5 7.5L6.5 8.1L6 10L5.5 8.1L3.5 7.5L5.5 6.9Z" fill="currentColor" />
      </svg>
    )
  }
];

const BrandPromise = () => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isHovered, setIsHovered] = useState(false);
  const [touchStartX, setTouchStartX] = useState(null);
  const [touchEndX, setTouchEndX] = useState(null);

  const handleNext = () => {
    setCurrentIndex((prev) => (prev + 1) % promiseFeatures.length);
  };

  const handlePrev = () => {
    setCurrentIndex((prev) => (prev - 1 + promiseFeatures.length) % promiseFeatures.length);
  };

  // Auto slide every 3.5 seconds
  useEffect(() => {
    if (isHovered) return;
    const interval = setInterval(() => {
      handleNext();
    }, 3500);
    return () => clearInterval(interval);
  }, [isHovered, currentIndex]);

  const handleTouchStart = (e) => {
    setTouchEndX(null);
    setTouchStartX(e.targetTouches[0].clientX);
  };

  const handleTouchMove = (e) => {
    setTouchEndX(e.targetTouches[0].clientX);
  };

  const handleTouchEnd = () => {
    if (!touchStartX || !touchEndX) return;
    const distance = touchStartX - touchEndX;
    if (distance > 45) {
      handleNext();
    } else if (distance < -45) {
      handlePrev();
    }
  };

  return (
    <section className="brand-promise-section" aria-label="Your Comfort, Our Promise">
      <div className="brand-promise-container">
        <h2 className="brand-promise-heading">Your Comfort, Our Promise</h2>

        <div
          className="brand-promise-carousel-wrapper"
          onMouseEnter={() => setIsHovered(true)}
          onMouseLeave={() => setIsHovered(false)}
          onTouchStart={handleTouchStart}
          onTouchMove={handleTouchMove}
          onTouchEnd={handleTouchEnd}
        >
          {/* Navigation Button: Previous */}
          <button
            type="button"
            className="brand-promise-nav-btn prev"
            onClick={handlePrev}
            aria-label="Previous promise feature"
          >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>

          {/* Carousel Viewport Window */}
          <div className="brand-promise-slider-window">
            <div
              className="brand-promise-grid"
              style={{
                '--current-slide': currentIndex
              }}
            >
              {promiseFeatures.map((item) => (
                <div key={item.id} className="brand-promise-card">
                  <div className="brand-promise-icon-circle">
                    {item.icon}
                  </div>
                  <h3 className="brand-promise-card-title">{item.title}</h3>
                  <p className="brand-promise-card-desc">{item.description}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Navigation Button: Next */}
          <button
            type="button"
            className="brand-promise-nav-btn next"
            onClick={handleNext}
            aria-label="Next promise feature"
          >
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>

        {/* Mobile Indicator Dots */}
        <div className="brand-promise-dots">
          {promiseFeatures.map((_, idx) => (
            <button
              key={idx}
              type="button"
              className={`brand-promise-dot ${currentIndex === idx ? 'active' : ''}`}
              onClick={() => setCurrentIndex(idx)}
              aria-label={`Go to slide ${idx + 1}`}
            />
          ))}
        </div>
      </div>
    </section>
  );
};

export default BrandPromise;
