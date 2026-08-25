import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import type { LucideIcon } from 'lucide-react-native';
import { useAppTheme } from '@/context/ThemeContext';

type Props = {
  icon: LucideIcon;
  label: string;
  focused: boolean;
};

export default function TabIcon({ icon: Icon, label, focused }: Props) {
  const { colors } = useAppTheme();
  const color = focused ? colors.tabActive : colors.tabInactive;

  return (
    <View style={styles.wrap}>
      <View style={[styles.iconBox, focused && { backgroundColor: colors.tabActiveBg }]}>
        <Icon
          size={20}
          color={color}
          strokeWidth={focused ? 2.3 : 1.8}
          fill={focused ? colors.tabActiveBg : 'transparent'}
        />
      </View>
      <Text style={[styles.label, { color: colors.tabInactive }, focused && { color: colors.tabActive, fontWeight: '700' }]} numberOfLines={1}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    alignItems: 'center',
    justifyContent: 'center',
    minWidth: 64,
    paddingTop: 2,
  },
  iconBox: {
    width: 44,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  label: {
    marginTop: 2,
    fontSize: 10,
    fontWeight: '600',
    letterSpacing: 0.2,
  },
});
