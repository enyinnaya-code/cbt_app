import { useState, type ReactNode } from 'react';
import {
  ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, TextInput, View,
  type StyleProp, type TextInputProps, type TextStyle, type ViewStyle,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Icon, type IconName } from './Icon';
import { fonts, radius, tone, useTheme } from './theme';

// ------------------------------------------------------------------------------------------ text

type Variant = 'display' | 'h1' | 'h2' | 'h3' | 'body' | 'small' | 'tiny' | 'label';

const VARIANTS: Record<Variant, TextStyle> = {
  display: { fontFamily: fonts.display, fontSize: 40, lineHeight: 44, letterSpacing: -0.8 },
  h1: { fontFamily: fonts.display, fontSize: 28, lineHeight: 32, letterSpacing: -0.5 },
  h2: { fontFamily: fonts.displayMedium, fontSize: 19, lineHeight: 24, letterSpacing: -0.2 },
  h3: { fontFamily: fonts.bold, fontSize: 15, lineHeight: 20 },
  body: { fontFamily: fonts.body, fontSize: 15, lineHeight: 22 },
  small: { fontFamily: fonts.body, fontSize: 13, lineHeight: 18 },
  tiny: { fontFamily: fonts.body, fontSize: 12, lineHeight: 16 },
  label: { fontFamily: fonts.semibold, fontSize: 13, lineHeight: 18 },
};

export function T({ variant = 'body', muted, color, style, children, ...rest }: {
  variant?: Variant; muted?: boolean; color?: string; style?: StyleProp<TextStyle>; children?: ReactNode;
} & Omit<React.ComponentProps<typeof Text>, 'style'>) {
  const { c } = useTheme();
  return <Text {...rest} style={[VARIANTS[variant], { color: color ?? (muted ? c.muted : c.text) }, style]}>{children}</Text>;
}

// ------------------------------------------------------------------------------------------ buttons

export function Button({ label, onPress, variant = 'primary', small, icon, loading, disabled, full = true, style }: {
  label: string; onPress?: () => void; variant?: 'primary' | 'outline' | 'text' | 'danger'; small?: boolean; icon?: IconName;
  loading?: boolean; disabled?: boolean; full?: boolean; style?: StyleProp<ViewStyle>;
}) {
  const { c } = useTheme();
  const bg = variant === 'primary' ? c.primary : variant === 'outline' ? c.surface : variant === 'danger' ? c.dangerSoft : 'transparent';
  const fg = variant === 'primary' ? c.onPrimary : variant === 'text' ? c.primary : variant === 'danger' ? c.danger : c.text;
  const off = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled: !!off, busy: !!loading }}
      disabled={off}
      onPress={onPress}
      style={({ pressed }) => [
        styles.btn,
        small && styles.btnSmall,
        { backgroundColor: bg, borderColor: variant === 'outline' ? c.border : 'transparent', opacity: off ? 0.5 : pressed ? 0.85 : 1 },
        full ? { alignSelf: 'stretch' } : { alignSelf: 'flex-start' },
        style,
      ]}
    >
      {loading ? <ActivityIndicator color={fg} /> : (
        <>
          {icon ? <Icon name={icon} size={small ? 15 : 18} color={fg} /> : null}
          <Text style={{ fontFamily: fonts.bold, fontSize: small ? 13 : 15, color: fg }}>{label}</Text>
        </>
      )}
    </Pressable>
  );
}

export function IconButton({ icon, onPress, label, size = 42, color, bg, active, filled }: {
  icon: IconName; onPress?: () => void; label: string; size?: number; color?: string; bg?: string; active?: boolean; filled?: boolean;
}) {
  const { c } = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      onPress={onPress}
      style={({ pressed }) => [styles.iconBtn, { width: size, height: size, backgroundColor: bg ?? c.surface, borderColor: c.border, opacity: pressed ? 0.8 : 1 }]}
    >
      <Icon name={icon} size={20} color={active ? c.accent : color ?? c.text} fill={filled ? (active ? c.accent : 'none') : undefined} />
    </Pressable>
  );
}

// ------------------------------------------------------------------------------------------ surfaces

export function Card({ children, style, kind = 'default', onPress, accessibilityLabel }: {
  children: ReactNode; style?: StyleProp<ViewStyle>; kind?: 'default' | 'primary' | 'flat'; onPress?: () => void; accessibilityLabel?: string;
}) {
  const { c } = useTheme();
  const base: ViewStyle = kind === 'primary'
    ? { backgroundColor: c.primary, borderColor: c.primary }
    : kind === 'flat' ? { backgroundColor: c.surface2, borderColor: c.surface2 } : { backgroundColor: c.surface, borderColor: c.border };

  if (onPress) {
    return (
      <Pressable accessibilityRole="button" accessibilityLabel={accessibilityLabel} onPress={onPress} style={({ pressed }) => [styles.card, base, { opacity: pressed ? 0.9 : 1 }, style]}>
        {children}
      </Pressable>
    );
  }
  return <View style={[styles.card, base, style]}>{children}</View>;
}

