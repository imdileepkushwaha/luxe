import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TouchableOpacity,
  Pressable,
  Platform,
  Alert,
  Modal,
  TextInput,
  ActivityIndicator,
  KeyboardAvoidingView,
} from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  User,
  Lock,
  LogOut,
  Trash2,
  Mail,
  Phone,
  ChevronRight,
  Shield,
  Calendar,
  Pencil,
  Sun,
  Moon,
} from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

function AppearancePicker() {
  const { scheme, setScheme, colors, isDark } = useAppTheme();
  return (
    <>
      <Text style={[styles.sectionTitle, { color: colors.muted }]}>Appearance</Text>
      <View style={styles.themeRow}>
        <Pressable
          onPress={() => setScheme('dark')}
          style={[
            styles.themeCard,
            {
              backgroundColor: colors.card,
              borderColor: scheme === 'dark' ? (isDark ? '#a78bfa' : '#0f172a') : colors.border,
            },
          ]}
        >
          <View style={[styles.themeIcon, { backgroundColor: isDark ? 'rgba(139,92,246,0.2)' : 'rgba(15,23,42,0.06)' }]}>
            <Moon size={18} color={scheme === 'dark' ? (isDark ? '#c4b5fd' : '#0f172a') : colors.iconMuted} />
          </View>
          <Text style={[styles.themeTitle, { color: colors.text }]}>Dark</Text>
          <Text style={[styles.themeSub, { color: colors.muted }]}>Elite night look</Text>
          {scheme === 'dark' && <Text style={[styles.themeActive, { color: isDark ? '#c4b5fd' : '#0f172a' }]}>Selected</Text>}
        </Pressable>
        <Pressable
          onPress={() => setScheme('light')}
          style={[
            styles.themeCard,
            {
              backgroundColor: colors.card,
              borderColor: scheme === 'light' ? (isDark ? '#a78bfa' : '#0f172a') : colors.border,
            },
          ]}
        >
          <View style={[styles.themeIcon, { backgroundColor: isDark ? 'rgba(251,191,36,0.16)' : 'rgba(239,68,68,0.1)' }]}>
            <Sun size={18} color={scheme === 'light' ? (isDark ? '#fbbf24' : '#ef4444') : colors.iconMuted} />
          </View>
          <Text style={[styles.themeTitle, { color: colors.text }]}>Light</Text>
          <Text style={[styles.themeSub, { color: colors.muted }]}>Matches the website</Text>
          {scheme === 'light' && <Text style={[styles.themeActive, { color: isDark ? '#c4b5fd' : '#0f172a' }]}>Selected</Text>}
        </Pressable>
      </View>
    </>
  );
}

type ModalKind = 'password' | 'delete' | null;

type AccountProfile = {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  gender: string;
  dob: string;
  dob_iso: string;
  member_since: string;
};

const GENDERS = [
  { id: 'male', label: 'Male' },
  { id: 'female', label: 'Female' },
  { id: 'other', label: 'Other' },
] as const;

function AccountField({
  icon: Icon,
  color,
  label,
  value,
}: {
  icon: React.ComponentType<{ size?: number; color?: string }>;
  color: string;
  label: string;
  value: string;
}) {
  const { colors } = useAppTheme();
  return (
    <View style={[styles.fieldRow, { backgroundColor: colors.cardMuted, borderColor: colors.border }]}>
      <View style={[styles.fieldIcon, { backgroundColor: `${color}22` }]}>
        <Icon size={16} color={color} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.fieldLabel, { color: colors.muted }]}>{label}</Text>
        <Text style={[styles.fieldValue, { color: colors.text }]} numberOfLines={1}>
          {value || 'Not added'}
        </Text>
      </View>
    </View>
  );
}

