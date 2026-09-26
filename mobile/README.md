# TestaCBT mobile app

Expo (React Native) app for Android and iPhone. Students practise WAEC, NECO and JAMB past questions and take timed mock exams, **with no internet after the first download**. It talks to the Laravel backend in the parent folder (`docs/API.md`).

## What it does

- **Offline first.** The app opens straight to Home with no connection. Questions come from downloaded packs (one small compressed file per exam and subject) and a starter set that ships inside the app. Answers, bookmarks and mock results are saved on the phone first and uploaded whenever there is a connection.
- **Practice** by subject, year and topic, with instant or end-of-session answers and English or Pidgin explanations.
- **Mock exams** in the real format (JAMB: Use of English + 3 subjects, 180 questions, 2 hours, out of 400; WAEC and NECO: one subject). The paper and deadline are stored on the phone, so closing the app never loses the exam or stops the clock.
- **Progress** (streaks, subject scores, weak topics), **Saved** questions, **Downloads** with sizes and a Wi-Fi-only switch.
- **Sign in** with email and password, or Google (development and store builds only).
- Progress follows the student to a new phone: signing in downloads their history.

## Run it

```bash
cd mobile
npm install
npm start          # then press "a" for Android, or scan the QR code with Expo Go
```

Point it at your server with `EXPO_PUBLIC_API_URL` (default `https://testacbt.com/api/v1`). For an Android emulator talking to a server on your own computer use `http://10.0.2.2:8000/api/v1`.

```bash
npm test           # 140+ unit tests (logic, real SQL on an in-memory database, sync, downloads)
npm run typecheck
npx expo-doctor
```

Expo Go can run everything except Google sign-in. Google sign-in needs a development build (`eas build --profile development`).

## Free sample and unlocking

Every subject has a small free pack (about 30 questions). The full pack comes after the student unlocks the subject on the website (card or bank transfer). The app never takes payment itself: **Unlock** asks the server for a one-time signed-in link (`POST /web-link`) and opens it in the browser, so students who joined with Google can pay too. When they return, the catalog is refreshed at once and the free pack is replaced by the full one (and back again if a purchase runs out). Mock exams need a full pack. Packs of the two kinds are numbered separately, so the app compares the `tier` as well as the `version` (`needsReplacing` in `src/core/views.ts`).

Before a store release, check Google Play's and Apple's rules on selling digital content: apps that unlock content usually must use the stores' own billing, or be allowed a link out to the website in your region.

## How it is built

| Folder | Purpose |
|---|---|
| `src/core` | Pure logic with no framework code: question selection (a port of the website's rules), mock building and marking, maths conversion, formatting. Most of the tests live here. |
| `src/db` | The on-device SQLite schema (versioned migrations) and every query. |
| `src/services` | API client, packs (download, checksum, unzip, install), sync engine, mock runs, sign-in. Each takes its dependencies as parameters so it can be tested without a phone. |
| `src/platform/native.ts` | The only file that touches Expo's native modules. |
| `src/state` | Small zustand stores (session, settings, catalog and packs). |
| `src/ui` | Design system (colours, fonts, components, the HTML renderer for question text). |
| `src/app` | Screens (Expo Router). |

Question HTML is rendered natively, not in a WebView (a WebView per question is too heavy for a 2 GB phone). Simple LaTeX maths such as `\( x^2 \)` and `\frac{a}{b}` is converted to readable Unicode. Very complex maths would need a real renderer later.

## Starter questions

`assets/starter/*.pack` are small original sample questions so the app works before anything is downloaded. They use negative question ids, so they never sync to the server. Rebuild them with `npm run starter`, or bundle real packs from your server (only if you hold the rights to that content):

```bash
node scripts/make-starter.mjs --from-api https://testacbt.com/api/v1 --token <token> jamb/english-language jamb/mathematics
```

## Icons

`npm run` is not needed: `node scripts/make-icons.mjs` redraws the placeholder icon set in `assets/images`. Replace those PNGs with final artwork any time.

## Releasing

Package name and bundle id: `com.testacbt.app`.

```bash
npx eas-cli@latest build --platform android --profile production   # an .aab for Google Play (or --profile preview for an .apk)
npx eas-cli@latest build --platform ios --profile production       # needs an Apple Developer account
```

Before a public release: set `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID` / `EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID`, add the Android client in Google Cloud with the SHA-1 from `eas credentials`, and confirm you have the rights to the question content.

## Testing without a Mac

iOS was checked by bundling the JavaScript for iOS (`npx expo export --platform ios`) and by running the same logic tests, but it has not been run on an iPhone or simulator. Test on a real iPhone with Expo Go, or an EAS build, before launch.
