# 03 — Admin

Pattern copied from `../vvsjongeren`: tabler layout, `layout.base.twig`,
`macro.tabler.twig`, admin menu in `template/_default/menu.admin.twig`,
modules under `App\Front\Module\Admin` with `secure()` returning true only for
`$_SESSION['user']->admin === true`.

## Login (`App\Front\Module\Login`)

Adapted from `vvsjongeren/app/front/module/Login.php`:

- Form: `login` (email) + `password`, tabler login layout
  (`layout.login.twig` exists already).
- On success: `$_SESSION['user'] = $user;` redirect to `/admin` (admins are the
  only users; non-admin rows get the same `no_organization`-style rejection:
  unset user + sticky message + redirect to login).
- `logout`: destroys the session, redirect to `/login`.
- The vvsjongeren Organization switch logic (`select`,
  `$_SESSION['organization']`, `admin_user` impersonation) is removed; the
  "remember me" cookie also goes. `display_select` becomes a no-op removal and
  gets deleted instead.
- No account creation, no signup. Password stays seeded/admin-modified in DB.
- Sticky messages (`Session::set_sticky(...)`) use trans tags — see 05-i18n.md.

## Admin dashboard (`App\Front\Module\Admin`)

`template/admin/index.twig`; redirect `/admin`. Cards: number of visible/total
blocks, download files, quick links to the other modules. **2026-09-17:**
dashboard tile + menu item for the calendar (see
[07-members-calendar.md](07-members-calendar.md)); the download admin screen
gains inline category management (add / rename / delete when empty, drag &
drop order with `POST /admin/download?action=category_order`, visibility
toggles per category) and the file forms carry the category select.
**2026-09-18 — dashboard upcoming list:** below the four count tiles the
dashboard shows an *Upcoming events* card with the **next ten scheduled
events** (`Calendar_Event::get_upcoming(10)`, earliest first). It is a
management view, so **hidden events are included and flagged** with a
"Hidden" badge — unlike the members lists (`get_visible_upcoming`), which
only show published events. Each row shows the category badge (contrast-safe
text colour), the Brussels date/time (all-day entries skip the time), a
Hidden badge when applicable, and an edit link into `/admin/calendar`.
`Calendar_Event::get_upcoming_entries($limit, $visible_only=false)` builds
the rows; `Admin::get_calendar_upcoming()` adds the category colours.

Admin chrome (`_default/layout.base.twig` the login layout
`layout.login.twig`): navbar brand shows the club logo (`/logo.webp`, served
from `app/front/media/image/`) next to the "GS Redan" name; favicon is
`/favicon.ico` with `/favicon.jpg` as `icon` + `apple-touch-icon` fallback.
Tabler's `card-header` is a flex row, so a `card-title` paired with a
`card-subtitle` must be wrapped together in a `<div>` to stack vertically.

## Block admin (`App\Front\Module\Admin\Block`)

One screen (`template/admin/block.twig`) + one edit form
(`template/admin/block_edit.twig`). (Built as flat names, not the
`block/list.twig` + `block/edit.twig` pair sketched originally — skeleton's
Twig loader resolves one directory level and flat names avoid ambiguity.)

List view:

List view:

- One table row per block (all blocks, including hidden ones) ordered by
  `sort_order`, showing: type, anchor, menu label, visibility toggle.
- **2026-09-17 cards evolution:** the type column renders a coloured badge
  with a tooltip (like the fixed singular `masthead` / `club` / `contact` /
  `monday_openings`, creatable "card" or general explanation) plus a
  human-readable label from `Block::TYPE_LABELS`. A footer form ("Add card")
  creates new blocks: `POST /admin/block?action=add` posts `type`, the block
  validates against `CREATABLE_TYPES`, starts `visible = 0` at the end of
  the order and redirects straight into the edit form. Feature labels and
  help texts are trans strings. Creatable types since the cards rework:
  `activities_light`, `activities_dark`, `activities_pair_light`,
  `activities_pair_dark`, `gallery`, `announcement` (the layout is part of
  the type; there is no text-layout dropdown any more).
