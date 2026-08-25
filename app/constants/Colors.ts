import { darkColors, lightColors } from '@/constants/theme';

export default {
  light: {
    text: lightColors.text,
    background: lightColors.bg,
    tint: lightColors.accent,
    tabIconDefault: lightColors.tabInactive,
    tabIconSelected: lightColors.tabActive,
    border: lightColors.border,
    card: lightColors.card,
    primary: lightColors.primary,
    secondary: lightColors.accent,
    muted: lightColors.muted,
  },
  dark: {
    text: darkColors.text,
    background: darkColors.bg,
    tint: darkColors.accent,
    tabIconDefault: darkColors.tabInactive,
    tabIconSelected: darkColors.tabActive,
    border: darkColors.border,
    card: darkColors.card,
    primary: darkColors.primary,
    secondary: darkColors.accent,
    muted: darkColors.muted,
  },
};
