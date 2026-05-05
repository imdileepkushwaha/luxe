import React from 'react';
import { StyleSheet, Text, View, TouchableOpacity, Platform, SafeAreaView } from 'react-native';
import { Search, ShoppingBag, User, ChevronLeft } from 'lucide-react-native';
import { useRouter } from 'expo-router';
import { useCart } from '@/context/CartContext';

interface LuxeHeaderProps {
  title?: string;
  showBack?: boolean;
  onSearchPress?: () => void;
}

export default function LuxeHeader({ title, showBack = false, onSearchPress }: LuxeHeaderProps) {
  const router = useRouter();
  const { cartCount } = useCart();

  return (
    <View style={styles.headerWrapper}>
      <View style={styles.header}>
        {showBack ? (
          <TouchableOpacity 
            style={styles.iconBtn} 
            onPress={() => router.back()}
          >
            <ChevronLeft size={24} color="#fff" />
          </TouchableOpacity>
        ) : (
          <Text style={styles.logo}>LUXE</Text>
        )}

        <View style={styles.headerIcons}>
          <TouchableOpacity 
            style={styles.iconBtn}
            onPress={onSearchPress || (() => router.push('/(tabs)/shop'))}
          >
            <Search size={22} color="#fff" />
          </TouchableOpacity>
          
          <TouchableOpacity 
            style={styles.iconBtn}
            onPress={() => router.push('/cart')}
          >
            {cartCount > 0 && (
              <View style={styles.cartBadge}>
                <Text style={styles.badgeText}>{cartCount}</Text>
              </View>
            )}
            <ShoppingBag size={22} color="#fff" />
          </TouchableOpacity>

          <TouchableOpacity 
            style={styles.iconBtn}
            onPress={() => router.push('/(tabs)/profile')}
          >
            <User size={22} color="#fff" />
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  headerWrapper: {
    backgroundColor: 'transparent',
    zIndex: 100,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 25,
    height: 70,
  },
  logo: {
    fontSize: 28,
    fontWeight: '300',
    color: '#fff',
    letterSpacing: 2,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
  headerIcons: {
    flexDirection: 'row',
    gap: 15,
  },
  iconBtn: {
    padding: 5,
    position: 'relative',
  },
  cartBadge: {
    position: 'absolute',
    top: -2,
    right: -2,
    backgroundColor: '#8b5cf6',
    width: 16,
    height: 16,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
    borderWidth: 1.5,
    borderColor: '#000',
  },
  badgeText: {
    color: '#fff',
    fontSize: 8,
    fontWeight: 'bold',
  },
});
