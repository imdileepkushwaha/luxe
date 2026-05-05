import React, { useState, useEffect, useRef } from 'react';
import { StyleSheet, Text, View, ScrollView, Image, TouchableOpacity, Dimensions, ActivityIndicator, SafeAreaView, Platform, Animated, FlatList } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { ChevronLeft, ShoppingBag, Star, Heart, Share2, ShieldCheck, Truck, RotateCcw, Info } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import Config from '@/constants/Config';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import LuxeHeader from '@/components/LuxeHeader';

const { width } = Dimensions.get('window');
const HEADER_HEIGHT = 100;

const stripHtml = (html: string) => {
  if (!html) return '';
  return html.replace(/<[^>]*>?/gm, '');
};

export default function ProductDetailScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  const { addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
  
  const [product, setProduct] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [selectedSize, setSelectedSize] = useState('M');
  
  const isFavorite = product ? isInWishlist(product.id) : false;
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  
  const scrollY = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    fetchProduct();
  }, [id]);

  const fetchProduct = async () => {
    try {
      const response = await fetch(`${Config.API_URL}/products.php`);
      const data = await response.json();
      if (data.ok) {
        const found = data.products.find((p: any) => p.id.toString() === id);
        setProduct(found);
      }
    } catch (error) {
      console.error('Fetch error:', error);
    } finally {
      setLoading(false);
    }
  };

  const headerOpacity = scrollY.interpolate({
    inputRange: [0, 200],
    outputRange: [0, 1],
    extrapolate: 'clamp',
  });

  const imageScale = scrollY.interpolate({
    inputRange: [-100, 0, 300],
    outputRange: [1.2, 1, 0.8],
    extrapolate: 'clamp',
  });

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <BackgroundScene />
        <ActivityIndicator size="large" color="#8b5cf6" />
      </View>
    );
  }

  if (!product) return null;

  const productImages = product.images && product.images.length > 0 ? product.images : [product.image_url];

  return (
    <View style={styles.container}>
      <BackgroundScene />
      
      {/* Dynamic Animated Header */}
      <Animated.View style={[styles.headerBlurContainer, { opacity: headerOpacity }]}>
        <BlurView intensity={80} tint="dark" style={StyleSheet.absoluteFill} />
      </Animated.View>

      <SafeAreaView style={styles.headerSafe}>
        <LuxeHeader showBack={true} />
      </SafeAreaView>

      <Animated.ScrollView 
        showsVerticalScrollIndicator={false} 
        onScroll={Animated.event(
          [{ nativeEvent: { contentOffset: { y: scrollY } } }],
          { useNativeDriver: true }
        )}
        scrollEventThrottle={16}
        contentContainerStyle={styles.scrollContent}
      >
        {/* Parallax Hero Image Gallery */}
        <View style={styles.imageGallerySection}>
          <Animated.View style={[styles.imageScaleContainer, { transform: [{ scale: imageScale }] }]}>
            <Image 
              source={{ uri: productImages[activeImageIndex] }} 
              style={styles.mainImage} 
              resizeMode="contain"
            />
            <LinearGradient 
              colors={['transparent', 'rgba(0,0,0,0.95)']} 
              style={styles.imageGradient} 
            />
          </Animated.View>
          
          {/* Thumbnail Strip */}
          {productImages.length > 1 && (
            <View style={styles.thumbnailStrip}>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.thumbnailScroll}>
                {productImages.map((img, idx) => (
                  <TouchableOpacity 
                    key={idx} 
                    onPress={() => setActiveImageIndex(idx)}
                    style={[styles.thumbnailBtn, activeImageIndex === idx && styles.thumbnailBtnActive]}
                  >
                    <Image source={{ uri: img }} style={styles.thumbnailImg} />
                  </TouchableOpacity>
                ))}
              </ScrollView>
            </View>
          )}
        </View>

        {/* Content Section */}
        <View style={styles.infoSection}>
          <View style={styles.tagRow}>
            <View style={styles.exclusiveBadge}>
              <Text style={styles.exclusiveText}>ELITE EDITION</Text>
            </View>
            <View style={styles.ratingBox}>
              <Star size={12} color="#f59e0b" fill="#f59e0b" />
              <Text style={styles.ratingText}>{product.rating || '4.9'}</Text>
            </View>
          </View>

          <Text style={styles.brand}>{product.brand?.toUpperCase() || 'LUXE'}</Text>
          <Text style={styles.title}>{product.name}</Text>

          <View style={styles.priceRow}>
            <View>
              <Text style={styles.price}>₹{product.price?.toLocaleString()}</Text>
              {product.original_price > product.price && (
                <Text style={styles.oldPrice}>₹{product.original_price?.toLocaleString()}</Text>
              )}
            </View>
            {product.original_price > product.price && (
              <GlassCard intensity={10} borderRadius={15} style={styles.offerTag}>
                <Text style={styles.offerText}>
                  {Math.round(((product.original_price - product.price) / product.original_price) * 100)}% OFF
                </Text>
              </GlassCard>
            )}
          </View>

          {/* Luxury Divider */}
          <LinearGradient 
            colors={['transparent', 'rgba(139, 92, 246, 0.3)', 'transparent']} 
            start={{x:0, y:0}} end={{x:1, y:0}}
            style={styles.divider}
          />

          <View style={styles.section}>
            <View style={styles.sectionHeaderRow}>
              <Text style={styles.sectionTitle}>SELECT SIZE</Text>
              <TouchableOpacity><Text style={styles.guideText}>Size Guide</Text></TouchableOpacity>
            </View>
            <View style={styles.sizeGrid}>
              {['S', 'M', 'L', 'XL', 'XXL'].map((size) => (
                <TouchableOpacity 
                  key={size}
                  onPress={() => setSelectedSize(size)}
                  style={[styles.sizeBtn, selectedSize === size && styles.sizeBtnActive]}
                >
                  <Text style={[styles.sizeBtnText, selectedSize === size && styles.sizeBtnTextActive]}>{size}</Text>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Details Card */}
          <GlassCard intensity={15} borderRadius={30} style={styles.detailsCard}>
            <View style={styles.detailTab}>
              <Info size={18} color="#8b5cf6" />
              <Text style={styles.detailTabText}>PRODUCT DETAILS</Text>
            </View>
            <Text style={styles.description}>
              {stripHtml(product.description) || "Crafted from superior materials, this luxury piece embodies elegance and sophistication. Features include premium stitching, high-durability fabric, and the iconic LUXE fit tailored for perfection."}
            </Text>
            
            <View style={styles.perksRow}>
              <View style={styles.perkItem}>
                <Truck size={18} color="#fff" />
                <Text style={styles.perkLabel}>Express Delivery</Text>
              </View>
              <View style={styles.perkItem}>
                <ShieldCheck size={18} color="#fff" />
                <Text style={styles.perkLabel}>Luxe Warranty</Text>
              </View>
            </View>
          </GlassCard>
          
          <View style={{ height: 120 }} />
        </View>
      </Animated.ScrollView>

      {/* Floating Checkout Bar */}
      <View style={styles.bottomBar}>
        <BlurView intensity={30} tint="dark" style={styles.bottomBlur}>
          <TouchableOpacity 
            activeOpacity={0.8}
            style={styles.addBtn}
            onPress={() => addToCart(product.id)}
          >
            <LinearGradient 
              colors={['#8b5cf6', '#ec4899']} 
              start={{x:0, y:0}} end={{x:1, y:0}}
              style={styles.addBtnGradient}
            >
              <View style={styles.btnIconBox}>
                <ShoppingBag size={20} color="#fff" />
              </View>
              <Text style={styles.addBtnText}>ADD TO ELITE BAG</Text>
              <Text style={styles.btnPrice}>₹{product.price?.toLocaleString()}</Text>
            </LinearGradient>
          </TouchableOpacity>
        </BlurView>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#000' },
  
  headerSafe: { position: 'absolute', top: 0, left: 0, right: 0, zIndex: 20 },
  headerBlurContainer: { position: 'absolute', top: 0, left: 0, right: 0, height: 100, zIndex: 15 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, height: 60 },
  headerTitle: { color: '#fff', fontSize: 16, fontWeight: '800', letterSpacing: 1 },
  headerIconBtn: { width: 45, height: 45, borderRadius: 22.5, backgroundColor: 'rgba(0,0,0,0.4)', justifyContent: 'center', alignItems: 'center', borderWidth: 0.5, borderColor: 'rgba(255,255,255,0.1)' },
  headerActions: { flexDirection: 'row', gap: 12 },
  
  scrollContent: { paddingBottom: 0 },
  imageGallerySection: { width: width, height: width * 1.4, backgroundColor: '#000' },
  imageScaleContainer: { width: width, height: width * 1.3 },
  mainImage: { width: '100%', height: '100%' },
  imageGradient: { position: 'absolute', bottom: 0, left: 0, right: 0, height: 250 },
  
  thumbnailStrip: { position: 'absolute', bottom: 40, width: '100%', paddingHorizontal: 20 },
  thumbnailScroll: { gap: 12, paddingRight: 40 },
  thumbnailBtn: { width: 60, height: 80, borderRadius: 12, overflow: 'hidden', borderWidth: 1.5, borderColor: 'rgba(255,255,255,0.2)', backgroundColor: 'rgba(0,0,0,0.5)' },
  thumbnailBtnActive: { borderColor: '#8b5cf6', borderWidth: 2 },
  thumbnailImg: { width: '100%', height: '100%' },

  infoSection: { padding: 25, marginTop: -60, borderTopLeftRadius: 45, borderTopRightRadius: 45, backgroundColor: 'transparent' },
  tagRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
  exclusiveBadge: { backgroundColor: 'rgba(139, 92, 246, 0.15)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 30, borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.4)' },
  exclusiveText: { color: '#8b5cf6', fontSize: 10, fontWeight: '900', letterSpacing: 2 },
  ratingBox: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  ratingText: { color: '#fff', fontWeight: '800', fontSize: 13 },
  
  brand: { color: '#8b5cf6', fontSize: 14, fontWeight: '900', letterSpacing: 3, marginBottom: 8 },
  title: { color: '#fff', fontSize: 32, fontWeight: '800', fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif', lineHeight: 38, marginBottom: 20 },
  
  priceRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 30 },
  price: { color: '#fff', fontSize: 32, fontWeight: '900' },
  oldPrice: { color: '#64748b', fontSize: 18, textDecorationLine: 'line-through', marginTop: 2 },
  offerTag: { paddingHorizontal: 15, paddingVertical: 10 },
  offerText: { color: '#10b981', fontSize: 12, fontWeight: '900' },
  
  divider: { height: 1, width: '100%', marginBottom: 30 },
  
  section: { marginBottom: 35 },
  sectionHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
  sectionTitle: { color: '#94a3b8', fontSize: 13, fontWeight: '900', letterSpacing: 2 },
  guideText: { color: '#8b5cf6', fontSize: 12, fontWeight: '700', textDecorationLine: 'underline' },
  sizeGrid: { flexDirection: 'row', gap: 12 },
  sizeBtn: { flex: 1, height: 60, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.03)', justifyContent: 'center', alignItems: 'center', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
  sizeBtnActive: { backgroundColor: '#fff', borderColor: '#fff' },
  sizeBtnText: { color: '#fff', fontSize: 16, fontWeight: '700' },
  sizeBtnTextActive: { color: '#000' },
  
  detailsCard: { padding: 30, marginBottom: 20 },
  detailTab: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 20 },
  detailTabText: { color: '#fff', fontSize: 14, fontWeight: '800', letterSpacing: 1 },
  description: { color: '#94a3b8', fontSize: 16, lineHeight: 28, fontWeight: '500', marginBottom: 30 },
  perksRow: { flexDirection: 'row', gap: 20 },
  perkItem: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  perkLabel: { color: '#fff', fontSize: 11, fontWeight: '700' },
  
  bottomBar: { position: 'absolute', bottom: 25, left: 20, right: 20, height: 85, borderRadius: 40, overflow: 'hidden', elevation: 20, shadowColor: '#8b5cf6', shadowOffset: { width: 0, height: 10 }, shadowOpacity: 0.5, shadowRadius: 20 },
  bottomBlur: { flex: 1, padding: 8 },
  addBtn: { flex: 1, borderRadius: 32, overflow: 'hidden' },
  addBtnGradient: { flex: 1, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 20 },
  btnIconBox: { width: 45, height: 45, borderRadius: 18, backgroundColor: 'rgba(255,255,255,0.15)', justifyContent: 'center', alignItems: 'center' },
  addBtnText: { flex: 1, color: '#fff', fontSize: 14, fontWeight: '900', letterSpacing: 1.5, marginLeft: 15 },
  btnPrice: { color: '#fff', fontSize: 18, fontWeight: '900' },
});
