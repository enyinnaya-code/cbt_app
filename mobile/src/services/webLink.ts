import type { Api } from './api';

export type WebPage = '/checkout' | '/orders' | '/pricing';

/**
 * Asks the server for a one-time link that opens the website already signed in, on the page for unlocking subjects.
 * A student who joined with Google has no website password, so this is how they get to pay. The link works once
 * and expires in five minutes; ask for a fresh one each time.
 */
export async function webLink(api: Api, o: { page?: WebPage; exam?: string; subjects?: string[] } = {}): Promise<string> {
  const res = await api.post<{ url: string }>('/web-link', o);
  return assertOurSite(res.data.url, api.baseUrl);
}

/**
 * The link is opened in the phone's browser already signed in, so it must lead to our own website and nowhere else,
 * whatever a server (or someone sitting between the phone and it) sends back. Plain http is only for a development server.
 */
export function assertOurSite(url: string, apiBase: string): string {
  const host = (u: string) => /^(https?):\/\/([^/?#]+)/i.exec(u);
  const link = host(url);
  const api = host(apiBase);
  const secure = link?.[1].toLowerCase() === 'https' || (typeof __DEV__ !== 'undefined' && __DEV__);

  if (!link || !api || !secure || link[2].toLowerCase() !== api[2].toLowerCase()) {
    throw new Error('That link did not look safe, so it was not opened.');
  }
  return url;
}

/** A public page on our own website (for example /forgot-password), found from where the API lives. Null if the API address is unusual. */
export function websitePage(apiBase: string, path: string): string | null {
  const m = /^(https?:\/\/[^/?#]+)/i.exec(apiBase);
  if (!m) return null;
  try {
    return assertOurSite(`${m[1]}${path.startsWith('/') ? path : `/${path}`}`, apiBase);
  } catch {
    return null;
  }
}
