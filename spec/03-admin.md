# 03 — Admin

Pattern copied from `../vvsjongeren`: tabler layout, `layout.base.twig`,
`macro.tabler.twig`, admin menu in `template/_default/menu.admin.twig`,
modules under `App\Front\Module\Admin` with `secure()` returning true only for
`$_SESSION['user']->admin === true`.

**2026-09-28 — `Admin\Base`:** that `secure()` check (plus the
`$login_required = true` flag) lived in every admin module. It now lives once
in `App\Front\Module\Admin\Base` (`app/front/module/Admin/Base.php`),
which every admin module extends (`Admin` included). The base also holds the
helpers the admin screens all needed:

- `get_current_user()` — `$_SESSION['user']`.
- `set_sticky()` / `redirect_with_message()` — see "Flash messages" below.
**No underscores in module and event class names.** `Admin\Base`, not
`Admin\Admin_Base`: skeleton's autoloader resolves the
`\App\Front\Module\` namespace by lowercasing the class name and then
`ucwords`-ing it (`Autoloader::load_class()`), so `Admin_Base` looks for
`module/Admin/Admin_base.php` — a lowercase `b` that only resolves on a
case-insensitive filesystem. That is why `Admin_Base` was "not found" on the
Linux server while it passed every local check. Underscores *are* the
convention for the `lib/model`, `lib/base` and `lib/component` include paths,
where they turn into directory separators: `Password_Reset_Mail` lives in
`lib/component/Password/Reset/Mail.php`, `Password_Reset_Token` in
`lib/model/Password/Reset/Token.php`. Same autoloader, opposite half of the
rule. The only reliable check is to replicate the transform against the real
file layout, not to read the error message and guess.

- `get_base_url()` — the absolute URL for links the admin opens elsewhere (a
  reset mail link, a members share link); a thin wrapper over
  `Http_Base_Url::get()`. The host is the **configured**
  `Application::hostname`, never the `Host` header: a mail link is read by a
  human, and a spoofed header would ship a live reset token to a host of the
  attacker's choosing. The scheme does come from the request
  (`X-Forwarded-Proto`, then `HTTPS`), because a front proxy is what decides
  that. `Admin\Download` builds its share URLs with it now instead of
  duplicating the scheme dance, and so does the login screen since the
  self-service reset mail.

## Login (`App\Front\Module\Login`)

Adapted from `vvsjongeren/app/front/module/Login.php`:

- Form: `login` (email) + `password`, tabler login layout
  (`layout.login.twig` exists already).
- On success: `$_SESSION['user'] = $user;` redirect to `/admin` (admins are the
  only users; non-admin rows get the same `no_organization`-style rejection:
  unset user + sticky message + redirect to login).
- `logout`: unsets `$_SESSION['user']` and redirects to `/login`.
- **2026-09-28 — `display_logout()` was missing.** The spec above already said
  "logout: destroys the session", and both the admin menu
  (`layout.base.twig`) and the members navbar (`layout.members.twig`) link to
  `/login?action=logout` — but no such method was ever written, from the very
  first commit. Skeleton's `handle_request()` is forgiving about this: when no
  `display_<action>` method exists it silently falls back to `$this->display()`,
  so clicking "Log out" showed the login form and left the session completely
  untouched. It looked like a link that did nothing, with no error anywhere.
  Written now, as `Login::display_logout()`. Two deliberate limits: only the
  user key is unset (the session stays alive for the language), and the
  members gate is *not* touched — it is a separate shared password with its own
  logout at `/members?action=logout`, so ending an admin session is no reason
  to lock the club board out. Sticky `logged_out` message on the login screen,
  same mechanism as `password_changed`.
- **A dead `?action=` link is invisible by design**, so all 25 `?action=` links
  in the templates were audited against the modules: each must resolve to a
  module file that really defines the `display_<action>`. That audit is what
  found this one; `php -l` and a Twig parse cannot.
- The vvsjongeren Organization switch logic (`select`,
  `$_SESSION['organization']`, `admin_user` impersonation) is removed; the
  "remember me" cookie also goes. `display_select` becomes a no-op removal and
  gets deleted instead.
- No account creation, no signup. Password stays seeded/admin-modified in DB.
- Sticky messages (`Session::set_sticky(...)`) use trans tags — see 05-i18n.md.
- **2026-09-28 — password reset form** (`display_reset_password()`,
  `template/password_reset.twig`, reached at
  `/login?action=reset_password&token=<raw>`): the user sets a new password
  there. The raw token is read from `$_GET` when the link is opened and from
  `$_POST` when the form comes back; `Password_Reset_Token::get_by_raw_token()`
  returns `null` for a used, expired or unknown token, and the template then
  shows "link not valid" with a back-to-login button and no form. On success
  `User::set_new_password()` runs (which also spends the token), a sticky
  `password_changed` message lands on `/login`, and the user logs in with the
  new password. Minimum 8 characters, same rule as the admin form.
- **2026-09-28 — self-service reset requested, previous decision reversed.**
  This section used to say there was deliberately no "I forgot your password"
  form, because an unauthenticated request form turns the login screen into a
  mail relay. The human asked for the button, so it is built — with the two
  properties that made the objection worth respecting:
  - **One answer for every address.** Known, unknown, archived and throttled
    all produce the same sentence ("If that address belongs to an admin
    account, a reset link is on its way…"), so the form cannot be used to
    discover who administers the site. `Login::send_reset_link()` returns
    quietly on every path, including a mail that fails to send: a broken mail
    server must not become an error oracle either. Nothing is logged.
  - **One mail per account per ten minutes**
    (`Password_Reset_Token::is_in_resend_cooldown()`,
    `RESEND_COOLDOWN_SECONDS = 600`). There is no rate-limiter package in the
    project, so the throttle is per account in the token table — it survives a
    cleared cookie, unlike a session counter. The already-sent link stays valid,
    so a throttled visitor loses nothing.
- The request form is `Login::display_reset_request()` at
  `/login?action=reset_request`: the login card swaps its login form for an
  email form ("Forgot your password?" / "Back to log in"), so there is one
  `autocomplete="username"` field on the page at a time. It only ever acts on
  an **active admin** (`get_active_by_email()` filters archived rows, plus an
  explicit `is_admin()` check), and the receiving half is the unchanged
  `display_reset_password()`.
- The reset URL is now built in two places, so the host/scheme decision moved
  out of `Admin\Base` into `Http_Base_Url::get()`
  (`lib/component/Http/Base/Url.php`). Still the configured
  `Application::hostname`, never the `Host` header; see "User management" for
  why that matters with a live token in the link.

## User management (`App\Front\Module\Admin\User`)

Added 2026-09-28. Route `/admin/user` by module convention (no entry in
`config/routes.php`, like the other admin modules). Every account is an admin
— the members area is password protected, not account based — so the module
always writes `admin = 1` and the forms have no admin checkbox.

- `GET /admin/user` — list: name (with a "you" badge on the current session
  user), email, language, and per row: edit link, reset form, archive form.
  The current user has no archive button, and `display_archive()` refuses it
  server side too: archiving yourself locks you out with no way back in
  through this screen.
- `GET /admin/user?action=add` / `POST` — `admin/user_edit.twig` with email,
  firstname, lastname, language select and a password field
  (`User::validate_password_strength()`, minlength 8, `required`).
- `GET /admin/user?action=edit&id=X` / `POST` — same template without the
  password field. The stored hash is never replaced by a value the typing
  admin picked; a password change is the mail flow. The module forces
  `admin = 1` again on save, since that is the only kind of user there is.
- `POST /admin/user?action=archive&id=X` — `User::archive(false)`, not
  `delete()`: the account leaves the list, can no longer log in
  (`get_active_by_email()`) and keeps its row, so the unique email stays taken
  and the reset tokens keep their foreign key (a hard delete fails on that
  foreign key for any account that ever requested a reset). Archive does not
  validate — it must not depend on the account data being complete — and it
  spends the user's open reset tokens. No un-archive screen.
- The module loads users through one private `get_user_by_id()` that refuses
  archived rows as well as missing ones: skeleton's `get_by_id()` selects by id
  without an `archived` filter, so a stale `?action=edit&id=…` link would
  otherwise keep working on an account that has left the list.
- `POST /admin/user?action=reset&id=X` — issues a token
  (`User::issue_password_reset_token()`) and mails the link through
  `Password_Reset_Mail::send()`. **POST, not a link:** a GET would let a mail
  client or link prefetcher fire the mail on its own. Feedback is sticky
  (`reset_sent` / `reset_failed`).
- Language select shows the English language name (`Dutch`, `French`) to match
  the English admin UI; the reset mail itself is written in the *recipient's*
  language, not the admin's (see 05-i18n.md).

Not done, and why:

- No "un-archive" and no "reveal password" — both need a decision about
  account recovery that a club admin does not need.
- No self-service reset request, see above.
- The table is not paged: the club has a handful of admins.

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
**2026-09-28 — users tile:** a fifth count tile (`User::count_all()`, active
accounts) linking to `/admin/user`, and a Users item in
`menu.admin.twig`. The counts are `COUNT(*)` queries
(`Block`/`Calendar_Event`/`Download_File`/`User::count_all()`) instead of
`count()` on loaded collections, so the dashboard stays cheap.

**2026-09-28 — the admin menu is grouped.** Eight flat items in one row stopped
being scannable, so `_default/menu.admin.twig` now reads: Dashboard, then a
**Content** dropdown (Cards, Calendar, Files), then an **Administration**
dropdown (Users, Settings), then the two links that leave the admin area
(Members area, View site) with their external icons. The grouping is by *what
the screen changes* — the pages and the schedule versus the accounts and the
site settings — which is the split an editor makes when they open the admin,
not by CRUD type. The two external links stay outside any group: grouping a
link that leaves the admin area under a heading that implies "editable
content" was worse than the flat row.

Implementation notes: the toggles are `<button class="nav-link dropdown-toggle
bg-transparent border-0" data-bs-toggle="dropdown">`, the same pattern as the
user menu in `layout.base.twig` — a `<button>`, because a dropdown toggle that
is not a link should not pretend to be one. Tabler renders a collapsed navbar's
dropdowns as `position: static`, so the groups expand inline on a phone without
extra CSS. A group's toggle carries `active` when the current module is one of
its screens, so the admin keeps their place; the paths are collected in
`content_modules` / `administration_modules` Twig variables and tested with
`in`, rather than five near-identical `if` lines. New msgids: `Content`,
`Administration` (see 05-i18n.md).

