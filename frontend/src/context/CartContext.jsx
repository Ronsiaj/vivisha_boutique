import React, { createContext, useContext, useState, useEffect } from 'react';
import { useAuth } from './AuthContext.jsx';
import { useToast } from './ToastContext.jsx';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost/vivisha_boutique/backend/api';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const { isAuthenticated, token } = useAuth();
  const { showToast } = useToast();
  
  const [cartItems, setCartItems] = useState([]);
  const [cartSummary, setCartSummary] = useState({
    total_cart_records: 0,
    total_items: 0,
    total_quantity: 0,
    subtotal: 0
  });
  const [cartId, setCartId] = useState(null);
  const [isLoading, setIsLoading] = useState(false);
  const [updatingItemIds, setUpdatingItemIds] = useState(new Set());
  const [appliedCoupon, setAppliedCoupon] = useState(null);

  useEffect(() => {
    if (isAuthenticated && token) {
      fetchCart(token, true);
    } else {
      setCartItems([]);
      setCartSummary({
        total_cart_records: 0,
        total_items: 0,
        total_quantity: 0,
        subtotal: 0
      });
      setCartId(null);
    }
  }, [isAuthenticated, token]);

  const fetchCart = async (customToken = null, showLoading = true) => {
    const activeToken = customToken || token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) {
      setCartItems([]);
      setCartSummary({ total_cart_records: 0, total_items: 0, total_quantity: 0, subtotal: 0 });
      setCartId(null);
      return;
    }
    if (showLoading) {
      setIsLoading(true);
    }
    try {
      const response = await fetch(`${API_BASE_URL}/cart/list.php`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${activeToken}`
        }
      });
      const data = await response.json();
      if (data.status && data.data && Array.isArray(data.data.cart_items)) {
        setCartItems(data.data.cart_items);
        setCartSummary({
          total_cart_records: data.data.summary?.total_cart_records ?? data.data.cart_items.length,
          total_items: data.data.summary?.total_items ?? data.data.cart_items.length,
          total_quantity: data.data.summary?.total_quantity ?? data.data.cart_items.reduce((sum, it) => sum + (it.quantity || 1), 0),
          subtotal: parseFloat(data.data.summary?.subtotal || 0)
        });
        if (data.data.cart_items.length > 0) {
          setCartId(data.data.cart_items[0].cart_id);
        } else {
          setCartId(null);
        }
      } else {
        setCartItems([]);
        setCartSummary({ total_cart_records: 0, total_items: 0, total_quantity: 0, subtotal: 0 });
        setCartId(null);
      }
    } catch (err) {
      console.error('Error fetching cart:', err);
      setCartItems([]);
    } finally {
      if (showLoading) {
        setIsLoading(false);
      }
    }
  };

  const addToCart = async (cartItemOrVariantId, quantity = 1, customToken = null) => {
    const activeToken = customToken || token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) {
      return { success: false, message: 'Please log in to add items to your cart.' };
    }

    const variantId = typeof cartItemOrVariantId === 'object' && cartItemOrVariantId !== null
      ? Number(cartItemOrVariantId.variantId || cartItemOrVariantId.variant_id || cartItemOrVariantId.id)
      : Number(cartItemOrVariantId);

    const qty = Math.max(1, Math.min(100, Number(quantity) || 1));

    if (!variantId || isNaN(variantId) || variantId <= 0) {
      return { success: false, message: 'Invalid product variant ID.' };
    }
    
    try {
      const response = await fetch(`${API_BASE_URL}/cart/add.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${activeToken}`
        },
        body: JSON.stringify({
          variant_id: variantId,
          quantity: qty
        })
      });
      const data = await response.json();
      if (data.status) {
        await fetchCart(activeToken, false);
        const action = data.data?.action || 'item_added';
        if (action === 'item_added') {
          showToast({ message: 'Product added to cart', type: 'cart' });
        } else if (action === 'quantity_updated') {
          showToast({ message: 'Product is already in your cart (quantity updated)', type: 'info' });
        } else {
          showToast({ message: 'Product added to cart', type: 'cart' });
        }

        return {
          success: true,
          message: data.message || 'Product added to cart',
          data: data.data,
          action
        };
      } else {
        return {
          success: false,
          message: data.message || 'Failed to add item to cart.'
        };
      }
    } catch (err) {
      console.error('Error adding to cart:', err);
      return { success: false, message: 'Network error adding item to cart.' };
    }
  };

  const updateQuantity = async (cartItemId, newQuantity) => {
    const activeToken = token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) {
      return { success: false, message: 'Please log in to update your cart.' };
    }

    const itemId = Number(cartItemId);
    const qty = Number(newQuantity);

    if (!itemId || isNaN(itemId) || itemId <= 0) {
      return { success: false, message: 'Invalid cart item ID.' };
    }
    if (isNaN(qty) || qty < 1 || qty > 100) {
      return { success: false, message: 'Quantity must be between 1 and 100.' };
    }

    setUpdatingItemIds(prev => new Set(prev).add(itemId));

    try {
      const response = await fetch(`${API_BASE_URL}/cart/quantity_update.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${activeToken}`
        },
        body: JSON.stringify({
          cart_item_id: itemId,
          quantity: qty
        })
      });
      const data = await response.json();
      if (data.status && data.data) {
        const updatedItem = data.data.cart_item;
        const summary = data.data.cart?.summary;

        // Dynamic State Update: update affected item without page reload or flicker
        setCartItems(prev => prev.map(item => {
          if (item.cart_item_id === itemId || item.id === itemId) {
            const unitPrice = parseFloat(updatedItem?.unit_price || item.unit_price || 0);
            return {
              ...item,
              quantity: updatedItem?.quantity ?? qty,
              unit_price: updatedItem?.unit_price ?? item.unit_price,
              line_total: updatedItem?.line_total ?? (unitPrice * qty).toFixed(2)
            };
          }
          return item;
        }));

        if (summary) {
          setCartSummary({
            total_cart_records: summary.total_items,
            total_items: summary.total_items,
            total_quantity: summary.total_quantity,
            subtotal: parseFloat(summary.subtotal || 0)
          });
        }

        // Silent background sync
        fetchCart(activeToken, false);

        return {
          success: true,
          message: data.message || 'Quantity updated successfully!',
          data: data.data
        };
      } else {
        return {
          success: false,
          message: data.message || 'Failed to update quantity.'
        };
      }
    } catch (err) {
      console.error('Error updating cart item quantity:', err);
      return { success: false, message: 'Network error updating quantity.' };
    } finally {
      setUpdatingItemIds(prev => {
        const next = new Set(prev);
        next.delete(itemId);
        return next;
      });
    }
  };

  const removeFromCart = async (cartItemId) => {
    const activeToken = token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) {
      return { success: false, message: 'Please log in to manage your cart.' };
    }

    const itemId = Number(cartItemId);
    if (!itemId || isNaN(itemId) || itemId <= 0) {
      return { success: false, message: 'Invalid cart item ID.' };
    }
    
    try {
      const response = await fetch(`${API_BASE_URL}/cart/remove.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${activeToken}`
        },
        body: JSON.stringify({
          cart_item_id: itemId
        })
      });
      const data = await response.json();
      if (data.status) {
        setCartItems(prev => prev.filter(item => (item.cart_item_id || item.id) !== itemId));
        await fetchCart(activeToken, false);
        showToast({ message: 'Item removed from cart', type: 'info' });
        return { success: true, message: data.message || 'Item removed from cart!' };
      } else {
        return { success: false, message: data.message || 'Failed to remove item.' };
      }
    } catch (err) {
      console.error('Error removing from cart:', err);
      return { success: false, message: 'Network error removing item from cart.' };
    }
  };

  const viewCartItem = async (cartItemId) => {
    const activeToken = token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) return null;

    try {
      const response = await fetch(`${API_BASE_URL}/cart/view.php?id=${cartItemId}`, {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${activeToken}`
        }
      });
      const data = await response.json();
      if (data.status && data.data) {
        return data.data;
      }
      return null;
    } catch (err) {
      console.error('Error viewing cart item:', err);
      return null;
    }
  };

  const convertCartToOrder = async (targetCartId = null) => {
    const activeToken = token || localStorage.getItem('vivisha_user_token');
    if (!activeToken) {
      return { success: false, message: 'Please log in to proceed.' };
    }

    const id = Number(targetCartId || cartId);
    if (!id || isNaN(id) || id <= 0) {
      return { success: false, message: 'No active cart found to convert.' };
    }

    try {
      const response = await fetch(`${API_BASE_URL}/cart/move_to.php`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${activeToken}`
        },
        body: JSON.stringify({
          cart_id: id
        })
      });
      const data = await response.json();
      if (data.status) {
        await fetchCart(activeToken, false);
        return {
          success: true,
          message: data.message || 'Order created from cart!',
          data: data.data
        };
      } else {
        return {
          success: false,
          message: data.message || 'Failed to convert cart to order.'
        };
      }
    } catch (err) {
      console.error('Error converting cart to order:', err);
      return { success: false, message: 'Network error converting cart to order.' };
    }
  };

  const clearCart = () => {
    setCartItems([]);
    setCartSummary({ total_cart_records: 0, total_items: 0, total_quantity: 0, subtotal: 0 });
    setCartId(null);
    setAppliedCoupon(null);
  };

  const cartCount = cartSummary.total_quantity;
  const subtotal = cartSummary.subtotal;
  
  const totalOriginal = cartItems.reduce((acc, item) => {
    const origPrice = parseFloat(item?.variant?.pricing?.original_price || item?.unit_price || 0);
    return acc + (origPrice * (item?.quantity || 1));
  }, 0);
  const totalSavings = Math.max(0, totalOriginal - subtotal);
  const deliveryFee = 0;
  const totalAmount = Math.max(0, subtotal - (appliedCoupon ? appliedCoupon.discount : 0));

  return (
    <CartContext.Provider
      value={{
        cartId,
        cartItems,
        cartSummary,
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
        viewCartItem,
        convertCartToOrder,
        clearCart,
        fetchCart,
        isLoading,
        updatingItemIds
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
