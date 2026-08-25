import React from 'react';
import { StyleSheet, Text, View, Image, TouchableOpacity } from 'react-native';
import { Heart, Star, ShoppingBag } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import type { Product } from '@/lib/api';
import { formatPrice } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

type Props = {
  product: Product;
  onPress: () => void;
  onWishlist?: () => void;
  wished?: boolean;
  onAdd?: () => void;
};

export default function ProductCard({ product, onPress, onWishlist, wished, onAdd }: Props) {
  const { colors, isDark } = useAppTheme();
  const discount =
    product.original_price > product.price && product.original_price > 0
      ? Math.round(((product.original_price - product.price) / product.original_price) * 100)
      : 0;

  return (
    <TouchableOpacity style={styles.wrap} activeOpacity={0.9} onPress={onPress}>
      <View
        style={[
          styles.card,
          {
            backgroundColor: colors.card,
            borderColor: colors.border,
            shadowColor: colors.shadowColor,
            shadowOpacity: isDark ? 0 : 0.08,
            shadowRadius: isDark ? 0 : 16,
            shadowOffset: { width: 0, height: 8 },
            elevation: isDark ? 0 : 3,
          },
        ]}
      >
        <View style={[styles.imageBox, { backgroundColor: colors.productImageBg }]}>
          {product.image_url ? (
            <Image source={{ uri: product.image_url }} style={styles.image} resizeMode="contain" />
          ) : (
            <View style={[styles.image, styles.imageFallback]}>
              <ShoppingBag size={28} color={colors.muted} />
            </View>
          )}
          <LinearGradient colors={['transparent', isDark ? 'rgba(0,0,0,0.55)' : 'rgba(15,23,42,0.12)']} style={styles.imageFade} />
          {!!product.badge && (
            <View style={[styles.badge, { backgroundColor: isDark ? '#8b5cf6' : '#0f172a' }]}>
              <Text style={styles.badgeText}>{product.badge.toUpperCase()}</Text>
            </View>
          )}
          {discount > 0 && (
            <View style={styles.saveTag}>
              <Text style={styles.saveText}>{discount}% OFF</Text>
            </View>
          )}
          {onWishlist && (
            <TouchableOpacity style={styles.wishBtn} onPress={onWishlist} hitSlop={8}>
              <Heart size={14} color={wished ? '#ef4444' : '#fff'} fill={wished ? '#ef4444' : 'transparent'} />
            </TouchableOpacity>
          )}
        </View>
        <View style={styles.info}>
          <Text style={[styles.brand, { color: isDark ? '#a78bfa' : '#ef4444' }]} numberOfLines={1}>
            {(product.brand || 'LUXE').toUpperCase()}
          </Text>
          <Text style={[styles.name, { color: colors.text }]} numberOfLines={2}>
            {product.name}
          </Text>
          <View style={styles.footer}>
            <View style={{ flex: 1 }}>
              <Text style={[styles.price, { color: colors.text }]}>{formatPrice(product.price)}</Text>
              {discount > 0 && (
                <Text style={styles.oldPrice}>{formatPrice(product.original_price)}</Text>
              )}
            </View>
            <View style={styles.rating}>
              <Star size={10} color="#f59e0b" fill="#f59e0b" />
              <Text style={[styles.ratingText, { color: colors.muted }]}>
                {product.rating ? product.rating.toFixed(1) : '—'}
              </Text>
            </View>
            {onAdd && (
              <TouchableOpacity
                style={[
                  styles.addBtn,
                  {
                    backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a',
                    borderColor: isDark ? 'rgba(139,92,246,0.55)' : '#0f172a',
                  },
                ]}
                onPress={onAdd}
                hitSlop={6}
              >
                <ShoppingBag size={14} color="#fff" />
              </TouchableOpacity>
            )}
          </View>
        </View>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  wrap: { width: '48%', marginBottom: 16 },
  card: {
    backgroundColor: 'rgba(255,255,255,0.04)',
    borderRadius: 18,
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
  },
  imageBox: {
    width: '100%',
    aspectRatio: 3 / 3.8,
    backgroundColor: '#14141f',
    position: 'relative',
    overflow: 'hidden',
  },
  image: { width: '100%', height: '100%' },
  imageFallback: { alignItems: 'center', justifyContent: 'center' },
  imageFade: { position: 'absolute', left: 0, right: 0, bottom: 0, height: 48 },
  badge: {
    position: 'absolute',
    top: 10,
    left: 10,
    backgroundColor: '#8b5cf6',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  badgeText: { color: '#fff', fontSize: 8, fontWeight: '800', letterSpacing: 0.6 },
  saveTag: {
    position: 'absolute',
    bottom: 10,
    left: 10,
    backgroundColor: 'rgba(16,185,129,0.9)',
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 7,
  },
  saveText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  wishBtn: {
    position: 'absolute',
    top: 8,
    right: 8,
    width: 30,
    height: 30,
    borderRadius: 15,
    backgroundColor: 'rgba(0,0,0,0.45)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  info: { padding: 11, paddingBottom: 12 },
  brand: { color: '#a78bfa', fontSize: 9, fontWeight: '800', letterSpacing: 1.1, marginBottom: 4 },
  name: { color: '#fff', fontSize: 13, fontWeight: '700', lineHeight: 17, minHeight: 34 },
  footer: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 8 },
  price: { color: '#fff', fontSize: 15, fontWeight: '800' },
  oldPrice: { color: '#64748b', fontSize: 11, textDecorationLine: 'line-through', marginTop: 1 },
  rating: { flexDirection: 'row', alignItems: 'center', gap: 3 },
  ratingText: { color: '#94a3b8', fontSize: 11, fontWeight: '700' },
  addBtn: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: 'rgba(139,92,246,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(139,92,246,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
  },
});
