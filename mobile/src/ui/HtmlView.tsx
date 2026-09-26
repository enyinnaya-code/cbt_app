/**
 * Shows question HTML with native text, no WebView (a WebView per question is far too heavy for a 2 GB phone).
 * The server has already removed anything unsafe. This handles what question writers actually use: bold,
 * italic, underline, sub/superscripts, lists, tables, images, and simple LaTeX maths.
 */
import { Image } from 'expo-image';
import { parseDocument } from 'htmlparser2';
import { memo, useMemo, type ReactNode } from 'react';
import { StyleSheet, Text, View, type TextStyle } from 'react-native';
import { mathToText } from '@/core/math';
import { fonts, useTheme } from './theme';

// domhandler node shapes, just the parts used here
interface Node { type: string; name?: string; data?: string; children?: Node[]; attribs?: Record<string, string> }

const INLINE = new Set(['b', 'strong', 'i', 'em', 'u', 'sup', 'sub', 'span', 'a', 'code', 'small', 'mark', 'del', 's', 'strike', 'ins', 'font', 'kbd', 'q', 'abbr']);
const BLOCK_CONTAINERS = new Set(['div', 'blockquote', 'figure', 'figcaption', 'section', 'article', 'dl', 'dd', 'dt', 'tbody', 'thead', 'tfoot', 'caption']);

const SUPER: Record<string, string> = { '0': '⁰', '1': '¹', '2': '²', '3': '³', '4': '⁴', '5': '⁵', '6': '⁶', '7': '⁷', '8': '⁸', '9': '⁹', '+': '⁺', '-': '⁻', '=': '⁼', '(': '⁽', ')': '⁾', n: 'ⁿ', x: 'ˣ', y: 'ʸ', i: 'ⁱ' };
const SUB: Record<string, string> = { '0': '₀', '1': '₁', '2': '₂', '3': '₃', '4': '₄', '5': '₅', '6': '₆', '7': '₇', '8': '₈', '9': '₉', '+': '₊', '-': '₋', '=': '₌', '(': '₍', ')': '₎', a: 'ₐ', e: 'ₑ', o: 'ₒ', x: 'ₓ', n: 'ₙ', m: 'ₘ' };

function textOf(n: Node): string {
  if (n.type === 'text') return n.data ?? '';
  return (n.children ?? []).map(textOf).join('');
}

function shift(text: string, table: Record<string, string>): string | null {
  const mapped = [...text].map((ch) => table[ch]);
  return mapped.every(Boolean) ? mapped.join('') : null;
}

