/** Small formatting helpers. No framework code, so they are easy to test. */

export function formatBytes(bytes: number): string {
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${bytes} B`;
}

/** 5 days from now at midnight is "5", today is "0", past dates are null. */
export function daysUntil(dateIso: string | null | undefined, now: Date = new Date()): number | null {
  if (!dateIso) return null;
  const target = new Date(`${dateIso.slice(0, 10)}T00:00:00`);
  if (Number.isNaN(target.getTime())) return null;
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const days = Math.round((target.getTime() - today.getTime()) / 86400000);
  return days >= 0 ? days : null;
}

export function greeting(now: Date = new Date()): string {
  const h = now.getHours();
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

/** 3725 seconds is "1:02:05"; 125 seconds is "02:05". */
export function clock(totalSeconds: number): string {
  const s = Math.max(0, Math.floor(totalSeconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  const mm = String(m).padStart(2, '0');
  const ss = String(sec).padStart(2, '0');
  return h ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
}

/** "12 March 2026 9:24 pm", the app-wide date format (see the website's date rule). */
export function dateTime(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const h = d.getHours();
  const min = String(d.getMinutes()).padStart(2, '0');
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()} ${h % 12 || 12}:${min} ${h < 12 ? 'am' : 'pm'}`;
}

/** "12 March 2026", the date part of the app-wide format. */
export function formatDate(iso: string): string {
  return dateTime(iso).replace(/ \d{1,2}:\d{2} (am|pm)$/, '');
}

/** 1500 is "₦1,500". */
export function naira(amount: number): string {
  return `\u20A6${Math.round(amount).toLocaleString('en-NG')}`;
}

export function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
}

/** "42 seconds ago", "3 hours ago", "yesterday"... good enough for a Home card. */
export function ago(iso: string, now: Date = new Date()): string {
  const secs = Math.max(0, Math.round((now.getTime() - new Date(iso).getTime()) / 1000));
  if (secs < 60) return 'just now';
  if (secs < 3600) { const m = Math.round(secs / 60); return `${m} ${m === 1 ? 'minute' : 'minutes'} ago`; }
  if (secs < 86400) { const h = Math.round(secs / 3600); return `${h} ${h === 1 ? 'hour' : 'hours'} ago`; }
  const d = Math.round(secs / 86400);
  return d === 1 ? 'yesterday' : `${d} days ago`;
}

export function plural(n: number, one: string, many = `${one}s`): string {
  return n === 1 ? one : many;
}
