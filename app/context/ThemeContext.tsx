import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { palettes, type ColorSchemeName, type ThemeColors } from '@/constants/theme';

const STORAGE_KEY = 'luxe_color_scheme';

type ThemeContextValue = {
  scheme: ColorSchemeName;
  isDark: boolean;
  colors: ThemeColors;
  setScheme: (scheme: ColorSchemeName) => void;
};

const ThemeContext = createContext<ThemeContextValue | undefined>(undefined);

function applyDomTheme(scheme: ColorSchemeName, colors: ThemeColors) {
  if (Platform.OS !== 'web' || typeof document === 'undefined') return;
  const root = document.documentElement;
  root.setAttribute('data-theme', scheme);
  root.style.colorScheme = scheme;
  root.style.backgroundColor = colors.bg;
  if (document.body) {
    document.body.style.backgroundColor = colors.bg;
    document.body.style.color = colors.text;
  }
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [scheme, setSchemeState] = useState<ColorSchemeName>('dark');

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const stored = await AsyncStorage.getItem(STORAGE_KEY);
        if (!cancelled && (stored === 'light' || stored === 'dark')) {
          setSchemeState(stored);
        }
      } catch {
        /* keep default */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const setScheme = useCallback((next: ColorSchemeName) => {
    setSchemeState(next);
    AsyncStorage.setItem(STORAGE_KEY, next).catch(() => {});
  }, []);

  const colors = palettes[scheme];

  useEffect(() => {
    applyDomTheme(scheme, colors);
  }, [scheme, colors]);

  const value = useMemo(
    () => ({
      scheme,
      isDark: scheme === 'dark',
      colors,
      setScheme,
    }),
    [scheme, colors, setScheme]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export function useAppTheme() {
  const ctx = useContext(ThemeContext);
  if (!ctx) {
    throw new Error('useAppTheme must be used within ThemeProvider');
  }
  return ctx;
}
