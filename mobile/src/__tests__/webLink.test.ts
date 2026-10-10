import { Api } from '@/services/api';
import { assertOurSite, webLink, websitePage } from '@/services/webLink';
import { formatDate, naira } from '@/core/format';
import { res } from './helpers/fixtures';

const api = (fetchImpl: jest.Mock) => new Api({ baseUrl: 'https://x.test/api/v1', getToken: () => 'tok', fetchImpl });

describe('webLink', () => {
  it('asks the server for a one-time link for the page and subject, and returns it', async () => {
    const fetchImpl = jest.fn().mockResolvedValue(res(200, { url: 'https://x.test/app-link/1?signature=abc' }));

    const url = await webLink(api(fetchImpl), { exam: 'jamb', subjects: ['physics'] });

    expect(url).toBe('https://x.test/app-link/1?signature=abc');
    const [target, init] = fetchImpl.mock.calls[0];
    expect(target).toBe('https://x.test/api/v1/web-link');
    expect(init.method).toBe('POST');
    expect(init.headers.Authorization).toBe('Bearer tok');
    expect(JSON.parse(init.body)).toEqual({ exam: 'jamb', subjects: ['physics'] });
  });

  it('says so plainly when the phone is offline', async () => {
    const fetchImpl = jest.fn().mockRejectedValue(new TypeError('Network request failed'));

    await expect(webLink(api(fetchImpl))).rejects.toMatchObject({ kind: 'offline' });
  });

  it('passes on the server refusing (for example a staff account)', async () => {
    const fetchImpl = jest.fn().mockResolvedValue(res(403, { message: 'Staff accounts sign in on the website.' }));

    await expect(webLink(api(fetchImpl))).rejects.toMatchObject({ kind: 'forbidden', message: 'Staff accounts sign in on the website.' });
  });
});

describe('money and date formatting', () => {
  it('writes naira with a thousands separator', () => {
    expect(naira(1500)).toBe('₦1,500');
    expect(naira(0)).toBe('₦0');
    expect(naira(2500000)).toBe('₦2,500,000');
  });

  it('writes a date without the time, in the app-wide style', () => {
    expect(formatDate('2027-03-12T21:24:00')).toBe('12 March 2027');
  });
});

describe('assertOurSite', () => {
  const base = 'https://testacbt.com/api/v1';

  it('lets a link to our own website through', () => {
    expect(assertOurSite('https://testacbt.com/app-link/1?signature=abc', base)).toBe('https://testacbt.com/app-link/1?signature=abc');
    expect(assertOurSite('https://TestaCBT.com/checkout', base)).toBe('https://TestaCBT.com/checkout');
  });

  it('refuses a link to anywhere else, however it is written', () => {
    for (const bad of [
      'https://evil.example.com/app-link/1',
      'https://testacbt.com.evil.example.com/app-link/1',
      'https://testacbt.com@evil.example.com/app-link/1',
      '//evil.example.com/x',
      'javascript:alert(1)',
      'intent://evil#Intent;scheme=https;end',
      'not a url',
      '',
    ]) {
      expect(() => assertOurSite(bad, base)).toThrow('did not look safe');
    }
  });

  it('refuses a link from a server whose address could not be read', () => {
    expect(() => assertOurSite('https://testacbt.com/x', 'nonsense')).toThrow('did not look safe');
  });
});

describe('websitePage', () => {
  it('finds a page on our own website from where the API lives', () => {
    expect(websitePage('https://testacbt.com/api/v1', '/forgot-password')).toBe('https://testacbt.com/forgot-password');
    expect(websitePage('https://testacbt.com/api/v1/', 'forgot-password')).toBe('https://testacbt.com/forgot-password');
  });

  it('gives up on an address it cannot read', () => {
    expect(websitePage('nonsense', '/forgot-password')).toBeNull();
  });
});