- The `footer` no longer shows in the list nor is it creatable: it is
  permanent page chrome rendered by `layout.public.twig` from the settings
  alone. Decided 2026-09-17 ("the footer should not be there, it is always
  the same").
- `masthead` is **not draggable**: `Block::is_draggable()` and a
  `non-draggable` row class (base.js skips those rows; the drag handle is
  replaced by a lock icon with tooltip). The order endpoint still accepts
  the rest of the rows unchanged. The `monday_openings` card is fixed
  (locked, cannot be created or deleted) but **is draggable** like any
  other card.
- Blocks input `drag & drop` (HTML5 `draggable`, vanilla JS in
  `app/front/media/javascript/base.js`, skeleton coding standard: snake_case,
  braces). On drop the client posts an ordered list of block ids to
  `POST /admin/block?action=order` with repeated `ordered[]` fields. No new
  libraries.
- Visibility: the toggle button posts to
  `POST /admin/block?action=visibility` with `id`; the server flips the flag and
  answers JSON `{ "visible": true|false }`, which the JS uses to update the
  button label (from `data-label-on` / `data-label-off`) and its class.
- Hidden blocks are shown with an outline button; visible rows use a filled
  success button.

Edit view (one stacked card per language, FR / NL / EN):

- `anchor` (text input) and `visible` (checkbox) in a sidebar card; `type` is
  read-only; `image_side` select only for `activities_light` and
  `monday_openings` (the dark and pair corners are parity-driven).
- **Code editor:** each `body` textarea gets the `code-editor` class and is
  replaced at runtime by a CodeMirror instance (`htmlmixed` mode: HTML with
  js/css inline highlighting, line numbers, active-line, line wrapping, tab
  indent). CodeMirror 5.65.18 is vendored locally in
  `media/javascript/vendor/codemirror/` + `media/css/vendor/codemirror/`
  (no composer dependency) and included via the `footer_scripts` block —
  **the core `codemirror.min.js` must be listed before the mode files**
  (they dereference the global; loading only modes produced
  "CodeMirror is not defined").
  The form stays untouched: CodeMirror wraps the original textarea.
- Per language: `title` (the menu label) + `body` (raw HTML, see above). No
  WYSIWYG; admins write HTML directly. The gallery card renders **no text**
  on the site: only the title is editable (alt text / menu label). The
  Monday openings card has no editable body at all — the text is generated
  from the calendar.
- **Pair cards (2-row):** the two row texts stay in ONE
  `block_translation.body` joined by the pair marker. The edit screen shows
  two CodeMirror textareas ("Content top row" / "Content bottom row",
  `body[<lang>]` and `body_pair[<lang>]`); on save the module stitches with
  `\Block::stitch_pair_body()` (marker `<!--pair-->`), on load
  `\Block::split_pair_body()` splits. A pair card also carries two image
  upload cards (top / bottom row; upload with `?action=upload_image&slot=2`
  writes `block.file_id_2`, replacing the previous file to avoid dead
  files).
- **Activity card extras:** an inline card shows the attached image
  (400×300 preview + original dimensions) and a replace upload
  (`POST /admin/block?action=upload_image&id=` for `activities_light` /
  `activities_dark` / `monday_openings`), `File::upload` resolved via
  `File::get_by_id` to a `Picture`; non-images are deleted immediately and
  the block is marked `image_failed`. Uploads happen through base.js
  (`data-upload-url` + `data-file-input`, multipart fetch, reload) — no
  jQuery. (The historical `image_layout` / `group_pair` options are gone —
  see [01](01-data-model.md) for the type conversion.)

### Monday openings card

Fixed singular card — **and, since `20260917_170000_openings_exact`, it IS
the former salle/Training Facility card**: block anchor `salle` converted
from `activities_light` to `monday_openings`, keeping its image, menu
labels (La Salle / De zaal / Training Facility) and exact styling. The
separate seeded `openings` block was removed. Behaviour family: **locked**
(no create, no delete — in `SINGULAR_TYPES`) but **draggable and
editable** (image, title, body text, anchor, visibility).

- Content: the per-language body keeps its static HTML
  (Training sessions on odd Mondays ..., ATTENTION! paragraph) with the
  `__OPENINGS__` placeholder where the date list goes. The Index module
  replaces it with the month-grouped labels of the visible upcoming
  events of the calendar category `Ouverture de la salle`
  (`Calendar_Category::OPENING_CATEGORY_NAME`,
  `Calendar_Event::get_visible_upcoming_by_category` limit 24,
  `Calendar_Event::format_opening_dates`). Club adds/removes/edits
  openings by managing calendar events of that category; the card updates
  itself.
- The openings dates come **exactly from the calendar** (limit 24, no
  other source): imported opening days for "Openings 2026" are 7 + 21
  September, 5 October, 9 + 23 November, 7 December 2026, 19:30–22:30
  Brussels (migration `20260917_170000_openings_exact`, replacing the
  generic 13-Monday seed of `20260917_160000`).
- Labels are localized per language: FR `7 et 21 Septembre`, NL
  `7 en 21 September`, EN `7th and 21st of September` (English ordinals);
  multiple dates of one month joined with the language's "and", grouped
  per month, joined with `<br>` inside the existing
  `<span class="salle-openings">` chip.
- The card can be placed anywhere by drag & drop and hidden via the
  visibility toggle like every other card.

## Admin calendar (fullcalendar)

**2026-09-17:** the calendar admin gained a full month/schedule view
(fullcalendar v6, installed via **composer** as `npm-asset/fullcalendar`
(pinned `^6.1`), served by the asset path at `/fullcalendar/index.global.min.js`
— the temporary local copy under `media/*/vendor/fullcalendar` is gone.
FullCalendar v6 bundles its own CSS at runtime (no css file to include).

- month grid on the calendar screen (`#admin-calendar`), week view toggle;
  first day Monday; the current date is kept when reloading.
- `GET /admin/calendar?action=events` streams a JSON feed
  (`{id,title,start,end,allDay,url,color,textColor,category:`): visible
  events use the category colour, hidden ones grey (the export skips them);
  clicking an event opens its edit screen. The init lives in `base.js`
  (`init_admin_calendar`).

**2026-09-17 — categories + brighter calendar:**

- `calendar_category` table (`id`, `uuid`, `name`, `color` (six-char hex),
  `sort_order`, timing columns; unique name) linked to calendar events via
  `calendar_event.calendar_category_id` FK (migration
  `20260917_140500_calendar_categories.php`); seeds: Réservation `#4a90d9`
  (replaced the original Entraînement seed — the same slot type, renamed per
  the club's request), Rallye `#f0ba4a`, Réunion `#e46a8f`,
  Sortie `#7a5cc9`, Divers `#67c29c`.
- The admin edit screen has a category `<select>`; each event chip in the
  month grid is drawn in the category colour (default `#9ab03e` lime).
- A colour legend (chip per category incl. its hex) sits in the calendar
  card footer; the events list underneath gained a Category badge column.
  *(2026-09-18: the legend badges use the same contrast-safe text colour as
  the calendar chips — `category_options()` now ships `text_color` —
  instead of the hardcoded black.)*

**2026-09-18 — chip hover info + overflow + category colours forced:**

- Chips clip long titles instead of overflowing their day cell: `.fc-event`
  and `.fc-event-main` get `overflow:hidden; text-overflow:ellipsis;
  white-space:nowrap; max-width:100%` in `base.css` (shared with the members
  grid, which uses FullCalendar's default chip content).
- The category colour is forced as an **inline style** on the chip in
  `init_admin_calendar` (`eventDidMount`, `backgroundColor` + `borderColor`).
  FullCalendar already maps the event's `color` to `backgroundColor`, but an
  explicit inline style survives browser extensions that strip injected
  `<style>` tags — the exact scenario the fullcalendar safety net in
  `base.css` guards against — so a chip never falls back to the default blue
  or looks unstyled.
- Hover tooltip enriched (native `title`, still no library): title, full time
  range (`calendar_build_time_label`, end date treated as exclusive — shifted
  back one day — matching the JSON feed), category name, location,
  description, and a "(hidden: not published)" marker for hidden events.
  Shared date/time formatters `calendar_pad`/`calendar_format_date`/
  `calendar_format_time`/`calendar_format_datetime` added to `base.js`.
- Chip title text is **bold** and ellipsis-clipped on the title element
  itself (`calendar-event-title`): `text-overflow` only applies where the
  overflowing content lives, so the one-level-deeper title div carries its
  own `nowrap`/`ellipsis`/`overflow` rules.

**2026-09-18 follow-up — readable chip text + overflow made self-sufficient
(after screenshot feedback: text bled past the chip and was hard to read):**

- Chip label colour is picked **per category** by WCAG relative luminance
  (`Calendar_Category::best_text_color()`): backgrounds darker than the
  black/white contrast crossover (luminance < 0.179) get white text, light
  ones black. Both event feeds (`Admin` and `Members` `display_events`)
  pass it as `textColor`, replacing the hardcoded `#000000` (hidden events
  compute against the grey `#e9ecef` background).
- Overflow clipping no longer depends on `base.css` loading: `eventDidMount`
  sets inline `overflow:hidden` on the chip element and `eventContent` sets
  inline `nowrap`/`overflow`/`ellipsis`/`max-width` on the title div, so a
  stale or stripped stylesheet cannot make titles bleed past the chip.
- **Blue chip text fix (2026-09-18):** the chip is an `<a>`, so Tabler's
  link colour painted the label blue instead of the feed's `textColor`.
  `eventDidMount` now also inlines `color` (from `info.textColor`) and
  `text-decoration:none` on the chip; `base.css` keeps
  `color:inherit;font-weight:700` so the whole chip text is bold in the
  feed-chosen colour. Same guard added to `init_members_calendar`.
- Chip text bumped to `.8rem` (min-height 1.3rem) and day cells to 4.2rem so
  two chips still fit; cache-bust `?v=20260918e` for both `base.js` and
  `base.css` on both layouts.
- **CalDAV credentials**: HTTP Basic account `redan` / `fD5D6A4Z`
  (overridable via `calendar_caldav_user` / `calendar_caldav_password` in
  gitignored `config/environment.php`). Two URLs in the admin interface:
  - read-only: `https://<host>/caldav/GSRedan/` — **no authentication
    required** (spec follow-up); retrieves only.
  - two-way editing: `https://redan:fD5D6A4Z@<host>/caldav/?edit=1` — the
    embedded credentials are never stored server-side; write rights equal
    the `redan` service account or an admin user.
  Only PUT authenticates: GET/PROPFIND/REPORT are anonymous. **The
  two-way editing path is `https://redan:fD5D6A4Z@<host>/caldav-edit/`** —
  a full editable collection per Thunderbird (PROPFIND + PUT). The traces
  `/caldav-edit/` requires credentials on every request (service account
  `redan` or an admin user); `?edit=1` is kept only for backwards
  compatibility.
- **2026-09-17 fixes:** FullCalendar 6 exposes `FullCalendar.Calendar`
  (not `window.Calendar`) — the init guard checks
  `typeof window.FullCalendar`. v6 also requires the **explicit
  `calendar.render()`** call after constructing the object: without it the
  constructor wires everything (including the event fetch) but never
  paints the grid, which produced the "created but did not render a
  skeleton" diagnostic. Editing an event pre-fills the
  datetime-local inputs from the stored UTC values in Brussels time
  (`starts_at_display` / `ends_at_display`, `T`-separated) because raw UTC
  strings do not prefill; and blank "Add event" forms
  (`?action=edit` without id) no longer attempt to fetch id 0 — they start
  from a fresh model and go through `?action=add` on save.
- **Gallery cards** manage their pictures inline: an ordered preview table
  (drag & drop reusing `data-sort-url` → `POST /admin/block?action=picture_order`,
  `Block_Picture::save_order`), single delete link
  (`?action=delete_picture` removes the `Block_Picture` row *and* the
  underlying file), and a multi-file upload
  (`?action=upload_picture`, `File::upload_multiple`; non-image uploads are
  dropped, valid images get `sort_order = count(pictures)`). Rendering
  happens through `/picture` with on-demand resize + cache (see 02).
- Cards creatable since the generics migration (activity / gallery /
  announcement via the "Add card" footer form) **and removable**:
  `?action=delete` with a JS `confirm()` dialog (`delete-confirm` in
  `base.js`). Deleting a card cascades: per-language translations and
  attached pictures (including the underlying files) are removed before the
  block row itself, because `block.file_id` and `block_picture.file_id`
  would otherwise block the file delete. The fixed cards (masthead / club /
  contact / monday_openings) are part of the page structure and refuse
  deletion (`Block::is_deletable()`); the server answers
  `/admin/block?delete_failed=1` and the template explains why.

## Live preview (edit screen)

The edit screen gains a **preview pane**. **2026-09-17 rework:** the
preview is a **full-width horizontal panel above the editor grid** that is
**collapsible** (card with a chevron button, toggled by base.js
`init_preview_collapse`, label flips Collapse/Expand, body hidden with
`d-none`); the iframe height is adjusted by `init_preview_height` after
every load:

- `GET /admin/block?action=preview&id=X` returns the card rendered with the
  **submitted** form values when provided (POST, without saving) — the
  pair-card bodies are split from `body[]` + `body_pair[]` and stitched
  again for the render; the Monday openings card renders the real calendar
  event list. Without POST data it renders the stored state.
- The response is a standalone HTML fragment (template
  `admin/block_preview.twig`, no layout include) that loads the public
  `styles.css` of the site so colours/classes match the real page, and the
  matching `_block/*.twig` partial is rendered with the same variables as
  the homepage.
- The preview iframe on `block_edit.twig` refreshes itself (vanilla JS, in
  `base.js`): it re-posts the current form values to the preview URL on
  CodeMirror `change` events (debounced 500 ms) and on every title/anchor/
  checkbox change; it also re-renders when a picture upload finishes
  (upload flow already reloads the page).
- **Contact card edit screen (2026-09-17):** the contact card is rendered
  entirely from settings (`_block/contact.twig` uses only `settings.*`), so
  its edit screen shows **no inputs at all** — no menu label, no content
  HTML, no anchor, no visibility checkbox, no save button. It renders an
  info notice pointing to `/admin/settings`; ordering and visibility stay
  on the Cards list screen. The preview iframe gets an explicit `src`
  (the refresh flow needs a form to post, which the contact screen no
  longer has) and renders the stored card.
- **2026-09-17 gallery/self-serve fixes in `base.js`:** the upload field
  name now comes from the **input's own `name` attribute** — the gallery
  input is `files[]` (server reads `$_FILES['files']`), the single-image
  inputs are `file`. The former heuristic (`files.length > 1 ? 'files[]' :
  'file'`) silently broke single-file gallery uploads (the server saw no
  `$_FILES['files']` and redirected with `image_failed`). Sortable tables
  gained a `dragover` preventDefault on the **tbody** (drops over gaps
  between rows were silently ignored — the drop event only fires where
  `dragover` was cancelled) and in-row `<img>` elements get
  `draggable="false"` so a native image drag can no longer hijack the row
  reorder. Cache-buster bumped `?v=20260917j` → `?v=20260917k`.
- The preview iframe never writes; only the member of `secure()` can call
  it. Content is HTML-escaped the same way the real partials do (`|raw` as
  in vendor layouts).
- `title[]` / `body[]` are normalised to arrays before use
  (`is_array($_POST['title'] ?? null)`); a malformed/non-array field falls
  back to `''` for every language instead of clobbering translations with
  string-offset garbage (found by testing, formerly wiped rows).
- "Blocks are not created/deleted in the UI" is **overridden 2026-09-17** by
  the cards evolution above: creatable generic cards and delete-with-confirm
  (hard delete cascading translations + pictures) live in the
  "List view" / "Cards creatable" bullets and in this file's cards spec; see
  the two bullets for the current model.

**Discrepancy flagged:** the original text said `announcement` "may be
created/removed through the settings screen". That was dropped — the settings
screen only edits key/value settings, no block rows. `announcement` is a normal
seeded block (seeded with `visible = 0`) and is shown/hidden like any other.

## Announcement block

Exactly one `block` row with `type='announcement'`, seeded between the usual
blocks. It behaves identically to every other block: orderable, hideable,
per-language title/body, included in the menu via its `title`. Nothing special
in the UI.

## Settings (`App\Front\Module\Admin\Settings`)

Patterned on `vvsjongeren/app/front/module/Admin/Settings.php`
(`display_save`, `Session::set_sticky('message', 'settings_saved')` + redirect
back). Tabs:

### Contact
- `contact_email` (validated with `filter_var(..., FILTER_VALIDATE_EMAIL)`)
- `address_local_street`, `address_local_zip`, `address_local_city`,
  `address_local_maps`
- `address_club_street`, `address_club_zip`, `address_club_city`,
  `address_club_maps`
- `social_instagram`, `social_facebook`
- `footer_company_number`

### Members area (tab renamed from "Download page")
- `download_password_hash` — never shown or read back.
- One field `download_password` (blank by default): when submitted non-empty the
  server hashes it with `password_hash()` and stores the hash via
  `Setting::set_value()`; when left blank the existing hash is untouched. The
  clear text is never persisted, logged or shown again.
- The template shows the field with an inline show/hide toggle
  (`data-toggle-password`, handled in `base.js`), followed by a note that
  leaving it blank keeps the current password.
- `GET /admin/settings` renders the password field empty — no hint about the
  actual value.

**Discrepancy flagged:** the original design used a sticky
`Session::set_sticky('message', ...)` and a "generate random password" action
that displayed the new password once in a modal. Neither was implemented: saves
redirect to `/admin/settings?saved=1` and the page shows a success alert from
`env.get.saved`. There is no random generator and no `modal.base.twig`; the
admin types the new value. This was chosen to keep the settings screen a single
plain form. Human confirmation of this simplification is still pending.

### Hero copy (masthead)
- `masthead_title` (default `Gs Redan`), used by the `masthead` block partial.

## Permission rules (from AGENTS.md, human checkpoints)

- Running migrations needs explicit human approval.
- Anything that emails Peppol/bookings does not apply here.
- `template` changes and test-running are free; every migration gets a `down()`
  and both `up()`/`down()` tested manually.
