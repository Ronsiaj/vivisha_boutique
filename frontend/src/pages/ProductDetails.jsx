import React, { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, useNavigate, Link, useLocation } from 'react-router-dom';
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';
import { useCart } from '../context/CartContext.jsx';
import { useWishlist } from '../context/WishlistContext.jsx';
import { useAuth } from '../context/AuthContext.jsx';
import { useToast } from '../context/ToastContext.jsx';
import { groupVariantsByProduct, formatImageUrl } from '../utils/productGrouping.js';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';
const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';
const fallbackImages = [banner1, banner2, banner3];

const ProductDetails = () => {
  const { id } = useParams(); // Can be initial variant ID or requested ID
  const navigate = useNavigate();
  const location = useLocation();
  const { isAuthenticated } = useAuth();
  const { addToCart } = useCart();
  const { toggleWishlist, isInWishlist } = useWishlist();
  const { showToast } = useToast();

  const [initialVariant, setInitialVariant] = useState(null);
  const [productVariants, setProductVariants] = useState([]);
  const [selectedColorId, setSelectedColorId] = useState(null);
  const [selectedSizeId, setSelectedSizeId] = useState(null);

  const [isLoading, setIsLoading] = useState(true);
  const [selectedImgIndex, setSelectedImgIndex] = useState(0);
  const [quantity, setQuantity] = useState(1);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);
  const [lightboxImg, setLightboxImg] = useState(null);
  const [isProductDetailsOpen, setIsProductDetailsOpen] = useState(false);

  // Related Products state & carousel controls
  const [relatedProducts, setRelatedProducts] = useState([]);
  const [isRelatedLoading, setIsRelatedLoading] = useState(false);
  const [addingCardId, setAddingCardId] = useState(null);
  const [addedCardId, setAddedCardId] = useState(null);
  const relatedTrackRef = useRef(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(false);

  const updateRelatedScrollState = () => {
    if (relatedTrackRef.current) {
      const { scrollLeft, scrollWidth, clientWidth } = relatedTrackRef.current;
      setCanScrollLeft(scrollLeft > 5);
      setCanScrollRight(scrollLeft + clientWidth < scrollWidth - 5);
    }
  };

  const handleScrollRelated = (direction) => {
    if (relatedTrackRef.current) {
      const card = relatedTrackRef.current.querySelector('.related-product-card');
      const scrollStep = card ? (card.offsetWidth + 20) * 2 : 500;
      relatedTrackRef.current.scrollBy({
        left: direction === 'left' ? -scrollStep : scrollStep,
        behavior: 'smooth'
      });
    }
  };

  useEffect(() => {
    const el = relatedTrackRef.current;
    if (el && relatedProducts.length > 0) {
      updateRelatedScrollState();
      el.addEventListener('scroll', updateRelatedScrollState, { passive: true });
      window.addEventListener('resize', updateRelatedScrollState);
      return () => {
        el.removeEventListener('scroll', updateRelatedScrollState);
        window.removeEventListener('resize', updateRelatedScrollState);
      };
    }
  }, [relatedProducts]);

  // Prevent background scrolling and handle Escape key for image viewer / lightbox
  useEffect(() => {
    if (isLightboxOpen) {
      const originalOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';

      const handleKeyDown = (e) => {
        if (e.key === 'Escape') {
          setIsLightboxOpen(false);
        }
      };

      window.addEventListener('keydown', handleKeyDown);

      return () => {
        document.body.style.overflow = originalOverflow;
        window.removeEventListener('keydown', handleKeyDown);
      };
    }
  }, [isLightboxOpen]);

  // 1. Fetch initial variant details from /varient/view.php?id={id} with product_id fallback
  useEffect(() => {
    let isCancelled = false;
    const fetchVariantDetails = async () => {
      setIsLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/varient/view.php?id=${id}`);
        const data = await response.json();
        if (!isCancelled && data.status && data.data && data.data.variant) {
          const v = data.data.variant;
          setInitialVariant(v);
          setSelectedColorId(v.color?.id ?? null);
          setSelectedSizeId(v.size?.id ?? null);
          setSelectedImgIndex(0);

          // 2. Fetch all variants belonging to this parent product
          const productId = v.product?.id || v.product_id;
          if (productId) {
            try {
              const listRes = await fetch(`${API_BASE_URL}/varient/list.php?product_id=${productId}&limit=100`);
              const listData = await listRes.json();
              if (!isCancelled && listData.status && listData.data && Array.isArray(listData.data.variants)) {
                setProductVariants(listData.data.variants);
              } else {
                setProductVariants([v]);
              }
            } catch (pErr) {
              console.error("Error fetching sibling variants:", pErr);
              if (!isCancelled) setProductVariants([v]);
            }
          } else {
            setProductVariants([v]);
          }
        } else if (!isCancelled) {
          // If viewing by product ID instead of variant ID
          try {
            const listRes = await fetch(`${API_BASE_URL}/varient/list.php?product_id=${id}&limit=100`);
            const listData = await listRes.json();
            if (!isCancelled && listData.status && listData.data && Array.isArray(listData.data.variants) && listData.data.variants.length > 0) {
              const vList = listData.data.variants;
              const firstVar = vList[0];
              setInitialVariant(firstVar);
              setSelectedColorId(firstVar.color?.id ?? null);
              setSelectedSizeId(firstVar.size?.id ?? null);
              setSelectedImgIndex(0);
              setProductVariants(vList);
              return;
            }
          } catch (pErr) {
            console.error("Error fetching product variants fallback:", pErr);
          }

          setInitialVariant(null);
          setProductVariants([]);
        }
      } catch (err) {
        console.error("Error fetching variant details:", err);
        if (!isCancelled) {
          // Fallback to fetch by product_id if id was not a variant ID
          try {
            const listRes = await fetch(`${API_BASE_URL}/varient/list.php?product_id=${id}&limit=100`);
            const listData = await listRes.json();
            if (!isCancelled && listData.status && listData.data && Array.isArray(listData.data.variants) && listData.data.variants.length > 0) {
              const vList = listData.data.variants;
              const firstVar = vList[0];
              setInitialVariant(firstVar);
              setSelectedColorId(firstVar.color?.id ?? null);
              setSelectedSizeId(firstVar.size?.id ?? null);
              setSelectedImgIndex(0);
              setProductVariants(vList);
              return;
            }
          } catch (pErr) {
            console.error("Error fetching product variants fallback:", pErr);
          }

          setInitialVariant(null);
          setProductVariants([]);
        }
      } finally {
        if (!isCancelled) setIsLoading(false);
      }
    };

    fetchVariantDetails();
    return () => {
      isCancelled = true;
    };
  }, [id]);

  // Distinct Colors available for this parent product
  const availableColors = useMemo(() => {
    const colorMap = new Map();
    for (const v of productVariants) {
      if (v.color && v.color.id && !colorMap.has(v.color.id)) {
        const vHasStock = v.is_available === 1 && (v.stock?.available_quantity ?? 0) > 0 && v.stock?.stock_status !== 'out_of_stock';
        colorMap.set(v.color.id, {
          id: v.color.id,
          name: v.color.name,
          hex_code: v.color.hex_code,
          hasStock: vHasStock
        });
      }
    }
    return Array.from(colorMap.values());
  }, [productVariants]);

  // Variants filtered by selected color
  const variantsForSelectedColor = useMemo(() => {
    if (availableColors.length === 0) return productVariants;
    if (!selectedColorId) {
      // Default to first color if none selected
      const firstColorId = availableColors[0]?.id;
      return productVariants.filter(v => (v.color?.id === firstColorId) || (!v.color && !firstColorId));
    }
    return productVariants.filter(v => v.color?.id === selectedColorId);
  }, [productVariants, selectedColorId, availableColors]);

  // Valid sizes for currently selected color (ONLY actual sizes returned from API/database)
  const availableSizesForSelectedColor = useMemo(() => {
    return variantsForSelectedColor
      .filter((v) => v.size && v.size.name)
      .map((v) => {
        const stockQty = v.stock?.available_quantity ?? 0;
        const isOutOfStock = !v.is_available || stockQty <= 0 || v.stock?.stock_status === 'out_of_stock';
        return {
          size: v.size,
          variant: v,
          isOutOfStock,
          stockQty,
          price: parseFloat(v.pricing?.selling_price || 0)
        };
      })
      .sort((a, b) => (a.size?.sort_order ?? 0) - (b.size?.sort_order ?? 0));
  }, [variantsForSelectedColor]);

  // Resolve the Exact Active Variant based on selected color and size
  const activeVariant = useMemo(() => {
    if (!initialVariant) return null;
    if (variantsForSelectedColor.length === 0) return initialVariant;

    // Try finding exact match with selectedSizeId
    if (selectedSizeId) {
      const match = variantsForSelectedColor.find(v => v.size?.id === selectedSizeId);
      if (match) return match;
    }

    // Otherwise prefer first in-stock variant for this color, or first variant
    const inStock = variantsForSelectedColor.find(v => {
      const isAvail = v.is_available === 1;
      const qty = v.stock?.available_quantity ?? 0;
      const notOOS = v.stock?.stock_status !== 'out_of_stock';
      return isAvail && qty > 0 && notOOS;
    });

    return inStock || variantsForSelectedColor[0] || initialVariant;
  }, [variantsForSelectedColor, selectedSizeId, initialVariant]);

  // Sync selectedSizeId and selectedColorId when activeVariant changes
  useEffect(() => {
    if (activeVariant) {
      if (activeVariant.color?.id && activeVariant.color.id !== selectedColorId) {
        setSelectedColorId(activeVariant.color.id);
      }
      if (activeVariant.size?.id && activeVariant.size.id !== selectedSizeId) {
        setSelectedSizeId(activeVariant.size.id);
      }
    }
  }, [activeVariant]);

  // Color change handler
  const handleSelectColor = (colorId) => {
    setSelectedColorId(colorId);
    const colorVars = productVariants.filter(v => v.color?.id === colorId);
    // Find matching size or first available size
    const sameSizeMatch = colorVars.find(v => v.size?.id === selectedSizeId);
    if (sameSizeMatch) {
      setSelectedSizeId(sameSizeMatch.size?.id);
    } else {
      const inStock = colorVars.find(v => (v.stock?.available_quantity ?? 0) > 0 && v.is_available === 1);
      const target = inStock || colorVars[0];
      if (target) {
        setSelectedSizeId(target.size?.id ?? null);
      }
    }
    setSelectedImgIndex(0);
  };

  // Size change handler
  const handleSelectSize = (sizeId, targetVariant) => {
    setSelectedSizeId(sizeId);
    if (targetVariant?.color?.id) {
      setSelectedColorId(targetVariant.color.id);
    }
  };

  // Fetch and rank related products (grouped by parent product)
  useEffect(() => {
    if (!initialVariant || !initialVariant.id) return;

    const fetchRelatedProducts = async () => {
      setIsRelatedLoading(true);
      try {
        const response = await fetch(`${API_BASE_URL}/varient/list.php?limit=100`);
        const data = await response.json();

        if (data.status && data.data && Array.isArray(data.data.variants)) {
          const currentProductId = initialVariant.product?.id || initialVariant.product_id;
          const currentCategoryId = initialVariant.category?.id || initialVariant.product?.category_id;
          const currentCategoryName = (initialVariant.category?.name || '').toLowerCase();
          const currentProductName = (initialVariant.product?.name || initialVariant.variant_name || '').toLowerCase();

          // Group all variants into parent products
          const grouped = groupVariantsByProduct(data.data.variants);

          // Filter out the current active product
          const otherProducts = grouped.filter((p) => p.productId !== currentProductId);

          // Stop words to ignore during tokenization
          const stopWords = new Set([
            'and', 'the', 'with', 'for', 'in', 'a', 'an', 'of', 'by', 'to', 'at',
            'is', 'on', 'from', 'women', 'womens', 'women\'s', 'slub', 'exclusive',
            'premium', 'pure', 'new', 'latest', 'best', 'fashion', 'wear', 'collection', 'set'
          ]);

          const extractTokens = (str) => {
            return (str || '')
              .replace(/[^\w\s]/gi, ' ')
              .toLowerCase()
              .split(/\s+/)
              .filter((word) => word.length >= 3 && !stopWords.has(word));
          };

          const nameTokens = extractTokens(currentProductName);
          const categoryTokens = extractTokens(currentCategoryName);
          const allCurrentTokens = Array.from(new Set([...nameTokens, ...categoryTokens]));

          const scored = otherProducts.map((p) => {
            let score = 0;
            const pCategoryId = p.category?.id;
            const pName = (p.name || '').toLowerCase();
            const pDesc = (p.description || '').toLowerCase();
            const pNameTokens = new Set(extractTokens(pName));
            const pDescTokens = new Set(extractTokens(pDesc));

            for (const token of allCurrentTokens) {
              if (pNameTokens.has(token)) score += 40;
              else if (pName.includes(token)) score += 25;
              else if (pDescTokens.has(token)) score += 10;
            }

            if (currentCategoryId && pCategoryId && currentCategoryId === pCategoryId) {
              score += 30;
            }

            return { product: p, score };
          });

          const ranked = scored
            .filter((item) => item.score > 0)
            .sort((a, b) => b.score - a.score)
            .map((item) => item.product);

          const fallbackList = otherProducts.slice(0, 8);
          setRelatedProducts(ranked.length > 0 ? ranked.slice(0, 8) : fallbackList);
        }
      } catch (err) {
        console.error("Error fetching related products:", err);
      } finally {
        setIsRelatedLoading(false);
      }
    };

    fetchRelatedProducts();
  }, [initialVariant]);

  useEffect(() => {
    if (isLightboxOpen) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => {
      document.body.style.overflow = '';
    };
  }, [isLightboxOpen]);

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsLightboxOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  // Parse description into normal description text and structured Product Details (Label: Value)
  const { normalDescription, parsedProductDetails } = useMemo(() => {
    const rawDescription = activeVariant?.product?.description || activeVariant?.description || '';
    if (!rawDescription || typeof rawDescription !== 'string') {
      return { normalDescription: '', parsedProductDetails: [] };
    }

    const lines = rawDescription.split(/\r?\n/);
    const descLines = [];
    const details = [];

    for (const rawLine of lines) {
      const line = rawLine.trim();
      if (!line) {
        if (descLines.length > 0 && descLines[descLines.length - 1] !== '') {
          descLines.push('');
        }
        continue;
      }

      const colonIndex = line.indexOf(':');
      if (colonIndex > 0) {
        const label = line.slice(0, colonIndex).trim();
        const value = line.slice(colonIndex + 1).trim();

        if (label && value) {
          details.push({ label, value });
          continue;
        }
      }

      // Non-matching lines remain part of the normal description
      descLines.push(line);
    }

    return {
      normalDescription: descLines.join('\n').trim(),
      parsedProductDetails: details
    };
  }, [activeVariant]);

  const washCare = activeVariant?.wash_care || activeVariant?.product?.wash_care || activeVariant?.care_instructions || null;
  const whatYouWillReceive = activeVariant?.what_you_will_receive || activeVariant?.product?.what_you_will_receive || activeVariant?.package_contents || null;

  // Gallery Images for active variant (computed before early returns so the useEffect below is always called)
  const displayImages = (activeVariant?.images && activeVariant.images.length > 0)
    ? activeVariant.images
    : (activeVariant?.primary_image ? [activeVariant.primary_image] : fallbackImages);

  // Keyboard navigation for lightbox (must be before early returns to satisfy Rules of Hooks)
  useEffect(() => {
    if (!isLightboxOpen) return;
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') {
        setIsLightboxOpen(false);
      } else if (e.key === 'ArrowLeft' && displayImages.length > 1) {
        setSelectedImgIndex((prev) => {
          const nextIdx = (prev - 1 + displayImages.length) % displayImages.length;
          setLightboxImg(formatImageUrl(displayImages[nextIdx], banner1));
          return nextIdx;
        });
      } else if (e.key === 'ArrowRight' && displayImages.length > 1) {
        setSelectedImgIndex((prev) => {
          const nextIdx = (prev + 1) % displayImages.length;
          setLightboxImg(formatImageUrl(displayImages[nextIdx], banner1));
          return nextIdx;
        });
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isLightboxOpen, displayImages]);


  if (isLoading) {
    return (
      <div className="product-details-page" style={{ minHeight: '60vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
        <h2>Loading Product...</h2>
      </div>
    );
  }

  if (!activeVariant) {
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

  const isWishlisted = isInWishlist(activeVariant.id);

  // Exact Variant-Aware Pricing
  const sellPrice = parseFloat(activeVariant.pricing?.selling_price || 0);
  const origPrice = parseFloat(activeVariant.pricing?.original_price || 0);
  const hasDiscount = origPrice > sellPrice;
  const discountPercent = hasDiscount
    ? Math.round(((origPrice - sellPrice) / origPrice) * 100)
    : 0;

  // Exact Variant-Aware Stock
  const stockQty = activeVariant.stock?.available_quantity ?? 0;
  const isOutOfStock = !activeVariant.is_available ||
    activeVariant.is_available === 0 ||
    stockQty <= 0 ||
    activeVariant.stock?.stock_status === 'out_of_stock';
  const isLowStock = !isOutOfStock && stockQty <= (activeVariant.stock?.low_stock_limit ?? 5);

  const maxAllowedQty = isOutOfStock ? 0 : Math.min(stockQty, 100);

  const handleQuantityChange = (type) => {
    if (isOutOfStock) return;
    if (type === 'decrease' && quantity > 1) {
      setQuantity((prev) => prev - 1);
    } else if (type === 'increase') {
      if (quantity < maxAllowedQty) {
        setQuantity((prev) => prev + 1);
      } else {
        if (maxAllowedQty >= 100 && stockQty >= 100) {
          alert('Quantity cannot exceed 100 per cart item.');
        } else {
          alert(`Only ${maxAllowedQty} items available in stock for this variant.`);
        }
      }
    }
  };

  const handleAddToCart = async () => {
    if (!activeVariant || !activeVariant.id) {
      alert('Please select a valid product variant.');
      return;
    }

    if (isOutOfStock) {
      alert('This variant is currently out of stock. Please select another size or color.');
      return;
    }

    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }

    const cartItem = {
      id: activeVariant.product?.id || activeVariant.product_id,
      name: `${activeVariant.product?.name || 'Product'}${activeVariant.color?.name ? ` - ${activeVariant.color.name}` : ''}${activeVariant.size?.name ? ` (${activeVariant.size.name})` : ''}`,
      price: sellPrice,
      image: activeVariant.primary_image ? formatImageUrl(activeVariant.primary_image.image) : '',
      variantId: activeVariant.id
    };
    await addToCart(cartItem, quantity);
  };

  const handleBuyNow = async () => {
    if (!activeVariant || !activeVariant.id) {
      alert('Please select a valid product variant.');
      return;
    }

    if (isOutOfStock) {
      alert('This variant is currently out of stock. Please select another size or color.');
      return;
    }

    const cartItem = {
      id: activeVariant.product?.id || activeVariant.product_id,
      name: `${activeVariant.product?.name || 'Product'}${activeVariant.color?.name ? ` - ${activeVariant.color.name}` : ''}${activeVariant.size?.name ? ` (${activeVariant.size.name})` : ''}`,
      price: sellPrice,
      image: activeVariant.primary_image ? formatImageUrl(activeVariant.primary_image.image) : '',
      variantId: activeVariant.id
    };

    if (!isAuthenticated) {
      const buyNowData = {
        buyNow: true,
        variantId: activeVariant.id,
        quantity: quantity,
        product: cartItem
      };
      sessionStorage.setItem('vivisha_buynow_pending', JSON.stringify(buyNowData));
      navigate('/login', {
        state: {
          from: location.pathname,
          buyNow: true,
          buyNowItem: buyNowData
        }
      });
      return;
    }

    const res = await addToCart(cartItem, quantity);
    if (res && res.success) {
      navigate('/cart');
    }
  };

  const handleToggleWishlist = () => {
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    toggleWishlist(activeVariant);
  };

  const copyProductUrlToClipboard = (url) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url)
        .then(() => {
          showToast({ message: 'Product link copied to clipboard!', type: 'success' });
        })
        .catch(() => {
          fallbackCopyTextToClipboard(url);
        });
    } else {
      fallbackCopyTextToClipboard(url);
    }
  };

  const fallbackCopyTextToClipboard = (text) => {
    try {
      const textArea = document.createElement("textarea");
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.top = '0';
      textArea.style.left = '0';
      textArea.style.opacity = '0';
      textArea.style.pointerEvents = 'none';
      document.body.appendChild(textArea);
      textArea.focus();
      textArea.select();
      const successful = document.execCommand('copy');
      document.body.removeChild(textArea);
      if (successful) {
        showToast({ message: 'Product link copied to clipboard!', type: 'success' });
      } else {
        showToast({ message: 'Could not copy link.', type: 'error' });
      }
    } catch (err) {
      console.error('Fallback copy failed', err);
      showToast({ message: 'Could not copy link.', type: 'error' });
    }
  };

  const handleShare = async () => {
    const currentUrl = activeVariant?.id
      ? `${window.location.origin}/product/${activeVariant.id}`
      : window.location.href;
    const productName = activeVariant?.product?.name || 'Handcrafted Ethnic Wear';
    const priceStr = sellPrice ? `Rs. ${sellPrice.toLocaleString('en-IN')}.00` : '';
    const shareTitle = `${productName} | Vivisha Boutique`;
    const shareText = `Check out "${productName}" ${priceStr ? `(${priceStr}) ` : ''}at Vivisha Boutique`;

    // 1. Try Native Web Share API (Mobile Android, iOS Safari & supported desktop browsers)
    if (navigator.share) {
      try {
        await navigator.share({
          title: shareTitle,
          text: shareText,
          url: currentUrl
        });
        return;
      } catch (err) {
        // If user cancelled the share dialog, do not display error
        if (err.name === 'AbortError') {
          return;
        }
        console.warn('Native Web Share failed, falling back to clipboard copy:', err);
      }
    }

    // 2. Fallback to copying URL to clipboard
    copyProductUrlToClipboard(currentUrl);
  };

  const handlePrevImage = (e) => {
    if (e) e.stopPropagation();
    if (displayImages.length <= 1) return;
    const nextIdx = (selectedImgIndex - 1 + displayImages.length) % displayImages.length;
    setSelectedImgIndex(nextIdx);
    if (isLightboxOpen) {
      setLightboxImg(formatImageUrl(displayImages[nextIdx], banner1));
    }
  };

  const handleNextImage = (e) => {
    if (e) e.stopPropagation();
    if (displayImages.length <= 1) return;
    const nextIdx = (selectedImgIndex + 1) % displayImages.length;
    setSelectedImgIndex(nextIdx);
    if (isLightboxOpen) {
      setLightboxImg(formatImageUrl(displayImages[nextIdx], banner1));
    }
  };

  const openLightbox = (imgItem, idx = null) => {
    if (idx !== null) {
      setSelectedImgIndex(idx);
    }
    const targetItem = imgItem || displayImages[selectedImgIndex] || banner1;
    const resolvedUrl = formatImageUrl(targetItem, banner1);
    setLightboxImg(resolvedUrl);
    setIsLightboxOpen(true);
  };

  const handleRelatedCardAddToCart = async (relProduct, e) => {
    if (e) e.stopPropagation();
    if (relProduct.isOutOfStock) {
      return;
    }
    if (!isAuthenticated) {
      navigate('/login', { state: { from: location.pathname } });
      return;
    }
    const defVariant = relProduct.defaultVariant;
    if (!defVariant) return;

    setAddingCardId(relProduct.productId);
    const cartItem = {
      id: relProduct.productId,
      name: relProduct.name,
      price: parseFloat(defVariant.pricing?.selling_price || 0),
      image: defVariant.primary_image ? formatImageUrl(defVariant.primary_image.image) : '',
      variantId: defVariant.id
    };
    await addToCart(cartItem, 1);
    setAddingCardId(null);
    setAddedCardId(relProduct.productId);
    setTimeout(() => {
      setAddedCardId(null);
    }, 1800);
  };

  // productDetailsList, washCare, whatYouWillReceive are now declared above the early returns to satisfy React Rules of Hooks

  return (
    <div className="product-details-page">
      <div className="container">
        <nav className="product-breadcrumb">
          <Link to="/">Home</Link>
          <span className="separator">/</span>
          <Link to="/collections">Collections</Link>
          <span className="separator">/</span>
          <span className="current">{activeVariant.product?.name}</span>
        </nav>
      </div>

      <div className="container product-details-layout">
        {/* Left Column: Gallery */}
        <div className="product-gallery-container">
          <div
            className="main-image-wrapper"
            onClick={() => openLightbox(displayImages[selectedImgIndex] || banner1)}
            title="Click to enlarge image"
          >
            <img
              src={displayImages[selectedImgIndex] ? formatImageUrl(displayImages[selectedImgIndex], banner1) : banner1}
              alt={activeVariant.product?.name || 'Product'}
              className="main-product-img"
            />
            {discountPercent > 0 && (
              <span className="detail-discount-tag">{discountPercent}% OFF</span>
            )}

            {/* Navigation Arrows & Counter for Main Image when multiple images exist */}
            {displayImages.length > 1 && (
              <>
                <button
                  type="button"
                  className="gallery-nav-btn gallery-nav-prev"
                  onClick={handlePrevImage}
                  aria-label="Previous image"
                  title="Previous image"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                  </svg>
                </button>
                <button
                  type="button"
                  className="gallery-nav-btn gallery-nav-next"
                  onClick={handleNextImage}
                  aria-label="Next image"
                  title="Next image"
                >
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                  </svg>
                </button>
                <div className="gallery-image-counter">
                  {selectedImgIndex + 1} / {displayImages.length}
                </div>
              </>
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

          {displayImages.length > 1 && (
            <div className="thumbnails-strip">
              {displayImages.map((img, idx) => (
                <button
                  key={img.id || idx}
                  className={`thumbnail-btn ${selectedImgIndex === idx ? 'active' : ''}`}
                  onClick={() => setSelectedImgIndex(idx)}
                  title="Click to view"
                >
                  <img src={formatImageUrl(img, banner1)} alt={`${activeVariant.product?.name} view ${idx + 1}`} />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Right Column: Product Info, Options & Purchase Actions */}
        <div className="product-info-container">
          {activeVariant.category && (
            <span className="product-category-badge">{activeVariant.category.name}</span>
          )}

          <h1 className="product-main-title">
            {activeVariant.product?.name}
          </h1>

          {/* SKU, Optional Variant Name, and Live Stock Status Badge */}
          <div className="product-meta-row">
            <span className="product-sku">SKU: {activeVariant.sku || 'N/A'}</span>
            {activeVariant?.variant_name && activeVariant.variant_name.trim() !== '' && (
              <span className="product-variant-name">
                Variant: <strong>{activeVariant.variant_name.trim()}</strong>
              </span>
            )}
            <span className={`stock-status ${isOutOfStock ? 'out-of-stock' : (isLowStock ? `Low Stock (${stockQty} left)` : 'In Stock')}`}>
              <span className="status-dot"></span>
              {isOutOfStock ? 'Out of Stock' : (isLowStock ? `Low Stock (${stockQty} left)` : 'In Stock')}
            </span>
          </div>

          {/* Dynamic Variant-Aware Price Display */}
          <div className="product-price-box">
            <span className="current-price">Rs. {sellPrice.toLocaleString('en-IN')}.00</span>
            {hasDiscount && (
              <span className="strikethrough-price">Rs. {origPrice.toLocaleString('en-IN')}.00</span>
            )}
            {hasDiscount && (
              <span className="savings-badge">Save ₹{(origPrice - sellPrice).toLocaleString('en-IN')}</span>
            )}
          </div>

          {normalDescription && (
            <p className="short-description" style={{ whiteSpace: 'pre-line' }}>
              {normalDescription}
            </p>
          )}

          {/* 1. COLOR SELECTOR */}
          {availableColors.length > 0 && (
            <div className="color-selector-section" style={{ marginBottom: '1.25rem' }}>
              <div className="color-header" style={{ marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '8px' }}>
                <span className="color-label" style={{ fontWeight: '600', color: '#333' }}>Color:</span>
                <span style={{ fontWeight: '700', color: 'var(--primary-color)' }}>
                  {activeVariant?.color?.name || availableColors[0]?.name}
                </span>
              </div>
              <div className="color-options-row" style={{ display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
                {availableColors.map((color) => {
                  const isColorSelected = color.id === selectedColorId;
                  const colorHasStock = productVariants.some(
                    (v) => v.color?.id === color.id && (v.stock?.available_quantity ?? 0) > 0 && v.is_available === 1 && v.stock?.stock_status !== 'out_of_stock'
                  );
                  return (
                    <button
                      key={color.id}
                      type="button"
                      className={`color-swatch-btn ${isColorSelected ? 'active' : ''}`}
                      onClick={() => handleSelectColor(color.id)}
                      title={`${color.name} ${!colorHasStock ? '(Out of Stock in some sizes)' : ''}`}
                      style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: '6px',
                        padding: '6px 14px',
                        borderRadius: '20px',
                        border: isColorSelected ? '2px solid var(--primary-color)' : '1px solid #d1d5db',
                        background: isColorSelected ? '#faf5ff' : '#ffffff',
                        cursor: 'pointer',
                        opacity: colorHasStock ? 1 : 0.65,
                        transition: 'all 0.2s ease'
                      }}
                    >
                      <span
                        style={{
                          width: '16px',
                          height: '16px',
                          borderRadius: '50%',
                          backgroundColor: color.hex_code || '#ccc',
                          border: '1px solid rgba(0,0,0,0.15)',
                          display: 'inline-block'
                        }}
                      />
                      <span style={{ fontSize: '0.88rem', fontWeight: isColorSelected ? '700' : '500', color: isColorSelected ? 'var(--primary-color)' : '#374151' }}>
                        {color.name}
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          {/* 2. SIZE SELECTOR (Strictly dependent on selected color) */}
          {availableSizesForSelectedColor.length > 0 && (
            <div className="size-selector-section">
              <div className="size-header">
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  <span className="size-label">Size:</span>
                  <span style={{ fontWeight: '700', color: 'var(--primary-color, #A049A3)' }}>
                    {activeVariant?.size?.name || availableSizesForSelectedColor[0]?.size?.name}
                  </span>
                </div>
              </div>
              <div className="size-options-row">
                {availableSizesForSelectedColor.map(({ size, variant: sVariant, isOutOfStock: sOOS }) => {
                  const isSizeSelected = sVariant.id === activeVariant?.id;
                  return (
                    <button
                      key={sVariant.id || size?.id}
                      type="button"
                      className={`size-btn ${isSizeSelected ? 'active' : ''} ${sOOS ? 'out-of-stock' : ''}`}
                      onClick={() => handleSelectSize(size?.id, sVariant)}
                      title={sOOS ? `${size.name} (Out of Stock)` : size.name}
                    >
                      {size.name}
                    </button>
                  );
                })}
              </div>
            </div>
          )}

          {/* Quantity Stepper */}
          <div className="quantity-section">
            <span className="quantity-label">Quantity:</span>
            <div className="quantity-stepper">
              <button
                type="button"
                onClick={() => handleQuantityChange('decrease')}
                disabled={isOutOfStock || quantity <= 1}
              >-</button>
              <span>{isOutOfStock ? 0 : quantity}</span>
              <button
                type="button"
                onClick={() => handleQuantityChange('increase')}
                disabled={isOutOfStock || quantity >= maxAllowedQty}
              >+</button>
            </div>
            {!isOutOfStock && (
              <span style={{ marginLeft: '15px', fontSize: '13px', color: '#666' }}>
                {stockQty} available in this variant
              </span>
            )}
          </div>

          {/* Purchase Action Buttons */}
          <div className="product-action-buttons">
            <button
              type="button"
              className="btn-add-to-cart"
              onClick={handleAddToCart}
              disabled={isOutOfStock}
              style={{
                opacity: isOutOfStock ? 0.6 : 1,
                cursor: isOutOfStock ? 'not-allowed' : 'pointer'
              }}
            >
              Add to Cart
            </button>

            {isOutOfStock ? (
              <button
                type="button"
                className="btn-out-of-stock-detail"
                disabled
                aria-disabled="true"
                style={{
                  background: '#9ca3af',
                  color: '#ffffff',
                  cursor: 'not-allowed',
                  border: 'none',
                  padding: '12px 24px',
                  borderRadius: '6px',
                  fontWeight: '700'
                }}
              >
                Out of Stock
              </button>
            ) : (
              <button
                type="button"
                className="btn-buy-now-detail"
                onClick={handleBuyNow}
              >
                Buy Now
              </button>
            )}

            <button
              type="button"
              className={`wishlist-toggle-btn ${isWishlisted ? 'active' : ''}`}
              onClick={handleToggleWishlist}
              aria-label="Wishlist"
              title={isWishlisted ? "Remove from Wishlist" : "Add to Wishlist"}
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill={isWishlisted ? '#A049A3' : 'none'} stroke={isWishlisted ? '#A049A3' : '#333333'} strokeWidth="2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
              </svg>
            </button>

            <button
              type="button"
              className="share-toggle-btn"
              onClick={handleShare}
              aria-label="Share Product"
              title="Share Product"
            >
              <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#333333" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="18" cy="5" r="3"></circle>
                <circle cx="6" cy="12" r="3"></circle>
                <circle cx="18" cy="19" r="3"></circle>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
              </svg>
            </button>
          </div>

          {/* 1. Product Details Section (Expandable / Collapsible) */}
          {parsedProductDetails.length > 0 && (
            <div className={`product-specifications-section accordion-section ${isProductDetailsOpen ? 'open' : 'collapsed'}`}>
              <button
                type="button"
                className="specifications-accordion-btn"
                onClick={() => setIsProductDetailsOpen((prev) => !prev)}
                aria-expanded={isProductDetailsOpen}
                aria-controls="product-details-accordion-body"
              >
                <span className="specifications-heading" style={{ margin: 0 }}>Product Details</span>
                <span className={`accordion-chevron-icon ${isProductDetailsOpen ? 'open' : ''}`}>
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                  </svg>
                </span>
              </button>

              <div
                id="product-details-accordion-body"
                className={`specifications-accordion-content ${isProductDetailsOpen ? 'expanded' : 'collapsed'}`}
              >
                <div className="specifications-grid">
                  {parsedProductDetails.map((item, idx) => (
                    <div key={idx} className="specification-row">
                      <span className="spec-label">{item.label}</span>
                      <span className="spec-value">{item.value}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* 2. Wash & Care (Only if existing API data is available) */}
          {washCare && (
            <div className="product-specifications-section wash-care-section">
              <h3 className="specifications-heading">Wash & Care</h3>
              <p className="spec-text-content">{washCare}</p>
            </div>
          )}

          {/* 3. What You Will Receive (Only if existing API data is available) */}
          {whatYouWillReceive && (
            <div className="product-specifications-section receive-section">
              <h3 className="specifications-heading">What You Will Receive</h3>
              <p className="spec-text-content">{whatYouWillReceive}</p>
            </div>
          )}

          {/* 4. Important Note (Common frontend note for all products) */}
          <div className="product-important-note">
            <span className="note-icon">ℹ️</span>
            <div className="note-content">
              <strong className="note-title">Important Note:</strong>
              <p className="note-text">
                Color may vary slightly from the actual product due to camera, lighting, photography, and individual screen settings.
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* ---------------------------------------------------- */}
      {/* Grouped Related Products Section                     */}
      {/* ---------------------------------------------------- */}
      {isRelatedLoading ? (
        <section className="related-products-section">
          <div className="container">
            <div className="related-products-header">
              <div className="related-header-left">
                <h2 className="section-heading">You May Also Like</h2>
                <p className="section-subheading">Handpicked fresh arrivals crafted for your wardrobe</p>
              </div>
            </div>
            <div className="category-loading-skeleton">
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
              <div className="skeleton-card"></div>
            </div>
          </div>
        </section>
      ) : relatedProducts.length > 0 ? (
        <section className="related-products-section">
          <div className="container">
            <div className="related-products-header">
              <div className="related-header-left">
                <h2 className="section-heading">You May Also Like</h2>
                <p className="section-subheading">Handcrafted styles curated to complement your selection</p>
              </div>
              <div className="related-header-actions">
                <Link to="/collections" className="view-all-link">View All Products &rarr;</Link>
                {relatedProducts.length > 2 && (
                  <div className="related-nav-arrows">
                    <button
                      type="button"
                      className="related-nav-btn prev"
                      onClick={() => handleScrollRelated('left')}
                      disabled={!canScrollLeft}
                      aria-label="Previous products"
                    >
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                      </svg>
                    </button>
                    <button
                      type="button"
                      className="related-nav-btn next"
                      onClick={() => handleScrollRelated('right')}
                      disabled={!canScrollRight}
                      aria-label="Next products"
                    >
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                      </svg>
                    </button>
                  </div>
                )}
              </div>
            </div>

            <div className="related-products-carousel-container">
              <div className="related-products-track" ref={relatedTrackRef}>
                {relatedProducts.map((relProduct, index) => {
                  const defVariant = relProduct.defaultVariant;
                  const isRelWishlisted = defVariant ? isInWishlist(defVariant.id) : false;
                  const isNew = relProduct.is_new_arrival === 1;

                  return (
                    <div
                      key={relProduct.productId}
                      className="product-card related-product-card"
                      onClick={() => {
                        if (defVariant) {
                          navigate(`/product/${defVariant.id}`);
                        }
                      }}
                      style={{ cursor: 'pointer' }}
                    >
                      <div className="product-image-wrapper related-image-wrapper">
                        {isNew && <span className="product-new-badge">New</span>}

                        {/* Stock Badge Overlay */}
                        {relProduct.isOutOfStock && (
                          <span className="product-status-tag out-of-stock" style={{ position: 'absolute', top: '10px', left: '10px', background: '#dc2626', color: '#fff', fontSize: '0.72rem', padding: '3px 8px', borderRadius: '4px', fontWeight: 'bold', zIndex: 2 }}>
                            Out of Stock
                          </span>
                        )}

                        <img
                          src={relProduct.primaryImageUrl || fallbackImages[index % fallbackImages.length]}
                          alt={relProduct.name || 'Product'}
                          className="product-img product-img-primary related-product-img"
                          loading="lazy"
                          onError={(e) => {
                            e.target.onerror = null;
                            e.target.src = fallbackImages[index % fallbackImages.length];
                          }}
                        />

                        {relProduct.hoverImageUrl && (
                          <img
                            src={relProduct.hoverImageUrl}
                            alt={`${relProduct.name || 'Product'} alternate view`}
                            className="product-img product-img-hover related-product-img"
                            loading="lazy"
                            onError={(e) => {
                              e.currentTarget.style.display = 'none';
                            }}
                          />
                        )}

                        {/* 3 Action Buttons on Right Side */}
                        <div className="product-hover-actions" onClick={(e) => e.stopPropagation()}>
                          {/* 1. Wishlist */}
                          <button
                            type="button"
                            className={`product-action-btn wishlist-action-btn ${isRelWishlisted ? 'active' : ''}`}
                            aria-label={isRelWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'}
                            title={isRelWishlisted ? 'In Wishlist' : 'Add to Wishlist'}
                            onClick={(e) => {
                              e.stopPropagation();
                              if (!isAuthenticated) {
                                navigate('/login', { state: { from: location.pathname } });
                                return;
                              }
                              if (defVariant) toggleWishlist(defVariant);
                            }}
                          >
                            <svg
                              width="17"
                              height="17"
                              viewBox="0 0 24 24"
                              fill={isRelWishlisted ? '#A049A3' : 'none'}
                              stroke={isRelWishlisted ? '#A049A3' : 'currentColor'}
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                          </button>

                          {/* 2. View Details */}
                          <button
                            type="button"
                            className="product-action-btn view-action-btn"
                            aria-label="View Product Details"
                            title="View Details"
                            onClick={(e) => {
                              e.stopPropagation();
                              if (defVariant) navigate(`/product/${defVariant.id}`);
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

                          {/* 3. Add to Cart */}
                          <button
                            type="button"
                            className={`product-action-btn cart-action-btn ${addedCardId === relProduct.productId ? 'added' : ''}`}
                            aria-label="Add to Cart"
                            title={relProduct.isOutOfStock ? 'Out of Stock' : (addedCardId === relProduct.productId ? 'Added to Cart!' : 'Add to Cart')}
                            disabled={addingCardId === relProduct.productId || relProduct.isOutOfStock}
                            onClick={(e) => handleRelatedCardAddToCart(relProduct, e)}
                            style={{
                              opacity: relProduct.isOutOfStock ? 0.5 : 1,
                              cursor: relProduct.isOutOfStock ? 'not-allowed' : 'pointer'
                            }}
                          >
                            {addedCardId === relProduct.productId ? (
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

                      <div className="product-info related-product-info">
                        <h4 className="product-title related-product-title" title={relProduct.name}>{relProduct.name}</h4>

                        {/* Color swatches preview row */}
                        <div className="related-colors-row">
                          {relProduct.colors && relProduct.colors.length > 1 ? (
                            <div style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                              {relProduct.colors.map((c) => (
                                <span
                                  key={c.id}
                                  title={c.name}
                                  style={{
                                    width: '12px',
                                    height: '12px',
                                    borderRadius: '50%',
                                    backgroundColor: c.hex_code || '#ccc',
                                    border: '1px solid rgba(0,0,0,0.15)',
                                    display: 'inline-block'
                                  }}
                                />
                              ))}
                              <span style={{ fontSize: '11px', color: '#6b7280', marginLeft: '4px' }}>
                                {relProduct.colors.length} colors
                              </span>
                            </div>
                          ) : null}
                        </div>

                        <div className="product-pricing related-product-pricing">
                          <span className="price">
                            {relProduct.hasPriceRange
                              ? `From Rs. ${relProduct.minSellingPrice.toLocaleString('en-IN')}.00`
                              : `Rs. ${relProduct.minSellingPrice.toLocaleString('en-IN')}.00`}
                          </span>
                          {relProduct.hasDiscount && (
                            <span className="original-price">
                              Rs. {relProduct.minOriginalPrice.toLocaleString('en-IN')}.00
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          </div>
        </section>
      ) : null}

      {/* Full-Screen Image Viewer / Lightbox */}
      {isLightboxOpen && (
        <div
          className="image-viewer-backdrop"
          onClick={() => setIsLightboxOpen(false)}
          role="dialog"
          aria-modal="true"
          aria-label="Enlarged product image viewer"
        >
          {/* Fixed Close (×) Button in the Top-Right Corner */}
          <button
            type="button"
            className="image-viewer-close-btn"
            onClick={(e) => {
              e.stopPropagation();
              setIsLightboxOpen(false);
            }}
            aria-label="Close image viewer"
            title="Close (Esc)"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"></line>
              <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>

          {/* Navigation Arrows for Lightbox when multiple images exist */}
          {displayImages.length > 1 && (
            <>
              <button
                type="button"
                className="image-viewer-nav-btn image-viewer-nav-prev"
                onClick={handlePrevImage}
                aria-label="Previous image"
                title="Previous (Left Arrow)"
              >
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
              </button>
              <button
                type="button"
                className="image-viewer-nav-btn image-viewer-nav-next"
                onClick={handleNextImage}
                aria-label="Next image"
                title="Next (Right Arrow)"
              >
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
              </button>
              <div className="image-viewer-counter">
                {selectedImgIndex + 1} / {displayImages.length}
              </div>
            </>
          )}

          {/* Centered Image Container */}
          <div className="image-viewer-container" onClick={(e) => e.stopPropagation()}>
            <img
              src={lightboxImg || banner1}
              alt={activeVariant?.product?.name || 'Enlarged Product View'}
              className="image-viewer-img"
              onError={(e) => {
                e.target.onerror = null;
                e.target.src = banner1;
              }}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default ProductDetails;
