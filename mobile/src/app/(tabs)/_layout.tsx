import { TabList, TabSlot, TabTrigger, Tabs } from 'expo-router/ui';
import { View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useData } from '@/state/data';
import { T } from '@/ui/components';
import { TabButton } from '@/ui/TabBar';
import { useTheme } from '@/ui/theme';

export default function TabsLayout() {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const online = useData((s) => s.online);

  return (
    <Tabs>
      {!online ? (
        <View accessibilityRole="alert" style={{ backgroundColor: c.accentSoft, paddingTop: insets.top + 4, paddingBottom: 6, alignItems: 'center' }}>
          <T variant="tiny" color={c.accentInk} style={{ fontWeight: '700' }}>Offline. Everything you have downloaded still works.</T>
        </View>
      ) : null}

      <TabSlot />

      <TabList style={{ backgroundColor: c.surface, borderTopWidth: 1, borderTopColor: c.border, paddingBottom: Math.max(insets.bottom, 8), paddingHorizontal: 4 }}>
        <TabTrigger name="index" href="/" asChild><TabButton icon="home" label="Home" /></TabTrigger>
        <TabTrigger name="practice" href="/practice" asChild><TabButton icon="book" label="Practice" /></TabTrigger>
        <TabTrigger name="mock" href="/mock" asChild><TabButton icon="clock" label="Mock" /></TabTrigger>
        <TabTrigger name="progress" href="/progress" asChild><TabButton icon="chart" label="Progress" /></TabTrigger>
        <TabTrigger name="profile" href="/profile" asChild><TabButton icon="user" label="Profile" /></TabTrigger>
      </TabList>
    </Tabs>
  );
}
