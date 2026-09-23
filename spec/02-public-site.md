# 02 — Public site

## Goal

`/` renders the GS Redan one-pager from the DB blocks, looking exactly like the
current static site, with FR/NL/EN switchable and menu order driven by the
admin.

## Templates (`app/front/template/`)

```text
_default/
	layout.public.twig      — Grayscale shell (head, navbar, footer; the
	                          footer partial is included directly, it is not
	                          a block any more)
_block/
	masthead.twig           — header.masthead (static, no translation except title)
	club.twig               — section "Le Club"
	activities_light.twig   — image + text card, light background (was the
	                          'featured' layout)
	activities_dark.twig    — image + black text box (was 'project'); corner
	                          parity alternates across the run of dark cards
	activities_pair_light.twig — 2-row welded card, light background
	activities_pair_dark.twig  — 2-row card, dark background (the classic
	                          Entraînement + Activités jeunes weld; one block
	                          carries both images and both texts, split by
	                          the pair marker)
	monday_openings.twig    — calendar-fed fixed card: image + auto list of
	                          the upcoming events of the calendar category
	                          'Ouverture de la salle' (date · time)
	gallery.twig            — one pictures row from block_picture rows,
	                          served through /picture; clicking opens the
	                          no-dependency lightbox (base.js)
	announcement.twig       — announcement block
	contact.twig            — contact cards (addresses + email) + social icons
	footer.twig             — footer (included from the layout, not a block)
index.twig               — loops blocks, includes the matching partial
members.twig             — members area (see 07)
download.twig            — compat shim → /members (see 04)
```

## Picture serving (`/picture`, `App\Front\Module\Picture`)

Card images (activity hero images, gallery pictures) are stored as
`\Skeleton\File\Picture\Picture` rows and streamed through
`/picture?id=<file_id>&size=<name>`. `size` names a registered resize
configuration (see Bootstrap: `1600x1200`, `1200x900`, `800x600`, `400x300`,
plus the tiny avatar sizes) — the resized copy is generated on first hit and
cached in `tmp/picture/<size>/<id>`, so uploads are effectively "auto-resize"
without double storage. Unknown ids 404; invalid sizes fall back to the
original. *(Fix 2026-09-17: "creation of resized images does not work" —
`build_resize` called `\Skeleton\File\Picture\Config::get()`, a method that
does not exist on that static class, so every request needing a resize died
with `Call to undefined method` and `tmp/picture/` stayed empty. The vendored
package is untouched; the call was removed — the cache root comes straight
from `Skeleton\File\Picture\Config::$tmp_path`, which Bootstrap already points
at `<root>/tmp/picture/`. No override existed to consult in the first place.)*

Assets: copy `../redan/assets/` into `app/front/media/` (`css/styles.css` and
theme JS merge with/next to `media/css/base.css`; images under
`media/image/`). Keep `?v=` cache-busting values.

**Decision — flat media URLs:** skeleton's `Media::detect()` finds
`app/front/media/{filetype}/{basename}` for a root-level request such as
`/rallye-1.webp`, so all asset URLs were rewritten from `/assets/img/rallye-1.webp`
to `/rallye-1.webp` (same for `/styles.css`, `/bs5.js`, `/scripts.js`). The
`/assets/img/...` form does **not** resolve through `Media`. The four
`url(/assets/img/caving-banner*.webp)` references in `styles.css` were rewritten
the same way. The Tabler admin assets keep their `/tabler--core/dist/...` paths,
which `Media` resolves via the package asset path.

## Rendering flow

`App\Front\Module\Index` (public, `login_required = false`):

1. Load `\Block::get_visible_ordered()`.
2. For each block get the `Block_Translation` for `$_SESSION['language']`,
   falling back to FR when missing.
3. `Template::get()` and assign `blocks` (with translations) + `settings`.
4. `index.twig` loops and includes `_block/{type}.twig`; a hidden block would
   never be in the list, and `display/edit` URLs must not leak content.

