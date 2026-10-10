import { BADGES, levelFor, message, qualifying, totalXp, dayXp, type BadgeKey, type LevelInfo } from '@/core/engagement';
import { allMocks, completedChallenges, dayTotals, kvGet, kvSet, localDay, streak, totals } from '@/db/progress';
import type { Db } from '@/db/types';

const KEY = 'badges.earned';

export interface BadgeView {
  key: BadgeKey;
  title: string;
  text: string;
  icon: (typeof BADGES)[BadgeKey]['icon'];
  earned: boolean;
}

export interface EngagementSnapshot {
  level: LevelInfo;
  xpToday: number;
  message: string;
  badges: BadgeView[];
  /** Badges earned for the first time by this call, so the screen can celebrate them once. */
  newBadges: BadgeKey[];
}

/**
 * Everything Home needs about XP, level and badges. Badges already earned stay earned (they are remembered on the
 * phone) even if the answers behind them are later removed, as on the website.
 */
export async function engagement(db: Db, now: Date = new Date()): Promise<EngagementSnapshot> {
  const [days, mocks, t, current, challenges] = await Promise.all([dayTotals(db), allMocks(db), totals(db), streak(db, now), completedChallenges(db)]);

  let remembered: Partial<Record<BadgeKey, string>> = {};
  try { remembered = JSON.parse((await kvGet(db, KEY)) ?? '{}') ?? {}; } catch { remembered = {}; }

  const now_ = qualifying({ answered: t.answered, correct: t.correct, days: days.map((d) => d.day), mocks, challenges });
  const newBadges = now_.filter((k) => !remembered[k]);
  if (newBadges.length) {
    for (const k of newBadges) remembered[k] = now.toISOString();
    await kvSet(db, KEY, JSON.stringify(remembered));
  }

  const today = days.find((d) => d.day === localDay(now.toISOString()));

  return {
    level: levelFor(totalXp(days, challenges.reduce((sum, c) => sum + c.xp, 0))),
    xpToday: today ? dayXp(today) : 0,
    message: message(current, today?.answered ?? 0),
    badges: (Object.keys(BADGES) as BadgeKey[]).map((key) => ({ key, ...BADGES[key], earned: !!remembered[key] })).sort((a, b) => Number(b.earned) - Number(a.earned)),
    newBadges,
  };
}
