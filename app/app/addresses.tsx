import React, { useCallback, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  ActivityIndicator,
  TouchableOpacity,
  TextInput,
  Pressable,
  Platform,
  Alert,
} from 'react-native';
import { useFocusEffect } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { MapPin, Plus } from 'lucide-react-native';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import { fetchAddresses, formatAddressLines, type UserAddress } from '@/lib/api';

const TYPES = ['Home', 'Work', 'Other'] as const;

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

export default function AddressesScreen() {
  const { user } = useAuth();
  const { colors, isDark } = useAppTheme();
  const [list, setList] = useState<UserAddress[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState({
    name: '',
    phone: '',
    line1: '',
    line2: '',
    city: '',
    state: '',
    pin: '',
    type: 'Home',
  });

  const load = useCallback(async () => {
    if (!user?.id) {
      setLoading(false);
      return;
    }
    try {
      setList(await fetchAddresses(Number(user.id)));
    } catch {
      setList([]);
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const openAdd = () => {
    setEditingId(null);
    setForm({
      name: [user?.first_name, user?.last_name].filter(Boolean).join(' '),
      phone: user?.phone || '',
      line1: '',
      line2: '',
      city: '',
      state: '',
      pin: '',
      type: 'Home',
    });
    setShowForm(true);
  };

  const openEdit = (a: UserAddress) => {
    setEditingId(a.id);
    setForm({
      name: a.name,
      phone: a.phone,
      line1: a.line1,
      line2: a.line2,
      city: a.city,
      state: a.state,
      pin: a.pin,
      type: TYPES.includes(a.type as (typeof TYPES)[number]) ? a.type : 'Home',
    });
    setShowForm(true);
  };

  const save = async () => {
    if (!user?.id) return;
    if (!form.name.trim() || !form.line1.trim() || !form.city.trim() || !form.state.trim() || !form.pin.trim()) {
      notify('Required', 'Name, address, city, state, and PIN are required.');
      return;
    }
    setSaving(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_addresses.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save',
          user_id: Number(user.id),
          id: editingId || 0,
          ...form,
          is_default: list.length === 0,
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not save address');
        return;
      }
      setList(data.addresses || []);
      setShowForm(false);
    } catch {
      notify('Error', 'Could not save address');
    } finally {
      setSaving(false);
    }
  };

  if (!user) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Address" />
          <Text style={[styles.empty, { color: colors.text }]}>Sign in to manage addresses.</Text>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title="Address" />
        {loading ? (
          <ActivityIndicator color="#c4b5fd" style={{ marginTop: 40 }} />
        ) : (
          <ScrollView contentContainerStyle={styles.scroll}>
            <TouchableOpacity style={styles.addBtn} onPress={openAdd}>
              <Plus size={16} color={isDark ? '#e9d5ff' : colors.accent} />
              <Text style={[styles.addText, { color: isDark ? '#e9d5ff' : colors.accent }]}>Add new address</Text>
            </TouchableOpacity>
            {list.length === 0 && !showForm && <Text style={[styles.empty, { color: colors.text }]}>No saved addresses yet.</Text>}
            {list.map((a) => (
              <View key={a.id} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <View style={styles.head}>
                  <MapPin size={16} color={isDark ? '#c4b5fd' : colors.accent} />
                  <Text style={[styles.name, { color: colors.text }]}>{a.name}</Text>
                  <Text style={[styles.type, { color: isDark ? '#c4b5fd' : colors.accent }]}>{a.type}</Text>
                  {a.isDefault ? <Text style={styles.defaultBadge}>Default</Text> : null}
                </View>
                <Text style={[styles.lines, { color: colors.textSecondary }]}>{formatAddressLines(a)}</Text>
                {!!a.phone && <Text style={[styles.phone, { color: colors.muted }]}>{a.phone}</Text>}
                <TouchableOpacity onPress={() => openEdit(a)}>
                  <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Edit</Text>
                </TouchableOpacity>
              </View>
            ))}

            {showForm && (
              <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                <Text style={[styles.formTitle, { color: colors.text }]}>{editingId ? 'Edit address' : 'New address'}</Text>
                <View style={styles.typeRow}>
                  {TYPES.map((t) => (
                    <TouchableOpacity
                      key={t}
                      style={[
                        styles.chip,
                        { borderColor: colors.border },
                        form.type === t && { backgroundColor: isDark ? 'rgba(139,92,246,0.3)' : '#0f172a', borderColor: isDark ? '#c4b5fd' : '#0f172a' },
                      ]}
                      onPress={() => setForm((f) => ({ ...f, type: t }))}
                    >
                      <Text style={[styles.chipText, { color: form.type === t ? '#fff' : colors.text }]}>{t}</Text>
                    </TouchableOpacity>
                  ))}
                </View>
                {(['name', 'phone', 'line1', 'line2', 'city', 'state', 'pin'] as const).map((key) => (
                  <TextInput
                    key={key}
                    style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                    placeholder={key === 'line1' ? 'Address line 1' : key === 'line2' ? 'Address line 2 (optional)' : key[0].toUpperCase() + key.slice(1)}
                    placeholderTextColor={colors.placeholder}
                    value={form[key]}
                    onChangeText={(v) => setForm((f) => ({ ...f, [key]: v }))}
                  />
                ))}
                <View style={styles.actions}>
                  <TouchableOpacity onPress={() => setShowForm(false)}>
                    <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Cancel</Text>
                  </TouchableOpacity>
                  <Pressable style={styles.save} onPress={save} disabled={saving}>
                    {saving ? <ActivityIndicator color="#fff" /> : <Text style={styles.saveText}>Save address</Text>}
                  </Pressable>
                </View>
              </View>
            )}
          </ScrollView>
        )}
      </SafeAreaView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  safeArea: { flex: 1 },
  scroll: { padding: 16, paddingBottom: 40 },
  addBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 12 },
  addText: { color: '#e9d5ff', fontWeight: '700' },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 16,
    padding: 14,
    marginBottom: 12,
    gap: 8,
  },
  head: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  name: { color: '#fff', fontSize: 16, fontWeight: '800' },
  type: { color: '#c4b5fd', fontSize: 11, fontWeight: '800' },
  defaultBadge: { color: '#34d399', fontSize: 11, fontWeight: '800' },
  lines: { color: '#e2e8f0', fontSize: 13, lineHeight: 19 },
  phone: { color: '#cbd5e1', fontSize: 13 },
  link: { color: '#e9d5ff', fontWeight: '700' },
  empty: { color: '#e2e8f0', textAlign: 'center', padding: 20 },
  formTitle: { color: '#fff', fontSize: 15, fontWeight: '800' },
  typeRow: { flexDirection: 'row', gap: 8 },
  chip: { borderWidth: 1, borderColor: 'rgba(255,255,255,0.18)', borderRadius: 999, paddingHorizontal: 10, paddingVertical: 6 },
  chipOn: { backgroundColor: 'rgba(139,92,246,0.3)', borderColor: '#c4b5fd' },
  chipText: { color: '#cbd5e1', fontSize: 12, fontWeight: '700' },
  chipTextOn: { color: '#fff' },
  input: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 11,
    color: '#fff',
  },
  actions: { flexDirection: 'row', justifyContent: 'flex-end', alignItems: 'center', gap: 14 },
  save: { backgroundColor: '#7c3aed', height: 42, minWidth: 120, borderRadius: 12, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 14 },
  saveText: { color: '#fff', fontWeight: '800' },
});
