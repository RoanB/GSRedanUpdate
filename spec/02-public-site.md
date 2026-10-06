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
	masthead.twig           — header.masthead: the hero. Title + per-language
	                          caption only; the background photo comes from the
	                          card image through the CSS custom properties in
	                          styles.css (see "Masthead hero image")
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
	                          no-dependency lightbox (lightbox.js)
	announcement.twig       — announcement block
	contact.twig            — contact cards (addresses + email) + social icons
	footer.twig             — footer (included from the layout, not a block)
index.twig               — loops blocks, includes the matching partial
members.twig             — members area (see 07)
```

`app/front/module/Download.php` is the compat shim for the old `/download`
URLs: it redirects to `/members` and has no template. It never had a template
of its own to keep, though `template/download.twig` did sit around unused until
2026-09-28 (see 04).

## Picture serving (`/picture`, `App\Front\Module\Picture`)

Card images (activity hero images, gallery pictures, the masthead hero) are
stored as `\Skeleton\File\Picture\Picture` rows and streamed through
`/picture?id=<file_id>&size=<name>`. `size` names a registered resize
configuration (see Bootstrap: `1600x1200`, `1200x900`, `800x600`, `400x300`,
plus the tiny avatar sizes and the three hero sizes) — the resized copy is
generated on first hit and cached in `tmp/picture/<size>/<id>-<md5prefix8>`
(since 2026-09-23; before that the key was the id alone — see "Image
quality"), so uploads are effectively "auto-resize" without double storage.
Unknown ids 404; invalid sizes fall back to the
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
`/assets/img/...` form does **not** resolve through `Media`. The
`url(/assets/img/caving-banner*.webp)` references in `styles.css` were rewritten
the same way; they survive today as the fallback values of the
`--masthead-image-*` custom properties (see "Masthead hero image"). The Tabler
admin assets keep their `/tabler--core/dist/...` paths, which `Media` resolves
via the package asset path.

## Masthead hero image

**2026-10-01.** The hero photo is no longer the committed
`app/front/media/image/caving-banner*.webp` set: it is the image uploaded on
the masthead card (admin side in [03](03-admin.md)), served through `/picture`
like every other card image. The static banner stays in the repository as the
fallback, so a masthead without a photo — and the three error pages, which have
no masthead card at all — look exactly as before.

- **Responsive variants.** The masthead is the LCP element of every public
  page, so it keeps a four-tier picture set of the static banner it
  replaces: one URL per breakpoint, `640x320` (<640px), `1280x640`
  (640–1279px), `1920x960` (1280–1919px) and `2560x1280` (≥1920px). These are
  the exact dimensions of the static files, so replacing the photo does not
  change the pixel budget of the page. They are registered as separate resize
  configurations in `Bootstrap` (`Masthead_Image::SIZES`), not reused from the
  card sizes: `auto` mode fits inside the box, and a card size like
  `1600x1200` would deliver a 4:3 crop of a banner that has to cover a
  full-bleed 85vh hero.
- **The breakpoint boundaries are the variant widths, not Bootstrap's
  breakpoints — this is load-bearing, and it was wrong until 2026-10-01.**
  `.masthead` is `width: 100%` with `background-size: cover`, so it has to be
  handed at least one image pixel per viewport pixel or the browser stretches
  the photo, and the hero is the largest thing on the page. The ladder used to
  switch at 768 and 1920, which handed the 1280px variant to every viewport from
  1281 to 1919 — a 1366, 1440, 1512, 1536, 1600 or 1700px laptop, i.e. most
  desktops. Measured against the real stylesheet, every one of those was
  upscaled:

  | viewport | 1280px variant | upscale |
  |---|---|---|
  | 1366 | 1366 | 1.07x |
  | 1440 | 1440 | 1.13x |
  | 1536 | 1536 | 1.20x |
  | 1700 | 1700 | 1.33x |
  | 1919 | 1919 | 1.50x |

  Worst at the seam, because 1919px got the 1280px file and 1920px got the
  2560px one. Boundaries are now 640 / 1280 / 1920 — each the width of the
  variant it switches away from — and every viewport from 500 to 2560px
  measured clean. The `1920x960` step is new, so that a 1440px laptop gets a
  1920px image instead of paying for the full 2560 one; without it, aligning
  the boundaries would have jumped 1280 → 2560 (78 KB → 302 KB) for the most
  common viewport. The static `caving-banner-wide.webp` is a genuine
  downscale of the 2560 master (webp q80, 167 KB); it was generated to match
  the committed variants, verified by re-deriving `caving-banner-mid.webp` the
  same way and comparing (RMSE 1.2%). **The number 640/1280/1920 now appears in
  four places — `Bootstrap` config names, `Masthead_Image::SIZES`, the
  `styles.css` media queries and the `hero_image.twig` preloads. Change one
  and you have to change all four.** The preloads in particular are written as
  *closed* ranges, because an open-ended `media="(min-width: 640px)"` on four
  separate links would match three of them at once on a wide screen and
  download the whole ladder; verified 1-of-4 matching at every width.
- **`caving-banner-small.webp` (1280x640, 78 KB) is dead.** Only the stale
  minified `.masthead` rule near the top of `styles.css` still names it, and
  the theming block near the end overrides that. Left in place pending a
  decision — deleting it was not authorised.
- **`Masthead_Image` (lib/component) resolves the URLs, not the template.**
  One class builds the variant map and the absolute `og:image` URL, so the
  size names live in one place instead of being spelled out in three templates.
  It looks the card up with `Block::get_masthead()` (by type, not through the
  visible ordered list: hiding the hero is a visibility decision and must not
  detach its photo) and resolves the `Picture` the same way the Index module
  does for card images — a dangling `file_id` falls back to the static banner
  rather than painting a hero whose background 404s.
- **`event/Module.php` assigns `masthead_image` on every page**, not just the
  homepage: the members area and the error pages carry their own
  `<header class="masthead">` but the same photo. The 404 path assigns it too,
  because `not_found()` never reaches `bootstrap()`.
- **The photo travels as CSS custom properties**, set by
  `_default/hero_image.twig` into a `<style>` block:
  `--masthead-image-{small,medium,wide,large}`. The stylesheet reads them with
  the static banner as `var()` fallback, so the cascade needs no `!important`
  and a page without the properties simply paints the banner. An alternative —
  an inline `style` attribute on the `<header>` — was rejected: it would have
  to be repeated in the masthead partial *and* the three error templates.
- **`hero_image.twig` also carries the preloads**, replacing the three
  hardcoded `rel="preload"` links the layout used to emit. A preload whose
  media query does not match the one the stylesheet switches on is a wasted
  download, so the two have to be edited together.
- **The `|raw` filters on the `url()` values are load-bearing.** `<style>` is a
  raw text element: the HTML parser does not decode entities in it, so without
  `|raw` the `&amp;` of the `/picture?id=..&size=..` query string would reach
  CSS literally and every variant would 404. The `href` attributes of the
  preloads keep the escaped form, which is what an attribute value wants. The
  values are server-generated from a validated integer id, so there is nothing
  to inject.
- **`og:image` follows the hero**, as the absolute URL of the `2560x1280`
  variant (`Http_Base_Url::get()` + the path — a crawler resolving a share
  from another host cannot follow a site-relative one). It falls back to the
  static banner. Facebook does not need a registered image URL, and the
  `/picture` endpoint is public, so this costs nothing extra.
- **No `srcset`.** The hero is a CSS background, not an `<img>`, and `srcset`
  only applies to the latter. The three variants are what makes the responsive
  choice; the picture itself is served with `background-size: cover` and is
  cropped by the browser, so an upload does not have to match the banner's
  aspect ratio to look right.

## Sitemap and robots.txt (`/sitemap.xml`, `/robots.txt`)

**2026-09-29 — added.** Both are generated, not committed, so the hostname and
the sitemap URL live in one place (`Http_Base_Url`, the same helper the reset
mail uses) instead of being a string that drifts after a domain change. Both
are anonymous (`login_required = false`, a crawler has no session) and send
`Cache-Control: no-store`: a long cache would have to reason about the session
cookie PHP attaches to the response, and a crawler fetches these a few times a
month anyway.

### The sitemap

One `<url>` per language, not one per section. The public site is a single
page: the blocks are sections of the homepage (`#rallye`, `#salle`, ...). A
fragment is not a URL, so `/#rallye` is not listable and there is nothing else
to list — three entries (`/en`, `/fr`, `/nl`), each carrying the full hreflang
set, is the complete public site.

