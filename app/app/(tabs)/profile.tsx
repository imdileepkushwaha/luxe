import React, { useCallback, useEffect, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TouchableOpacity,
  TextInput,
  Modal,
  ActivityIndicator,
  Alert,
  Platform,
  KeyboardAvoidingView,
  Pressable,
  Keyboard,
  Dimensions,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Settings,
  Package,
  Heart,
  LogOut,
  Mail,
  Lock,
  ChevronRight,
  MapPin,
  Gift,
  Star,
  CheckCircle2,
  Clock,
  XCircle,
  Wallet,
  X,
  Eye,
  EyeOff,
  User,
} from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import { useAuth } from '@/context/AuthContext';
import { useWishlist } from '@/context/WishlistContext';
import LuxeHeader from '@/components/LuxeHeader';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import { fetchProfileSummary, formatPrice, type ProfileSummary } from '@/lib/api';

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

function AuthField({
  label,
  icon,
  trailing,
  ...inputProps
}: {
  label: string;
  icon: React.ReactNode;
  trailing?: React.ReactNode;
} & React.ComponentProps<typeof TextInput>) {
  const { colors } = useAppTheme();
  return (
    <View style={styles.field}>
      <Text style={[styles.fieldLabel, { color: colors.muted }]}>{label}</Text>
      <View style={[styles.inputWrap, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}>
        {icon}
        <TextInput
          style={[styles.inputField, { color: colors.text }]}
          placeholderTextColor={colors.placeholder}
          autoFocus={false}
          {...inputProps}
        />
        {trailing}
      </View>
    </View>
  );
}

export default function ProfileScreen() {
  const { user, login, logout } = useAuth();
  const { colors, isDark } = useAppTheme();
  const { wishlist } = useWishlist();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [keyboardHeight, setKeyboardHeight] = useState(0);

  const [modalVisible, setModalVisible] = useState(false);
  const [isLogin, setIsLogin] = useState(true);
  const [loading, setLoading] = useState(false);
  const [summary, setSummary] = useState<ProfileSummary | null>(null);

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [authError, setAuthError] = useState('');

  const loadSummary = useCallback(async () => {
    if (!user?.id) {
      setSummary(null);
      return;
    }
    try {
      setSummary(await fetchProfileSummary(Number(user.id)));
    } catch {
      setSummary(null);
    }
  }, [user?.id]);

  useFocusEffect(
    useCallback(() => {
      loadSummary();
    }, [loadSummary])
  );

  useEffect(() => {
    const showEvt = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvt = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const show = Keyboard.addListener(showEvt, (e) => {
      const winH = Dimensions.get('window').height;
      const overlap = Math.max(0, winH - e.endCoordinates.screenY);
      setKeyboardHeight(overlap);
    });
    const hide = Keyboard.addListener(hideEvt, () => setKeyboardHeight(0));
    return () => {
      show.remove();
      hide.remove();
    };
  }, []);

  useEffect(() => {
    if (!modalVisible) {
      setKeyboardHeight(0);
      return;
    }
    const t = setTimeout(() => Keyboard.dismiss(), 80);
    return () => clearTimeout(t);
  }, [modalVisible]);

  const closeAuth = () => {
    Keyboard.dismiss();
    setModalVisible(false);
    setAuthError('');
    setShowPassword(false);
  };

  const handleAuth = async () => {
    if (!email.trim() || !password) {
      setAuthError('Email and password are required.');
      return;
    }
    if (!isLogin && (!firstName.trim() || !lastName.trim())) {
      setAuthError('Please enter your first and last name.');
      return;
    }
    setAuthError('');
    setLoading(true);
    try {
      const response = await fetch(`${Config.API_URL}/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: isLogin ? 'login' : 'register',
          email: email.trim(),
          password: password.trim(),
          first_name: firstName,
          last_name: lastName,
        }),
      });
      const data = await response.json();
      if (data.ok) {
        if (isLogin) {
          await login(data.user);
          closeAuth();
        } else {
          setIsLogin(true);
          setPassword('');
          setAuthError('');
          notify('Account created', 'Sign in with your new LUXE account.');
        }
      } else {
        setAuthError(data.error || 'Could not continue');
      }
    } catch {
      setAuthError('Connection failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  const fullName = [user?.first_name, user?.last_name].filter(Boolean).join(' ') || 'Member';
  const stats = summary?.stats;
  const orderCount = stats?.order_count ?? 0;
  const points = summary?.loyalty.balance ?? 0;
  const pendingPoints = summary?.loyalty.pending ?? 0;
  const memberSince = summary?.user.member_since || '';
  const reviewCount = summary?.reviews.length ?? 0;

  const requireAuth = (go: () => void) => {
    if (!user) {
      setAuthError('');
      setModalVisible(true);
      return;
    }
    go();
  };

  const menu = [
    { icon: Package, label: 'Orders', sub: 'Track, return, invoice', color: '#a78bfa', action: () => requireAuth(() => router.push('/orders')) },
    { icon: MapPin, label: 'Address', sub: 'Saved delivery addresses', color: '#34d399', action: () => requireAuth(() => router.push('/addresses')) },
    { icon: Gift, label: 'Rewards', sub: user ? `${points.toLocaleString('en-IN')} LUXE points` : 'Earn on delivered orders', color: '#fbbf24', action: () => requireAuth(() => router.push('/rewards')) },
    { icon: Star, label: 'Reviews', sub: user ? `${reviewCount} product${reviewCount === 1 ? '' : 's'} to rate` : 'Rate delivered products', color: '#f472b6', action: () => requireAuth(() => router.push('/reviews')) },
    { icon: Settings, label: 'Account details', sub: 'Name, password and privacy', color: '#94a3b8', action: () => requireAuth(() => router.push('/settings')) },
    { icon: Heart, label: 'Wishlist', sub: `${wishlist.length} saved item${wishlist.length === 1 ? '' : 's'}`, color: '#fb7185', action: () => router.push('/(tabs)/wishlist') },
  ];

  const activity = user
    ? [
        { icon: Package, label: 'Total orders', value: String(orderCount), color: '#a78bfa' },
        { icon: CheckCircle2, label: 'Completed', value: String(stats?.delivered_count ?? 0), color: '#34d399' },
        { icon: Clock, label: 'Pending', value: String(stats?.pending_count ?? 0), color: '#fbbf24' },
        { icon: XCircle, label: 'Cancelled', value: String(stats?.cancelled_count ?? 0), color: '#f87171' },
        { icon: Heart, label: 'Wishlist', value: String(wishlist.length), color: '#fb7185' },
        { icon: Star, label: 'Reviews', value: String(reviewCount), color: '#f472b6' },
      ]
    : [];

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader title="Profile" />
        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          <View style={styles.hero}>
            <View style={styles.avatar}>
              <Text style={styles.avatarText}>{fullName.charAt(0).toUpperCase()}</Text>
            </View>
            <Text style={[styles.userName, { color: colors.text }]}>{user ? fullName : 'Welcome to LUXE'}</Text>
            <Text style={[styles.userEmail, { color: colors.textSecondary }]}>{user ? user.email : 'Sign in to manage orders, rewards, and reviews'}</Text>
            {!!memberSince && <Text style={[styles.memberSince, { color: colors.muted }]}>{memberSince}</Text>}

            {user ? (
              <View style={styles.badge}>
                <Text style={[styles.badgeText, { color: colors.goldText }]}>{summary?.loyalty.tier.title || 'LUXE Member'}</Text>
              </View>
            ) : (
              <Pressable
                style={[styles.ctaBtn]}
                onPress={() => {
                  setAuthError('');
                  setModalVisible(true);
                }}
              >
                <LinearGradient
                  colors={colors.cta}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 0 }}
                  style={styles.ctaGrad}
                  pointerEvents="none"
                >
                  <Text style={styles.ctaText}>Sign in</Text>
                </LinearGradient>
              </Pressable>
            )}
          </View>

          {user && summary && (
            <>
              <Pressable
                style={[
                  styles.rewardsBanner,
                  {
                    backgroundColor: isDark ? 'rgba(251,191,36,0.1)' : colors.goldBg,
                    borderColor: isDark ? 'rgba(251,191,36,0.35)' : 'rgba(217,119,6,0.25)',
                  },
                ]}
                onPress={() => router.push('/rewards')}
              >
                <View style={styles.rewardsIcon}>
                  <Gift size={20} color={colors.gold} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.rewardsPts, { color: colors.text }]}>{points.toLocaleString('en-IN')} LUXE points</Text>
                  <Text style={[styles.rewardsSub, { color: colors.goldText }]}>
                    {pendingPoints > 0
                      ? `${pendingPoints.toLocaleString('en-IN')} pending · tap to redeem`
                      : summary.loyalty.tier.lead}
                  </Text>
                </View>
                <ChevronRight size={18} color={colors.gold} />
              </Pressable>

              <View style={styles.spendRow}>
                <View style={[styles.spendCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Wallet size={16} color={isDark ? '#c4b5fd' : colors.purple} />
                  <Text style={[styles.spendLabel, { color: colors.muted }]}>Lifetime spend</Text>
                  <Text style={[styles.spendValue, { color: colors.text }]}>{formatPrice(summary.stats.lifetime_spend_rupees)}</Text>
                </View>
                <View style={[styles.spendCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <Gift size={16} color={colors.success} />
                  <Text style={[styles.spendLabel, { color: colors.muted }]}>Total saved</Text>
                  <Text style={[styles.spendValue, { color: colors.text }]}>{formatPrice(summary.stats.total_saved_rupees)}</Text>
                </View>
              </View>

              <Text style={[styles.sectionTitle, { color: colors.muted }]}>Activity</Text>
              <View style={styles.activityGrid}>
                {activity.map((item) => (
                  <View key={item.label} style={[styles.activityCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                    <View style={[styles.activityIcon, { backgroundColor: `${item.color}22` }]}>
                      <item.icon size={16} color={item.color} />
                    </View>
                    <Text style={[styles.activityValue, { color: colors.text }]}>{item.value}</Text>
                    <Text style={[styles.activityLabel, { color: colors.muted }]}>{item.label}</Text>
                  </View>
                ))}
              </View>

              <View style={[styles.infoCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <View style={styles.infoHead}>
                  <Text style={[styles.infoTitle, { color: colors.text }]}>Personal information</Text>
                  <TouchableOpacity onPress={() => router.push('/settings?edit=1')}>
                    <Text style={[styles.infoEdit, { color: isDark ? '#c4b5fd' : colors.purple }]}>Edit</Text>
                  </TouchableOpacity>
                </View>
                <View style={[styles.infoRow, { borderTopColor: colors.hairline }]}>
                  <Text style={[styles.infoDt, { color: colors.muted }]}>Full name</Text>
                  <Text style={[styles.infoDd, { color: colors.text }]}>{fullName}</Text>
                </View>
                <View style={[styles.infoRow, { borderTopColor: colors.hairline }]}>
                  <Text style={[styles.infoDt, { color: colors.muted }]}>Email</Text>
                  <Text style={[styles.infoDd, { color: colors.text }]}>{user.email}</Text>
                </View>
                <View style={[styles.infoRow, { borderTopColor: colors.hairline }]}>
                  <Text style={[styles.infoDt, { color: colors.muted }]}>Mobile</Text>
                  <Text style={[styles.infoDd, { color: colors.text }]}>{summary.user.phone || '—'}</Text>
                </View>
              </View>
            </>
          )}

          <Text style={[styles.sectionTitle, { color: colors.muted }]}>Account</Text>
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            {menu.map((item, i) => (
              <TouchableOpacity
                key={item.label}
                style={[styles.row, i > 0 && styles.rowBorder, i > 0 && { borderTopColor: colors.hairline }]}
                onPress={item.action}
                activeOpacity={0.8}
              >
                <View style={[styles.iconBox, { backgroundColor: `${item.color}22` }]}>
                  <item.icon size={18} color={item.color} />
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.rowTitle, { color: colors.text }]}>{item.label}</Text>
                  <Text style={[styles.rowSub, { color: colors.muted }]}>{item.sub}</Text>
                </View>
                <ChevronRight size={18} color={colors.iconMuted} />
              </TouchableOpacity>
            ))}
          </View>

          {user && (
            <TouchableOpacity style={[styles.logoutBtn, { borderColor: colors.dangerBg }]} onPress={logout}>
              <LogOut size={16} color={colors.danger} />
              <Text style={[styles.logoutText, { color: colors.dangerText }]}>Sign out</Text>
            </TouchableOpacity>
          )}
          <Text style={[styles.version, { color: colors.muted }]}>LUXE  ·  Version 1.0.0</Text>
        </ScrollView>
      </SafeAreaView>

      <Modal
        visible={modalVisible}
        animationType="slide"
        transparent
        statusBarTranslucent
        onRequestClose={closeAuth}
        onShow={() => Keyboard.dismiss()}
      >
        <KeyboardAvoidingView
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
          style={[
            styles.modalWrap,
            Platform.OS === 'android' ? { paddingBottom: keyboardHeight } : null,
          ]}
        >
          <Pressable style={styles.modalBackdrop} onPress={closeAuth} />
          <View
            style={[
              styles.sheet,
              {
                backgroundColor: colors.modal,
                borderColor: colors.border,
                maxHeight: Math.max(
                  280,
                  Dimensions.get('window').height - insets.top - 16 - (Platform.OS === 'android' ? keyboardHeight : 0)
                ),
                paddingBottom: Math.max(insets.bottom, 18),
                shadowColor: colors.shadowColor,
              },
            ]}
          >
            <View style={[styles.sheetHandle, { backgroundColor: isDark ? 'rgba(255,255,255,0.22)' : 'rgba(15,23,42,0.18)' }]} />
            <ScrollView
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="on-drag"
              showsVerticalScrollIndicator={false}
              bounces={false}
              contentContainerStyle={styles.sheetContent}
            >
              <View style={styles.authTop}>
                <View style={styles.authBrand}>
                  <Text style={[styles.authLogo, { color: colors.text }]}>LUXE</Text>
                  <View style={[styles.authLogoDot, { backgroundColor: isDark ? '#c4b5fd' : colors.accent }]} />
                </View>
                <Pressable
                  onPress={closeAuth}
                  hitSlop={10}
                  style={[styles.authClose, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}
                  accessibilityLabel="Close"
                >
                  <X size={18} color={colors.icon} />
                </Pressable>
              </View>

              <Text style={[styles.authKicker, { color: isDark ? '#c4b5fd' : colors.gold }]}>Member access</Text>
              <Text style={[styles.authTitle, { color: colors.text }]}>
                {isLogin ? 'Welcome back' : 'Join LUXE'}
              </Text>
              <Text style={[styles.authSub, { color: colors.textSecondary }]}>
                {isLogin
                  ? 'Sign in to track orders, save wishlist, and earn rewards.'
                  : 'Create an account to checkout faster and collect LUXE points.'}
              </Text>

              <View style={[styles.authTabs, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}>
                <Pressable
                  onPress={() => {
                    setIsLogin(true);
                    setAuthError('');
                  }}
                  style={[styles.authTab, isLogin && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a' }]}
                >
                  <Text style={[styles.authTabText, { color: isLogin ? '#fff' : colors.muted }]}>Sign in</Text>
                </Pressable>
                <Pressable
                  onPress={() => {
                    setIsLogin(false);
                    setAuthError('');
                  }}
                  style={[styles.authTab, !isLogin && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a' }]}
                >
                  <Text style={[styles.authTabText, { color: !isLogin ? '#fff' : colors.muted }]}>Sign up</Text>
                </Pressable>
              </View>

              {!!authError && (
                <View style={[styles.authError, { backgroundColor: colors.dangerBg }]}>
                  <Text style={[styles.authErrorText, { color: colors.dangerText }]}>{authError}</Text>
                </View>
              )}

              {!isLogin && (
                <View style={styles.nameRow}>
                  <View style={styles.flex}>
                    <AuthField
                      label="First name"
                      icon={<User size={16} color={isDark ? '#c4b5fd' : colors.iconMuted} />}
                      placeholder="Aanya"
                      value={firstName}
                      onChangeText={(v) => {
                        setFirstName(v);
                        if (authError) setAuthError('');
                      }}
                      autoCapitalize="words"
                      returnKeyType="next"
                    />
                  </View>
                  <View style={styles.flex}>
                    <AuthField
                      label="Last name"
                      icon={<User size={16} color={isDark ? '#c4b5fd' : colors.iconMuted} />}
                      placeholder="Sharma"
                      value={lastName}
                      onChangeText={(v) => {
                        setLastName(v);
                        if (authError) setAuthError('');
                      }}
                      autoCapitalize="words"
                      returnKeyType="next"
                    />
                  </View>
                </View>
              )}

              <AuthField
                label="Email"
                icon={<Mail size={16} color={isDark ? '#c4b5fd' : colors.iconMuted} />}
                placeholder="you@email.com"
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="email-address"
                textContentType="emailAddress"
                value={email}
                onChangeText={(v) => {
                  setEmail(v);
                  if (authError) setAuthError('');
                }}
                returnKeyType="next"
              />

              <AuthField
                label="Password"
                icon={<Lock size={16} color={isDark ? '#c4b5fd' : colors.iconMuted} />}
                placeholder={isLogin ? 'Your password' : 'Min. 8 characters'}
                secureTextEntry={!showPassword}
                textContentType="password"
                value={password}
                onChangeText={(v) => {
                  setPassword(v);
                  if (authError) setAuthError('');
                }}
                returnKeyType="done"
                onSubmitEditing={handleAuth}
                trailing={
                  <Pressable onPress={() => setShowPassword((v) => !v)} hitSlop={8} accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}>
                    {showPassword ? (
                      <EyeOff size={18} color={colors.iconMuted} />
                    ) : (
                      <Eye size={18} color={colors.iconMuted} />
                    )}
                  </Pressable>
                }
              />

              <Pressable style={styles.authCta} onPress={handleAuth} disabled={loading}>
                <LinearGradient colors={colors.cta} start={{ x: 0, y: 0 }} end={{ x: 1, y: 0 }} style={styles.authCtaGrad} pointerEvents="none">
                  {loading ? (
                    <ActivityIndicator color="#fff" />
                  ) : (
                    <Text style={styles.authCtaText}>{isLogin ? 'Sign in to LUXE' : 'Create my account'}</Text>
                  )}
                </LinearGradient>
              </Pressable>

              <View style={styles.perkRow}>
                {[
                  { icon: Package, label: 'Track orders' },
                  { icon: Gift, label: 'Earn points' },
                  { icon: Heart, label: 'Save wishlist' },
                ].map((perk) => (
                  <View key={perk.label} style={[styles.perk, { backgroundColor: colors.input, borderColor: colors.inputBorder }]}>
                    <perk.icon size={14} color={isDark ? '#c4b5fd' : colors.accent} />
                    <Text style={[styles.perkText, { color: colors.muted }]}>{perk.label}</Text>
                  </View>
                ))}
              </View>
            </ScrollView>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  safeArea: { flex: 1 },
  scrollContent: { padding: 16, paddingBottom: 110 },
  hero: { alignItems: 'center', paddingTop: 8, paddingBottom: 8 },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 24,
    backgroundColor: 'rgba(139,92,246,0.28)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  avatarText: { color: '#fff', fontSize: 28, fontWeight: '800' },
  userName: { color: '#fff', fontSize: 24, fontWeight: '800' },
  userEmail: { color: '#cbd5e1', fontSize: 14, marginTop: 4, textAlign: 'center' },
  memberSince: { color: '#94a3b8', fontSize: 12, marginTop: 4 },
  badge: {
    marginTop: 14,
    backgroundColor: 'rgba(251,191,36,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(251,191,36,0.4)',
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  badgeText: { color: '#fde68a', fontSize: 12, fontWeight: '800' },
  ctaBtn: { width: '100%', maxWidth: 260, borderRadius: 14, overflow: 'hidden', marginTop: 16 },
  ctaGrad: { height: 48, alignItems: 'center', justifyContent: 'center' },
  ctaText: { color: '#fff', fontSize: 14, fontWeight: '800' },
  rewardsBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    backgroundColor: 'rgba(251,191,36,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(251,191,36,0.35)',
    borderRadius: 16,
    padding: 14,
    marginTop: 8,
  },
  rewardsIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: 'rgba(251,191,36,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  rewardsPts: { color: '#fff', fontSize: 16, fontWeight: '800' },
  rewardsSub: { color: '#fde68a', fontSize: 12, marginTop: 3, lineHeight: 17 },
  spendRow: { flexDirection: 'row', gap: 10, marginTop: 12 },
  spendCard: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 14,
    padding: 12,
    gap: 6,
  },
  spendLabel: { color: '#cbd5e1', fontSize: 11, fontWeight: '700' },
  spendValue: { color: '#fff', fontSize: 16, fontWeight: '800' },
  activityGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 8 },
  activityCard: {
    width: '31.5%',
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 14,
    paddingVertical: 12,
    paddingHorizontal: 8,
    alignItems: 'center',
  },
  activityIcon: { width: 32, height: 32, borderRadius: 10, alignItems: 'center', justifyContent: 'center', marginBottom: 8 },
  activityValue: { color: '#fff', fontSize: 16, fontWeight: '800' },
  activityLabel: { color: '#cbd5e1', fontSize: 10, fontWeight: '700', marginTop: 2, textAlign: 'center' },
  infoCard: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 16,
    padding: 14,
    marginTop: 8,
    marginBottom: 4,
  },
  infoHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  infoTitle: { color: '#fff', fontSize: 15, fontWeight: '800' },
  infoEdit: { color: '#c4b5fd', fontSize: 13, fontWeight: '800' },
  infoRow: { paddingVertical: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)' },
  infoDt: { color: '#94a3b8', fontSize: 11, fontWeight: '700' },
  infoDd: { color: '#fff', fontSize: 14, fontWeight: '700', marginTop: 3 },
  sectionTitle: { color: '#e2e8f0', fontSize: 12, fontWeight: '800', letterSpacing: 0.5, marginBottom: 8, marginTop: 14 },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    paddingHorizontal: 12,
    marginBottom: 16,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 12 },
  rowBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)' },
  iconBox: { width: 38, height: 38, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  rowTitle: { color: '#fff', fontSize: 15, fontWeight: '700' },
  rowSub: { color: '#cbd5e1', fontSize: 12, marginTop: 2 },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    height: 48,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: 'rgba(248,113,113,0.35)',
  },
  logoutText: { color: '#fca5a5', fontSize: 14, fontWeight: '800' },
  version: { color: '#94a3b8', fontSize: 12, textAlign: 'center', marginTop: 18 },
  modalWrap: { flex: 1, justifyContent: 'flex-end' },
  modalBackdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.58)' },
  sheet: {
    backgroundColor: '#12121a',
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    paddingHorizontal: 20,
    paddingTop: 10,
    overflow: 'hidden',
    ...Platform.select({
      ios: {
        shadowOpacity: 0.18,
        shadowRadius: 24,
        shadowOffset: { width: 0, height: -8 },
      },
      android: { elevation: 18 },
      web: { boxShadow: '0 -12px 40px rgba(0,0,0,0.18)' } as object,
    }),
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 40,
    height: 4,
    borderRadius: 2,
    marginBottom: 12,
  },
  sheetContent: { paddingBottom: 10, gap: 0 },
  authTop: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 18,
  },
  authBrand: { flexDirection: 'row', alignItems: 'center' },
  authLogo: {
    fontSize: 20,
    fontWeight: '700',
    letterSpacing: 2.6,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
  authLogoDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
    marginLeft: 4,
  },
  authClose: {
    width: 36,
    height: 36,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  authKicker: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1.8,
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  authTitle: {
    fontSize: 26,
    fontWeight: '800',
    letterSpacing: -0.4,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
  authSub: {
    fontSize: 13,
    lineHeight: 19,
    marginTop: 6,
    marginBottom: 16,
  },
  authTabs: {
    flexDirection: 'row',
    borderRadius: 14,
    borderWidth: 1,
    padding: 4,
    marginBottom: 14,
  },
  authTab: {
    flex: 1,
    height: 38,
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
  },
  authTabText: { fontSize: 13, fontWeight: '800' },
  authError: {
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 12,
  },
  authErrorText: { fontSize: 13, fontWeight: '600', lineHeight: 18 },
  field: { marginBottom: 12 },
  fieldLabel: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.4,
    marginBottom: 6,
    textTransform: 'uppercase',
  },
  nameRow: { flexDirection: 'row', gap: 10 },
  flex: { flex: 1 },
  inputWrap: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    borderRadius: 14,
    paddingHorizontal: 14,
    minHeight: 50,
  },
  inputField: { flex: 1, color: '#fff', fontSize: 15, paddingVertical: 12 },
  authCta: {
    width: '100%',
    borderRadius: 16,
    overflow: 'hidden',
    marginTop: 6,
  },
  authCtaGrad: { height: 52, alignItems: 'center', justifyContent: 'center' },
  authCtaText: { color: '#fff', fontSize: 15, fontWeight: '800', letterSpacing: 0.2 },
  perkRow: { flexDirection: 'row', gap: 8, marginTop: 16 },
  perk: {
    flex: 1,
    alignItems: 'center',
    gap: 6,
    paddingVertical: 10,
    paddingHorizontal: 4,
    borderRadius: 12,
    borderWidth: 1,
  },
  perkText: { fontSize: 10, fontWeight: '700', textAlign: 'center' },
});
