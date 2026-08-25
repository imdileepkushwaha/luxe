import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  Image,
  TouchableOpacity,
  Pressable,
  Dimensions,
  ActivityIndicator,
  Platform,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { ShoppingBag, Star, Heart, ShieldCheck, Truck, Check, Maximize2 } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import LuxeHeader from '@/components/LuxeHeader';
import EmptyState from '@/components/EmptyState';
import ImageZoomModal from '@/components/ImageZoomModal';
import { fetchProducts, formatPrice, type Product, type ProductColor, type ProductReview } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

const { width, height: SCREEN_H } = Dimensions.get('window');
const PAGE_PAD = 16;
const MAX_IMAGE_H = SCREEN_H * 0.78;

const stripHtml = (html: string) => {
  if (!html) return '';
  return html
    .replace(/<[^>]*>?/gm, ' ')
    .replace(/&nbsp;/g, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/\s+/g, ' ')
    .trim();
};

function colorHex(name: string): string {
  const n = name.toLowerCase();
  if (/(white|ivory|cream|pearl)/.test(n)) return '#f1f5f9';
  if (/(black|charcoal|midnight|jet)/.test(n)) return '#0f172a';
  if (/(red|crimson|maroon|burgundy)/.test(n)) return '#ef4444';
  if (/(blue|navy|indigo|azure)/.test(n)) return '#1e40af';
  if (/(teal|cyan|aqua)/.test(n)) return '#0f766e';
  if (/(green|olive|emerald|mint)/.test(n)) return '#059669';
  if (/(yellow|gold|mustard|amber)/.test(n)) return '#ca8a04';
  if (/(orange|coral|peach)/.test(n)) return '#f97316';
  if (/(pink|rose|magenta)/.test(n)) return '#ec4899';
  if (/(purple|violet|lavender)/.test(n)) return '#8b5cf6';
  if (/(gray|grey|silver)/.test(n)) return '#64748b';
  if (/(brown|tan|beige|khaki|camel)/.test(n)) return '#92400e';
  return '#64748b';
}

