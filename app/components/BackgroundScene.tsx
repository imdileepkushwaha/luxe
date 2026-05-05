import React from 'react';
import { StyleSheet, View, Dimensions } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';

const { width, height } = Dimensions.get('window');

export default function BackgroundScene() {
  return (
    <View style={styles.container}>
      <LinearGradient
        colors={['#080812', '#12122a', '#080812']}
        style={StyleSheet.absoluteFill}
      />
      
      {/* Deep Vibrant Blobs for Dark Elite Theme */}
      <View style={[styles.blob, styles.blob1]} />
      <View style={[styles.blob, styles.blob2]} />
      <View style={[styles.blob, styles.blob3]} />
      
      {/* Premium Grid Pattern Overlay */}
      <View style={styles.gridOverlay} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    ...StyleSheet.absoluteFillObject,
    zIndex: -1,
    backgroundColor: '#080812',
  },
  blob: {
    position: 'absolute',
    borderRadius: 999,
    opacity: 0.25,
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
  gridOverlay: {
    ...StyleSheet.absoluteFillObject,
    opacity: 0.04,
    backgroundImage: 'radial-gradient(#fff 0.8px, transparent 0.8px)',
    backgroundSize: '35px 35px',
  }
});
