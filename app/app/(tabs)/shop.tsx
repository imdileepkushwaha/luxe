import React, { useEffect, useState, useRef } from 'react';
import { StyleSheet, Text, View, ScrollView, TextInput, TouchableOpacity, Dimensions, ActivityIndicator, Image, SafeAreaView, Platform } from 'react-native';
import { Search, SlidersHorizontal, ShoppingBag, User, Heart, X } from 'lucide-react-native';
import Colors from '@/constants/Colors';
import Config from '@/constants/Config';
import { useColorScheme } from '@/components/useColorScheme';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import { useRouter } from 'expo-router';
import { BlurView } from 'expo-blur';
import LuxeHeader from '@/components/LuxeHeader';

const { width } = Dimensions.get('window');

export default function ShopScreen() {
  const router = useRouter();
  const searchInputRef = useRef<TextInput>(null);
  const { cartCount, addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const [categories, setCategories] = useState<string[]>(['All']);
  const [products, setProducts] = useState<any[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchCategories();
  }, []);

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      fetchProducts();
    }, 500);

    return () => clearTimeout(delayDebounceFn);
  }, [selectedCategory, searchQuery]);

  const fetchCategories = async () => {
    try {
      const response = await fetch(`${Config.API_URL}/categories.php`);
      const data = await response.json();
      if (data.ok) setCategories(data.categories);
    } catch (error) { console.error(error); }
  };

  const fetchProducts = async () => {
    setLoading(true);
    try {
      let url = `${Config.API_URL}/products.php?category=${selectedCategory}`;
      if (searchQuery) {
        url += `&search=${encodeURIComponent(searchQuery)}`;
      }
      const response = await fetch(url);
      const data = await response.json();
      if (data.ok) setProducts(data.products);
    } catch (error) { console.error(error); } 
    finally { setLoading(false); }
  };

  return (
    <View style={styles.container}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea}>
        {/* Global Luxe Header */}
        <LuxeHeader onSearchPress={() => searchInputRef.current?.focus()} />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          <View style={styles.searchBarContainer}>
            <GlassCard style={styles.searchBox} intensity={15} borderRadius={27}>
              <View style={styles.searchInner}>
                <Search size={20} color="rgba(255,255,255,0.4)" />
                <TextInput 
                  ref={searchInputRef}
                  placeholder="Search products..." 
                  placeholderTextColor="rgba(255,255,255,0.4)"
                  style={styles.searchInput}
                  value={searchQuery}
                  onChangeText={setSearchQuery}
                />
                {searchQuery.length > 0 && (
                  <TouchableOpacity onPress={() => setSearchQuery('')} style={{ padding: 5 }}>
                    <X size={18} color="rgba(255,255,255,0.4)" />
                  </TouchableOpacity>
                )}
                <TouchableOpacity style={styles.filterBtn}>
                  <SlidersHorizontal size={20} color="#8b5cf6" />
                </TouchableOpacity>
              </View>
            </GlassCard>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.catScroll}>
            {categories.map((cat, i) => (
              <TouchableOpacity key={i} onPress={() => setSelectedCategory(cat)}>
                <GlassCard 
                  style={[styles.catPill, selectedCategory === cat && { backgroundColor: '#8b5cf6', borderColor: '#8b5cf6' }]} 
                  intensity={selectedCategory === cat ? 80 : 20}
                  borderRadius={15}
                >
                  <Text style={[styles.catLabel, { color: '#fff' }]}>{cat}</Text>
                </GlassCard>
              </TouchableOpacity>
            ))}
          </ScrollView>

          <View style={styles.section}>
            {loading ? (
              <ActivityIndicator size="large" color="#8b5cf6" style={{ marginTop: 60 }} />
            ) : (
              <View style={styles.productGrid}>
                {products.length > 0 ? (
                  products.map((item) => (
                    <TouchableOpacity 
                      key={item.id} 
                      style={styles.productItem} 
                      activeOpacity={0.9}
                      onPress={() => router.push(`/product/${item.id}`)}
                    >
                      <GlassCard style={styles.productCard} intensity={40} borderRadius={18}>
                        <View style={styles.imageBox}>
                          <Image 
                            source={{ uri: item.image_url || `https://picsum.photos/seed/${item.id}/300/400` }} 
                            style={styles.pImage} 
                            resizeMode="contain"
                          />
                          <TouchableOpacity 
                            style={styles.pWishlistBtn} 
                            onPress={() => isInWishlist(item.id) ? removeFromWishlist(item.id) : addToWishlist(item)}
                          >
                            <BlurView intensity={30} tint="dark" style={styles.pWishlistBlur}>
                              <Heart size={14} color={isInWishlist(item.id) ? "#ef4444" : "#fff"} fill={isInWishlist(item.id) ? "#ef4444" : "transparent"} />
                            </BlurView>
                          </TouchableOpacity>
                        </View>
                        <View style={styles.pInfo}>
                          <Text style={styles.pName} numberOfLines={1}>{item.name.toUpperCase()}</Text>
                          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
                            <Text style={styles.pPrice}>₹{item.price.toLocaleString()}</Text>
                            <TouchableOpacity 
                              style={{ backgroundColor: 'rgba(255,255,255,0.05)', padding: 8, borderRadius: 12 }}
                              onPress={() => addToCart(item.id)}
                            >
                              <ShoppingBag size={16} color="#94a3b8" />
                            </TouchableOpacity>
                          </View>
                        </View>
                      </GlassCard>
                    </TouchableOpacity>
                  ))
                ) : (
                  <View style={styles.emptyContainer}>
                    <Search size={48} color="rgba(255,255,255,0.1)" style={{ marginBottom: 15 }} />
                    <Text style={styles.emptyTitle}>No products found</Text>
                    <Text style={styles.emptySubtitle}>Try adjusting your search or category filter</Text>
                    <TouchableOpacity 
                      style={styles.resetBtn}
                      onPress={() => {
                        setSearchQuery('');
                        setSelectedCategory('All');
                      }}
                    >
                      <Text style={styles.resetBtnText}>Clear All Filters</Text>
                    </TouchableOpacity>
                  </View>
                )}
              </View>
            )}
          </View>
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 25, height: 70 },
  logo: { fontSize: 28, fontWeight: '300', color: '#fff', letterSpacing: 2, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  headerIcons: { flexDirection: 'row', gap: 15 },
  iconBtn: { padding: 5 },
  scrollContent: { paddingBottom: 100 },
  searchBarContainer: { paddingHorizontal: 20, paddingVertical: 15 },
  searchBox: { padding: 0, height: 54, backgroundColor: 'rgba(255,255,255,0.03)' },
  searchInner: { flex: 1, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20, gap: 12 },
  searchInput: { flex: 1, color: '#fff', fontSize: 16, fontWeight: '400' },
  catScroll: { paddingHorizontal: 20, gap: 10, marginBottom: 20 },
  catPill: { paddingHorizontal: 20, paddingVertical: 10, minWidth: 80, alignItems: 'center' },
  catLabel: { fontSize: 12, fontWeight: '800' },
  filterBtn: { padding: 5 },
  emptyContainer: { flex: 1, alignItems: 'center', justifyContent: 'center', marginTop: 80, width: width - 40 },
  emptyTitle: { color: '#fff', fontSize: 18, fontWeight: '600', marginBottom: 8 },
  emptySubtitle: { color: '#94a3b8', fontSize: 14, textAlign: 'center', marginBottom: 25 },
  resetBtn: { backgroundColor: 'rgba(139, 92, 246, 0.1)', paddingHorizontal: 20, paddingVertical: 10, borderRadius: 12, borderWidth: 1, borderColor: '#8b5cf6' },
  resetBtnText: { color: '#8b5cf6', fontWeight: '700', fontSize: 14 },
  section: { padding: 20, paddingTop: 0 },
  productGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 15 },
  productItem: { width: (width - 55) / 2 },
  productCard: { padding: 0, overflow: 'hidden' },
  imageBox: { width: '100%', height: 180, backgroundColor: 'rgba(255,255,255,0.05)', padding: 10, position: 'relative' },
  pImage: { width: '100%', height: '100%' },
  pWishlistBtn: { position: 'absolute', top: 10, right: 10, width: 32, height: 32, borderRadius: 16, overflow: 'hidden', zIndex: 10 },
  pWishlistBlur: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  pInfo: { padding: 10, paddingTop: 5, paddingBottom: 20 },
  pName: { color: '#fff', fontSize: 11, fontWeight: '800', marginBottom: 6 },
  pPrice: { fontSize: 14, fontWeight: '900', color: '#fff' },
  cartBadge: {
    position: 'absolute',
    top: -4,
    right: -4,
    backgroundColor: '#8b5cf6',
    width: 16,
    height: 16,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
  },
  badgeText: {
    color: '#fff',
    fontSize: 9,
    fontWeight: 'bold',
  },
});
