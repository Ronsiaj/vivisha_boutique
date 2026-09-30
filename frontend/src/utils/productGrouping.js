/**
 * Utility functions for grouping variants under their parent product
 * adhering to real-world e-commerce product catalog presentation standards.
 */

const ASSET_BASE_URL = import.meta.env.VITE_ASSET_BASE_URL || 'http://localhost/vivisha_boutique/backend/';

/**
 * Format image URL safely
 */
export const formatImageUrl = (imgPath, fallback = '') => {
  if (!imgPath) return fallback;
  if (typeof imgPath === 'object' && imgPath !== null) {
    imgPath = imgPath.image_path || imgPath.image || imgPath.url || imgPath.path || imgPath.src || '';
  }
  if (!imgPath || typeof imgPath !== 'string') return fallback;
  if (imgPath.startsWith('http://') || imgPath.startsWith('https://') || imgPath.startsWith('data:') || imgPath.startsWith('/src') || imgPath.startsWith('static/')) {
    return imgPath;
  }
  const clean = imgPath.startsWith('/') ? imgPath.slice(1) : imgPath;
  return `${ASSET_BASE_URL}${clean}`;
};

/**
 * Group raw variant list from backend /varient/list.php by parent product.id
 *
 * @param {Array} variantsList - Flat list of variants returned by API
 * @returns {Array} Grouped parent products array
 */