That is not a formality. Nothing on the page links to the French homepage *as
a page*: the switcher is a language selector and its links carry the session
language, so without a sitemap a crawler discovers only the one variant it
happened to request from its `Accept-Language` and never learns the other two
exist. The `x-default` alternate points at the unprefixed `/`.

- Only languages that are actually routable are listed. `Seo_Sitemap` parses
  the `$language[en,fr,nl]` restriction out of the `Index` route in
  `app/front/config/routes.php` and intersects it with `\Language::get_all()`.
  Hardcoding the three letters would publish a 404 the day a language is added
  to the route list, or silently drop one that was added to the database.
- `<lastmod>` is real: the newest `updated` among the visible blocks and the
  visible upcoming calendar events (the Monday-openings card renders events).
  Date only — the columns are local datetimes and the site publishes no
  timezone.
- `<changefreq>` and `<priority>` are omitted. Google has ignored both since
  2015, and a made-up crawl frequency is noise that looks like signal.

### robots.txt

Everything public is allowed; the private paths are disallowed. A `Disallow`
is not a lock: the admin, the members area and the CalDAV subscription are
protected by sessions and passwords, and robots.txt only keeps them out of the
index. The one thing it genuinely guards is the CalDAV token in the path,
which a crawler would otherwise be free to walk into.

- `/login` and `/members` are also routable with a language prefix
  (`/fr/login`, `/fr/members`). A robots.txt line matches a *path prefix*, so
  `Disallow: /login` does **not** cover `/fr/login`; the wildcard form
  (`Disallow: /*/login`) is what blocks every translation.
- `/admin`, `/caldav`, `/caldav-edit` and `/picture` are unprefixed by design
  (see `app/front/config/routes.php`), so they need no wildcard.

### Two framework behaviours made this non-obvious; do not "simplify" them away

1. `/sitemap.xml` cannot be a plain module path. `Module::resolve()` turns the
   URI into a classname segment by segment, and `Sitemap.xml` is not a
   classname. It is dispatched through a route with a constrained variable
   (`/$file[sitemap.xml]`) — the only way in for a path containing a dot.
2. `/robots.txt` cannot be a route at all. The media detector runs **before**
   the router (`Application\Web::run`) and claims every request whose
   extension it knows, and `txt` is a known extension (the `doc` filetype).
   With no `app/front/media/doc/robots.txt`, the core `Media` event throws
   `Media\Not\Found`, which `Application\Web` turns into a 404 before
   routing happens. `App\Front\Event\Media` therefore intercepts that miss
   and serves the generated text. A committed
   `app/front/media/doc/robots.txt` would be found by the detector first and
   win — the static file overrides the generated one, which is the obvious
   outcome.

### AI crawlers: the only AI-related setting in the project

There is no AI, LLM or machine-translation integration anywhere in the code;
translations are expected to come from a human/TAS/DeepL workflow (05-i18n.md).
The single knob is `Seo_Robots::BLOCKED_CRAWLERS`, **empty by default**.
Filling it emits one `User-agent: x` / `Disallow: /` group per entry.

The trade-off: a `Disallow` does not stop a bot from *reading* the page, it
decides whether the content may be used for training and whether the URL may
appear in an answer. Blocking the training crawlers (GPTBot, CCBot,
Google-Extended) while leaving the search engines and the on-demand fetchers
alone is a values decision, not a technical default.

**Decision 2026-09-29 — allow all crawlers.** Asked explicitly, the club chose
to leave `BLOCKED_CRAWLERS` empty. The public page is meant to be found, and a
club that wants caving documented in answers has no reason to hide from
assistants. This is the deliberate choice, not the untouched default: do not
fill the array in without asking again. Flipping it later is a one-line edit,
which is the point of keeping the knob visible in `Seo_Robots` rather than
hand-writing the groups into a static file.

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

**Decision:** `not_found()` sets its status with `http_response_code(404)`, not
with `Skeleton\Core\Http\Status::code_404()`. The framework helper echoes the
status line into the response body in every variant; the non-exiting variant
only suppresses the `exit`, so `code_404('module', false)` prepended the literal
text `404 Not Found (module)` to the rendered `error404.twig`. The status line
never reached the user anyway (browsers show their own `Not Found` wording), so
nothing useful is lost by setting the code directly. Consequence: any future
error page that renders a template must do the same, and the `code_xxx()` helper
is only safe where the echoed body is actually wanted (the CalDAV endpoints in
`app/front/module/Caldav.php` deliberately follow the echo with their own
protocol body).

**Decision:** `not_found()` outputs the page with `Template::display()`, not
`Template::render()`. `render()` returns the rendered HTML as a string,
`display()` echoes it — that is the pair `Skeleton\Application\Web\Module::
handle_request()` uses for every normal page. Calling `render()` and dropping the
return value produced a `404` with `content-length: 0`: the status was right and
the template never reached the client. Consequence: `not_found()` is the only
place in the app that is not a `Module`, so it has no `$this->template` to hand
to `handle_request()` and must call `display()` itself. Any future event that
sends a page instead of protocol output follows the same rule.

## Public page: the descent ramp

Four decisions landed here on 2026-10-01, in order, and only the last is live.
They are recorded in sequence because the reasoning that killed the earlier ones
is the thing most likely to get them re-introduced.

1. **"Add the gradient from the error page to the blocks page … one single
   gradient for the whole page."** → `body.public-page` painted the error pages'
   pale limestone ramp, and the club band, contact band and footer were made
   light to match.
2. **"I want a gradient from very dark green almost black down to practically
   white, before the footer."** → darkened. First attempt anchored the descent to
   the club band only, which the user rejected: *the gradient should be over the
   whole page*.
