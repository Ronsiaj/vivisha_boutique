import React, { useState, useEffect } from 'react';

const testimonials = [
  {
    id: 1,
    name: 'Ananya Iyer',
    rating: 5,
    text: 'Best purchase I’ve made this year. Fast shipping and the product is exactly as described. Highly recommend!'
  },
  {
    id: 2,
    name: 'Kavya Nair',
    rating: 5,
    text: 'Amazing quality and great value for money. I’ve already recommended this to all my friends and family!'
  },
  {
    id: 3,
    name: 'Radhika Gupta',
    rating: 5,
    text: 'Exceptional product and service. Will definitely be ordering again soon!'
  },
  {
    id: 4,
    name: 'Aishwarya Menon',
    rating: 4,
    text: 'Great product overall. Very satisfied with my purchase and the delivery was quick.'
  },
  {
    id: 5,
    name: 'Meera Sharma',
    rating: 5,
    text: 'Beautiful quality and exactly what I was looking for. The entire shopping experience was smooth and easy.'
  },
  {
    id: 6,
    name: 'Priya Kapoor',
    rating: 5,
    text: 'Absolutely loved my purchase! The quality exceeded my expectations and I’ll definitely shop again.'
  }
];

const extendedTestimonials = [...testimonials, ...testimonials, ...testimonials];

const renderStars = (rating) => {
  return Array.from({ length: 5 }, (_, i) => {
    const isFilled = i < rating;
    return (
      <svg
        key={i}
        width="15"
        height="15"
        viewBox="0 0 24 24"
        fill={isFilled ? 'var(--accent-color, #C86395)' : 'none'}
        stroke={isFilled ? 'var(--accent-color, #C86395)' : 'var(--border-color, #e6cfe7)'}
        strokeWidth={isFilled ? '1' : '1.5'}
      >
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
      </svg>
    );
  });
};