export default function SettingsScreen() {
  const router = useRouter();
  const params = useLocalSearchParams<{ edit?: string }>();
  const { user, logout, updateUser, isLoading: authLoading } = useAuth();
  const { scheme, setScheme, colors, isDark } = useAppTheme();

  const [deletionPending, setDeletionPending] = useState(false);
  const [profile, setProfile] = useState<AccountProfile | null>(null);
  const [modal, setModal] = useState<ModalKind>(null);
  const [deleteStep, setDeleteStep] = useState(1);
  const [submitting, setSubmitting] = useState(false);

  const [editing, setEditing] = useState(false);
  const [editForm, setEditForm] = useState({
    first_name: '',
    last_name: '',
    phone: '',
    dob: '',
    gender: '',
  });
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');

  const firstName = profile?.first_name || user?.first_name || '';
  const lastName = profile?.last_name || user?.last_name || '';
  const fullName = [firstName, lastName].filter(Boolean).join(' ') || 'Member';
  const email = profile?.email || user?.email || '';
  const phone = profile?.phone || user?.phone || '';
  const initial = fullName.charAt(0).toUpperCase();

  const loadProfile = useCallback(async () => {
    if (!user?.id) {
      setDeletionPending(false);
      setProfile(null);
      return;
    }
    try {
      const res = await fetch(`${Config.API_URL}/mobile_settings.php?user_id=${user.id}`);
      const data = await res.json();
      if (data.ok) {
        setDeletionPending(!!data.deletion_pending);
        setProfile({
          first_name: String(data.user?.first_name || user.first_name || ''),
          last_name: String(data.user?.last_name || user.last_name || ''),
          email: String(data.user?.email || user.email || ''),
          phone: String(data.user?.phone || user.phone || ''),
          gender: String(data.user?.gender || ''),
          dob: String(data.user?.dob || ''),
          dob_iso: String(data.user?.dob_iso || ''),
          member_since: String(data.user?.member_since || ''),
        });
      }
    } catch {
      /* keep local state */
    }
  }, [user?.id, user?.first_name, user?.last_name, user?.email, user?.phone]);

  useEffect(() => {
    if (!authLoading) loadProfile();
  }, [authLoading, loadProfile]);

  const autoEditDone = useRef(false);
  useEffect(() => {
    if (autoEditDone.current || params.edit !== '1' || !profile) return;
    autoEditDone.current = true;
    setEditForm({
      first_name: profile.first_name,
      last_name: profile.last_name,
      phone: profile.phone,
      dob: profile.dob_iso,
      gender: (profile.gender || '').toLowerCase(),
    });
    setEditing(true);
  }, [params.edit, profile]);

  const startEdit = () => {
    setEditForm({
      first_name: firstName,
      last_name: lastName,
      phone,
      dob: profile?.dob_iso || '',
      gender: (profile?.gender || '').toLowerCase(),
    });
    setEditing(true);
  };

  const cancelEdit = () => setEditing(false);

  const submitProfile = async () => {
    if (!user?.id) return;
    if (!editForm.first_name.trim() || !editForm.last_name.trim()) {
      notify('Required', 'First name and last name are required.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_settings.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update_profile',
          user_id: Number(user.id),
          first_name: editForm.first_name.trim(),
          last_name: editForm.last_name.trim(),
          phone: editForm.phone.trim(),
          dob: editForm.dob.trim(),
          gender: editForm.gender,
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not save profile');
        return;
      }
      const saved = data.user || {};
      setProfile({
        first_name: String(saved.first_name || editForm.first_name.trim()),
        last_name: String(saved.last_name || editForm.last_name.trim()),
        email: String(saved.email || email),
        phone: String(saved.phone || editForm.phone.trim()),
        gender: String(saved.gender || editForm.gender),
        dob: String(saved.dob || ''),
        dob_iso: String(saved.dob_iso || editForm.dob.trim()),
        member_since: String(saved.member_since || profile?.member_since || ''),
      });
      await updateUser({
        first_name: saved.first_name || editForm.first_name.trim(),
        last_name: saved.last_name || editForm.last_name.trim(),
        phone: saved.phone || editForm.phone.trim(),
      });
      setEditing(false);
      notify('Done', data.message || 'Profile updated.');
    } catch {
      notify('Error', 'Something went wrong. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const closeModal = () => {
    setModal(null);
    setDeleteStep(1);
    setCurrentPassword('');
    setNewPassword('');
    setConfirmPassword('');
  };

  const submitPassword = async () => {
    if (!user?.id) return;
    if (!currentPassword || !newPassword || !confirmPassword) {
      notify('Required', 'Please fill all password fields.');
      return;
    }
    if (newPassword !== confirmPassword) {
      notify('Error', 'New passwords do not match.');
      return;
    }
    if (newPassword.length < 8) {
      notify('Error', 'New password must be at least 8 characters.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_settings.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'change_password',
          user_id: Number(user.id),
          current_password: currentPassword,
          new_password: newPassword,
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not update password');
        return;
      }
      notify('Done', data.message || 'Password updated successfully.');
      closeModal();
    } catch {
      notify('Error', 'Something went wrong. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const submitDelete = async () => {
    if (!user?.id) return;
    setSubmitting(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_settings.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'delete_account',
          user_id: Number(user.id),
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not submit request');
        return;
      }
      notify('Request submitted', data.message || 'Your account will be removed within 48 hours.');
      closeModal();
      await logout();
      router.replace('/(tabs)/profile');
    } catch {
      notify('Error', 'Something went wrong. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    router.replace('/(tabs)/profile');
  };

  if (authLoading) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Settings" />
          <View style={styles.centered}>
            <ActivityIndicator color={isDark ? '#c4b5fd' : colors.icon} />
          </View>
        </SafeAreaView>
      </View>
    );
  }

  if (!user) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Settings" />
          <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
            <AppearancePicker />
            <View style={styles.centered}>
              <View style={[styles.emptyIconBox, { backgroundColor: colors.primarySoft }]}>
                <Shield size={28} color={isDark ? '#c4b5fd' : colors.icon} />
              </View>
              <Text style={[styles.emptyTitle, { color: colors.text }]}>Sign in to manage settings</Text>
              <Text style={[styles.emptySub, { color: colors.textSecondary }]}>
                Password, sessions, and account privacy are available after login.
              </Text>
              <Pressable style={styles.ctaBtn} onPress={() => router.push('/(tabs)/profile')}>
                <LinearGradient
                  colors={colors.cta}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 0 }}
                  style={styles.ctaGrad}
                  pointerEvents="none"
                >
                  <Text style={styles.ctaText}>Go to profile</Text>
                </LinearGradient>
              </Pressable>
            </View>
          </ScrollView>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title="Settings" />
        <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.scrollContent}>
          {deletionPending && (
            <View style={styles.warnBanner}>
              <Text style={styles.warnTitle}>Deletion requested</Text>
              <Text style={styles.warnText}>Your account is scheduled for removal within 48 hours.</Text>
            </View>
          )}

          <Text style={[styles.sectionTitle, { color: colors.muted }]}>Account</Text>
          <View style={[styles.accountCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <LinearGradient
              colors={
                isDark
                  ? ['rgba(139,92,246,0.42)', 'rgba(219,39,119,0.22)', 'rgba(8,8,14,0.2)']
                  : ['#0f172a', '#1e293b', '#334155']
              }
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 1 }}
              style={styles.accountHero}
            >
              <View style={styles.avatarLg}>
                <Text style={styles.avatarLgText}>{initial}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.accountName}>{fullName}</Text>
                <Text style={styles.accountEmail} numberOfLines={1}>
                  {email || 'No email on file'}
                </Text>
                {!!profile?.member_since && (
                  <View style={styles.memberChip}>
                    <Text style={styles.memberChipText}>{profile.member_since}</Text>
                  </View>
                )}
              </View>
              <TouchableOpacity
                style={styles.heroEditBtn}
                onPress={editing ? cancelEdit : startEdit}
                activeOpacity={0.85}
              >
                <Pencil size={13} color="#fff" />
                <Text style={styles.heroEditText}>{editing ? 'Cancel' : 'Edit'}</Text>
              </TouchableOpacity>
            </LinearGradient>

            <View style={styles.accountBody}>
              {editing ? (
                <>
                  <View style={styles.fieldGrid}>
                    <View style={styles.fieldHalf}>
                      <Text style={[styles.inputLabel, { color: colors.muted }]}>First name</Text>
                      <TextInput
                        style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                        value={editForm.first_name}
                        onChangeText={(v) => setEditForm((f) => ({ ...f, first_name: v }))}
                        placeholder="First name"
                        placeholderTextColor="#94a3b8"
                      />
                    </View>
                    <View style={styles.fieldHalf}>
                      <Text style={[styles.inputLabel, { color: colors.muted }]}>Last name</Text>
                      <TextInput
                        style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                        value={editForm.last_name}
                        onChangeText={(v) => setEditForm((f) => ({ ...f, last_name: v }))}
                        placeholder="Last name"
                        placeholderTextColor="#94a3b8"
                      />
                    </View>
                  </View>
                  <Text style={[styles.inputLabel, { color: colors.muted }]}>Email address</Text>
                  <View style={[styles.readonlyField, { backgroundColor: colors.cardMuted, borderColor: colors.border }]}>
                    <Text style={[styles.readonlyText, { color: colors.text }]}>{email || '—'}</Text>
                    <Text style={[styles.readonlyHint, { color: colors.muted }]}>Used for login · cannot be changed here</Text>
                  </View>
                  <Text style={[styles.inputLabel, { color: colors.muted }]}>Mobile number</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                    value={editForm.phone}
                    onChangeText={(v) => setEditForm((f) => ({ ...f, phone: v }))}
                    placeholder="10-digit mobile number"
                    placeholderTextColor="#94a3b8"
                    keyboardType="phone-pad"
                  />
                  <Text style={[styles.inputLabel, { color: colors.muted }]}>Date of birth</Text>
                  <TextInput
                    style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                    value={editForm.dob}
                    onChangeText={(v) => setEditForm((f) => ({ ...f, dob: v }))}
                    placeholder="YYYY-MM-DD"
                    placeholderTextColor="#94a3b8"
                  />
                  <Text style={[styles.inputLabel, { color: colors.muted }]}>Gender</Text>
                  <View style={styles.genderRow}>
                    {GENDERS.map((g) => {
                      const on = editForm.gender === g.id;
                      return (
                        <Pressable
                          key={g.id}
                          style={[
                            styles.genderChip,
                            { borderColor: colors.border, backgroundColor: colors.input },
                            on && { backgroundColor: isDark ? 'rgba(139,92,246,0.28)' : '#0f172a', borderColor: isDark ? 'rgba(196,181,253,0.7)' : '#0f172a' },
                          ]}
                          onPress={() => setEditForm((f) => ({ ...f, gender: on ? '' : g.id }))}
                        >
                          <Text style={[styles.genderChipText, { color: on ? '#fff' : colors.text }]}>{g.label}</Text>
                        </Pressable>
                      );
                    })}
                  </View>
                  <Pressable style={styles.saveProfileBtn} onPress={submitProfile} disabled={submitting}>
                    {submitting ? (
                      <ActivityIndicator color="#fff" />
                    ) : (
                      <Text style={styles.saveBtnText}>Save changes</Text>
                    )}
                  </Pressable>
                </>
              ) : (
                <>
                  <View style={styles.fieldGrid}>
                    <View style={styles.fieldHalf}>
                      <AccountField icon={User} color="#a78bfa" label="First name" value={firstName} />
                    </View>
                    <View style={styles.fieldHalf}>
                      <AccountField icon={User} color="#c4b5fd" label="Last name" value={lastName} />
                    </View>
                  </View>
                  <AccountField icon={Mail} color="#38bdf8" label="Email address" value={email} />
                  <AccountField icon={Phone} color="#34d399" label="Mobile number" value={phone} />
                  <View style={styles.fieldGrid}>
                    <View style={styles.fieldHalf}>
                      <AccountField
                        icon={User}
                        color="#f472b6"
                        label="Gender"
                        value={profile?.gender ? profile.gender.charAt(0).toUpperCase() + profile.gender.slice(1) : ''}
                      />
                    </View>
                    <View style={styles.fieldHalf}>
                      <AccountField icon={Calendar} color="#fbbf24" label="Date of birth" value={profile?.dob || ''} />
                    </View>
                  </View>
                </>
              )}
            </View>
          </View>

          <AppearancePicker />

          <Text style={[styles.sectionTitle, { color: colors.muted }]}>Security & access</Text>
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <TouchableOpacity style={styles.row} onPress={() => setModal('password')}>
              <View style={[styles.iconBox, { backgroundColor: colors.primarySoft }]}>
                <Lock size={16} color={isDark ? '#c4b5fd' : colors.icon} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.rowTitle, { color: colors.text }]}>Change password</Text>
                <Text style={[styles.rowSub, { color: colors.muted }]}>Keep your account secure with a strong password.</Text>
              </View>
              <ChevronRight size={18} color={colors.iconMuted} />
            </TouchableOpacity>
            <TouchableOpacity style={[styles.row, styles.rowBorder, { borderTopColor: colors.hairline }]} onPress={handleLogout}>
              <View style={[styles.iconBox, { backgroundColor: colors.primarySoft }]}>
                <LogOut size={16} color={isDark ? '#c4b5fd' : colors.icon} />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.rowTitle, { color: colors.text }]}>Sign out</Text>
                <Text style={[styles.rowSub, { color: colors.muted }]}>Currently logged in on this device.</Text>
              </View>
            </TouchableOpacity>
          </View>

          <Text style={[styles.sectionTitle, { color: colors.dangerText }]}>Danger zone</Text>
          <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
            <TouchableOpacity
              style={styles.row}
              onPress={() => {
                setDeleteStep(1);
                setModal('delete');
              }}
              disabled={deletionPending}
            >
              <View style={[styles.iconBox, styles.iconDanger]}>
                <Trash2 size={16} color="#fca5a5" />
              </View>
              <View style={{ flex: 1 }}>
                <Text style={[styles.rowTitle, { color: '#fca5a5' }]}>Delete account</Text>
                <Text style={[styles.rowSub, { color: colors.muted }]}>
                  {deletionPending
                    ? 'A deletion request is already pending.'
                    : 'Permanently remove your data. This cannot be undone.'}
                </Text>
              </View>
              {!deletionPending && <ChevronRight size={18} color="#fca5a5" />}
            </TouchableOpacity>
          </View>

          <Text style={[styles.versionText, { color: colors.muted }]}>LUXE  ·  Version 1.0.0</Text>
        </ScrollView>
      </SafeAreaView>

      <Modal visible={modal !== null} transparent animationType="fade" onRequestClose={closeModal}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.modalWrap}>
          <Pressable style={styles.modalBackdrop} onPress={closeModal} />
          <View style={[styles.modalCard, { backgroundColor: colors.modal, borderColor: colors.border }]}>
            {modal === 'password' ? (
              <>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Change password</Text>
                <Text style={[styles.modalSub, { color: colors.textSecondary }]}>Choose a secure password for your LUXE account.</Text>
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Current password"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  value={currentPassword}
                  onChangeText={setCurrentPassword}
                />
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="New password (min 8 characters)"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  value={newPassword}
                  onChangeText={setNewPassword}
                />
                <TextInput
                  style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Confirm new password"
                  placeholderTextColor="#94a3b8"
                  secureTextEntry
                  value={confirmPassword}
                  onChangeText={setConfirmPassword}
                />
                <View style={styles.modalActions}>
                  <TouchableOpacity onPress={closeModal}>
                    <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Cancel</Text>
                  </TouchableOpacity>
                  <Pressable style={styles.saveBtn} onPress={submitPassword} disabled={submitting}>
                    {submitting ? <ActivityIndicator color="#fff" /> : <Text style={styles.saveBtnText}>Update password</Text>}
                  </Pressable>
                </View>
              </>
            ) : (
              <>
                <Text style={[styles.modalTitle, { color: colors.text }]}>Delete account</Text>
                {deleteStep === 1 ? (
                  <>
                    <Text style={[styles.modalSub, { color: colors.textSecondary }]}>We're sorry to see you go. This action is permanent.</Text>
                    <View style={styles.warnBox}>
                      <Text style={styles.warnBoxTitle}>This will remove</Text>
                      <Text style={[styles.warnBoxItem, { color: colors.textSecondary }]}>• Order history</Text>
                      <Text style={[styles.warnBoxItem, { color: colors.textSecondary }]}>• Saved addresses and preferences</Text>
                      <Text style={[styles.warnBoxItem, { color: colors.textSecondary }]}>• Wishlist and loyalty points</Text>
                      <Text style={[styles.warnBoxItem, { color: colors.textSecondary }]}>• Reviews and ratings</Text>
                    </View>
                    <View style={styles.modalActions}>
                      <TouchableOpacity onPress={closeModal}>
                        <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Cancel</Text>
                      </TouchableOpacity>
                      <Pressable style={styles.saveBtn} onPress={() => setDeleteStep(2)}>
                        <Text style={styles.saveBtnText}>Continue</Text>
                      </Pressable>
                    </View>
                  </>
                ) : (
                  <>
                    <Text style={[styles.modalSub, { color: colors.textSecondary }]}>
                      Submit a deletion request. Your account will be deactivated and scheduled for permanent removal within 48 hours.
                    </Text>
                    <View style={styles.modalActions}>
                      <TouchableOpacity onPress={() => setDeleteStep(1)}>
                        <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Back</Text>
                      </TouchableOpacity>
                      <Pressable style={[styles.saveBtn, styles.deleteBtn]} onPress={submitDelete} disabled={submitting}>
                        {submitting ? (
                          <ActivityIndicator color="#fff" />
                        ) : (
                          <Text style={styles.saveBtnText}>Delete my account</Text>
                        )}
                      </Pressable>
                    </View>
                  </>
                )}
              </>
            )}
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  safeArea: { flex: 1 },
  centered: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  scrollContent: { padding: 16, paddingBottom: 40 },

  sectionTitle: {
    color: '#e2e8f0',
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.6,
    marginBottom: 8,
    marginTop: 6,
  },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    padding: 14,
    marginBottom: 16,
  },
  accountCard: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    overflow: 'hidden',
    marginBottom: 18,
  },
  accountHero: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    paddingHorizontal: 16,
    paddingVertical: 18,
  },
  heroEditBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: 'rgba(255,255,255,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.28)',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  heroEditText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  avatarLg: {
    width: 64,
    height: 64,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.28)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarLgText: { color: '#fff', fontSize: 26, fontWeight: '800' },
  accountName: { color: '#fff', fontSize: 20, fontWeight: '800' },
  accountEmail: { color: '#e9d5ff', fontSize: 13, marginTop: 3 },
  memberChip: {
    alignSelf: 'flex-start',
    marginTop: 8,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  memberChipText: { color: '#f5f3ff', fontSize: 11, fontWeight: '700' },
  accountBody: { padding: 12, gap: 8 },
  fieldGrid: { flexDirection: 'row', gap: 8 },
  fieldHalf: { flex: 1, minWidth: 0 },
  fieldRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: 'rgba(8,8,14,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    borderRadius: 14,
    paddingVertical: 10,
    paddingHorizontal: 10,
  },
  fieldIcon: {
    width: 34,
    height: 34,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  fieldLabel: { color: '#94a3b8', fontSize: 11, fontWeight: '700', letterSpacing: 0.2 },
  fieldValue: { fontSize: 14, fontWeight: '700', marginTop: 2 },
  themeRow: { flexDirection: 'row', gap: 10, marginBottom: 18 },
  themeCard: {
    flex: 1,
    borderWidth: 1.5,
    borderRadius: 16,
    padding: 14,
    gap: 6,
  },
  themeIcon: {
    width: 36,
    height: 36,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 4,
  },
  themeTitle: { fontSize: 15, fontWeight: '800' },
  themeSub: { fontSize: 11, fontWeight: '600', lineHeight: 15 },
  themeActive: { fontSize: 11, fontWeight: '800', marginTop: 4 },
  inputLabel: { color: '#94a3b8', fontSize: 11, fontWeight: '700', marginBottom: 6, marginTop: 2 },
  readonlyField: {
    backgroundColor: 'rgba(8,8,14,0.35)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
  readonlyText: { color: '#e2e8f0', fontSize: 14, fontWeight: '700' },
  readonlyHint: { color: '#94a3b8', fontSize: 11, marginTop: 3 },
  genderRow: { flexDirection: 'row', gap: 8 },
  genderChip: {
    flex: 1,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.16)',
    backgroundColor: 'rgba(255,255,255,0.05)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  genderChipOn: {
    backgroundColor: 'rgba(139,92,246,0.28)',
    borderColor: 'rgba(196,181,253,0.7)',
  },
  genderChipText: { color: '#cbd5e1', fontSize: 13, fontWeight: '700' },
  genderChipTextOn: { color: '#fff' },
  saveProfileBtn: {
    backgroundColor: '#7c3aed',
    borderRadius: 12,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 6,
  },

  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 6 },
  rowBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)', marginTop: 10, paddingTop: 14 },
  iconBox: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: 'rgba(139,92,246,0.18)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconDanger: { backgroundColor: 'rgba(248,113,113,0.16)' },
  rowTitle: { color: '#fff', fontSize: 15, fontWeight: '700' },
  rowSub: { color: '#cbd5e1', fontSize: 12, marginTop: 3, lineHeight: 17 },

  warnBanner: {
    backgroundColor: 'rgba(251,191,36,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(251,191,36,0.4)',
    borderRadius: 14,
    padding: 14,
    marginBottom: 16,
  },
  warnTitle: { color: '#fbbf24', fontSize: 14, fontWeight: '800' },
  warnText: { color: '#fde68a', fontSize: 13, marginTop: 4, lineHeight: 18 },

  emptyIconBox: {
    width: 64,
    height: 64,
    borderRadius: 20,
    backgroundColor: 'rgba(139,92,246,0.16)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  emptyTitle: { color: '#fff', fontSize: 18, fontWeight: '800', textAlign: 'center' },
  emptySub: { color: '#e2e8f0', fontSize: 14, textAlign: 'center', lineHeight: 20, marginBottom: 12 },
  ctaBtn: { width: '100%', maxWidth: 240, borderRadius: 14, overflow: 'hidden', marginTop: 8 },
  ctaGrad: { height: 48, alignItems: 'center', justifyContent: 'center' },
  ctaText: { color: '#fff', fontSize: 14, fontWeight: '800' },
  link: { color: '#e9d5ff', fontSize: 13, fontWeight: '700' },
  versionText: { color: '#94a3b8', fontSize: 12, textAlign: 'center', marginTop: 8, fontWeight: '600' },

  modalWrap: { flex: 1, justifyContent: 'flex-end' },
  modalBackdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.55)' },
  modalCard: {
    backgroundColor: '#12121a',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    padding: 18,
    paddingBottom: Platform.OS === 'ios' ? 28 : 18,
    gap: 10,
  },
  modalTitle: { color: '#fff', fontSize: 18, fontWeight: '800' },
  modalSub: { color: '#e2e8f0', fontSize: 13, lineHeight: 19 },
  input: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 11,
    color: '#fff',
    fontSize: 14,
  },
  modalActions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 16, marginTop: 6 },
  saveBtn: {
    backgroundColor: '#7c3aed',
    borderRadius: 12,
    paddingHorizontal: 16,
    height: 42,
    minWidth: 140,
    alignItems: 'center',
    justifyContent: 'center',
  },
  deleteBtn: { backgroundColor: '#dc2626' },
  saveBtnText: { color: '#fff', fontSize: 13, fontWeight: '800' },
  warnBox: {
    backgroundColor: 'rgba(248,113,113,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(248,113,113,0.35)',
    borderRadius: 12,
    padding: 12,
    gap: 4,
  },
  warnBoxTitle: { color: '#fca5a5', fontSize: 13, fontWeight: '800', marginBottom: 4 },
  warnBoxItem: { color: '#e2e8f0', fontSize: 13, lineHeight: 20 },
});