3. **"This is too much — I want the page very light green after 40% of the page,
   and white by 80%."** → percentages: dark hold to 25%, pale at 40%, white at 80%.
4. **"The cutoff can be a lot more sudden, maybe 20% or 30% — I just want the
   initial full screen mostly dark."** → `100vh` hold, easing out over 35vh.
5. **"I don't like [that] — a hard cutoff on vh to the light colour is better?"**
   → the live shape: two stops at the same `100vh`, so the transition is a single
   pixel line.

**Why the eased version could not be rescued by tightening it.** Any gradient that
travels from `#0a1206` (L 0.005) to `#e7f1dc` (L 0.851) must pass through the mid
tones, and the mid point of those two is a desaturated grey-green — measured at
`#50594a` → `#737c6c` → `#969f8e` down the old band. That grey is the mud in the
screenshot: not rock, not daylight, and unmistakably a rendering artefact. Making
the run shorter does not help, it only concentrates the same grey into a narrower
stripe. Duplicating the stop at `100vh` removes it *by construction* — nothing
between the two stops is ever painted, so the grey cannot appear.

**Live: `body.public-page`**

```css
background-color: var(--redan-page);
background-image: linear-gradient(180deg,
	var(--redan-cave-deep) 0, var(--redan-cave-deep) 100vh,
	var(--redan-moss-pale) 100vh, var(--redan-page) 300vh);
```

Verified from rendered pixels at 1280×900: gutter is `#0a1206` at y=986 and
`#e8f1dd` at y=987 — a one-pixel edge, L 0.005 → 0.853. Sweeping every gutter
pixel from the top of the page to y=4300 finds **zero** in the muddy
L 0.05–0.45 range: all 170 mid-tone samples fall at y=64..402, inside the hero
photo. After the cut, the climb from moss-pale to page white is safe because both
are already light — interpolating between two pale colours only ever yields a pale
colour, however long the run.

**The cut cannot be trusted with text, so it isn't given any.** `100vh` moves with
the viewport; the club band's white text ends at a fixed pixel set by
admin-editable copy. Measured, that collides: at **390×667 the last paragraph line
hung 43px below the cut** — white text on `#e7f1dc`, invisible — and 1024×640
cleared by only 3px. No `vh` value fixes it. So `.about-section` now paints its
own `--redan-cave-deep` plate rather than borrowing the ramp:

```css
.about-section {
	background-color: var(--redan-cave-deep);
}
```

This is visually free. The ramp's first stop is that same colour and held dark
past the band at every size measured, so the band was already sitting on
cave-deep — it now guarantees it instead of hoping to. The hero's overlay also
sinks to `--redan-cave-deep` at its bottom stop, so pixel-sampling across y=565
gives `#0a1206` on both sides: photo, band and ramp meet with no seam. A
consequence worth knowing: the visible edge lands on the band's bottom (987)
rather than on the moving `100vh` line, because the band paints over it — which
is both sturdier and reads better, since the dark now ends exactly where the club
intro ends.

**The rule this settles**: text never rides on the raw ramp. The club band owns a
dark plate, `.featured-text` and the contact cards own white ones, and the
`bg-black` activity halves always did. What is left on the bare gradient is
padding, section margins and the gutters between cards — nowhere a glyph can go
wrong.

**The arithmetic that rules out the obvious alternative.** No single ink can read
at both ends of a full-height dark→white ramp: clearing the dark end needs
relative luminance ≥ 0.198, clearing the light end needs ≤ 0.169. The ranges do
not overlap. So text must not sit on the ramp — which is why `.featured-text`
now paints an opaque card:

```css
.projects-section .featured-text {
	background-color: var(--bs-white);
	border-radius: var(--bs-border-radius);
	box-shadow: var(--redan-shadow);
	padding: 2rem;
	flex: 1;
	display: flex;
	flex-direction: column;
	justify-content: center;
}
```

