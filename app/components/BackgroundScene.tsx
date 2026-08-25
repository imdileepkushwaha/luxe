import React from 'react';
import { StyleSheet, View, Dimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { useAppTheme } from '@/context/ThemeContext';

const { width, height } = Dimensions.get('window');

export default function BackgroundScene() {
  const { colors, isDark } = useAppTheme();

  return (
    <View style={[styles.container, { backgroundColor: colors.bg }]}>
      <LinearGradient colors={colors.bgGradient} style={StyleSheet.absoluteFill} />
      {isDark ? (
        <>
          <View style={[styles.blob, styles.blob1, { opacity: colors.blobOpacity }]} />
          <View style={[styles.blob, styles.blob2, { opacity: colors.blobOpacity }]} />
          <View style={[styles.blob, styles.blob3, { opacity: colors.blobOpacity }]} />
          <View style={styles.gridOverlay} />
        </>
      ) : (
        <>
          <View style={[styles.blob, styles.lightBlob1, { opacity: colors.blobOpacity }]} />
          <View style={[styles.blob, styles.lightBlob2, { opacity: colors.blobOpacity }]} />
        </>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    ...StyleSheet.absoluteFillObject,
    zIndex: -1,
  },
  blob: {
    position: 'absolute',
    borderRadius: 999,
  },
  blob1: {
    width: width * 1.5,
    height: width * 1.5,
    backgroundColor: '#8b5cf6',
    top: -200,
    right: -250,
    filter: 'blur(120px)',
  },
  blob2: {
    width: width * 1.2,
    height: width * 1.2,
    backgroundColor: '#ec4899',
    bottom: -150,
    left: -200,
    filter: 'blur(150px)',
  },
  blob3: {
    width: width * 0.9,
    height: width * 0.9,
    backgroundColor: '#3b82f6',
    top: height * 0.4,
    right: -150,
    filter: 'blur(100px)',
  },
  lightBlob1: {
    width: width * 0.9,
    height: width * 0.9,
    backgroundColor: '#fecaca',
    top: -180,
    right: -120,
    filter: 'blur(90px)',
  },
  lightBlob2: {
    width: width * 0.7,
    height: width * 0.7,
    backgroundColor: '#e2e8f0',
    bottom: -80,
    left: -100,
    filter: 'blur(80px)',
  },
  gridOverlay: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.04,
    backgroundImage: 'radial-gradient(#fff 0.8px, transparent 0.8px)',
    backgroundSize: '35px 35px',
  },
});
