import type { AuthResult } from './auth';
import { useData } from '@/state/data';
import { useSession } from '@/state/session';

/**
 * Runs right after any successful sign-in or sign-up: keep the session on the phone, then fetch the catalog and
 * bring this student's history down (which is how progress follows them to a new phone). The student is not made
 * to wait for any of it; they land on Home straight away.
 */
export async function completeSignIn(result: AuthResult): Promise<void> {
  await useSession.getState().signIn(result.token, result.user);

  void (async () => {
    await useData.getState().refreshCatalogNow();
    await useData.getState().syncNow();
  })();
}
