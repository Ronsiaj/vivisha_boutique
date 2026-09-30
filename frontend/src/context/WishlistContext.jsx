import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext.jsx';
import { useToast } from './ToastContext.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const WishlistContext = createContext();

export const WishlistProvider = ({ children }) => {
  const { isAuthenticated, token } = useAuth();
  const { showToast } = useToast();
  const [wishlistItems, setWishlistItems] = useState([]);
  const [wishlistCount, setWishlistCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchWishlist();
    } else {
      setWishlistItems([]);
      setWishlistCount(0);
    }
  }, [isAuthenticated, token]);

  const fetchWishlist = async () => {
    if (!token) return;
    setIsLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/wishlist/list.php?limit=100`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && Array.isArray(data.data.wishlists)) {
        setWishlistItems(data.data.wishlists);
        setWishlistCount(data.data.summary?.wishlist_count ?? data.data.wishlists.length);
      } else {
        setWishlistItems([]);
        setWishlistCount(0);
      }
    } catch (err) {
      console.error('Error fetching wishlist:', err);
      setWishlistItems([]);
      setWishlistCount(0);
    } finally {
      setIsLoading(false);
    }
  };

  const addToWishlist = async (variantOrId) => {
    if (!isAuthenticated || !token) {
      return { success: false, message: 'Please log in to add items to your wishlist.' };
    }
    
    const variantId = typeof variantOrId === 'object' && variantOrId !== null
      ? Number(variantOrId.id || variantOrId.variant_id)
      : Number(variantOrId);

    if (!variantId || isNaN(variantId) || variantId <= 0) {
      return { success: false, message: 'Invalid product variant ID.' };
    }

    try {
      const response = await fetch(`${API_BASE_URL}/wishlist/add.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ variant_id: variantId })
      });
      const data = await response.json();
      if (data.status) {
        await fetchWishlist();
        showToast({ message: 'Product added to wishlist', type: 'wishlist' });
        return { success: true, message: data.message || 'Product added to wishlist', data: data.data };
      } else {
        // If already in wishlist (409 conflict)
        if (response.status === 409 || data.message?.toLowerCase().includes('already')) {
          await fetchWishlist();
          showToast({ message: 'Product is already in your wishlist', type: 'info' });
          return { success: true, message: data.message || 'Product is already in your wishlist', alreadyExists: true };
        }
        return { success: false, message: data.message || 'Failed to add to wishlist.' };
      }
    } catch (err) {
      console.error('Error adding to wishlist:', err);
      return { success: false, message: 'Network error adding to wishlist.' };
    }
  };

  const removeFromWishlist = async (wishlistId) => {
    if (!isAuthenticated || !token) {
      return { success: false, message: 'Please log in to manage your wishlist.' };
    }

    const id = Number(wishlistId);
    if (!id || isNaN(id) || id <= 0) {
      return { success: false, message: 'Invalid wishlist item ID.' };
    }
    
    try {
      const response = await fetch(`${API_BASE_URL}/wishlist/remove.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ wishlist_id: id })
      });
      const data = await response.json();
      if (data.status) {
        setWishlistItems((prev) => prev.filter((item) => Number(item.id) !== id));
        setWishlistCount((prev) => Math.max(0, prev - 1));
        showToast({ message: 'Product removed from wishlist', type: 'info' });
        return { success: true, message: data.message || 'Product removed from wishlist' };
      } else {
        return { success: false, message: data.message || 'Failed to remove from wishlist.' };
      }
    } catch (err) {
      console.error('Error removing from wishlist:', err);
      return { success: false, message: 'Network error removing from wishlist.' };
    }
  };

  const removeFromWishlistByVariantId = async (variantOrId) => {
    const variantId = typeof variantOrId === 'object' && variantOrId !== null
      ? Number(variantOrId.id || variantOrId.variant_id)
      : Number(variantOrId);

    const existing = wishlistItems.find((item) => Number(item.variant_id) === variantId);
    if (existing) {
      return await removeFromWishlist(existing.id);
    }
    return { success: false, message: 'Item not found in wishlist.' };
  };

  const clearWishlist = () => {
    setWishlistItems([]);
    setWishlistCount(0);
  };

  const isInWishlist = (variantOrId) => {
    if (!variantOrId) return false;
    const variantId = typeof variantOrId === 'object' && variantOrId !== null
      ? Number(variantOrId.id || variantOrId.variant_id)
      : Number(variantOrId);

    return wishlistItems.some((item) => Number(item.variant_id) === variantId);
  };

  const getWishlistItem = (variantOrId) => {
    if (!variantOrId) return null;
    const variantId = typeof variantOrId === 'object' && variantOrId !== null
      ? Number(variantOrId.id || variantOrId.variant_id)
      : Number(variantOrId);

    return wishlistItems.find((item) => Number(item.variant_id) === variantId) || null;
  };

  const toggleWishlist = async (variantOrId) => {
    const variantId = typeof variantOrId === 'object' && variantOrId !== null
      ? Number(variantOrId.id || variantOrId.variant_id)
      : Number(variantOrId);

    const existing = wishlistItems.find((item) => Number(item.variant_id) === variantId);
    if (existing) {
      const res = await removeFromWishlist(existing.id);
      return { ...res, action: 'removed' };
    } else {
      const res = await addToWishlist(variantId);
      return { ...res, action: 'added' };
    }
  };

  return (
    <WishlistContext.Provider
      value={{
        wishlistItems,
        wishlistCount,
        isLoading,
        addToWishlist,
        removeFromWishlist,
        removeFromWishlistByVariantId,
        clearWishlist,
        isInWishlist,
        getWishlistItem,
        toggleWishlist,
        fetchWishlist
      }}
    >
      {children}
    </WishlistContext.Provider>
  );
};

export const useWishlist = () => {
  const context = useContext(WishlistContext);
  if (!context) {
    throw new Error('useWishlist must be used within a WishlistProvider');
  }
  return context;
};
