import React from 'react';
import { StyleSheet, Text, View, TouchableOpacity } from 'react-native';
import { RefreshCw } from 'lucide-react-native';
import { useAppTheme } from '@/context/ThemeContext';

type Props = {
  title: string;
  message: string;
  actionLabel?: string;
  onAction?: () => void;
};

export default function EmptyState({ title, message, actionLabel = 'Try again', onAction }: Props) {
  const { colors, isDark } = useAppTheme();
  return (
    <View style={styles.box}>
      <Text style={[styles.title, { color: colors.text }]}>{title}</Text>
      <Text style={[styles.message, { color: colors.muted }]}>{message}</Text>
      {onAction && (
        <TouchableOpacity
          style={[
            styles.btn,
            {
              backgroundColor: isDark ? 'rgba(139,92,246,0.15)' : colors.primarySoft,
              borderColor: isDark ? 'rgba(139,92,246,0.5)' : colors.borderStrong,
            },
          ]}
          onPress={onAction}
        >
          <RefreshCw size={14} color={isDark ? '#c4b5fd' : colors.icon} />
          <Text style={[styles.btnText, { color: isDark ? '#c4b5fd' : colors.text }]}>{actionLabel}</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  box: { width: '100%', alignItems: 'center', paddingVertical: 48, paddingHorizontal: 24 },
  title: { fontSize: 18, fontWeight: '700', marginBottom: 8, textAlign: 'center' },
  message: { fontSize: 14, lineHeight: 20, textAlign: 'center', marginBottom: 18 },
  btn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 12,
  },
  btnText: { fontWeight: '700', fontSize: 13 },
});
