import { router } from 'expo-router';
import { View } from 'react-native';
import { Badge, Button, Screen, T } from '@/ui/components';
import { Icon } from '@/ui/Icon';
import { fonts, useTheme } from '@/ui/theme';

const ROWS: { n: number; fill: 'A' | 'B' | 'C' | 'D'; pencil?: boolean }[] = [
  { n: 1, fill: 'B' }, { n: 2, fill: 'D' }, { n: 3, fill: 'A' }, { n: 4, fill: 'C', pencil: true },
];

export default function Welcome() {
  const { c } = useTheme();

  return (
    <Screen>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 10, paddingTop: 8 }}>
        <View style={{ width: 36, height: 36, borderRadius: 10, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
          <Icon name="check" size={18} color={c.onPrimary} />
        </View>
        <T variant="display" style={{ fontSize: 22, lineHeight: 26 }}>TestaCBT</T>
      </View>

      {/* The answer sheet: the idea the whole app is built around. */}
      <View accessible accessibilityLabel="An answer sheet being shaded in" style={{ backgroundColor: c.surface, borderColor: c.border, borderWidth: 1, borderRadius: 24, padding: 20, gap: 12 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <T variant="h3">JAMB practice</T>
          <Badge label="Works offline" kind="green" icon="wifioff" />
        </View>
        {ROWS.map((r) => (
          <View key={r.n} style={{ flexDirection: 'row', alignItems: 'center', gap: 10 }}>
            <T variant="small" muted style={{ width: 22, fontFamily: fonts.bold }}>{r.n}</T>
            {(['A', 'B', 'C', 'D'] as const).map((l) => {
              const on = l === r.fill;
              const bg = on ? (r.pencil ? c.accent : c.primary) : 'transparent';
              return (
                <View key={l} style={{ width: 30, height: 30, borderRadius: 15, borderWidth: 2, borderColor: on ? bg : c.muted, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}>
                  <T style={{ fontFamily: fonts.bold, fontSize: 12, color: on ? (r.pencil ? '#2A1D00' : c.onPrimary) : c.muted }}>{l}</T>
                </View>
              );
            })}
          </View>
        ))}
      </View>

      <View style={{ gap: 10 }}>
        <T variant="h1">Pass WAEC, NECO and JAMB. No data wahala.</T>
        <T muted>Past questions with simple explanations in English and Pidgin. Download once, then study anywhere, even when network no dey.</T>
      </View>

      <View style={{ gap: 10, marginTop: 8 }}>
        <Button label="Get started" onPress={() => router.push('/sign-up')} />
        <Button label="I already have an account" variant="text" onPress={() => router.push('/sign-in')} />
      </View>
    </Screen>
  );
}
