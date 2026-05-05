import React, { useState } from 'react';
import { StyleSheet, Text, View, ScrollView, TouchableOpacity, SafeAreaView, Alert, ActivityIndicator, Platform } from 'react-native';
import { useRouter } from 'expo-router';
import { ChevronLeft, MapPin, CreditCard, ShieldCheck, CheckCircle2, Truck, Smartphone, Banknote } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import { useAuth } from '@/context/AuthContext';
import Config from '@/constants/Config';
import LuxeHeader from '@/components/LuxeHeader';

export default function CheckoutScreen() {
  const router = useRouter();
  const { items, cartCount, fetchCart } = useCart();
  const { user } = useAuth();
  
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('UPI');

  const subtotal = items.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const shipping = 0;
  const total = subtotal + shipping;

  const handlePlaceOrder = async () => {
    if (!user) return;
    setLoading(true);
    try {
      const response = await fetch(`${Config.API_URL}/mobile_checkout.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          address: "Sector 15, Noida, UP 201301",
          payment_method: paymentMethod
        }),
      });
      const data = await response.json();
      if (data.ok) {
        setSuccess(true);
        fetchCart();
      } else {
        Alert.alert('Error', data.error || 'Failed to place order');
      }
    } catch (error) {
      Alert.alert('Error', 'Something went wrong');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <View style={styles.container}>
        <BackgroundScene />
        <View style={styles.successContent}>
          <View style={styles.successIconBox}>
            <CheckCircle2 size={60} color="#10b981" />
            <LinearGradient colors={['rgba(16, 185, 129, 0.2)', 'transparent']} style={StyleSheet.absoluteFill} />
          </View>
          <Text style={styles.successTitle}>ELITE ORDER PLACED</Text>
          <Text style={styles.successSub}>Thank you for choosing LUXE. Your premium curation is being prepared for delivery.</Text>
          <TouchableOpacity 
            style={styles.homeBtn}
            onPress={() => router.push('/(tabs)')}
          >
            <LinearGradient colors={['#8b5cf6', '#ec4899']} style={styles.homeBtnGradient}>
              <Text style={styles.homeBtnText}>CONTINUE SHOPPING</Text>
            </LinearGradient>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea}>
        <LuxeHeader showBack={true} />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {/* Progress Indicator */}
          <View style={styles.progressRow}>
            <View style={[styles.progressStep, { backgroundColor: '#8b5cf6' }]} />
            <View style={[styles.progressStep, { backgroundColor: '#8b5cf6' }]} />
            <View style={[styles.progressStep, { backgroundColor: 'rgba(255,255,255,0.1)' }]} />
          </View>

          {/* Shipping Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>DELIVERY ADDRESS</Text>
            <GlassCard intensity={20} borderRadius={25} style={styles.addressCard}>
              <View style={styles.addressHeader}>
                <View style={styles.addressIconBox}>
                  <MapPin size={20} color="#fff" />
                  <LinearGradient colors={['#8b5cf6', '#ec4899']} style={StyleSheet.absoluteFill} />
                </View>
                <View>
                  <Text style={styles.addressType}>HOME</Text>
                  <Text style={styles.addressName}>{user?.first_name} {user?.last_name}</Text>
                </View>
              </View>
              <Text style={styles.addressText}>Sector 15, Emerald Heights, Noida, UP 201301</Text>
              <TouchableOpacity style={styles.changeBtn}>
                <Text style={styles.changeBtnText}>CHANGE ADDRESS</Text>
              </TouchableOpacity>
            </GlassCard>
          </View>

          {/* Payment Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>PAYMENT METHOD</Text>
            <View style={styles.paymentGrid}>
              {[
                { id: 'UPI', icon: Smartphone, label: 'UPI' },
                { id: 'CARD', icon: CreditCard, label: 'Cards' },
                { id: 'COD', icon: Banknote, label: 'Cash' }
              ].map((method) => (
                <TouchableOpacity 
                  key={method.id}
                  onPress={() => setPaymentMethod(method.id)}
                  style={styles.paymentItem}
                >
                  <GlassCard 
                    intensity={paymentMethod === method.id ? 80 : 15} 
                    borderRadius={20} 
                    style={[styles.paymentCard, paymentMethod === method.id && { borderColor: '#8b5cf6', borderWidth: 1 }]}
                  >
                    <method.icon size={24} color={paymentMethod === method.id ? "#fff" : "#64748b"} />
                    <Text style={[styles.paymentLabel, paymentMethod === method.id && { color: '#fff' }]}>{method.label}</Text>
                  </GlassCard>
                </TouchableOpacity>
              ))}
            </View>
          </View>

          {/* Summary Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>ORDER SUMMARY</Text>
            <GlassCard intensity={15} borderRadius={25} style={styles.summaryCard}>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Subtotal ({cartCount} items)</Text>
                <Text style={styles.summaryValue}>₹{subtotal.toLocaleString()}</Text>
              </View>
              <View style={styles.summaryRow}>
                <Text style={styles.summaryLabel}>Elite Shipping</Text>
                <Text style={[styles.summaryValue, { color: '#10b981' }]}>FREE</Text>
              </View>
              <View style={styles.divider} />
              <View style={styles.summaryRow}>
                <Text style={styles.totalLabel}>TOTAL AMOUNT</Text>
                <Text style={styles.totalValue}>₹{total.toLocaleString()}</Text>
              </View>
            </GlassCard>
          </View>

          <View style={styles.safetyInfo}>
            <ShieldCheck size={16} color="#64748b" />
            <Text style={styles.safetyText}>Secured by LUXE 256-bit encryption</Text>
          </View>

          <View style={{ height: 120 }} />
        </ScrollView>

        {/* Floating Checkout Bar */}
        <View style={styles.bottomBar}>
          <BlurView intensity={30} tint="dark" style={styles.bottomBlur}>
            <TouchableOpacity 
              style={styles.placeOrderBtn}
              onPress={handlePlaceOrder}
              disabled={loading}
            >
              <LinearGradient 
                colors={['#8b5cf6', '#ec4899']} 
                start={{x:0, y:0}} end={{x:1, y:0}}
                style={styles.placeOrderGradient}
              >
                {loading ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.placeOrderText}>PLACE ELITE ORDER · ₹{total.toLocaleString()}</Text>
                )}
              </LinearGradient>
            </TouchableOpacity>
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
  headerTitle: { color: '#fff', fontSize: 15, fontWeight: '800', letterSpacing: 2 },
  headerIconBtn: { width: 40, height: 40, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.05)', justifyContent: 'center', alignItems: 'center' },
  
  scrollContent: { padding: 25 },
  progressRow: { flexDirection: 'row', gap: 10, marginBottom: 40, paddingHorizontal: 5 },
  progressStep: { flex: 1, height: 4, borderRadius: 2 },
  
  section: { marginBottom: 35 },
  sectionTitle: { color: '#94a3b8', fontSize: 11, fontWeight: '900', letterSpacing: 2, marginBottom: 15 },
  
  addressCard: { padding: 20 },
  addressHeader: { flexDirection: 'row', alignItems: 'center', gap: 15, marginBottom: 15 },
  addressIconBox: { width: 45, height: 45, borderRadius: 15, overflow: 'hidden', justifyContent: 'center', alignItems: 'center' },
  addressType: { color: '#8b5cf6', fontSize: 10, fontWeight: '900', letterSpacing: 1 },
  addressName: { color: '#fff', fontSize: 17, fontWeight: '700' },
  addressText: { color: '#94a3b8', fontSize: 14, lineHeight: 22 },
  changeBtn: { marginTop: 15 },
  changeBtnText: { color: '#8b5cf6', fontSize: 12, fontWeight: '800', textDecorationLine: 'underline' },
  
  paymentGrid: { flexDirection: 'row', gap: 15 },
  paymentItem: { flex: 1 },
  paymentCard: { height: 90, justifyContent: 'center', alignItems: 'center', gap: 8 },
  paymentLabel: { color: '#64748b', fontSize: 12, fontWeight: '800' },
  
  summaryCard: { padding: 25 },
  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 15 },
  summaryLabel: { color: '#94a3b8', fontSize: 14, fontWeight: '500' },
  summaryValue: { color: '#fff', fontSize: 14, fontWeight: '700' },
  divider: { height: 1, backgroundColor: 'rgba(255,255,255,0.05)', marginVertical: 15 },
  totalLabel: { color: '#fff', fontSize: 14, fontWeight: '900', letterSpacing: 1 },
  totalValue: { color: '#fff', fontSize: 22, fontWeight: '900' },
  
  safetyInfo: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, marginTop: 10 },
  safetyText: { color: '#475569', fontSize: 12, fontWeight: '600' },
  
  bottomBar: { position: 'absolute', bottom: 0, width: '100%', borderTopLeftRadius: 35, borderTopRightRadius: 35, overflow: 'hidden' },
  bottomBlur: { padding: 25, paddingBottom: Platform.OS === 'ios' ? 40 : 25 },
  placeOrderBtn: { width: '100%', height: 65, borderRadius: 25, overflow: 'hidden' },
  placeOrderGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  placeOrderText: { color: '#fff', fontSize: 15, fontWeight: '900', letterSpacing: 1.5 },
  
  successContent: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  successIconBox: { width: 130, height: 130, borderRadius: 65, overflow: 'hidden', justifyContent: 'center', alignItems: 'center', marginBottom: 30 },
  successTitle: { color: '#fff', fontSize: 26, fontWeight: '900', letterSpacing: 2, textAlign: 'center', marginBottom: 15 },
  successSub: { color: '#94a3b8', fontSize: 16, textAlign: 'center', lineHeight: 24, marginBottom: 40 },
  homeBtn: { width: '100%', height: 65, borderRadius: 25, overflow: 'hidden' },
  homeBtnGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  homeBtnText: { color: '#fff', fontSize: 14, fontWeight: '900', letterSpacing: 1.5 },
});
