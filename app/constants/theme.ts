export type ColorSchemeName = 'light' | 'dark';

export type ThemeColors = {
  scheme: ColorSchemeName;
  bg: string;
  bgAlt: string;
  bgGradient: [string, string, string];
  text: string;
  textSecondary: string;
  muted: string;
  card: string;
  cardMuted: string;
  border: string;
  borderStrong: string;
  hairline: string;
  overlay: string;
  modal: string;
  input: string;
  inputBorder: string;
  placeholder: string;
  icon: string;
  iconMuted: string;
  inverse: string;
  primary: string;
  primarySoft: string;
  accent: string;
  cta: [string, string];
  tabBar: string;
  tabBorder: string;
  tabActive: string;
  tabInactive: string;
  tabActiveBg: string;
  headerBtn: string;
  headerBtnBorder: string;
  danger: string;
  dangerBg: string;
  dangerText: string;
  gold: string;
  goldBg: string;
  goldText: string;
  success: string;
  purple: string;
  purpleSoft: string;
  productImageBg: string;
  blobOpacity: number;
  statusBar: 'light' | 'dark';
  shadowColor: string;
};

export const darkColors: ThemeColors = {
  scheme: 'dark',
  bg: '#08080e',
  bgAlt: '#0c0c14',
  bgGradient: ['#080812', '#12122a', '#080812'],
  text: '#ffffff',
  textSecondary: '#e2e8f0',
  muted: '#94a3b8',
  card: 'rgba(255,255,255,0.06)',
  cardMuted: 'rgba(8,8,14,0.35)',
  border: 'rgba(255,255,255,0.12)',
  borderStrong: 'rgba(255,255,255,0.18)',
  hairline: 'rgba(255,255,255,0.1)',
  overlay: 'rgba(0,0,0,0.55)',
  modal: '#12121a',
  input: 'rgba(255,255,255,0.06)',
  inputBorder: 'rgba(255,255,255,0.14)',
  placeholder: '#94a3b8',
  icon: '#ffffff',
  iconMuted: '#cbd5e1',
  inverse: '#0b0b10',
  primary: '#7c3aed',
  primarySoft: 'rgba(139,92,246,0.18)',
  accent: '#8b5cf6',
  cta: ['#8b5cf6', '#db2777'],
  tabBar: 'rgba(10, 10, 16, 0.96)',
  tabBorder: 'rgba(255,255,255,0.1)',
  tabActive: '#ddd6fe',
  tabInactive: 'rgba(255,255,255,0.45)',
  tabActiveBg: 'rgba(139,92,246,0.28)',
  headerBtn: 'rgba(255,255,255,0.07)',
  headerBtnBorder: 'rgba(255,255,255,0.1)',
  danger: '#fca5a5',
  dangerBg: 'rgba(248,113,113,0.16)',
  dangerText: '#fca5a5',
  gold: '#fbbf24',
  goldBg: 'rgba(251,191,36,0.12)',
  goldText: '#fde68a',
  success: '#34d399',
  purple: '#c4b5fd',
  purpleSoft: 'rgba(139,92,246,0.22)',
  productImageBg: '#14141f',
  blobOpacity: 0.25,
  statusBar: 'light',
  shadowColor: '#000',
};

/** Light palette aligned with theme-3 storefront (`--bg`, `--text`, `--card`, `--line`). */
export const lightColors: ThemeColors = {
  scheme: 'light',
  bg: '#ffffff',
  bgAlt: '#f8fafc',
  bgGradient: ['#ffffff', '#f8fafc', '#f1f5f9'],
  text: '#0f172a',
  textSecondary: '#334155',
  muted: '#64748b',
  card: '#ffffff',
  cardMuted: '#f8fafc',
  border: 'rgba(15, 23, 42, 0.08)',
  borderStrong: 'rgba(15, 23, 42, 0.14)',
  hairline: '#ececec',
  overlay: 'rgba(15, 23, 42, 0.45)',
  modal: '#ffffff',
  input: '#f8fafc',
  inputBorder: 'rgba(15, 23, 42, 0.1)',
  placeholder: '#94a3b8',
  icon: '#0f172a',
  iconMuted: '#64748b',
  inverse: '#ffffff',
  primary: '#0f172a',
  primarySoft: 'rgba(15, 23, 42, 0.06)',
  accent: '#ef4444',
  cta: ['#0f172a', '#1e293b'],
  tabBar: '#ffffff',
  tabBorder: 'rgba(15, 23, 42, 0.08)',
  tabActive: '#0f172a',
  tabInactive: '#94a3b8',
  tabActiveBg: 'rgba(15, 23, 42, 0.06)',
  headerBtn: '#f8fafc',
  headerBtnBorder: 'rgba(15, 23, 42, 0.08)',
  danger: '#dc2626',
  dangerBg: 'rgba(220, 38, 38, 0.08)',
  dangerText: '#b91c1c',
  gold: '#d97706',
  goldBg: 'rgba(245, 158, 11, 0.12)',
  goldText: '#92400e',
  success: '#059669',
  purple: '#7c3aed',
  purpleSoft: 'rgba(124, 58, 237, 0.1)',
  productImageBg: '#f1f5f9',
  blobOpacity: 0.08,
  statusBar: 'dark',
  shadowColor: 'rgba(15, 23, 42, 0.08)',
};

export const palettes = { dark: darkColors, light: lightColors } as const;
