import React, { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';

// Import banner images
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';

const bannerImages = [
  { id: 1, src: banner1, title: 'Slub Silk Collection' },
  { id: 2, src: banner2, title: 'Viyani Salwar Suit Sets' },
  { id: 3, src: banner3, title: 'Designer Ethnic Wear' }
];

const newArrivalProducts = [
  {
    id: 1,
    name: 'Peacock Blue Salwar Set (3 Piece Suit) - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner1
  },
  {
    id: 2,
    name: 'Avocado Green Salwar Suit - Slub Silk',
    category: 'Salwar Sets',
    price: 1799,
    originalPrice: 2299,
    image: banner2
  },
  {
    id: 3,
    name: 'Mustard 3 Piece Set - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner3
  },
  {
    id: 4,
    name: 'Royal Magenta Anarkali Suit Set',
    category: 'Anarkali Suits',
    price: 2499,
    originalPrice: 3199,
    image: banner1
  }
];

const Home = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { toggleWishlist, isInWishlist } = useWishlist();
  const [currentSlide, setCurrentSlide] = useState(0);

  // Auto slide on mobile view
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % bannerImages.length);
    }, 4000);
    return () => clearInterval(timer);
  }, []);

  const handleShopNowClick = () => {
    navigate('/collections');
  };

  return (
    <div className="home-page-container">
      {/* ---------------------------------------------------- */}
      {/* Hero Banner Section with Floating Bottom Trust Bar  */}
      {/* ---------------------------------------------------- */}
      <section className="hero-banner-section">
        {/* Desktop View: Horizontal Side-by-Side Banners */}
        <div className="desktop-horizontal-banners">
          {bannerImages.map((banner) => (
            <div key={banner.id} className="desktop-banner-item">
              <img src={banner.src} alt={banner.title} className="banner-img" />
            </div>
          ))}

          {/* Desktop Overlay: ONLY Shop Now Button with Pulse Ripple */}
          <div className="banner-hero-overlay-minimal">
            <button className="banner-shop-now-btn pulse-ripple" onClick={handleShopNowClick}>
              Shop Now
            </button>
          </div>
        </div>

        {/* Mobile View: Slide Carousel */}
        <div className="mobile-banner-carousel">
          <div
            className="carousel-track"
            style={{ transform: `translateX(-${currentSlide * 100}%)` }}
          >
            {bannerImages.map((banner) => (
              <div key={banner.id} className="mobile-slide-item">
                <img src={banner.src} alt={banner.title} className="slide-img" />
              </div>
            ))}
          </div>

          {/* Mobile Banner Overlay: ONLY Shop Now Button with Pulse Ripple */}
          <div className="mobile-banner-overlay-minimal">
            <button className="banner-shop-now-btn pulse-ripple" onClick={handleShopNowClick}>
              Shop Now
            </button>
          </div>

          {/* Slide Indicator Dots */}
          <div className="carousel-dots">
            {bannerImages.map((_, idx) => (
              <button
                key={idx}
                className={`dot ${currentSlide === idx ? 'active' : ''}`}
                onClick={() => setCurrentSlide(idx)}
                aria-label={`Go to slide ${idx + 1}`}
              />
            ))}
          </div>
        </div>

        {/* Premium Floating Trust Card ON the Banner (Matching Reference Image) */}
        <div className="banner-trust-floating-card">
          <div className="trust-features-grid">
            <div className="trust-feature-card">
              <div className="trust-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                  <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
              </div>
              <div className="trust-text-box">
                <h4 className="trust-title">Seamless Shipping</h4>
                <p className="trust-subtitle">Fast & Reliable Delivery</p>
              </div>
            </div>

            <div className="trust-feature-card">
              <div className="trust-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                  <polyline points="9 12 11 14 15 10"></polyline>
                </svg>
              </div>
              <div className="trust-text-box">
                <h4 className="trust-title">Uncompromising Quality</h4>
                <p className="trust-subtitle">Premium Quality Products</p>
              </div>
            </div>

            <div className="trust-feature-card">
              <div className="trust-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8z"></path>
                  <path d="M12 6a6 6 0 0 0-6 6c0 3.31 2.69 6 6 6s6-2.69 6-6a6 6 0 0 0-6-6z"></path>
                </svg>
              </div>
              <div className="trust-text-box">
                <h4 className="trust-title">Trusted by Thousands</h4>
                <p className="trust-subtitle">Loved by Our Customers</p>
              </div>
            </div>

            <div className="trust-feature-card">
              <div className="trust-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="2" y="5" width="20" height="14" rx="2" ry="2"></rect>
                  <line x1="2" y1="10" x2="22" y2="10"></line>
                </svg>
              </div>
              <div className="trust-text-box">
                <h4 className="trust-title">Secured Payments</h4>
                <p className="trust-subtitle">Safe & Secure Checkout</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ---------------------------------------------------- */}
      {/* Product Categories Quick Link Bar */}
      {/* ---------------------------------------------------- */}
      <section className="home-categories-section">
        <div className="container">
          <div className="section-title-wrapper">
            <h2 className="section-heading">Product Categories</h2>
            <p className="section-subheading">Explore our exclusive handcrafted boutique collections</p>
          </div>

          <div className="category-cards-grid">
            <Link to="/collections?cat=3-piece-suit" className="category-card">
              <div className="cat-img-box">
                <img src={banner1} alt="3 Piece Suit Sets" />
              </div>
              <h3 className="cat-name">3 Piece Suit Sets</h3>
            </Link>

            <Link to="/collections?cat=salwar-sets" className="category-card">
              <div className="cat-img-box">
                <img src={banner2} alt="Salwar Suit Sets" />
              </div>
              <h3 className="cat-name">Salwar Suit Sets</h3>
            </Link>

            <Link to="/collections?cat=slub-silk" className="category-card">
              <div className="cat-img-box">
                <img src={banner3} alt="Slub Silk Cotton" />
              </div>
              <h3 className="cat-name">Slub Silk Cotton</h3>
            </Link>

            <Link to="/collections?cat=anarkali" className="category-card">
              <div className="cat-img-box">
                <img src={banner1} alt="Anarkali Suits" />
              </div>
              <h3 className="cat-name">Anarkali Suits</h3>
            </Link>
          </div>
        </div>
      </section>

      {/* ---------------------------------------------------- */}
      {/* New Arrivals Section (Without Offer Labels)           */}
      {/* ---------------------------------------------------- */}
      <section className="home-products-section">
        <div className="container">
          <div className="products-header">
            <div>
              <h2 className="section-heading">New Arrivals</h2>
              <p className="section-subheading">Handpicked fresh arrivals crafted for your wardrobe</p>
            </div>
            <Link to="/collections" className="view-all-link">View All Products &rarr;</Link>
          </div>

          <div className="products-grid">
            {newArrivalProducts.map((product) => {
              const isWishlisted = isInWishlist(product.id);
              return (
                <div
                  key={product.id}
                  className="product-card"
                  onClick={() => navigate(`/product/${product.id}`)}
                  style={{ cursor: 'pointer' }}
                >
                  <div className="product-image-wrapper">
                    <img src={product.image} alt={product.name} className="product-img" />
                    <button
                      className={`wishlist-heart-btn ${isWishlisted ? 'active' : ''}`}
                      aria-label="Add to Wishlist"
                      onClick={(e) => {
                        e.stopPropagation();
                        if (!isAuthenticated) {
                          navigate('/login', { state: { from: location.pathname } });
                          return;
                        }
                        toggleWishlist(product);
                      }}
                    >
                      <svg width="18" height="18" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                      </svg>
                    </button>
                    <div className="product-image-arrow-overlay">
                      <span>View Details</span>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                      </svg>
                    </div>
                  </div>
                  <div className="product-info">
                    <h4 className="product-title">{product.name}</h4>
                    <div className="product-info-row">
                      <div className="product-pricing">
                        <span className="price">Rs. {product.price.toLocaleString('en-IN')}.00</span>
                        {product.originalPrice > product.price && (
                          <span className="original-price">Rs. {product.originalPrice.toLocaleString('en-IN')}.00</span>
                        )}
                      </div>
                      <button
                        className="product-detail-arrow-btn"
                        aria-label="View Product Details"
                        onClick={(e) => {
                          e.stopPropagation();
                          navigate(`/product/${product.id}`);
                        }}
                      >
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                          <line x1="5" y1="12" x2="19" y2="12"></line>
                          <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>
    </div>
  );
};

export default Home;
