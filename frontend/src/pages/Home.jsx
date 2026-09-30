import React, { useState, useEffect } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useCart } from '../context/CartContext.jsx';
import Testimonials from '../components/Testimonials.jsx';
import BrandPromise from '../components/BrandPromise.jsx';
import { groupVariantsByProduct } from '../utils/productGrouping.js';

// Import banner & fallback images
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';
import bannerCarousel2 from '../../assets/images/banner_carousel2.png';
import bannerCarousel3 from '../../assets/images/banner_carousel3.png';

// Import category images
import kurtiCategoryImg from '../../assets/images/Product_category/kurti.png';
import chuditharCategoryImg from '../../assets/images/Product_category/chudithar.png';
import sareeCategoryImg from '../../assets/images/Product_category/saree.png';

const FEATURED_CATEGORIES = [
  {
    id: 'kurti',
    slug: 'kurti',
    name: 'Kurti',
    desc: 'Effortless styles for every occasion',
    image: kurtiCategoryImg,
    fallbackId: 2,
    objectPosition: 'center 15%'
  },
  {
    id: 'chudithar',
    slug: 'chuditar',
    name: 'Chudithar',
    desc: 'Comfortable elegance for every day',
    image: chuditharCategoryImg,
    fallbackId: 3,
    objectPosition: 'center 15%'
  },
  {
    id: 'saree',
    slug: 'sarees',
    name: 'Saree',
    desc: 'Timeless elegance, beautifully styled',
    image: sareeCategoryImg,
    fallbackId: 1,
    objectPosition: 'center 12%'
  }
];

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

const bannerImages = [
  { id: 1, src: banner1, title: 'Slub Silk Collection' },
  { id: 2, src: banner2, title: 'Viyani Salwar Suit Sets' },
  { id: 3, src: banner3, title: 'Designer Ethnic Wear' }
];

const desktopBannerSlides = [
  {
    id: 'portrait-grid',
    type: 'grid',
    banners: [
      { src: banner1, title: 'Slub Silk Collection' },
      { src: banner2, title: 'Viyani Salwar Suit Sets' },
      { src: banner3, title: 'Designer Ethnic Wear' }
    ]
  },
  { id: 'carousel2', type: 'full', src: bannerCarousel2, title: 'Timeless Elegance' },
  { id: 'carousel3', type: 'full', src: bannerCarousel3, title: 'Grace In Every Drape' }
];

const fallbackImages = [banner1, banner2, banner3];