const Testimonials = () => {
  const [testimonialIndex, setTestimonialIndex] = useState(testimonials.length);
  const [isTestimonialTransitioning, setIsTestimonialTransitioning] = useState(true);
  const [isTestimonialHovered, setIsTestimonialHovered] = useState(false);
  const [isTabVisible, setIsTabVisible] = useState(typeof document !== 'undefined' ? !document.hidden : true);

  const getVisibleTestimonialCount = () => {
    if (typeof window === 'undefined') return 4;
    if (window.innerWidth <= 640) return 1;
    if (window.innerWidth <= 1024) return 2;
    return 4;
  };

  const [visibleTestimonialCount, setVisibleTestimonialCount] = useState(getVisibleTestimonialCount());

  useEffect(() => {
    const handleResize = () => {
      setVisibleTestimonialCount(getVisibleTestimonialCount());
    };
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // Listen to tab visibility to pause auto-sliding when tab is hidden or backgrounded,
  // and normalize the index when returning to the tab so it never gets stuck off-screen.
  useEffect(() => {
    const handleVisibilityChange = () => {
      const isVisible = !document.hidden;
      setIsTabVisible(isVisible);
      if (isVisible) {
        setTestimonialIndex((prev) => {
          if (prev >= testimonials.length * 2 || prev < testimonials.length) {
            const normalized = ((prev % testimonials.length) + testimonials.length) % testimonials.length;
            return normalized + testimonials.length;
          }
          return prev;
        });
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, []);

  const handleNextTestimonial = () => {
    setIsTestimonialTransitioning(true);
    setTestimonialIndex((prev) => {
      // Guard against unbounded runaway index if transitionend was dropped
      if (prev >= testimonials.length * 2) {
        return testimonials.length + 1;
      }
      return prev + 1;
    });
  };

  const handlePrevTestimonial = () => {
    setIsTestimonialTransitioning(true);
    setTestimonialIndex((prev) => {
      if (prev <= 0) {
        return testimonials.length * 2 - 1;
      }
      return prev - 1;
    });
  };

  const handleTestimonialTransitionEnd = () => {
    setTestimonialIndex((currentIndex) => {
      if (currentIndex >= testimonials.length * 2) {
        setIsTestimonialTransitioning(false);
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            setIsTestimonialTransitioning(true);
          });
        });
        const normalized = ((currentIndex % testimonials.length) + testimonials.length) % testimonials.length;
        return normalized + testimonials.length;
      } else if (currentIndex < testimonials.length) {
        setIsTestimonialTransitioning(false);
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            setIsTestimonialTransitioning(true);
          });
        });
        const normalized = ((currentIndex % testimonials.length) + testimonials.length) % testimonials.length;
        return normalized + testimonials.length;
      }
      return currentIndex;
    });
  };

  // Watchdog fallback: Browsers drop/throttle CSS transitionend events when tabs are backgrounded
  // or elements are off-screen. This watchdog guarantees that boundary resets occur reliably.
  useEffect(() => {
    if (testimonialIndex >= testimonials.length * 2 || testimonialIndex < testimonials.length) {
      const fallbackTimer = setTimeout(() => {
        handleTestimonialTransitionEnd();
      }, 550);
      return () => clearTimeout(fallbackTimer);
    }
  }, [testimonialIndex]);

  // Auto slide testimonials every 4.5 seconds (only when visible and not hovered)
  useEffect(() => {
    if (isTestimonialHovered || !isTabVisible) return;
    const interval = setInterval(() => {
      handleNextTestimonial();
    }, 4500);
    return () => clearInterval(interval);
  }, [isTestimonialHovered, isTabVisible]);

  // Touch swipe support for mobile/tablet
  const [touchStartX, setTouchStartX] = useState(null);
  const [touchEndX, setTouchEndX] = useState(null);

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
      handleNextTestimonial();
    } else if (distance < -45) {
      handlePrevTestimonial();
    }
  };

  return (
    <section className="home-testimonials-section">
      <div className="container">
        <div className="testimonials-header">
          <h2 className="testimonials-heading">What Our Customers Say</h2>
          <p className="testimonials-subheading">Real reviews from real customers</p>
        </div>

        <div
          className="testimonials-carousel-container"
          onMouseEnter={() => setIsTestimonialHovered(true)}
          onMouseLeave={() => setIsTestimonialHovered(false)}
          onTouchStart={handleTouchStart}
          onTouchMove={handleTouchMove}
          onTouchEnd={handleTouchEnd}
        >
          {/* Left Chevron Button */}
          <button
            className="testimonial-nav-btn prev"
            onClick={handlePrevTestimonial}
            aria-label="Previous reviews"
            type="button"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
          </button>

          {/* Slider Track Window */}
          <div className="testimonials-slider-window">
            <div
              className="testimonials-slider-track"
              onTransitionEnd={handleTestimonialTransitionEnd}
              onTransitionCancel={handleTestimonialTransitionEnd}
              style={{
                transform: `translateX(-${testimonialIndex * (100 / visibleTestimonialCount)}%)`,
                transition: isTestimonialTransitioning
                  ? 'transform 0.5s cubic-bezier(0.25, 1, 0.5, 1)'
                  : 'none'
              }}
            >
              {extendedTestimonials.map((item, idx) => (
                <div key={`${item.id}-${idx}`} className="testimonial-card-item">
                  <div className="testimonial-card">
                    <div className="testimonial-card-header">
                      <div className="testimonial-avatar" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
                          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" />
                        </svg>
                      </div>
                      <div className="testimonial-author-meta">
                        <h4 className="testimonial-author-name">{item.name}</h4>
                        <div className="testimonial-stars-row" aria-label={`${item.rating} out of 5 stars`}>
                          {renderStars(item.rating)}
                        </div>
                      </div>
                    </div>
                    <p className="testimonial-card-text">“{item.text}”</p>
                    <div className="testimonial-card-divider" />
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Right Chevron Button */}
          <button
            className="testimonial-nav-btn next"
            onClick={handleNextTestimonial}
            aria-label="Next reviews"
            type="button"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="9 18 15 12 9 6"></polyline>
            </svg>
          </button>
        </div>
      </div>
    </section>
  );
};

export default Testimonials;
