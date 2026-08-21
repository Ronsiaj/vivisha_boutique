import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext.jsx';

const API_BASE_URL = 'http://localhost/vivisha_boutique/backend/api';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const { isAuthenticated, token } = useAuth();
  
  const [cartItems, setCartItems] = useState([]);
  const [cartSummary, setCartSummary] = useState({
    total_items: 0,
    total_quantity: 0,
    subtotal: 0
  });
  const [cartId, setCartId] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [appliedCoupon, setAppliedCoupon] = useState(null);

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchCart();
    } else {
      setCartItems([]);
      setCartSummary({
        total_items: 0,
        total_quantity: 0,
        subtotal: 0
      });
      setCartId(null);
    }
  }, [isAuthenticated, token]);

  const fetchCart = async () => {
    setIsLoading(true);
    try {
      const response = await fetch(`${API_BASE_URL}/cart/list.php`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && data.data.cart_items) {
        setCartItems(data.data.cart_items);
        setCartSummary({
          total_items: data.data.summary.total_items,
          total_quantity: data.data.summary.total_quantity,
          subtotal: parseFloat(data.data.summary.subtotal)
        });
        if (data.data.cart_items.length > 0) {
           setCartId(data.data.cart_items[0].cart_id);
        } else {
           setCartId(null);
        }
      } else {
        setCartItems([]);
        setCartSummary({ total_items: 0, total_quantity: 0, subtotal: 0 });
        setCartId(null);
      }
    } catch (err) {
      console.error('Error fetching cart:', err);
      setCartItems([]);
    } finally {
      setIsLoading(false);
    }
  };

  const addToCart = async (cartItem, quantity = 1) => {
    if (!isAuthenticated || !token) return false;
    
    try {
      const response = await fetch(`${API_BASE_URL}/cart/add.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          variant_id: cartItem.variantId,
          quantity: quantity
        })
      });
      const data = await response.json();
      if (data.status) {
        fetchCart();
        return true;
      } else {
        console.error(data.message);
        return false;
      }
    } catch (err) {
      console.error('Error adding to cart:', err);
      return false;
    }
  };

  const removeFromCart = async (cartItemId) => {
    if (!isAuthenticated || !token) return false;
    
    try {
      const response = await fetch(`${API_BASE_URL}/cart/remove.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          cart_item_id: cartItemId
        })
      });
      const data = await response.json();
      if (data.status) {
        fetchCart();
        return true;
      } else {
        console.error(data.message);
        return false;
      }
    } catch (err) {
      console.error('Error removing from cart:', err);
      return false;
    }
  };

  const clearCart = () => {
    setCartItems([]);
    setCartSummary({ total_items: 0, total_quantity: 0, subtotal: 0 });
    setCartId(null);
    setAppliedCoupon(null);
  };

  const cartCount = cartSummary.total_quantity;
  const subtotal = cartSummary.subtotal;
  
  const totalOriginal = cartItems.reduce((acc, item) => {
    const origPrice = parseFloat(item.variant.pricing.original_price || item.unit_price);
    return acc + (origPrice * item.quantity);
  }, 0);
  const totalSavings = totalOriginal - subtotal;
  const deliveryFee = 0;
  const totalAmount = subtotal - (appliedCoupon ? appliedCoupon.discount : 0);

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
        removeFromCart,
        clearCart,
        isLoading
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
