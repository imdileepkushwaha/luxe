import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  StyleSheet,
  Text,
  View,
  ScrollView,
  TouchableOpacity,
  Pressable,
  ActivityIndicator,
  Image,
  RefreshControl,
  Modal,
  TextInput,
  Platform,
  Linking,
  Alert,
  KeyboardAvoidingView,
} from 'react-native';
import { useRouter } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import {
  Package,
  Clock,
  CheckCircle2,
  Check,
  Truck,
  AlertCircle,
  ShoppingBag,
  ChevronDown,
  MapPin,
  FileText,
  Star,
  RotateCcw,
} from 'lucide-react-native';
import { LinearGradient } from 'expo-linear-gradient';
import BackgroundScene from '@/components/BackgroundScene';
import LuxeHeader from '@/components/LuxeHeader';
import { useAuth } from '@/context/AuthContext';
import { useAppTheme } from '@/context/ThemeContext';
import Config from '@/constants/Config';
import { fetchOrders, formatPrice, type Order, type OrderItem } from '@/lib/api';

const FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'processing', label: 'Processing' },
  { id: 'shipped', label: 'Shipped' },
  { id: 'delivered', label: 'Delivered' },
  { id: 'cancelled', label: 'Cancelled' },
] as const;

const CANCEL_REASONS = [
  'Changed my mind',
  'Ordered by mistake',
  'Found better price elsewhere',
  'Delivery is taking too long',
  'Other',
];

const RETURN_REASONS = [
  'Damaged product',
  'Wrong item delivered',
  'Quality not as expected',
  'Size/Fit issue',
  'Other',
];

const JOURNEY_STEPS = ['Placed', 'Confirmed', 'Shipped', 'Out for delivery', 'Delivered'];

type FilterId = (typeof FILTERS)[number]['id'];
type ActionKind = 'cancel' | 'return' | 'review' | null;

function notify(title: string, message: string) {
  if (Platform.OS === 'web' && typeof window !== 'undefined') {
    window.alert(`${title}: ${message}`);
    return;
  }
  Alert.alert(title, message);
}

function statusKey(status: string): FilterId {
  const s = (status || '').toLowerCase().replace(/_/g, ' ').trim();
  if (s === 'delivered' || s === 'completed') return 'delivered';
  if (s === 'cancelled' || s === 'canceled') return 'cancelled';
  if (s === 'shipped' || s === 'out for delivery' || s === 'out') return 'shipped';
  return 'processing';
}

function statusLabel(status: string): string {
  const s = (status || '').toLowerCase().replace(/_/g, ' ').trim();
  if (s === 'delivered' || s === 'completed') return 'Delivered';
  if (s === 'cancelled' || s === 'canceled') return 'Cancelled';
  if (s === 'out for delivery' || s === 'out') return 'Out for delivery';
  if (s === 'shipped') return 'Shipped';
  if (s === 'confirmed') return 'Confirmed';
  if (s === 'pending') return 'Pending';
  return 'Processing';
}

function statusColor(status: string): string {
  switch (statusKey(status)) {
    case 'delivered':
      return '#34d399';
    case 'shipped':
      return '#60a5fa';
    case 'cancelled':
      return '#f87171';
    default:
      return '#fbbf24';
  }
}

function StatusIcon({ status }: { status: string }) {
  const color = statusColor(status);
  const key = statusKey(status);
  if (key === 'delivered') return <CheckCircle2 size={14} color={color} />;
  if (key === 'shipped') return <Truck size={14} color={color} />;
  if (key === 'cancelled') return <AlertCircle size={14} color={color} />;
  return <Clock size={14} color={color} />;
}

function journeyStep(status: string): number {
  const s = (status || '').toLowerCase().replace(/_/g, ' ').trim();
  if (s === 'delivered' || s === 'completed') return 4;
  if (s === 'out for delivery' || s === 'out') return 3;
  if (s === 'shipped') return 2;
  if (s === 'confirmed') return 1;
  return 0;
}

function paymentLabel(method: string): string {
  const v = (method || '').toUpperCase();
  if (v === 'COD') return 'Cash on Delivery';
  if (v === 'ONLINE' || v === 'UPI' || v === 'CARD') return 'Online Payment';
  return method || '—';
}

