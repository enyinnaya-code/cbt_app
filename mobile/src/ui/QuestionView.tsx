import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';
import type { SessionQuestion } from '@/core/types';
import { HtmlView, plain } from './HtmlView';
import { Icon } from './Icon';
import { Card, Segmented, T } from './components';
import { fonts, radius, useTheme } from './theme';

/** A question with its passage (if any) and its options as answer-sheet bubbles. Used by Practice and Mock. */
export function QuestionView({ q, passage, chosen, showAnswer, onChoose }: {
  q: SessionQuestion;
  passage?: string | null;
  chosen: string | undefined;
  /** Practice reveals right and wrong; a mock never does. */
  showAnswer: boolean;
  onChoose: (letter: string) => void;
}) {
  const { c } = useTheme();
  const letters = Object.keys(q.options).sort();

  return (
    <View style={{ gap: 16 }}>
      {passage ? (
        <Card kind="flat" style={{ gap: 8 }}>
          <T variant="h3">Read this first</T>
          <HtmlView html={passage} size={15} />
        </Card>
      ) : null}

      <HtmlView html={q.html} size={18} bold />

      <View style={{ gap: 10 }} accessibilityRole="radiogroup">
        {letters.map((letter) => {
          const isRight = showAnswer && letter === q.answer;
          const isWrong = showAnswer && letter === chosen && letter !== q.answer;
          const selected = !showAnswer && letter === chosen;

          const border = isRight ? c.primary : isWrong ? c.danger : selected ? c.text : c.border;
          const bg = isRight ? c.primarySoft : isWrong ? c.dangerSoft : c.surface;
          const bubbleBg = isRight ? c.primary : isWrong ? c.danger : selected ? c.text : 'transparent';
          const bubbleBorder = isRight ? c.primary : isWrong ? c.danger : selected ? c.text : c.muted;
          const bubbleText = isRight ? c.onPrimary : isWrong ? '#FFFFFF' : selected ? c.bg : c.muted;

          return (
            <Pressable
              key={letter}
              accessibilityRole="radio"
              accessibilityLabel={`Option ${letter}. ${plain(q.options[letter])}`}
              accessibilityState={{ selected: letter === chosen, disabled: showAnswer && chosen !== undefined }}
              disabled={showAnswer && chosen !== undefined}
              onPress={() => onChoose(letter)}
              style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: 14, padding: 14, minHeight: 56, borderRadius: radius.lg, borderWidth: 1.5, borderColor: border, backgroundColor: bg, opacity: pressed ? 0.85 : 1 })}
            >
              <View style={{ width: 32, height: 32, borderRadius: 16, borderWidth: 2, borderColor: bubbleBorder, backgroundColor: bubbleBg, alignItems: 'center', justifyContent: 'center' }}>
                <Text style={{ fontFamily: fonts.bold, fontSize: 13, color: bubbleText }}>{letter}</Text>
              </View>
              <View style={{ flex: 1 }}><HtmlView html={q.options[letter]} size={16} /></View>
              {isRight ? <Icon name="check" size={20} color={c.primary} /> : isWrong ? <Icon name="x" size={20} color={c.danger} /> : null}
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

/** "Correct!" or "Not quite. The correct answer is B." */
export function Verdict({ q, chosen }: { q: SessionQuestion; chosen: string | undefined }) {
  const { c } = useTheme();
  const ok = chosen === q.answer;
  const bg = ok ? c.primarySoft : chosen ? c.dangerSoft : c.surface2;
  const fg = ok ? c.primaryInk : chosen ? c.danger : c.text;
  const text = ok ? 'Correct!' : chosen ? `Not quite. The correct answer is ${q.answer}.` : `You skipped this one. The correct answer is ${q.answer}.`;

  return (
    <View accessibilityRole="alert" style={{ backgroundColor: bg, padding: 14, borderRadius: radius.md }}>
      <Text style={{ fontFamily: fonts.semibold, fontSize: 14, color: fg }}>{text}</Text>
    </View>
  );
}

/** The explanation, in English or Pidgin, with a switch when both exist. */
export function Explanation({ q, lang }: { q: SessionQuestion; lang: 'en' | 'pcm' }) {
  const [chosen, setChosen] = useState<'en' | 'pcm' | null>(null);
  const both = !!q.explanationEn && !!q.explanationPcm;
  if (!q.explanationEn && !q.explanationPcm) return null;

  const show = chosen ?? (lang === 'pcm' ? (q.explanationPcm ? 'pcm' : 'en') : q.explanationEn ? 'en' : 'pcm');

  return (
    <Card kind="flat" style={{ gap: 12 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 12 }}>
        <T variant="h3">Explanation</T>
        {both ? (
          <View style={{ width: 180 }}>
            <Segmented value={show} onChange={setChosen} options={[{ value: 'en', label: 'English' }, { value: 'pcm', label: 'Pidgin' }]} />
          </View>
        ) : null}
      </View>
      <HtmlView html={(show === 'pcm' ? q.explanationPcm : q.explanationEn) ?? ''} size={14} />
    </Card>
  );
}

export type SheetState = 'answered' | 'flagged' | 'right' | 'wrong' | 'none';

/** The numbered answer sheet. Tap a number to jump to that question. */
export function AnswerSheet({ count, current, stateOf, onJump, offset = 0 }: {
  count: number;
  current: number;
  stateOf: (index: number) => SheetState;
  onJump: (index: number) => void;
  /** Index of the first question shown, for a mock's per-subject sheet. */
  offset?: number;
}) {
  const { c } = useTheme();

  return (
    <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: 10 }}>
      {Array.from({ length: count }, (_, i) => {
        const index = offset + i;
        const s = stateOf(index);
        const cur = index === current;
        const bg = s === 'right' ? c.primary : s === 'wrong' ? c.danger : s === 'answered' ? c.text : s === 'flagged' ? c.accentSoft : 'transparent';
        const border = cur ? c.primary : s === 'right' ? c.primary : s === 'wrong' ? c.danger : s === 'answered' ? c.text : s === 'flagged' ? c.accent : c.border;
        const fg = s === 'right' ? c.onPrimary : s === 'wrong' ? '#FFFFFF' : s === 'answered' ? c.bg : s === 'flagged' ? c.accentInk : cur ? c.primary : c.muted;

        return (
          <Pressable
            key={index}
            accessibilityRole="button"
            accessibilityLabel={`Question ${i + 1}${cur ? ', current' : ''}${s === 'answered' ? ', answered' : s === 'flagged' ? ', flagged' : ''}`}
            onPress={() => onJump(index)}
            style={{ width: 40, height: 40, borderRadius: 20, borderWidth: cur ? 3 : 2, borderColor: border, backgroundColor: bg, alignItems: 'center', justifyContent: 'center' }}
          >
            <Text style={{ fontFamily: fonts.bold, fontSize: 12, color: fg }}>{i + 1}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}
