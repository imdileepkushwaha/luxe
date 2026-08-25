import React, { useCallback, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  ActivityIndicator,
  TextInput,
  Pressable,
  Platform,
  Alert,
} from 'react-native';
import { useFocusEffect } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Gift } from 'lucide-react-native';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import { fetchProfileSummary, type ProfileSummary } from '@/lib/api';

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

export default function RewardsScreen() {
  const { user } = useAuth();
  const { colors, isDark } = useAppTheme();
  const [data, setData] = useState<ProfileSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [points, setPoints] = useState('100');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    if (!user?.id) {
      setLoading(false);
      return;
    }
    try {
      setData(await fetchProfileSummary(Number(user.id)));
    } catch {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const redeem = async () => {
    if (!user?.id) return;
    const pts = parseInt(points, 10);
    if (!Number.isFinite(pts) || pts < 100 || pts % 100 !== 0) {
      notify('Invalid', 'Redeem in multiples of 100 points (minimum 100).');
      return;
    }
    setSubmitting(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_profile.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'redeem', user_id: Number(user.id), points: pts }),
      });
      const json = await res.json();
      if (!json.ok) {
        notify('Error', json.error || 'Could not redeem points');
        return;
      }
      notify('Done', json.message || 'Points redeemed');
      await load();
    } catch {
      notify('Error', 'Something went wrong');
    } finally {
      setSubmitting(false);
    }
  };

  if (!user) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Rewards" />
          <Text style={[styles.empty, { color: colors.text }]}>Sign in to view rewards.</Text>
        </SafeAreaView>
      </View>
    );
  }

  const loyalty = data?.loyalty;
  const progress = Math.max(0, Math.min(100, loyalty?.tier.progress ?? 0));

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title="Rewards" />
        {loading ? (
          <ActivityIndicator color="#c4b5fd" style={{ marginTop: 40 }} />
        ) : (
          <ScrollView contentContainerStyle={styles.scroll}>
            <View style={styles.hero}>
              <Gift size={22} color="#fbbf24" />
              <Text style={[styles.pts, { color: colors.text }]}>{Number(loyalty?.balance || 0).toLocaleString('en-IN')}</Text>
              <Text style={[styles.ptsLbl, { color: colors.goldText }]}>LUXE points</Text>
              <Text style={[styles.tier, { color: colors.text }]}>{loyalty?.tier.title || 'LUXE Member'}</Text>
              <Text style={[styles.lead, { color: colors.textSecondary }]}>{loyalty?.tier.lead}</Text>
              <View style={styles.track}>
                <View style={[styles.fill, { width: `${progress}%` }]} />
              </View>
              <View style={styles.trackLabels}>
                <Text style={[styles.trackLbl, { color: colors.muted }]}>Gold ({loyalty?.gold_at || 1000})</Text>
                <Text style={[styles.trackLbl, { color: colors.muted }]}>Platinum ({loyalty?.platinum_at || 5000})</Text>
              </View>
            </View>

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.text }]}>Redeem points</Text>
              <Text style={[styles.cardSub, { color: colors.muted }]}>100 points = ₹10 off on your next eligible checkout. Redeem in multiples of 100.</Text>
              <TextInput
                style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                keyboardType="number-pad"
                value={points}
                onChangeText={setPoints}
                placeholder="Enter points"
                placeholderTextColor={colors.placeholder}
              />
              <Pressable style={styles.btn} onPress={redeem} disabled={submitting}>
                {submitting ? <ActivityIndicator color="#fff" /> : <Text style={styles.btnText}>Redeem</Text>}
              </Pressable>
            </View>

            <View style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.cardTitle, { color: colors.text }]}>Points history</Text>
              {(loyalty?.history || []).length === 0 ? (
                <Text style={[styles.empty, { color: colors.text }]}>No points history yet. Delivered orders of ₹1,000+ earn 10 points per ₹1,000 after 10 days.</Text>
              ) : (
                loyalty?.history.map((h, i) => (
                  <View key={`${h.ref}-${i}`} style={[styles.histRow, { borderTopColor: colors.hairline }]}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.histLabel, { color: colors.text }]}>{h.label}</Text>
                      <Text style={styles.histMeta}>
                        {h.ref ? `#${h.ref}` : ''} {h.date}
                      </Text>
                    </View>
                    <Text style={styles.histPts}>
                      +{h.pts}
                      {h.type === 'pending' ? ' pending' : ''}
                    </Text>
                  </View>
                ))
              )}
            </View>
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
  hero: {
    backgroundColor: 'rgba(251,191,36,0.1)',
    borderWidth: 1,
    borderColor: 'rgba(251,191,36,0.35)',
    borderRadius: 16,
    padding: 18,
    alignItems: 'center',
    marginBottom: 16,
  },
  pts: { color: '#fff', fontSize: 40, fontWeight: '800', marginTop: 8 },
  ptsLbl: { color: '#fde68a', fontSize: 13, fontWeight: '700' },
  tier: { color: '#fff', fontSize: 16, fontWeight: '800', marginTop: 10 },
  lead: { color: '#e2e8f0', fontSize: 13, textAlign: 'center', marginTop: 6, lineHeight: 19 },
  track: { width: '100%', height: 8, borderRadius: 4, backgroundColor: 'rgba(255,255,255,0.12)', marginTop: 14, overflow: 'hidden' },
  fill: { height: '100%', backgroundColor: '#fbbf24' },
  trackLabels: { width: '100%', flexDirection: 'row', justifyContent: 'space-between', marginTop: 8 },
  trackLbl: { color: '#cbd5e1', fontSize: 11, fontWeight: '700' },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 16,
    padding: 14,
    marginBottom: 14,
  },
  cardTitle: { color: '#fff', fontSize: 16, fontWeight: '800', marginBottom: 6 },
  cardSub: { color: '#cbd5e1', fontSize: 13, lineHeight: 19, marginBottom: 10 },
  input: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 11,
    color: '#fff',
    marginBottom: 10,
  },
  btn: { backgroundColor: '#7c3aed', height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  btnText: { color: '#fff', fontWeight: '800' },
  histRow: { flexDirection: 'row', gap: 10, paddingVertical: 10, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)' },
  histLabel: { color: '#fff', fontSize: 13, fontWeight: '700' },
  histMeta: { color: '#94a3b8', fontSize: 12, marginTop: 2 },
  histPts: { color: '#fbbf24', fontSize: 13, fontWeight: '800' },
  empty: { color: '#e2e8f0', fontSize: 14, padding: 16, textAlign: 'center' },
});
