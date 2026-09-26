import * as Network from 'expo-network';
import { useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import { installStarterIfNeeded } from '@/services/starter';
import { useData } from '@/state/data';
import { initServices, services } from '@/state/services';
import { useSession } from '@/state/session';
import { useSettings } from '@/state/settings';

/**
 * Starts the app: opens the database, restores the saved sign-in and settings, installs the bundled starter
 * questions on first launch, and keeps an eye on the connection. Nothing here needs the internet, so the
 * app opens straight to Home on a phone that is offline.
 */
export function useBoot(): { ready: boolean; error: string | null } {
  const [ready, setReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        await initServices();
        await useSettings.getState().hydrate();
        await useSession.getState().hydrate();
        await installStarterIfNeeded(services().packs);
        await useData.getState().loadFromPhone();
        if (!cancelled) setReady(true);
      } catch (e) {
        if (!cancelled) setError(e instanceof Error ? e.message : 'The app could not start.');
      }
    })();

    return () => { cancelled = true; };
  }, []);

  return { ready, error };
}

/** After start-up: follow the connection, and quietly refresh and sync whenever it is worth doing. */
export function useBackgroundWork(active: boolean) {
  const status = useSession((s) => s.status);
  const last = useRef(0);

  const work = async () => {
    const { online } = useData.getState();
    if (!online || useSession.getState().status !== 'in') return;

    // At most once a minute: cheap on data and on a small battery. Coming back from the unlock page is the exception:
    // the student is waiting to see what they bought.
    const waiting = useData.getState().awaitingPurchase;
    if (!waiting && Date.now() - last.current < 60000) return;
    last.current = Date.now();
    if (waiting) useData.getState().setAwaitingPurchase(false);

    await useData.getState().refreshCatalogNow();
    if (!useSession.getState().needsReauth) await useData.getState().syncNow();
  };

  useEffect(() => {
    if (!active) return;

    const apply = (s: Network.NetworkState) => {
      const online = !!s.isConnected && s.isInternetReachable !== false;
      useData.getState().setNetwork(online, s.type === Network.NetworkStateType.WIFI || s.type === Network.NetworkStateType.ETHERNET);
      if (online) void work();
    };

    Network.getNetworkStateAsync().then(apply).catch(() => {});
    const net = Network.addNetworkStateListener(apply);
    const app = AppState.addEventListener('change', (s) => { if (s === 'active') void work(); });

    return () => { net.remove(); app.remove(); };
  }, [active, status]);
}
