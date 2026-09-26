import { Alert } from 'react-native';
import { formatBytes } from '@/core/format';
import type { SubjectView } from '@/core/views';
import { useData } from '@/state/data';
import { useSettings } from '@/state/settings';

/**
 * Starts a subject download, looking after the student's data: it says so when they are offline, and when they
 * are on mobile data and have asked to be careful, it shows the size and asks first.
 */
export function startDownload(view: SubjectView): void {
  const { online, onWifi, catalog, download } = useData.getState();
  const subject = catalog?.exams.find((e) => e.slug === view.examSlug)?.subjects.find((s) => s.slug === view.slug);

  if (!subject?.pack) {
    Alert.alert('Not available yet', 'This subject has no downloadable questions yet. Please check again later.');
    return;
  }
  if (!online) {
    Alert.alert('You are offline', 'Connect to Wi-Fi or mobile data to download this subject.');
    return;
  }

  const go = () => { void download(view.examSlug, subject); };

  if (useSettings.getState().wifiOnly && !onWifi) {
    Alert.alert(
      'Use mobile data?',
      `${view.name} is ${formatBytes(subject.pack.size_bytes)}. You are not on Wi-Fi, and you asked us to save your mobile data.`,
      [{ text: 'Wait for Wi-Fi', style: 'cancel' }, { text: 'Download now', onPress: go }],
    );
    return;
  }
  go();
}

export function confirmDelete(view: SubjectView, onConfirm: () => void): void {
  Alert.alert(
    `Delete ${view.name}?`,
    `This removes ${view.examName} ${view.name} from your phone${view.installed ? ` (${formatBytes(view.installed.size_bytes)})` : ''}. Your scores and saved questions are kept. You can download it again any time.`,
    [{ text: 'Cancel', style: 'cancel' }, { text: 'Delete', style: 'destructive', onPress: onConfirm }],
  );
}