export function Chip({ label, selected, onPress, icon }: { label: string; selected?: boolean; onPress?: () => void; icon?: IconName }) {
  const { c } = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: !!selected }}
      onPress={onPress}
      style={[styles.chip, { borderColor: selected ? c.text : c.border, backgroundColor: selected ? c.text : c.surface }]}
    >
      {icon ? <Icon name={icon} size={15} color={selected ? c.bg : c.text} /> : null}
      <Text style={{ fontFamily: fonts.semibold, fontSize: 13, color: selected ? c.bg : c.text }}>{label}</Text>
    </Pressable>
  );
}

export function Segmented<V extends string>({ options, value, onChange }: { options: { value: V; label: string }[]; value: V; onChange: (v: V) => void }) {
  const { c } = useTheme();
  return (
    <View style={[styles.seg, { backgroundColor: c.surface2 }]} accessibilityRole="tablist">
      {options.map((o) => {
        const on = o.value === value;
        return (
          <Pressable key={o.value} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => onChange(o.value)}
            style={[styles.segItem, on && { backgroundColor: c.surface }]}>
            <Text style={{ fontFamily: fonts.semibold, fontSize: 13, color: on ? c.text : c.muted }} numberOfLines={1}>{o.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

export function ProgressBar({ value, amber }: { value: number; amber?: boolean }) {
  const { c } = useTheme();
  return (
    <View style={[styles.bar, { backgroundColor: c.surface2 }]} accessibilityRole="progressbar" accessibilityValue={{ min: 0, max: 100, now: Math.round(value) }}>
      <View style={{ width: `${Math.max(0, Math.min(100, value))}%`, height: '100%', borderRadius: 99, backgroundColor: amber ? c.accent : c.primary }} />
    </View>
  );
}

export function Badge({ label, kind = 'amber', icon }: { label: string; kind?: 'amber' | 'green' | 'neutral' | 'red'; icon?: IconName }) {
  const { c } = useTheme();
  const map = {
    amber: { bg: c.accentSoft, fg: c.accentInk }, green: { bg: c.primarySoft, fg: c.primaryInk },
    neutral: { bg: c.surface2, fg: c.muted }, red: { bg: c.dangerSoft, fg: c.danger },
  }[kind];
  return (
    <View style={[styles.badge, { backgroundColor: map.bg }]}>
      {icon ? <Icon name={icon} size={13} color={map.fg} /> : null}
      <Text style={{ fontFamily: fonts.bold, fontSize: 12, color: map.fg }}>{label}</Text>
    </View>
  );
}

export function Avatar({ text, size = 44 }: { text: string; size?: number }) {
  const { c } = useTheme();
  return (
    <View style={{ width: size, height: size, borderRadius: size / 2, backgroundColor: c.accent, alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ fontFamily: fonts.display, fontSize: size * 0.36, color: '#2A1D00' }}>{text}</Text>
    </View>
  );
}

export function SubjectTile({ code, index = 0, size = 42 }: { code: string; index?: number; size?: number }) {
  const { c } = useTheme();
  const t = tone(c, index);
  return (
    <View style={{ width: size, height: size, borderRadius: 12, backgroundColor: t.bg, alignItems: 'center', justifyContent: 'center' }}>
      <Text style={{ fontFamily: fonts.display, fontSize: size * 0.36, color: t.fg }}>{code}</Text>
    </View>
  );
}

export function Divider({ label }: { label?: string }) {
  const { c } = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
      <View style={{ flex: 1, height: 1, backgroundColor: c.border }} />
      {label ? <T variant="small" muted>{label}</T> : null}
      <View style={{ flex: 1, height: 1, backgroundColor: c.border }} />
    </View>
  );
}

export function Banner({ text, kind = 'info', icon }: { text: string; kind?: 'info' | 'ok' | 'error'; icon?: IconName }) {
  const { c } = useTheme();
  const map = { info: { bg: c.surface2, fg: c.text }, ok: { bg: c.primarySoft, fg: c.primaryInk }, error: { bg: c.dangerSoft, fg: c.danger } }[kind];
  return (
    <View accessibilityRole="alert" style={[styles.banner, { backgroundColor: map.bg }]}>
      {icon ? <Icon name={icon} size={18} color={map.fg} /> : null}
      <Text style={{ flex: 1, fontFamily: fonts.semibold, fontSize: 14, lineHeight: 20, color: map.fg }}>{text}</Text>
    </View>
  );
}

export function Empty({ icon, title, body, action }: { icon: IconName; title: string; body?: string; action?: ReactNode }) {
  const { c } = useTheme();
  return (
    <View style={{ alignItems: 'center', gap: 10, paddingVertical: 28, paddingHorizontal: 16 }}>
      <View style={{ width: 48, height: 48, borderRadius: 14, backgroundColor: c.primarySoft, alignItems: 'center', justifyContent: 'center' }}>
        <Icon name={icon} size={22} color={c.primaryInk} />
      </View>
      <T variant="h3" style={{ textAlign: 'center' }}>{title}</T>
      {body ? <T variant="small" muted style={{ textAlign: 'center' }}>{body}</T> : null}
      {action}
    </View>
  );
}

// ------------------------------------------------------------------------------------------ input

export function Field({ label, icon, error, secure, ...rest }: { label: string; icon?: IconName; error?: string; secure?: boolean } & TextInputProps) {
  const { c } = useTheme();
  const [focus, setFocus] = useState(false);
  const [hidden, setHidden] = useState(!!secure);

  return (
    <View style={{ gap: 6 }}>
      <T variant="label">{label}</T>
      <View style={[styles.input, { backgroundColor: c.surface, borderColor: error ? c.danger : focus ? c.primary : c.border }]}>
        {icon ? <Icon name={icon} size={18} color={c.muted} /> : null}
        <TextInput
          {...rest}
          accessibilityLabel={label}
          secureTextEntry={hidden}
          placeholderTextColor={c.muted}
          onFocus={(e) => { setFocus(true); rest.onFocus?.(e); }}
          onBlur={(e) => { setFocus(false); rest.onBlur?.(e); }}
          style={{ flex: 1, fontFamily: fonts.body, fontSize: 15, color: c.text, paddingVertical: 0 }}
        />
        {secure ? (
          <Pressable accessibilityRole="button" accessibilityLabel={hidden ? 'Show password' : 'Hide password'} onPress={() => setHidden((h) => !h)} hitSlop={10}>
            <Icon name="eye" size={18} color={hidden ? c.muted : c.primary} />
          </Pressable>
        ) : null}
      </View>
      {error ? <T variant="small" color={c.danger} style={{ fontFamily: fonts.semibold }}>{error}</T> : null}
    </View>
  );
}

// ------------------------------------------------------------------------------------------ layout

export function Screen({ children, scroll = true, padded = true, footer, header }: {
  children: ReactNode; scroll?: boolean; padded?: boolean; footer?: ReactNode; header?: ReactNode;
}) {
  const { c } = useTheme();
  const insets = useSafeAreaInsets();
  const body = padded ? { padding: 20, gap: 18 } : {};

  return (
    <View style={{ flex: 1, backgroundColor: c.bg, paddingTop: insets.top }}>
      {header}
      {scroll ? (
        <ScrollView contentContainerStyle={[body, { paddingBottom: footer ? 24 : 24 }]} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
          {children}
        </ScrollView>
      ) : (
        <View style={[{ flex: 1 }, body]}>{children}</View>
      )}
      {footer ? <View style={{ backgroundColor: c.surface, borderTopWidth: 1, borderTopColor: c.border, paddingHorizontal: 20, paddingTop: 12, paddingBottom: Math.max(insets.bottom, 12), flexDirection: 'row', gap: 10 }}>{footer}</View> : null}
    </View>
  );
}

export function Row({ children, between, style, wrap }: { children: ReactNode; between?: boolean; style?: StyleProp<ViewStyle>; wrap?: boolean }) {
  return <View style={[{ flexDirection: 'row', alignItems: 'center', gap: 12 }, between && { justifyContent: 'space-between' }, wrap && { flexWrap: 'wrap' }, style]}>{children}</View>;
}

const styles = StyleSheet.create({
  btn: { minHeight: 52, borderRadius: radius.md, borderWidth: 1.5, paddingHorizontal: 18, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 10 },
  btnSmall: { minHeight: 40, borderRadius: radius.sm, paddingHorizontal: 14 },
  iconBtn: { borderRadius: 12, borderWidth: 1, alignItems: 'center', justifyContent: 'center' },
  card: { borderRadius: radius.xl, borderWidth: 1, padding: 16 },
  chip: { minHeight: 40, paddingHorizontal: 14, borderRadius: radius.full, borderWidth: 1.5, flexDirection: 'row', alignItems: 'center', gap: 6 },
  seg: { flexDirection: 'row', borderRadius: 12, padding: 4, gap: 4 },
  segItem: { flex: 1, minHeight: 38, borderRadius: 9, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8 },
  bar: { height: 8, borderRadius: 99, overflow: 'hidden' },
  badge: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 10, paddingVertical: 5, borderRadius: radius.full, alignSelf: 'flex-start' },
  banner: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 14, borderRadius: radius.md },
  input: { minHeight: 52, borderWidth: 1.5, borderRadius: radius.md, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10 },
});
