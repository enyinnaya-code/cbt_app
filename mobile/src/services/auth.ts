import type { User } from '@/core/types';
import type { Api } from './api';

export interface AuthResult {
  token: string;
  user: User;
}

const clean = (email: string) => email.trim().toLowerCase();

export async function loginWithEmail(api: Api, email: string, password: string): Promise<AuthResult> {
  const { data } = await api.post<AuthResult>('/auth/login', { email: clean(email), password, device_name: api.deviceName }, { auth: false });
  return data;
}

export async function registerWithEmail(api: Api, o: { name: string; email: string; password: string; exams: string[] }): Promise<AuthResult> {
  const { data } = await api.post<AuthResult>(
    '/auth/register',
    { name: o.name.trim(), email: clean(o.email), password: o.password, device_name: api.deviceName, preferred_exams: o.exams },
    { auth: false },
  );
  return data;
}

export async function loginWithGoogle(api: Api, idToken: string): Promise<AuthResult> {
  const { data } = await api.post<AuthResult>('/auth/google', { id_token: idToken, device_name: api.deviceName }, { auth: false });
  return data;
}

export async function fetchMe(api: Api): Promise<User> {
  const { data } = await api.get<{ user: User }>('/me');
  return data.user;
}

/** Best effort: the server forgets this phone's token. Signing out works even when offline. */
export async function logoutRemote(api: Api): Promise<void> {
  try { await api.post('/auth/logout'); } catch { /* offline: the token stays valid on the server until it is next used */ }
}

/** Client-side checks that give a friendly message before a request is even sent. */
export function validateSignIn(email: string, password: string): Record<string, string> {
  const e: Record<string, string> = {};
  if (!/^\S+@\S+\.\S+$/.test(email.trim())) e.email = 'Enter a valid email address.';
  if (!password) e.password = 'Enter your password.';
  return e;
}

export function validateSignUp(o: { name: string; email: string; password: string }): Record<string, string> {
  const e: Record<string, string> = {};
  if (o.name.trim().length < 2) e.name = 'Enter your full name.';
  if (!/^\S+@\S+\.\S+$/.test(o.email.trim())) e.email = 'Enter a valid email address.';
  if (o.password.length < 8) e.password = 'Use at least 8 characters.';
  return e;
}
