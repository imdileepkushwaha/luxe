import React, { useState } from 'react';
import { StyleSheet, Text, View, ScrollView, TouchableOpacity, TextInput, Modal, ActivityIndicator, Alert, SafeAreaView, Platform, KeyboardAvoidingView } from 'react-native';
import { useRouter } from 'expo-router';
import { User, Settings, Package, Heart, LogOut, X, Mail, Lock, ChevronRight, UserPlus, Fingerprint, Search, ShoppingBag, ArrowRight, HelpCircle, Info } from 'lucide-react-native';
import Colors from '@/constants/Colors';
import Config from '@/constants/Config';
import { useColorScheme } from '@/components/useColorScheme';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import { useAuth } from '@/context/AuthContext';
import { LinearGradient } from 'expo-linear-gradient';
import { BlurView } from 'expo-blur';
import LuxeHeader from '@/components/LuxeHeader';

export default function ProfileScreen() {
  const colorScheme = useColorScheme();
  const colors = Colors[colorScheme ?? 'light'];
  const { user, login, logout } = useAuth();
  const router = useRouter();
  
  const [modalVisible, setModalVisible] = useState(false);
  const [isLogin, setIsLogin] = useState(true);
  const [loading, setLoading] = useState(false);
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');

  const handleAuth = async () => {
    if (!email || !password) return Alert.alert('Error', 'Please fill all fields');
    setLoading(true);
    try {
      const trimmedEmail = email.trim();
      const trimmedPassword = password.trim();
      const response = await fetch(`${Config.API_URL}/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: isLogin ? 'login' : 'register', email: trimmedEmail, password: trimmedPassword, first_name: firstName, last_name: lastName })
      });
      const data = await response.json();
      if (data.ok) {
        if (isLogin) { 
          await login(data.user); 
          setModalVisible(false); 
        } else { 
          Alert.alert('Success', 'Registration successful! Please login.'); 
          setIsLogin(true); 
        }
      } else { 
        Alert.alert('Error', data.error); 
      }
    } catch (error) { 
      Alert.alert('Error', 'Connection failed'); 
    } finally { 
      setLoading(false); 
    }
  };

  const menuSections = [
    {
      title: 'ACCOUNT',
      items: [
        { icon: Package, label: 'My Orders', color: '#8b5cf6', sub: 'View status & tracking', action: () => router.push('/orders') },
        { icon: Heart, label: 'Wishlist', color: '#ec4899', sub: 'Your saved favorites', action: () => router.push('/(tabs)/wishlist') },
      ]
    },
    {
      title: 'PREFERENCES',
      items: [
        { icon: Settings, label: 'Settings', color: '#64748b', sub: 'Account & privacy', action: () => router.push('/settings') },
      ]
    },
    {
      title: 'SUPPORT',
      items: [
        { icon: HelpCircle, label: 'Help Center', color: '#94a3b8', sub: 'FAQs & Support' },
        { icon: Info, label: 'About LUXE', color: '#94a3b8', sub: 'Version 2.4.0' },
      ]
    }
  ];

  return (
    <View style={styles.container}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea}>
        <LuxeHeader />

        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {/* Elite User Header */}
          <View style={styles.profileHeader}>
            <View style={styles.avatarContainer}>
              <LinearGradient colors={['#8b5cf6', '#ec4899']} style={styles.avatarGlow} />
              <View style={styles.avatarWrapper}>
                <View style={styles.avatarInner}>
                  <User size={50} color="#fff" />
                </View>
              </View>
              {user && (
                <View style={styles.memberBadge}>
                  <LinearGradient colors={['#f59e0b', '#d97706']} style={StyleSheet.absoluteFill} />
                  <Text style={styles.memberText}>DIAMOND</Text>
                </View>
              )}
            </View>
            
            <View style={styles.userTextContent}>
              <Text style={styles.userName}>
                {user ? `${user.first_name} ${user.last_name}` : 'Welcome to LUXE'}
              </Text>
              <Text style={styles.userEmail}>
                {user ? user.email : 'Join the elite curation'}
              </Text>
            </View>

            {!user ? (
              <TouchableOpacity activeOpacity={0.8} style={styles.mainLoginBtn} onPress={() => setModalVisible(true)}>
                <LinearGradient colors={['#8b5cf6', '#ec4899']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.loginGradient}>
                  <Text style={styles.loginText}>UNLOCK ELITE ACCESS</Text>
                  <ArrowRight size={18} color="#fff" />
                </LinearGradient>
              </TouchableOpacity>
            ) : (
              <View style={styles.statsRow}>
                <View style={styles.statItem}>
                  <Text style={styles.statValue}>12</Text>
                  <Text style={styles.statLabel}>Orders</Text>
                </View>
                <View style={styles.statDivider} />
                <View style={styles.statItem}>
                  <Text style={styles.statValue}>450</Text>
                  <Text style={styles.statLabel}>Luxe Points</Text>
                </View>
                <View style={styles.statDivider} />
                <View style={styles.statItem}>
                  <Text style={styles.statValue}>8</Text>
                  <Text style={styles.statLabel}>Wishlist</Text>
                </View>
              </View>
            )}
          </View>

          {/* Menu Sections */}
          <View style={styles.menuContainer}>
            {menuSections.map((section, sIndex) => (
              <View key={sIndex} style={styles.sectionWrapper}>
                <Text style={styles.sectionTitle}>{section.title}</Text>
                {section.items.map((item, index) => (
                  <TouchableOpacity key={index} activeOpacity={0.7} onPress={item.action}>
                    <GlassCard style={styles.menuCard} intensity={25} borderRadius={25}>
                      <View style={styles.menuItemContent}>
                        <View style={[styles.iconCircle, { backgroundColor: `${item.color}15` }]}>
                          <item.icon size={22} color={item.color} />
                        </View>
                        <View style={styles.menuTexts}>
                          <Text style={styles.menuLabel}>{item.label}</Text>
                          <Text style={styles.menuSubText}>{item.sub}</Text>
                        </View>
                        <ChevronRight size={18} color="rgba(255,255,255,0.2)" />
                      </View>
                    </GlassCard>
                  </TouchableOpacity>
                ))}
              </View>
            ))}

            {user && (
              <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
                <LinearGradient colors={['rgba(239, 68, 68, 0.1)', 'transparent']} style={styles.logoutGradient}>
                  <LogOut size={18} color="#ef4444" />
                  <Text style={styles.logoutText}>SIGN OUT OF SESSION</Text>
                </LinearGradient>
              </TouchableOpacity>
            )}
          </View>
          
          <Text style={styles.versionTag}>LUXE ELITE EDITION · v2.4.0</Text>
          <View style={{ height: 120 }} />
        </ScrollView>
      </SafeAreaView>

      {/* Auth Modal (Rest unchanged for functionality) */}
      <Modal visible={modalVisible} animationType="slide" transparent statusBarTranslucent>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} style={{ flex: 1 }}>
          <View style={styles.modalOverlay}>
            <TouchableOpacity style={styles.dismissArea} activeOpacity={1} onPress={() => setModalVisible(false)} />
            <View style={styles.bottomSheet}>
              <BlurView intensity={80} tint="dark" style={StyleSheet.absoluteFill} />
              <View style={styles.sheetHandle} />
              <View style={styles.sheetContent}>
                <View style={styles.authHeader}>
                  <View style={styles.authIconBox}>
                    {isLogin ? <Fingerprint size={32} color="#fff" /> : <UserPlus size={32} color="#fff" />}
                    <LinearGradient colors={['#8b5cf6', '#ec4899']} style={StyleSheet.absoluteFill} />
                  </View>
                  <Text style={styles.authTitle}>{isLogin ? 'Welcome Back' : 'Create Account'}</Text>
                  <Text style={styles.authSubtitle}>
                    {isLogin ? 'Sign in to access your elite curation' : 'Join the LUXE membership for exclusive perks'}
                  </Text>
                </View>
                <View style={styles.form}>
                  {!isLogin && (
                    <View style={styles.inputRow}>
                      <View style={[styles.inputBox, { flex: 1, marginRight: 10 }]}>
                        <TextInput placeholder="First Name" placeholderTextColor="#64748b" style={styles.premiumInputCompact} onChangeText={setFirstName} />
                      </View>
                      <View style={[styles.inputBox, { flex: 1 }]}>
                        <TextInput placeholder="Last Name" placeholderTextColor="#64748b" style={styles.premiumInputCompact} onChangeText={setLastName} />
                      </View>
                    </View>
                  )}
                  <View style={styles.inputBox}>
                    <Mail size={18} color="#8b5cf6" style={styles.fieldIcon} />
                    <TextInput 
                      placeholder="Email Address" placeholderTextColor="#64748b" style={styles.premiumInput} 
                      autoCapitalize="none" autoCorrect={false} keyboardType="email-address"
                      onChangeText={setEmail} value={email}
                    />
                  </View>
                  <View style={styles.inputBox}>
                    <Lock size={18} color="#8b5cf6" style={styles.fieldIcon} />
                    <TextInput placeholder="Password" placeholderTextColor="#64748b" style={styles.premiumInput} secureTextEntry onChangeText={setPassword} />
                  </View>
                  <TouchableOpacity style={styles.submitBtn} onPress={handleAuth} disabled={loading}>
                    <LinearGradient colors={['#8b5cf6', '#ec4899']} start={{x:0, y:0}} end={{x:1, y:0}} style={styles.submitGradient}>
                      {loading ? <ActivityIndicator color="#fff" /> : <Text style={styles.submitBtnText}>{isLogin ? 'CONTINUE' : 'GET STARTED'}</Text>}
                    </LinearGradient>
                  </TouchableOpacity>
                  <TouchableOpacity style={styles.switchLink} onPress={() => setIsLogin(!isLogin)}>
                    <Text style={styles.switchText}>
                      {isLogin ? "New to LUXE? " : "Already have an account? "}
                      <Text style={{ color: '#8b5cf6', fontWeight: '900' }}>{isLogin ? 'Sign Up' : 'Login'}</Text>
                    </Text>
                  </TouchableOpacity>
                </View>
                <View style={{ height: 40 }} />
              </View>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  scrollContent: { paddingBottom: 100 },
  
  // New Elite Header
  profileHeader: { alignItems: 'center', paddingTop: 30, paddingBottom: 40 },
  avatarContainer: { position: 'relative', width: 130, height: 130, justifyContent: 'center', alignItems: 'center' },
  avatarGlow: { position: 'absolute', width: 140, height: 140, borderRadius: 70, opacity: 0.3 },
  avatarWrapper: { width: 110, height: 110, borderRadius: 55, padding: 4, backgroundColor: 'rgba(255,255,255,0.05)', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
  avatarInner: { flex: 1, borderRadius: 55, backgroundColor: 'rgba(255,255,255,0.05)', justifyContent: 'center', alignItems: 'center', overflow: 'hidden' },
  memberBadge: { position: 'absolute', bottom: 5, backgroundColor: '#f59e0b', paddingHorizontal: 12, paddingVertical: 4, borderRadius: 12, overflow: 'hidden', borderWidth: 2, borderColor: '#000' },
  memberText: { color: '#000', fontSize: 9, fontWeight: '900', letterSpacing: 1 },
  
  userTextContent: { alignItems: 'center', marginTop: 20 },
  userName: { fontSize: 32, fontWeight: '900', color: '#fff', letterSpacing: -0.5, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  userEmail: { fontSize: 15, color: '#64748b', fontWeight: '500', marginTop: 4 },
  
  mainLoginBtn: { width: '75%', height: 65, borderRadius: 32, overflow: 'hidden', marginTop: 30, elevation: 20, shadowColor: '#8b5cf6', shadowOpacity: 0.5, shadowRadius: 20 },
  loginGradient: { flex: 1, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 15 },
  loginText: { color: '#fff', fontWeight: '900', fontSize: 14, letterSpacing: 1.5 },
  
  statsRow: { flexDirection: 'row', alignItems: 'center', backgroundColor: 'rgba(255,255,255,0.03)', paddingVertical: 20, paddingHorizontal: 30, borderRadius: 30, marginTop: 35, borderWidth: 1, borderColor: 'rgba(255,255,255,0.05)' },
  statItem: { alignItems: 'center', width: 70 },
  statValue: { color: '#fff', fontSize: 20, fontWeight: '900' },
  statLabel: { color: '#64748b', fontSize: 11, fontWeight: '700', marginTop: 4 },
  statDivider: { width: 1, height: 30, backgroundColor: 'rgba(255,255,255,0.05)' },

  menuContainer: { paddingHorizontal: 20 },
  sectionWrapper: { marginBottom: 30 },
  sectionTitle: { color: '#475569', fontSize: 11, fontWeight: '900', letterSpacing: 2, marginBottom: 15, paddingLeft: 10 },
  menuCard: { padding: 0, marginBottom: 12 },
  menuItemContent: { flexDirection: 'row', alignItems: 'center', padding: 18 },
  iconCircle: { width: 45, height: 45, borderRadius: 15, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
  menuTexts: { flex: 1 },
  menuLabel: { fontSize: 16, fontWeight: '700', color: '#fff' },
  menuSubText: { fontSize: 12, color: '#64748b', fontWeight: '500', marginTop: 2 },
  
  logoutBtn: { height: 60, borderRadius: 25, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.2)', marginTop: 10 },
  logoutGradient: { flex: 1, flexDirection: 'row', justifyContent: 'center', alignItems: 'center', gap: 12 },
  logoutText: { color: '#ef4444', fontWeight: '900', fontSize: 13, letterSpacing: 1.5 },
  
  versionTag: { color: '#1e293b', fontSize: 10, textAlign: 'center', marginTop: 40, fontWeight: '800', letterSpacing: 2 },

  // Auth Modal Styles (Kept as requested)
  modalOverlay: { flex: 1, backgroundColor: 'rgba(0,0,0,0.7)', justifyContent: 'flex-end' },
  dismissArea: { flex: 1 },
  bottomSheet: { borderTopLeftRadius: 45, borderTopRightRadius: 45, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(255,255,255,0.1)' },
  sheetHandle: { width: 40, height: 4, backgroundColor: 'rgba(255,255,255,0.2)', borderRadius: 2, alignSelf: 'center', marginTop: 15 },
  sheetContent: { padding: 30 },
  authHeader: { alignItems: 'center', marginBottom: 35 },
  authIconBox: { width: 64, height: 64, borderRadius: 22, overflow: 'hidden', justifyContent: 'center', alignItems: 'center', marginBottom: 20 },
  authTitle: { fontSize: 32, fontWeight: '800', color: '#fff', marginBottom: 8, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  authSubtitle: { fontSize: 14, color: '#94a3b8', textAlign: 'center', lineHeight: 22 },
  form: { gap: 15 },
  inputRow: { flexDirection: 'row' },
  inputBox: { position: 'relative' },
  fieldIcon: { position: 'absolute', left: 18, top: 18, zIndex: 1 },
  premiumInput: { backgroundColor: 'rgba(255, 255, 255, 0.05)', borderRadius: 20, padding: 18, paddingLeft: 52, color: '#fff', fontSize: 16, fontWeight: '600', borderWidth: 1, borderColor: 'rgba(255,255,255,0.08)' },
  premiumInputCompact: { backgroundColor: 'rgba(255, 255, 255, 0.05)', borderRadius: 20, padding: 18, color: '#fff', fontSize: 16, fontWeight: '600', borderWidth: 1, borderColor: 'rgba(255,255,255,0.08)' },
  submitBtn: { height: 60, borderRadius: 20, overflow: 'hidden', marginTop: 10 },
  submitGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  submitBtnText: { color: '#fff', fontWeight: '900', fontSize: 15, letterSpacing: 2 },
  switchLink: { alignItems: 'center', marginTop: 15 },
  switchText: { color: '#94a3b8', fontSize: 15 },
});

