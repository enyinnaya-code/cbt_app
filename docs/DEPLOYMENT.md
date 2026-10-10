# TestaCBT: updating the live server and releasing the app

Last checked: 10 October 2026. Keep this file up to date when the setup changes.
It holds no passwords or keys. Those live in `.env` on the server, in `~/.gradle/gradle.properties` on the build computer, and in the signing key file.

## Where things are

| Thing | Where |
| --- | --- |
| Live website folder | `/var/www/cbt_app` on the server (Apache `httpd`, document root `public`, files owned by `apache`) |
| The empty `/root/cbt_app` | Not the site. Ignore it. |
| Git remote and branch | `https://github.com/enyinnaya-code/cbt_app.git`, branch `testacbt-redesign` (`main` is untouched) |
| Local project on the developer PC | `C:\xampp\htdocs\CBT` (web) and `C:\xampp\htdocs\CBT\mobile` (Expo app) |
| Local databases | `cbt` (the real local one) and `testacbt_dev` (a demo copy used with `APP_ENV=clone`) |
| Database backups on the PC | `storage/backups/` (ignored by Git, because they hold real student data. Never commit them.) |

## Updating the live website

Always in this order. Take the backup first.

```bash
cd /var/www/cbt_app
mysqldump -u DB_USER -p DB_NAME > /root/cbt_app-before-update.sql     # DB_USER and DB_NAME are in .env
ls -lh /root/cbt_app-before-update.sql                                # must not be tiny
git status                                                            # look first; files you uploaded are normally ignored
git pull origin testacbt-redesign
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=ExamCatalogSeeder                         # safe to repeat, adds only what is missing
php artisan testacbt:build-packs                                      # packs also rebuild by themselves when papers change
php artisan optimize:clear
chown -R apache:apache storage bootstrap/cache public/uploads public/downloads
systemctl reload httpd
curl -sI https://testacbt.com/login | head -8                         # expect 200 and an X-Frame-Options line
```

One-time on the server: `git config --global --add safe.directory /var/www/cbt_app` (Git refuses to run as root in an apache-owned folder otherwise).

Signed-in users are asked to sign in again after an update that changes sessions. That is expected.

### Settings the live `.env` must have

- `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
- Leave `LEGACY_SCHOOL` out (the old school screens stay off)
- The Paystack key (saved in Site settings, or `PAYSTACK_SECRET_KEY` in `.env`) and, in the Paystack dashboard, the webhook `https://testacbt.com/api/webhooks/paystack`
- `GOOGLE_CLIENT_IDS` (comma-separated web, Android and iOS client IDs) when Google sign-in is turned on
- After editing `.env`, run `php artisan optimize:clear`

### Email

Set it in the website, not in `.env`: **Content, Site settings, Email account** (host, port, TLS or SSL, username, password, from address). The password is stored encrypted. Press **Send a test email to me**. Until an account is saved, emails (including "Forgot my password" links) only go to `storage/logs/laravel.log`.

### Never do this on the server

- Do not run `php artisan testacbt:remove-old-tests` there. It deletes old tests and the papers made from them.
- Do not run `migrate` before the backup exists.
- Do not delete `public/uploads/posts/` (uploaded article pictures).

## Releasing a new version of the Android app

The app is **not kept in Git**. Each build is about 60 MB.

1. Bump the version in `mobile/app.json`: `expo.version` (for example `1.2.0`) **and** `expo.android.versionCode` (a whole number that must go up every release; Android will not update to a lower or equal number).
2. Build on the PC (needs JDK 17+, the Android SDK at `%LOCALAPPDATA%\Android\Sdk`, and the signing key):
   ```
   cd C:\xampp\htdocs\CBT\mobile
   node scripts/build-apk.mjs
   ```
   The first build took about 35 minutes; later ones are faster. The result is `public/downloads/TestaCBT.apk` plus `TestaCBT.json`.
   Alternative: GitHub, Actions, **Build Android app**, Run workflow. It attaches the app to the run (Artifacts); it no longer saves it into the repository.
