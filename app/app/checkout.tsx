import React, { useEffect, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TouchableOpacity,
  Pressable,
  TextInput,
  ActivityIndicator,
  Platform,
  Image,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  MapPin,
  Globe,
  ShieldCheck,
  CheckCircle2,
  Banknote,
  ShoppingBag,
  Plus,
} from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import { useCart } from '@/context/CartContext';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import LuxeHeader from '@/components/LuxeHeader';
import { fetchAddresses, formatAddressLines, formatPrice, type UserAddress } from '@/lib/api';

const PAYMENTS = [
  { id: 'COD', icon: Banknote, label: 'Cash on Delivery', desc: 'Pay when delivered' },
  { id: 'ONLINE', icon: Globe, label: 'Online Payment', desc: 'UPI, Cards, Netbanking' },
];

const ADDR_TYPES = ['Home', 'Work', 'Other'] as const;

const emptyForm = {
  name: '',
  phone: '',
  line1: '',
  line2: '',
  city: '',
  state: '',
  pin: '',
  type: 'Home',
};

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

export default function CheckoutScreen() {
  const router = useRouter();
  const { items, cartCount, fetchCart } = useCart();
  const { user } = useAuth();
  const { colors, isDark } = useAppTheme();

  const [loading, setLoading] = useState(false);
  const [addrLoading, setAddrLoading] = useState(false);
  const [savingAddr, setSavingAddr] = useState(false);
  const [success, setSuccess] = useState(false);
  const [orderRef, setOrderRef] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('COD');
  const [addresses, setAddresses] = useState<UserAddress[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);

  const subtotal = items.reduce((sum, item) => sum + item.price * item.qty, 0);
  const shipping = 0;
  const total = subtotal + shipping;
  const selected = addresses.find((a) => a.id === selectedId) || null;

  const applyList = (list: UserAddress[], preferId?: number | null) => {
    setAddresses(list);
    const preferred =
      (preferId && list.find((a) => a.id === preferId)?.id) ||
      list.find((a) => a.isDefault)?.id ||
      list[0]?.id ||
      null;
    setSelectedId(preferred);
    if (!list.length) {
      setShowForm(true);
      setEditingId(null);
    }
  };

  const loadAddresses = async (userId: number) => {
    setAddrLoading(true);
    try {
      const list = await fetchAddresses(userId);
      applyList(list);
      if (!list.length) {
        setForm({
          ...emptyForm,
          name: [user?.first_name, user?.last_name].filter(Boolean).join(' '),
          phone: user?.phone || '',
        });
      } else {
        setShowForm(false);
      }
    } catch {
      applyList([]);
    } finally {
      setAddrLoading(false);
    }
  };

  useEffect(() => {
    if (!user?.id) {
      setAddresses([]);
      setSelectedId(null);
      setShowForm(false);
      return;
    }
    loadAddresses(Number(user.id));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id]);

  const openAddForm = () => {
    setEditingId(null);
    setForm({
      ...emptyForm,
      name: [user?.first_name, user?.last_name].filter(Boolean).join(' '),
      phone: user?.phone || '',
    });
    setShowForm(true);
  };

  const openEditForm = (addr: UserAddress) => {
    setEditingId(addr.id);
    setForm({
      name: addr.name || '',
      phone: addr.phone || '',
      line1: addr.line1 || '',
      line2: addr.line2 || '',
      city: addr.city || '',
      state: addr.state || '',
      pin: addr.pin || '',
      type: ADDR_TYPES.includes(addr.type as (typeof ADDR_TYPES)[number]) ? addr.type : 'Home',
    });
    setShowForm(true);
  };

  const saveAddress = async () => {
    if (!user?.id) {
      notify('Login required', 'Please sign in to save an address.');
      router.push('/(tabs)/profile');
      return;
    }
    if (!form.name.trim() || !form.line1.trim() || !form.city.trim() || !form.state.trim() || !form.pin.trim()) {
      notify('Address incomplete', 'Name, address, city, state, and PIN are required.');
      return;
    }

    setSavingAddr(true);
    try {
      const response = await fetch(`${Config.API_URL}/mobile_addresses.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save',
          user_id: Number(user.id),
          id: editingId || 0,
          type: form.type,
          name: form.name.trim(),
          phone: form.phone.trim(),
          line1: form.line1.trim(),
          line2: form.line2.trim(),
          city: form.city.trim(),
          state: form.state.trim(),
          pin: form.pin.trim(),
          is_default: addresses.length === 0 || editingId === selectedId,
        }),
      });
      const data = await response.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not save address');
        return;
      }
      applyList(data.addresses || [], data.address_id || editingId);
      setShowForm(false);
      setEditingId(null);
    } catch {
      notify('Error', 'Could not save address. Please try again.');
    } finally {
      setSavingAddr(false);
    }
  };

  const handlePlaceOrder = async () => {
    if (!user) {
      notify('Login required', 'Please sign in to place your order.');
      router.push('/(tabs)/profile');
      return;
    }
    if (!items.length) {
      notify('Bag empty', 'Add items before checkout.');
      return;
    }
    if (!selectedId) {
      notify('Address required', 'Please add or select a delivery address.');
      return;
    }

    setLoading(true);
    try {
      const response = await fetch(`${Config.API_URL}/mobile_checkout.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          user_id: user.id,
          address_id: selectedId,
          payment_method: paymentMethod,
        }),
      });
      const data = await response.json();
      if (data.ok) {
        setOrderRef(data.order_ref || '');
        setSuccess(true);
        fetchCart();
      } else {
        notify('Error', data.error || 'Failed to place order');
      }
    } catch {
      notify('Error', 'Something went wrong. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea}>
          <View style={styles.successContent}>
            <View style={styles.successIconBox}>
              <CheckCircle2 size={42} color="#34d399" />
            </View>
            <Text style={[styles.successTitle, { color: colors.text }]}>Order placed</Text>
            {!!orderRef && <Text style={[styles.orderRef, { color: isDark ? '#c4b5fd' : colors.accent }]}>#{orderRef}</Text>}
            <Text style={[styles.successSub, { color: colors.muted }]}>
              We’ll pack your items and send tracking updates soon.
            </Text>
            <Pressable style={styles.homeBtn} onPress={() => router.push('/(tabs)')}>
              <LinearGradient
                colors={colors.cta}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 0 }}
                style={styles.homeBtnGradient}
                pointerEvents="none"
              >
                <Text style={styles.homeBtnText}>Continue shopping</Text>
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
        <LuxeHeader showBack title="Checkout" />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {!user && (
            <Pressable style={[styles.loginBanner, { backgroundColor: colors.primarySoft, borderColor: colors.border }]} onPress={() => router.push('/(tabs)/profile')}>
              <Text style={[styles.loginBannerText, { color: colors.text }]}>Sign in to place your order</Text>
              <Text style={[styles.loginBannerLink, { color: isDark ? '#c4b5fd' : colors.accent }]}>Go to profile</Text>
            </Pressable>
          )}

          {items.length === 0 && (
            <View style={[styles.emptyCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <ShoppingBag size={28} color={isDark ? '#c4b5fd' : colors.accent} />
              <Text style={[styles.emptyTitle, { color: colors.text }]}>Your bag is empty</Text>
              <Pressable onPress={() => router.push('/(tabs)/shop')}>
                <Text style={[styles.emptyLink, { color: isDark ? '#c4b5fd' : colors.accent }]}>Browse products</Text>
              </Pressable>
            </View>
          )}

          <View style={styles.sectionHead}>
            <Text style={[styles.sectionTitle, { color: colors.text }]}>Delivery address</Text>
            {!!user && (
              <TouchableOpacity onPress={openAddForm}>
                <View style={styles.addRow}>
                  <Plus size={14} color={isDark ? '#c4b5fd' : colors.accent} />
                  <Text style={[styles.link, { color: isDark ? '#c4b5fd' : colors.accent }]}>Add new</Text>
                </View>
              </TouchableOpacity>
            )}
          </View>

          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {!user ? (
              <Text style={[styles.addressText, { color: colors.textSecondary }]}>Sign in to use your saved addresses.</Text>
            ) : addrLoading ? (
              <ActivityIndicator color={isDark ? '#c4b5fd' : '#0f172a'} />
            ) : addresses.length === 0 && !showForm ? (
              <View style={styles.emptyAddr}>
                <MapPin size={20} color={isDark ? '#c4b5fd' : colors.accent} />
                <Text style={[styles.emptyTitle, { color: colors.text }]}>No saved address</Text>
                <TouchableOpacity onPress={openAddForm}>
                  <Text style={[styles.emptyLink, { color: isDark ? '#c4b5fd' : colors.accent }]}>Add delivery address</Text>
                </TouchableOpacity>
              </View>
            ) : (
              addresses.map((addr, i) => {
                const on = selectedId === addr.id;
                return (
                  <TouchableOpacity
                    key={addr.id}
                    style={[styles.addrRow, i > 0 && styles.payRowBorder, on && styles.addrRowOn]}
                    onPress={() => setSelectedId(addr.id)}
                    activeOpacity={0.8}
                  >
                    <View style={[styles.radio, on && styles.radioOn]}>
                      {on && <View style={styles.radioDot} />}
                    </View>
                    <View style={{ flex: 1 }}>
                      <View style={styles.addrTop}>
                        <Text style={[styles.cardTitle, { color: colors.text }]}>{addr.name || 'Address'}</Text>
                        <Text style={[styles.cardKicker, { color: isDark ? '#a78bfa' : colors.accent }]}>{addr.type || 'Home'}</Text>
                        {addr.isDefault ? <Text style={styles.defaultBadge}>Default</Text> : null}
                      </View>
                      <Text style={[styles.addressText, { color: colors.textSecondary }]}>{formatAddressLines(addr)}</Text>
                      {!!addr.phone && <Text style={[styles.phoneText, { color: colors.muted }]}>{addr.phone}</Text>}
                      <TouchableOpacity onPress={() => openEditForm(addr)}>
                        <Text style={[styles.editLink, { color: isDark ? '#c4b5fd' : colors.accent }]}>Edit</Text>
                      </TouchableOpacity>
                    </View>
                  </TouchableOpacity>
                );
              })
            )}

            {showForm && !!user && (
              <View style={[styles.form, addresses.length > 0 && styles.formTop, addresses.length > 0 && { borderTopColor: colors.hairline }]}>
                <Text style={[styles.formTitle, { color: colors.text }]}>{editingId ? 'Edit address' : 'New address'}</Text>
                <View style={styles.typeRow}>
                  {ADDR_TYPES.map((type) => (
                    <TouchableOpacity
                      key={type}
                      style={[
                        styles.typeChip,
                        { borderColor: colors.border },
                        form.type === type && { backgroundColor: isDark ? 'rgba(139,92,246,0.28)' : '#0f172a', borderColor: isDark ? 'rgba(167,139,250,0.6)' : '#0f172a' },
                      ]}
                      onPress={() => setForm((f) => ({ ...f, type }))}
                    >
                      <Text style={[styles.typeChipText, { color: form.type === type ? '#fff' : colors.text }]}>
                        {type}
                      </Text>
                    </TouchableOpacity>
                  ))}
                </View>
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Full name"
                  placeholderTextColor={colors.placeholder}
                  value={form.name}
                  onChangeText={(name) => setForm((f) => ({ ...f, name }))}
                />
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Phone"
                  placeholderTextColor={colors.placeholder}
                  keyboardType="phone-pad"
                  value={form.phone}
                  onChangeText={(phone) => setForm((f) => ({ ...f, phone }))}
                />
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Address line 1"
                  placeholderTextColor={colors.placeholder}
                  value={form.line1}
                  onChangeText={(line1) => setForm((f) => ({ ...f, line1 }))}
                />
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Address line 2 (optional)"
                  placeholderTextColor={colors.placeholder}
                  value={form.line2}
                  onChangeText={(line2) => setForm((f) => ({ ...f, line2 }))}
                />
                <View style={styles.row2}>
                  <TextInput
                    style={[styles.input, styles.flex, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                    placeholder="City"
                    placeholderTextColor={colors.placeholder}
                    value={form.city}
                    onChangeText={(city) => setForm((f) => ({ ...f, city }))}
                  />
                  <TextInput
                    style={[styles.input, styles.flex, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                    placeholder="State"
                    placeholderTextColor={colors.placeholder}
                    value={form.state}
                    onChangeText={(state) => setForm((f) => ({ ...f, state }))}
                  />
                </View>
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="PIN code"
                  placeholderTextColor={colors.placeholder}
                  keyboardType="number-pad"
                  value={form.pin}
                  onChangeText={(pin) => setForm((f) => ({ ...f, pin }))}
                />
                <View style={styles.formActions}>
                  {addresses.length > 0 && (
                    <TouchableOpacity
                      onPress={() => {
                        setShowForm(false);
                        setEditingId(null);
                      }}
                    >
                      <Text style={[styles.link, { color: isDark ? '#c4b5fd' : colors.accent }]}>Cancel</Text>
                    </TouchableOpacity>
                  )}
                  <Pressable style={styles.saveBtn} onPress={saveAddress} disabled={savingAddr}>
                    {savingAddr ? (
                      <ActivityIndicator color="#fff" />
                    ) : (
                      <Text style={styles.saveBtnText}>Save address</Text>
                    )}
                  </Pressable>
                </View>
              </View>
            )}
          </View>

          <Text style={[styles.sectionTitle, { color: colors.text }]}>Payment method</Text>
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {PAYMENTS.map((method, i) => {
              const on = paymentMethod === method.id;
              const Icon = method.icon;
              return (
                <TouchableOpacity
                  key={method.id}
                  style={[styles.payRow, i > 0 && styles.payRowBorder, i > 0 && { borderTopColor: colors.hairline }]}
                  onPress={() => setPaymentMethod(method.id)}
                  activeOpacity={0.8}
                >
                  <View style={[styles.payIcon, { backgroundColor: colors.input }, on && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a' }]}>
                    <Icon size={18} color={on ? '#fff' : colors.iconMuted} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={[styles.payLabel, { color: on ? colors.text : colors.muted }]}>{method.label}</Text>
                    <Text style={[styles.payDesc, { color: colors.muted }]}>{method.desc}</Text>
                  </View>
                  <View style={[styles.radio, { borderColor: colors.border }, on && styles.radioOn]}>
                    {on && <View style={styles.radioDot} />}
                  </View>
                </TouchableOpacity>
              );
            })}
            <Text style={[styles.payNote, { color: colors.muted }]}>
              {paymentMethod === 'COD'
                ? 'Cash on Delivery — Please keep exact change ready for smoother delivery.'
                : 'You will be redirected to complete payment after placing the order.'}
            </Text>
          </View>

          {items.length > 0 && (
            <>
              <Text style={[styles.sectionTitle, { color: colors.text }]}>Items ({cartCount})</Text>
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                {items.map((item, i) => (
                  <View key={item.id} style={[styles.itemRow, i > 0 && styles.payRowBorder, i > 0 && { borderTopColor: colors.hairline }]}>
                    {item.image_url ? (
                      <Image source={{ uri: item.image_url }} style={[styles.itemImg, { backgroundColor: colors.productImageBg }]} />
                    ) : (
                      <View style={[styles.itemImg, styles.itemImgFallback, { backgroundColor: colors.productImageBg }]} />
                    )}
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={1}>
                        {item.name}
                      </Text>
                      <Text style={[styles.itemMeta, { color: colors.muted }]}>Qty {item.qty}</Text>
                    </View>
                    <Text style={[styles.itemPrice, { color: colors.text }]}>{formatPrice(item.price * item.qty)}</Text>
                  </View>
                ))}
              </View>
            </>
          )}

          <Text style={[styles.sectionTitle, { color: colors.text }]}>Order summary</Text>
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <View style={styles.summaryRow}>
              <Text style={[styles.summaryLabel, { color: colors.muted }]}>Subtotal</Text>
              <Text style={[styles.summaryValue, { color: colors.text }]}>{formatPrice(subtotal)}</Text>
            </View>
            <View style={styles.summaryRow}>
              <Text style={[styles.summaryLabel, { color: colors.muted }]}>Delivery</Text>
              <Text style={styles.freeText}>FREE</Text>
            </View>
            <View style={[styles.divider, { backgroundColor: colors.hairline }]} />
            <View style={styles.summaryRow}>
              <Text style={[styles.totalLabel, { color: colors.text }]}>Total</Text>
              <Text style={[styles.totalValue, { color: colors.text }]}>{formatPrice(total)}</Text>
            </View>
          </View>

          <View style={styles.safetyInfo}>
            <ShieldCheck size={14} color="#64748b" />
            <Text style={styles.safetyText}>Secure checkout</Text>
          </View>
        </ScrollView>

        <View style={[styles.bottomBar, { backgroundColor: colors.tabBar, borderTopColor: colors.tabBorder }]}>
          <View>
            <Text style={[styles.bottomHint, { color: colors.muted }]}>Payable</Text>
            <Text style={[styles.bottomTotal, { color: colors.text }]}>{formatPrice(total)}</Text>
          </View>
          <Pressable
            style={[styles.placeBtn, (!items.length || loading || !selected) && { opacity: 0.55 }]}
            onPress={handlePlaceOrder}
            disabled={loading || !items.length || !selected}
          >
            <LinearGradient
              colors={colors.cta}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.placeGrad}
              pointerEvents="none"
            >
              {loading ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.placeText}>Place order</Text>
              )}
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

  loginBanner: {
    backgroundColor: 'rgba(139,92,246,0.14)',
    borderWidth: 1,
    borderColor: 'rgba(139,92,246,0.35)',
    borderRadius: 14,
    padding: 14,
    marginBottom: 16,
  },
  loginBannerText: { color: '#fff', fontSize: 14, fontWeight: '700' },
  loginBannerLink: { color: '#c4b5fd', fontSize: 13, fontWeight: '700', marginTop: 4 },

  emptyCard: {
    alignItems: 'center',
    padding: 24,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    marginBottom: 16,
    gap: 8,
  },
  emptyTitle: { color: '#fff', fontSize: 16, fontWeight: '700' },
  emptyLink: { color: '#c4b5fd', fontSize: 13, fontWeight: '700' },
  emptyAddr: { alignItems: 'center', gap: 8, paddingVertical: 8 },

  sectionHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
    marginTop: 6,
  },
  sectionTitle: {
    color: '#94a3b8',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.6,
    marginBottom: 8,
    marginTop: 6,
  },
  addRow: { flexDirection: 'row', alignItems: 'center', gap: 4, marginBottom: 8 },
  card: {
    backgroundColor: 'rgba(255,255,255,0.05)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    padding: 14,
    marginBottom: 16,
  },
  addrRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12, paddingVertical: 10 },
  addrRowOn: { backgroundColor: 'rgba(139,92,246,0.08)', marginHorizontal: -6, paddingHorizontal: 6, borderRadius: 12 },
  addrTop: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 4 },
  cardKicker: { color: '#a78bfa', fontSize: 10, fontWeight: '800', letterSpacing: 1 },
  cardTitle: { color: '#fff', fontSize: 16, fontWeight: '700' },
  defaultBadge: {
    color: '#34d399',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.4,
    borderWidth: 1,
    borderColor: 'rgba(52,211,153,0.35)',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 6,
  },
  link: { color: '#c4b5fd', fontSize: 13, fontWeight: '700' },
  editLink: { color: '#c4b5fd', fontSize: 12, fontWeight: '700', marginTop: 8 },
  addressText: { color: '#94a3b8', fontSize: 14, lineHeight: 21 },
  phoneText: { color: '#64748b', fontSize: 13, marginTop: 4 },
  form: { gap: 8 },
  formTop: { marginTop: 12, paddingTop: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)' },
  formTitle: { color: '#fff', fontSize: 14, fontWeight: '700', marginBottom: 4 },
  typeRow: { flexDirection: 'row', gap: 8 },
  typeChip: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  typeChipOn: { backgroundColor: 'rgba(139,92,246,0.28)', borderColor: 'rgba(167,139,250,0.6)' },
  typeChipText: { color: '#94a3b8', fontSize: 12, fontWeight: '700' },
  typeChipTextOn: { color: '#fff' },
  input: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 11,
    color: '#fff',
    fontSize: 14,
  },
  row2: { flexDirection: 'row', gap: 8 },
  flex: { flex: 1 },
  formActions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 16, marginTop: 4 },
  saveBtn: {
    backgroundColor: '#7c3aed',
    borderRadius: 12,
    paddingHorizontal: 16,
    height: 42,
    minWidth: 120,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveBtnText: { color: '#fff', fontSize: 13, fontWeight: '800' },

  payRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10 },
  payRowBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.08)' },
  payIcon: {
    width: 38,
    height: 38,
    borderRadius: 10,
    backgroundColor: 'rgba(255,255,255,0.06)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  payIconOn: { backgroundColor: 'rgba(139,92,246,0.35)' },
  payLabel: { color: '#94a3b8', fontSize: 14, fontWeight: '600' },
  payLabelOn: { color: '#fff' },
  payDesc: { color: '#64748b', fontSize: 12, marginTop: 2 },
  payNote: { color: '#94a3b8', fontSize: 12, lineHeight: 18, marginTop: 8 },
  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.25)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioOn: { borderColor: '#a78bfa' },
  radioDot: { width: 10, height: 10, borderRadius: 5, backgroundColor: '#a78bfa' },

  itemRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8 },
  itemImg: { width: 48, height: 58, borderRadius: 8, backgroundColor: '#16161f' },
  itemImgFallback: {},
  itemName: { color: '#fff', fontSize: 13, fontWeight: '700' },
  itemMeta: { color: '#64748b', fontSize: 12, marginTop: 2 },
  itemPrice: { color: '#fff', fontSize: 13, fontWeight: '800' },

  summaryRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
  summaryLabel: { color: '#94a3b8', fontSize: 14 },
  summaryValue: { color: '#fff', fontSize: 14, fontWeight: '700' },
  freeText: { color: '#34d399', fontSize: 13, fontWeight: '800' },
  divider: { height: StyleSheet.hairlineWidth, backgroundColor: 'rgba(255,255,255,0.12)', marginVertical: 6 },
  totalLabel: { color: '#fff', fontSize: 15, fontWeight: '800' },
  totalValue: { color: '#fff', fontSize: 20, fontWeight: '800' },

  safetyInfo: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6, marginTop: 4 },
  safetyText: { color: '#475569', fontSize: 12 },

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
  placeBtn: { flex: 1, maxWidth: 200, borderRadius: 14, overflow: 'hidden' },
  placeGrad: { height: 50, alignItems: 'center', justifyContent: 'center' },
  placeText: { color: '#fff', fontSize: 14, fontWeight: '800' },

  successContent: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
  successIconBox: {
    width: 88,
    height: 88,
    borderRadius: 28,
    backgroundColor: 'rgba(16,185,129,0.15)',
    borderWidth: 1,
    borderColor: 'rgba(16,185,129,0.35)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  successTitle: { color: '#fff', fontSize: 24, fontWeight: '800', marginBottom: 8 },
  orderRef: { color: '#c4b5fd', fontSize: 14, fontWeight: '700', marginBottom: 10 },
  successSub: { color: '#94a3b8', fontSize: 15, textAlign: 'center', lineHeight: 22, marginBottom: 28 },
  homeBtn: { width: '100%', maxWidth: 280, borderRadius: 14, overflow: 'hidden' },
  homeBtnGradient: { height: 50, alignItems: 'center', justifyContent: 'center' },
  homeBtnText: { color: '#fff', fontSize: 14, fontWeight: '800' },
});