function styleOf(attribs: Record<string, string> | undefined): TextStyle {
  const out: TextStyle = {};
  for (const decl of (attribs?.style ?? '').split(';')) {
    const [prop, ...rest] = decl.split(':');
    const value = rest.join(':').trim();
    switch (prop?.trim().toLowerCase()) {
      case 'font-weight': if (value === 'bold' || Number(value) >= 600) out.fontFamily = fonts.bold; break;
      case 'font-style': if (value === 'italic') out.fontStyle = 'italic'; break;
      case 'text-decoration': if (value.includes('underline')) out.textDecorationLine = 'underline'; else if (value.includes('line-through')) out.textDecorationLine = 'line-through'; break;
      case 'color': if (/^#[0-9a-f]{3,8}$/i.test(value)) out.color = value; break;
      case 'text-align': if (['left', 'center', 'right', 'justify'].includes(value)) out.textAlign = value as TextStyle['textAlign']; break;
    }
  }
  return out;
}

function Img({ src, alt }: { src: string; alt?: string }) {
  const { c } = useTheme();
  return (
    <Image
      source={{ uri: src }}
      accessibilityLabel={alt || 'Picture in the question'}
      contentFit="contain"
      style={{ width: '100%', height: 200, borderRadius: 12, backgroundColor: c.surface2 }}
      transition={0}
    />
  );
}

interface Ctx { color: string; size: number; muted: string; border: string; primary: string; bold: boolean }

/** Inline children become nested <Text> so bold, italic and sub/superscripts flow with the words around them. */
function inline(nodes: Node[], ctx: Ctx, key: string): ReactNode[] {
  return nodes.map((n, i) => {
    const k = `${key}.${i}`;

    if (n.type === 'text') return mathToText((n.data ?? '').replace(/\s+/g, ' '));
    if (n.type !== 'tag') return null;
    const name = (n.name ?? '').toLowerCase();

    if (name === 'br') return '\n';
    if (name === 'sup' || name === 'sub') {
      const raw = textOf(n).trim();
      const mapped = shift(raw, name === 'sup' ? SUPER : SUB);
      return mapped ?? <Text key={k} style={{ fontSize: ctx.size * 0.7 }}>{name === 'sup' ? '^' : '_'}{raw}</Text>;
    }

    const style: TextStyle = { ...styleOf(n.attribs) };
    if (name === 'b' || name === 'strong') style.fontFamily = fonts.bold;
    if (name === 'i' || name === 'em' || name === 'q') style.fontStyle = 'italic';
    if (name === 'u' || name === 'ins') style.textDecorationLine = 'underline';
    if (name === 'del' || name === 's' || name === 'strike') style.textDecorationLine = 'line-through';
    if (name === 'a') { style.color = ctx.primary; style.textDecorationLine = 'underline'; }
    if (name === 'code' || name === 'kbd') style.fontFamily = 'monospace';
    if (name === 'small') style.fontSize = ctx.size * 0.85;

    return <Text key={k} style={style}>{inline(n.children ?? [], ctx, k)}</Text>;
  });
}

function isInlineNode(n: Node): boolean {
  return n.type === 'text' || (n.type === 'tag' && INLINE.has((n.name ?? '').toLowerCase())) || (n.type === 'tag' && (n.name ?? '').toLowerCase() === 'br');
}

/** Renders a run of nodes: consecutive inline nodes make one paragraph, block nodes make their own boxes. */
function blocks(nodes: Node[], ctx: Ctx, key: string): ReactNode[] {
  const out: ReactNode[] = [];
  let run: Node[] = [];

  const flush = () => {
    if (!run.length) return;
    const content = inline(run, ctx, `${key}.r${out.length}`);
    const hasText = run.some((n) => textOf(n).trim() !== '');
    run = [];
    if (hasText) out.push(<Text key={`${key}.t${out.length}`} style={{ color: ctx.color, fontSize: ctx.size, lineHeight: ctx.size * 1.5, fontFamily: ctx.bold ? fonts.semibold : fonts.body }}>{content}</Text>);
  };

  nodes.forEach((n, i) => {
    const k = `${key}.${i}`;
    if (isInlineNode(n)) { run.push(n); return; }
    flush();
    if (n.type !== 'tag') return;

    const name = (n.name ?? '').toLowerCase();
    const kids = n.children ?? [];

    if (name === 'p' || name === 'h1' || name === 'h2' || name === 'h3' || name === 'h4' || name === 'h5' || name === 'h6' || name === 'pre') {
      const heading = name.startsWith('h');
      out.push(<View key={k}>{blocks(kids, { ...ctx, bold: ctx.bold || heading, size: heading ? ctx.size * 1.05 : ctx.size }, k)}</View>);
    } else if (name === 'ul' || name === 'ol') {
      out.push(
        <View key={k} style={{ gap: 6 }}>
          {kids.filter((c) => c.type === 'tag' && (c.name ?? '').toLowerCase() === 'li').map((li, j) => (
            <View key={`${k}.${j}`} style={{ flexDirection: 'row', gap: 8, paddingRight: 8 }}>
              <Text style={{ color: ctx.muted, fontSize: ctx.size, lineHeight: ctx.size * 1.5, minWidth: 18 }}>{name === 'ol' ? `${j + 1}.` : '•'}</Text>
              <View style={{ flex: 1, gap: 4 }}>{blocks(li.children ?? [], ctx, `${k}.${j}`)}</View>
            </View>
          ))}
        </View>,
      );
    } else if (name === 'table') {
      const rows: Node[] = [];
      const collect = (nn: Node[]) => nn.forEach((x) => { if (x.type !== 'tag') return; const nm = (x.name ?? '').toLowerCase(); if (nm === 'tr') rows.push(x); else collect(x.children ?? []); });
      collect(kids);
      out.push(
        <View key={k} style={{ borderWidth: StyleSheet.hairlineWidth * 2, borderColor: ctx.border, borderRadius: 8, overflow: 'hidden' }}>
          {rows.map((tr, r) => (
            <View key={`${k}.${r}`} style={{ flexDirection: 'row', borderTopWidth: r ? StyleSheet.hairlineWidth * 2 : 0, borderColor: ctx.border }}>
              {(tr.children ?? []).filter((cell) => cell.type === 'tag' && ['td', 'th'].includes((cell.name ?? '').toLowerCase())).map((cell, ci) => (
                <View key={`${k}.${r}.${ci}`} style={{ flex: 1, padding: 8, borderLeftWidth: ci ? StyleSheet.hairlineWidth * 2 : 0, borderColor: ctx.border }}>
                  {blocks(cell.children ?? [], { ...ctx, size: ctx.size * 0.9, bold: ctx.bold || (cell.name ?? '').toLowerCase() === 'th' }, `${k}.${r}.${ci}`)}
                </View>
              ))}
            </View>
          ))}
        </View>,
      );
    } else if (name === 'img') {
      const src = n.attribs?.src;
      if (src) out.push(<Img key={k} src={src} alt={n.attribs?.alt} />);
    } else if (name === 'hr') {
      out.push(<View key={k} style={{ height: 1, backgroundColor: ctx.border }} />);
    } else if (BLOCK_CONTAINERS.has(name) || kids.length) {
      out.push(<View key={k} style={{ gap: 6 }}>{blocks(kids, ctx, k)}</View>);
    }
  });

  flush();
  return out;
}

export const HtmlView = memo(function HtmlView({ html, size = 16, color, bold }: { html: string; size?: number; color?: string; bold?: boolean }) {
  const { c } = useTheme();
  const tree = useMemo(() => parseDocument(html ?? '').children as unknown as Node[], [html]);
  const ctx: Ctx = { color: color ?? c.text, size, muted: c.muted, border: c.border, primary: c.primary, bold: !!bold };
  return <View style={{ gap: 8 }}>{blocks(tree, ctx, 'h')}</View>;
});

/** Plain text of some HTML, for short labels such as an option's screen-reader text. */
export function plain(html: string): string {
  return textOf({ type: 'root', children: parseDocument(html ?? '').children as unknown as Node[] }).replace(/\s+/g, ' ').trim();
}
