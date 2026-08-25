import React, { useState, useEffect, useCallback } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  Image,
  TouchableOpacity,
  ActivityIndicator,
  Platform,
  RefreshControl,
  TextInput,
} from 'react-native';
import { Search, ChevronRight, X, LayoutGrid } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import ProductCard from '@/components/ProductCard';
import EmptyState from '@/components/EmptyState';
import HeroBanner from '@/components/HeroBanner';
import OfferSection from '@/components/OfferSection';
import { fetchProducts, fetchCategories, categoryLabel, type Product } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

export default function HomeScreen() {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const { addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();

  const [products, setProducts] = useState<Product[]>([]);
  const [heroSlides, setHeroSlides] = useState<Product[]>([]);
  const [categories, setCategories] = useState<string[]>(['All']);
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchDraft, setSearchDraft] = useState('');
  const [offerProducts, setOfferProducts] = useState<Product[]>([]);

  const load = useCallback(async () => {
    try {
      setError('');
      const isFiltered = !!searchQuery || selectedCategory !== 'All';
      const [featuredRes, cats, gridRes, offerRes] = await Promise.all([
        fetchProducts({ limit: 5 }),
        fetchCategories(),
        isFiltered
          ? fetchProducts({
              limit: searchQuery ? 24 : 12,
              search: searchQuery || undefined,
              category: selectedCategory,
            })
          : Promise.resolve(null),
        fetchProducts({ limit: 10, offers: true }),
      ]);

      const featured = featuredRes.products.filter((p) => !!p.image_url).slice(0, 5);
      setHeroSlides(featured.length ? featured : featuredRes.products.slice(0, 5));
      setCategories(cats);
      setProducts(gridRes ? gridRes.products : featuredRes.products);
      setOfferProducts(offerRes.products);
    } catch (e: any) {
      setError(e?.message || 'Could not load products');
      setProducts([]);
      setOfferProducts([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [searchQuery, selectedCategory]);

  useEffect(() => {
    setLoading(true);
    load();
  }, [load]);

  const onRefresh = () => {
    setRefreshing(true);
    load();
  };

  const isFiltered = !!searchQuery || selectedCategory !== 'All';
  const gridProducts = products;

  const categoryCovers: Record<string, string> = {};
  for (const p of [...heroSlides, ...products]) {
    const key = (p.category || '').toLowerCase();
    if (key && p.image_url && !categoryCovers[key]) {
      categoryCovers[key] = p.image_url;
    }
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader />

        <ScrollView
          showsVerticalScrollIndicator={false}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={isDark ? '#8b5cf6' : '#0f172a'} />}
        >
          <View style={styles.searchWrap}>
            <View style={[styles.searchBox, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}>
              <Search size={18} color={colors.iconMuted} />
              <TextInput
                placeholder="Search LUXE products"
                placeholderTextColor={colors.placeholder}
                style={[styles.searchInput, { color: colors.text }]}
                value={searchDraft}
                onChangeText={setSearchDraft}
                returnKeyType="search"
                onSubmitEditing={() => setSearchQuery(searchDraft.trim())}
              />
              {searchDraft.length > 0 && (
                <TouchableOpacity
                  onPress={() => {
                    setSearchDraft('');
                    setSearchQuery('');
                  }}
                >
                  <X size={16} color={colors.iconMuted} />
                </TouchableOpacity>
              )}
            </View>
          </View>

          {(heroSlides.length > 0 || !loading) && <HeroBanner slides={heroSlides} />}

          <View style={styles.sectionHeader}>
            <View>
              <Text style={[styles.sectionTitle, { color: colors.text }]}>Categories</Text>
              <Text style={[styles.sectionSubtitle, { color: colors.muted }]}>Shop by collection</Text>
            </View>
            <TouchableOpacity style={styles.viewAllBtn} onPress={() => router.push('/(tabs)/shop')}>
              <Text style={[styles.viewAllText, { color: isDark ? '#a78bfa' : '#ef4444' }]}>View all</Text>
              <ChevronRight size={14} color={isDark ? '#a78bfa' : '#ef4444'} />
            </TouchableOpacity>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.catRow}>
            {categories.map((cat) => {
              const active = selectedCategory === cat;
              const cover = cat !== 'All' ? categoryCovers[cat.toLowerCase()] : '';
              return (
                <TouchableOpacity
                  key={cat}
                  onPress={() => setSelectedCategory(cat)}
                  style={[
                    styles.catCard,
                    { backgroundColor: colors.card, borderColor: colors.border },
                    active && { borderColor: isDark ? '#a78bfa' : '#0f172a', borderWidth: 1.5 },
                  ]}
                  activeOpacity={0.9}
                >
                  {cover ? (
                    <Image source={{ uri: cover }} style={styles.catImage} />
                  ) : (
                    <View style={styles.catImageFallback}>
                      <LayoutGrid size={22} color={active ? (isDark ? '#ddd6fe' : '#0f172a') : colors.iconMuted} />
                    </View>
                  )}
                  <LinearGradient
                    colors={
                      isDark
                        ? ['transparent', active ? 'rgba(76,29,149,0.92)' : 'rgba(0,0,0,0.78)']
                        : ['transparent', active ? 'rgba(15,23,42,0.82)' : 'rgba(15,23,42,0.55)']
                    }
                    style={styles.catFade}
                  />
                  <Text style={[styles.catLabel, active && styles.catLabelActive]} numberOfLines={1}>
                    {categoryLabel(cat)}
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {!searchQuery && <OfferSection products={offerProducts} />}

          <View style={styles.sectionHeader}>
            <View>
              <Text style={[styles.sectionTitle, { color: colors.text }]}>{isFiltered ? 'Results' : 'New arrivals'}</Text>
              <Text style={[styles.sectionSubtitle, { color: colors.muted }]}>
                {loading ? 'Loading…' : `${products.length} product${products.length === 1 ? '' : 's'} from the store`}
              </Text>
            </View>
            {!isFiltered && (
              <TouchableOpacity style={styles.viewAllBtn} onPress={() => router.push('/(tabs)/shop')}>
                <Text style={[styles.viewAllText, { color: isDark ? '#a78bfa' : '#ef4444' }]}>View all</Text>
                <ChevronRight size={14} color={isDark ? '#a78bfa' : '#ef4444'} />
              </TouchableOpacity>
            )}
          </View>

          {loading ? (
            <ActivityIndicator size="large" color={isDark ? '#8b5cf6' : '#0f172a'} style={{ marginTop: 36 }} />
          ) : error ? (
            <EmptyState title="Could not load products" message={error} onAction={load} />
          ) : products.length === 0 ? (
            <EmptyState
              title="No products found"
              message={isFiltered ? 'Try another search or category.' : 'No live products in the database yet.'}
              actionLabel={isFiltered ? 'Clear filters' : 'Retry'}
              onAction={() => {
                setSearchDraft('');
                setSearchQuery('');
                setSelectedCategory('All');
                if (!isFiltered) load();
              }}
            />
          ) : (
            <View style={styles.grid}>
              {gridProducts.map((item) => (
                <ProductCard
                  key={item.id}
                  product={item}
                  onPress={() => router.push(`/product/${item.id}`)}
                  wished={isInWishlist(item.id)}
                  onWishlist={() => (isInWishlist(item.id) ? removeFromWishlist(item.id) : addToWishlist(item))}
                  onAdd={() => addToCart(item)}
                />
              ))}
            </View>
          )}

          <View style={{ height: 88 }} />
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  searchWrap: { paddingHorizontal: 16, paddingBottom: 14 },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    height: 48,
    borderRadius: 16,
    paddingHorizontal: 14,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  searchInput: { flex: 1, color: '#fff', fontSize: 15 },

  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    paddingHorizontal: 16,
    marginTop: 18,
    marginBottom: 12,
  },
  sectionTitle: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
  sectionSubtitle: { color: '#64748b', fontSize: 12, marginTop: 4 },
  viewAllBtn: { flexDirection: 'row', alignItems: 'center', gap: 2 },
  viewAllText: { color: '#a78bfa', fontSize: 13, fontWeight: '700' },

  catRow: { paddingHorizontal: 16, gap: 10, paddingBottom: 4 },
  catCard: {
    width: 118,
    height: 92,
    borderRadius: 16,
    overflow: 'hidden',
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  catCardActive: { borderColor: '#a78bfa', borderWidth: 1.5 },
  catImage: { width: '100%', height: '100%' },
  catImageFallback: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(139,92,246,0.12)',
  },
  catFade: { position: 'absolute', left: 0, right: 0, bottom: 0, height: '70%' },
  catLabel: {
    position: 'absolute',
    left: 8,
    right: 8,
    bottom: 8,
    color: '#e2e8f0',
    fontSize: 12,
    fontWeight: '800',
    textAlign: 'center',
  },
  catLabelActive: { color: '#fff' },

  grid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 16, justifyContent: 'space-between', alignItems: 'flex-start' },
});
