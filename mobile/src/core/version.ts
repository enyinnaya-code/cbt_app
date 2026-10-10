/** The version of the app on this phone, from app.json. */
export const INSTALLED_VERSION: string = (require('../../app.json') as { expo: { version: string } }).expo.version;

/** "1.10.2" becomes [1, 10, 2]. Anything that is not made of numbers separated by dots is not a version. */
export function parseVersion(text: unknown): number[] | null {
  if (typeof text !== 'string') return null;
  const m = /^v?(\d{1,4}(?:\.\d{1,4}){0,3})(?:[-+].*)?$/.exec(text.trim());
  return m ? m[1].split('.').map(Number) : null;
}

/** True only when `latest` is clearly a higher version than `installed`. Anything unreadable counts as "not newer". */
export function isNewer(latest: unknown, installed: unknown): boolean {
  const a = parseVersion(latest);
  const b = parseVersion(installed);
  if (!a || !b) return false;

  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    const x = a[i] ?? 0;
    const y = b[i] ?? 0;
    if (x !== y) return x > y;
  }
  return false;
}