Applies to `activities_light` and `monday_openings` — the only cards whose text
had no surface of its own. Two details that are easy to get wrong and are covered
under "The card's specificity trap" below: the selector **must** carry the
`.projects-section` prefix, and the radius is `--bs-border-radius` (0.375rem, the
photo's) rather than `--redan-radius` (1rem).

The card is squared with its photo on two axes, and both are needed:

- `.projects-section .row:has(> div > .featured-text) { align-items: flex-start !important }` —
  the row's own `align-items-center` utility centres each column against the
  tallest, so a text-heavy card stuck out **83px above and 82px below** its photo.
  `!important` is required because the utility is itself `!important`.
- `.projects-section .row > div:has(> .featured-text) { align-self: stretch; display: flex }` —
  the column rises to the row's height, and `display: flex` is what makes the card
  actually fill it: flex items stretch on the cross axis by default, which in a
  row-direction container is vertical. `align-self: stretch` **alone does nothing
  visible** — the first version of this rule shipped exactly that mistake, and the
  card stayed 433px inside a 465px column.

Measured at 1280×900: both cards now have a **0px top delta**; the short-text card
(`#salle`) also has a 0px bottom delta, so it is an exact rectangle beside its
photo, and the long-text card (`#rallye`) runs 154px below the photo instead of
overflowing both ends. Text sits top-aligned inside the stretched card (insets
32px above, 64px below), which keeps copy level with the top of the photo rather
than floating in the middle of it.

**Widening the text column was measured and cannot deliver a straight line.** The
instinct is to give the text more width so it wraps less and fits the photo, but a
raster photo's height *is* its width divided by its aspect — moving columns from
photo to text shrinks the photo at the same time as the card grows. Sweeping the
live page (card height needed vs photo height):

| split (photo / text) | xl, 1280px | lg, 1100px |
|---|---|---|
| 8 / 4 (current) | 574 vs 464 | 726 vs 384 |
| 7 / 5 | 472 vs 406 | 573 vs 336 |
| 6 / 6 | 424 vs 348 | 471 vs 288 |

The card is too tall at every ratio, and at `lg` it is roughly 3× worse. The
overhang is a property of the Rally copy (~250 words), not of the grid. **The only
fixes that make both edges straight are shortening that body text, or stretching
the photo to the card's height with `object-fit: cover`** — which crops the dome
about 24%, and was declined. So the long card hangs below, which is what a text
column is supposed to do.

**Ink on the light end.** Body text is the vendored `--bs-body-color` (#212529,
14.46:1 on the page white) and needs nothing. Accent-coloured text, buttons and
rules were retargeted onto the lamp scale — see "The accent is the warm yellow of
a lamp", which supersedes the earlier green figures in this section.

**The hero joins the ramp without a seam.** All four authored `.masthead` overlay
rules now sink to `var(--redan-cave-deep)` instead of `#000` at their bottom
stop, so the photo's base and the ramp's first stop are the same colour.

### The accent is the warm yellow of a lamp

**Decision — 2026-10-01 ("I want the warm yellow of a lamp as accent color").**
The palette already shipped a lamp scale (`--redan-lamp-600/500/400/200`) the
theme had never used. The accent was retargeted onto it, split across two ends
because one yellow cannot do both jobs:

| token | value | white card | cave-deep | role |
|---|---|---|---|---|
| `--redan-accent` | #b45309 (lamp-600) | **5.02:1** | 3.79:1 | links, buttons, rules, icons, focus ring, `.error-code` |
| `--redan-accent-light` | #f0b429 (lamp-400) | 1.86:1 | **10.22:1** | glow on dark only: club `h2` fill, club-band links |
| `--redan-lamp-700` | #7c2d12 | 9.37:1 | 2.03:1 | hover/active, so the state moves against the rest colour |

The deep end carries text and is slightly *better* than what it replaced (the
green was 4.99:1 on white, the amber is 5.02:1). The bright end is 1.86:1 on
white, which is why one "lamp yellow" token was never an option. `--bs-primary`,
`--bs-primary-rgb`, `--bs-link-color` and `--bs-link-hover-color` are retargeted
in the same authored `:root`, because `.btn-primary` and every bare `<a>` read
those rather than `--redan-accent`.

**Four vendored rules had the green baked in** and would have lagged the retheme.
Re-declared in the authored part — line 13 is a hand-minified sheet shared with
the members and admin shells and is not edited by hand:

- `a:focus-visible { outline: 2px solid var(--redan-accent-light) }`. The swap
  moved that token from #9ab03e (2.43:1 on white) to #f0b429 (1.86:1), making an
  already-weak ring weaker. A focus indicator wants 3:1; on the deep amber it is
  5.02:1 on a white card and 3.60:1 on a black card half, so one value clears
  every surface a link can sit on. This is the one spot where the retheme fixed a
  pre-existing failure instead of creating one.
- The navbar-shrink active pill and the dropdown hover, literally
  `rgba(77,124,15,.12)`. Restated **inside the same `@media (min-width: 992px)`**
  the originals live in: hoisting them out would give the mobile navbar a pill it
  has never had — a layout change disguised as a colour one. The `.12` alpha is
  kept rather than borrowing the 7% `--redan-tint` used for static plates.
- `#mainNav.navbar-shrink .nav-link:active { color: #467370 }`, a teal leftover
  from the pre-theming sheet.
- `.contact-section .social a`, previously set to the green ink tokens: the 2rem
  card icons immediately above it read `--redan-accent` and had already gone
  amber, so leaving the socials green put two accent colours in one section.

`--redan-tint` moved with the accent to `rgba(180,83,9,.07)`; `.error-art` and
the social plates read it. Two `--redan-ink-*` tokens became unused and were
deleted rather than left as dead declarations.

### The card's specificity trap

`.featured-text` first got `padding: 2rem` from a bare single-class rule. That
**loses** to the vendored `@media (min-width: 992px) { .projects-section
.featured-text { padding: 0 0 0 2rem } }` — 0-2-0 against 0-1-0 — so the card
quietly kept zero top and bottom padding and text ran flush against the rounded
corner. Caught by measuring the inset of the card's first line from its own top
edge: it read `0`, then `32` once the selector became `.projects-section
.featured-text`. That inset is the check if the padding ever breaks again.

The card matches its photo deliberately: radius `6px` against the photo's `6px`
(`--bs-border-radius`, not the `--redan-radius` 1rem the site uses on `.card`
elements — two radii side by side read as a mistake, not a system), and the pair is
squared by the two rules documented above (`align-items: flex-start` on the row,
`align-self: stretch` + `display: flex` on the column). Measured at 1280×900: both
light cards have a **0px top delta**; the short-text card `#salle` is an exact
465/465 rectangle, and the long-text card `#rallye` runs 154px below its photo
instead of overflowing both ends as it did before. *(Amended 2026-10-02: the four
corners on the photo↔card seam are now squared at lg and up — see "junction
corners" in the decisions list; the match of radii applies to the outer corners
only.)*

### What the ramp did NOT touch

- **The footer is the user's own markup decision and stays untouched**:
  `bg-black text-white` in `_block/footer.twig`. Both are `!important`
  utilities, so the plate and the white ink come from the markup and no CSS rule
  overrides them. An earlier attempt to recolour the footer to cave-deep was a
  misreading of a request that was never made and was reverted; do not re-apply.
- **The error pages keep their pale `--redan-limestone` ramp.** They are one
  screen of light with no photo. They were briefly given the dark descent —
  another change nobody asked for — and reverted.
- **The activity cards keep their own `bg-black` / `bg-white` halves** and white
  `.project-text h3`: card content on a card background, which the page ramp
  never reaches.

**Latent, not fixed, not live:** `.projects-section .project-text h3` is
unconditionally `color: #fff`, and `activities_pair_light.twig` puts
`.project-text` inside a `bg-white` half — white heading on a white card. No
pair-light card exists on the live page (verified: zero `bg-white` in the
rendered HTML), so nothing is broken today, but it will show the moment one is
created.

**Unexercised risk:** `.announcement-section` has no background of its own, so
its admin HTML would sit directly on the ramp. Placed in the dark zone, its
near-black inherited text becomes unreadable. It is not currently on the
homepage. Give it an opaque surface like `.featured-text` before using it.

### Process traps worth keeping

**Cache-buster ordering.** `styles.css` was published at `?v=20261001n`, then
edited again in the same session; Chromium kept serving the cached `n` bytes, so
a correct fix looked broken — CDP's own matched-rules query reported the new
selector absent. **Bump the `?v=` after the final content change, not before.**

**A test can be silently wrong.** The first CDP contrast audit parsed the ramp
stops from `getComputedStyle(body).backgroundImage` with a regex written inside a
JS template literal. `\(` collapses to `(` there, the pattern never matched, and
the audit fell back to a hardcoded two-stop ramp — reporting a real failure
(1.37:1) that did not exist and, worse, passing everything else against a
background the page never paints. Every "0 failures" claim before that was
worthless. Fixed by using backslash-free patterns and making the audit **abort
rather than guess** when it cannot parse the ramp, and by having it print the
stops it parsed. Where possible the checks were then re-grounded in pixels:
screenshot the page, sample the left gutter, and confirm 40% renders #e7f1dc and
80% renders #f7f8f4.

### Verification

Current state, with the working audit (backslash-free parsing, aborts on failure,
reports its parsed stops) and pixel sampling:

- **0 WCAG AA failures** of 23 text elements on the homepage at 1280×900,
  1280×700, 1024×640, 1920×1080, 768×1024, 390×844, 390×667 and 320×568; **0 of
  7** on the error page at 1280×900 and 390×844. The short-viewport cases
  (1024×640, 390×667) are the ones the `100vh` cut used to fail before the club
  band owned its own plate.
- Gutter pixels confirm the hard cut: `#0a1206` (L 0.005) at y=986 → `#e8f1dd`
  (L 0.853) at y=987, and **zero** samples anywhere between the top of the page
  and y=4300 land in the muddy L 0.05–0.45 range. The hero→band join at y=565 is
  `#0a1206` on both sides, so photo, band and ramp meet without a seam.
- Club band white text on its own cave-deep plate: 19.06:1, independent of
  viewport height and of how long the admin's copy is.
- Error page: masthead still paints limestone, ASCII grid 0 glyphs misaligned,
  console art still fires, footer still pinned to the viewport bottom, no nav.
- Status codes unchanged: `/`, `/en`, `/fr`, `/nl`, `/login`, `/members`,
  `/sitemap.xml`, `/robots.txt` → 200, `/admin` → 302, missing paths → 404.




## Error pages: no photo, light background

**Decision:** the 404/403/500 pages show no cave photo *and* sit on a light
background — a pale limestone gradient (`#f7f8f4` → `#e6ecdc`), with dark ink.
*(Amended 2026-10-01: "the one light page on an otherwise dark site" was true
again by the end of the day — the homepage gained a dark→light descent ramp, but
the error pages deliberately **keep** their pale limestone and were never given
the descent. What is distinctive here now is the "no photo" half. See "Public
page: the descent ramp".)* The `.masthead` class
paints `var(--masthead-image-{small,medium,large}, <static caving banner>)` in
three breakpoint steps, so keeping the class alone would have put the club's own
hero shot behind a "your page is missing" message — a real advertising image
attached to a dead end. The templates therefore keep `masthead` for its
geometry (padding, `min-height`, vertical centring, the `id="main-content"`
skip-link target) and add `error-masthead`, which overrides `background-image`
*and* `background-color` (the latter matters: `.masthead` declares `background`
as a shorthand carrying `#000`, which a longhand override does not reset). Two
classes beat the single `.masthead` selector, so the override wins even though
those rules sit later in `styles.css`.

Ink follows the background: `.error-message` uses `--redan-cave-900` (15.6:1),
`.error-code` uses `--redan-accent` (4.7:1, and it is 8rem so it only needs
3:1), and the art is `--redan-cave-800` on a 6% accent tint (~10:1). The
templates lost their `text-white` on the message so the stylesheet owns the
colour instead of Bootstrap's `!important` utility fighting it.

**Decision:** the light page forces the navbar into its solid state. The navbar's
default is a transparent bar with *white* text, meant to sit on the dark
masthead photo, so on a pale page it would be white on near-white.
`scripts.js` only toggles `.navbar-shrink` on `scrollY`, so at the top of the
page it never applies. The fix is `body.error-page #mainNav` in CSS, pinned to
the look the navbar normally gets on scroll (translucent white plate, black
text). Adding `.navbar-shrink` to the markup was rejected: `scripts.js` strips
that class again on `DOMContentLoaded`, so it would have needed a guard in the
script as well. The link padding is pinned too, because `.navbar-shrink` changes
it and a mid-scroll reflow is exactly the jump to avoid.

`not_found()` therefore does *not* call `assign_masthead_image()`, and assigns a
single `error_page` flag instead. It drives both the hero suppression and the
`<body class="error-page">` in `layout.public.twig`. The flag also stops
`_default/hero_image.twig` from emitting its `<link rel="preload">` block: both
of its branches would otherwise fetch a banner the page never paints, so a 404
would still pay for three hero images. The og:image meta tag keeps its static
fallback, which is harmless — it is a link for a crawler to follow, not an image
the browser loads. The footer needs nothing: it carries `bg-black` on the element
itself, so it stays dark under a light page.

**Decision:** the fun element is ASCII art, in the shared partial
`_default/error_art.twig` — a cave passage with stalactites, a drip, a stick
figure standing in the water and a stalagmite, drawn by the user and pasted in
verbatim. One file for all three templates so the joke is identical everywhere,
and the status code is *not* baked into the art (it is shared with the 403 and
the 500), each template rendering its own as the bouncing `.error-code`. Cave
puns were the alternative; ASCII was chosen over an inline SVG cave illustration
because it needs no drawing skill and no extra request. The art is
`aria-hidden` — it is decoration, and the status plus the message carry the
meaning for a screen reader. Spaces only, never tabs: a tab renders at the
font's tab width rather than 8 and shears the walls apart.

The pasted drawing carries its own three-line commentary under a blank line
("NOTE: This is a cave. Yes, really."). It stays inside the `<pre>` rather than
becoming a wrapping paragraph, because the user composed it as one block and the
monospace is part of the joke — but see the width note below, because it is what
sets the font size. The block is **60 columns** at its widest (that last NOTE
line), not the 36 the stylesheet comment used to claim; the trailing whitespace
in the paste was stripped, which changes no glyph and only makes the next edit's
diff honest.

**Decision:** the same drawing is printed to the devtools console.
`scripts.js` — the one script the public layout loads — reads it back out of the
`.error-art` element with `textContent` rather than keeping a second copy of the
ASCII in the JS. One source of truth: change the drawing in the partial and the
console follows. That also means the console art cannot drift out of sync with
the page, which a duplicated string would. The guard is implicit — `.error-art`
only exists on the error pages, so nothing is logged elsewhere.

**Decision:** the footer sticks to the bottom of the viewport. `body.error-page`
becomes a flex column with `min-height: 100vh` so the masthead absorbs the
leftover height.

*(Amended 2026-10-01: this decision also gave `body.public-page` a
`background-color: #000` so leftover height read as black rather than white. That
black is gone: `body.public-page` now paints the descent ramp and its
`background-color` is `--redan-page`, a fallback for a browser that cannot paint
the image. The error pages are the exception — they keep the pale
`--redan-limestone`, so they never show a dark top. See "Public page: the descent
ramp".)*

The flex column is scoped to the error pages **on purpose**. `index.twig`'s
content block emits one `<section>` per card run as *siblings*, so making `body`
a flex container would turn them into flex items: the margin collapsing the
section spacing relies on would stop happening, and they would pick up the
default `flex-shrink`, which risks compressing homepage cards that were tuned by
eye. The homepage is far longer than any viewport so it never needed the sticky
footer. If a short public page ever does, wrap the content block in a single
element first, then widen the selector.

**Decision:** `.error-content { min-width: 0 }` on the error pages' content
wrapper, and a font-size ladder on `.error-art` rather than one fixed size.
Measured in headless Chromium: a flex item's automatic minimum size is its
min-content width, and `white-space: pre` makes that the width of the widest
line, so the item refused to shrink, the art's own `max-width: 100%` resolved
against the oversized item instead of against the space really available, and
**33px of the cave — the start of the ceiling and the "this guy" label — hung off
the left edge of a 390px screen with nothing clipping it.** The reset lets
`overflow-x: auto` on the art scroll the drawing instead of losing it.

The ladder is 0.55rem / 0.7rem / 0.8rem at 0 / 480px / 576px, sized so 60
columns plus padding fit the container. The measured character width of
`--bs-font-monospace` is ~0.636em, not the 0.6em assumed, so a 390px phone
still scrolls the art by ~12px at 0.55rem. That is deliberate: the alternative is
0.53rem to make it fit exactly, and legibility of the joke is worth more than
12px of scroll. If the NOTE ever moves out of the `<pre>` into a wrapping
paragraph, the widest line becomes the 51-column ceiling and every step can grow
by about a fifth.

The three error templates also lost a redundant nested `div`: the content sat in
three stacked flex wrappers where two do the same job, and flattening it means
one `min-width: 0` target instead of two.

**Decision:** `scripts.js` got a `?v=` cache-buster it never had
(`?v=20261001a`, in `layout.public.twig`). Every other public asset carries one
(`styles.css`, `lightbox.js`) — without it a browser holding a cached copy never
picks up the console art.

**Decision:** `.error-art { text-align: left }`. This is load-bearing, not
cosmetic, and it was a real bug rather than a missing nicety. The drawing is one
grid — every line's leading spaces place its glyphs at fixed columns — but
`.text-center` on the wrapper sets `text-align: center`, which **inherits into
the `<pre>` and centres each line independently**. Measured over CDP: the
ceiling landed 4.6 columns right of the floor, and the stick figure's head
(`O/`, 46 columns) sat 18 columns left of its own torso (`/|`, 9 columns). Every
line was individually correct in the HTML and the drawing was still torn apart.
After the fix all 19 non-empty lines share one left edge, and reconstructing the
art from the *measured* glyph positions reproduces the source exactly — 0 lines
with a glyph in the wrong column, at both 390px and 1280px. Centring the box is
the wrapper's job (`d-flex justify-content-center` plus the art's own
`margin: 0 auto`); the text inside must stay left.

**Decision:** the error pages have **no menu**. `layout.public.twig` skips the
whole `<nav>` when `error_page` is set. A 404 that offers the full navigation
reads as a working page, and every link on it is a second way to get it wrong.
The skip link stays — it is the one keyboard affordance that must still reach the
content, and the error masthead still carries `id="main-content"` for it.
Verified: 0 `nav-link` elements on the 404, 5 on the homepage, and no uncaught
JS exception either way (`scripts.js` already guards a missing `#mainNav`).

That removed the reason the `body.error-page #mainNav` block existed (pinning
the bar to its scrolled, solid state because the light page could not carry the
default white-on-photo bar), so **the whole block was deleted** rather than left
as dead CSS. `html { scroll-padding-top }` stays: it is a global anchor offset
for the pages that do have a fixed navbar, and an error page has no anchors.

It also left the masthead's navbar allowance behind. `.masthead` is
`padding: 7rem 0 5rem` below 992px and `height: calc(85vh - 12.5rem)` above it —
all of it reserved for the fixed bar. On a phone that became **112px of empty
gradient above the drawing**, so `.masthead.error-masthead` now overrides the
mobile padding to a symmetric `2rem` (measured: 32px of dead space, and the page
stopped scrolling — 918px → 900px, exactly the viewport). The desktop height is
deliberately untouched: at ≥992px the padding is already 0 and the height only
acts as the flex base that `flex-grow` expands to reach the footer, which still
lands exactly on the bottom edge at 1280×900.

`error403.twig` and `error500.twig` are styled identically but still **have no
code path that renders them** — nothing in the app raises a 403 or a 500 through
the web layer, so they remain templates only. All three are now proved to render
by driving them through Twig directly with stubbed globals, since the live site
can only ever show the 404.

**Known gap:** the 404 page honours the session language and `?language=xx`, but
not the *path* prefix (`/en/does-not-exist` renders in the session language).
`$language[en,fr,nl]` is only turned into `$_GET['language']` by a matching
route, and a missing page matches no route — `I18n::detect_language()` then sees
no prefix and falls back to the session. Fixing it means reading the first path
segment inside `not_found()`; not done, as the page is correct apart from the
wording.

## Anchors

`anchor` is the HTML id the section gets (`#rallye`, `#salle`, ...) and stays
unique. The admin can edit it; defaults match the current ids so existing
external links keep working.

## Language selection

- `$_SESSION['language']`, default `fr`.
- Language switcher posts `/lang/switch` with the requested language.
- Language persistence: session only (no cookie) — keep it simple.
- 2026-09-23: URLs are also dispatchable with a language prefix
  (`/fr/...`, `/nl/...`, `/en/...`); the prefix behaves like the
  `?language=xx` switch. Details in spec/05-i18n.md section 3.

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
  three), click opens the lightbox (vanilla overlay, arrows wrap, Esc
  closes, full-quality copy served from `/picture?size=1600x1200`).

**Decision — 2026-09-23 ("make the image on the homepage a lightbox"):**
the lightbox lives in its own `app/front/media/javascript/lightbox.js`
(=`init_gallery_lightbox`, extracted from `base.js`) and is loaded with
`<script defer>` from `layout.public.twig` (`?v=20260923a`) and from the
admin preview iframe (`admin/block_preview.twig`), which renders the same
`_block/gallery.twig` fragment. The lightbox CSS (`styles.css`,
`.gallery-lightbox*`) and the anchor markup were already shipped, but the
init JS only ran in the admin/members shells — `base.js` is loaded by
`layout.base.twig`, `layout.members.twig` and `layout.login.twig` only, so
on the public site a picture click just navigated to the raw 1600×1200
image. **Discrepancy flagged:** the previous spec wording said the lightbox
lived in `base.js` while implying it worked publicly; it did not. Admin
pages still get no lightbox (they render the pictures as a drag table, not
lightbox anchors), and `base.js` lost the (now redundant) function.
- Card `<img>` tags carry the `width`/`height` attributes of the original
  picture (from the `picture` table) so no layout shift happens, and
  `loading="lazy"` everywhere except the first image-bearing card in the
  page flow (that one stays eager for LCP).
- **2026-09-23 — whitespace under the Monday openings card.** The
  projects-section separates cards with `.row + .row { margin-top: 11rem }`,
  but the `#salle` row is followed by the `activities-pair` wrapper (not a
  plain row), so the only gap below the openings card collapsed to its
  small `mb-4/mb-lg-5` margin. Fix in `styles.css`:
  `.projects-section #salle + .row, .projects-section #salle +
  .activities-pair { margin-top: 11rem; }` — the added margin-top merges
  with the card's own bottom margin (CSS collapse, no doubling), giving the
  salle card the same breathing room as its siblings. Selector stays tied
  to the `salle` anchor, like the existing `#rallye + .row` rule.
  Cache-buster: `styles.css` bumped `?v=20260917b` → `?v=20260923a`.
- **2026-09-28 — mobile corner classes (the `rounded-sm-*` rule).** The
  hand-minified Bootstrap in `styles.css` ships the directional corner
  utilities **gated behind `@media (min-width:992px)`**:
  `rounded-top-start/end` and `rounded-bottom-start/end` do nothing below
  992px. The only corner utilities active below 992px are `rounded-sm-top`
  (both top corners) and `rounded-sm-bottom` (both bottom corners) —
  despite the `sm` name they mean *below lg*. **Rule: every directional
  corner class on a card half must be paired with its `rounded-sm-*`
  companion**, exactly like `../redan/index.html` lines 202-225:
  photo 1 `rounded-top-end rounded-sm-top`, black box 1
  `rounded-top-start rounded-sm-bottom`, photo 2
  `rounded-bottom-start rounded-sm-top`, black box 2
  `rounded-bottom-end rounded-sm-bottom`. Above 992px the directional
  classes weld the two rows into one card (only the four outer corners
  rounded); below 992px the `rounded-sm-*` classes make each half a
  self-contained rounded card. `activities_pair_dark.twig` had lost three
  of the four companions and had `rounded-sm-top` on the wrong element
  (black box 2), so below 992px the card had square outer corners and a
  stray notch mid-card; fixed to match the reference.
- **Corollary — `overflow-hidden` and `shadow` are not in the stylesheet.**
  The `activities-pair` wrapper carries `rounded overflow-hidden shadow`,
  but neither utility existed in the minified `styles.css` at the time of the
  original note. As of **2026-10-01** the missing utility classes are added
  where needed (the text utilities and `object-fit-cover`, `bg-white`; the
  `d-block` case is handled on `.gallery-lightbox`). `overflow-hidden` and
  `shadow` are still not defined globally: Bootstrap 5 provides them, but this
  hand-minified sheet does not ship them. In practice the per-half corner
  classes do the visible cornering and the wrapper's background is absent, so
  clipping by `overflow-hidden` is not strictly needed for the current design;
  all visible cornering comes from the per-half classes above. Do not rely on
  the wrapper for corners.
- **2026-10-02 — junction corners on the light cards (photo + `.featured-text`).**
  The user asked for "these corners gone on home — when 2 cards touch each
  other": the `#salle`/`#rallye` photo and its white text card share both ends
  (the lg stretch rules above), so the four radii meeting on their seam let the
  page ramp show through as notches. Decision: pure CSS in the existing
  `@media (min-width: 992px)` geometry block — no template change — squaring
  only the junction corners (photo right + card left, or the mirror when
  `image_side == 'right'`) so the pair reads as one welded card and the two
  outer ends keep their 6px round. The seam side is read from the same
  `order-lg-last` / `order-lg-first` classes the template already emits for
  `image_side`, so a side flip in the admin needs no CSS change. The vendored
  `.rounded` on the photo is `!important`; the match uses `!important` longhands
  at the `:has()`-selector specificity the geometry block already uses. Below
  lg nothing is touched: the columns stack with the photo's `mb-3` gap, they
  never touch, and all corners round.
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
  non-webp sources), cached in `tmp/picture/<size>/<id>-<md5prefix8>`.