## Blocks and placeholders

Block bodies come from the DB (seeded from `../redan` HTML), but dynamic values
are **not** part of the seeded HTML — the template partials render them from
`Setting`:

- mailto links → `settings.contact_email`
- addresses + maps links → `settings.address_*_…`
- social links → `settings.social_*`
- footer company number → `settings.footer_company_number`
- year placeholders `.current-year` → Twig `now`, trans-tagged footer label.

When extracting the seed content from the three HTML files, strip those links
from the HTML so the admin setting takes over. Everything else (formulas,
styling classes like `accent-strong`, `rallye-badge`) stays inside `body` HTML.

## Menu

The navbar (`layout.public.twig`) shows one `<li>` per visible block whose
`anchor` is non-empty **and** whose translated `title` is non-empty, ordered by
`sort_order` — drag & drop in the admin automatically changes both page order
and menu order. The language-selector dropdown (FR/NL/EN) stays part of the
layout, not a block.

**Decision (deviation from the original text):** the menu condition is
`anchor AND title`, not anchor alone. The static `../redan` navbar omits "Le
Club" even though the club section has `id="about"`, because its label is empty
in the source. Requiring a non-empty title reproduces that: `club` keeps
`anchor='about'` (the `#about` deep link still works) but has an empty `title`,
so it is not rendered in the menu. Only `rallye`, `salle`, `training` and
`contact` carry menu titles. Consequence: an admin can show/hide a menu entry
purely by setting or clearing the per-language title, without touching the
anchor. *(Deviation 2026-09-17: the contact card edit screen no longer has any
inputs, so its title/anchor can no longer be changed per-card — `contact` keeps
its seeded anchor and title, and its menu entry is switched with the visibility
toggle on the Cards list. See 03-admin.md.)*

`nav_items` is built in `app/front/event/Module.php::bootstrap()` (not in the
Index module) so the same menu is available on every public page, including the
404 page (`not_found()`), which does not run the normal bootstrap.

## Anchors

`anchor` is the HTML id the section gets (`#rallye`, `#salle`, ...) and stays
unique. The admin can edit it; defaults match the current ids so existing
external links keep working.

## Language selection

- `$_SESSION['language']`, default `fr`.
- Language switcher posts `/lang/switch` with the requested language.
- Language persistence: session only (no cookie) — keep it simple.

## Preview

After the transplant the public site must visually match `../redan` 1:1 before
any admin work continues.

