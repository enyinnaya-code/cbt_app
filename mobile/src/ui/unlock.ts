import { Alert, Linking } from 'react-native';
import { ApiError } from '@/services/api';
import { webLink, type WebPage } from '@/services/webLink';
import { useData } from '@/state/data';
import { services } from '@/state/services';

/**
 * Opens the website, already signed in, where the student picks subjects and pays (card or bank transfer).
 * When they come back to the app the catalog is refreshed straight away, so what they bought unlocks.
 */
export async function unlockOnWebsite(o: { exam?: string; subject?: string; page?: WebPage } = {}): Promise<void> {
  if (!useData.getState().online) {
    Alert.alert('You are offline', 'Connect to the internet to unlock subjects.');
    return;
  }

  try {
    const url = await webLink(services().api, { page: o.page, exam: o.exam, subjects: o.subject ? [o.subject] : undefined });
    useData.getState().setAwaitingPurchase(true);
    await Linking.openURL(url);
  } catch (e) {
    Alert.alert('Could not open the page', e instanceof ApiError ? e.message : 'Please try again in a moment.');
  }
}
