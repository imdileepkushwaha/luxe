import React, { useEffect, useRef, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  Image,
  TouchableOpacity,
  FlatList,
  Dimensions,
  NativeSyntheticEvent,
  NativeScrollEvent,
  Platform,
} from 'react-native';
import { ArrowRight, Sparkles } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useRouter } from 'expo-router';
import { formatPrice, type Product } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

const { width } = Dimensions.get('window');
const SIDE = 16;
const HERO_HEIGHT = 228;
const PAGE_WIDTH = width;

type Props = {
  slides: Product[];
};

export default function HeroBanner({ slides }: Props) {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const listRef = useRef<FlatList<Product>>(null);
  const [active, setActive] = useState(0);
  const activeRef = useRef(0);

  useEffect(() => {
    activeRef.current = active;
  }, [active]);

  useEffect(() => {
    if (slides.length < 2) return;
    const timer = setInterval(() => {
      const next = (activeRef.current + 1) % slides.length;
      listRef.current?.scrollToOffset({ offset: next * PAGE_WIDTH, animated: true });
      setActive(next);
    }, 4500);
    return () => clearInterval(timer);
  }, [slides.length]);

  const onScroll = (e: NativeSyntheticEvent<NativeScrollEvent>) => {
    const i = Math.round(e.nativeEvent.contentOffset.x / PAGE_WIDTH);
    if (i !== active && i >= 0 && i < slides.length) setActive(i);
  };

  if (slides.length === 0) {
    return (
      <View style={styles.fallback}>
        <LinearGradient colors={['#2e1065', '#0a0a10']} style={StyleSheet.absoluteFill} />
        <Text style={styles.kicker}>LUXE</Text>
        <Text style={styles.fallbackTitle}>New season edits</Text>
      </View>
    );
  }

  return (
    <View style={styles.wrap}>
      <FlatList
        ref={listRef}
        data={slides}
        keyExtractor={(item) => String(item.id)}
        horizontal
        pagingEnabled
        snapToInterval={PAGE_WIDTH}
        snapToAlignment="start"
        disableIntervalMomentum
        style={styles.list}
        showsHorizontalScrollIndicator={false}
        decelerationRate="fast"
        bounces={false}
        overScrollMode="never"
        onScroll={onScroll}
        scrollEventThrottle={16}
        getItemLayout={(_, index) => ({
          length: PAGE_WIDTH,
          offset: PAGE_WIDTH * index,
          index,
        })}
        renderItem={({ item, index }) => {
          const discount =
            item.original_price > item.price && item.original_price > 0
              ? Math.round(((item.original_price - item.price) / item.original_price) * 100)
              : 0;

          return (
            <View style={styles.page}>
            <TouchableOpacity
              style={[
                styles.card,
                {
                  backgroundColor: isDark ? '#120c1c' : '#ffffff',
                  borderColor: isDark ? 'rgba(196,181,253,0.18)' : colors.border,
                },
              ]}
              activeOpacity={0.95}
              onPress={() => router.push(`/product/${item.id}`)}
            >
              <LinearGradient
                colors={isDark ? ['#24143d', '#120c1c', '#0a0810'] : ['#ffffff', '#f8fafc', '#f1f5f9']}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 1 }}
                style={styles.copy}
              >
                <View
                  style={[
                    styles.chip,
                    !isDark && { backgroundColor: 'rgba(239,68,68,0.1)', borderColor: 'rgba(239,68,68,0.25)' },
                  ]}
                >
                  <Sparkles size={10} color={isDark ? '#f5d0fe' : '#ef4444'} />
                  <Text style={[styles.chipText, { color: isDark ? '#f5d0fe' : '#ef4444' }]}>
                    {(item.badge || 'Featured').toUpperCase()}
                  </Text>
                </View>
                <Text style={[styles.brand, { color: isDark ? 'rgba(196,181,253,0.85)' : '#64748b' }]} numberOfLines={1}>
                  {(item.brand || 'LUXE').toUpperCase()}
                </Text>
                <Text style={[styles.title, { color: colors.text }]} numberOfLines={2}>
                  {item.name}
                </Text>
                <View style={styles.priceRow}>
                  <Text style={[styles.price, { color: colors.text }]}>{formatPrice(item.price)}</Text>
                  {discount > 0 && (
                    <Text style={styles.oldPrice}>{formatPrice(item.original_price)}</Text>
                  )}
                </View>
                <View style={styles.cta}>
                  <LinearGradient
                    colors={isDark ? ['#8b5cf6', '#db2777'] : ['#0f172a', '#1e293b']}
                    start={{ x: 0, y: 0 }}
                    end={{ x: 1, y: 0 }}
                    style={styles.ctaGrad}
                  >
                    <Text style={styles.ctaText}>Shop now</Text>
                    <ArrowRight size={13} color="#fff" />
                  </LinearGradient>
                </View>
              </LinearGradient>

              <View style={[styles.visual, { backgroundColor: isDark ? '#1a1428' : '#f1f5f9' }]}>
                {item.image_url ? (
                  <Image source={{ uri: item.image_url }} style={styles.image} resizeMode="cover" />
                ) : (
                  <View style={[styles.image, styles.imageEmpty]} />
                )}
                <LinearGradient
                  colors={['rgba(18,12,28,0.2)', 'transparent']}
                  start={{ x: 0, y: 0.5 }}
                  end={{ x: 0.45, y: 0.5 }}
                  style={StyleSheet.absoluteFill}
                />
                {discount > 0 && (
                  <View style={styles.saveBadge}>
                    <Text style={styles.saveText}>{discount}% OFF</Text>
                  </View>
                )}
                <View style={styles.indexBadge}>
                  <Text style={styles.indexText}>
                    {String(index + 1).padStart(2, '0')}
                    <Text style={styles.indexMuted}> / {String(slides.length).padStart(2, '0')}</Text>
                  </Text>
                </View>
              </View>
            </TouchableOpacity>
            </View>
          );
        }}
      />

      {slides.length > 1 && (
        <View style={styles.dots}>
          {slides.map((_, i) => (
            <View key={i} style={[styles.dot, { backgroundColor: isDark ? 'rgba(255,255,255,0.22)' : '#e2e8f0' }, i === active && { backgroundColor: isDark ? '#c4b5fd' : '#0f172a', width: 20 }]} />
          ))}
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 4, overflow: 'hidden', width: PAGE_WIDTH },
  list: { width: PAGE_WIDTH },
  page: {
    width: PAGE_WIDTH,
    paddingHorizontal: SIDE,
  },
  card: {
    width: '100%',
    height: HERO_HEIGHT,
    borderRadius: 24,
    overflow: 'hidden',
    flexDirection: 'row',
    backgroundColor: '#120c1c',
    borderWidth: 1,
    borderColor: 'rgba(196,181,253,0.18)',
    ...Platform.select({
      ios: {
        shadowColor: '#8b5cf6',
        shadowOpacity: 0.22,
        shadowRadius: 18,
        shadowOffset: { width: 0, height: 8 },
      },
      android: { elevation: 8 },
      web: { boxShadow: '0 12px 36px rgba(139,92,246,0.22)' } as object,
    }),
  },
  copy: {
    width: '52%',
    paddingHorizontal: 16,
    paddingVertical: 18,
    justifyContent: 'center',
  },
  chip: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 5,
    backgroundColor: 'rgba(244,114,182,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(244,114,182,0.35)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 999,
    marginBottom: 10,
  },
  chipText: { color: '#f5d0fe', fontSize: 9, fontWeight: '800', letterSpacing: 1 },
  brand: {
    color: 'rgba(196,181,253,0.85)',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    marginBottom: 6,
  },
  title: {
    color: '#fff',
    fontSize: 17,
    fontWeight: '800',
    lineHeight: 22,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
    marginBottom: 10,
  },
  priceRow: { flexDirection: 'row', alignItems: 'baseline', gap: 8, marginBottom: 14 },
  price: { color: '#fff', fontSize: 18, fontWeight: '800' },
  oldPrice: { color: 'rgba(148,163,184,0.85)', fontSize: 12, textDecorationLine: 'line-through' },
  cta: { alignSelf: 'flex-start', borderRadius: 999, overflow: 'hidden' },
  ctaGrad: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 14,
    paddingVertical: 9,
  },
  ctaText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  visual: {
    width: '48%',
    height: '100%',
    position: 'relative',
    backgroundColor: '#1a1428',
  },
  image: { width: '100%', height: '100%' },
  imageEmpty: { backgroundColor: '#1a1428' },
  saveBadge: {
    position: 'absolute',
    top: 12,
    right: 12,
    backgroundColor: '#10b981',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  saveText: { color: '#fff', fontSize: 9, fontWeight: '800' },
  indexBadge: {
    position: 'absolute',
    bottom: 12,
    right: 12,
    backgroundColor: 'rgba(0,0,0,0.45)',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 8,
  },
  indexText: { color: '#fff', fontSize: 10, fontWeight: '800' },
  indexMuted: { color: 'rgba(255,255,255,0.5)', fontWeight: '600' },
  dots: { flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 6, marginTop: 12 },
  dot: { width: 6, height: 6, borderRadius: 3, backgroundColor: 'rgba(255,255,255,0.22)' },
  dotOn: { width: 20, height: 6, borderRadius: 3, backgroundColor: '#c4b5fd' },
  fallback: {
    marginHorizontal: SIDE,
    height: HERO_HEIGHT,
    borderRadius: 24,
    overflow: 'hidden',
    justifyContent: 'flex-end',
    padding: 22,
    marginBottom: 4,
  },
  kicker: { color: '#c4b5fd', fontSize: 11, fontWeight: '800', letterSpacing: 2, marginBottom: 8 },
  fallbackTitle: {
    color: '#fff',
    fontSize: 24,
    fontWeight: '800',
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
});
