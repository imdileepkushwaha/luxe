import React from 'react';
import { StyleSheet, Text, View, Pressable, Platform, StatusBar as RNStatusBar } from 'react-native';
import { ShoppingBag, ChevronLeft, Search, Heart } from 'lucide-react-native';
import { useRouter } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useCart } from '@/context/CartContext';
import { useWishlist } from '@/context/WishlistContext';
import { useAppTheme } from '@/context/ThemeContext';

interface LuxeHeaderProps {
  title?: string;
  showBack?: boolean;
  onSearchPress?: () => void;
}

function HeaderIcon({
  onPress,
  label,
  children,
  bg,
  border,
}: {
  onPress: () => void;
  label: string;
  children: React.ReactNode;
  bg: string;
  border: string;
}) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={onPress}
      hitSlop={6}
      style={({ pressed }) => [
        styles.iconBtn,
        { backgroundColor: bg, borderColor: border, opacity: pressed ? 0.72 : 1 },
      ]}
    >
      {children}
    </Pressable>
  );
}

function CountBadge({ count, color, border }: { count: number; color: string; border: string }) {
  if (count <= 0) return null;
  return (
    <View style={[styles.badge, { backgroundColor: color, borderColor: border }]}>
      <Text style={styles.badgeText}>{count > 9 ? '9+' : count}</Text>
    </View>
  );
}

export default function LuxeHeader({ title, showBack = false, onSearchPress }: LuxeHeaderProps) {
  const router = useRouter();
  const { cartCount } = useCart();
  const { wishlist } = useWishlist();
  const { colors, isDark } = useAppTheme();
  const insets = useSafeAreaInsets();
  const topInset = Math.max(
    insets.top,
    Platform.OS === 'android' ? RNStatusBar.currentHeight || 0 : 0
  );

  const accent = isDark ? '#c4b5fd' : '#ef4444';
  const badgeColor = isDark ? '#8b5cf6' : '#ef4444';
  const iconBg = isDark ? 'rgba(255,255,255,0.08)' : '#f8fafc';
  const iconBorder = isDark ? 'rgba(255,255,255,0.12)' : 'rgba(15,23,42,0.06)';

  const goSearch = () => {
    if (onSearchPress) {
      onSearchPress();
      return;
    }
    router.push('/(tabs)/shop');
  };

  return (
    <View
      style={[
        styles.headerWrapper,
        {
          paddingTop: topInset,
          paddingLeft: Math.max(insets.left, 0),
          paddingRight: Math.max(insets.right, 0),
          backgroundColor: isDark ? 'rgba(12,12,20,0.94)' : '#ffffff',
          borderBottomColor: isDark ? 'rgba(255,255,255,0.08)' : '#ececec',
          shadowColor: isDark ? '#000' : 'rgba(15,23,42,0.12)',
        },
      ]}
    >
      <View style={styles.header}>
        {showBack ? (
          <HeaderIcon label="Back" onPress={() => router.back()} bg={iconBg} border={iconBorder}>
            <ChevronLeft size={22} color={colors.icon} />
          </HeaderIcon>
        ) : (
          <Pressable
            onPress={() => router.push('/(tabs)')}
            style={styles.brand}
            accessibilityRole="button"
            accessibilityLabel="LUXE home"
          >
            <Text style={[styles.logo, { color: colors.text }]}>LUXE</Text>
            <View style={[styles.logoDot, { backgroundColor: accent }]} />
          </Pressable>
        )}

        {showBack ? (
          <Text style={[styles.pageTitle, { color: colors.text }]} numberOfLines={1}>
            {title || ''}
          </Text>
        ) : (
          <View style={styles.spacer} />
        )}

        <View style={styles.actions}>
          <HeaderIcon label="Search" onPress={goSearch} bg={iconBg} border={iconBorder}>
            <Search size={18} color={colors.icon} strokeWidth={2} />
          </HeaderIcon>

          {!showBack && (
            <HeaderIcon
              label="Wishlist"
              onPress={() => router.push('/(tabs)/wishlist')}
              bg={iconBg}
              border={iconBorder}
            >
              <Heart size={18} color={colors.icon} strokeWidth={2} />
              <CountBadge count={wishlist.length} color={badgeColor} border={isDark ? '#0c0c14' : '#ffffff'} />
            </HeaderIcon>
          )}

          <HeaderIcon label="Bag" onPress={() => router.push('/cart')} bg={iconBg} border={iconBorder}>
            <ShoppingBag size={18} color={colors.icon} strokeWidth={2} />
            <CountBadge count={cartCount} color={badgeColor} border={isDark ? '#0c0c14' : '#ffffff'} />
          </HeaderIcon>
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  headerWrapper: {
    zIndex: 100,
    borderBottomWidth: StyleSheet.hairlineWidth,
    ...Platform.select({
      ios: {
        shadowOpacity: 0.08,
        shadowRadius: 10,
        shadowOffset: { width: 0, height: 4 },
      },
      android: { elevation: 3 },
      web: { boxShadow: '0 1px 0 rgba(15,23,42,0.06)' } as object,
    }),
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    minHeight: 56,
    gap: 8,
  },
  brand: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 4,
    paddingRight: 8,
  },
  logo: {
    fontSize: 22,
    fontWeight: '700',
    letterSpacing: 2.4,
    fontFamily: Platform.OS === 'ios' ? 'Georgia' : 'serif',
  },
  logoDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    marginLeft: 3,
  },
  spacer: { flex: 1 },
  pageTitle: {
    flex: 1,
    textAlign: 'center',
    fontSize: 16,
    fontWeight: '700',
    letterSpacing: 0.3,
    paddingHorizontal: 4,
  },
  actions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    position: 'relative',
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -4,
    minWidth: 16,
    height: 16,
    paddingHorizontal: 4,
    borderRadius: 8,
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 1,
    borderWidth: 1.5,
  },
  badgeText: {
    color: '#fff',
    fontSize: 8,
    fontWeight: '800',
  },
});