Admin chrome (`_default/layout.base.twig`, the members layout
`_default/layout.members.twig` and the login layout `layout.login.twig`): navbar
brand shows the club logo (`/logo.webp`, served from
`app/front/media/image/`) next to the "GS Redan" name; favicon is
`/favicon.ico` with `/favicon.jpg` as `icon` + `apple-touch-icon` fallback.

**The logo keeps its own colours on the admin and members navbars** — the brand
text beside it is white, the mark is not filtered. Whitening was applied on
2026-10-01 and reverted the same day: `brightness(0) invert(1)` flattens the
mark to a silhouette, and the logo is the club's identity. The mark's tan reads
~2.8:1 on the cave-green navbar, under the 3:1 WCAG asks of meaningful
non-text graphics, which is an accepted trade and the same contrast it had on
the old purple navbar. See 00-overview.md for the full sequence and the
measurement.
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
- **2026-10-01 — the "New card type" picker explains each type.**
  A card type name says what a card is *called*, not what it *looks like*, and
  one shared sentence for all six types ("Activity: one image + text. 2-row:
  …") had to describe six layouts at once, which it could not do without
  becoming a wall of text. Each option now carries its own one-line
  explanation in `Block::TYPE_EXPLANATIONS`, the hint beside the picker
  renders the explanation of the selected type, and `base.js`
  (`init_select_explanations()`) swaps it on `change`. The mechanism is
  generic — any `<select data-explanation-for="element-id">` with
  `data-explanation` on its options — because a `<select>` cannot hold rich
  content itself, and the strings travel from the template (data attribute)
  since the i18n extractor does not scan JS. Without JS the first option's
  explanation is rendered server-side, which is the correct one for the
  initially selected option.
- **Card type labels and explanations are translated** (same date). The
  labels were already routed through the catalog, but `Block::get_type_label()`
  used `Language::get_default()` — the *default* language, not the one the
  admin is browsing in — so a Dutch admin saw French type names next to
  Dutch chrome. Both `get_type_label()` and the new `get_type_explanation()`
  go through a private `Block::translate()` that prefers `\Language::get()`
  (the session language, what Twig uses for the `|trans` filter) and falls
  back to `get_default()` when no language is set (console, tests). The four
  singular type labels (`Masthead hero`, `Club text card`, `Contact card`,
  `Monday openings card (calendar-fed)`) had never been added to the
  catalogs and rendered as English in every language; they are filled in now,
  along with the six explanations. `base.js` cache-buster bumped to
  `20261001b` in `layout.base.twig` and `layout.members.twig` (the two
  layouts that load it; `layout.login.twig` still carries `20260925a` and
  does not need the new function).

  Verified against the real catalogs and the rendered template, per language:
  the six options carry a translated label and a non-empty
  `data-explanation`, the hint holds the first option's explanation, and the
  no-session-language path (`\Language::get()` throws) falls back to the
  default language instead of failing.
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
  [Decision 2026-09-23] Every editor gets a **Format** button in a
  `.code-editor-toolbar` strip above the editor (`base.js` injects it per
  editor around `init_code_editors()`). It runs `format_html()`: a vanilla
  pretty-printer that re-indents HTML with tabs (one tag per line, void and
  self-closing tags keep the depth, verbatim bodies for `pre`/`script`/
  `style`/`textarea`, inline text collapsed to single lines). The button
  label comes from the textarea's `data-format-title` (`{{ 'Format'|trans }}`),
  because the twig extractor does not scan JS files.
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
  `activities_dark` / `monday_openings` / `masthead`), `File::upload` resolved via
  `File::get_by_id` to a `Picture`; non-images are deleted immediately and
  the block is marked `image_failed`. Uploads happen through base.js
  (`data-upload-url` + `data-file-input`, multipart fetch, reload) — no
  jQuery. (The historical `image_layout` / `group_pair` options are gone —
  see [01](01-data-model.md) for the type conversion.)
