import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  Image,
  TouchableOpacity,
  Platform,
} from 'react-native';
import { ChevronRight, Percent, Zap, ShoppingBag } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { formatPrice, type Product } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

type Props = {
  products: Product[];
};

export default function OfferSection({ products }: Props) {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const deals = products.filter((p) => (p.discount_percent || 0) > 0 || p.original_price > p.price);
  if (deals.length === 0) return null;

  const maxOff = Math.max(...deals.map((p) => p.discount_percent || 0));

  return (
    <View style={styles.section}>
      <View style={styles.sectionHeader}>
        <View>
          <Text style={[styles.sectionTitle, { color: colors.text }]}>Offers</Text>
          <Text style={[styles.sectionSubtitle, { color: colors.muted }]}>Limited-time deals from the store</Text>
        </View>
        <TouchableOpacity style={styles.viewAllBtn} onPress={() => router.push('/(tabs)/shop')}>
          <Text style={[styles.viewAllText, { color: isDark ? '#a78bfa' : '#ef4444' }]}>View all</Text>
          <ChevronRight size={14} color={isDark ? '#a78bfa' : '#ef4444'} />
        </TouchableOpacity>
      </View>

      <TouchableOpacity
        style={styles.promoWrap}
        activeOpacity={0.92}
        onPress={() => router.push('/(tabs)/shop')}
      >
        <LinearGradient
          colors={isDark ? ['#7c3aed', '#db2777'] : ['#ef4444', '#c93532']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={styles.promo}
        >
          <View style={styles.promoIcon}>
            <Zap size={18} color="#fff" fill="#fff" />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.promoKicker}>FLASH DEALS</Text>
            <Text style={styles.promoTitle}>
              {maxOff > 0 ? `Up to ${maxOff}% off` : 'Special prices today'}
            </Text>
          </View>
          <Percent size={28} color="rgba(255,255,255,0.28)" />
        </LinearGradient>
      </TouchableOpacity>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.row}>
        {deals.map((item) => {
          const off =
            item.discount_percent ||
            (item.original_price > item.price && item.original_price > 0
              ? Math.round(((item.original_price - item.price) / item.original_price) * 100)
              : 0);
          return (
            <TouchableOpacity
              key={item.id}
              style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}
              activeOpacity={0.9}
              onPress={() => router.push(`/product/${item.id}`)}
            >
              <View style={[styles.imageBox, { backgroundColor: colors.productImageBg }]}>
                {item.image_url ? (
                  <Image source={{ uri: item.image_url }} style={styles.image} />
                ) : (
                  <View style={[styles.image, styles.imageEmpty]}>
                    <ShoppingBag size={22} color={colors.muted} />
                  </View>
                )}
                {off > 0 && (
                  <View style={styles.offBadge}>
                    <Text style={styles.offText}>{off}% OFF</Text>
                  </View>
                )}
              </View>
              <Text style={[styles.brand, { color: isDark ? '#f9a8d4' : '#ef4444' }]} numberOfLines={1}>
                {(item.brand || 'LUXE').toUpperCase()}
              </Text>
              <Text style={[styles.name, { color: colors.text }]} numberOfLines={2}>
                {item.name}
              </Text>
              <View style={styles.priceRow}>
                <Text style={[styles.price, { color: colors.text }]}>{formatPrice(item.price)}</Text>
                {off > 0 && <Text style={styles.old}>{formatPrice(item.original_price)}</Text>}
              </View>
              {!!item.offer_flash_text && (
                <Text style={[styles.flash, { color: colors.gold }]} numberOfLines={1}>
                  {item.offer_flash_text}
                </Text>
              )}
            </TouchableOpacity>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  section: { marginTop: 8 },
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
  promoWrap: { marginHorizontal: 16, marginBottom: 14, borderRadius: 18, overflow: 'hidden' },
  promo: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 16,
    paddingHorizontal: 16,
  },
  promoIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: 'rgba(255,255,255,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  promoKicker: { color: 'rgba(255,255,255,0.8)', fontSize: 10, fontWeight: '800', letterSpacing: 1.4 },
  promoTitle: { color: '#fff', fontSize: 18, fontWeight: '800', marginTop: 2 },
  row: { paddingHorizontal: 16, gap: 12 },
  card: {
    width: 148,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.08)',
    padding: 8,
    paddingBottom: 12,
  },
  imageBox: {
    height: 132,
    borderRadius: 12,
    overflow: 'hidden',
    backgroundColor: '#16161f',
    marginBottom: 8,
  },
  image: { width: '100%', height: '100%' },
  imageEmpty: { alignItems: 'center', justifyContent: 'center' },
  offBadge: {
    position: 'absolute',
    top: 8,
    left: 8,
    backgroundColor: '#ef4444',
    paddingHorizontal: 7,
    paddingVertical: 3,
    borderRadius: 7,
  },
  offText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  brand: { color: '#f9a8d4', fontSize: 9, fontWeight: '800', letterSpacing: 0.8, marginBottom: 3 },
  name: { color: '#fff', fontSize: 12, fontWeight: '700', lineHeight: 16, minHeight: 32 },
  priceRow: { flexDirection: 'row', alignItems: 'baseline', gap: 6, marginTop: 6 },
  price: { color: '#fff', fontSize: 14, fontWeight: '800' },
  old: { color: '#64748b', fontSize: 11, textDecorationLine: 'line-through' },
  flash: { color: '#fbbf24', fontSize: 10, fontWeight: '600', marginTop: 6 },
});
