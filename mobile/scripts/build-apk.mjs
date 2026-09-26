// Builds the signed Android app (.apk) on this computer and puts it where the website serves it:
//   node scripts/build-apk.mjs
// which writes ../public/downloads/TestaCBT.apk (+ TestaCBT.json with the version), ready to commit and pull on the server.
//
// One-time setup (not stored in the repository):
//   * JDK 17 or newer, and the Android SDK (Android Studio installs both).
//   * A signing key. Create it once and KEEP A BACKUP: an app can only be updated by a build signed with the same key.
//       keytool -genkeypair -v -keystore %USERPROFILE%\.testacbt\testacbt-release.jks -alias testacbt -keyalg RSA -keysize 2048 -validity 10000
//     then add these lines to %USERPROFILE%\.gradle\gradle.properties:
//       TESTACBT_KEYSTORE=C:/Users/you/.testacbt/testacbt-release.jks
//       TESTACBT_KEYSTORE_PASSWORD=...
//       TESTACBT_KEY_ALIAS=testacbt
//       TESTACBT_KEY_PASSWORD=...
//
// The app talks to https://testacbt.com/api/v1 unless EXPO_PUBLIC_API_URL is set when you run this.
import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const outDir = join(root, '..', 'public', 'downloads');
const isWin = process.platform === 'win32';
const ARCHS = 'arm64-v8a,armeabi-v7a';   // nearly every phone in use; leaves out emulators and very old chips to keep the file small

const run = (cmd, args, opts = {}) => {
  console.log(`\n> ${cmd} ${args.join(' ')}`);
  const r = spawnSync(cmd, args, { cwd: root, stdio: 'inherit', shell: isWin, env: process.env, ...opts });
  if (r.status !== 0) { console.error(`\nFailed: ${cmd} ${args.join(' ')}`); process.exit(r.status ?? 1); }
};

// ---- check the machine ----
const gradleProps = join(homedir(), '.gradle', 'gradle.properties');
const props = existsSync(gradleProps) ? readFileSync(gradleProps, 'utf8') : '';
for (const key of ['TESTACBT_KEYSTORE', 'TESTACBT_KEYSTORE_PASSWORD', 'TESTACBT_KEY_ALIAS', 'TESTACBT_KEY_PASSWORD']) {
  if (!new RegExp(`^${key}=.+`, 'm').test(props)) { console.error(`Missing ${key} in ${gradleProps}. See the notes at the top of this file.`); process.exit(1); }
}
const keystore = props.match(/^TESTACBT_KEYSTORE=(.+)$/m)[1].trim();
if (!existsSync(keystore)) { console.error(`The signing key was not found at ${keystore}.`); process.exit(1); }

process.env.ANDROID_HOME ||= process.env.ANDROID_SDK_ROOT || join(process.env.LOCALAPPDATA ?? join(homedir(), 'AppData', 'Local'), 'Android', 'Sdk');
if (!existsSync(process.env.ANDROID_HOME)) { console.error('Android SDK not found. Install Android Studio, or set ANDROID_HOME.'); process.exit(1); }
process.env.NODE_ENV = 'production';
process.env.EXPO_NO_TELEMETRY = '1';

// ---- generate the native project (kept out of git; it is rebuilt from app.json every time) ----
// (prebuild rewrites a few lines of package.json; put it back so the build leaves no changes behind)
const packageJson = readFileSync(join(root, 'package.json'), 'utf8');
run('npx', ['expo', 'prebuild', '--platform', 'android', '--no-install', '--clean']);
writeFileSync(join(root, 'package.json'), packageJson);

// ---- sign release builds with our key instead of the debug key ----
const gradleFile = join(root, 'android', 'app', 'build.gradle');
let gradle = readFileSync(gradleFile, 'utf8');
if (!gradle.includes('TESTACBT_KEYSTORE')) {
  gradle = gradle.replace(/signingConfigs\s*\{/, `signingConfigs {
        release {
            storeFile file(TESTACBT_KEYSTORE)
            storePassword TESTACBT_KEYSTORE_PASSWORD
            keyAlias TESTACBT_KEY_ALIAS
            keyPassword TESTACBT_KEY_PASSWORD
        }`);
  gradle = gradle.replace(/(buildTypes\s*\{[\s\S]*?release\s*\{[\s\S]*?)signingConfig signingConfigs\.debug/, '$1signingConfig signingConfigs.release');
  if (!gradle.includes('signingConfigs.release')) { console.error('Could not set up release signing in android/app/build.gradle (the generated file has a new shape).'); process.exit(1); }
  writeFileSync(gradleFile, gradle);
}

// ---- build ----
const gradlew = join(root, 'android', isWin ? 'gradlew.bat' : 'gradlew');
run(gradlew, ['assembleRelease', `-PreactNativeArchitectures=${ARCHS}`, '--no-daemon'], { cwd: join(root, 'android') });

// ---- publish next to the website ----
const built = join(root, 'android', 'app', 'build', 'outputs', 'apk', 'release', 'app-release.apk');
if (!existsSync(built)) { console.error(`The build finished but ${built} is missing.`); process.exit(1); }

mkdirSync(outDir, { recursive: true });
copyFileSync(built, join(outDir, 'TestaCBT.apk'));
const version = JSON.parse(readFileSync(join(root, 'app.json'), 'utf8')).expo.version;
writeFileSync(join(outDir, 'TestaCBT.json'), JSON.stringify({ version, built_at: new Date().toISOString(), size: statSync(built).size }, null, 2) + '\n');

console.log(`\nDone: public/downloads/TestaCBT.apk (${(statSync(built).size / 1048576).toFixed(1)} MB), version ${version}.`);
