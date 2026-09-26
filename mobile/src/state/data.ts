/**
 * What the phone knows about content: the catalog, which packs are installed, downloads in progress and
 * whether there is a connection. Screens read from here and call its actions.
 */
import { create } from 'zustand';
import type { Catalog, CatalogSubject } from '@/core/types';
import { kvGet, kvSet } from '@/db/progress';
import { ApiError } from '@/services/api';
import { CatalogIndex, cachedCatalog, refreshCatalog } from '@/services/catalog';
import { PackError, type PackMeta } from '@/services/packs';
import { services } from './services';
import { useSettings } from './settings';

export interface DownloadState {
  done: number;
  total: number;
  error?: string;
}

interface DataState {
  catalog: Catalog | null;
  index: CatalogIndex;
  installed: PackMeta[];
  downloads: Record<string, DownloadState>;
  online: boolean;
  onWifi: boolean;
  syncing: boolean;
  lastSyncedAt: string | null;
  pending: number;
  /** The student went to the website to unlock something: refresh the catalog as soon as they are back. */
  awaitingPurchase: boolean;

  setAwaitingPurchase: (v: boolean) => void;
  syncTiers: () => Promise<void>;
  loadFromPhone: () => Promise<void>;
  refreshCatalogNow: () => Promise<void>;
  refreshInstalled: () => Promise<void>;
  setNetwork: (online: boolean, onWifi: boolean) => void;
  download: (examSlug: string, subject: CatalogSubject) => Promise<void>;
  cancelDownload: (key: string) => void;
  removePack: (examSlug: string, subjectSlug: string) => Promise<void>;
  syncNow: () => Promise<void>;
  refreshPending: () => Promise<void>;
}

const controllers = new Map<string, AbortController>();
export const packKey = (exam: string, subject: string) => `${exam}/${subject}`;

export const useData = create<DataState>((set, get) => ({
  catalog: null,
  index: new CatalogIndex(null),
  installed: [],
  downloads: {},
  online: true,
  onWifi: false,
  syncing: false,
  lastSyncedAt: null,
  pending: 0,
  awaitingPurchase: false,

  setAwaitingPurchase(v) {
    set({ awaitingPurchase: v });
  },

  async loadFromPhone() {
    const { db, packs } = services();
    const catalog = await cachedCatalog(db);
    set({ catalog, index: new CatalogIndex(catalog), installed: await packs.installed(), lastSyncedAt: await kvGet(db, 'sync.at') });
    await get().refreshPending();
  },

  /** Quietly asks the server whether anything changed. Costs almost no data when nothing did. */
  async refreshCatalogNow() {
    const { db, api } = services();
    try {
      const { catalog } = await refreshCatalog(db, api);
      set({ catalog, index: new CatalogIndex(catalog) });
      void get().syncTiers();
    } catch {
      // Offline or a server hiccup: keep using what is on the phone.
    }
  },

  /**
   * Keeps each downloaded subject in step with what the student may use: after a purchase the free sample is replaced
   * by the full pack, and when a purchase runs out the full pack goes back to the sample. Respects "Wi-Fi only".
   */
  async syncTiers() {
    const { catalog, online, onWifi } = get();
    if (!catalog || !online) return;
    if (useSettings.getState().wifiOnly && !onWifi) return;

    const installed = await services().packs.installed();
    for (const exam of catalog.exams) {
      for (const subject of exam.subjects) {
        const have = installed.find((i) => i.exam_slug === exam.slug && i.subject_slug === subject.slug);
        if (have && !have.starter && subject.pack && (subject.pack.tier ?? 'full') !== have.tier) void get().download(exam.slug, subject);
      }
    }
  },

  async refreshInstalled() {
    set({ installed: await services().packs.installed() });
  },

  setNetwork(online, onWifi) {
    set({ online, onWifi });
  },

  async download(examSlug, subject) {
    const { packs } = services();
    const key = packKey(examSlug, subject.slug);
    if (get().downloads[key] && !get().downloads[key].error) return;

    const controller = new AbortController();
    controllers.set(key, controller);
    set((s) => ({ downloads: { ...s.downloads, [key]: { done: 0, total: subject.pack?.size_bytes ?? 0 } } }));

    try {
      await packs.download(examSlug, subject, (done, total) => {
        set((s) => ({ downloads: { ...s.downloads, [key]: { done, total: total > 0 ? total : subject.pack?.size_bytes ?? 0 } } }));
      }, controller.signal);

      await get().refreshInstalled();
      set((s) => { const d = { ...s.downloads }; delete d[key]; return { downloads: d }; });
    } catch (e) {
      if (e instanceof PackError && e.kind === 'cancelled') {
        set((s) => { const d = { ...s.downloads }; delete d[key]; return { downloads: d }; });
        return;
      }
      const message = e instanceof PackError || e instanceof ApiError ? e.message : 'The download failed. Please try again.';
      set((s) => ({ downloads: { ...s.downloads, [key]: { done: 0, total: 0, error: message } } }));
    } finally {
      controllers.delete(key);
    }
  },

  cancelDownload(key) {
    controllers.get(key)?.abort();
    set((s) => { const d = { ...s.downloads }; delete d[key]; return { downloads: d }; });
  },

  async removePack(examSlug, subjectSlug) {
    await services().packs.remove(examSlug, subjectSlug);
    await get().refreshInstalled();
  },

  async refreshPending() {
    set({ pending: await services().sync.pending() });
  },

  /** Uploads what is waiting and downloads anything new. Does nothing when offline or signed out. */
  async syncNow() {
    if (get().syncing || !get().online) return;
    const { sync, db } = services();
    set({ syncing: true });
    try {
      await sync.run();
      const at = new Date().toISOString();
      await kvSet(db, 'sync.at', at);
      set({ lastSyncedAt: at });
    } catch {
      // Try again next time; nothing is lost because everything is saved on the phone first.
    } finally {
      set({ syncing: false });
      await get().refreshPending();
    }
  },
}));
