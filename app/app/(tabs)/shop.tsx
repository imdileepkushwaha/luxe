import React, { useEffect, useState, useRef, useCallback } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { Search, X } from 'lucide-react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import ProductCard from '@/components/ProductCard';
import EmptyState from '@/components/EmptyState';
import { fetchProducts, fetchCategories, categoryLabel, type Product } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

export default function ShopScreen() {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const searchInputRef = useRef<TextInput>(null);
  const { addToCart } = useCart();
  const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
  const [categories, setCategories] = useState<string[]>(['All']);
  const [products, setProducts] = useState<Product[]>([]);
  const [selectedCategory, setSelectedCategory] = useState('All');
  const [searchQuery, setSearchQuery] = useState('');
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const loadCategories = useCallback(async () => {
    try {
      setCategories(await fetchCategories());
    } catch {
      /* keep All */
    }
  }, []);

  const loadProducts = useCallback(async () => {
    try {
      setError('');
      const data = await fetchProducts({
        limit: 40,
        category: selectedCategory,
        search: searchQuery || undefined,
      });
      setProducts(data.products);
    } catch (e: any) {
      setError(e?.message || 'Could not load products');
      setProducts([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [selectedCategory, searchQuery]);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    const t = setTimeout(() => {
      setLoading(true);
      loadProducts();
    }, searchQuery ? 350 : 0);
    return () => clearTimeout(t);
  }, [loadProducts, searchQuery]);

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader title="Shop" />

        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); loadProducts(); }} tintColor={isDark ? '#8b5cf6' : '#0f172a'} />
          }
        >
          <View style={styles.searchBarContainer}>
            <View style={[styles.searchBox, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}>
              <Search size={18} color={colors.iconMuted} />
              <TextInput
                ref={searchInputRef}
                placeholder="Search products..."
                placeholderTextColor={colors.placeholder}
                style={[styles.searchInput, { color: colors.text }]}
                value={searchQuery}
                onChangeText={setSearchQuery}
              />
              {searchQuery.length > 0 && (
                <TouchableOpacity onPress={() => setSearchQuery('')} style={{ padding: 4 }}>
                  <X size={16} color={colors.iconMuted} />
                </TouchableOpacity>
              )}
            </View>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.catScroll}>
            {categories.map((cat) => {
              const active = selectedCategory === cat;
              return (
                <TouchableOpacity
                  key={cat}
                  onPress={() => setSelectedCategory(cat)}
                  style={[
                    styles.catPill,
                    { backgroundColor: colors.card, borderColor: colors.border },
                    active && { backgroundColor: isDark ? '#8b5cf6' : '#0f172a', borderColor: isDark ? '#8b5cf6' : '#0f172a' },
                  ]}
                >
                  <Text style={[styles.catLabel, { color: colors.textSecondary }, active && { color: '#fff' }]}>{categoryLabel(cat)}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {loading ? (
            <ActivityIndicator size="large" color={isDark ? '#8b5cf6' : '#0f172a'} style={{ marginTop: 50 }} />
          ) : error ? (
            <EmptyState title="Shop unavailable" message={error} onAction={loadProducts} />
          ) : products.length === 0 ? (
            <EmptyState
              title="No products found"
              message="Try another search or category."
              actionLabel="Clear filters"
              onAction={() => {
                setSearchQuery('');
                setSelectedCategory('All');
              }}
            />
          ) : (
            <View style={styles.productGrid}>
              {products.map((item) => (
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
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  scrollContent: { paddingBottom: 96 },
  searchBarContainer: { paddingHorizontal: 20, paddingVertical: 8 },
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
  catScroll: { paddingHorizontal: 20, gap: 8, marginBottom: 16, marginTop: 8 },
  catPill: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 14,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  catPillActive: { backgroundColor: '#8b5cf6', borderColor: '#8b5cf6' },
  catLabel: { color: '#cbd5e1', fontSize: 12, fontWeight: '700' },
  catLabelActive: { color: '#fff' },
  productGrid: { flexDirection: 'row', flexWrap: 'wrap', paddingHorizontal: 16, justifyContent: 'space-between', alignItems: 'flex-start' },
});
