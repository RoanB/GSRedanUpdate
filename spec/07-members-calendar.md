# 07 — Members area (files + calendar)

Naming decision: the feature formerly called "download center" is renamed to
**Members area** (the club board calls it this in Dutch). One shared password
gate (`$_SESSION['members_authenticated']`) protects both the file list and
the calendar — anything members-only lands there. The old `/download` URL
redirects to `/members` so existing links keep working.

## Gate (unchanged in mechanics)

**2026-09-17: separate members layout.** The members page extends its own
`_default/layout.members.twig` (a smaller tabler shell): same brand + logo,
but the navbar holds the members links instead of the admin modules, plus the
user `log out` pill only when an admin session exists. The breadcrumb header
always says "Members area". *(Superseded 2026-09-17 by the calendar tab: the
navbar is now *Files / Calendar / Admin* — see "Calendar tab" below.)*
**2026-09-18: no navbar on the gate screen.** Logged-out visitors only see
the password gate, and the members navbar (Files/Calendar/Admin) is hidden
for them (`layout.members.twig` wraps the `<header>` in
`{% if show_gate is not defined or show_gate == false %}`): there is nothing
to navigate before the shared password unlocks the area. The navbar returns
once authenticated (or on the calendar tab). The page-header ("Members area"
title) is hidden on the gate screen too (`show_gate == false` guard around
`page-header`), leaving only the password card on the page.
**2026-09-18: token URL.** A GET works like the gate form:
`GET /members?access=<shared password>` verifies the value against the same
`download_password_hash` setting (`Members::grant_from_secret()`, shared
with the POST form) and sets `members_authenticated`, then 302s to a clean
`/members` so the secret drops out of the address bar. Wrong/missing token
behaves like a wrong password (`?failed=1`). That means the shared password
doubles as a magic link — useful to seed the club board without them typing
it — at the cost that the password now also travels in URLs (browser
history, server access logs, bookmarks jumped on by hitting Enter). The
app's own `request.log` masks it (`Application::teardown()` rewrites
`?access=<v>` to `?access=********`; `Util_Log::safe_url` also obfuscates
the key); the residual exposure is outside the app (browser history, web
server access logs, HTTP referrers).

- `GET /members` renders the gate or the content, flagged by the session.
- `POST /members?action=gate` verifies the seeded setting
  `download_password_hash` (the setting name stays, it is referenced in
  `config` seeds and the admin screen). Wrong password → `?failed=1`.
- `GET /download` (module `Download::display()`) redirects to `/members` —
  the module class stays as a compat shim.
- Session key renamed from `download_authenticated` to
  `members_authenticated` (old key is dropped on gate load).

## Files with categories

- New table `download_category`: `id`, `uuid`, `name`, `sort_order`,
  `visible` (timing columns follow the magic pattern). Seeded with the
  three board categories from the old printed pages: `Documents`,
  `Planning`, `Photos`.
- `download_file` gains `download_category_id` int unsigned NULL FK
  (nullable: uncategorised files render in a default "All" group first).
- Public list groups by category; category names come from their rows, and
  a category with zero visible files is not rendered.
- Admin: categories managed in the same `Admin\Download` screen (add /
  rename inline form, drag & drop order, visibility toggle, delete only
  allowed when no file references it). The file edit form gains the
  category `<select>`.

## Calendar

Purpose: the club shows its yearly schedule (trainings, rig-ups, the rally…)
inside the members area, and club members subscribe the same calendar in
their phone / Outlook / Google via ICS or CalDAV.

### Data model

- Table `calendar_event`: `id`, `uuid`, `title` (varchar 255 NOT NULL),
  `description` text NULL (plain text: ICS DESCRIPTION + CalDAV),
  `location` varchar 255 NULL, `starts_at` datetime NOT NULL in **UTC**,
  `ends_at` datetime NULL UTC, `all_day` tinyint NOT NULL default 0,
  `visible` tinyint NOT NULL default 1, plus the timing columns.
- Time zone: stored UTC; ICS writes UTC with `Z` suffixes; the admin and the
  public page render in `Europe/Brussels` (calendar context of the site).
