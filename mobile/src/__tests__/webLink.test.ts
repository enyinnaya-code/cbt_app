import { Api } from '@/services/api';
import { webLink } from '@/services/webLink';
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
