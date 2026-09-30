import { useLayoutEffect, useEffect } from 'react';
import { useLocation, useNavigationType } from 'react-router-dom';

/**
 * ScrollToTop Component
 * 
 * Ensures that whenever a route changes across the entire Vivisha Boutique application
 * (Navbar links, Product cards, Category links, programmatic navigate(), Link/NavLink),
 * the viewport instantly resets to scrollTop = 0.
 * 
 * Supports in-page hash links (#section) by scrolling to the target element if provided.
 */
const ScrollToTop = () => {
  const { pathname, search, hash } = useLocation();
  const navigationType = useNavigationType();

  // Set browser scroll restoration to manual for consistent SPA behavior
  useEffect(() => {
    if ('scrollRestoration' in window.history) {
      window.history.scrollRestoration = 'manual';
    }
  }, []);

  useLayoutEffect(() => {
    // If navigation targets an in-page hash anchor (e.g. #reviews)
    if (hash) {
      const id = hash.replace('#', '');
      const element = document.getElementById(id);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        return;
      }
    }

    const resetScroll = () => {
      // Primary window scroll reset
      window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'instant'
      });

      // Explicitly reset documentElement and body for cross-browser reliability
      if (document.documentElement) {
        document.documentElement.scrollTop = 0;
      }
      if (document.body) {
        document.body.scrollTop = 0;
      }
    };

    // Immediate synchronous reset before paint
    resetScroll();

    // Fallback animation frame to ensure position is retained after initial DOM mount
    const rafId = requestAnimationFrame(() => {
      resetScroll();
    });

    return () => cancelAnimationFrame(rafId);
  }, [pathname, search, hash, navigationType]);

  return null;
};

export default ScrollToTop;
