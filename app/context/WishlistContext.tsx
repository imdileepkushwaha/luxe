import React, { createContext, useContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

interface WishlistItem {
  id: number;
  name: string;
  price: number;
  image_url: string;
  brand: string;
}

interface WishlistContextType {
  wishlist: WishlistItem[];
  addToWishlist: (product: any) => void;
  removeFromWishlist: (id: number) => void;
  isInWishlist: (id: number) => boolean;
  clearWishlist: () => void;
}

const WishlistContext = createContext<WishlistContextType | undefined>(undefined);

export function WishlistProvider({ children }: { children: React.ReactNode }) {
  const [wishlist, setWishlist] = useState<WishlistItem[]>([]);

  useEffect(() => {
    loadWishlist();
  }, []);

  const loadWishlist = async () => {
    try {
      const saved = await AsyncStorage.getItem('luxe_wishlist');
      if (saved) setWishlist(JSON.parse(saved));
    } catch (e) {
      console.error('Failed to load wishlist');
    }
  };

  const saveWishlist = async (newList: WishlistItem[]) => {
    try {
      await AsyncStorage.setItem('luxe_wishlist', JSON.stringify(newList));
    } catch (e) {
      console.error('Failed to save wishlist');
    }
  };

  const addToWishlist = (product: any) => {
    setWishlist(prev => {
      if (prev.find(item => item.id === product.id)) return prev;
      const newList = [...prev, {
        id: product.id,
        name: product.name,
        price: product.price,
        image_url: product.image_url || (product.images ? product.images[0] : ''),
        brand: product.brand || 'LUXE'
      }];
      saveWishlist(newList);
      return newList;
    });
  };

  const removeFromWishlist = (id: number) => {
    setWishlist(prev => {
      const newList = prev.filter(item => item.id !== id);
      saveWishlist(newList);
      return newList;
    });
  };

  const isInWishlist = (id: number) => {
    return wishlist.some(item => item.id === id);
  };

  const clearWishlist = () => {
    setWishlist([]);
    saveWishlist([]);
  };

  return (
    <WishlistContext.Provider value={{ wishlist, addToWishlist, removeFromWishlist, isInWishlist, clearWishlist }}>
      {children}
    </WishlistContext.Provider>
  );
}

export function useWishlist() {
  const context = useContext(WishlistContext);
  if (context === undefined) {
    throw new Error('useWishlist must be used within a WishlistProvider');
  }
  return context;
}
