import React from 'react';
import { StyleSheet, Text, View, ScrollView, Image, TouchableOpacity, Dimensions, Platform } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { ChevronLeft, ShoppingBag, Heart, Trash2, ArrowRight } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useWishlist } from '@/context/WishlistContext';
import { useCart } from '@/context/CartContext';
import LuxeHeader from '@/components/LuxeHeader';
import { useAppTheme } from '@/context/ThemeContext';

const { width } = Dimensions.get('window');
const CARD_WIDTH = (width - 60) / 2;

export default function WishlistScreen() {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const { wishlist, removeFromWishlist } = useWishlist();
  const { addToCart } = useCart();

  if (wishlist.length === 0) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader title="Wishlist" />
          
          <View style={styles.emptyContainer}>
            <View style={styles.emptyIconBox}>
              <Heart size={60} color="#ec4899" fill="#ec4899" />
              <LinearGradient colors={['rgba(236, 72, 153, 0.2)', 'transparent']} style={StyleSheet.absoluteFill} />
            </View>
            <Text style={[styles.emptyTitle, { color: colors.text }]}>Wishlist is empty</Text>
            <Text style={[styles.emptySub, { color: colors.muted }]}>Save your favorite elite pieces to view them later and stay updated on their availability.</Text>
            <TouchableOpacity 
              style={styles.continueBtn}
              onPress={() => router.push('/(tabs)/shop')}
            >
              <LinearGradient colors={colors.cta} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.continueGradient}>
                <Text style={styles.continueText}>DISCOVER PRODUCTS</Text>
              </LinearGradient>
            </TouchableOpacity>
          </View>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader title="Wishlist" />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          <View style={styles.grid}>
            {wishlist.map((item) => (
              <View key={item.id} style={styles.itemWrapper}>
                <GlassCard intensity={20} borderRadius={25} style={styles.wishCard}>
                  <TouchableOpacity 
                    activeOpacity={0.9} 
                    onPress={() => router.push(`/product/${item.id}`)}
                    style={styles.imageBox}
                  >
                    <Image source={{ uri: item.image_url }} style={styles.itemImage} />
                    <TouchableOpacity 
                      style={styles.removeBtn} 
                      onPress={() => removeFromWishlist(item.id)}
                    >
                      <BlurView intensity={30} tint="dark" style={styles.removeBlur}>
                        <Trash2 size={16} color="#ef4444" />
                      </BlurView>
                    </TouchableOpacity>
                  </TouchableOpacity>

                  <View style={styles.itemInfo}>
                    <Text style={[styles.itemBrand, { color: isDark ? '#8b5cf6' : '#ef4444' }]}>{item.brand}</Text>
                    <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={1}>{item.name}</Text>
                    <Text style={[styles.itemPrice, { color: colors.text }]}>₹{item.price.toLocaleString()}</Text>
                    
                    <TouchableOpacity 
                      style={styles.addBtn}
                      onPress={() => addToCart(item)}
                    >
                      <LinearGradient colors={colors.cta} style={styles.addGradient}>
                        <ShoppingBag size={14} color="#fff" />
                        <Text style={styles.addText}>ADD</Text>
                      </LinearGradient>
                    </TouchableOpacity>
                  </View>
                </GlassCard>
              </View>
            ))}
          </View>
          <View style={{ height: 100 }} />
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  header: { paddingHorizontal: 25, height: 70, justifyContent: 'center' },
  headerTitle: { color: '#fff', fontSize: 22, fontWeight: '900', letterSpacing: 2, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  
  scrollContent: { padding: 16, paddingBottom: 96 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between' },
  itemWrapper: { width: CARD_WIDTH, marginBottom: 20 },
  wishCard: { padding: 0, overflow: 'hidden' },
  imageBox: { width: '100%', height: 180, position: 'relative' },
  itemImage: { width: '100%', height: '100%' },
  removeBtn: { position: 'absolute', top: 10, right: 10, width: 32, height: 32, borderRadius: 16, overflow: 'hidden' },
  removeBlur: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  
  itemInfo: { padding: 12 },
  itemBrand: { color: '#8b5cf6', fontSize: 9, fontWeight: '900', letterSpacing: 1.5, marginBottom: 4 },
  itemName: { color: '#fff', fontSize: 13, fontWeight: '700', marginBottom: 8 },
  itemPrice: { color: '#fff', fontSize: 16, fontWeight: '900', marginBottom: 15 },
  
  addBtn: { height: 40, borderRadius: 15, overflow: 'hidden' },
  addGradient: { flex: 1, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 8 },
  addText: { color: '#fff', fontSize: 11, fontWeight: '900', letterSpacing: 1 },

  emptyContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40, paddingBottom: 96 },
  emptyIconBox: { width: 120, height: 120, borderRadius: 60, justifyContent: 'center', alignItems: 'center', marginBottom: 30, overflow: 'hidden' },
  emptyTitle: { color: '#fff', fontSize: 24, fontWeight: '800', marginBottom: 10 },
  emptySub: { color: '#94a3b8', fontSize: 15, textAlign: 'center', lineHeight: 22, marginBottom: 35 },
  continueBtn: { width: '100%', height: 60, borderRadius: 25, overflow: 'hidden' },
  continueGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  continueText: { color: '#fff', fontWeight: '900', fontSize: 14, letterSpacing: 1.5 },
});
