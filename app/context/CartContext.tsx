import React, { createContext, useContext, useState, useEffect } from 'react';
import { Alert } from 'react-native';
import Config from '@/constants/Config';
import { useAuth } from './AuthContext';

interface CartItem {
  id: number;
  product_id: number;
  name: string;
  price: number;
  qty: number;
  image_url?: string;
}

interface CartContextType {
  items: CartItem[];
  cartCount: number;
  addToCart: (productId: number) => Promise<void>;
  removeFromCart: (cartItemId: number) => Promise<void>;
  updateQty: (cartItemId: number, qty: number) => Promise<void>;
  fetchCart: () => Promise<void>;
  loading: boolean;
}

const CartContext = createContext<CartContextType | undefined>(undefined);

export function CartProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [items, setItems] = useState<CartItem[]>([]);
  const [cartCount, setCartCount] = useState(0);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchCart();
  }, [user]);

  const fetchCart = async () => {
    if (!user) {
      setItems([]);
      setCartCount(0);
      return;
    }
    setLoading(true);
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php?action=list&user_id=${user.id}`);
      const data = await response.json();
      if (data.ok) {
        setItems(data.items);
        setCartCount(data.count);
      }
    } catch (error) {
      console.error('Cart fetch error:', error);
    } finally {
      setLoading(false);
    }
  };

  const addToCart = async (productId: number) => {
    if (!user) {
      Alert.alert('Login Required', 'Please login to add items to your cart.');
      return;
    }
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', user_id: user.id, product_id: productId, qty: 1 }),
      });
      const data = await response.json();
      if (data.ok) {
        Alert.alert('Success', 'Added to cart');
        fetchCart();
      } else {
        Alert.alert('Error', data.error || 'Could not add to cart');
      }
    } catch (error) {
      console.error('Add to cart error:', error);
      Alert.alert('Error', 'Connection error. Please try again.');
    }
  };

  const removeFromCart = async (cartItemId: number) => {
    if (!user) return;
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', user_id: user.id, cart_item_id: cartItemId }),
      });
      const data = await response.json();
      if (data.ok) fetchCart();
    } catch (error) {
      console.error('Remove from cart error:', error);
    }
  };

  const updateQty = async (cartItemId: number, qty: number) => {
    if (!user || qty < 1) return;
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', user_id: user.id, cart_item_id: cartItemId, qty: qty }),
      });
      const data = await response.json();
      if (data.ok) fetchCart();
      else Alert.alert('Error', data.error || 'Could not update quantity');
    } catch (error) {
      console.error('Update qty error:', error);
    }
  };

  return (
    <CartContext.Provider value={{ items, cartCount, addToCart, removeFromCart, updateQty, fetchCart, loading }}>
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const context = useContext(CartContext);
  if (context === undefined) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}