- **2026-10-01 — masthead image upload:** the `masthead` block type is now
  in the `$is_uploadable` check of `display_upload_image()` and in the
  template's `has_single_image` list, so the hero background image can be
  changed from the block edit screen like any other image card. The public
  side of that (the responsive variants, the CSS custom properties, the
  preloads and `og:image`) lives in [02](02-public-site.md), "Masthead hero
  image"; this spec records only the admin behaviour.
  - The card preview shows the uploaded hero: `display_preview()` did not
    need a `masthead` entry in its image-slot list, because the preview
    includes `_default/hero_image.twig` like the public layout and that
    partial reads the same `masthead_image` the event assigns. Adding a slot
    would have been a second, divergent way to reach the same photo.
  - Uploading a masthead image replaces the previous file (slot 1 logic,
    shared with the other single-image cards), so there is no way to *clear*
    the hero and go back to the static banner — the same one-way rule the
    other cards follow. The static banner is only reachable on a masthead
    that was never given a photo.
  - No admin-side validation that the upload is a wide banner: the hero is
    cropped by `background-size: cover`, so a portrait upload is still
    rendered, just with more of it cut off. Refusing it would be a guess
    about the photo, not a constraint the page actually has.

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
- **2026-09-23 fix ("reordering the cards does not change the live
  preview"):** the preview now also refreshes after a **gallery picture
  drag-drop reorder**. `init_preview_refresh` exposes its re-render as
  `window.block_preview_refresh`; `init_sortable_tables` calls it in the
  `drop` handler's `fetch().then()` — i.e. only once the
  `POST /admin/block?action=picture_order` save has resolved, so the
  re-POST renders the persisted order. The hook is a guarded no-op on
  screens without a preview (the Cards list), where it is undefined.
  `base.js` cache-buster bumped → `?v=20260923a` (also in
  `layout.login.twig`, which still carried `?v=20260916`).
- **2026-10-01 — autoformat on save:** the card edit form now runs
  `format_html()` on every CodeMirror editor before the form is submitted
  (a `submit` listener on `form.preview-source` in `init_preview_refresh`).
  The stored body is always readable — one tag per line, consistent tab
  indent — without the admin having to click the Format button first.
- **2026-09-23 decision (light wiring):** `init_gallery_lightbox` moved out
  of `base.js` into its own `lightbox.js`, loaded on `layout.public.twig`
  and on the preview iframe (`admin/block_preview.twig`). Reason: the public
  layout never loaded `base.js` (admin/members/login only), so the spec'd
  gallery lightbox was **markup-and-CSS-only on the public site** — clicking
  a homepage picture just followed the href to the raw image. See
  [02](02-public-site.md).
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
- `download_password_hash` — never shown or read back; the shared secret is
  verified via `password_verify` only.
- One field `download_password` (blank by default): when submitted non-empty the
  server hashes it with `password_hash()` and stores the hash via
  `Setting::set_value()`; when left blank the existing hash is untouched.
- The template shows the field with an inline show/hide toggle
  (`data-toggle-password`, handled in `base.js`), followed by a note that
  leaving it blank keeps the current password.
- `GET /admin/settings` renders the password field empty — no hint about the
  actual value.
- **2026-09-23 (contradicts the earlier "clear text is never persisted"
  decision):** the same submission also persists the plaintext in
  `download_password` (a second setting). Rationale: the admin file list
  renders a share URL per file (`/members?action=get&id=X&access=<password>`)
  that skips the members gate; the bcrypt hash cannot be read back, so the
  plaintext must live alongside it. The plaintext only leaves the app inside
  that admin-only URL; `request.log` elsewhere masks `?access=`. On password
  change the hash and the mirror are written together.

**Discrepancy flagged (2026-09-23, still open on the random generator):** the
original design used a sticky `Session::set_sticky('message', ...)` and a
"generate random password" action that displayed the new password once in a
modal. The random generator is still not implemented: the admin types the new
value, which was chosen to keep the settings screen a single plain form. Human
confirmation of this simplification is still pending.

**Resolved 2026-09-28:** the sticky-message half *was* implemented, in the
original spirit and with the actual framework API. Every admin save/delete
redirect now sets a sticky session key and the templates read it as
`sticky_session.<key>`, instead of the old `?saved=1` / `env.get.saved` query
parameters. Two reasons: a message no longer sticks to the URL when the admin
refreshes or copies the link, and the reset token never appears in a URL that
gets bookmarked. A dead `sticky_session.error` branch was removed along the way
(no admin module ever set it).

There is no `modal.base.twig`: the file that existed under that name was a
byte-for-byte copy of `form.base.twig`, and neither was ever rendered (see
"Dead code" below).

### Hero copy (masthead)
- `masthead_title` (default `Gs Redan`), used by the `masthead` block partial.

## Cleanup pass (2026-09-28)

Data loss:

- `Admin\Settings` wrote every setting key in the table on each save, including
  ones no form field exists for. `footer_legal_entity` (seeded) was therefore
  wiped by any settings save. The write now only touches submitted fields, so
  the setting keeps its seed value. It is still not editable: nothing renders
  it (the footer uses `footer_company_number`).
- `Admin\Calendar` did not read the `visible` checkbox on save, so unticking a
  published event silently republished it. The posted value is now applied, and
  a new event defaults to `visible = 1`.
- Replacing a download file left the old file row and its bytes behind.
  `Download_File::replace_file(\Skeleton\File\File $file)` swaps the reference
  first and deletes the old `\File` after, so a failed save cannot lose the
  file that is still in use.

Dead code (verified unused, then removed):

- `template/_default/form.base.twig` and `modal.base.twig` — identical modals,
  no `{% extends %}` anywhere. `modal.base.twig` was a copy of `form.base.twig`.
- `template/_default/macro.base.twig` — held `pager_count` and
  `select_country`; its only importer was the dead `pager_header.twig`, and
  nothing instantiates a `Pager`. `select_country` was the only caller of
  `lib/model/Country.php`, which queried a `country` table this project never
  creates, so that model went with it.
- `template/_default/pager_header.twig`, `pager_links.twig` — skeleton pager
  boilerplate, no Pager in any module.
- `template/_default/menu.user.twig` — linked to `/customer/message` and
  `/customer/organization`, modules this project does not have.
- `Admin\Block::assign_block_image()` — private, never called. `assign_image()`
  covers the live paths.
- After the deletions, all 40 remaining Twig templates parse (checked with the
  real skeleton-i18n token parser).

Wiring, not deleting: `Admin\Download::display_rename_category()` was
unreachable because the category table rendered the name as plain text, even
though 07-members-calendar.md asks for an inline rename form. The form is now
there (hidden `id` + name input + pencil button per row). `init_sortable_tables()`
in `base.js` sets `draggable="false"` on `input`/`select`/`textarea` inside a
sortable row, same trick as the existing `img` guard, so selecting text in the
rename input does not drag the row.

Deliberately left alone:

- Nothing in the admin area. The public members area got its own small pass in
  the same session, on the human's call: `App\Front\Module\Members` now sets
  the gate error as a sticky key instead of `/members?failed=1`, and the
  duplicated members access link in `admin/download.twig` was collapsed (see
  07). `Members` does not extend `Admin\Base` — it has no admin session — so it
  carries its own `redirect_with_message()`.

## Permission rules (from AGENTS.md, human checkpoints)

- Running migrations needs explicit human approval.
- Anything that emails Peppol/bookings does not apply here.
- `template` changes and test-running are free; every migration gets a `down()`
  and both `up()`/`down()` tested manually.

## Theme (2026-10-01)

Admin chrome follows the shared caving palette in `00-overview.md` — green for
actions, warm yellow for the current place. Two things specific to Tabler 1.4
are worth repeating here, because both are invisible in the rendered result:

- Tabler themes through `--tblr-*`, never `--bs-*`. The old green/amber
  `:root` block in `base.css` was therefore inert, and the stock blue
  `#066fd1` primary was live the whole time. `--tblr-primary-rgb` and
  `--tblr-primary-lt-rgb` are hardcoded `rgb()` triplets in the vendor sheet
  and must be overridden explicitly; the `color-mix()` ones recompute.
- `--tblr-navbar-active-color` defaults to the near-black body colour, so a
  dark navbar needs it repointed or the current page is unreadable.

`--tblr-warning` is `lamp-600` (`#b45309`) rather than the brighter
`lamp-500`, because `.text-warning` paints `--tblr-warning-rgb` as text
(5.05:1 on white, against 2.22:1 for the brighter amber). The purple navbar
gradient, the purple card-header tint, the purple `.btn-primary` glow (which
haloed a blue button) and the purple `.page-wrapper` tint were all removed;
see `00-overview.md` for the full list.
