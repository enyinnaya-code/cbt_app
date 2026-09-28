/** Colours and fonts from the SabiPrep design (the same tokens as the website). No gradients anywhere. */
import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { useColorScheme } from 'react-native';

export interface Palette {
  bg: string; surface: string; surface2: string; text: string; muted: string; border: string;
  primary: string; onPrimary: string; primarySoft: string; primaryInk: string;
  accent: string; accentSoft: string; accentInk: string;
  danger: string; dangerSoft: string;
  blueSoft: string; blueInk: string; plumSoft: string; plumInk: string;
}

export const light: Palette = {
  bg: '#FFFFFF', surface: '#FFFFFF', surface2: '#EEF1EC', text: '#13201A', muted: '#5A6A61', border: '#DCE2DB',
  primary: '#0A7A4A', onPrimary: '#FFFFFF', primarySoft: '#DCEFE4', primaryInk: '#086A40',
  accent: '#E8A200', accentSoft: '#FCF0CF', accentInk: '#7A5400',
  danger: '#C23A2E', dangerSoft: '#FAE3DF',
  blueSoft: '#E2EAF8', blueInk: '#23508F', plumSoft: '#F2E4EF', plumInk: '#7A2E6C',
};

export const dark: Palette = {
  bg: '#0F1512', surface: '#17201B', surface2: '#1F2A24', text: '#E6EEE9', muted: '#98A9A0', border: '#2A3831',
  primary: '#3DBE82', onPrimary: '#05140C', primarySoft: '#163827', primaryInk: '#74D8A8',
  accent: '#F2B73A', accentSoft: '#3A2D10', accentInk: '#F5CB70',
  danger: '#EE7A6E', dangerSoft: '#3A1C19',
  blueSoft: '#1A2842', blueInk: '#94B7F2', plumSoft: '#35202F', plumInk: '#E3A6D6',
};

export const fonts = {
  display: 'BricolageGrotesque_800ExtraBold',
  displayMedium: 'BricolageGrotesque_700Bold',
  body: 'Figtree_400Regular',
  medium: 'Figtree_500Medium',
  semibold: 'Figtree_600SemiBold',
  bold: 'Figtree_700Bold',
} as const;

export type ThemePreference = 'system' | 'light' | 'dark';

interface ThemeValue {
  c: Palette;
  isDark: boolean;
}

const ThemeContext = createContext<ThemeValue>({ c: light, isDark: false });

export function ThemeProvider({ preference, children }: { preference: ThemePreference; children: ReactNode }) {
  const system = useColorScheme();
  const isDark = preference === 'system' ? system === 'dark' : preference === 'dark';
  const value = useMemo<ThemeValue>(() => ({ c: isDark ? dark : light, isDark }), [isDark]);
  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
}

export const useTheme = () => useContext(ThemeContext);

/** Rotating tile colours for subject squares, as in the design. */
export function tone(c: Palette, i: number): { bg: string; fg: string } {
  const tones = [
    { bg: c.blueSoft, fg: c.blueInk },
    { bg: c.accentSoft, fg: c.accentInk },
    { bg: c.primarySoft, fg: c.primaryInk },
    { bg: c.plumSoft, fg: c.plumInk },
  ];
  return tones[Math.abs(i) % tones.length];
}

export const radius = { sm: 10, md: 14, lg: 18, xl: 20, full: 999 } as const;
export const space = { xs: 4, sm: 8, md: 12, lg: 16, xl: 20, xxl: 24 } as const;