**2026-09-17 layout parity audit** (device: diff of rendered `/` against
https://redan.buysse.io/). Differences found and fixed:

- The site previously emitted **two** `projects-section` blocks (one for the
  activities cards, a second because `gallery` fell out of the wrap).
  Decision: all image-bearing card types share one wrapped section.
  **2026-09-17 cards rework:** `section_types` = `['activities_light',
  'activities_dark', 'activities_pair_light', 'activities_pair_dark',
  'gallery', 'monday_openings']`; `index.twig` opens/closes the
  `projects-section` around every consecutive run of these types.
- **2026-09-17 cards rework** — the original featured/project layouts are now
  separate card *types* (see [01](01-data-model.md)); the rendering notes
  below stay true per type:
  - `activities_light`: `row gx-0 mb-4 mb-lg-5 align-items-center` with
    `featured-text` (photo column + white text column as in rallye/salle);
  - `activities_dark`: the black `project` markup (`row gx-0
    justify-content-center`, img `col-lg-6`, black box `bg-black …project
    rounded-*` corners); the **parity** of the card within the consecutive
    dark run determines top vs bottom corners (`top-start/top-end` for the
    first, `bottom-start/bottom-end` for the second, matching
    training/youth). Parity is counted in the Index module.
  - the pair cards render as one `activities-pair` welded card (no wrapper
    rows outside it); both images (`file_id`, `file_id_2`) and both texts
    (pair marker) render top/bottom like ../redan training/youth.
  - `image_side` mirrors the photo column of light cards
    (`order-lg-first/last`); dark and pair corners are parity-driven.
- Gallery card: **no text rendered**, free amount of pictures (rows of
  three), click opens the lightbox (vanilla overlay in `base.js`, arrows
  wrap, Esc closes, full-quality copy served from `/picture?size=1600x1200`).
- Card `<img>` tags carry the `width`/`height` attributes of the original
  picture (from the `picture` table) so no layout shift happens, and
  `loading="lazy"` everywhere except the first image-bearing card in the
  page flow (that one stays eager for LCP).
- **2026-09-17 follow-up ("layout is no longer the same"):** the first pair
  implementation added an outer `activities-pair` wrapper that changed the
  section structure. Root cause + rules (superseded by the cards rework
  above, kept for history):
  - the projects-section is assembled from single blocks (the pair grouping
    via `group_pair` and `render_items` was removed in the cards rework: a
    pair card is now ONE block and renders directly);
  - the **pair renders with no extra wrapper element**: like ../redan, the
    two rows weld visually through the corner classes only (top row =
    photo right with `rounded-top-end`, black box top-left; bottom row =
    photo left with `mt-4` scaling, black box bottom-right). The gap
    closing lives in the row classes (`mb-5 mb-lg-0` on the second);
  - `project_layout` cornering ignores `image_side` for the project rows:
    the parity decides sides (even = photo right, odd = photo left),
    matching the original markup exactly.
- The language switcher in the navbar was rebuilt to mirror ../redan 1:1
  (`<button id="language-selector" class="dropdown-toggle nav-link
  dropdown">` + `dropdown-menu` with `<a class="dropdown-item">` anchors
  and the mobile `<span><a class="" ...>` trio). Links use the
  `?language=xx` query string; `app/front/event/Module.php` consumes the
  GET parameter into `$_SESSION['language']` (both the regular bootstrap
  and `not_found()`).

## Favicons

- Every layout fragment ships the same set: `rel="icon" href="/favicon.ico"`,
  `rel="icon" type="image/jpeg" href="/favicon.jpg"`, and
  `rel="apple-touch-icon" href="/favicon.jpg"` (with `?v=` cache-bust, see
  below).
- The `favicon.ico` and `favicon.jpg` files live in `app/front/media/image/`
  and are served by `Media::detect()` at the root (`/favicon.ico`).
- Layouts included: `layout.public.twig`, `layout.base.twig` (admin),
  `layout.login.twig` and `layout.members.twig` (members gate + tabs).
- **2026-09-18: regenerated `favicon.ico`.** The old `.ico` was a single
  75x75, RGB-all-black frame (alpha-only silhouette) — invisible-ish on
  dark tabs and a non-standard size. Rebuilt from `favicon.jpg` (colour
  badge, white corners flood-filled transparent, square 148x148 canvas) as
  a 4-frame ICO (16/32/48/64). favicon.jpg itself unchanged. Favicon links
  carry `?v=20260918a` for cache busting; `Http\Handler` strips the query
  string (`parse_url`) before `Media::detect()`, so query-param URLs
  resolve fine. Browsers cache favicons aggressively — the `?v=` bump is
  what actually makes a changed icon show up.

## Image quality

**Root cause of "horrible quality":** `Picture::show()` re-encodes every
resized image with `Manipulation::output()`'s default `quality = -1`; for
webp that collapses to quality 9 (scratchy blocks), for png to compression 9.
Decision: the file-picture package is not modified; the `/picture` module
implements its own serving logic instead:

- If the resize target is **larger than** the original, stream the original
  file directly (no re-encode, no quality loss, no cache file).
- If the target is smaller, resize with `\Skeleton\File\Picture\Manipulation`
  and output explicit `quality = 85` in `image/webp` (or `image/jpeg` for
  non-webp sources), cached in `tmp/picture/<size>/<id>`.
- Bad caches from the quality bug are wiped (`tmp/picture/*`) after the
  change so existing re-encodes are rebuilt.
