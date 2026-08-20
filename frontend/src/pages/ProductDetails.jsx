import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';

// Mock product dataset matching backend API schema
const mockProducts = [
  {
    id: 1,
    name: 'Peacock Blue Salwar Set (3 Piece Suit) - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    inStock: true,
    rating: 4.8,
    reviewsCount: 24,
    sku: 'VB-3PS-101',
    images: [banner1, banner2, banner3, banner1],
    sizes: ['S', 'M', 'L', 'XL'],
    description: 'Elevate your ethnic wardrobe with our exquisite Peacock Blue Salwar Set. Tailored in premium slub silk cotton fabric, this 3-piece suit offers unmatched elegance, comfort, and festive luster. Features traditional embroidery work on the neckline and includes a matching dupatta.',
    specifications: {
      fabric: 'Slub Silk Cotton',
      topLength: '44 Inches',
      bottomType: 'Salwar Pants',
      dupattaFabric: 'Chiffon Silk',
      washCare: 'Dry Clean Only'
    }
  },
  {
    id: 2,
    name: 'Avocado Green Salwar Suit - Slub Silk',
    category: 'Salwar Sets',
    price: 1799,
    originalPrice: 2299,
    inStock: true,
    rating: 4.7,
    reviewsCount: 18,
    sku: 'VB-SLW-102',
    images: [banner2, banner1, banner3, banner2],
    sizes: ['M', 'L', 'XL'],
    description: 'A charming avocado green salwar suit set crafted with rich slub silk. Perfectly blend grace and fashion for any festive or casual gathering.',
    specifications: {
      fabric: 'Slub Silk',
      topLength: '42 Inches',
      bottomType: 'Comfort Pants',
      dupattaFabric: 'Organza',
      washCare: 'Dry Clean Only'
    }
  },
  {
    id: 3,
    name: 'Mustard 3 Piece Set - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    inStock: true,
    rating: 4.9,
    reviewsCount: 31,
    sku: 'VB-3PS-103',
    images: [banner3, banner2, banner1, banner3],
    sizes: ['S', 'M', 'L'],
    description: 'Vibrant mustard yellow 3-piece suit featuring intricate neckline work and lightweight silk cotton fabric.',
    specifications: {
      fabric: 'Slub Silk Cotton',
      topLength: '44 Inches',
      bottomType: 'Palazzo Pants',
      dupattaFabric: 'Silk Blend',
      washCare: 'Hand Wash / Dry Clean'
    }
  },
  {
    id: 4,
    name: 'Royal Magenta Anarkali Suit Set with Dupatta',
    category: 'Anarkali Suits',
    price: 2499,
    originalPrice: 3199,
    inStock: true,
    rating: 5.0,
    reviewsCount: 42,
    sku: 'VB-ANK-104',
    images: [banner1, banner3, banner2, banner1],
    sizes: ['L', 'XL', 'XXL'],
    description: 'Regal magenta flared Anarkali suit set with heavy Dupatta. Designed to make a stunning statement at weddings and grand celebrations.',
    specifications: {
      fabric: 'Georgette Silk',
      topLength: '52 Inches',
      bottomType: 'Churidar',
      dupattaFabric: 'Net Embroidery',
      washCare: 'Dry Clean Only'
    }
  },
  {
    id: 5,
    name: 'Handcrafted Festive Silk Kurti Set',
    category: 'Slub Silk',
    price: 1999,
    originalPrice: 2599,
    inStock: true,
    rating: 4.6,
    reviewsCount: 15,
    sku: 'VB-SLK-105',
    images: [banner2, banner3, banner1, banner2],
    sizes: ['M', 'L', 'XL'],
    description: 'Elegant handcrafted festive kurti set in premium slub silk with subtle zari highlights.',
    specifications: {
      fabric: 'Pure Slub Silk',
      topLength: '44 Inches',
      bottomType: 'Straight Pants',
      dupattaFabric: 'Silk Dupatta',
      washCare: 'Dry Clean Only'
    }
  },
  {
    id: 6,
    name: 'Elegance Emerald Green Salwar Suit',
    category: 'Salwar Sets',
    price: 1899,
    originalPrice: 2399,
    inStock: true,
    rating: 4.8,
    reviewsCount: 29,
    sku: 'VB-SLW-106',
    images: [banner3, banner1, banner2, banner3],
    sizes: ['S', 'M', 'L', 'XXL'],
    description: 'Sophisticated emerald green salwar set with artistic border detailing and breathable cotton lining.',
    specifications: {
      fabric: 'Cotton Silk Blend',
      topLength: '43 Inches',
      bottomType: 'Salwar',
      dupattaFabric: 'Chiffon',
      washCare: 'Dry Clean Only'
    }
  }
];

const ProductDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isInWishlist } = useWishlist();

  const [product, setProduct] = useState(null);
  const [selectedImgIndex, setSelectedImgIndex] = useState(0);
  const [selectedSize, setSelectedSize] = useState('');
  const [quantity, setQuantity] = useState(1);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);
  const [lightboxImg, setLightboxImg] = useState(null);
  const [activeTab, setActiveTab] = useState('description');
  const [sizeError, setSizeError] = useState(false);

  // Dynamic Product Fetching
  useEffect(() => {
    const foundProduct = mockProducts.find(p => p.id === parseInt(id, 10));
    if (foundProduct) {
      setProduct(foundProduct);
      setSelectedImgIndex(0);
      if (foundProduct.sizes && foundProduct.sizes.length > 0) {
        setSelectedSize(foundProduct.sizes[0]);
      }
    } else {
      setProduct(null);
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }, [id]);

  if (!product) {
    return (
      <div className="product-not-found-container">
        <div className="container text-center py-5">
          <h2>Product Not Found</h2>
          <p>Sorry, the product you are looking for does not exist or has been removed.</p>
          <button className="btn-primary-purple" onClick={() => navigate('/collections')}>
            Back to Collections
          </button>
        </div>
      </div>
    );
  }

  const isWishlisted = isInWishlist(product.id);

  const discountPercent = product.originalPrice > product.price
    ? Math.round(((product.originalPrice - product.price) / product.originalPrice) * 100)
    : 0;

  const handleQuantityChange = (type) => {
    if (type === 'decrease' && quantity > 1) {
      setQuantity(prev => prev - 1);
    } else if (type === 'increase' && quantity < 10) {
      setQuantity(prev => prev + 1);
    }
  };

  const handleAddToCart = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    if (!selectedSize) {
      setSizeError(true);
      return;
    }
    setSizeError(false);
    addToCart({ ...product, image: product.images[0] || banner1 }, quantity);
  };

  const handleBuyNow = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    if (!selectedSize) {
      setSizeError(true);
      return;
    }
    setSizeError(false);
    addToCart({ ...product, image: product.images[0] || banner1 }, quantity);
    navigate('/cart');
  };

  const handleToggleWishlist = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlist(product);
  };

  const openLightbox = (imgUrl, idx = null) => {
    if (idx !== null) {
      setSelectedImgIndex(idx);
    }
    setLightboxImg(imgUrl);
    setIsLightboxOpen(true);
  };

  return (
    <div className="product-details-page">
      {/* Breadcrumb Bar */}
      <div className="container">
        <nav className="product-breadcrumb">
          <Link to="/">Home</Link>
          <span className="separator">/</span>
          <Link to="/collections">Collections</Link>
          <span className="separator">/</span>
          <span className="current">{product.name}</span>
        </nav>
      </div>

      <div className="container product-details-layout">
        {/* ========================================================= */}
        {/* LEFT COLUMN: IMAGE GALLERY                                */}
        {/* ========================================================= */}
        <div className="product-gallery-container">
          {/* Main Default Image */}
          <div
            className="main-image-wrapper"
            onClick={() => openLightbox(product.images[selectedImgIndex] || banner1)}
          >
            <img
              src={product.images[selectedImgIndex] || banner1}
              alt={product.name}
              className="main-product-img"
            />
            {discountPercent > 0 && (
              <span className="detail-discount-tag">{discountPercent}% OFF</span>
            )}
            <div className="zoom-hint">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                <line x1="11" y1="8" x2="11" y2="14"></line>
                <line x1="8" y1="11" x2="14" y2="11"></line>
              </svg>
              <span>Click to enlarge</span>
            </div>
          </div>

          {/* Additional Thumbnails Strip (Click opens in Dialog Box) */}
          <div className="thumbnails-strip">
            {product.images.map((img, idx) => (
              <button
                key={idx}
                className={`thumbnail-btn ${selectedImgIndex === idx ? 'active' : ''}`}
                onClick={() => openLightbox(img, idx)}
                title="Click to open in dialog box"
              >
                <img src={img} alt={`${product.name} view ${idx + 1}`} />
              </button>
            ))}
          </div>
        </div>

        {/* ========================================================= */}
        {/* RIGHT COLUMN: PRODUCT DETAILS & ACTIONS                  */}
        {/* ========================================================= */}
        <div className="product-info-container">
          <span className="product-category-badge">{product.category}</span>
          <h1 className="product-main-title">{product.name}</h1>

          <div className="product-meta-row">
            <span className="product-sku">SKU: {product.sku}</span>
            <span className="stock-status in-stock">
              <span className="status-dot"></span> In Stock
            </span>
          </div>

          {/* Pricing Section */}
          <div className="product-price-box">
            <span className="current-price">Rs. {product.price.toLocaleString('en-IN')}.00</span>
            {product.originalPrice > product.price && (
              <span className="strikethrough-price">Rs. {product.originalPrice.toLocaleString('en-IN')}.00</span>
            )}
            {discountPercent > 0 && (
              <span className="savings-badge">Save ₹{(product.originalPrice - product.price).toLocaleString('en-IN')}</span>
            )}
          </div>

          <p className="short-description">{product.description}</p>

          {/* Size Selector */}
          <div className="size-selector-section">
            <div className="size-header">
              <span className="size-label">Select Size:</span>
              {sizeError && <span className="size-error-msg">* Please select a size</span>}
            </div>
            <div className="size-options-row">
              {product.sizes.map((size) => (
                <button
                  key={size}
                  className={`size-btn ${selectedSize === size ? 'active' : ''}`}
                  onClick={() => {
                    setSelectedSize(size);
                    setSizeError(false);
                  }}
                >
                  {size}
                </button>
              ))}
            </div>
          </div>

          {/* Quantity Selector */}
          <div className="quantity-section">
            <span className="quantity-label">Quantity:</span>
            <div className="quantity-stepper">
              <button onClick={() => handleQuantityChange('decrease')} disabled={quantity <= 1}>-</button>
              <span>{quantity}</span>
              <button onClick={() => handleQuantityChange('increase')} disabled={quantity >= 10}>+</button>
            </div>
          </div>

          {/* Action Buttons: Add to Cart, Buy Now & Wishlist */}
          <div className="product-action-buttons">
            <button className="btn-add-to-cart" onClick={handleAddToCart}>
              Add to Cart
            </button>
            <button className="btn-buy-now-detail" onClick={handleBuyNow}>
              Buy Now
            </button>
            <button
              className={`wishlist-toggle-btn ${isWishlisted ? 'active' : ''}`}
              onClick={handleToggleWishlist}
              aria-label="Wishlist"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </button>
          </div>

          {/* Details Accordion / Tabs */}
          <div className="product-tabs-container">
            <div className="tabs-header">
              <button
                className={`tab-btn ${activeTab === 'description' ? 'active' : ''}`}
                onClick={() => setActiveTab('description')}
              >
                Product Details
              </button>
              <button
                className={`tab-btn ${activeTab === 'specifications' ? 'active' : ''}`}
                onClick={() => setActiveTab('specifications')}
              >
                Specifications
              </button>
            </div>

            <div className="tab-content">
              {activeTab === 'description' && (
                <div className="tab-pane">
                  <p>{product.description}</p>
                </div>
              )}

              {activeTab === 'specifications' && (
                <div className="tab-pane">
                  <table className="specs-table">
                    <tbody>
                      <tr>
                        <td>Fabric</td>
                        <td>{product.specifications.fabric}</td>
                      </tr>
                      <tr>
                        <td>Top Length</td>
                        <td>{product.specifications.topLength}</td>
                      </tr>
                      <tr>
                        <td>Bottom Style</td>
                        <td>{product.specifications.bottomType}</td>
                      </tr>
                      <tr>
                        <td>Dupatta</td>
                        <td>{product.specifications.dupattaFabric}</td>
                      </tr>
                      <tr>
                        <td>Wash Care</td>
                        <td>{product.specifications.washCare}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* ========================================================= */}
      {/* FULLSCREEN IMAGE DIALOG BOX WITH CLOSE BUTTON             */}
      {/* ========================================================= */}
      {isLightboxOpen && (
        <div className="lightbox-modal-overlay" onClick={() => setIsLightboxOpen(false)}>
          <div className="lightbox-modal-content" onClick={(e) => e.stopPropagation()}>
            <button className="lightbox-close-btn" onClick={() => setIsLightboxOpen(false)} aria-label="Close Lightbox">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
              </svg>
            </button>
            <img
              src={lightboxImg || product.images[selectedImgIndex] || banner1}
              alt={product.name}
              className="lightbox-img"
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetails;
