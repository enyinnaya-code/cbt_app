import type { Api } from './api';

export type WebPage = '/checkout' | '/orders' | '/pricing';

/**
 * Asks the server for a one-time link that opens the website already signed in, on the page for unlocking subjects.
 * A student who joined with Google has no website password, so this is how they get to pay. The link works once
 * and expires in five minutes; ask for a fresh one each time.
 */
export async function webLink(api: Api, o: { page?: WebPage; exam?: string; subjects?: string[] } = {}): Promise<string> {
  const res = await api.post<{ url: string }>('/web-link', o);
  return res.data.url;
}
