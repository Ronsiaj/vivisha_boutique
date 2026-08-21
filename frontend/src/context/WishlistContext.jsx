import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';

const WishlistContext = createContext();

export const WishlistProvider = ({ children }) => {
  const { isAuthenticated, token } = useAuth();
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
    setIsLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/wishlist/list.php?limit=100`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && data.data.wishlists) {
        setWishlistItems(data.data.wishlists);
        setWishlistCount(data.data.summary?.wishlist_count || data.data.wishlists.length);
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

  const addToWishlist = async (variantId) => {
    if (!isAuthenticated || !token) return false;
    
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
        fetchWishlist();
        return true;
      } else {
        console.error(data.message);
        return false;
      }
    } catch (err) {
      console.error('Error adding to wishlist:', err);
      return false;
    }
  };

  const removeFromWishlist = async (wishlistId) => {
    if (!isAuthenticated || !token) return false;
    
    try {
      const response = await fetch(`${API_BASE_URL}/wishlist/remove.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ wishlist_id: wishlistId })
      });
      const data = await response.json();
      if (data.status) {
        setWishlistItems((prev) => prev.filter((item) => item.id !== wishlistId));
        setWishlistCount((prev) => Math.max(0, prev - 1));
        return true;
      } else {
        console.error(data.message);
        return false;
      }
    } catch (err) {
      console.error('Error removing from wishlist:', err);
      return false;
    }
  };

  const clearWishlist = () => {
    setWishlistItems([]);
    setWishlistCount(0);
  };

  const isInWishlist = (variantId) => {
    return wishlistItems.some((item) => item.variant_id === variantId);
  };

  const toggleWishlist = async (variant) => {
    const existingItem = wishlistItems.find((item) => item.variant_id === variant.id);
    if (existingItem) {
      return await removeFromWishlist(existingItem.id);
    } else {
      return await addToWishlist(variant.id);
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
        clearWishlist,
        isInWishlist,
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
