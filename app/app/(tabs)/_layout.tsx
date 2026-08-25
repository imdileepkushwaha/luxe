import React from 'react';
import { Tabs } from 'expo-router';
import { Heart, User, Home, LayoutGrid } from 'lucide-react-native';
import { Platform, StyleSheet } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import TabIcon from '@/components/TabIcon';
import { useAppTheme } from '@/context/ThemeContext';

export default function TabLayout() {
  const insets = useSafeAreaInsets();
  const { colors, isDark } = useAppTheme();
  const bottomPad = Platform.OS === 'web' ? 8 : Math.max(insets.bottom, 8);
  const barHeight = 64 + bottomPad;

  return (
    <Tabs
      safeAreaInsets={{ bottom: 0 }}
      screenOptions={{
        headerShown: false,
        tabBarShowLabel: false,
        tabBarHideOnKeyboard: true,
        tabBarActiveTintColor: colors.tabActive,
        tabBarInactiveTintColor: colors.tabInactive,
        tabBarStyle: {
          position: 'absolute',
          left: 0,
          right: 0,
          bottom: 0,
          height: barHeight,
          paddingTop: 8,
          paddingBottom: bottomPad,
          marginHorizontal: 0,
          backgroundColor: colors.tabBar,
          borderTopWidth: StyleSheet.hairlineWidth,
          borderTopColor: colors.tabBorder,
          borderTopLeftRadius: 18,
          borderTopRightRadius: 18,
          elevation: 12,
          shadowColor: colors.shadowColor,
          shadowOpacity: isDark ? 0.35 : 0.08,
          shadowRadius: 12,
          shadowOffset: { width: 0, height: -4 },
        },
        tabBarItemStyle: {
          height: 54,
          paddingVertical: 0,
          justifyContent: 'center',
        },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Home',
          tabBarIcon: ({ focused }) => <TabIcon icon={Home} label="Home" focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="shop"
        options={{
          title: 'Shop',
          tabBarIcon: ({ focused }) => <TabIcon icon={LayoutGrid} label="Shop" focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="wishlist"
        options={{
          title: 'Wishlist',
          tabBarIcon: ({ focused }) => <TabIcon icon={Heart} label="Wishlist" focused={focused} />,
        }}
      />
      <Tabs.Screen
        name="profile"
        options={{
          title: 'Profile',
          tabBarIcon: ({ focused }) => <TabIcon icon={User} label="Profile" focused={focused} />,
        }}
      />
    </Tabs>
  );
}
