import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  Image,
  TouchableOpacity,
  Pressable,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Trash2, Plus, Minus, ShoppingBag, ArrowRight, Tag } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import LuxeHeader from '@/components/LuxeHeader';
import { formatPrice } from '@/lib/api';
import { useAppTheme } from '@/context/ThemeContext';

export default function CartScreen() {
  const router = useRouter();
  const { colors, isDark } = useAppTheme();
  const { items, cartCount, removeFromCart, updateQty } = useCart();

  const subtotal = items.reduce((sum, item) => sum + item.price * item.qty, 0);
  const shipping = 0;
  const total = subtotal + shipping;

  if (items.length === 0) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Bag" />
          <View style={styles.emptyContainer}>
            <View style={styles.emptyIconBox}>
              <ShoppingBag size={36} color="#c4b5fd" />
            </View>
            <Text style={[styles.emptyTitle, { color: colors.text }]}>Your bag is empty</Text>
            <Text style={[styles.emptySub, { color: colors.muted }]}>Add something you like and it will show up here.</Text>
            <Pressable style={styles.continueBtn} onPress={() => router.push('/(tabs)/shop')}>
              <LinearGradient
                colors={colors.cta}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 0 }}
                style={styles.continueGradient}
                pointerEvents="none"
              >
                <Text style={styles.continueText}>Continue shopping</Text>
              </LinearGradient>
            </Pressable>
          </View>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title={`Bag (${cartCount})`} />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {items.map((item) => {
            const lineTotal = item.price * item.qty;
            return (
              <View key={item.id} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Pressable onPress={() => router.push(`/product/${item.product_id || item.id}`)}>
                  {item.image_url ? (
                    <Image source={{ uri: item.image_url }} style={[styles.itemImage, { backgroundColor: colors.productImageBg }]} resizeMode="contain" />
                  ) : (
                    <View style={[styles.itemImage, styles.imageFallback, { backgroundColor: colors.productImageBg }]}>
                      <ShoppingBag size={22} color={colors.muted} />
                    </View>
                  )}
                </Pressable>

                <View style={styles.itemDetails}>
                  <View style={styles.itemTop}>
                    <Pressable
                      style={{ flex: 1, paddingRight: 8 }}
                      onPress={() => router.push(`/product/${item.product_id || item.id}`)}
                    >
                      <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={2}>
                        {item.name || 'Product'}
                      </Text>
                    </Pressable>
                    <TouchableOpacity
                      style={styles.deleteBtn}
                      onPress={() => removeFromCart(item.id)}
                      hitSlop={8}
                    >
                      <Trash2 size={16} color="#f87171" />
                    </TouchableOpacity>
                  </View>

                  <Text style={styles.unitPrice}>{formatPrice(item.price)} each</Text>

                  <View style={styles.itemBottom}>
                    <View style={[styles.qtyBox, { backgroundColor: colors.input, borderColor: colors.border }]}>
                      <TouchableOpacity
                        style={[styles.qtyBtn, { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : colors.cardMuted }]}
                        onPress={() => item.qty > 1 && updateQty(item.id, item.qty - 1)}
                      >
                        <Minus size={14} color={colors.icon} />
                      </TouchableOpacity>
                      <Text style={[styles.qtyText, { color: colors.text }]}>{item.qty}</Text>
                      <TouchableOpacity
                        style={[styles.qtyBtn, { backgroundColor: isDark ? 'rgba(255,255,255,0.08)' : colors.cardMuted }]}
                        onPress={() => updateQty(item.id, item.qty + 1)}
                      >
                        <Plus size={14} color={colors.icon} />
                      </TouchableOpacity>
                    </View>
                    <Text style={[styles.lineTotal, { color: colors.text }]}>{formatPrice(lineTotal)}</Text>
                  </View>
                </View>
              </View>
            );
          })}

          <View style={[styles.summaryCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <Text style={[styles.summaryTitle, { color: colors.text }]}>Order summary</Text>
            <View style={styles.summaryRow}>
              <Text style={[styles.summaryLabel, { color: colors.muted }]}>Subtotal ({cartCount} items)</Text>
              <Text style={[styles.summaryValue, { color: colors.text }]}>{formatPrice(subtotal)}</Text>
            </View>
            <View style={styles.summaryRow}>
              <View style={styles.shipLabel}>
                <Tag size={13} color="#34d399" />
                <Text style={[styles.summaryLabel, { color: colors.muted }]}>Delivery</Text>
              </View>
              <Text style={styles.freeText}>{shipping === 0 ? 'FREE' : formatPrice(shipping)}</Text>
            </View>
            <View style={[styles.summaryDivider, { backgroundColor: colors.hairline }]} />
            <View style={styles.summaryRow}>
              <Text style={[styles.totalLabel, { color: colors.muted }]}>Total</Text>
              <Text style={[styles.totalValue, { color: colors.text }]}>{formatPrice(total)}</Text>
            </View>
          </View>
        </ScrollView>

        <View style={[styles.bottomBar, { backgroundColor: colors.tabBar, borderTopColor: colors.tabBorder }]}>
          <View>
            <Text style={[styles.bottomHint, { color: colors.muted }]}>Payable</Text>
            <Text style={[styles.bottomTotal, { color: colors.text }]}>{formatPrice(total)}</Text>
          </View>
          <Pressable
            style={styles.checkoutBtn}
            onPress={() => router.push('/checkout')}
          >
            <LinearGradient
              colors={colors.cta}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.checkoutGradient}
              pointerEvents="none"
            >
              <Text style={styles.checkoutText}>Checkout</Text>
              <ArrowRight size={16} color="#fff" />
            </LinearGradient>
          </Pressable>
        </View>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  safeArea: { flex: 1 },
  scrollContent: { padding: 16, paddingBottom: 120 },

  card: {
    flexDirection: 'row',
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    padding: 12,
    marginBottom: 12,
    gap: 12,
  },
  itemImage: {
    width: 92,
    height: 118,
    borderRadius: 12,
    backgroundColor: '#16161f',
  },
  imageFallback: { alignItems: 'center', justifyContent: 'center' },
  itemDetails: { flex: 1, justifyContent: 'space-between' },
  itemTop: { flexDirection: 'row', alignItems: 'flex-start' },
  itemName: { color: '#fff', fontSize: 15, fontWeight: '700', lineHeight: 20 },
  deleteBtn: {
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: 'rgba(239,68,68,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  unitPrice: { color: '#94a3b8', fontSize: 12, marginTop: 6 },
  itemBottom: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 10,
  },
  qtyBox: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    padding: 3,
  },
  qtyBtn: {
    width: 30,
    height: 30,
    borderRadius: 9,
    backgroundColor: 'rgba(255,255,255,0.08)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  qtyText: { color: '#fff', minWidth: 28, textAlign: 'center', fontWeight: '800', fontSize: 14 },
  lineTotal: { color: '#fff', fontSize: 16, fontWeight: '800' },

  summaryCard: {
    marginTop: 8,
    padding: 16,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
  },
  summaryTitle: {
    color: '#fff',
    fontSize: 16,
    fontWeight: '800',
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
    marginBottom: 14,
  },
  summaryRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  summaryLabel: { color: '#94a3b8', fontSize: 13 },
  summaryValue: { color: '#fff', fontSize: 14, fontWeight: '700' },
  shipLabel: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  freeText: { color: '#34d399', fontSize: 13, fontWeight: '800' },
  summaryDivider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: 'rgba(255,255,255,0.12)',
    marginVertical: 6,
  },
  totalLabel: { color: '#fff', fontSize: 15, fontWeight: '800' },
  totalValue: { color: '#fff', fontSize: 20, fontWeight: '800' },

  bottomBar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 12,
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: Platform.OS === 'ios' ? 24 : 14,
    backgroundColor: 'rgba(8,8,14,0.96)',
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.08)',
  },
  bottomHint: { color: '#94a3b8', fontSize: 11, fontWeight: '600' },
  bottomTotal: { color: '#fff', fontSize: 20, fontWeight: '800', marginTop: 2 },
  checkoutBtn: { flex: 1, maxWidth: 200, borderRadius: 14, overflow: 'hidden' },
  checkoutGradient: {
    height: 50,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  checkoutText: { color: '#fff', fontSize: 14, fontWeight: '800' },

  emptyContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: 32 },
  emptyIconBox: {
    width: 88,
    height: 88,
    borderRadius: 28,
    backgroundColor: 'rgba(139,92,246,0.15)',
    borderWidth: 1,
    borderColor: 'rgba(139,92,246,0.3)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  emptyTitle: { color: '#fff', fontSize: 22, fontWeight: '800', marginBottom: 8 },
  emptySub: { color: '#94a3b8', fontSize: 14, textAlign: 'center', lineHeight: 21, marginBottom: 24 },
  continueBtn: { width: '100%', maxWidth: 280, borderRadius: 14, overflow: 'hidden' },
  continueGradient: { height: 50, alignItems: 'center', justifyContent: 'center' },
  continueText: { color: '#fff', fontWeight: '800', fontSize: 14 },
});
