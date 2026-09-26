import { create } from 'zustand';
import { kvGet, kvSet } from '@/db/progress';
import type { ThemePreference } from '@/ui/theme';
import { services } from './services';

export interface SettingsState {
  hydrated: boolean;
  theme: ThemePreference;
  /** Which explanation to show first when a question has both. */
  lang: 'en' | 'pcm';
  wifiOnly: boolean;
  targetExam: string | null;
  targetExamDate: string | null;
  seenWelcome: boolean;
  hydrate: () => Promise<void>;
  set: (patch: Partial<Omit<SettingsState, 'hydrated' | 'hydrate' | 'set'>>) => Promise<void>;
}

const KEY = 'settings';

type Saved = Pick<SettingsState, 'theme' | 'lang' | 'wifiOnly' | 'targetExam' | 'targetExamDate' | 'seenWelcome'>;

const defaults: Saved = { theme: 'system', lang: 'en', wifiOnly: true, targetExam: null, targetExamDate: null, seenWelcome: false };

export const useSettings = create<SettingsState>((set, get) => ({
  ...defaults,
  hydrated: false,

  async hydrate() {
    const raw = await kvGet(services().db, KEY);
    let saved: Partial<Saved> = {};
    try { saved = raw ? (JSON.parse(raw) as Partial<Saved>) : {}; } catch { saved = {}; }
    set({ ...defaults, ...saved, hydrated: true });
  },

  async set(patch) {
    set(patch);
    const s = get();
    const toSave: Saved = { theme: s.theme, lang: s.lang, wifiOnly: s.wifiOnly, targetExam: s.targetExam, targetExamDate: s.targetExamDate, seenWelcome: s.seenWelcome };
    await kvSet(services().db, KEY, JSON.stringify(toSave));
  },
}));
