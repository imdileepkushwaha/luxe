import React, { useCallback, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  ActivityIndicator,
  Image,
  TouchableOpacity,
  Modal,
  TextInput,
  Pressable,
  Platform,
  Alert,
} from 'react-native';
import { useFocusEffect, useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Star, Package } from 'lucide-react-native';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import { fetchProfileSummary, type ProfileReview } from '@/lib/api';

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

export default function ReviewsScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const { colors, isDark } = useAppTheme();
  const [reviews, setReviews] = useState<ProfileReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [item, setItem] = useState<ProfileReview | null>(null);
  const [rating, setRating] = useState(5);
  const [text, setText] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    if (!user?.id) {
      setLoading(false);
      return;
    }
    try {
      const data = await fetchProfileSummary(Number(user.id));
      setReviews(data.reviews || []);
    } catch {
      setReviews([]);
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  const submit = async () => {
    if (!user?.id || !item) return;
    if (text.trim().length < 10) {
      notify('Required', 'Please write at least 10 characters.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await fetch(`${Config.API_URL}/mobile_order_actions.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'review',
          user_id: Number(user.id),
          order_ref: item.order_ref,
          product_id: item.product_id,
          rating,
          review_text: text.trim(),
        }),
      });
      const data = await res.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not submit review');
        return;
      }
      notify('Done', data.message || 'Review submitted');
      setItem(null);
      setText('');
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
          <LuxeHeader showBack title="Reviews" />
          <Text style={[styles.empty, { color: colors.text }]}>Sign in to view reviews.</Text>
        </SafeAreaView>
      </View>
    );
  }

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <BackgroundScene />
      <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
        <LuxeHeader showBack title="Reviews" />
        {loading ? (
          <ActivityIndicator color="#c4b5fd" style={{ marginTop: 40 }} />
        ) : (
          <ScrollView contentContainerStyle={styles.scroll}>
            {reviews.length === 0 ? (
              <Text style={[styles.empty, { color: colors.text }]}>No delivered products to review yet.</Text>
            ) : (
              reviews.map((r) => (
                <View key={`${r.product_id}-${r.order_ref}`} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <TouchableOpacity style={styles.row} onPress={() => r.product_id && router.push(`/product/${r.product_id}`)}>
                    {r.image_url ? (
                      <Image source={{ uri: r.image_url }} style={[styles.img, { backgroundColor: colors.productImageBg }]} />
                    ) : (
                      <View style={[styles.img, styles.fallback, { backgroundColor: colors.productImageBg }]}>
                        <Package size={16} color={colors.muted} />
                      </View>
                    )}
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.name, { color: colors.text }]}>{r.name}</Text>
                      <Text style={[styles.meta, { color: colors.muted }]}>#{r.order_ref}</Text>
                      {r.rating > 0 && (
                        <Text style={styles.rating}>
                          {'★'.repeat(r.rating)}
                          {'☆'.repeat(Math.max(0, 5 - r.rating))} {r.review_status}
                        </Text>
                      )}
                      {!!r.review_text && <Text style={[styles.body, { color: colors.textSecondary }]}>{r.review_text}</Text>}
                    </View>
                  </TouchableOpacity>
                  {r.can_review && (
                    <TouchableOpacity
                      style={styles.reviewBtn}
                      onPress={() => {
                        setItem(r);
                        setRating(5);
                        setText('');
                      }}
                    >
                      <Star size={13} color={isDark ? '#e9d5ff' : colors.accent} />
                      <Text style={[styles.reviewBtnText, { color: isDark ? '#e9d5ff' : colors.accent }]}>Write review</Text>
                    </TouchableOpacity>
                  )}
                </View>
              ))
            )}
          </ScrollView>
        )}
      </SafeAreaView>

      <Modal visible={!!item} transparent animationType="fade" onRequestClose={() => setItem(null)}>
        <View style={styles.modalWrap}>
          <Pressable style={styles.backdrop} onPress={() => setItem(null)} />
          <View style={[styles.sheet, { backgroundColor: colors.modal }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>Rate & review</Text>
            <Text style={[styles.meta, { color: colors.muted }]}>{item?.name}</Text>
            <View style={styles.stars}>
              {[1, 2, 3, 4, 5].map((n) => (
                <TouchableOpacity key={n} onPress={() => setRating(n)}>
                  <Star size={26} color={n <= rating ? '#fbbf24' : '#64748b'} fill={n <= rating ? '#fbbf24' : 'none'} />
                </TouchableOpacity>
              ))}
            </View>
            <TextInput
              style={[styles.input, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
              placeholder="Share your experience..."
              placeholderTextColor={colors.placeholder}
              multiline
              value={text}
              onChangeText={setText}
            />
            <Pressable style={styles.btn} onPress={submit} disabled={submitting}>
              {submitting ? <ActivityIndicator color="#fff" /> : <Text style={styles.btnText}>Submit review</Text>}
            </Pressable>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#08080e' },
  safeArea: { flex: 1 },
  scroll: { padding: 16, paddingBottom: 40 },
  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    borderRadius: 16,
    padding: 12,
    marginBottom: 12,
  },
  row: { flexDirection: 'row', gap: 10 },
  img: { width: 56, height: 68, borderRadius: 8, backgroundColor: '#16161f' },
  fallback: { alignItems: 'center', justifyContent: 'center' },
  name: { color: '#fff', fontSize: 14, fontWeight: '700' },
  meta: { color: '#94a3b8', fontSize: 12, marginTop: 3 },
  rating: { color: '#fbbf24', fontSize: 12, marginTop: 4 },
  body: { color: '#e2e8f0', fontSize: 13, marginTop: 6, lineHeight: 18 },
  reviewBtn: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 10 },
  reviewBtnText: { color: '#e9d5ff', fontSize: 13, fontWeight: '700' },
  empty: { color: '#e2e8f0', fontSize: 14, padding: 24, textAlign: 'center' },
  modalWrap: { flex: 1, justifyContent: 'flex-end' },
  backdrop: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(0,0,0,0.55)' },
  sheet: {
    backgroundColor: '#12121a',
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    padding: 18,
    gap: 10,
  },
  modalTitle: { color: '#fff', fontSize: 18, fontWeight: '800' },
  stars: { flexDirection: 'row', gap: 8 },
  input: {
    minHeight: 80,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.14)',
    borderRadius: 12,
    color: '#fff',
    padding: 12,
    textAlignVertical: 'top',
  },
  btn: { backgroundColor: '#7c3aed', height: 44, borderRadius: 12, alignItems: 'center', justifyContent: 'center' },
  btnText: { color: '#fff', fontWeight: '800' },
});
