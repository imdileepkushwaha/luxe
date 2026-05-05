import React, { useState, useEffect, useRef } from 'react';
import { StyleSheet, Text, View, ScrollView, Image, TouchableOpacity, Dimensions, ActivityIndicator, SafeAreaView, Platform, RefreshControl, TextInput, FlatList, Animated } from 'react-native';
import { Search, ShoppingBag, User, Heart, Star, ChevronRight, Zap, X, ArrowRight } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { useColorScheme } from '@/components/useColorScheme';
import Colors from '@/constants/Colors';
import Config from '@/constants/Config';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useAuth } from '@/context/AuthContext';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import { BlurView } from 'expo-blur';
import LuxeHeader from '@/components/LuxeHeader';

const { width } = Dimensions.get('window');
const CARD_WIDTH = width * 0.44;

const HERO_SLIDES = [
  {
    id: 1,
    title: "Elegant\nStyle",
    tag: "CURATED COLLECTION",
    image: "https://images.unsplash.com/photo-1539109132314-34a936699561?auto=format&fit=crop&w=800&q=80"
  },
  {
    id: 2,
    title: "Timeless\nLuxury",
    tag: "NEW ARRIVALS",
    image: "https://images.unsplash.com/photo-1445205170230-053b830c6050?auto=format&fit=crop&w=800&q=80"
  },
  {
    id: 3,
    title: "Modern\nClassic",
    tag: "EXCLUSIVE EDITS",
    image: "https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=800&q=80"
  }
];

