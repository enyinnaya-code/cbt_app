import { kvGet, kvSet } from '@/db/progress';
import type { Db } from '@/db/types';
import type { Api } from './api';
import { assertOurSite } from './webLink';

export interface Avatar {
  key: string;
  emoji: string;
}

/** What the server knows about this student's place in the rankings. Nothing here is a real name. */
export interface RankProfile {
  opted_in: boolean;
  nickname: string | null;
  avatar: string | null;
  school: string | null;
  avatars: Avatar[];
  privacy_version: string;
  privacy_url: string;
}

export type BoardScope = 'week' | 'all' | 'schools';

export interface BoardRow {
  rank: number;
  nickname?: string;
  emoji?: string;
  school: string | null;
  points: number;
  played?: number;
  members?: number;
  me?: boolean;
  mine?: boolean;
}

export interface Board {
  scope: BoardScope;
  period: string;
  rows: BoardRow[];
  me: { rank: number | null; points: number; played: number } | null;
  min_members?: number;
}

const KEY = 'rank.profile';

/** The last profile the server sent, so Home knows whether to show the challenge even with no internet. */
export async function cachedProfile(db: Db): Promise<RankProfile | null> {
  try {
    const raw = await kvGet(db, KEY);
    return raw ? (JSON.parse(raw) as RankProfile) : null;
  } catch {
    return null;
  }
}

async function remember(db: Db, profile: RankProfile): Promise<RankProfile> {
  await kvSet(db, KEY, JSON.stringify(profile));
  return profile;
}

/** The daily challenge is for students who joined. Without a known answer (never online yet) it stays hidden. */
export async function isJoined(db: Db): Promise<boolean> {
  return (await cachedProfile(db))?.opted_in === true;
}

export async function refreshProfile(db: Db, api: Api): Promise<RankProfile> {
  const { data } = await api.get<RankProfile>('/leaderboard/profile');
  return remember(db, data);
}

export async function join(db: Db, api: Api, input: { nickname: string; avatar: string; school: string; acceptPrivacy: boolean }): Promise<RankProfile> {
  const { data } = await api.put<RankProfile>('/leaderboard/profile', {
    nickname: input.nickname.trim(), avatar: input.avatar, school: input.school.trim() || null, accept_privacy: input.acceptPrivacy,
  });
  return remember(db, data);
}

export async function leave(db: Db, api: Api): Promise<RankProfile> {
  const { data } = await api.del<RankProfile>('/leaderboard/profile');
  return remember(db, data);
}

export async function board(api: Api, scope: BoardScope): Promise<Board> {
  const { data } = await api.get<Board>(`/leaderboard?scope=${scope}`);
  return data;
}

/** The privacy policy link, which the server supplies: only ever opened if it is on our own website. */
export function policyUrl(api: Api, profile: Pick<RankProfile, 'privacy_url'>): string {
  return assertOurSite(profile.privacy_url, api.baseUrl);
}
