# 08 — Google Calendar two-way sync

> **Status (2026-10-19): DESIGN ONLY — implementation removed at user
> request ("for the moment").** The engine was built, migrated and
> live-tested earlier the same day, then rolled back: schema dropped
> (`migrate:down` of `20261019_150000_google_calendar_sync`), code files
> removed (`lib/component/Google/`, `app/front/module/Gcal.php`, the
> migration, the `/gcal/*` routes). A full copy of the implementation
> survives outside the repo in `/tmp/opencode/gcal-sync-backup/` on the
> dev machine. This document is retained as the design + decision log
> for picking the work back up; the component table below describes what
> WAS built and can be restored from that backup as-is.

Goal: the club calendar (master copy in the application database, served to
CalDAV/ICS clients by the existing backend) is mirrored to one Google
calendar and vice versa. Edits made in Google flow back into the database and
propagate to CalDAV clients unchanged; edits in the app (admin or CalDAV PUT)
flow to Google.

Implemented 2026-10-19 against the v1 scope decisions below. The engine
spec it implements arrived as a generic design (two-way Google sync for a
PHP CalDAV server); this file is the binding record of how it maps onto THIS
application, including every deviation.

## Scope decisions (2026-10-19, confirmed by Roan)

- **v1 without recurrence.** `calendar_event` has no RRULE column and no
  per-instance exceptions; the admin form, ICS export and CalDAV PUT parser
  are all recurrence-free. Adding recurrence first would touch every
  calendar surface and is deferred. Consequences:
  - Outbound: every local event is pushed as a single (non-recurring) Google
    event. Nothing else changes.
  - Inbound: a Google resource carrying `recurrence` (recurring master) or
    `recurringEventId` (instance exception) is NOT imported. It is recorded
    as a per-item failure (`recurring_not_supported_v1`) and retried-logged
    on every run; when such an event carries a map row (event became
    recurring after mapping) the local copy is left untouched.
  - A cancelled/deleted recurring INSTANCE therefore never reaches the local
    model in v1 — acceptable: v1 events in Google are all single events.
- **Hand-rolled REST client** (`Google_Calendar_Client`) instead of the
  official `google/apiclient` (its spec point PLT-002): the official client
  drags a large dependency tree onto a shared host, the project already
  hand-rolls its CalDAV endpoint, and no `composer require` is needed.
  OAuth refresh-token flow, events.list/insert/patch/delete and watch
  channels are all this app needs (~350 lines).
- **Triggers: token-protected URL + Google push webhook** (REQ-010):
  - `GET /gcal/sync?token=...` — pinged every 5–15 min by an external
    scheduler (hosting-panel cron, cron-job.org, uptime pinger). The token
    must equal the gitignored `google_sync_cron_token` config; wrong token →
    403 + log. The JSON response carries the run counters (GUD-003).
  - `POST /gcal/push` — watch-channel receiver. Carries no event data; the
    `X-Goog-Channel-ID` / `X-Goog-Resource-ID` headers must match the
    stored channel before a sync is triggered (SEC-003, AC-012 → 403).
    Unconfigured syncs absorb the notification with 200 `skipped`.
  - The push receiver runs the sync inline (a club calendar syncs in
    seconds; no job worker exists). Channel renewal is automatic: any run
    re-opens the channel when it expires within 24 h (6-day TTL vs Google's
    7-day max, CON-003); without `google_sync_webhook_url` the sync simply
    degrades to polling.

## Components

| File | Class | Role |
| --- | --- | --- |
| `migration/20261019_150000_google_calendar_sync.php` | — | schema (below) |
| `lib/component/Google/Calendar/Client.php` | `Google_Calendar_Client` | REST + OAuth + retry/backoff |
| `lib/component/Google/Calendar/Translator.php` | `Google_Calendar_Translator` | field mapping PAT-001 |
| `lib/component/Google/Calendar/Sync/Engine.php` | `Google_Calendar_Sync_Engine` | pull/push/lock/reconcile |
| `lib/component/Google/Calendar/Exception.php` | `Google_Calendar_Exception` | non-retryable failure |
| `lib/component/Google/Calendar/Exception/Gone.php` | `Google_Calendar_Exception_Gone` | HTTP 410 signal |
| `app/front/module/Gcal.php` | `App\Front\Module\Gcal` | the two endpoints |

Route: `/gcal/$action[sync,push]` in `app/front/config/routes.php`. The
variable constraint doubles as an allow-list: no other action dispatches.
Framework behaviour discovered while wiring it: skeleton-application-web
intercepts `?action=…` itself and calls `display_<action>()` via
`call_user_func` (same pattern as the Members module's
`display_events()`), so the two action handlers are **public** methods and
the module's `display()` is only the fallback for requests that arrive
without a valid action (404 JSON).

## Schema

`google_event_map` (one row per synced event pair) and
`google_calendar_sync` (one row of state) follow the project conventions
(`id`, `uuid`, timing columns), otherwise as the engine spec's section 4.1 /
4.2. `calendar_event` gains `sync_source varchar(32) NULL` — set to
`'google'` by every inbound write, left NULL for local/CalDAV writes
(REQ-015).

