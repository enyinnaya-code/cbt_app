import { useFocusEffect } from 'expo-router';
import { useCallback, useRef, useState } from 'react';

/**
 * Runs an async loader every time a screen comes into view, so lists are fresh after the student goes
 * away and back. Keeps showing the old data while reloading, so screens do not flicker.
 */
export function useFocusLoad<T>(load: () => Promise<T>): { data: T | null; loading: boolean; reload: () => void } {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const loadRef = useRef(load);
  loadRef.current = load;
  const [tick, setTick] = useState(0);

  useFocusEffect(
    useCallback(() => {
      let live = true;
      setLoading(true);
      loadRef.current()
        .then((d) => { if (live) setData(d); })
        .catch(() => { /* keep the previous data; a failed local read should not crash a screen */ })
        .finally(() => { if (live) setLoading(false); });
      return () => { live = false; };
    }, [tick]),
  );

  return { data, loading, reload: () => setTick((t) => t + 1) };
}
