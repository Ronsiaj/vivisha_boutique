import React, { useState, useEffect, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';

const ANNOUNCEMENTS = [
  {
    id: 1,
    type: 'clock',
    text: 'Order before 3:00 PM for same-day dispatch from our Immediate Dispatch Collection',
    link: '/collections'
  },
  {
    id: 2,
    type: 'shipping',
    text: 'Free Shipping on Orders Above ₹999',
    link: '/collections'
  },
  {
    id: 3,
    type: 'sparkle',
    text: 'New Arrivals — Explore the Latest Collection',
    link: '/collections'
  },
  {
    id: 4,
    type: 'secure',
    text: 'Easy Returns & 100% Secure Payments',
    link: '/contact'
  }
];

const AnnouncementIcon = ({ type }) => {
  switch (type) {
    case 'clock':
      return (
        <svg className="announcement-item-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <circle cx="12" cy="12" r="10" />
          <polyline points="12 6 12 12 15 14" />
        </svg>
      );
    case 'shipping':
      return (
        <svg className="announcement-item-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <rect x="1" y="3" width="15" height="13" />
          <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
          <circle cx="5.5" cy="18.5" r="2.5" />
          <circle cx="18.5" cy="18.5" r="2.5" />
        </svg>
      );
    case 'sparkle':
      return (
        <svg className="announcement-item-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M12 2l2.4 7.4H22l-6 4.5 2.3 7.1-6.3-4.6-6.3 4.6 2.3-7.1-6-4.5h7.6z" />
        </svg>
      );
    case 'secure':
      return (
        <svg className="announcement-item-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
          <polyline points="9 12 11 14 15 10" />
        </svg>
      );
    default:
      return null;
  }
};

const AnnouncementBar = () => {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [direction, setDirection] = useState('next');
  const [isPaused, setIsPaused] = useState(false);

  const touchStartX = useRef(0);
  const touchEndX = useRef(0);
  const autoPlayTimerRef = useRef(null);

  const handleNext = useCallback(() => {
    setDirection('next');
    setCurrentIndex((prev) => (prev + 1) % ANNOUNCEMENTS.length);
  }, []);

  const handlePrev = useCallback(() => {
    setDirection('prev');
    setCurrentIndex((prev) => (prev - 1 + ANNOUNCEMENTS.length) % ANNOUNCEMENTS.length);
  }, []);

  // Auto-rotation every 4.5 seconds
  useEffect(() => {
    if (isPaused) return;

    autoPlayTimerRef.current = setInterval(() => {
      handleNext();
    }, 4500);

    return () => {
      if (autoPlayTimerRef.current) {
        clearInterval(autoPlayTimerRef.current);
      }
    };
  }, [isPaused, handleNext]);

  // Touch gesture support for mobile
  const handleTouchStart = (e) => {
    setIsPaused(true);
    touchStartX.current = e.targetTouches[0].clientX;
  };

  const handleTouchMove = (e) => {
    touchEndX.current = e.targetTouches[0].clientX;
  };

  const handleTouchEnd = () => {
    setIsPaused(false);
    if (!touchStartX.current || !touchEndX.current) return;
    const distance = touchStartX.current - touchEndX.current;
    if (distance > 40) {
      handleNext();
    } else if (distance < -40) {
      handlePrev();
    }
    touchStartX.current = 0;
    touchEndX.current = 0;
  };

  const currentAnnouncement = ANNOUNCEMENTS[currentIndex];

  return (
    <div
      className="boutique-announcement-bar"
      role="region"
      aria-label="Announcements and Offers"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
      onTouchStart={handleTouchStart}
      onTouchMove={handleTouchMove}
      onTouchEnd={handleTouchEnd}
    >
      <div className="announcement-bar-content">
        <button
          type="button"
          className="announcement-nav-btn prev-btn"
          onClick={handlePrev}
          aria-label="Previous announcement"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="15 18 9 12 15 6" />
          </svg>
        </button>

        <div className="announcement-viewport">
          <div
            key={`${currentAnnouncement.id}-${direction}`}
            className={`announcement-slide slide-${direction}`}
          >
            <AnnouncementIcon type={currentAnnouncement.type} />
            <Link to={currentAnnouncement.link} className="announcement-text">
              {currentAnnouncement.text}
            </Link>
          </div>
        </div>

        <button
          type="button"
          className="announcement-nav-btn next-btn"
          onClick={handleNext}
          aria-label="Next announcement"
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <polyline points="9 18 15 12 9 6" />
          </svg>
        </button>
      </div>
    </div>
  );
};

export default AnnouncementBar;