Deliberate deviations from the engine spec's SQL:

- **No foreign key on `google_event_map.local_event_id`.** The push phase
  detects "deleted locally, still in Google" precisely BY the map row
  outliving the event (orphan check); a FK (with or without cascade) would
  either break the app's hard deletes or hide the deletion. The engine
  cleans orphan rows up itself.
- **No `calendar` table / no `local_calendar_id` FK.** This app has exactly
  one club calendar; the column is kept (constant `LOCAL_CALENDAR_ID = 0`)
  for schema parity, and the state row is created lazily on first sync.

## Run algorithm (per run: pull → push, under lock)

1. **Lock** (REQ-007): one atomic `UPDATE … SET lock_owner = <token> WHERE
   lock-owner free OR last_run_at older than 10 min`; loser returns
   `skipped` with zero API calls (AC-007). The 10-minute steal window
   recovers locks from killed cron runs.
2. **PULL** (REQ-004): `events.list` with the stored sync token
   (`showDeleted=true`); 410 GONE → token cleared, full list, reconcile.
   Per item: cancelled/deleted + no map row → ignore; cancelled master →
   delete local event + map row; recurring → per-item failure (v1); no map
   row → create via `Calendar_Event` + `save()` (REQ-003); map row → LWW
   update. New token persisted; full syncs also stamp `last_full_sync_at`.
3. **PUSH** (REQ-008): first orphan map rows (local event gone →
   `events.delete` + row removed, 404/410 treated as already-deleted), then
   every event where the payload hash drifted from `last_pushed_hash` OR
   `updated > last_pushed_at`: no map row + visible → `events.insert` + map
   row; map row → `events.patch` (a patch 404/410 means Google deleted it →
   propagate deletion locally); hidden (`visible = 0`) with map row →
   delete in Google + drop row (hidden = not published anywhere, so it is
   not in Google either); hidden without map row → skipped.
4. **Conflicts** (AC-005): LWW on modification timestamps with a 2-second
   tolerance; near-equal timestamps resolve as Google-wins. Resolutions
   counted in `conflicts_resolved`.
5. Release lock, JSON-log the counters to `tmp/log/google-sync.log` (GUD-003).

Key invariants:

- **Echo suppression / idempotency (REQ-013):** after an inbound apply, the
  map row is marked pushed with the hash of the freshly-applied state, so
  the push phase sends nothing back; a no-op run makes no API writes and
  changes nothing but `last_run_at`.
- **REQ-012 retry policy** lives in the client: 429/5xx/network errors
  retried up to 5 attempts (1 + 4), exponential 1/2/4/8/16 s, `Retry-After`
  honoured; a 401 refreshes the token exactly once then retries (AC-010);
  everything else fails the item (REQ-014) or the run, never retried.
- **First sync:** start from an empty Google calendar — the database is
  master and pushes everything. Google-side events that already exist get
  imported as new local rows (deduping pre-existing twins is explicitly out
  of scope); the admin deletes the local dupe.

## Field mapping (spec/08 section 4.4 deltas)

- Times: local model stores UTC; timed events push/pull RFC 3339 UTC
  (`dateTime`), all-day events date-only (`date`). Google's all-day end is
  EXCLUSIVE (day after the last covered day): pushed as `ends_at + 1 day`,
  pulled back by subtracting a day — round-trip stable with the local
  inclusive semantics; a single-day all-day event omits `end`.
- `description` / `location` are always present in patch bodies (possibly
  empty strings — that is how a PATCH clears them); `end` is patched with
  an explicit `null` when the local model no longer has one.
- Google-side extras (attendees, reminders, colours, conference data) are
  not modelled locally and are therefore not synced; a patch never touches
  fields the local model does not know (PATCH semantics: absent keys stay).

## Configuration (gitignored `config/environment.php`, SEC-001)

```php
'google_oauth_client_id' => '…apps.googleusercontent.com',
'google_oauth_client_secret' => '…',
'google_refresh_token' => '…',        // offline consent, scope calendar
'google_calendar_id' => 'primary',    // or the calendar's address
'google_sync_cron_token' => '…',      // random string for GET /gcal/sync
'google_sync_webhook_url' => 'https://<host>/gcal/push',  // optional
```

Access tokens are never persisted: each run refreshes once and keeps it in
memory (short-lived cron process; SEC-004). The OAuth scope is
`https://www.googleapis.com/auth/calendar` (SEC-002). One-time setup: create
the OAuth client in a Google Cloud project (Calendar API enabled), run the
consent flow offline-style to obtain the refresh token, paste the keys above,
add the cron ping.

## Known v1 limitations

- Recurring events one-way-blocked (see scope): they fail per-item, never
  break the rest of the run.
- Interrupted-push duplicate window: a crash between a successful
  `events.insert` and its map-row write (milliseconds) would insert again
  on the next run. Accepted; the spec's own edge case makes the same trade.
- No test framework is vendored (`skeleton-test` absent, adding it needs a
  `composer require`), so translator/backoff unit tests are still TODO —
  see spec/06. Syntax-verified with `php -l`; runtime verification is the
  manual end-to-end cycle of VAL-001 once configured.
