import React from 'react';
import { StyleSheet, Text, View, ScrollView, Image, TouchableOpacity, SafeAreaView, Dimensions, Platform } from 'react-native';
import { useRouter } from 'expo-router';
import { ChevronLeft, Trash2, Plus, Minus, ShoppingBag, ArrowRight } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import LuxeHeader from '@/components/LuxeHeader';

const { width } = Dimensions.get('window');

export default function CartScreen() {
  const router = useRouter();
  const { items, cartCount, removeFromCart, updateQty } = useCart();

  const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const shipping = 0; // Free for elite members
  const total = subtotal + shipping;

  if (items.length === 0) {
    return (
      <View style={styles.container}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea}>
          <LuxeHeader showBack={true} />
          
          <View style={styles.emptyContainer}>
            <View style={styles.emptyIconBox}>
              <ShoppingBag size={60} color="#8b5cf6" />
              <LinearGradient colors={['rgba(139, 92, 246, 0.2)', 'transparent']} style={StyleSheet.absoluteFill} />
            </View>
            <Text style={styles.emptyTitle}>Your bag is empty</Text>
            <Text style={styles.emptySub}>Start exploring our elite curation and find your next masterpiece.</Text>
            <TouchableOpacity 
              style={styles.continueBtn}
              onPress={() => router.push('/(tabs)/shop')}
            >
              <LinearGradient colors={['#8b5cf6', '#ec4899']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.continueGradient}>
                <Text style={styles.continueText}>CONTINUE SHOPPING</Text>
              </LinearGradient>
            </TouchableOpacity>
          </View>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea}>
          <LuxeHeader showBack={true} />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {items.map((item) => (
            <GlassCard key={item.id} intensity={15} borderRadius={25} style={styles.cartItem}>
              <View style={styles.itemImageContainer}>
                <Image 
                  source={{ uri: item.image_url || `https://picsum.photos/seed/${item.id}/200/300` }} 
                  style={styles.itemImage} 
                  resizeMode="cover"
                />
              </View>
              
              <View style={styles.itemDetails}>
                <View style={styles.itemHeader}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.itemBrand}>LUXE ELITE</Text>
                    <Text style={styles.itemName} numberOfLines={1}>{item.name.toUpperCase()}</Text>
                  </View>
                  <TouchableOpacity onPress={() => removeFromCart(item.id)}>
                    <Trash2 size={18} color="#ef4444" />
                  </TouchableOpacity>
                </View>

                <View style={styles.itemFooter}>
                  <Text style={styles.itemPrice}>₹{item.price.toLocaleString()}</Text>
                  <View style={styles.qtyContainer}>
                    <TouchableOpacity 
                      style={styles.qtyBtn} 
                      onPress={() => item.qty > 1 && updateQty(item.id, item.qty - 1)}
                    >
                      <Minus size={14} color="#fff" />
                    </TouchableOpacity>
                    <Text style={styles.qtyText}>{item.qty}</Text>
                    <TouchableOpacity 
                      style={styles.qtyBtn}
                      onPress={() => updateQty(item.id, item.qty + 1)}
                    >
                      <Plus size={14} color="#fff" />
                    </TouchableOpacity>
                  </View>
                </View>
              </View>
            </GlassCard>
          ))}
          
          <View style={{ height: 200 }} />
        </ScrollView>

        {/* Bottom Checkout Section */}
        <View style={styles.bottomBar}>
          <BlurView intensity={30} tint="dark" style={styles.bottomBlur}>
            <View style={styles.summaryRow}>
              <View>
                <Text style={styles.totalLabel}>TOTAL AMOUNT</Text>
                <Text style={styles.totalPrice}>₹{total.toLocaleString()}</Text>
              </View>
              <TouchableOpacity 
                activeOpacity={0.8}
                style={styles.checkoutBtn}
                onPress={() => router.push('/checkout')}
              >
                <LinearGradient 
                  colors={['#8b5cf6', '#ec4899']} 
                  start={{x:0, y:0}} end={{x:1, y:0}}
                  style={styles.checkoutGradient}
                >
                  <Text style={styles.checkoutText}>CHECKOUT</Text>
                  <ArrowRight size={18} color="#fff" />
                </LinearGradient>
              </TouchableOpacity>
            </View>
          </BlurView>
        </View>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', paddingHorizontal: 20, height: 60 },
  headerTitle: { color: '#fff', fontSize: 16, fontWeight: '800', letterSpacing: 2 },
  headerIconBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.05)', justifyContent: 'center', alignItems: 'center' },
  
  scrollContent: { padding: 20 },
  cartItem: { flexDirection: 'row', padding: 12, marginBottom: 15 },
  itemImageContainer: { width: 100, height: 110, borderRadius: 18, overflow: 'hidden', backgroundColor: '#1a1a1a' },
  itemImage: { width: '100%', height: '100%' },
  itemDetails: { flex: 1, marginLeft: 15, justifyContent: 'space-between' },
  itemHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  itemBrand: { color: '#8b5cf6', fontSize: 9, fontWeight: '900', letterSpacing: 1.5, marginBottom: 4 },
  itemName: { color: '#fff', fontSize: 15, fontWeight: '700' },
  itemFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  itemPrice: { color: '#fff', fontSize: 18, fontWeight: '900' },
  qtyContainer: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.05)', borderRadius: 12, padding: 4 },
  qtyBtn: { width: 28, height: 28, borderRadius: 8, backgroundColor: 'rgba(255,255,255,0.1)', justifyContent: 'center', alignItems: 'center' },
  qtyText: { color: '#fff', paddingHorizontal: 12, fontWeight: '800', fontSize: 14 },
  
  bottomBar: { position: 'absolute', bottom: 0, width: '100%', borderTopLeftRadius: 35, borderTopRightRadius: 35, overflow: 'hidden' },
  bottomBlur: { padding: 25, paddingBottom: Platform.OS === 'ios' ? 40 : 25 },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  totalLabel: { color: '#94a3b8', fontSize: 10, fontWeight: '900', letterSpacing: 1, marginBottom: 5 },
  totalPrice: { color: '#fff', fontSize: 24, fontWeight: '900' },
  checkoutBtn: { height: 55, borderRadius: 22, overflow: 'hidden', width: '55%' },
  checkoutGradient: { flex: 1, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 10 },
  checkoutText: { color: '#fff', fontSize: 14, fontWeight: '900', letterSpacing: 1.5 },
  
  emptyContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyIconBox: { width: 120, height: 120, borderRadius: 60, justifyContent: 'center', alignItems: 'center', marginBottom: 30, overflow: 'hidden' },
  emptyTitle: { color: '#fff', fontSize: 24, fontWeight: '800', marginBottom: 10 },
  emptySub: { color: '#94a3b8', fontSize: 15, textAlign: 'center', lineHeight: 22, marginBottom: 35 },
  continueBtn: { width: '100%', height: 60, borderRadius: 25, overflow: 'hidden' },
  continueGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  continueText: { color: '#fff', fontWeight: '900', fontSize: 14, letterSpacing: 1.5 },
});
