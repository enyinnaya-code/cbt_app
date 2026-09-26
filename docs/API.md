# TestaCBT mobile API (v1)

Base URL: `https://<your-domain>/api/v1`. All requests and responses are JSON, except the pack download.
Send `Accept: application/json` and, after signing in, `Authorization: Bearer <token>`.

Errors are always JSON: `401` not signed in, `403` suspended or not allowed, `404`, `422` validation
(`{"message": "...", "errors": {"field": ["..."]}}`), `429` too many attempts.

## Sign in

| Method | Path | Body | Notes |
|---|---|---|---|
| POST | `/auth/register` | `name, email, password (min 8), device_name, preferred_exams[] (exam slugs, optional)` | Always creates a student. |
| POST | `/auth/login` | `email, password, device_name` | 5 wrong tries per minute per email+IP, then `429`. Never suspends the account. |
| POST | `/auth/google` | `id_token, device_name` | `id_token` comes from Google Sign-In on the phone. `503` until `GOOGLE_CLIENT_IDS` is set on the server. |
| GET | `/me` | | Current user. |
| POST | `/auth/logout` | | Revokes this phone's token. |

Every sign-in response is `{ "token": "...", "user": { id, name, email, role, avatar_url, preferred_exams, has_password } }`.
Store the token in secure storage. One token per `device_name` (signing in again on the same phone replaces it);
the 10 most recent phones are kept. The app keeps working offline with an expired or revoked token; only sync and downloads need it.

Google sign-in rules: a new Google account creates a student. An existing **student** with the same email is linked;
if that email was never verified, the old password and sessions are removed (someone else may have registered it first).
Staff accounts (admin/examiner) are never linked automatically (`403`).

## Content packs

`GET /catalog` returns every exam, its subjects, and the current pack for each subject (or `pack: null`):

```json
{ "exams": [ { "slug": "jamb", "name": "JAMB", "subjects": [
  { "slug": "english-language", "name": "English Language", "display_name": "Use of English", "code": "En",
    "pack": { "version": 3, "size_bytes": 66754, "sha256": "...", "paper_count": 7, "question_count": 420,
              "years": [2018, 2019], "built_at": "2026-09-26T18:30:00+01:00",
              "url": "https://.../api/v1/packs/jamb/english-language" } } ] } ] }
```

Every subject also says what this student may use:

| Field | Meaning |
|---|---|
| `access` | `full` (unlocked, or a free subject, or staff) or `free` (only the free sample) |
| `price` | naira to unlock it (0 = free subject) |
| `free_questions` | size of the free sample |
| `expires_at` | when a purchase ends (`null` when not bought) |
| `full_question_count` | how many questions unlocking gives ("Unlock all 1,200 questions") |
| `pack.tier` | `full` or `free`: which pack `pack` describes. A locked subject offers the small free pack |

The catalog top level also has `urls.pricing` and `urls.checkout` (web pages where the student unlocks subjects) and each exam has `bundle_price`.
A pack's `tier` is also inside the pack file. After a purchase the catalog changes (new `access`, `pack.tier = full`), so refresh it and
download again; the full pack replaces the free one. Version numbers count separately for each tier, so compare `tier` as well as `version`.
The server also refuses `POST /sync/progress` answers and bookmarks for questions the student has not unlocked (they count as `rejected`).

Show `size_bytes` before the student downloads. The response has an `ETag`; send it back as `If-None-Match` and an unchanged
catalog costs a bodyless `304`. Compare `pack.version` with the stored version to know when to re-download.

`GET /packs/{exam}/{subject}` downloads `application/gzip` (a gzipped JSON file). Headers: `X-Pack-Version`, `X-Pack-Tier`, `X-Pack-Sha256`. The server picks the tier from the student's access.
Verify the SHA-256 of the downloaded bytes against the catalog before unzipping. It supports `Range` requests, so an interrupted
download can resume from the byte it stopped at. The app stores it in SQLite; nothing else is needed offline.

### Pack format (`format: 1`)

```json
{ "format": 1, "version": 3, "generated_at": "...",
  "exam": {"slug": "jamb", "name": "JAMB"},
  "subject": {"slug": "english-language", "name": "English Language", "display_name": "Use of English"},
  "topics": [ {"id": 5, "name": "Concord"} ],
  "papers": [ { "id": 8, "year": 2020, "title": "...", "duration_minutes": 30, "items": [
      {"id": 38, "type": "instruction", "html": "<p>Read the passage...</p>"},
      {"id": 39, "type": "mcq", "html": "<p>Question...</p>", "options": {"A": "...", "B": "..."},
       "answer": "C", "marks": 5, "topic_id": 5, "explanation_en": null, "explanation_pcm": null} ] } ] }
```

- `items` are in exam order. An `instruction` (passage or section heading) applies to the `mcq` items that follow it until the next one.
- `html` and option text are HTML. Scripts and event handlers are already stripped. Images are embedded as `data:` URIs.
- Question text may contain LaTeX maths, so the app needs a maths renderer.
- `explanation_*` are `null` until an examiner writes them.
- Never ask the server for an answer: it is in the pack, so practice and mocks work in airplane mode.

## Progress sync

`POST /sync/progress` uploads what happened offline. It is safe to send again after a dropped connection.

```json
{ "attempts": [ {"client_uuid": "uuid", "question_id": 39, "mode": "practice|mock", "selected": "C",
                 "time_ms": 8200, "answered_at": "2026-09-26T17:47:37Z"} ],
  "bookmarks": [ {"question_id": 39, "bookmarked": true, "changed_at": "2026-09-26T17:47:37Z"} ],
  "mock_sessions": [ {"client_uuid": "uuid", "exam_id": 3, "score": 268, "total": 400, "duration_seconds": 6480,
                      "taken_at": "...", "subject_scores": [ {"subject_id": 1, "correct": 40, "total": 60} ]} ] }
```

Limits per request: 500 attempts, 500 bookmarks, 50 mock sessions. Response:
`{ "accepted": {...counts}, "rejected": {"attempts": n, "bookmarks": n}, "server_time": "..." }`.

- Generate `client_uuid` on the phone once per answer or mock. The same uuid is stored only once.
- The server decides `is_correct` from the answer key. Anything the phone claims is ignored.
- Passages and unknown question ids are counted in `rejected`, not errors. Use `selected: null` for a skipped question.
- Bookmarks: the newest `changed_at` wins, so two phones cannot undo each other.
- Send times in UTC (ISO-8601). Dates more than a day in the future are pulled back to now.

`GET /sync/progress?cursor=<n>&since=<iso8601>` restores progress on a new phone or catches up.
Start with no parameters. If `has_more` is true, repeat with `cursor=<next_cursor>` (1000 attempts a page).
Afterwards, pass the last `next_cursor` and the last `server_time` as `since` to fetch only what is new. Times in responses carry their
offset (`2026-09-26T18:47:37+01:00`).

## Server side

```
php artisan testacbt:publish-papers --all --build   # publish draft papers and build packs
php artisan testacbt:build-packs                    # rebuild packs (only changed ones get a new version)
```

Set `GOOGLE_CLIENT_IDS` (comma-separated Android/iOS/web OAuth client IDs) in `.env` to enable Google sign-in.
