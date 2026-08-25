import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { Alert, Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import Config from '@/constants/Config';
import { useAuth } from './AuthContext';

const GUEST_CART_KEY = 'luxe_guest_cart';

export type CartItem = {
  id: number;
  product_id: number;
  name: string;
  price: number;
  qty: number;
  image_url?: string;
};

export type CartSnapshot = {
  id: number;
  name?: string;
  price?: number;
  image_url?: string;
};

interface CartContextType {
  items: CartItem[];
  cartCount: number;
  addToCart: (product: CartSnapshot) => Promise<boolean>;
  removeFromCart: (cartItemId: number) => Promise<void>;
  updateQty: (cartItemId: number, qty: number) => Promise<void>;
  fetchCart: () => Promise<void>;
  loading: boolean;
}

const CartContext = createContext<CartContextType | undefined>(undefined);

function notify(title: string, message: string) {
  if (Platform.OS === 'web') {
    if (typeof window !== 'undefined') {
      window.alert(`${title}: ${message}`);
    }
    return;
  }
  Alert.alert(title, message);
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const { user } = useAuth();
  const [items, setItems] = useState<CartItem[]>([]);
  const [cartCount, setCartCount] = useState(0);
  const [loading, setLoading] = useState(false);

  const applyItems = (next: CartItem[]) => {
    setItems(next);
    setCartCount(next.reduce((sum, i) => sum + i.qty, 0));
  };

  const saveGuestCart = async (next: CartItem[]) => {
    applyItems(next);
    await AsyncStorage.setItem(GUEST_CART_KEY, JSON.stringify(next));
  };

  const fetchCart = useCallback(async () => {
    if (!user) {
      try {
        const raw = await AsyncStorage.getItem(GUEST_CART_KEY);
        applyItems(raw ? JSON.parse(raw) : []);
      } catch {
        applyItems([]);
      }
      return;
    }
    setLoading(true);
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php?action=list&user_id=${user.id}`);
      const data = await response.json();
      if (data.ok) {
        setItems(data.items || []);
        setCartCount(data.count || 0);
      }
    } catch (error) {
      console.error('Cart fetch error:', error);
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    fetchCart();
  }, [fetchCart]);

  const addToCart = async (product: CartSnapshot): Promise<boolean> => {
    const productId = product.id;
    if (!productId) return false;

    if (!user) {
      const existing = items.find((i) => i.product_id === productId);
      let next: CartItem[];
      if (existing) {
        next = items.map((i) =>
          i.product_id === productId ? { ...i, qty: i.qty + 1 } : i
        );
      } else {
        next = [
          ...items,
          {
            id: productId,
            product_id: productId,
            name: product.name || 'Product',
            price: Number(product.price || 0),
            qty: 1,
            image_url: product.image_url || '',
          },
        ];
      }
      await saveGuestCart(next);
      return true;
    }

    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', user_id: user.id, product_id: productId, qty: 1 }),
      });
      const data = await response.json();
      if (data.ok) {
        await fetchCart();
        return true;
      }
      notify('Error', data.error || 'Could not add to cart');
      return false;
    } catch (error) {
      console.error('Add to cart error:', error);
      notify('Error', 'Connection error. Please try again.');
      return false;
    }
  };

  const removeFromCart = async (cartItemId: number) => {
    if (!user) {
      await saveGuestCart(items.filter((i) => i.id !== cartItemId && i.product_id !== cartItemId));
      return;
    }
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
    if (qty < 1) return;
    if (!user) {
      await saveGuestCart(
        items.map((i) =>
          i.id === cartItemId || i.product_id === cartItemId ? { ...i, qty } : i
        )
      );
      return;
    }
    try {
      const response = await fetch(`${Config.API_URL}/mobile_cart.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update', user_id: user.id, cart_item_id: cartItemId, qty }),
      });
      const data = await response.json();
      if (data.ok) fetchCart();
      else notify('Error', data.error || 'Could not update quantity');
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
