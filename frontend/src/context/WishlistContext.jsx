import React, { createContext, useContext, useState, useEffect } from 'react';

// Import default mock banner images
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';

const initialWishlistItems = [
  {
    id: 1,
    name: 'Peacock Blue Salwar Set (3 Piece Suit) - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner1,
    inStock: true
  },
  {
    id: 2,
    name: 'Avocado Green Salwar Suit - Slub Silk',
    category: 'Salwar Sets',
    price: 1799,
    originalPrice: 2299,
    image: banner2,
    inStock: true
  },
  {
    id: 3,
    name: 'Mustard 3 Piece Set - Slub Silk Cotton',
    category: '3 Piece Suit',
    price: 1799,
    originalPrice: 2299,
    image: banner3,
    inStock: true
  }
];

const WishlistContext = createContext();

export const WishlistProvider = ({ children }) => {
  const [wishlistItems, setWishlistItems] = useState(() => {
    try {
      const saved = localStorage.getItem('vivisha_wishlist_items');
      if (saved) return JSON.parse(saved);
    } catch (e) {
      console.error('Error reading wishlist from localStorage', e);
    }
    return initialWishlistItems;
  });

  useEffect(() => {
    try {
      localStorage.setItem('vivisha_wishlist_items', JSON.stringify(wishlistItems));
    } catch (e) {
      console.error('Error saving wishlist to localStorage', e);
    }
  }, [wishlistItems]);

  // Dynamic Item Count
  const wishlistCount = wishlistItems.length;

  const addToWishlist = (product) => {
    setWishlistItems((prev) => {
      if (prev.some((item) => item.id === product.id)) {
        return prev;
      }
      return [...prev, product];
    });
  };

  const removeFromWishlist = (productId) => {
    setWishlistItems((prev) => prev.filter((item) => item.id !== productId));
  };

  const clearWishlist = () => {
    setWishlistItems([]);
  };

  const isInWishlist = (productId) => {
    return wishlistItems.some((item) => item.id === productId);
  };

  const toggleWishlist = (product) => {
    if (isInWishlist(product.id)) {
      removeFromWishlist(product.id);
      return false; // Removed
    } else {
      addToWishlist(product);
      return true; // Added
    }
  };

  return (
    <WishlistContext.Provider
      value={{
        wishlistItems,
        wishlistCount,
        addToWishlist,
        removeFromWishlist,
        clearWishlist,
        isInWishlist,
        toggleWishlist,
        setWishlistItems
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