export const groupVariantsByProduct = (variantsList = []) => {
  if (!Array.isArray(variantsList) || variantsList.length === 0) {
    return [];
  }

  const productMap = new Map();

  for (const variant of variantsList) {
    if (!variant || !variant.id) continue;

    // Stable parent product identifier
    const parentProduct = variant.product || {};
    const productId = parentProduct.id || variant.product_id || variant.id;

    if (!productMap.has(productId)) {
      productMap.set(productId, {
        id: productId,
        product_id: productId,
        name: parentProduct.name || variant.variant_name || 'Product',
        slug: parentProduct.slug || '',
        description: parentProduct.description || '',
        is_new_arrival: (parentProduct.is_new_arrival === 1 || variant.is_new_arrival === 1) ? 1 : 0,
        is_featured: (parentProduct.is_featured === 1 || variant.is_featured === 1) ? 1 : 0,
        is_best_seller: (parentProduct.is_best_seller === 1 || variant.is_best_seller === 1) ? 1 : 0,
        category: variant.category || parentProduct.category || null,
        hsn_profile: variant.hsn_profile || null,
        variants: []
      });
    }

    const group = productMap.get(productId);
    group.variants.push(variant);
  }

  const groupedProducts = [];

  for (const group of productMap.values()) {
    const variants = group.variants;

    // Find default variant: first variant with available stock, or first variant in list
    const inStockVariant = variants.find((v) => {
      const isAvail = v.is_available === 1;
      const stockQty = v.stock?.available_quantity ?? v.stock?.stock_quantity ?? 0;
      const notOOS = v.stock?.stock_status !== 'out_of_stock';
      return isAvail && stockQty > 0 && notOOS;
    });

    const defaultVariant = inStockVariant || variants[0];

    // Compute prices across all variants of this product
    const sellingPrices = variants
      .map((v) => parseFloat(v.pricing?.selling_price ?? v.selling_price ?? 0))
      .filter((p) => !isNaN(p) && p > 0);

    const originalPrices = variants
      .map((v) => parseFloat(v.pricing?.original_price ?? v.original_price ?? 0))
      .filter((p) => !isNaN(p) && p > 0);

    const minSellingPrice = sellingPrices.length > 0 ? Math.min(...sellingPrices) : 0;
    const maxSellingPrice = sellingPrices.length > 0 ? Math.max(...sellingPrices) : 0;
    const minOriginalPrice = originalPrices.length > 0 ? Math.min(...originalPrices) : 0;
    const maxOriginalPrice = originalPrices.length > 0 ? Math.max(...originalPrices) : 0;

    const hasPriceRange = minSellingPrice !== maxSellingPrice && sellingPrices.length > 1;

    // Check discount
    const hasDiscount = minOriginalPrice > minSellingPrice;
    let maxDiscountPercent = 0;
    for (const v of variants) {
      const sp = parseFloat(v.pricing?.selling_price ?? 0);
      const op = parseFloat(v.pricing?.original_price ?? 0);
      if (op > sp && op > 0) {
        const pct = Math.round(((op - sp) / op) * 100);
        if (pct > maxDiscountPercent) maxDiscountPercent = pct;
      }
    }

    // Total stock check
    const totalAvailableStock = variants.reduce((sum, v) => {
      const qty = v.is_available === 1 ? (v.stock?.available_quantity ?? 0) : 0;
      return sum + Math.max(0, qty);
    }, 0);

    const hasAnyStock = variants.some((v) => {
      const isAvail = v.is_available === 1;
      const stockQty = v.stock?.available_quantity ?? 0;
      const notOOS = v.stock?.stock_status !== 'out_of_stock';
      return isAvail && stockQty > 0 && notOOS;
    });

    const isOutOfStock = !hasAnyStock;

    // Unique Colors
    const colorMap = new Map();
    for (const v of variants) {
      if (v.color && v.color.id) {
        if (!colorMap.has(v.color.id)) {
          const vStock = (v.stock?.available_quantity ?? 0) > 0 && v.is_available === 1 && v.stock?.stock_status !== 'out_of_stock';
          colorMap.set(v.color.id, {
            id: v.color.id,
            name: v.color.name,
            hex_code: v.color.hex_code,
            primaryVariantId: v.id,
            hasStock: vStock,
            image: v.primary_image?.image || null
          });
        }
      }
    }
    const colors = Array.from(colorMap.values());

    // Unique Sizes
    const sizeMap = new Map();
    for (const v of variants) {
      if (v.size && v.size.id) {
        if (!sizeMap.has(v.size.id)) {
          sizeMap.set(v.size.id, {
            id: v.size.id,
            name: v.size.name,
            sort_order: v.size.sort_order ?? v.size.id
          });
        }
      }
    }
    const sizes = Array.from(sizeMap.values()).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));

    // --- VARIANT-SPECIFIC IMAGES FOR DISPLAYED DEFAULT VARIANT ONLY ---
    // Extract all image paths belonging EXCLUSIVELY to the displayed defaultVariant
    const defaultVariantImages = Array.isArray(defaultVariant?.images) && defaultVariant.images.length > 0
      ? defaultVariant.images
      : [defaultVariant?.primary_image, defaultVariant?.image].filter(Boolean);

    const defaultVariantImagePaths = [];
    const seenDefaultVariantPaths = new Set();

    for (const im of defaultVariantImages) {
      const p = typeof im === 'string' ? im : im?.image_path || im?.image || im?.url || im?.path || im?.src;
      if (p && typeof p === 'string' && !seenDefaultVariantPaths.has(p)) {
        seenDefaultVariantPaths.add(p);
        defaultVariantImagePaths.push(p);
      }
    }

    // 1. Primary Image Path: primary_image of defaultVariant, or first image of defaultVariant
    const primaryImgPath =
      (typeof defaultVariant?.primary_image === 'string'
        ? defaultVariant.primary_image
        : defaultVariant?.primary_image?.image_path || defaultVariant?.primary_image?.image) ||
      defaultVariantImagePaths[0] ||
      null;

    // 2. Hover Image Path: Distinct second image belonging strictly to this SAME defaultVariant
    let hoverImgPath = null;
    if (defaultVariantImagePaths.length > 1) {
      const secondVariantImg = defaultVariantImagePaths.find((p) => p !== primaryImgPath);
      if (secondVariantImg) {
        hoverImgPath = secondVariantImg;
      }
    }

    // Optional allImages collection across all variants
    const allImages = [];
    const seenImgs = new Set();
    for (const v of variants) {
      const vImgs = Array.isArray(v.images) ? v.images : [v.primary_image, v.image].filter(Boolean);
      for (const im of vImgs) {
        const p = typeof im === 'string' ? im : im?.image_path || im?.image || im?.url || im?.path || im?.src;
        if (p && typeof p === 'string' && !seenImgs.has(p)) {
          seenImgs.add(p);
          allImages.push(im);
        }
      }
    }

    groupedProducts.push({
      id: group.id,
      productId: group.id,
      name: group.name,
      slug: group.slug,
      description: group.description,
      is_new_arrival: group.is_new_arrival,
      is_featured: group.is_featured,
      is_best_seller: group.is_best_seller,
      category: group.category,
      hsn_profile: group.hsn_profile,
      variants,
      variantCount: variants.length,
      defaultVariant,
      defaultVariantId: defaultVariant.id,
      minSellingPrice,
      maxSellingPrice,
      minOriginalPrice,
      maxOriginalPrice,
      hasPriceRange,
      hasDiscount,
      maxDiscountPercent,
      totalAvailableStock,
      hasAnyStock,
      isOutOfStock,
      colors,
      sizes,
      primaryImageUrl: formatImageUrl(primaryImgPath),
      hoverImageUrl: hoverImgPath ? formatImageUrl(hoverImgPath) : null,
      allImages
    });
  }

  return groupedProducts;
};