- Bad caches from the quality bug are wiped (`tmp/picture/*`) after the
  change so existing re-encodes are rebuilt.
- 2026-09-23: the cache key was `<id>` only. After the seed migration
  recreated the file rows (2026-09-18), an id got reused for a different
  image and `tmp/picture/160x160/8` still held the previous file's
  derivative, so the admin gallery table showed the wrong thumbnail while
  the homepage pictures looked fine (they use freshly generated sizes).
  Decision: the cached copy now includes an 8-char md5 prefix of the
  source, e.g. `160x160/8-a7c7205d` — a reused id can no longer serve a
  stale derivative, and a cache wipe is only needed for format changes.
  (The old-style files were wiped; the cache is disposable.)

## Theme (2026-10-01)

`styles.css` follows the shared caving palette documented in `00-overview.md`:
`--bs-primary`/`--redan-accent` `#6F8227` → `#4d7c0f`, `--bs-yellow` warmed to
`#f0b429`, `--bs-warning` to `#e0a32e`.

Two notes for whoever re-tunes this next:

- **The green was duplicated in three forms.** Thirteen `#6f8227` literals plus
  two `rgba(111,130,39,.12)` tints (the same olive as `rgb()`, which a hex
  grep does not catch) plus the `--redan-accent` variable itself. All now read
  `var(--redan-accent)`. Change the variable, not the rules.
