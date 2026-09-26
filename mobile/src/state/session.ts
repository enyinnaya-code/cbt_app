import { create } from 'zustand';
import { clearStudentData, kvDelete, kvGet, kvSet } from '@/db/progress';
import { tokenStore } from '@/platform/native';
import type { User } from '@/core/types';
import { services } from './services';

interface SessionState {
  status: 'loading' | 'out' | 'in';
  token: string | null;
  user: User | null;
  /** The server no longer accepts this phone's token. The app keeps working offline; syncing needs a new sign-in. */
  needsReauth: boolean;
  hydrate: () => Promise<void>;
  signIn: (token: string, user: User) => Promise<void>;
  signOut: () => Promise<void>;
  markNeedsReauth: () => void;
  updateUser: (user: User) => Promise<void>;
}

const USER_KEY = 'auth.user';

export const useSession = create<SessionState>((set, get) => ({
  status: 'loading',
  token: null,
  user: null,
  needsReauth: false,

  /** Reads the saved sign-in from the phone. Works with no internet: the token is never checked at start-up. */
  async hydrate() {
    const token = await tokenStore.get();
    const raw = await kvGet(services().db, USER_KEY);
    let user: User | null = null;
    try { user = raw ? (JSON.parse(raw) as User) : null; } catch { user = null; }

    set(token && user ? { status: 'in', token, user } : { status: 'out', token: null, user: null });
  },

  async signIn(token, user) {
    await tokenStore.set(token);
    await kvSet(services().db, USER_KEY, JSON.stringify(user));
    set({ status: 'in', token, user, needsReauth: false });
  },

  /** Also wipes this student's records from the phone, so the next person to sign in starts clean. */
  async signOut() {
    const { db } = services();
    await tokenStore.clear();
    await kvDelete(db, USER_KEY);
    await clearStudentData(db);
    set({ status: 'out', token: null, user: null, needsReauth: false });
  },

  markNeedsReauth() {
    if (get().status === 'in') set({ needsReauth: true });
  },

  async updateUser(user) {
    await kvSet(services().db, USER_KEY, JSON.stringify(user));
    set({ user });
  },
}));
