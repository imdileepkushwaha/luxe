import React from 'react';
import { StyleSheet, View, ViewStyle, Platform } from 'react-native';
import { BlurView } from 'expo-blur';
import { LinearGradient } from 'expo-linear-gradient';
import { useAppTheme } from '@/context/ThemeContext';

interface GlassCardProps {
  children: React.ReactNode;
  style?: ViewStyle;
  intensity?: number;
  tint?: 'light' | 'dark' | 'default';
  borderRadius?: number;
  borderWidth?: number;
}

export default function GlassCard({
  children,
  style,
  intensity = 50,
  tint = 'dark',
  borderRadius = 25,
  borderWidth = 1,
}: GlassCardProps) {
  const isWeb = Platform.OS === 'web';
  const { isDark, colors } = useAppTheme();

  return (
    <View style={[
      styles.container, 
      { borderRadius, backgroundColor: isDark ? 'rgba(255, 255, 255, 0.02)' : colors.card }, 
      isWeb && { boxShadow: isDark ? '0 4px 30px rgba(0, 0, 0, 0.5)' : '0 8px 32px rgba(15, 23, 42, 0.07)' },
      style
    ]}>
      <BlurView 
        intensity={isDark ? intensity : 10} 
        tint={isDark ? tint : 'light'} 
        style={[styles.blur, { borderRadius }]}
      >
        <LinearGradient
          colors={isDark ? ['rgba(255, 255, 255, 0.1)', 'rgba(255, 255, 255, 0.05)'] : ['#ffffff', '#f8fafc']}
          start={{ x: 0, y: 0 }}
          end={{ x: 1, y: 1 }}
          style={[styles.gradient, { borderRadius }]}
        >
          <View style={styles.content}>{children}</View>
        </LinearGradient>
      </BlurView>
      
      {/* Preciss Border */}
      <View style={[
        styles.border, 
        { 
          borderRadius, 
          borderWidth,
          borderColor: isDark ? 'rgba(255, 255, 255, 0.1)' : colors.border,
        }
      ]} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    overflow: 'hidden',
    backgroundColor: 'rgba(255, 255, 255, 0.02)',
  },
  blur: {
    flex: 1,
  },
  gradient: {
    flex: 1,
  },
  content: {
    padding: 0, // Padding handled by internal components for more control
  },
  border: {
    ...StyleSheet.absoluteFillObject,
    pointerEvents: 'none',
  }
});