- **The `#mainNav` block is inside `@media (min-width: 992px)`.** Any check of
  the shrunk navbar has to run at desktop width or the rules are inert and the
  active-link colour reads as unset.

`--bs-link-hover-color` (`#50817e`) and `--bs-primary-rgb`
(`100, 161, 157`) were both teal leftovers, inconsistent with the green around
them — links went teal on hover. Corrected. `theme-color` moved from
`#000000` to cave green.

## Block items (2026-10-01)

The public index is assembled from `_block/*.twig` templates by `index.twig`:
`section_types` (`activities_light`, `activities_dark`, both `activities_pair_*`,
`gallery`, `monday_openings`) get wrapped in a shared
`<section class="projects-section bg-light">`; the rest render standalone.
Every block therefore reaches the theme either through that wrapper or through
its own section class (`masthead`, `about-section` for `club`, `contact-section`,
`announcement-section`, `footer`).

Checking every block template's classes against the vendored sheet turned up
classes the markup asks for that the minified Bootstrap does not contain — the
utility set was cut to what the old static site used, and the block templates
were written against the full framework. They were inert, not errors, so
nothing looked broken, just subtly wrong. Fixed in `styles.css` rather than in
the templates, so the markup keeps stating intent as plain Bootstrap classes:

| class | blocks | what it did before |
|---|---|---|
| `object-fit-cover` | both pair cards | halves are `w-100 h-100`, so the photo was **stretched** to row height, not cropped |
| `bg-white` | `activities_pair_light` | text halves inherited `bg-light`; the welded card had no white against the photos |
| `d-block` | `gallery` | anchor stayed `inline`, so `overflow: hidden` + `border-radius` on `.gallery-lightbox` clipped nothing and the hover zoom had no box to grow inside |
| `btn` / `btn-primary` / `btn-lg` | the three error pages | "Back to the homepage" rendered as a bare underlined green link — no background, no padding, inline |

