import type { TabTriggerSlotProps } from 'expo-router/ui';
import { forwardRef } from 'react';
import { Pressable, Text, View } from 'react-native';
import { Icon, type IconName } from './Icon';
import { fonts, useTheme } from './theme';

type Props = TabTriggerSlotProps & { icon: IconName; label: string };

/** One tab in the bottom bar: an icon in a pill that fills when the tab is open, as in the design. */
export const TabButton = forwardRef<View, Props>(function TabButton({ icon, label, isFocused, ...props }, ref) {
  const { c } = useTheme();

  return (
    <Pressable
      {...props}
      ref={ref}
      accessibilityRole="tab"
      accessibilityLabel={label}
      accessibilityState={{ selected: !!isFocused }}
      style={{ flex: 1, alignItems: 'center', gap: 3, paddingTop: 8, paddingBottom: 4 }}
    >
      <View style={{ width: 52, height: 30, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: isFocused ? c.primarySoft : 'transparent' }}>
        <Icon name={icon} size={21} color={isFocused ? c.primary : c.muted} />
      </View>
      <Text style={{ fontFamily: fonts.semibold, fontSize: 11, color: isFocused ? c.primary : c.muted }}>{label}</Text>
    </Pressable>
  );
});