- `uuid` doubles as the CalDAV UID (stable across edits).
- Future events first (`starts_at DESC` for admin, `starts_at ASC` visible
  and starting today-or-later for the page), nothing is auto-deleted.

### Endpoints

- `GET /members/calendar.ics` — full ICS feed of visible events
  (`PRODID:-//GS Redan//Members calendar//EN`, VEVENT per row, `RSVP`-level
  completeness only: summary, description, location, dtstart/dtend or
  date-only for all-day events, UID = uuid, LAST-MODIFIED). Content-type
  `text/calendar`. **2026-09-17: public without authentication** ("fix ics
  download"): calendar clients cannot share the PHP session and the
  read-only CalDAV GET is already anonymous, so the ICS feed needs no
  password either (the members *page* keeps its gate).
- `GET /caldav/<uuid>.ics` and CalDAV on `/caldav/`: minimal **read-only
  CalDAV** backend (spec flags the reduced scope):
  - `OPTIONS` advertises `calendar-access`;
  - authenticated `PROPFIND` (Depth 0 on the principal, Depth 0/1 on the
    calendar) returns `calendar-data` collection entries;
  - `REPORT` `calendar-query` (and the empty variant) returns every visible
    event as a `calendar-data` resource;
  - `GET`/`PUT` on `/<filename>.ics` resources: GET streams; PUT is refused
    (editability lives in the admin, no two-way sync).
  - Authentication: HTTP Basic (login + password of an admin user — the
    most defensible shared-credential model without per-member accounts).

**2026-09-17 — two-way CalDAV:** the backend additionally answers **PUT**
on a resource (create / update the event as posted by the client) so the
calendars are no longer read-only.

**2026-09-18 — "Add calendar shows the token as the name":** Thunderbird
names a discovered calendar after the **HREF's last path segment** and
ignores the DAV `displayname` property. The collection therefore lives at
`/caldav-edit/fD5D6A4Z/GSRedan/` — the last segment (GSRedan) becomes the
imported calendar name, the secret stays inside the path, and the old
`/caldav-edit/fD5D6A4Z/` keeps working. Verified cycle: token-path GET,
per-item PUT (201) and GET (200) all under the *pretty* collection, and
the Depth-1 listing echoes matching hrefs + `calendar-home-set` on the
same path.
Lightning **ignores embedded `user:pass@` credentials in calendar URLs**
(stripped by policy) and *silently* fails a 401-challenged collection when
the password manager holds a stale login for the host — no prompt, no PUT,
the item stays local ("Modification failed" on earlier rounds). The
working contract, verbatim, is a **secret-token collection**:

- `https://<host>/caldav-edit/fD5D6A4Z/GSRedan/` — token-only editing URL.
  The path segment `fD5D6A4Z` *is* the credential (rotate via
  `caldav_edit_secret` in gitignored `config/environment.php`);
- routes map `/caldav-edit/<secret>/$resource` so Lightning sets
  `<secret>/GSRedan/<uid>.ics` as the item URL automatically;
- the collection PROPFIND answers with matching hrefs
  (`<collection-path>/<uuid>.ics`) and `calendar-home-set` resolving to
  the same token path, so Thunderbird builds resource URLs consistent with
  the token root;
- collection-root PUTs (Thunderbird's favorite "create" form) fall back to
  the ICS UID or a generated v4 UUID as the resource name;
- debugging helper: every CalDAV PUT payload is dumped to
  `tmp/last_put.ics` for wrong-date diagnosis (temp file, removed later).

The Basic-auth routes stay for compatibility (`/caldav-edit/` with
user:pass@host URLs is *kept* for curl tests; it just never worked from
Thunderbird's calendar UI because Lightning strips the userinfo). Events
get category colours from `calendar_category` (see [03](03-admin.md)) and
the calendar shows the time inside the chip plus location/description on
hover.
  - A full-dupe CalDAV server (PUT→sync, etags, aggregation, free/busy) is
    explicitly out of scope; this satisfies "caldav compatible" for
    read-only subscription in mainstream clients (Radicale-compliant GET/
    PROPFIND/REPORT).
- Semi-public display: after the gate, `/members` shows an "Upcoming
  events" card (next ~10 visible events ascending) next to the file card.
  No i18n pluralisation of the dates needed; long-format via `Intl` PHP
  formatter already available in the framework stack.
  **2026-09-18 — back on the files tab, and shared formatting:** the
  upcoming card returns to `/members` (the files tab), side by side with
  the file card (`col-lg-8` files / `col-lg-4` upcoming), in the same
  format as the calendar tab's list — date + time-range + location. The
  rows now come from one shared model helper
  `Calendar_Event::get_upcoming_entries($limit = 10, $visible_only = true)`
  (Brussels date `d/m/Y`, `H:i` time, `ends_label` only for timed events,
  plus `id` / `visible` / `calendar_category_id` for the admin variant);
  the members calendar tab's private `get_calendar_list()` delegates to it.
  The card footer links to `/members/calendar`.
- **2026-09-17 read-only calendar view:** the members page additionally
  shows a **read-only fullcalendar** (month/week, same composer asset
  `/fullcalendar/index.global.min.js`). The feed is
  `GET /members?action=events` (`Members::display_events`): visible events
  only, category colour, **no edit URL** — the members view is strictly
  read-only. Init lives in `base.js` (`init_members_calendar`, element
  `#members-calendar`); the fullcalendar script tag is on the members page
  only (the members layout now exposes a `footer_scripts` block for it).
  *(2026-09-18: chip label colour is now white or black by background
  luminance — `Calendar_Category::best_text_color()` — same as the admin
  feed.)*
  *(2026-09-18: full chip parity with the admin calendar — `eventContent`
  now uses the shared `build_calendar_chip()` in `base.js` (bold title,
  time inside the chip, native hover tooltip with time range, category,
  location and description), `dayMaxEvents: 2`, and the same inline colour/
  clipping guards via `eventDidMount`. The feed now also ships `category`
  `{id,name}` for the tooltip. A **category legend** (badge + name, contrast-
  safe text colour) sits in the calendar card footer; it only lists
  categories that currently have visible events (`count_visible_events() >
  0`), unlike the admin legend which lists all categories. Cache-bust
  `base.js?v=20260918f`.)*

### Calendar tab (2026-09-17)

Decision: all club-calendar information moves off the files page onto a
dedicated **calendar tab** at `https://<host>/members/calendar`. The
members area is now two tabs — *Files* (`/members`) and *Calendar*
(`/members/calendar`) — with a `nav nav-tabs` strip rendered by
`_default/layout.members.twig` when the module assigns `members_tab`
(`files` / `calendar`). The gate screen assigns neither, so it shows no
tabs; the navbar links are *Files*, *Calendar* and *Admin* (the old
`Calendar (.ics)` nav item is gone).

- Serving module: `App\Front\Module\Members\Calendar`
  (`app/front/module/Members/Calendar.php`). `/members/calendar` has no
  `routes.php` entry: skeleton's `Module::resolve()` maps the path to
  `\App\Front\Module\Members\Calendar` automatically. The class lives in
  namespace `App\Front\Module\Members`, next to the parent
  `App\Front\Module\Members` (their `Members.php`).
- Template `members_calendar.twig`: left the read-only fullcalendar
  (`#members-calendar`), right a **Subscribe** card and the upcoming-events
  list. The Subscribe card exposes the **public read-only CalDAV URL**
  (`https://<host>/caldav/GSRedan/`, no credentials) and the one-shot ICS URL
  (`https://<host>/members/calendar.ics`), both rendered through
  `tabler.copy_input()`, plus a *Download .ics* button.
- The page keeps the members password gate: `Members\Calendar::display()`
  redirects unauthenticated visitors to `/members`, which owns the gate
  form.
- The fullcalendar JSON feed moved from `Members::display_events()` to
  `Members\Calendar::display_events()`, so `base.js`
  (`init_members_calendar`) now fetches `/members/calendar?action=events`.
  The feed stays anonymous (like ICS/CalDAV GET); `/members?action=events`
  no longer exists.
- `Members::display_ics()` and the `/members/calendar.ics` route are
  unchanged (still served by the `Members` module, still public).
- Cache-busting: `base.js` is unchanged in name but its content changed,
  so the `?v=` query used by `layout.base.twig` and `layout.members.twig`
  was bumped to `20260917j`.

### Calendar-seeded Monday openings (see also 03)

- New category `Ouverture de la salle` (colour `#3fa7a7`), seeded by
  `20260917_160000_card_types`; it feeds the locked homepage card
  `monday_openings` (documented in [03](03-admin.md)).
- Current dates: the migration `20260917_170000_openings_exact` imports
  **exactly the announced "Openings 2026"** — 7 + 21 September, 5 October,
  9 + 23 November, 7 December, 19:30–22:30 Europe/Brussels (the generic
  "next 13 Mondays" seed of `20260917_160000` was removed with it). Times
  are ordinary calendar events the admin edits/removes freely; seeds only
  exist so the card and calendar have current data from day one.

## Admin management

- `App\Front\Module\Admin\Calendar` (`template/admin/calendar.twig` list,
  `template/admin/calendar_edit.twig` form):
  - fields: title, description (textarea), location, start date/time
    (datetime-local, Europe/Brussels), end (optional), all_day checkbox.
  - validation: title non-empty; `starts_at` required; end >= start when
    set; hidden events are skipped in every public output (page, ICS,
    CalDAV).
  - delete only via explicit button (mimics the block module hard delete —
    calendar entries are disposable).
- Admin menu gains "Calendar" above "Files"; the dashboard quick links get
  a calendar tile.
- **2026-09-17 admin screen — Thunderbird wiring.** The right-hand card is
  labelled *Calendar in clients* and covers three numbered
  URL blocks: 1. view only (no credentials), 2. edit
  (`https://redan:fD5D6A4Z@<host>/caldav/?edit=1`), 3. one-shot ICS import. The cards use `tabler.alert('warning', ...)` so
  the edit URL prompts to keep the password private.
- **2026-09-18 — card copy buttons + plain-English explanations.** The card
  now renders every URL through `tabler.copy_input()` (readonly input + copy
  button in an input-group) — a new `copy` icon in the tabler macro and
  `init_copy_buttons()` in `base.js` (Clipboard API, icon flips to a check
  mark on success, input-selection fallback). The two URLs were already the
  modern `/caldav/GSRedan/` and `/caldav-edit/fD5D6A4Z/GSRedan/` shapes. The
  Thunderbird-specific label and hint were dropped in favour of plain-English
  goal copy: the card subtitle explains that these links add the club
  calendar to any calendar app (live links update on their own, the ICS file
  is a snapshot), and each field gets a one-line hint (read-only/no login;
  edit link carries the password — share only with editors; ICS is a
  one-time download). The copy icon lives directly inside the button (the
  Tabler `btn-icon` negative-margin layout breaks when the icon sits in a
  wrapping `<span>`, which made the icon overflow the button edge).
  `base.js` cache-busting bumped to `20260918a`; the members **Subscribe**
  card (members_calendar.twig) got the same treatment — `copy_input()` for
  its two links and goal-copy hints — but shows only the live read-only URL
  and the ICS file, never the admin edit URL (it carries the password).
- **2026-09-18 — collapsible Subscribe cards.** The members **Subscribe**
  card and the admin **Calendar in clients** card collapse: a Collapse/
  Expand button sits in the card header (`data-collapse` + generic
  `init_collapse_toggles()` in `base.js`, two label spans with chevron-up/
  chevron-down icons, `d-none` toggling — no text surgery, translation-safe)
  and hides the card body (`#members-subscribe` / `#club-calendar-subscribe`)
  behind the still-visible header. The general explanation stays: the card
  title + subtitle never collapse, so a visitor who can't be bothered with
  the specific links still reads what they do. The members **Subscribe**
  card starts **collapsed** (the expansion link is one tap away; the common
  visitor path is the calendar + list, not the subscription URLs), the
  admin card starts expanded. New `chevron-up` / `chevron-down` icons in
  the tabler macro; `base.js` cache-busting bumped to `20260918g`.

**2026-09-17 PUT conditional-sync fixes:** the PUT pathway adds ETag
semantics — new resources answer `ETag` + `ETag: <new etag>` plus
`Location`; `If-Match` mismatches and `If-None-Match` with an existing
resource answer 412 (Thunderbird then resyncs). The anonymous collection
PROPFIND dropped the `<D:unauthenticated/>` stub for
`current-user-principal` — some clients treat that literal as an
unsupported principal and flag the calendar read-only; the anonymous
response simply omits the property now.

**2026-09-18 — root-put = full-collection sync (postmortem #2):** the
"wrong title / wrong date on new events" report was Thunderbird sending
its **entire local cache** in one PUT — 8 `BEGIN:VEVENT` blocks — while
the parser grabbed only the *first* VEVENT every time. Every edit thus
arrived as "Ouverture de la salle" and new items never reached the DB.
`parse_ics_events()` now splits the payload into per-VEVENT blocks and
`put_resource()` upserts **every** VEVENT, matched on the ICS UID
(per-resource PUTs filter to the matching VEVENT only). Rows are never
deleted by the sync; the broken-sync duplicates of the opener row were
removed once, data-wise, in the same session (rows 61, 63-67 + 54).

Also cleaned up meanwhile: `tretze` (23/09 12:00 Brussels) and `fqzfzf`
(24/09 12:00) now exist as real rows after the multi-upsert re-sync.

**2026-09-18 — CalDAV DELETE + deletion propagation + readonly lockdown
+ naming:**

- `DELETE /caldav-edit/fD5D6A4Z/GSRedan/<uuid>.ics` is implemented
  (previously 405): per-item delete answers `204`.
- Thunderbird *never* sends DELETE in practice — it pushes its whole
  local snapshot (multi-VEVENT PUT) with deleted items absent.
  Collection pushes on the editing path are therefore **authoritative**:
  every visible row missing from the payload is set `visible = 0`
  (soft-hide, recoverable in the admin) so deletions propagate.
- **2026-09-18 follow-up — deletion is a hard delete:** per user request
  the absent-from-sync rows are now **removed outright**
  (`Calendar_Event::delete()`), matching Thunderbird's model — the admin
  no longer keeps a tombstone. Verified live: probe row exists →
  collection push without it → row gone from the DB completely.
- the **public `/caldav/` collection is WRITE-LOCKED** (the readonly leak
  came from TB replaying its stored `redan` password against the readonly
  path): PUT and DELETE answer `405 read-only calendar` for everyone —
  anonymous *and* credentialed. GET/PROPFIND/REPORT stay anonymous.
  Verified: anon `PUT/DELETE 405`, creds `PUT/DELETE 405`, reads `200`.
- both collections advertise **GSRedan-named** hrefs
  (`/caldav/GSRedan/` readonly, `/caldav-edit/fD5D6A4Z/GSRedan/`
  editing) because Thunderbird names the calendar after the HREF's last
  path segment and ignores the DAV `displayname`. The ICS feeds also
  carry `X-WR-CALNAME:GSRedan`. Existing subscriptions keep their local
  name until removed/re-added.
- **2026-09-18 follow-up — the public subscription URL is named too:** the
  read-only URL handed out on the members Subscribe card and the admin
  "Calendar in clients" card now ends `/caldav/GSRedan/` (was `/caldav/`).
  Same mechanism as the editable collection: the HREF's last segment
  becomes the client-side calendar name, so the read-only import no longer
  shows up as "caldav".

**2026-09-17 well-known discovery:** routes additionally map
`/.well-known/caldav` + `/.well-known/caldavs` to the CalDAV module. They
redirect (HTTP 301) to `https://<host>/caldav/` so DAVx5 / Thunderbird's
provider auto-discovery against the club host finds the members calendar.