`d-block` is handled on `.gallery-lightbox` itself rather than as a bare
`.d-block` utility: a global `display: block !important` would hit every
element that ever picks the class, which is wider than the one call site.

**`text-lg-left` / `text-lg-right` are deliberately left inert** (reverted
2026-10-01, at the user's request). The activity and openings templates carry
them, and they are absent from the minified sheet, so the text stays
`text-center` at every breakpoint — which is the design as it stands, matching
`../redan`. Adding the utilities at `@media (min-width: 992px)` was tried and
removed: it left/right-aligned every activity card against its photo from lg
up, which is a layout change beyond the scope of a colour theme. Do not
re-add without an explicit request.

Two contrast fixes on the blocks, both measured on the rendered page:

- **Footer links** (`_block/footer.twig`, `link-secondary text-white-50`) —
  neither utility exists, so they fell through to the global `a` rule and
  painted cave-500 on the black footer: **4.21:1, a fail**. Repointed at
  `moss-400`, **8.65:1**. The class names stay; the colour is set by
  `.footer-links a`. *(Superseded 2026-10-01, twice over: the footer was briefly
  made light, then the user put `bg-black text-white` back in the markup as a
  deliberate choice. Both classes are `!important` utilities, so the footer's
  plate and ink now come from the markup and **no colour rule for footer links
  survives in `styles.css`** — any such rule would lose to `text-white` and sit
  in the file doing nothing. See "Public page: the descent ramp".)*
- **Contact card muted text** — `.text-black-50` is `rgba(0,0,0,.5)` on a white
  card, which resolves to `#808080`: **3.12:1** for the small address and email
  text it is used for. Overridden to 70% body colour (`#646669`,
  **5.76:1**) on `.contact-section` only, `!important` because `.text-black-50`
  is itself `!important`.

The **announcement band** was the one block with no themed rule at all — a bare
light strip between the black masthead and the black club section, reading as an
unstyled gap. It now gets a green hairline top and bottom. Deliberately no
background of its own: the body is admin-editable HTML that may carry its own
styling.

Cache-buster: `styles.css` bumped `?v=20261001e` → `?v=20261001f` in
`layout.public.twig` and `admin/block_preview.twig`.

Cache-buster: `styles.css` bumped `?v=20261001f` → `?v=20261001g` in
`layout.public.twig` and `admin/block_preview.twig` for the error pages
(`.masthead.error-masthead`, `.error-art`). Both templates carry the value, so
the admin card preview stays in step with the public layout.

Cache-buster: `styles.css` bumped `?v=20261001g` → `?v=20261001h` for the light
error background and the `body.error-page #mainNav` overrides. `error-page` is
only ever set by `not_found()`, so no other page picks the rules up — verified
the homepage carries no `error-page` class and still emits its three hero
preloads.

Cache-buster: `styles.css` bumped `?v=20261001h` → `?v=20261001i` for the sticky
footer (`body.public-page` / `body.error-page`).

Cache-buster: `styles.css` bumped `?v=20261001i` → `?v=20261001j` for
`.error-content { min-width: 0 }` and the `.error-art` font ladder — the fix for
33px of the drawing hanging off the left edge of a phone screen.

Cache-buster: `styles.css` bumped `?v=20261001j` → `?v=20261001k` for
`.error-art { text-align: left }` — the fix for the drawing being torn apart by
inherited per-line centring.

Cache-buster: `styles.css` bumped `?v=20261001k` → `?v=20261001l` for dropping
the navbar allowance from `.masthead.error-masthead` on mobile.

Cache-buster: `scripts.js` got `?v=20261001a`, its first; see the error pages
section for why.

Cache-buster: `styles.css` `?v=20261001l` → `?v=20261001m` (masthead hero
variants), then `n` → `?v=20261001aa` across the public-page gradient work. The
letters are not each worth an entry — what matters is that **`n` through `z` were
all burned by one mistake**: the buster was moved *before* the last content edit
repeatedly, which is the ordering trap recorded under "Process traps worth
keeping". Live value at the end of this pass: `?v=20261001aa`, covering the
limestone ramp, the dark descent, its `vh` retiming, the hard cut at `100vh`, the
club band's own plate, the `.featured-text` card and its specificity fix, and the
lamp accent retarget.

## Block image resolution (2026-10-01)

The block card images now request `size=1600x1200` instead of `1200x900`
(8 call sites across `activities_dark` ×2, `activities_light`, both
`activities_pair_*` ×2 each, and `monday_openings`). No new resize
configuration was needed: `1600x1200` was already registered in
`lib/base/Bootstrap.php` and is also what the gallery lightbox asks for, so
the block cards share the existing `tmp/picture/1600x1200/` cache instead of
growing a new one.

The cap was chosen by measuring the rendered width of each slot against the
real `styles.css` at real viewport widths, not by guessing:

| block | column | ≥1400px | ≥1200px | 992–1199px | 2x DPR needs |
|---|---|---|---|---|---|
| `activities_light`, `monday_openings` | `col-xl-8 col-lg-7` | 816 | 696 | 504 | **1632** |
| `activities_dark`, both `activities_pair_*` | `col-lg-6` | 612 | 522 | 432 | **1224** |
| `gallery` thumbnail | `col-lg-4` | 392 | 332 | 272 | 784 |

`1200x900` fell short of 2x for both card shapes. The gallery thumbnail was
left at `800x600`, which already clears its 784px requirement — raising it
would have cost bytes for nothing. The pair cards need more than the table
suggests: they are `w-100 h-100 object-fit-cover`, so cropping means only part
of the source is shown and the effective source width needed is higher.

**This is a retina-only gain, and it is not free.** Measured on
`8-img_0261.jpg` (5472x3072) re-encoded at the module's own webp quality 85:
1200x674 is 211 KB, 1600x898 is 371 KB, so **+76% bytes for +33% linear
resolution**. At 1x DPR the old 1200px version already exceeded the widest
816px slot, so there the new size is pure waste.

**The real ceiling is the source file, not the request.** When the requested
size is at or above the original, `Picture::needs_resize()` returns false and
`Manipulation::calculate_size()` never upscales — the original is streamed
as-is. All five seeded block images are already at or below 1200px
(`rallye-main` 1200x800, `salle`/`rallye-1`/`rallye-2`/`rallye-3` 800x534,
`rally` 650x488, `tower` 800x613), so **the bump changes nothing visible for
them**. Only admin-uploaded originals (the 2500x1667 to 5760x3840 photos) gain
anything. Empirical confirmation: there is no `tmp/picture/1200x900/`
directory on disk even though eight templates request it — every block request
has been streaming the original. Sharper seeded images require replacing the
source files with the club's own originals; no code change can add pixels.

Not done, deliberately: `srcset` with 800/1200/1600 candidates and a
breakpoint `sizes` ladder per column shape would buy the same retina
sharpness without the 1x byte penalty, but it puts a long measured `sizes`
attribute in five templates to serve images that gain nothing either way while
the sources stay 800px. Reconsider once real photos are uploaded.

## Masthead hero resolution (2026-10-01)

The hero was the one image on the site that was upscaled by the browser on most
viewports, because its breakpoint ladder did not line up with the widths of the
variants it served. Fixed as described under "Masthead hero image" above: the
boundaries are now 640 / 1280 / 1920 (the variant widths) instead of 768 / 1920
(Bootstrap's), and a genuine `1920x960` step was added in between.

- `app/front/media/css/styles.css` — the four `.masthead` `background-image`
  steps and the comment above them.
- `lib/base/Bootstrap.php` — registered the `1920x960` resize configuration.
- `lib/component/Masthead/Image.php` — `SIZES` gained `'wide' => '1920x960'`;
  `large` is unchanged so `og:image` still points at `2560x1280`.
- `app/front/template/_default/hero_image.twig` — fourth preload
  (`caving-banner-wide.webp` / `masthead_image.wide`), the
  `--masthead-image-wide` custom property, and all four media queries turned
  into closed ranges.
- `app/front/media/image/caving-banner-wide.webp` — new static file, 1920x960
  webp q80, 167 KB, a real downscale of the 2560px master. It resolves with no
  manifest: `Http\Media::get_path()` builds the path from the application's
  `media_path` + `image/` and only checks the extension against a list that
  already includes `webp`.
- Cache-buster: `styles.css` bumped `?v=20261001l` → `?v=20261001m` in
  `layout.public.twig` and `admin/block_preview.twig`. The new image file has
  no version query — the other three banners have none either, and they are
  served with an ETag.

Verified with the real `styles.css` at 500/640/768/1024/1280/1366/1440/1512/
1536/1600/1700/1919/1920/2560px: every viewport now gets a variant at least as
wide as itself, against 1.07x–1.50x of upscaling before, and exactly one of the
four preload queries matches at every width.