const Home = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated, token } = useAuth();
  const { toggleWishlist, isInWishlist } = useWishlist();
  const { addToCart } = useCart();
  const [currentSlide, setCurrentSlide] = useState(0);
  const [currentDesktopSlide, setCurrentDesktopSlide] = useState(0);

  // Dynamic Backend Categories State
  const [categories, setCategories] = useState([]);
  const [isCategoryLoading, setIsCategoryLoading] = useState(true);

  // Dynamic Backend New Arrivals State
  const [newArrivalVariants, setNewArrivalVariants] = useState([]);
  const groupedNewArrivals = React.useMemo(() => groupVariantsByProduct(newArrivalVariants), [newArrivalVariants]);
  const [isNewArrivalsLoading, setIsNewArrivalsLoading] = useState(true);
  const [addingCartId, setAddingCartId] = useState(null);
  const [addedCartId, setAddedCartId] = useState(null);

  // Dynamic Backend Top Selling State
  const [topSellingVariants, setTopSellingVariants] = useState([]);
  const groupedTopSelling = React.useMemo(() => groupVariantsByProduct(topSellingVariants), [topSellingVariants]);
  const [isTopSellingLoading, setIsTopSellingLoading] = useState(true);
  const topSellingTrackRef = React.useRef(null);
  const [canScrollLeftTopSelling, setCanScrollLeftTopSelling] = useState(false);
  const [canScrollRightTopSelling, setCanScrollRightTopSelling] = useState(true);

  const checkTopSellingScroll = () => {
    if (topSellingTrackRef.current) {
      const { scrollLeft, scrollWidth, clientWidth } = topSellingTrackRef.current;
      setCanScrollLeftTopSelling(scrollLeft > 5);
      setCanScrollRightTopSelling(scrollLeft < scrollWidth - clientWidth - 5);
    }
  };

  const handleScrollTopSelling = (direction) => {
    if (topSellingTrackRef.current) {
      const scrollAmount = topSellingTrackRef.current.clientWidth * 0.8;
      topSellingTrackRef.current.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
      });
      setTimeout(checkTopSellingScroll, 350);
    }
  };

  useEffect(() => {
    const track = topSellingTrackRef.current;
    if (track) {
      track.scrollLeft = 0;
      track.addEventListener('scroll', checkTopSellingScroll);
      window.addEventListener('resize', checkTopSellingScroll);
      checkTopSellingScroll();
      return () => {
        track.removeEventListener('scroll', checkTopSellingScroll);
        window.removeEventListener('resize', checkTopSellingScroll);
      };
    }
  }, [groupedTopSelling]);

  // Fetch Categories from Backend API
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const response = await fetch(`${API_BASE_URL}/category/list.php?limit=100`);
        const data = await response.json();
        if (data.status && data.data && data.data.categories) {
          const activeCategories = data.data.categories.filter(c => c.status === 'active');
          setCategories(activeCategories.length > 0 ? activeCategories : data.data.categories);
        }
      } catch (err) {
        console.error('Error loading backend categories:', err);
      } finally {
        setIsCategoryLoading(false);
      }
    };
    fetchCategories();
  }, []);

  // Fetch New Arrivals directly from Dedicated Backend API (/api/new_arrival/list.php)
  useEffect(() => {
    let isCurrent = true;
    const fetchNewArrivals = async () => {
      setIsNewArrivalsLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/new_arrival/list.php?status=active&limit=50`);
        if (response.ok) {
          const data = await response.json();
          if ((data.status || data.success) && data.data) {
            const rawList = Array.isArray(data.data.new_arrivals) ? data.data.new_arrivals : [];
            const mappedVariants = rawList.map((item) => {
              const variant = item.product_variant || {};
              const product = item.product || {};
              const primaryImg = variant.primary_image || (Array.isArray(variant.images) && variant.images.find(im => im.is_primary === 1)) || (Array.isArray(variant.images) && variant.images[0]) || null;
              const primaryPath = primaryImg?.image_path || primaryImg?.image || null;

              return {
                ...variant,
                id: variant.id || item.product_variant_id || item.id,
                new_arrival_id: item.id,
                product_variant_id: item.product_variant_id || variant.id,
                product_id: product.id || variant.product_id,
                sku: variant.sku || '',
                variant_name: variant.variant_name || null,
                is_new_arrival: 1,
                sort_order: item.sort_order ?? variant.sort_order ?? 0,
                status: item.status || variant.status || 'active',
                product: {
                  ...product,
                  id: product.id || variant.product_id,
                  name: product.name || 'Product',
                  slug: product.slug || '',
                  description: product.description || '',
                  status: product.status || 'active',
                  is_new_arrival: 1
                },
                category: variant.category || product.category || null,
                size: variant.size || null,
                color: variant.color || null,
                pricing: variant.pricing || {},
                stock: variant.stock || {},
                is_available: variant.is_available ?? 1,
                primary_image: primaryImg ? {
                  ...primaryImg,
                  image: primaryPath,
                  image_path: primaryPath
                } : null,
                images: Array.isArray(variant.images)
                  ? variant.images.map(img => ({
                    ...img,
                    image: img.image_path || img.image,
                    image_path: img.image_path || img.image
                  }))
                  : []
              };
            });
            if (isCurrent) {
              setNewArrivalVariants(mappedVariants);
            }
            return;
          }
        }
        if (isCurrent) {
          setNewArrivalVariants([]);
        }
      } catch (err) {
        console.error('Error loading new arrival products from API:', err);
        if (isCurrent) {
          setNewArrivalVariants([]);
        }
      } finally {
        if (isCurrent) {
          setIsNewArrivalsLoading(false);
        }
      }
    };
    fetchNewArrivals();
    return () => {
      isCurrent = false;
    };
  }, []);

  // Fetch Top Selling directly from Dedicated Backend API (/api/top_selling/list.php)
  useEffect(() => {
    let isCurrent = true;
    const fetchTopSelling = async () => {
      setIsTopSellingLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/top_selling/list.php?status=active&limit=50`);
        if (response.ok) {
          const data = await response.json();
          if ((data.status || data.success) && data.data) {
            const rawList = Array.isArray(data.data.top_selling) ? data.data.top_selling : [];
            const mappedVariants = rawList.map((item) => {
              const variant = item.product_variant || {};
              const product = item.product || {};
              const primaryImg = variant.primary_image || (Array.isArray(variant.images) && variant.images.find(im => im.is_primary === 1)) || (Array.isArray(variant.images) && variant.images[0]) || null;
              const primaryPath = primaryImg?.image_path || primaryImg?.image || null;

              return {
                ...variant,
                id: variant.id || item.product_variant_id || item.id,
                top_selling_id: item.id,
                product_variant_id: item.product_variant_id || variant.id,
                product_id: product.id || variant.product_id,
                sku: variant.sku || '',
                variant_name: variant.variant_name || null,
                is_top_selling: 1,
                is_best_seller: 1,
                sort_order: item.sort_order ?? variant.sort_order ?? 0,
                status: item.status || variant.status || 'active',
                product: {
                  ...product,
                  id: product.id || variant.product_id,
                  name: product.name || 'Product',
                  slug: product.slug || '',
                  description: product.description || '',
                  status: product.status || 'active',
                  is_top_selling: 1,
                  is_best_seller: 1
                },
                category: variant.category || product.category || null,
                size: variant.size || null,
                color: variant.color || null,
                pricing: variant.pricing || {},
                stock: variant.stock || {},
                is_available: variant.is_available ?? 1,
                primary_image: primaryImg ? {
                  ...primaryImg,
                  image: primaryPath,
                  image_path: primaryPath
                } : null,
                images: Array.isArray(variant.images)
                  ? variant.images.map(img => ({
                    ...img,
                    image: img.image_path || img.image,
                    image_path: img.image_path || img.image
                  }))
                  : []
              };
            });
            if (isCurrent) {
              setTopSellingVariants(mappedVariants);
            }
            return;
          }
        }
        if (isCurrent) {
          setTopSellingVariants([]);
        }
      } catch (err) {
        console.error('Error loading top selling products from API:', err);
        if (isCurrent) {
          setTopSellingVariants([]);
        }
      } finally {
        if (isCurrent) {
          setIsTopSellingLoading(false);
        }
      }
    };
    fetchTopSelling();
    return () => {
      isCurrent = false;
    };
  }, []);

  // Auto slide on mobile view
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % bannerImages.length);
    }, 4000);
    return () => clearInterval(timer);
  }, []);

  // Auto slide on desktop view
  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentDesktopSlide((prev) => (prev + 1) % desktopBannerSlides.length);
    }, 5000);
    return () => clearInterval(timer);
  }, []);

  const handleAddToCart = async (variant, e) => {
    if (e) e.stopPropagation();
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    setAddingCartId(variant.id);
    const imgPath = variant.primary_image?.image_path || variant.primary_image?.image || '';
    const cartItem = {
      id: variant.product?.id || variant.id,
      name: variant.product?.name || variant.variant_name,
      price: parseFloat(variant.pricing?.selling_price || 0),
      image: imgPath ? (imgPath.startsWith('http') ? imgPath : ASSET_BASE_URL + imgPath.replace(/^\//, '')) : '',
      variantId: variant.id
    };
    await addToCart(cartItem, 1);
    setAddingCartId(null);
    setAddedCartId(variant.id);
    setTimeout(() => {
      setAddedCartId(null);
    }, 1800);
  };

  const handleShopNowClick = () => {
    navigate('/collections');
  };

  const getCategoryLink = (featured) => {
    if (categories && categories.length > 0) {
      const matched = categories.find(
        (c) =>
          c.slug?.toLowerCase() === featured.slug.toLowerCase() ||
          c.name?.toLowerCase() === featured.name.toLowerCase() ||
          c.slug?.toLowerCase().includes(featured.id) ||
          c.name?.toLowerCase().includes(featured.id)
      );
      if (matched) {
        return `/collections?category_id=${matched.id}`;
      }
    }
    return `/collections?category_id=${featured.fallbackId}`;
  };

  const getCategoryImageUrl = (cat, index) => {
    if (cat.image && cat.image.trim() !== '') {
      if (cat.image.startsWith('http')) return cat.image;
      return `${ASSET_BASE_URL}${cat.image}`;
    }
    return fallbackImages[index % fallbackImages.length];
  };

  const getVariantCardImages = (variant, index = 0) => {
    const primaryPath = variant.primary_image?.image;
    const imageList = Array.isArray(variant.images)
      ? variant.images.map((im) => (typeof im === 'string' ? im : im?.image)).filter(Boolean)
      : [];

    const defaultPath = primaryPath || imageList[0] || null;
    const hoverPath = imageList.find((im) => im && im !== defaultPath) || null;

    const format = (p) => {
      if (!p) return null;
      if (p.startsWith('http')) return p;
      return `${ASSET_BASE_URL}${p}`;
    };

    const fallback = fallbackImages[index % fallbackImages.length];

    return {
      defaultUrl: defaultPath ? format(defaultPath) : fallback,
      hoverUrl: hoverPath ? format(hoverPath) : null
    };
  };

  const getProductImageUrl = (variant, index) => {
    if (variant.primary_image && variant.primary_image.image) {
      const imgPath = variant.primary_image.image;
      if (imgPath.startsWith('http')) return imgPath;
      return `${ASSET_BASE_URL}${imgPath}`;
    }
    return fallbackImages[index % fallbackImages.length];
  };

  return (
    <div className="home-page-container">
      {/* ---------------------------------------------------- */}
      {/* Hero Banner Section with Floating Bottom Trust Bar  */}
      {/* ---------------------------------------------------- */}
      <section className="hero-banner-section">
        {/* Desktop View: Banner Carousel with Crossfade */}
        <div className="desktop-banner-carousel">
          {desktopBannerSlides.map((slide, idx) => (
            <div
              key={slide.id}
              className={`desktop-carousel-slide ${currentDesktopSlide === idx ? 'active' : ''}`}
            >
              {slide.type === 'grid' ? (
                <div className="desktop-horizontal-banners">
                  {slide.banners.map((banner, i) => (
                    <div key={i} className="desktop-banner-item">
                      <img src={banner.src} alt={banner.title} className="banner-img" />
                    </div>
                  ))}
                </div>
              ) : (
                <img src={slide.src} alt={slide.title} className="desktop-fullwidth-banner-img" />
              )}
            </div>
          ))}

          {/* Desktop Overlay: Shop Now Button with Pulse Ripple */}
          <div className="banner-hero-overlay-minimal">
            <button
              className="banner-shop-now-btn pulse-ripple"
              onClick={handleShopNowClick}
              aria-label="Shop Now at Vivisha Boutique"
            >
              <span>Shop Now</span>
              <svg
                className="shop-btn-arrow"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.4"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
            </button>
          </div>

          {/* Desktop Carousel Indicator Dots */}
          <div className="desktop-carousel-dots">
            {desktopBannerSlides.map((_, idx) => (
              <button
                key={idx}
                className={`dot ${currentDesktopSlide === idx ? 'active' : ''}`}
                onClick={() => setCurrentDesktopSlide(idx)}
                aria-label={`Go to desktop slide ${idx + 1}`}
              />
            ))}
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
            <button
              className="banner-shop-now-btn pulse-ripple"
              onClick={handleShopNowClick}
              aria-label="Shop Now at Vivisha Boutique"
            >
              <span>Shop Now</span>
              <svg
                className="shop-btn-arrow"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.4"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
              </svg>
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

        {/* Premium Floating Trust Card ON the Banner */}
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
      {/* Product Categories Section (Find What Fits Your Day) */}
      {/* ---------------------------------------------------- */}
      <section className="home-categories-section">
        <div className="container">
          <div className="home-categories-header">
            <h2 className="category-section-title">Find What Fits Your Day</h2>
          </div>

          <div className="category-cards-grid">
            {FEATURED_CATEGORIES.map((cat) => (
              <Link
                key={cat.id}
                to={getCategoryLink(cat)}
                className="category-card"
                aria-label={`Shop ${cat.name} - ${cat.desc}`}
              >
                <div className="category-card-img-wrapper">
                  <img
                    src={cat.image}
                    alt={cat.name}
                    className="category-card-img"
                    style={{ objectPosition: cat.objectPosition }}
                    loading="lazy"
                  />
                  <div className="category-card-overlay"></div>
                </div>

                <div className="category-card-content">
                  <h3 className="category-card-title">{cat.name}</h3>
                  <p className="category-card-desc">{cat.desc}</p>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* ---------------------------------------------------- */}
      {/* Dynamic Backend New Arrivals Section                  */}
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

          {isNewArrivalsLoading ? (
            <div className="category-loading-skeleton">
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
            </div>
          ) : groupedNewArrivals.length === 0 ? (
            <div className="products-empty-state">
              <p className="no-categories-text">No new arrivals available right now.</p>
            </div>
          ) : (
            <div className="products-grid">
              {groupedNewArrivals.map((product, index) => {
                const defaultVariant = product.defaultVariant;
                const isWishlisted = isInWishlist(defaultVariant.id);
                const isNew = product.is_new_arrival === 1;
                const primaryImg = product.primaryImageUrl || fallbackImages[index % fallbackImages.length];
                const hoverImg = product.hoverImageUrl;

                return (
                  <div
                    key={product.id}
                    className="product-card"
                    onClick={() => navigate(`/product/${defaultVariant.id}`)}
                    style={{ cursor: 'pointer' }}
                  >
                    <div className="product-image-wrapper">
                      {product.isOutOfStock ? (
                        <span className="product-status-tag out-of-stock" style={{ position: 'absolute', top: '10px', left: '10px', background: '#dc2626', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold', zIndex: 2 }}>
                          Out of Stock
                        </span>
                      ) : isNew ? (
                        <span className="product-new-badge">New</span>
                      ) : null}

                      <img
                        src={primaryImg}
                        alt={product.name}
                        className="product-img product-img-primary"
                        loading="lazy"
                        onError={(e) => {
                          e.target.onerror = null;
                          e.target.src = fallbackImages[index % fallbackImages.length];
                        }}
                      />

                      {hoverImg && (
                        <img
                          src={hoverImg}
                          alt={`${product.name} alternate view`}
                          className="product-img product-img-hover"
                          loading="lazy"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                          }}
                        />
                      )}

                      {/* 3 Action Buttons on Right Side */}
                      <div className="product-hover-actions" onClick={(e) => e.stopPropagation()}>
                        {/* 1. Wishlist (Heart) */}
                        <button
                          type="button"
                          className={`product-action-btn wishlist-action-btn ${isWishlisted ? 'active' : ''}`}
                          aria-label={isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'}
                          title={isWishlisted ? 'In Wishlist' : 'Add to Wishlist'}
                          onClick={(e) => {
                            e.stopPropagation();
                            if (!isAuthenticated) {
                              navigate('/login', { state: { from: location.pathname } });
                              return;
                            }
                            toggleWishlist(defaultVariant);
                          }}
                        >
                          <svg
                            width="17"
                            height="17"
                            viewBox="0 0 24 24"
                            fill={isWishlisted ? '#A049A3' : 'none'}
                            stroke={isWishlisted ? '#A049A3' : 'currentColor'}
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                          </svg>
                        </button>

                        {/* 2. View (Eye) */}
                        <button
                          type="button"
                          className="product-action-btn view-action-btn"
                          aria-label="View Product Details"
                          title="View Details"
                          onClick={(e) => {
                            e.stopPropagation();
                            navigate(`/product/${defaultVariant.id}`);
                          }}
                        >
                          <svg
                            width="17"
                            height="17"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                        </button>

                        {/* 3. Add to Cart (Shopping Bag) */}
                        <button
                          type="button"
                          className={`product-action-btn cart-action-btn ${addedCartId === defaultVariant.id ? 'added' : ''}`}
                          aria-label="Add to Cart"
                          title={product.isOutOfStock ? 'Out of Stock' : addedCartId === defaultVariant.id ? 'Added to Cart!' : 'Add to Cart'}
                          disabled={product.isOutOfStock || addingCartId === defaultVariant.id}
                          onClick={(e) => handleAddToCart(defaultVariant, e)}
                        >
                          {addedCartId === defaultVariant.id ? (
                            <svg
                              width="17"
                              height="17"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="#22c55e"
                              strokeWidth="2.2"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                          ) : (
                            <svg
                              width="17"
                              height="17"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                              <line x1="3" y1="6" x2="21" y2="6"></line>
                              <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                          )}
                        </button>
                      </div>
                    </div>

                    <div className="product-info">
                      <h4 className="product-title">{product.name}</h4>
                      {product.colors && product.colors.length > 0 && (
                        <div style={{ display: 'flex', gap: '4px', margin: '4px 0 6px', alignItems: 'center' }}>
                          {product.colors.slice(0, 4).map((col) => (
                            <span
                              key={col.id}
                              title={col.name}
                              style={{
                                width: '10px',
                                height: '10px',
                                borderRadius: '50%',
                                backgroundColor: col.hex_code || '#ccc',
                                border: '1px solid rgba(0,0,0,0.15)',
                                display: 'inline-block'
                              }}
                            />
                          ))}
                          <span style={{ fontSize: '0.72rem', color: '#666', marginLeft: '3px' }}>
                            {product.colors.length} {product.colors.length === 1 ? 'color' : 'colors'}
                          </span>
                        </div>
                      )}
                      <div className="product-pricing">
                        <span className="price">
                          {product.hasPriceRange ? `From Rs. ${product.minSellingPrice.toLocaleString('en-IN')}.00` : `Rs. ${product.minSellingPrice.toLocaleString('en-IN')}.00`}
                        </span>
                        {product.hasDiscount && (
                          <span className="original-price">Rs. {product.minOriginalPrice.toLocaleString('en-IN')}.00</span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </section>

      {/* ---------------------------------------------------- */}
      {/* Dynamic Backend Top Selling Section                  */}
      {/* ---------------------------------------------------- */}
      <section className="top-selling-section">
        <div className="container">
          <div className="top-selling-header">
            <div>
              <h2 className="section-heading">Top Selling</h2>
              <p className="section-subheading">Customer favorites and most-loved styles</p>
            </div>
            <div className="top-selling-header-right">
              {groupedTopSelling.length > 0 && (
                <div className="top-selling-nav-controls">
                  <button
                    type="button"
                    className="top-selling-nav-btn"
                    aria-label="Scroll left"
                    title="Previous"
                    onClick={() => handleScrollTopSelling('left')}
                    disabled={!canScrollLeftTopSelling}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                  </button>
                  <button
                    type="button"
                    className="top-selling-nav-btn"
                    aria-label="Scroll right"
                    title="Next"
                    onClick={() => handleScrollTopSelling('right')}
                    disabled={!canScrollRightTopSelling}
                  >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                  </button>
                </div>
              )}
              <Link to="/collections?sort=best-selling" className="view-all-link">View All Products &rarr;</Link>
            </div>
          </div>

          {isTopSellingLoading ? (
            <div className="category-loading-skeleton">
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
            </div>
          ) : groupedTopSelling.length === 0 ? (
            <div className="products-empty-state">
              <p className="no-categories-text">No top selling products available right now.</p>
            </div>
          ) : (
            <div className="top-selling-carousel-wrapper">
              <div className="top-selling-track" ref={topSellingTrackRef}>
                {groupedTopSelling.map((product, index) => {
                  const defaultVariant = product.defaultVariant;
                  const isWishlisted = isInWishlist(defaultVariant.id);
                  const primaryImg = product.primaryImageUrl || fallbackImages[index % fallbackImages.length];
                  const hoverImg = product.hoverImageUrl;

                  return (
                    <div
                      key={`top-selling-${product.id}`}
                      className="product-card"
                      onClick={() => navigate(`/product/${defaultVariant.id}`)}
                      style={{ cursor: 'pointer' }}
                    >
                      <div className="product-image-wrapper">
                        {product.isOutOfStock ? (
                          <span className="product-status-tag out-of-stock" style={{ position: 'absolute', top: '10px', left: '10px', background: '#dc2626', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold', zIndex: 2 }}>
                            Out of Stock
                          </span>
                        ) : (
                          <span className="top-selling-badge">Top</span>
                        )}

                        <img
                          src={primaryImg}
                          alt={product.name}
                          className="product-img product-img-primary"
                          loading="lazy"
                          onError={(e) => {
                            e.target.onerror = null;
                            e.target.src = fallbackImages[index % fallbackImages.length];
                          }}
                        />

                        {hoverImg && (
                          <img
                            src={hoverImg}
                            alt={`${product.name} alternate view`}
                            className="product-img product-img-hover"
                            loading="lazy"
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                            }}
                          />
                        )}

                        {/* 3 Action Buttons on Right Side */}
                        <div className="product-hover-actions" onClick={(e) => e.stopPropagation()}>
                          {/* 1. Wishlist (Heart) */}
                          <button
                            type="button"
                            className={`product-action-btn wishlist-action-btn ${isWishlisted ? 'active' : ''}`}
                            aria-label={isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'}
                            title={isWishlisted ? 'In Wishlist' : 'Add to Wishlist'}
                            onClick={(e) => {
                              e.stopPropagation();
                              if (!isAuthenticated) {
                                navigate('/login', { state: { from: location.pathname } });
                                return;
                              }
                              toggleWishlist(defaultVariant);
                            }}
                          >
                            <svg
                              width="17"
                              height="17"
                              viewBox="0 0 24 24"
                              fill={isWishlisted ? '#A049A3' : 'none'}
                              stroke={isWishlisted ? '#A049A3' : 'currentColor'}
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                          </button>

                          {/* 2. View (Eye) */}
                          <button
                            type="button"
                            className="product-action-btn view-action-btn"
                            aria-label="View Product Details"
                            title="View Details"
                            onClick={(e) => {
                              e.stopPropagation();
                              navigate(`/product/${defaultVariant.id}`);
                            }}
                          >
                            <svg
                              width="17"
                              height="17"
                              viewBox="0 0 24 24"
                              fill="none"
                              stroke="currentColor"
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                              <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                          </button>

                          {/* 3. Add to Cart (Shopping Bag) */}
                          <button
                            type="button"
                            className={`product-action-btn cart-action-btn ${addedCartId === defaultVariant.id ? 'added' : ''}`}
                            aria-label="Add to Cart"
                            title={product.isOutOfStock ? 'Out of Stock' : addedCartId === defaultVariant.id ? 'Added to Cart!' : 'Add to Cart'}
                            disabled={product.isOutOfStock || addingCartId === defaultVariant.id}
                            onClick={(e) => handleAddToCart(defaultVariant, e)}
                          >
                            {addedCartId === defaultVariant.id ? (
                              <svg
                                width="17"
                                height="17"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="#22c55e"
                                strokeWidth="2.2"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              >
                                <polyline points="20 6 9 17 4 12"></polyline>
                              </svg>
                            ) : (
                              <svg
                                width="17"
                                height="17"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.8"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              >
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                              </svg>
                            )}
                          </button>
                        </div>
                      </div>

                      <div className="product-info">
                        <h4 className="product-title">{product.name}</h4>
                        {product.colors && product.colors.length > 0 && (
                          <div style={{ display: 'flex', gap: '4px', margin: '4px 0 6px', alignItems: 'center' }}>
                            {product.colors.slice(0, 4).map((col) => (
                              <span
                                key={col.id}
                                title={col.name}
                                style={{
                                  width: '10px',
                                  height: '10px',
                                  borderRadius: '50%',
                                  backgroundColor: col.hex_code || '#ccc',
                                  border: '1px solid rgba(0,0,0,0.15)',
                                  display: 'inline-block'
                                }}
                              />
                            ))}
                            <span style={{ fontSize: '0.72rem', color: '#666', marginLeft: '3px' }}>
                              {product.colors.length} {product.colors.length === 1 ? 'color' : 'colors'}
                            </span>
                          </div>
                        )}
                        <div className="product-pricing">
                          <span className="price">
                            {product.hasPriceRange ? `From Rs. ${product.minSellingPrice.toLocaleString('en-IN')}.00` : `Rs. ${product.minSellingPrice.toLocaleString('en-IN')}.00`}
                          </span>
                          {product.hasDiscount && (
                            <span className="original-price">Rs. {product.minOriginalPrice.toLocaleString('en-IN')}.00</span>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </div>
      </section>

      {/* ---------------------------------------------------- */}
      {/* What Our Customers Say - Testimonials Component      */}
      {/* ---------------------------------------------------- */}
      <Testimonials />

      {/* ---------------------------------------------------- */}
      {/* Your Comfort, Our Promise Section                   */}
      {/* ---------------------------------------------------- */}
      <BrandPromise />
    </div>
  );
};

export default Home;