export default function HomeScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const { cartCount, addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  
  const [products, setProducts] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  
  const scrollX = useRef(new Animated.Value(0)).current;
  const [activeSlide, setActiveSlide] = useState(0);

  useEffect(() => {
    fetchProducts();
  }, [searchQuery]);

  const fetchProducts = async () => {
    try {
      const url = searchQuery 
        ? `${Config.API_URL}/products.php?search=${encodeURIComponent(searchQuery)}`
        : `${Config.API_URL}/products.php?limit=8`;
        
      const response = await fetch(url);
      const data = await response.json();
      if (data.ok) {
        setProducts(data.products);
      }
    } catch (error) {
      console.error('Fetch error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchProducts();
  };

  const renderHeroItem = ({ item }: { item: typeof HERO_SLIDES[0] }) => (
    <View style={styles.heroSlide}>
      <GlassCard style={styles.heroCard} intensity={40} borderRadius={40}>
        <Image source={{ uri: item.image }} style={styles.heroImage} />
        <LinearGradient 
          colors={['transparent', 'rgba(0,0,0,0.85)']} 
          style={styles.heroGradient} 
        />
        <View style={styles.heroContent}>
          <Text style={styles.heroTag}>{item.tag}</Text>
          <Text style={styles.heroTitle}>{item.title}</Text>
          <TouchableOpacity 
            style={styles.heroBtn}
            onPress={() => router.push('/(tabs)/shop')}
          >
            <Text style={styles.heroBtnText}>EXPLORE</Text>
            <ArrowRight size={14} color="#000" />
          </TouchableOpacity>
        </View>
      </GlassCard>
    </View>
  );

  return (
    <View style={styles.container}>
      <BackgroundScene />
      
      <SafeAreaView style={styles.safeArea}>
        <LuxeHeader />

        <ScrollView 
          showsVerticalScrollIndicator={false}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#8b5cf6" />}
        >
          {/* Hero Banner Carousel */}
          {!isSearching && (
            <View style={styles.heroWrapper}>
              <FlatList
                data={HERO_SLIDES}
                renderItem={renderHeroItem}
                keyExtractor={(item) => item.id.toString()}
                horizontal
                pagingEnabled
                showsHorizontalScrollIndicator={false}
                onScroll={Animated.event(
                  [{ nativeEvent: { contentOffset: { x: scrollX } } }],
                  { useNativeDriver: false }
                )}
                onMomentumScrollEnd={(e) => {
                  setActiveSlide(Math.round(e.nativeEvent.contentOffset.x / width));
                }}
              />
              {/* Pagination Dots */}
              <View style={styles.pagination}>
                {HERO_SLIDES.map((_, i) => (
                  <View 
                    key={i} 
                    style={[
                      styles.dot, 
                      activeSlide === i ? styles.activeDot : styles.inactiveDot
                    ]} 
                  />
                ))}
              </View>
            </View>
          )}

          {/* Section Header */}
          <View style={styles.sectionHeader}>
            <View>
              <Text style={styles.sectionTitle}>{isSearching ? 'SEARCH RESULTS' : 'NEW ARRIVALS'}</Text>
              <Text style={styles.sectionSubtitle}>
                {isSearching ? `Found ${products.length} items` : 'The latest from our elite designers'}
              </Text>
            </View>
            {!isSearching && (
              <TouchableOpacity style={styles.viewAllBtn} onPress={() => router.push('/(tabs)/shop')}>
                <Text style={styles.viewAllText}>View All</Text>
                <ChevronRight size={14} color="#8b5cf6" />
              </TouchableOpacity>
            )}
          </View>

          {/* Product Grid */}
          {loading ? (
            <ActivityIndicator size="large" color="#8b5cf6" style={{ marginTop: 40 }} />
          ) : (
            <View style={styles.productGrid}>
              {products.length > 0 ? products.map((item, i) => (
                <TouchableOpacity 
                  key={item.id} 
                  style={styles.productCardWrapper}
                  activeOpacity={0.9}
                  onPress={() => router.push(`/product/${item.id}`)}
                >
                  <GlassCard style={styles.pCard} intensity={25} borderRadius={18}>
                    {/* Just In Tag */}
                    {!isSearching && i < 2 && (
                      <View style={styles.justInTag}>
                        <Zap size={10} color="#fff" fill="#fff" />
                        <Text style={styles.justInText}>JUST IN</Text>
                      </View>
                    )}
                    
                    <View style={styles.pImageContainer}>
                      <Image 
                        source={{ uri: item.image_url || `https://picsum.photos/seed/${item.id}/400/500` }} 
                        style={styles.pImage} 
                        resizeMode="cover"
                      />
                      <LinearGradient 
                        colors={['transparent', 'rgba(0,0,0,0.6)']} 
                        style={styles.pGradient} 
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
                      <View style={styles.pMeta}>
                        <Text style={styles.pBrand}>{item.brand?.toUpperCase() || 'LUXE'}</Text>
                        <View style={styles.pRating}>
                          <Star size={10} color="#f59e0b" fill="#f59e0b" />
                          <Text style={styles.pRatingText}>{item.rating || '4.8'}</Text>
                        </View>
                      </View>
                      
                      <Text style={styles.pTitle} numberOfLines={1}>{item.name}</Text>
                      
                      <View style={styles.pFooter}>
                        <Text style={styles.pPrice}>₹{item.price?.toLocaleString()}</Text>
                        <TouchableOpacity 
                          style={styles.pAddBtn}
                          onPress={() => addToCart(item.id)}
                        >
                          <LinearGradient 
                            colors={['#8b5cf6', '#ec4899']} 
                            style={styles.pAddBtnGradient}
                          >
                            <ShoppingBag size={14} color="#fff" />
                          </LinearGradient>
                        </TouchableOpacity>
                      </View>
                    </View>
                  </GlassCard>
                </TouchableOpacity>
              )) : (
                <View style={styles.emptyResults}>
                  <Text style={styles.emptyText}>No products found for "{searchQuery}"</Text>
                </View>
              )}
            </View>
          )}
          
          <View style={{ height: 100 }} />
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 25, height: 70 },
  logo: { fontSize: 24, fontWeight: '900', color: '#fff', letterSpacing: 4, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  headerIcons: { flexDirection: 'row', gap: 15 },
  iconBtn: { width: 40, height: 40, justifyContent: 'center', alignItems: 'center', position: 'relative' },
  cartBadge: { position: 'absolute', top: 5, right: 5, backgroundColor: '#8b5cf6', width: 14, height: 14, borderRadius: 7, justifyContent: 'center', alignItems: 'center', zIndex: 1 },
  badgeText: { color: '#fff', fontSize: 8, fontWeight: '900' },

  searchBarContainer: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 15 },
  searchInputWrapper: { flex: 1, flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 15, paddingHorizontal: 15, height: 45, borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
  searchIconInside: { marginRight: 10 },
  searchInput: { flex: 1, color: '#fff', fontSize: 14, fontWeight: '500' },
  cancelBtn: { paddingVertical: 10 },
  cancelBtnText: { color: '#8b5cf6', fontSize: 14, fontWeight: '700' },

  heroWrapper: { position: 'relative' },
  heroSlide: { width: width, padding: 20 },
  heroCard: { height: 420, overflow: 'hidden' },
  heroImage: { width: '100%', height: '100%', position: 'absolute' },
  heroGradient: { position: 'absolute', bottom: 0, left: 0, right: 0, height: '75%' },
  heroContent: { position: 'absolute', bottom: 45, left: 30, right: 30 },
  heroTag: { color: '#8b5cf6', fontSize: 12, fontWeight: '900', letterSpacing: 3, marginBottom: 15 },
  heroTitle: { color: '#fff', fontSize: 45, fontWeight: '800', lineHeight: 50, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif', marginBottom: 25 },
  heroBtn: { backgroundColor: '#fff', paddingHorizontal: 22, paddingVertical: 14, borderRadius: 30, alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 8 },
  heroBtnText: { color: '#000', fontSize: 12, fontWeight: '900', letterSpacing: 1 },
  
  pagination: { flexDirection: 'row', justifyContent: 'center', gap: 8, position: 'absolute', bottom: 45, width: '100%' },
  dot: { height: 4, borderRadius: 2 },
  activeDot: { width: 24, backgroundColor: '#8b5cf6' },
  inactiveDot: { width: 8, backgroundColor: 'rgba(255,255,255,0.2)' },

  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-end', paddingHorizontal: 25, marginTop: 10, marginBottom: 25 },
  sectionTitle: { color: '#fff', fontSize: 22, fontWeight: '800', fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif', letterSpacing: 1 },
  sectionSubtitle: { color: '#64748b', fontSize: 13, marginTop: 4 },
  viewAllBtn: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  viewAllText: { color: '#8b5cf6', fontSize: 13, fontWeight: '700' },

  productGrid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 15, justifyContent: 'space-between' },
  productCardWrapper: { width: CARD_WIDTH, marginBottom: 25 },
  pCard: { padding: 0, overflow: 'hidden' },
  
  justInTag: { position: 'absolute', top: 10, left: 10, zIndex: 5, flexDirection: 'row', alignItems: 'center', backgroundColor: '#8b5cf6', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8, gap: 4 },
  justInText: { color: '#fff', fontSize: 8, fontWeight: '900', letterSpacing: 1 },
  
  pImageContainer: { width: '100%', height: 180, backgroundColor: '#1a1a1a', position: 'relative' },
  pImage: { width: '100%', height: '100%' },
  pGradient: { position: 'absolute', bottom: 0, left: 0, right: 0, height: '40%' },
  pWishlistBtn: { position: 'absolute', top: 10, right: 10, width: 32, height: 32, borderRadius: 16, overflow: 'hidden', zIndex: 10 },
  pWishlistBlur: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  
  pInfo: { padding: 10, justifyContent: 'center' },
  pMeta: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  pBrand: { color: '#8b5cf6', fontSize: 9, fontWeight: '900', letterSpacing: 1.2, flex: 1 },
  pRating: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  pRatingText: { color: '#94a3b8', fontSize: 10, fontWeight: '700' },
  
  pTitle: { color: '#fff', fontSize: 13, fontWeight: '700', marginBottom: 12 },
  
  pFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  pPrice: { color: '#fff', fontSize: 16, fontWeight: '900' },
  pAddBtn: { width: 36, height: 36, borderRadius: 12, overflow: 'hidden' },
  pAddBtnGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },

  emptyResults: { flex: 1, padding: 40, alignItems: 'center' },
  emptyText: { color: '#64748b', fontSize: 16, textAlign: 'center' },
});
