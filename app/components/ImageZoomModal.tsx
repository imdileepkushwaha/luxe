import React, { useEffect, useRef, useState } from 'react';
import {
  Modal,
  View,
  Image,
  Pressable,
  Text,
  StyleSheet,
  Dimensions,
  Platform,
  ScrollView,
} from 'react-native';
import { X, ZoomIn, ZoomOut, ChevronLeft, ChevronRight } from 'lucide-react-native';

const { width: SCREEN_W, height: SCREEN_H } = Dimensions.get('window');
const MIN_ZOOM = 1;
const MAX_ZOOM = 4;

type Props = {
  visible: boolean;
  images: string[];
  index: number;
  onClose: () => void;
  onIndexChange?: (index: number) => void;
};

export default function ImageZoomModal({ visible, images, index, onClose, onIndexChange }: Props) {
  const [current, setCurrent] = useState(index);
  const [scale, setScale] = useState(1);
  const lastTap = useRef(0);

  useEffect(() => {
    if (visible) {
      setCurrent(index);
      setScale(1);
    }
  }, [visible, index]);

  const clampZoom = (value: number) => Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Number(value.toFixed(2))));

  const zoomBy = (delta: number) => setScale((s) => clampZoom(s + delta));

  const goTo = (next: number) => {
    if (next < 0 || next >= images.length) return;
    setCurrent(next);
    setScale(1);
    onIndexChange?.(next);
  };

  const handleImagePress = () => {
    const now = Date.now();
    if (now - lastTap.current < 280) {
      setScale((s) => (s > 1.15 ? 1 : 2.4));
    }
    lastTap.current = now;
  };

  const uri = images[current];
  const imgW = SCREEN_W;
  const imgH = SCREEN_H * 0.72;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View
        style={styles.backdrop}
        {...(Platform.OS === 'web'
          ? {
              onWheel: (e: any) => {
                const dy = e?.nativeEvent?.deltaY ?? e?.deltaY ?? 0;
                if (dy) zoomBy(dy > 0 ? -0.2 : 0.2);
              },
            }
          : {})}
      >
        <View style={styles.topBar}>
          <Pressable onPress={onClose} style={styles.iconBtn} hitSlop={10}>
            <X size={22} color="#fff" />
          </Pressable>
          <Text style={styles.counter}>
            {images.length ? `${current + 1} / ${images.length}` : ''}
          </Text>
          <View style={styles.zoomBtns}>
            <Pressable onPress={() => zoomBy(-0.4)} style={styles.iconBtn} hitSlop={8}>
              <ZoomOut size={20} color="#fff" />
            </Pressable>
            <Text style={styles.zoomLabel}>{Math.round(scale * 100)}%</Text>
            <Pressable onPress={() => zoomBy(0.4)} style={styles.iconBtn} hitSlop={8}>
              <ZoomIn size={20} color="#fff" />
            </Pressable>
          </View>
        </View>

        <ScrollView
          style={styles.scroller}
          contentContainerStyle={[
            styles.scrollerContent,
            {
              width: imgW * scale,
              minHeight: imgH * scale,
            },
          ]}
          maximumZoomScale={MAX_ZOOM}
          minimumZoomScale={MIN_ZOOM}
          centerContent
          bouncesZoom
          showsHorizontalScrollIndicator={false}
          showsVerticalScrollIndicator={false}
        >
          <Pressable onPress={handleImagePress}>
            {uri ? (
              <Image
                source={{ uri }}
                style={{ width: imgW * scale, height: imgH * scale }}
                resizeMode="contain"
              />
            ) : null}
          </Pressable>
        </ScrollView>

        {images.length > 1 && (
          <>
            {current > 0 && (
              <Pressable style={[styles.nav, styles.navLeft]} onPress={() => goTo(current - 1)}>
                <ChevronLeft size={26} color="#fff" />
              </Pressable>
            )}
            {current < images.length - 1 && (
              <Pressable style={[styles.nav, styles.navRight]} onPress={() => goTo(current + 1)}>
                <ChevronRight size={26} color="#fff" />
              </Pressable>
            )}
          </>
        )}

        <Text style={styles.hint}>
          {Platform.OS === 'web' ? 'Scroll to zoom · double-click to toggle' : 'Pinch or double-tap to zoom'}
        </Text>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.96)',
  },
  topBar: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 12,
    paddingTop: Platform.OS === 'ios' ? 54 : 16,
    paddingBottom: 10,
    zIndex: 2,
  },
  counter: { color: '#fff', fontSize: 14, fontWeight: '700' },
  zoomBtns: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  zoomLabel: { color: 'rgba(255,255,255,0.7)', fontSize: 12, fontWeight: '700', minWidth: 40, textAlign: 'center' },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  scroller: { flex: 1 },
  scrollerContent: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  nav: {
    position: 'absolute',
    top: '50%',
    marginTop: -22,
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255,255,255,0.12)',
    alignItems: 'center',
    justifyContent: 'center',
    zIndex: 3,
  },
  navLeft: { left: 10 },
  navRight: { right: 10 },
  hint: {
    textAlign: 'center',
    color: 'rgba(255,255,255,0.4)',
    fontSize: 12,
    paddingBottom: Platform.OS === 'ios' ? 28 : 16,
  },
});