function formatReviewDate(value?: string): string {
  if (!value) return '';
  const d = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

export default function ProductDetailScreen() {
  const { id } = useLocalSearchParams();
  const router = useRouter();
  const { addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
  const { colors, isDark } = useAppTheme();

  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [selectedSize, setSelectedSize] = useState('');
  const [selectedColor, setSelectedColor] = useState('');
  const [activeImageIndex, setActiveImageIndex] = useState(0);
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);
  const [zoomOpen, setZoomOpen] = useState(false);
  const [imageHeight, setImageHeight] = useState(width);

  const isFavorite = product ? isInWishlist(product.id) : false;

  useEffect(() => {
    fetchProduct();
  }, [id]);

  useEffect(() => {
    const uri =
      product?.images?.[activeImageIndex] ||
      product?.images?.[0] ||
      product?.image_url ||
      '';
    if (!uri) {
      setImageHeight(width);
      return;
    }
    Image.getSize(
      uri,
      (imgW, imgH) => {
        if (imgW > 0 && imgH > 0) {
          const autoH = width * (imgH / imgW);
          setImageHeight(Math.min(MAX_IMAGE_H, Math.max(220, autoH)));
        }
      },
      () => setImageHeight(width)
    );
  }, [product, activeImageIndex]);

  const fetchProduct = async () => {
    try {
      setError('');
      const data = await fetchProducts({ id: String(id) });
      const found = data.product;
      setProduct(found);
      setSelectedSize(found?.sizes?.[0] || '');
      setSelectedColor(found?.color_swatches?.[0]?.name || found?.colors?.[0] || '');
      setActiveImageIndex(0);
    } catch (e: any) {
      setError(e?.message || 'Could not load this product');
      setProduct(null);
    } finally {
      setLoading(false);
    }
  };

  const handleAddToBag = async () => {
    if (!product || adding) return;
    setAdding(true);
    try {
      const ok = await addToCart({
        id: product.id,
        name: product.name,
        price: product.price,
        image_url: product.image_url || product.images?.[0] || '',
      });
      if (ok) {
        setAdded(true);
        setTimeout(() => setAdded(false), 1800);
      }
    } finally {
      setAdding(false);
    }
  };

  if (loading) {
    return (
      <View style={[styles.loadingContainer, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <ActivityIndicator size="large" color={isDark ? '#8b5cf6' : '#0f172a'} />
      </View>
    );
  }

  if (!product) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Product" />
        </SafeAreaView>
        <EmptyState
          title="Product not found"
          message={error || 'This item is not available.'}
          actionLabel="Back to shop"
          onAction={() => router.push('/(tabs)/shop')}
        />
      </View>
    );
  }

  const sizeOptions = product.sizes?.length ? product.sizes : [];
  const colorSwatches: ProductColor[] =
    product.color_swatches?.length
      ? product.color_swatches
      : (product.colors || []).map((name) => ({
          name,
          hex: colorHex(name),
          product_id: product.id,
        }));
  const reviews: ProductReview[] = product.reviews || [];
  const productImages =
    product.images?.length > 0 ? product.images : product.image_url ? [product.image_url] : [];
  const discount =
    product.original_price > product.price && product.original_price > 0
      ? Math.round(((product.original_price - product.price) / product.original_price) * 100)
      : 0;
  const detailsText =
    stripHtml(product.description || '') || 'Premium materials and a tailored LUXE fit.';

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />

      <SafeAreaView style={[styles.headerSafe, { backgroundColor: colors.bg }]} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title="Product" />
      </SafeAreaView>

      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
      >
        <Pressable
          style={[styles.gallery, { height: imageHeight, backgroundColor: colors.productImageBg }]}
          onPress={() => productImages.length > 0 && setZoomOpen(true)}
        >
          {productImages.length > 0 ? (
            <Image
              source={{ uri: productImages[activeImageIndex] }}
              style={styles.mainImage}
              resizeMode="contain"
            />
          ) : (
            <View style={[styles.mainImage, { backgroundColor: colors.productImageBg }]} />
          )}
          {discount > 0 && (
            <View style={styles.saveBadge} pointerEvents="none">
              <Text style={styles.saveText}>{discount}% OFF</Text>
            </View>
          )}
          {productImages.length > 0 && (
            <View style={styles.zoomHint} pointerEvents="none">
              <Maximize2 size={15} color="#fff" />
            </View>
          )}
        </Pressable>

        {productImages.length > 1 && (
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.thumbs}
          >
            {productImages.map((img, idx) => (
              <TouchableOpacity
                key={idx}
                onPress={() => setActiveImageIndex(idx)}
                style={[styles.thumb, { borderColor: colors.border }, activeImageIndex === idx && { borderColor: isDark ? '#a78bfa' : '#0f172a' }]}
              >
                <Image source={{ uri: img }} style={styles.thumbImg} />
              </TouchableOpacity>
            ))}
          </ScrollView>
        )}

        <View style={styles.body}>
          <View style={styles.tagRow}>
            <View style={styles.badge}>
              <Text style={styles.badgeText}>
                {(product.badge || product.category || 'LUXE').toUpperCase()}
              </Text>
            </View>
            <View style={styles.tagRight}>
              <View style={styles.ratingBox}>
                <Star size={12} color="#f59e0b" fill="#f59e0b" />
                <Text style={[styles.ratingText, { color: colors.text }]}>
                  {product.rating ? product.rating.toFixed(1) : '—'}
                </Text>
                <Text style={[styles.reviewCount, { color: colors.muted }]}>
                  ({product.review_count || product.reviews?.length || 0} reviews)
                </Text>
              </View>
              <TouchableOpacity
                onPress={() =>
                  isFavorite ? removeFromWishlist(product.id) : addToWishlist(product)
                }
                style={[styles.wishBtn, { backgroundColor: colors.headerBtn }]}
              >
                <Heart
                  size={18}
                  color={isFavorite ? '#ef4444' : colors.icon}
                  fill={isFavorite ? '#ef4444' : 'transparent'}
                />
              </TouchableOpacity>
            </View>
          </View>

          <Text style={[styles.brand, { color: isDark ? '#a78bfa' : '#ef4444' }]}>{(product.brand || 'LUXE').toUpperCase()}</Text>
          <Text style={[styles.title, { color: colors.text }]}>{product.name}</Text>

          <View style={styles.priceRow}>
            <Text style={[styles.price, { color: colors.text }]}>{formatPrice(product.price)}</Text>
            {discount > 0 && (
              <>
                <Text style={styles.oldPrice}>{formatPrice(product.original_price)}</Text>
                <View style={styles.offPill}>
                  <Text style={styles.offPillText}>{discount}% off</Text>
                </View>
              </>
            )}
          </View>

          {colorSwatches.length > 0 && (
            <View style={styles.block}>
              <Text style={[styles.blockLabel, { color: colors.muted }]}>
                Color{selectedColor ? ` · ${selectedColor}` : ''}
              </Text>
              <View style={styles.colorRow}>
                {colorSwatches.map((swatch) => {
                  const on = selectedColor === swatch.name;
                  const isLightSwatch = ['#f1f5f9', '#ffffff', '#fff'].includes(swatch.hex.toLowerCase());
                  return (
                    <TouchableOpacity
                      key={`${swatch.product_id}-${swatch.name}`}
                      onPress={() => {
                        if (swatch.product_id && swatch.product_id !== product.id) {
                          router.push(`/product/${swatch.product_id}`);
                          return;
                        }
                        setSelectedColor(swatch.name);
                      }}
                      style={styles.colorItem}
                      accessibilityLabel={swatch.name}
                    >
                      <View
                        style={[
                          styles.colorDot,
                          {
                            backgroundColor: swatch.hex,
                            borderColor: on ? (isDark ? '#fff' : '#0f172a') : isLightSwatch ? '#cbd5e1' : colors.border,
                            borderWidth: on ? 2 : 1,
                          },
                        ]}
                      />
                      <Text
                        style={[styles.colorName, { color: on ? colors.text : colors.muted }]}
                        numberOfLines={1}
                      >
                        {swatch.name}
                      </Text>
                    </TouchableOpacity>
                  );
                })}
              </View>
            </View>
          )}

          {sizeOptions.length > 0 && (
            <View style={styles.block}>
              <Text style={[styles.blockLabel, { color: colors.muted }]}>Select size</Text>
              <View style={styles.sizeRow}>
                {sizeOptions.map((size) => (
                  <TouchableOpacity
                    key={size}
                    onPress={() => setSelectedSize(size)}
                    style={[
                      styles.sizeBtn,
                      { backgroundColor: colors.card, borderColor: colors.border },
                      selectedSize === size && (isDark ? styles.sizeBtnOn : { backgroundColor: '#0f172a', borderColor: '#0f172a' }),
                    ]}
                  >
                    <Text
                      style={[
                        styles.sizeText,
                        { color: colors.text },
                        selectedSize === size && (isDark ? styles.sizeTextOn : { color: '#fff' }),
                      ]}
                    >
                      {size}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            </View>
          )}

          <View style={[styles.detailsCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={[styles.detailsHeading, { color: colors.text }]}>Product details</Text>
            <Text style={[styles.description, { color: colors.textSecondary }]}>{detailsText}</Text>
            <View style={styles.perks}>
              <View style={styles.perk}>
                <Truck size={16} color={isDark ? '#c4b5fd' : colors.accent} />
                <Text style={[styles.perkText, { color: colors.textSecondary }]}>Express delivery</Text>
              </View>
              <View style={styles.perk}>
                <ShieldCheck size={16} color={isDark ? '#c4b5fd' : colors.accent} />
                <Text style={[styles.perkText, { color: colors.textSecondary }]}>LUXE warranty</Text>
              </View>
            </View>
          </View>

          <View style={[styles.detailsCard, { backgroundColor: colors.card, borderColor: colors.border, marginTop: 16 }]}>
            <Text style={[styles.detailsHeading, { color: colors.text }]}>
              Reviews{reviews.length ? ` (${reviews.length})` : ''}
            </Text>
            {reviews.length === 0 ? (
              <Text style={[styles.description, { color: colors.muted }]}>
                No reviews yet. Be the first to review this product.
              </Text>
            ) : (
              reviews.map((rev, i) => (
                <View
                  key={`${rev.customer_name}-${rev.created_at}-${i}`}
                  style={[styles.reviewItem, i > 0 && { borderTopColor: colors.hairline, borderTopWidth: StyleSheet.hairlineWidth }]}
                >
                  <View style={styles.reviewHead}>
                    <View style={[styles.reviewAvatar, { backgroundColor: isDark ? 'rgba(139,92,246,0.28)' : '#0f172a' }]}>
                      <Text style={styles.reviewAvatarText}>
                        {(rev.customer_name || 'C').charAt(0).toUpperCase()}
                      </Text>
                    </View>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.reviewName, { color: colors.text }]}>{rev.customer_name}</Text>
                      <Text style={[styles.reviewMeta, { color: colors.muted }]}>{formatReviewDate(rev.created_at)}</Text>
                    </View>
                    <Text style={styles.reviewStars}>
                      {'★'.repeat(rev.rating)}
                      {'☆'.repeat(Math.max(0, 5 - rev.rating))}
                    </Text>
                  </View>
                  {!!rev.review_text && (
                    <Text style={[styles.reviewBody, { color: colors.textSecondary }]}>{rev.review_text}</Text>
                  )}
                  {!!rev.seller_response && (
                    <View style={[styles.sellerReply, { backgroundColor: colors.cardMuted, borderColor: colors.border }]}>
                      <Text style={[styles.sellerReplyLbl, { color: colors.muted }]}>Seller response</Text>
                      <Text style={[styles.reviewBody, { color: colors.textSecondary, marginTop: 4 }]}>{rev.seller_response}</Text>
                    </View>
                  )}
                </View>
              ))
            )}
          </View>
        </View>
      </ScrollView>

      <View
        style={[
          styles.bottomBar,
          {
            backgroundColor: colors.tabBar,
            borderTopColor: colors.tabBorder,
            shadowColor: colors.shadowColor,
            shadowOpacity: isDark ? 0 : 0.1,
            shadowRadius: isDark ? 0 : 16,
            shadowOffset: { width: 0, height: -6 },
            elevation: isDark ? 0 : 10,
          },
        ]}
        pointerEvents="box-none"
      >
        {!isDark && (
          <View style={styles.lightPriceCol}>
            <Text style={[styles.lightPriceHint, { color: colors.muted }]}>Price</Text>
            <Text style={[styles.lightPriceValue, { color: colors.text }]}>{formatPrice(product.price)}</Text>
          </View>
        )}
        <Pressable
          onPress={handleAddToBag}
          disabled={adding}
          style={({ pressed }) => [styles.addBtn, !isDark && styles.addBtnLight, pressed && { opacity: 0.88 }]}
        >
          {isDark ? (
            <LinearGradient
              colors={added ? ['#059669', '#10b981'] : colors.cta}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.addBtnGrad}
              pointerEvents="none"
            >
              {added ? <Check size={18} color="#fff" /> : <ShoppingBag size={18} color="#fff" />}
              <Text style={styles.addBtnText}>{added ? 'ADDED TO BAG' : adding ? 'ADDING…' : 'ADD TO BAG'}</Text>
              <Text style={styles.addBtnPrice}>{formatPrice(product.price)}</Text>
            </LinearGradient>
          ) : (
            <View
              style={[styles.addBtnLightInner, { backgroundColor: added ? '#059669' : '#0f172a' }]}
              pointerEvents="none"
            >
              {added ? <Check size={18} color="#fff" /> : <ShoppingBag size={16} color="#fff" />}
              <Text style={styles.addBtnLightText}>
                {added ? 'Added to bag' : adding ? 'Adding…' : 'Add to bag'}
              </Text>
            </View>
          )}
        </Pressable>
      </View>
      <ImageZoomModal
        visible={zoomOpen}
        images={productImages}
        index={activeImageIndex}
        onClose={() => setZoomOpen(false)}
        onIndexChange={setActiveImageIndex}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#08080e' },
  headerSafe: { zIndex: 20, backgroundColor: 'rgba(8,8,14,0.92)' },
  scrollContent: { paddingBottom: 108 },

  gallery: {
    width,
    backgroundColor: '#12121a',
    position: 'relative',
    overflow: 'hidden',
  },
  mainImage: { width: '100%', height: '100%' },
  saveBadge: {
    position: 'absolute',
    top: 14,
    left: PAGE_PAD,
    backgroundColor: '#10b981',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 8,
  },
  saveText: { color: '#fff', fontSize: 11, fontWeight: '800' },
  zoomHint: {
    position: 'absolute',
    top: 14,
    right: 14,
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: 'rgba(0,0,0,0.55)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },

  thumbs: { paddingHorizontal: PAGE_PAD, paddingTop: 12, paddingBottom: 4, gap: 8 },
  thumb: {
    width: 56,
    height: 72,
    borderRadius: 10,
    overflow: 'hidden',
    borderWidth: 1.5,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  thumbOn: { borderColor: '#a78bfa' },
  thumbImg: { width: '100%', height: '100%' },

  body: { paddingHorizontal: PAGE_PAD, paddingTop: 16, paddingBottom: 8 },
  tagRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  badge: {
    backgroundColor: 'rgba(139,92,246,0.16)',
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(139,92,246,0.4)',
  },
  badgeText: { color: '#c4b5fd', fontSize: 10, fontWeight: '800', letterSpacing: 1 },
  tagRight: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  ratingBox: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  ratingText: { color: '#fff', fontWeight: '700', fontSize: 13 },
  reviewCount: { fontSize: 12, fontWeight: '600' },
  wishBtn: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: 'rgba(255,255,255,0.07)',
    alignItems: 'center',
    justifyContent: 'center',
  },

  brand: { color: '#a78bfa', fontSize: 12, fontWeight: '800', letterSpacing: 1.6, marginBottom: 6 },
  title: {
    color: '#fff',
    fontSize: 24,
    fontWeight: '800',
    lineHeight: 30,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
    marginBottom: 12,
  },
  priceRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 10, marginBottom: 20 },
  price: { color: '#fff', fontSize: 26, fontWeight: '800' },
  oldPrice: { color: '#64748b', fontSize: 16, textDecorationLine: 'line-through' },
  offPill: { backgroundColor: 'rgba(16,185,129,0.15)', paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8 },
  offPillText: { color: '#34d399', fontSize: 12, fontWeight: '800' },

  block: { marginBottom: 20 },
  blockLabel: { color: '#94a3b8', fontSize: 12, fontWeight: '800', letterSpacing: 1, marginBottom: 10 },
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  colorItem: { alignItems: 'center', width: 56, gap: 6 },
  colorDot: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  colorName: { fontSize: 11, fontWeight: '700', textAlign: 'center' },
  sizeRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  sizeBtn: {
    minWidth: 52,
    height: 44,
    paddingHorizontal: 14,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  sizeBtnOn: { backgroundColor: '#fff', borderColor: '#fff' },
  sizeText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  sizeTextOn: { color: '#111' },

  detailsCard: {
    padding: 16,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  detailsHeading: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 10,
  },
  description: { color: '#94a3b8', fontSize: 14, lineHeight: 22 },
  perks: { flexDirection: 'row', flexWrap: 'wrap', gap: 14, marginTop: 16 },
  perk: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  perkText: { color: '#e2e8f0', fontSize: 12, fontWeight: '600' },
  reviewItem: { paddingTop: 14, marginTop: 4 },
  reviewHead: { flexDirection: 'row', alignItems: 'center', gap: 10, marginBottom: 8 },
  reviewAvatar: {
    width: 34,
    height: 34,
    borderRadius: 17,
    alignItems: 'center',
    justifyContent: 'center',
  },
  reviewAvatarText: { color: '#fff', fontSize: 14, fontWeight: '800' },
  reviewName: { fontSize: 14, fontWeight: '700' },
  reviewMeta: { fontSize: 11, marginTop: 2 },
  reviewStars: { color: '#f59e0b', fontSize: 12, fontWeight: '700' },
  reviewBody: { fontSize: 13, lineHeight: 19 },
  sellerReply: { marginTop: 10, padding: 10, borderRadius: 10, borderWidth: 1 },
  sellerReplyLbl: { fontSize: 11, fontWeight: '800', letterSpacing: 0.4 },

  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    zIndex: 50,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingHorizontal: PAGE_PAD,
    paddingTop: 10,
    paddingBottom: Platform.OS === 'ios' ? 24 : 14,
    backgroundColor: 'rgba(8,8,14,0.96)',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.08)',
  },
  lightPriceCol: { paddingRight: 4 },
  lightPriceHint: { fontSize: 11, fontWeight: '600' },
  lightPriceValue: { fontSize: 18, fontWeight: '800', marginTop: 1 },
  addBtn: { flex: 1, borderRadius: 16, overflow: 'hidden' },
  addBtnLight: { borderRadius: 999 },
  addBtnGrad: {
    minHeight: 54,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    gap: 10,
  },
  addBtnText: { flex: 1, color: '#fff', fontSize: 14, fontWeight: '800', letterSpacing: 0.8 },
  addBtnPrice: { color: '#fff', fontSize: 16, fontWeight: '800' },
  addBtnLightInner: {
    minHeight: 48,
    borderRadius: 999,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    paddingHorizontal: 18,
  },
  addBtnLightText: { color: '#fff', fontSize: 15, fontWeight: '700' },
});
