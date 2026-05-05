import React, { useState } from 'react';
import { StyleSheet, Text, View, ScrollView, TouchableOpacity, SafeAreaView, Switch, Platform, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { ChevronLeft, User, Bell, Shield, Eye, Trash2, Info, ChevronRight, HelpCircle } from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import GlassCard from '@/components/GlassCard';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';

export default function SettingsScreen() {
  const router = useRouter();
  const { user, logout } = useAuth();
  
  const [notifications, setNotifications] = useState(true);
  const [darkMode, setDarkMode] = useState(true);

  const handleDeleteAccount = () => {
    Alert.alert(
      "Delete Account",
      "Are you sure you want to request account deletion? This action is permanent.",
      [
        { text: "Cancel", style: "cancel" },
        { text: "Request Deletion", style: "destructive", onPress: () => Alert.alert("Request Sent", "Your deletion request has been submitted.") }
      ]
    );
  };

  const SettingItem = ({ icon: Icon, label, value, type = 'chevron', onPress, color = '#fff' }: any) => (
    <TouchableOpacity activeOpacity={0.7} onPress={onPress} disabled={type === 'switch'}>
      <GlassCard intensity={15} borderRadius={20} style={styles.settingItem}>
        <View style={[styles.iconBox, { backgroundColor: `${color}15` }]}>
          <Icon size={20} color={color} />
        </View>
        <Text style={styles.settingLabel}>{label}</Text>
        {type === 'chevron' && <ChevronRight size={18} color="#475569" />}
        {type === 'switch' && (
          <Switch 
            value={value} 
            onValueChange={onPress}
            trackColor={{ false: '#1e293b', true: '#8b5cf6' }}
            thumbColor={Platform.OS === 'ios' ? '#fff' : value ? '#fff' : '#94a3b8'}
          />
        )}
        {type === 'text' && <Text style={styles.settingValue}>{value}</Text>}
      </GlassCard>
    </TouchableOpacity>
  );

  return (
    <View style={styles.container}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea}>
        <LuxeHeader showBack={true} />
        
        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          <View style={styles.pageHeader}>
            <Text style={styles.pageTitle}>SETTINGS</Text>
            <Text style={styles.pageSub}>Personalize your elite experience</Text>
          </View>

          {/* Account Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>ACCOUNT</Text>
            <SettingItem 
              icon={User} 
              label="Profile Details" 
              value={user ? `${user.first_name} ${user.last_name}` : 'Not logged in'}
              onPress={() => {}} 
              color="#8b5cf6"
            />
            <SettingItem 
              icon={Shield} 
              label="Privacy & Security" 
              onPress={() => {}} 
              color="#10b981"
            />
          </View>

          {/* Preferences Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>PREFERENCES</Text>
            <SettingItem 
              icon={Bell} 
              label="Push Notifications" 
              type="switch"
              value={notifications}
              onPress={() => setNotifications(!notifications)} 
              color="#f59e0b"
            />
            <SettingItem 
              icon={Eye} 
              label="Elite Dark Mode" 
              type="switch"
              value={darkMode}
              onPress={() => setDarkMode(!darkMode)} 
              color="#3b82f6"
            />
          </View>

          {/* Support Section */}
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>SUPPORT</Text>
            <SettingItem icon={HelpCircle} label="Help Center" onPress={() => {}} color="#94a3b8" />
            <SettingItem icon={Info} label="About LUXE" onPress={() => {}} color="#94a3b8" />
          </View>

          {/* Danger Zone */}
          <View style={styles.section}>
            <Text style={[styles.sectionTitle, { color: '#ef4444' }]}>DANGER ZONE</Text>
            <SettingItem 
              icon={Trash2} 
              label="Delete Account" 
              onPress={handleDeleteAccount} 
              color="#ef4444"
            />
          </View>

          {user && (
            <TouchableOpacity style={styles.logoutBtn} onPress={logout}>
              <LinearGradient colors={['rgba(239, 68, 68, 0.1)', 'transparent']} style={styles.logoutGradient}>
                <Text style={styles.logoutText}>SIGN OUT OF SESSION</Text>
              </LinearGradient>
            </TouchableOpacity>
          )}

          <Text style={styles.versionText}>LUXE v2.4.0 Elite Edition</Text>
          <View style={{ height: 100 }} />
        </ScrollView>
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#000' },
  safeArea: { flex: 1 },
  scrollContent: { padding: 25 },
  
  pageHeader: { marginBottom: 35, paddingHorizontal: 5 },
  pageTitle: { color: '#fff', fontSize: 24, fontWeight: '900', letterSpacing: 2, fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif' },
  pageSub: { color: '#94a3b8', fontSize: 13, fontWeight: '500', marginTop: 5 },

  section: { marginBottom: 30 },
  sectionTitle: { color: '#475569', fontSize: 11, fontWeight: '900', letterSpacing: 2, marginBottom: 15, paddingLeft: 5 },
  
  settingItem: { flexDirection: 'row', alignItems: 'center', padding: 15, marginBottom: 10 },
  iconBox: { width: 40, height: 40, borderRadius: 12, justifyContent: 'center', alignItems: 'center', marginRight: 15 },
  settingLabel: { flex: 1, color: '#fff', fontSize: 15, fontWeight: '600' },
  settingValue: { color: '#64748b', fontSize: 14, fontWeight: '500' },

  logoutBtn: { marginTop: 20, height: 60, borderRadius: 20, overflow: 'hidden', borderWidth: 1, borderColor: 'rgba(239, 68, 68, 0.2)' },
  logoutGradient: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  logoutText: { color: '#ef4444', fontWeight: '900', fontSize: 13, letterSpacing: 1.5 },
  
  versionText: { color: '#334155', fontSize: 11, textAlign: 'center', marginTop: 40, fontWeight: '700', letterSpacing: 1 },
});