function formatDate(value: string): string {
  const t = Date.parse(value);
  if (!Number.isFinite(t)) return '';
  return new Date(t).toLocaleDateString('en-IN', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function returnableItems(order: Order): OrderItem[] {
  return (order.items || []).filter((it) => !it.return_request);
}

export default function OrdersScreen() {
  const router = useRouter();
  const { user, isLoading: authLoading } = useAuth();
  const { colors, isDark } = useAppTheme();
  const [orders, setOrders] = useState<Order[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [filter, setFilter] = useState<FilterId>('all');
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const [actionKind, setActionKind] = useState<ActionKind>(null);
  const [actionOrder, setActionOrder] = useState<Order | null>(null);
  const [actionItem, setActionItem] = useState<OrderItem | null>(null);
  const [reason, setReason] = useState('');
  const [details, setDetails] = useState('');
  const [rating, setRating] = useState(5);
  const [reviewText, setReviewText] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const loadOrders = useCallback(
    async (silent = false) => {
      if (!user?.id) {
        setOrders([]);
        setLoading(false);
        setRefreshing(false);
        return;
      }
      if (!silent) setLoading(true);
      setError('');
      try {
        const list = await fetchOrders(Number(user.id));
        setOrders(list);
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load orders');
      } finally {
        setLoading(false);
        setRefreshing(false);
      }
    },
    [user?.id]
  );

  useEffect(() => {
    if (!authLoading) loadOrders();
  }, [authLoading, loadOrders]);

  const filtered = useMemo(() => {
    if (filter === 'all') return orders;
    return orders.filter((o) => statusKey(o.status) === filter);
  }, [orders, filter]);

  const closeAction = () => {
    setActionKind(null);
    setActionOrder(null);
    setActionItem(null);
    setReason('');
    setDetails('');
    setRating(5);
    setReviewText('');
  };

  const openCancel = (order: Order) => {
    setActionOrder(order);
    setActionKind('cancel');
    setReason('');
    setDetails('');
  };

  const openReturn = (order: Order, item?: OrderItem) => {
    const items = item ? [item] : returnableItems(order);
    setActionOrder(order);
    setActionItem(items[0] || null);
    setActionKind('return');
    setReason('');
    setDetails('');
  };

  const openReview = (order: Order, item: OrderItem) => {
    setActionOrder(order);
    setActionItem(item);
    setActionKind('review');
    setRating(5);
    setReviewText('');
  };

  const openInvoice = async (order: Order) => {
    if (!user?.id) return;
    const url = `${Config.SITE_ORIGIN}/download-invoice.php?order_ref=${encodeURIComponent(order.order_ref)}&user_id=${user.id}`;
    if (Platform.OS === 'web' && typeof window !== 'undefined') {
      window.open(url, '_blank');
      return;
    }
    await Linking.openURL(url);
  };

  const submitAction = async () => {
    if (!user?.id || !actionOrder || !actionKind) return;
    if (actionKind !== 'review' && !reason) {
      notify('Required', 'Please select a reason.');
      return;
    }
    if (actionKind === 'review' && reviewText.trim().length < 10) {
      notify('Required', 'Please write at least 10 characters.');
      return;
    }
    if (actionKind === 'return' && !actionItem) {
      notify('Required', 'Please select an item to return.');
      return;
    }

    setSubmitting(true);
    try {
      const body: Record<string, unknown> = {
        action: actionKind,
        user_id: Number(user.id),
        order_ref: actionOrder.order_ref,
      };
      if (actionKind === 'cancel') {
        body.reason = reason;
        body.details = details.trim();
      } else if (actionKind === 'return') {
        body.reason = reason;
        body.details = details.trim();
        body.order_item_id = actionItem?.id || 0;
      } else {
        body.product_id = actionItem?.product_id || 0;
        body.rating = rating;
        body.review_text = reviewText.trim();
      }

      const response = await fetch(`${Config.API_URL}/mobile_order_actions.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });
      const data = await response.json();
      if (!data.ok) {
        notify('Error', data.error || 'Could not submit request');
        return;
      }
      notify('Done', data.message || 'Request submitted');
      closeAction();
      await loadOrders(true);
    } catch {
      notify('Error', 'Something went wrong. Please try again.');
    } finally {
      setSubmitting(false);
    }
  };

  const actionTitle =
    actionKind === 'cancel' ? 'Cancel order' : actionKind === 'return' ? 'Return item' : 'Rate & review';
  const reasonList = actionKind === 'return' ? RETURN_REASONS : CANCEL_REASONS;

  if (authLoading) {
    return (
      <View style={[styles.container, { backgroundColor: colors.bg }]}>
        <BackgroundScene />
        <SafeAreaView style={styles.safeArea} edges={['bottom', 'left', 'right']}>
          <LuxeHeader showBack title="Orders" />
          <View style={styles.centered}>
            <ActivityIndicator color="#c4b5fd" />
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
          <LuxeHeader showBack title="Orders" />
          <View style={styles.centered}>
            <View style={styles.emptyIconBox}>
              <Package size={32} color="#c4b5fd" />
            </View>
            <Text style={[styles.emptyTitle, { color: colors.text }]}>Sign in to view orders</Text>
            <Text style={[styles.emptySub, { color: colors.muted }]}>Your order history and tracking will show up here.</Text>
            <Pressable style={styles.ctaBtn} onPress={() => router.push('/(tabs)/profile')}>
              <LinearGradient
                colors={['#8b5cf6', '#db2777']}
                start={{ x: 0, y: 0 }}
                end={{ x: 1, y: 0 }}
                style={styles.ctaGrad}
                pointerEvents="none"
              >
                <Text style={styles.ctaText}>Go to profile</Text>
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
        <LuxeHeader showBack title="Orders" />

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          style={styles.filterScroll}
          contentContainerStyle={styles.filterRow}
        >
          {FILTERS.map((f) => {
            const on = filter === f.id;
            const count =
              f.id === 'all' ? orders.length : orders.filter((o) => statusKey(o.status) === f.id).length;
            return (
              <TouchableOpacity
                key={f.id}
                style={[
                  styles.chip,
                  { backgroundColor: colors.card, borderColor: colors.border },
                  on && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a', borderColor: isDark ? 'rgba(196,181,253,0.8)' : '#0f172a' },
                ]}
                onPress={() => setFilter(f.id)}
              >
                <Text style={[styles.chipText, { color: on ? '#fff' : colors.text }]}>
                  {f.label}
                  {count > 0 ? ` ${count}` : ''}
                </Text>
              </TouchableOpacity>
            );
          })}
        </ScrollView>

        <ScrollView
          showsVerticalScrollIndicator={false}
          contentContainerStyle={styles.scrollContent}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                loadOrders(true);
              }}
              tintColor="#c4b5fd"
            />
          }
        >
          {loading ? (
            <View style={styles.centered}>
              <ActivityIndicator color="#c4b5fd" />
            </View>
          ) : error ? (
            <View style={[styles.emptyCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.emptyTitle, { color: colors.text }]}>Could not load orders</Text>
              <Text style={[styles.emptySub, { color: colors.muted }]}>{error}</Text>
              <TouchableOpacity onPress={() => loadOrders()}>
                <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Try again</Text>
              </TouchableOpacity>
            </View>
          ) : orders.length === 0 ? (
            <View style={[styles.emptyCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <View style={styles.emptyIconBox}>
                <ShoppingBag size={28} color="#c4b5fd" />
              </View>
              <Text style={[styles.emptyTitle, { color: colors.text }]}>No orders yet</Text>
              <Text style={[styles.emptySub, { color: colors.muted }]}>When you place an order, it will appear here.</Text>
              <Pressable style={styles.ctaBtn} onPress={() => router.push('/(tabs)/shop')}>
                <LinearGradient
                  colors={['#8b5cf6', '#db2777']}
                  start={{ x: 0, y: 0 }}
                  end={{ x: 1, y: 0 }}
                  style={styles.ctaGrad}
                  pointerEvents="none"
                >
                  <Text style={styles.ctaText}>Start shopping</Text>
                </LinearGradient>
              </Pressable>
            </View>
          ) : filtered.length === 0 ? (
            <View style={[styles.emptyCard, { backgroundColor: colors.card, borderColor: colors.border }]}>
              <Text style={[styles.emptyTitle, { color: colors.text }]}>No {filter} orders</Text>
              <TouchableOpacity onPress={() => setFilter('all')}>
                <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>Show all orders</Text>
              </TouchableOpacity>
            </View>
          ) : (
            filtered.map((order) => {
              const color = statusColor(order.status);
              const open = expandedId === order.id;
              const cancelled = statusKey(order.status) === 'cancelled';
              const step = journeyStep(order.status);
              const firstItems = order.items.slice(0, 3);
              const cancelStatus = (order.cancel_request?.status || '').toLowerCase();

              return (
                <View key={order.id} style={[styles.card, { backgroundColor: colors.card, borderColor: colors.border }]}>
                  <View style={styles.cardHead}>
                    <View style={{ flex: 1 }}>
                      <Text style={[styles.orderRef, { color: colors.text }]}>#{order.order_ref || order.id}</Text>
                      <Text style={[styles.meta, { color: colors.muted }]}>
                        Placed on {formatDate(order.created_at)}
                        {order.item_count ? `  ·  ${order.item_count} item${order.item_count === 1 ? '' : 's'}` : ''}
                      </Text>
                    </View>
                    <View style={styles.headRight}>
                    <View style={[styles.statusBadge, { borderColor: `${color}55` }]}>
                      <StatusIcon status={order.status} />
                      <Text style={[styles.statusText, { color }]}>{statusLabel(order.status)}</Text>
                    </View>
                    {!!order.loyalty_points && order.loyalty_status && order.loyalty_status !== 'none' ? (
                      <View style={styles.pointsChip}>
                        <Text style={styles.pointsChipText}>
                          {order.loyalty_status === 'credited' ? '+' : ''}
                          {order.loyalty_points} pts
                        </Text>
                      </View>
                    ) : null}
                    </View>
                  </View>

                  {order.can_cancel ? (
                    <TouchableOpacity style={styles.cancelBtn} onPress={() => openCancel(order)}>
                      <Text style={styles.cancelBtnText}>Cancel order</Text>
                    </TouchableOpacity>
                  ) : cancelStatus === 'pending' ? (
                    <Text style={styles.cancelPending}>Cancel pending</Text>
                  ) : cancelStatus === 'rejected' ? (
                    <Text style={styles.cancelRejected}>Cancel rejected</Text>
                  ) : null}

                  {firstItems.map((item, i) => (
                    <View key={item.id} style={[styles.itemRow, i > 0 && styles.itemBorder]}>
                      <Pressable
                        style={styles.itemMain}
                        onPress={() =>
                          item.product_id
                            ? router.push(`/product/${item.product_id}`)
                            : setExpandedId(open ? null : order.id)
                        }
                      >
                        {item.image_url ? (
                          <Image source={{ uri: item.image_url }} style={styles.itemImg} />
                        ) : (
                          <View style={[styles.itemImg, styles.itemImgFallback]}>
                            <Package size={16} color={colors.muted} />
                          </View>
                        )}
                        <View style={{ flex: 1 }}>
                          <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={1}>
                            {item.name}
                          </Text>
                          <Text style={[styles.itemMeta, { color: colors.muted }]}>
                            Qty {item.qty}
                            {item.variant ? `  ·  ${item.variant}` : ''}
                          </Text>
                          {item.return_request ? (
                            <Text style={styles.returnStatus}>
                              Return {item.return_request.status.replace(/_/g, ' ')}
                            </Text>
                          ) : null}
                        </View>
                        <Text style={[styles.itemPrice, { color: colors.text }]}>{formatPrice(item.price * item.qty)}</Text>
                      </Pressable>
                      {item.can_review ? (
                        <TouchableOpacity style={styles.itemAction} onPress={() => openReview(order, item)}>
                          <Star size={12} color={isDark ? '#c4b5fd' : colors.accent} />
                          <Text style={[styles.itemActionText, { color: isDark ? '#e9d5ff' : colors.accent }]}>Rate & review</Text>
                        </TouchableOpacity>
                      ) : null}
                    </View>
                  ))}

                  {order.items.length > 3 && !open && (
                    <Text style={[styles.moreItems, { color: colors.muted }]}>+{order.items.length - 3} more items</Text>
                  )}

                  {open && (
                    <View style={styles.details}>
                      {order.items.length > 3 &&
                        order.items.slice(3).map((item) => (
                          <View key={item.id} style={[styles.itemRow, styles.itemBorder]}>
                            <Pressable
                              style={styles.itemMain}
                              onPress={() => item.product_id && router.push(`/product/${item.product_id}`)}
                            >
                              {item.image_url ? (
                                <Image source={{ uri: item.image_url }} style={styles.itemImg} />
                              ) : (
                                <View style={[styles.itemImg, styles.itemImgFallback]}>
                                  <Package size={16} color={colors.muted} />
                                </View>
                              )}
                              <View style={{ flex: 1 }}>
                                <Text style={[styles.itemName, { color: colors.text }]} numberOfLines={1}>
                                  {item.name}
                                </Text>
                                <Text style={[styles.itemMeta, { color: colors.muted }]}>Qty {item.qty}</Text>
                              </View>
                              <Text style={[styles.itemPrice, { color: colors.text }]}>{formatPrice(item.price * item.qty)}</Text>
                            </Pressable>
                            {item.can_review ? (
                              <TouchableOpacity style={styles.itemAction} onPress={() => openReview(order, item)}>
                                <Star size={12} color={isDark ? '#c4b5fd' : colors.accent} />
                                <Text style={[styles.itemActionText, { color: isDark ? '#e9d5ff' : colors.accent }]}>Rate & review</Text>
                              </TouchableOpacity>
                            ) : null}
                          </View>
                        ))}

                      <View style={styles.detailBlock}>
                        <Text style={[styles.detailLabel, { color: colors.muted }]}>Payment</Text>
                        <Text style={[styles.detailValue, { color: colors.text }]}>{paymentLabel(order.payment_method)}</Text>
                      </View>
                      {!!order.shipping_address && (
                        <View style={styles.detailBlock}>
                          <View style={styles.addrLabelRow}>
                            <MapPin size={12} color={colors.iconMuted} />
                            <Text style={[styles.detailLabel, { color: colors.muted }]}>Delivery address</Text>
                          </View>
                          <Text style={[styles.detailValue, { color: colors.text }]}>{order.shipping_address}</Text>
                        </View>
                      )}
                    </View>
                  )}

                  {!cancelled && (
                    <View style={styles.timeline}>
                      <Text style={[styles.journeyTitle, { color: colors.muted }]}>Order journey</Text>
                      <View style={styles.track}>
                        <View style={styles.trackRail}>
                          <View style={styles.trackLine} />
                          <View
                            style={[
                              styles.trackFill,
                              {
                                width: `${(step / (JOURNEY_STEPS.length - 1)) * 100}%`,
                                backgroundColor: color,
                              },
                            ]}
                          />
                        </View>
                        {JOURNEY_STEPS.map((label, i) => {
                          const done = i < step;
                          const current = i === step;
                          return (
                            <View key={label} style={styles.stepCol}>
                              <View
                                style={[
                                  styles.stepDot,
                                  (done || current) && { backgroundColor: color, borderColor: color },
                                ]}
                              >
                                {done ? (
                                  <Check size={11} color="#0b0b10" strokeWidth={3} />
                                ) : (
                                  <Text style={[styles.stepNum, current && styles.stepNumOn]}>{i + 1}</Text>
                                )}
                              </View>
                              <Text
                                style={[styles.timeLabel, { color: colors.muted }, (done || current) && { color: colors.text }]}
                                numberOfLines={2}
                              >
                                {label}
                              </Text>
                            </View>
                          );
                        })}
                      </View>
                    </View>
                  )}

                  <View style={[styles.footer, { borderTopColor: colors.hairline }]}>
                    <View>
                      <Text style={[styles.totalHint, { color: colors.muted }]}>Total amount paid</Text>
                      <Text style={[styles.totalValue, { color: colors.text }]}>{formatPrice(order.total_amount)}</Text>
                      {!!order.loyalty_points && order.loyalty_status !== 'none' && (
                        <Text style={styles.pointsText}>
                          {order.loyalty_status === 'credited'
                            ? `+${order.loyalty_points} LUXE points credited`
                            : order.loyalty_status === 'pending'
                              ? `+${order.loyalty_points} points pending`
                              : `Earns +${order.loyalty_points} points after delivery`}
                        </Text>
                      )}
                    </View>
                    <View style={styles.actionWrap}>
                      {order.can_return ? (
                        <TouchableOpacity style={[styles.outlineBtn, { borderColor: colors.border }]} onPress={() => openReturn(order)}>
                          <RotateCcw size={13} color={colors.icon} />
                          <Text style={[styles.outlineBtnText, { color: colors.text }]}>Return</Text>
                        </TouchableOpacity>
                      ) : null}
                      {order.can_invoice ? (
                        <TouchableOpacity style={[styles.outlineBtn, { borderColor: colors.border }]} onPress={() => openInvoice(order)}>
                          <FileText size={13} color={colors.icon} />
                          <Text style={[styles.outlineBtnText, { color: colors.text }]}>Invoice</Text>
                        </TouchableOpacity>
                      ) : null}
                      <TouchableOpacity
                        style={styles.detailsBtn}
                        onPress={() => setExpandedId(open ? null : order.id)}
                      >
                        <Text style={[styles.detailsBtnText, { color: isDark ? '#e9d5ff' : colors.accent }]}>{open ? 'Hide details' : 'View details'}</Text>
                        <ChevronDown
                          size={14}
                          color={isDark ? '#e9d5ff' : colors.accent}
                          style={{ transform: [{ rotate: open ? '180deg' : '0deg' }] }}
                        />
                      </TouchableOpacity>
                    </View>
                  </View>
                </View>
              );
            })
          )}
        </ScrollView>
      </SafeAreaView>

      <Modal visible={actionKind !== null} transparent animationType="fade" onRequestClose={closeAction}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.modalWrap}>
          <Pressable style={styles.modalBackdrop} onPress={closeAction} />
          <View style={[styles.modalCard, { backgroundColor: colors.modal, borderColor: colors.border }]}>
            <Text style={[styles.modalTitle, { color: colors.text }]}>{actionTitle}</Text>
            {actionKind === 'cancel' && (
              <Text style={[styles.modalSub, { color: colors.textSecondary }]}>Are you sure you want to cancel this order? Please provide a reason.</Text>
            )}
            {actionKind === 'return' && actionOrder && returnableItems(actionOrder).length > 1 && (
              <View style={styles.reasonWrap}>
                {returnableItems(actionOrder).map((it) => (
                  <TouchableOpacity
                    key={it.id}
                    style={[
                      styles.reasonChip,
                      { borderColor: colors.border },
                      actionItem?.id === it.id && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a', borderColor: isDark ? '#c4b5fd' : '#0f172a' },
                    ]}
                    onPress={() => setActionItem(it)}
                  >
                    <Text style={[styles.reasonChipText, { color: actionItem?.id === it.id ? '#fff' : colors.text }]} numberOfLines={1}>
                      {it.name}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>
            )}
            {actionKind === 'review' && (
              <Text style={[styles.modalSub, { color: colors.textSecondary }]} numberOfLines={2}>
                {actionItem?.name}
              </Text>
            )}

            {actionKind === 'review' ? (
              <>
                <View style={styles.starRow}>
                  {[1, 2, 3, 4, 5].map((n) => (
                    <TouchableOpacity key={n} onPress={() => setRating(n)}>
                      <Star size={26} color={n <= rating ? '#fbbf24' : '#64748b'} fill={n <= rating ? '#fbbf24' : 'none'} />
                    </TouchableOpacity>
                  ))}
                </View>
                <TextInput
                  style={[styles.input, styles.inputArea, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Share your experience with this product..."
                  placeholderTextColor={colors.placeholder}
                  multiline
                  value={reviewText}
                  onChangeText={setReviewText}
                />
              </>
            ) : (
              <>
                <Text style={[styles.modalLabel, { color: colors.text }]}>Reason</Text>
                <View style={styles.reasonWrap}>
                  {reasonList.map((r) => (
                    <TouchableOpacity
                      key={r}
                      style={[
                        styles.reasonChip,
                        { borderColor: colors.border },
                        reason === r && { backgroundColor: isDark ? 'rgba(139,92,246,0.35)' : '#0f172a', borderColor: isDark ? '#c4b5fd' : '#0f172a' },
                      ]}
                      onPress={() => setReason(r)}
                    >
                      <Text style={[styles.reasonChipText, { color: reason === r ? '#fff' : colors.text }]}>{r}</Text>
                    </TouchableOpacity>
                  ))}
                </View>
                <TextInput
                  style={[styles.input, styles.inputArea, { backgroundColor: colors.input, borderColor: colors.inputBorder, color: colors.text }]}
                  placeholder="Additional details (optional)"
                  placeholderTextColor={colors.placeholder}
                  multiline
                  value={details}
                  onChangeText={setDetails}
                />
              </>
            )}

            <View style={styles.modalActions}>
              <TouchableOpacity onPress={closeAction}>
                <Text style={[styles.link, { color: isDark ? '#e9d5ff' : colors.accent }]}>{actionKind === 'cancel' ? 'Keep order' : 'Close'}</Text>
              </TouchableOpacity>
              <Pressable style={styles.saveBtn} onPress={submitAction} disabled={submitting}>
                {submitting ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <Text style={styles.saveBtnText}>
                    {actionKind === 'cancel' ? 'Cancel order' : actionKind === 'return' ? 'Submit return' : 'Submit review'}
                  </Text>
                )}
              </Pressable>
            </View>
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
  scrollContent: { padding: 16, paddingBottom: 40, paddingTop: 4 },

  filterScroll: { flexGrow: 0 },
  filterRow: { paddingHorizontal: 16, paddingTop: 8, paddingBottom: 4, gap: 8 },
  chip: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
    backgroundColor: 'rgba(255,255,255,0.06)',
  },
  chipOn: { backgroundColor: 'rgba(139,92,246,0.35)', borderColor: 'rgba(196,181,253,0.8)' },
  chipText: { color: '#e2e8f0', fontSize: 12, fontWeight: '700' },
  chipTextOn: { color: '#fff' },

  card: {
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    padding: 14,
    marginBottom: 14,
  },
  cardHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 10, marginBottom: 10 },
  orderRef: { color: '#fff', fontSize: 15, fontWeight: '800' },
  meta: { color: '#cbd5e1', fontSize: 12, marginTop: 3 },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
  },
  statusText: { fontSize: 11, fontWeight: '800' },
  headRight: { alignItems: 'flex-end', gap: 6 },
  pointsChip: {
    backgroundColor: 'rgba(251,191,36,0.16)',
    borderWidth: 1,
    borderColor: 'rgba(251,191,36,0.4)',
    borderRadius: 999,
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  pointsChipText: { color: '#fbbf24', fontSize: 10, fontWeight: '800' },
  cancelBtn: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderColor: 'rgba(248,113,113,0.7)',
    backgroundColor: 'rgba(248,113,113,0.12)',
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 7,
    marginBottom: 10,
  },
  cancelBtnText: { color: '#fca5a5', fontSize: 12, fontWeight: '800' },
  cancelPending: { color: '#fbbf24', fontSize: 12, fontWeight: '800', marginBottom: 10 },
  cancelRejected: { color: '#f87171', fontSize: 12, fontWeight: '800', marginBottom: 10 },

  itemRow: { paddingVertical: 8 },
  itemMain: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  itemBorder: { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: 'rgba(255,255,255,0.1)' },
  itemImg: { width: 48, height: 58, borderRadius: 8, backgroundColor: '#16161f' },
  itemImgFallback: { alignItems: 'center', justifyContent: 'center' },
  itemName: { color: '#fff', fontSize: 13, fontWeight: '700' },
  itemMeta: { color: '#cbd5e1', fontSize: 12, marginTop: 2 },
  itemPrice: { color: '#fff', fontSize: 13, fontWeight: '800' },
  returnStatus: { color: '#fbbf24', fontSize: 11, fontWeight: '700', marginTop: 4, textTransform: 'capitalize' },
  itemAction: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 8, marginLeft: 58 },
  itemActionText: { color: '#e9d5ff', fontSize: 12, fontWeight: '700' },
  moreItems: { color: '#e2e8f0', fontSize: 12, fontWeight: '600', marginTop: 4 },

  details: { marginTop: 6 },
  detailBlock: { marginTop: 10 },
  addrLabelRow: { flexDirection: 'row', alignItems: 'center', gap: 6, marginBottom: 4 },
  detailLabel: { color: '#e2e8f0', fontSize: 11, fontWeight: '800', letterSpacing: 0.4, marginBottom: 4 },
  detailValue: { color: '#f1f5f9', fontSize: 13, lineHeight: 19 },

  timeline: { marginTop: 16 },
  journeyTitle: {
    color: '#e2e8f0',
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.6,
    textTransform: 'uppercase',
    marginBottom: 12,
  },
  track: {
    position: 'relative',
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  trackRail: {
    position: 'absolute',
    top: 11,
    left: 18,
    right: 18,
    height: 3,
  },
  trackLine: {
    ...StyleSheet.absoluteFillObject,
    borderRadius: 2,
    backgroundColor: 'rgba(255,255,255,0.16)',
  },
  trackFill: {
    height: 3,
    borderRadius: 2,
  },
  stepCol: { flex: 1, alignItems: 'center', zIndex: 1 },
  stepDot: {
    width: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: '#1e1e28',
    borderWidth: 2,
    borderColor: 'rgba(255,255,255,0.28)',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 6,
  },
  stepNum: { color: '#cbd5e1', fontSize: 10, fontWeight: '800' },
  stepNumOn: { color: '#0b0b10' },
  timeLabel: { color: '#cbd5e1', fontSize: 9, fontWeight: '700', textAlign: 'center', lineHeight: 12 },
  timeLabelOn: { color: '#fff' },

  footer: {
    marginTop: 14,
    paddingTop: 12,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: 'rgba(255,255,255,0.1)',
    gap: 12,
  },
  totalHint: { color: '#e2e8f0', fontSize: 11, fontWeight: '600' },
  totalValue: { color: '#fff', fontSize: 18, fontWeight: '800', marginTop: 2 },
  pointsText: { color: '#fbbf24', fontSize: 12, fontWeight: '700', marginTop: 4 },
  actionWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, alignItems: 'center' },
  outlineBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderColor: 'rgba(226,232,240,0.35)',
    borderRadius: 10,
    paddingHorizontal: 10,
    paddingVertical: 8,
  },
  outlineBtnText: { color: '#f1f5f9', fontSize: 12, fontWeight: '700' },
  detailsBtn: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  detailsBtnText: { color: '#e9d5ff', fontSize: 13, fontWeight: '700' },

  emptyCard: {
    alignItems: 'center',
    padding: 28,
    borderRadius: 16,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
    marginTop: 12,
    gap: 8,
  },
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
  emptySub: { color: '#e2e8f0', fontSize: 14, textAlign: 'center', lineHeight: 20, marginBottom: 8 },
  link: { color: '#e9d5ff', fontSize: 13, fontWeight: '700' },
  ctaBtn: { width: '100%', maxWidth: 240, borderRadius: 14, overflow: 'hidden', marginTop: 8 },
  ctaGrad: { height: 48, alignItems: 'center', justifyContent: 'center' },
  ctaText: { color: '#fff', fontSize: 14, fontWeight: '800' },

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
  modalLabel: { color: '#f1f5f9', fontSize: 12, fontWeight: '800', marginTop: 4 },
  reasonWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  reasonChip: {
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.18)',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 7,
  },
  reasonChipOn: { backgroundColor: 'rgba(139,92,246,0.35)', borderColor: '#c4b5fd' },
  reasonChipText: { color: '#e2e8f0', fontSize: 12, fontWeight: '600' },
  reasonChipTextOn: { color: '#fff' },
  starRow: { flexDirection: 'row', gap: 8, marginVertical: 4 },
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
  inputArea: { minHeight: 80, textAlignVertical: 'top' },
  modalActions: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 16, marginTop: 6 },
  saveBtn: {
    backgroundColor: '#7c3aed',
    borderRadius: 12,
    paddingHorizontal: 16,
    height: 42,
    minWidth: 130,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveBtnText: { color: '#fff', fontSize: 13, fontWeight: '800' },
});
