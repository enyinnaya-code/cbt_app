import { Asset } from 'expo-asset';
import { File } from 'expo-file-system';
import type { PackManager } from './packs';

// Bundled as data files (see metro.config.js), so they cost nothing at start-up. Made by scripts/make-starter.mjs.
const STARTER_FILES = [
  require('../../assets/starter/jamb-english-language.pack'),
  require('../../assets/starter/jamb-mathematics.pack'),
  require('../../assets/starter/jamb-physics.pack'),
  require('../../assets/starter/jamb-chemistry.pack'),
];

/**
 * First launch only: installs the questions that ship inside the app, so Practice and Mock work in airplane
 * mode before anything is downloaded. A downloaded pack for the same subject later replaces them.
 */
export async function installStarterIfNeeded(packs: PackManager): Promise<number> {
  if ((await packs.installed()).length > 0) return 0;

  let installed = 0;
  for (const mod of STARTER_FILES) {
    try {
      const asset = Asset.fromModule(mod);
      await asset.downloadAsync();
      if (!asset.localUri) continue;
      const text = await new File(asset.localUri).text();
      await packs.installFromText(text, { starter: true, size: text.length });
      installed++;
    } catch {
      // A missing starter file must never stop the app from starting.
    }
  }
  return installed;
}
