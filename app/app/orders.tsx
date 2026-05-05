import React, { useState, useEffect } from 'react';
import { StyleSheet, Text, View, ScrollView, TouchableOpacity, SafeAreaView, ActivityIndicator, Image, Platform, RefreshControl } from 'react-native';
import { useRouter } from 'expo-router';
import { ChevronLeft, Package, Clock, CheckCircle2, ChevronRight, Truck, AlertCircle } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import Config from '@/constants/Config';

export default function OrdersScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const [orders, setOrders] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchOrders = async () => {
    if (!user) return;
    try {
      const response = await fetch(`${Config.API_URL}/mobile_orders.php?user_id=${user.id}`);
      const data = await response.json();
      if (data.ok) {
        setOrders(data.orders);
      }
    } catch (error) {
      console.error('Fetch orders error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, [user]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchOrders();
  };

  const getStatusStep = (status: string) => {
    switch (status.toLowerCase()) {
      case 'delivered': return 3;
      case 'shipped': return 2;
      case 'processing': return 1;
      default: return 1;
    }
  };

  const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
      case 'delivered': return '#10b981';
      case 'shipped': return '#3b82f6';
      case 'processing': return '#f59e0b';
      case 'cancelled': return '#ef4444';
      default: return '#8b5cf6';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status.toLowerCase()) {
      case 'delivered': return CheckCircle2;
      case 'shipped': return Truck;
      case 'processing': return Clock;
      case 'cancelled': return AlertCircle;
      default: return Package;
    }
  };

  if (!user) {
    return (
      <View style={styles.container}>
        <BackgroundScene />
        <LuxeHeader showBack={true} />
        <View style={styles.emptyContainer}>
          <Package size={80} color="#8b5cf6" />
          <Text style={styles.emptyTitle}>ELITE ACCESS REQUIRED</Text>
          <Text style={styles.emptySub}>Please sign in to view and track your luxury curations and elite order history.</Text>
          <TouchableOpacity style={styles.loginBtn} onPress={() => router.push('/(tabs)/profile')}>
            <LinearGradient colors={['#8b5cf6', '#ec4899']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.loginBtnGradient}>
              <Text style={styles.loginBtnText}>SIGN IN NOW</Text>
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
        
        <ScrollView 
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#8b5cf6" />
          }
        >
          <View style={styles.pageHeader}>
            <Text style={styles.pageTitle}>MY ELITE ORDERS</Text>
            <View style={styles.headerLine} />
            <Text style={styles.pageSub}>Track your luxury curations and delivery status</Text>
          </View>

          {loading ? (
            <View style={styles.loaderContainer}>
              <ActivityIndicator color="#8b5cf6" size="large" />
              <Text style={styles.loaderText}>Curating your order history...</Text>
            </View>
          ) : orders.length === 0 ? (
            <View style={styles.emptyState}>
              <GlassCard intensity={15} borderRadius={35} style={styles.emptyCard}>
                <Package size={60} color="rgba(255,255,255,0.1)" />
                <Text style={styles.emptyText}>No Curations Yet</Text>
                <Text style={styles.emptySubText}>Begin your luxury journey with our latest collection.</Text>
                <TouchableOpacity style={styles.shopBtn} onPress={() => router.push('/(tabs)/shop')}>
                  <LinearGradient colors={['rgba(139, 92, 246, 0.2)', 'rgba(236, 72, 153, 0.2)']} style={styles.shopBtnGradient}>
                    <Text style={styles.shopNow}>DISCOVER COLLECTION</Text>
                  </LinearGradient>
                </TouchableOpacity>
              </GlassCard>
            </View>
          ) : (
            orders.map((order) => {
              const StatusIcon = getStatusIcon(order.status);
              const statusStep = getStatusStep(order.status);
              const statusColor = getStatusColor(order.status);

              return (
                <TouchableOpacity 
                  key={order.id} 
                  activeOpacity={0.9}
                  onPress={() => {/* Order Details */}}
                >
                  <GlassCard intensity={20} borderRadius={30} style={styles.orderCard}>
                    {/* Top Row: Order ID & Status */}
                    <View style={styles.orderHeader}>
                      <View style={styles.idBadge}>
                        <Package size={14} color="#8b5cf6" />
                        <Text style={styles.orderId}>#{order.id}</Text>
                      </View>
                      <View style={[styles.statusBadge, { borderColor: `${statusColor}40` }]}>
                        <View style={[styles.statusDot, { backgroundColor: statusColor }]} />
                        <Text style={[styles.statusText, { color: statusColor }]}>{order.status.toUpperCase()}</Text>
                      </View>
                    </View>

                    {/* Middle Row: Product Preview & Price */}
                    <View style={styles.orderBody}>
                      <View style={styles.previewContainer}>
                        <LinearGradient colors={['rgba(255,255,255,0.05)', 'transparent']} style={styles.previewGlow} />
                        <View style={styles.previewBox}>
                          {order.preview_image ? (
                            <Image source={{ uri: order.preview_image }} style={styles.previewImg} />
                          ) : (
                            <Package size={24} color="rgba(255,255,255,0.1)" />
                          )}
                        </View>
                      </View>

                      <View style={styles.orderInfo}>
                        <Text style={styles.orderDate}>{new Date(order.created_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })}</Text>
                        <Text style={styles.itemCount}>{order.item_count} {order.item_count === 1 ? 'ELITE ITEM' : 'ELITE ITEMS'}</Text>
                        <Text style={styles.orderTotal}>₹{parseFloat(order.total_amount).toLocaleString()}</Text>
                      </View>

                      <View style={styles.actionBox}>
                        <ChevronRight size={20} color="rgba(255,255,255,0.2)" />
                      </View>
                    </View>

                    {/* Bottom Row: Minimalist Timeline */}
                    <View style={styles.timelineWrapper}>
                      <View style={styles.timelineBar}>
                        <View style={styles.timelineBg} />
                        <LinearGradient 
                          colors={[statusColor, `${statusColor}80`]} 
                          start={{x:0, y:0}} end={{x:1, y:0}}
                          style={[styles.timelineFill, { width: `${(statusStep / 3) * 100}%` }]} 
                        />
                      </View>
                      <View style={styles.timelineLabels}>
                        <Text style={[styles.timeLabel, statusStep >= 1 && { color: '#fff' }]}>Placed</Text>
                        <Text style={[styles.timeLabel, statusStep >= 2 && { color: '#fff' }, { textAlign: 'center' }]}>Shipped</Text>
                        <Text style={[styles.timeLabel, statusStep >= 3 && { color: '#fff' }, { textAlign: 'right' }]}>Arrived</Text>
                      </View>
                    </View>
                  </GlassCard>
                </TouchableOpacity>
              )
            })
          )}
          <View style={{ height: 120 }} />
        </ScrollView>
      </SafeAreaView>

      <TouchableOpacity style={styles.fab} activeOpacity={0.8}>
        <LinearGradient colors={['#8b5cf6', '#ec4899']} style={styles.fabGradient}>
          <AlertCircle size={20} color="#fff" />
          <Text style={styles.fabText}>ELITE SUPPORT</Text>
        </LinearGradient>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  scrollContent: { padding: 18 },
  
  pageHeader: { marginBottom: 30, paddingHorizontal: 5 },
  pageTitle: { color: '#fff', fontSize: 26, fontWeight: '900', letterSpacing: 2, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  headerLine: { width: 40, height: 3, backgroundColor: '#8b5cf6', marginTop: 10, borderRadius: 2 },
  pageSub: { color: '#64748b', fontSize: 13, fontWeight: '500', marginTop: 12, lineHeight: 18 },

  orderCard: { padding: 20, marginBottom: 18, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
  orderHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 },
  idBadge: { flexDirection: 'row', alignItems: 'center', gap: 8, backgroundColor: 'rgba(139, 92, 246, 0.1)', paddingHorizontal: 12, paddingVertical: 6, borderRadius: 12 },
  orderId: { color: '#fff', fontSize: 13, fontWeight: '800', letterSpacing: 0.5 },
  statusBadge: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingHorizontal: 12, paddingVertical: 5, borderRadius: 12, borderWidth: 1 },
  statusDot: { width: 6, height: 6, borderRadius: 3 },
  statusText: { fontSize: 10, fontWeight: '900', letterSpacing: 1 },

  orderBody: { flexDirection: 'row', alignItems: 'center', gap: 15, marginBottom: 20 },
  previewContainer: { position: 'relative', width: 70, height: 70 },
  previewGlow: { ...StyleSheet.absoluteFillObject, borderRadius: 20, opacity: 0.5 },
  previewBox: { flex: 1, borderRadius: 20, backgroundColor: 'rgba(255,255,255,0.03)', justifyContent: 'center', alignItems: 'center', overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.08)' },
  previewImg: { width: '100%', height: '100%' },
  orderInfo: { flex: 1, gap: 4 },
  orderDate: { color: '#475569', fontSize: 11, fontWeight: '700', letterSpacing: 0.5 },
  itemCount: { color: '#8b5cf6', fontSize: 10, fontWeight: '800', letterSpacing: 1 },
  orderTotal: { color: '#fff', fontSize: 20, fontWeight: '900', marginTop: 2 },
  actionBox: { width: 32, height: 32, borderRadius: 16, backgroundColor: 'rgba(255,255,255,0.02)', justifyContent: 'center', alignItems: 'center' },

  // New Minimalist Timeline
  timelineWrapper: { marginTop: 5 },
  timelineBar: { height: 4, borderRadius: 2, position: 'relative', overflow: 'hidden', marginBottom: 10 },
  timelineBg: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(255,255,255,0.05)' },
  timelineFill: { height: '100%', borderRadius: 2 },
  timelineLabels: { flexDirection: 'row', justifyContent: 'space-between' },
  timeLabel: { flex: 1, color: '#1e293b', fontSize: 9, fontWeight: '800', letterSpacing: 0.5 },

  loaderContainer: { alignItems: 'center', marginTop: 100, gap: 15 },
  loaderText: { color: '#64748b', fontSize: 14, fontWeight: '500' },

  emptyContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
  emptyTitle: { color: '#fff', fontSize: 24, fontWeight: '900', marginTop: 25, letterSpacing: 1 },
  emptySub: { color: '#64748b', fontSize: 15, textAlign: 'center', marginTop: 12, lineHeight: 24, marginBottom: 40 },
  loginBtn: { width: '100%', height: 65, borderRadius: 32, overflow: 'hidden' },
  loginBtnGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  loginBtnText: { color: '#fff', fontWeight: '900', fontSize: 14, letterSpacing: 2 },

  emptyState: { alignItems: 'center', marginTop: 40 },
  emptyCard: { width: '100%', padding: 45, alignItems: 'center', gap: 15 },
  emptyText: { color: '#fff', fontSize: 20, fontWeight: '800' },
  emptySubText: { color: '#64748b', fontSize: 13, textAlign: 'center', lineHeight: 20 },
  shopBtn: { marginTop: 10, height: 50, borderRadius: 25, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(139, 92, 246, 0.3)' },
  shopBtnGradient: { flex: 1, paddingHorizontal: 25, justifyContent: 'center', alignItems: 'center' },
  shopNow: { color: '#fff', fontSize: 12, fontWeight: '900', letterSpacing: 1 },

  fab: { position: 'absolute', bottom: 100, right: 20, height: 55, borderRadius: 28, overflow: 'hidden', elevation: 15, shadowColor: '#8b5cf6', shadowOpacity: 0.5, shadowRadius: 15 },
  fabGradient: { flex: 1, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 22, gap: 10 },
  fabText: { color: '#fff', fontWeight: '900', fontSize: 12, letterSpacing: 1 },
});


