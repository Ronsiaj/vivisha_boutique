import React, { createContext, useContext, useState, useEffect } from 'react';
import banner1 from '../../assets/images/banner1.png';
import banner2 from '../../assets/images/banner2.png';
import banner3 from '../../assets/images/banner3.png';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  // Initialize cart with mock items for immediate demonstration & state management
  const [cartItems, setCartItems] = useState(() => {
    try {
      const savedCart = localStorage.getItem('vivisha_cart_items');
      if (savedCart) return JSON.parse(savedCart);
    } catch (e) {
      console.error('Error reading cart from localStorage', e);
    }
    return [
      {
        id: 1,
        name: 'Peacock Blue Salwar Set (3 Piece Suit) - Slub Silk Cotton',
        category: '3 Piece Suit',
        price: 1799,
        originalPrice: 2299,
        quantity: 1,
        image: banner1
      },
      {
        id: 2,
        name: 'Avocado Green Salwar Suit - Slub Silk',
        category: 'Salwar Sets',
        price: 1799,
        originalPrice: 2299,
        quantity: 2,
        image: banner2
      }
    ];
  });

  const [appliedCoupon, setAppliedCoupon] = useState(null);

  useEffect(() => {
    try {
      localStorage.setItem('vivisha_cart_items', JSON.stringify(cartItems));
    } catch (e) {
      console.error('Error saving cart to localStorage', e);
    }
  }, [cartItems]);

  // Total item count across all quantities
  const cartCount = cartItems.reduce((total, item) => total + item.quantity, 0);

  // Subtotal calculation
  const subtotal = cartItems.reduce((acc, item) => acc + item.price * item.quantity, 0);

  // Total original price (for savings calculation)
  const totalOriginal = cartItems.reduce((acc, item) => acc + item.originalPrice * item.quantity, 0);
  const totalSavings = totalOriginal - subtotal;

  const deliveryFee = 0; // FREE Delivery
  const totalAmount = subtotal - (appliedCoupon ? appliedCoupon.discount : 0);

  /**
   * Add Item to Cart
   * 
   * API INTEGRATION POINT:
   * When integrating PHP API:
   * await ApiCall.post('/cart/add.php', { productId: product.id, quantity });
   */
  const addToCart = (product, quantity = 1) => {
    setCartItems((prev) => {
      const existingIndex = prev.findIndex((item) => item.id === product.id);
      if (existingIndex > -1) {
        const updated = [...prev];
        updated[existingIndex].quantity += quantity;
        return updated;
      } else {
        return [
          ...prev,
          {
            id: product.id,
            name: product.name,
            category: product.category || 'Ethnic Wear',
            price: product.price,
            originalPrice: product.originalPrice || product.price + 500,
            quantity: quantity,
            image: product.image
          }
        ];
      }
    });
  };

  /**
   * Update Item Quantity
   */
  const updateQuantity = (id, delta) => {
    setCartItems((prev) =>
      prev
        .map((item) => {
          if (item.id === id) {
            const newQty = item.quantity + delta;
            return newQty > 0 ? { ...item, quantity: newQty } : null;
          }
          return item;
        })
        .filter(Boolean)
    );
  };

  /**
   * Remove Item from Cart
   */
  const removeFromCart = (id) => {
    setCartItems((prev) => prev.filter((item) => item.id !== id));
  };

  /**
   * Clear Cart after order placement
   */
  const clearCart = () => {
    setCartItems([]);
    setAppliedCoupon(null);
  };

  return (
    <CartContext.Provider
      value={{
        cartItems,
        cartCount,
        subtotal,
        totalOriginal,
        totalSavings,
        deliveryFee,
        totalAmount,
        appliedCoupon,
        setAppliedCoupon,
        addToCart,
        updateQuantity,
        removeFromCart,
        clearCart
      }}
    >
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
};