3. **Signing key.** The key is `~/.testacbt/testacbt-release.jks` (alias `testacbt`), with its passwords in `~/.gradle/gradle.properties`. Keep a backup of it somewhere safe: an installed app can only be updated by a build signed with the same key. The app students have is signed with certificate SHA-256 `40:AC:C5:EE:BD:71:11:8B:A0:AF:C2:7F:36:CE:AC:7D:77:70:1A:46:2A:0E:C8:7D:35:99:7B:A4:B8:0D:3B:94`. Check a new build with `apksigner verify --print-certs TestaCBT.apk` (in the SDK's `build-tools`).
4. Put it on the website: **Content, Site settings, App downloads**, upload the `.apk` and type its version. The server's PHP `upload_max_filesize` and `post_max_size` must be at least 70 MB. (Or copy the two files into `/var/www/cbt_app/public/downloads/` and `chown apache:apache` them.)
5. Check: `curl -s https://testacbt.com/api/v1/app-version` shows the new `"latest"`.

Students with an older app see an "A new version is ready" banner on Home (apps from 1.1.0 onward) with **Update now** (opens the download page) and **Later**. Students on 1.0.0 have to be told once to download the new app. Installing over the old app keeps their progress.

If `git status` on the server shows `public/downloads/TestaCBT.apk` as untracked, that is normal and safe: the files are ignored on purpose.

## Developing on the PC

- Web tests: `php artisan test` (about 525 tests). Mobile: in `mobile`, `npx tsc --noEmit` and `npx jest` (about 207 tests).
- Demo copy: `APP_ENV=clone` uses database `testacbt_dev` and its own pack folder `storage/app/private-clone` (set in `.env.clone`). Start it for a phone with `php -S 0.0.0.0:8000` from `public`.
- Phone testing with Expo Go: `EXPO_PUBLIC_API_URL=http://<pc-ip>:8000/api/v1 npx expo start --lan` from `mobile`. Release builds ignore non-https API addresses.

## Facts worth remembering

- An admin reset sets the password to `123456` and forces the person to choose their own at the next sign-in (the app is blocked until they do, on the website).
- The daily challenge and rankings are opt-in (nickname, picture, optional school, privacy policy accepted). The policy is at `/privacy`; raising `privacy_version` in `config/testacbt.php` asks everyone to accept again. Have a lawyer review the policy text.
- Papers publish their packs automatically; packs also rebuild when questions or topics of a published paper change.

## Paystack (online payments)

Checked against Paystack's own documentation pages (Accept Payments, Webhooks, Verify Payments, Payment Channels, Gateway Responses) on 10 October 2026. The flow is Paystack's hosted "Redirect" checkout.

- **How it works:** `POST /transaction/initialize` (email, amount in kobo, currency, our order reference, `callback_url`) sends the student to Paystack. When they return, `GET /transaction/verify/{reference}` decides, never the redirect. The `charge.success` webhook (header `x-paystack-signature`, HMAC-SHA512 of the raw body with the secret key) unlocks the subjects even if the student closed the page. Access is granted only when `data.status` is `success`, and the amount, currency and reference match the order. The order is locked while it is marked paid, so the redirect and the webhook together count once.
- **Status handling:** `success` unlocks. `failed`, `abandoned`, `reversed` mark the order failed. `pending`, `ongoing`, `processing`, `queued` (a bank transfer or USSD payment still under way) leave the order open and the webhook finishes it.
- **Fee bearer:** if the Paystack account makes the customer bear the fee, Paystack's `amount` is price plus fee and the price is `requested_amount`; both are accepted, an underpayment never is.
- **Setup:** paste the secret key in **Content, Site settings, Paystack key** (test key `sk_test_...` first, then `sk_live_...`) and press **Check the key**. It is stored encrypted, shows only test/live and the last 4 characters, and takes effect at once. A key saved there wins over `PAYSTACK_SECRET_KEY` in `.env` (which still works if nothing is saved). In the Paystack dashboard (Settings, API Keys & Webhooks) set the webhook URL `https://testacbt.com/api/webhooks/paystack` **separately for Test and for Live**. Webhooks are retried for 72 hours (live) until our server answers 200, and the dashboard can resend events.
- **Test, then live:** make a test-mode purchase with a card from Paystack's "Test Payments" page, check the order is Paid and the webhook delivery log shows 200, then switch to the live key and live webhook URL and make one small real payment.
- **Server check:** `curl -s -o /dev/null -w "%{http_code}
" https://api.paystack.co` should print a number (Paystack needs a TLS 1.2 connection).
- **Not handled yet:** refunds and chargebacks (`refund.processed`, `charge.dispute.*`) do not take access back; an admin would suspend or edit the order by hand. Add them from Paystack's Refunds and Disputes pages if needed.
- **Optional extra:** Paystack's webhook IPs are 52.31.139.75, 52.49.173.169 and 52.214.14.220. We rely on the signature, which is enough on its own.

