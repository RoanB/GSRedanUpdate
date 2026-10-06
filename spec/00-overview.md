# 00 — Overview

## Product

`gsredanupdate` is the rebuild of https://www.gsredan.be. The existing site is a
static, trilingual (FR default, NL, EN) Bootstrap "Grayscale" one-pager with a
few content sections ("blocks"). The rebuild turns it into a skeleton
application (tigron/skeleton + Twig + tabler) with:

1. The current `gsredan.be` content transplanted into the skeleton public site,
   prefilled in the seed migrations with the exact HTML content from `../redan`,
   presented as reusable cards (fixed singular cards + creatable card types).
2. A basic login + admin (pattern copied from `../vvsjongeren`), users seeded
   via migration.
3. In the admin: edit card content (per language) with an html-highlighted
   code editor and a live preview, hide/show cards, drag & drop ordering
   (the masthead is pinned) — menu order follows the card order.
4. Address/mail and other contact data editable in the admin via settings.
5. The **members area** (formerly "download center") under `/members` behind
   one shared password (initially **`OuEstLaCorde`**), holding categorized
   files managed in the normal admin, plus the club **calendar** (ICS export
   + read-only CalDAV, see [07-members-calendar.md](07-members-calendar.md)).

## Tech stack

- PHP (PSR-12 base, tigron/skeleton-coding-standard), tabs, `===`, snake_case.
- skeleton 4 (already in `composer.json`): skeleton-i18n, skeleton-file,
  skeleton-pager, skeleton-transaction, skeleton-file-picture, tabler.
- Twig templates in `app/front/template/`.
- MySQL via `config/environment.php` DSN (gitignored).
- Vanilla JS for drag & drop + CodeMirror (vendored, no composer dependency)
  in `app/front/media/javascript/base.js`.

## Environment

- Webroot: `webroot/` does not exist; the server rewrites everything to
  `handler.php` in the repo root (see `.htaccess`).
- Hosts: `gsredanupdate.buysse.io` (dev), eventually `www.gsredan.be`.
- PHP version: the server runs **PHP 8.5** (CLI and web), while the vendored
  skeleton packages predate it. `Skeleton\Error\Handler` escalates any error
  level enabled in `error_reporting` to an `ErrorException`, so PHP 8.5
  deprecations in vendor code (e.g. `Rewrite.php` using `null` as an array
  offset) would take down every page. Decision: `lib/base/Bootstrap.php` sets
  `\Skeleton\Error\Config::$error_reporting = E_ALL & ~E_DEPRECATED;` before
  `Handler::enable()`. Deprecations are logged to the normal error log but not
  fatal; real errors still throw. When the framework gains PHP 8.5 support,
  remove this line.

## Roles

| Role | Access |
| --- | --- |
| Visitor | Public one-pager |
| Password holder | Members area (`/members`): files + calendar (see [07](07-members-calendar.md)) |
| Admin | Login at `/login`; `/admin/*` requires `$_SESSION['user']` with `admin = 1` |

## Non-goals (for now)

- Registration / public sign-up, e-mail verification, password reset mail.
- Front-end user accounts (Members only).
- any other language than fr/nl/en.

## Related decisions

- Block content is stored **per language** (fr/nl/en), with FR as fallback.
- The extra announcement is a **single fixed announcement block** (one row of
  type `announcement`), fully orderable and hideable like other blocks.
- Download files are stored via **skeleton-file** (already in composer).
- Short UI strings use **trans tags** compiled to po via skeleton-i18n; long DB
  content is translated inside the DB (`block_translation`), see [05-i18n.md](05-i18n.md).

## Theme: green + warm yellow (2026-10-01)

The site wears one palette everywhere: the deep green of a cave with the warm
amber of a carbide lamp. One stylesheet per area, because the two areas are
built on different libraries and cannot share a variable namespace:

| Area | Library | Stylesheet |
| --- | --- | --- |
| Public site | Bootstrap 5 + Start Bootstrap Grayscale | `media/css/styles.css` |
| Admin, members, login | Tabler 1.4.0 | `media/css/base.css` |

### The palette

Declared once per stylesheet as `--redan-*` custom properties. `base.css` and
`styles.css` are loaded on disjoint page sets, so the list is duplicated rather
than shared through a third file.

| Variable | Hex | Role |
| --- | --- | --- |
| `--redan-cave-900` | `#16210f` | navbar gradient start, `theme-color` |
| `--redan-cave-800` | `#24401a` | navbar gradient mid, `theme-color` |
| `--redan-cave-700` | `#3f6212` | primary green (admin buttons, links, page title) |
| `--redan-cave-600` | `#365314` | primary hover |
| `--redan-cave-500` | `#4d7c0f` | public-site primary (`--bs-primary`, `--redan-accent`) |
| `--redan-moss-400` | `#9ab03e` | heading gradients, default calendar category |
| `--redan-lamp-600` | `#b45309` | `--tblr-warning` |
| `--redan-lamp-500` | `#e0a32e` | active/hover accents, navbar border, calendar "today" |
| `--redan-lamp-400` | `#f0b429` | current nav link, `--bs-yellow` |
| `--redan-lamp-200` | `#fcd34d` | pale amber, reserved |

Two greens, not one: the admin uses the darker `#3f6212` because Tabler buttons
need white label text at a solid contrast ratio (7.08:1), while the public site
uses the lighter `#4d7c0f` because its green is mostly small text on white
(4.99:1). Choosing one value for both would push one of the two areas under
WCAG AA.

### Green drives actions, amber marks place

Deliberate split, applied consistently in both areas:

- **Green** = a primary action. Buttons, links, form focus, checked inputs,
  the page-header accent bar, the card-header tint.
- **Warm yellow** = where you are, or something to notice. The current nav
  link, focus, warnings, the calendar's "today" cell.

`--tblr-warning` is `lamp-600` rather than the brighter `lamp-500`. Tabler
builds an alert out of `color-mix` of its alert colour (10% background, 20%
border), so the amber reads right there either way; the deciding factor is
`.text-warning`, which paints `--tblr-warning-rgb` as text — `lamp-600` gives
5.05:1 on white, `lamp-500` only 2.22:1. Worth knowing: `.alert` sets no
colour of its own, so alert *text* stays at the body colour regardless.

### What changed

**`base.css` — the `--bs-*` block was dead code.** Tabler 1.4 reads no
`--bs-*` variable at all (`grep -c 'var(--bs-' tabler.min.css` → `0`); it
themes entirely through `--tblr-*`. The green/amber `:root` block that sat at
the top of the file therefore did nothing, and the stock Tabler primary
`#066fd1` was still in force underneath. The block is replaced by real
`--tblr-*` overrides.

Two derived colours in the vendor sheet are hardcoded `rgb()` triplets and do
**not** recompute from `--tblr-primary`, so both are set explicitly:
`--tblr-primary-rgb` (focus rings, `.text-primary`, `.link-underline-primary`)
and `--tblr-primary-lt-rgb`. `--tblr-primary-darken` / `-lt` / `-200` are
`color-mix()` expressions in the vendor sheet and follow `--tblr-primary` on
their own.

**The purple is gone.** A `.navbar` gradient (`#7c3aed`→`#5b21b6`) sat in the
second half of `base.css` and, coming later in the sheet with `!important`,
overrode the green/amber navbar above it. Both copies are now one rule, next
to the palette it is built from. The leftover purple tints went with it: the
card-header gradient, the `.btn-primary` glow (which painted a purple halo
around a *blue* button) and the `.page-wrapper` tint.

**Dark navbar active links were unreadable.** Tabler sets
`--tblr-navbar-active-color: var(--tblr-body-color)`, i.e. near black, which on
a dark navbar renders the current page as dark-on-dark. Repointed at
`lamp-400`, plus a `2px` amber underline so the state survives on the
gradient. `layout.members.twig` had no `active` class on its links at all, so
the members navbar could not show a current page; it now carries `members_tab`
the same way the page title already did.

**`styles.css` — public site.** `--bs-primary` and `--redan-accent` move from
`#6F8227` to `#4d7c0f`; `--bs-yellow` warms to `#f0b429`; `--bs-warning` to
`#e0a32e`. Thirteen hardcoded `#6f8227` literals and two leftover
`rgba(111,130,39,.12)` tints (the same olive stored in `rgb()` form, invisible
to a hex grep) became `var(--redan-accent)`. Two stale values that were not
green at all: `--bs-link-hover-color` was `#50817e` (teal — links went teal on
hover) and `--bs-primary-rgb` was `100, 161, 157` (also teal, and inconsistent
with its own `--bs-primary`). Both corrected.

**`theme-color` / `msapplication-TileColor`** were stock Tabler blue
(`#4188c9`, `#2d89ef`) on the admin layout and `#000000` on the public one;
all now cave green.

**Untouched on purpose:** `Calendar_Category::DEFAULT_COLOR` (`#9ab03e`) and
the matching `base.js` calendar-chip fallbacks. That moss green is
`--redan-moss-400` and already sits on the palette.

### Verification

No browser is attached to the agent session, and the admin needs a login, so
both stylesheets were checked headlessly: `tabler.min.css` + `base.css` (and
`styles.css`) were served from a scratch harness built from the real markup,
then Chromium was asked for *computed* styles rather than screenshots — the
computed value is what the cascade actually produced, so it catches dead rules
rather than trusting that a rule looks right. Every pair clears WCAG AA:

```
navbar link (idle)                #ffffff on #24401a  11.52:1  PASS AA
navbar link.active (lamp amber)   #f0b429 on #24401a   6.18:1  PASS AA
navbar brand                      #ffffff on #24401a  11.52:1  PASS AA
primary button label              #ffffff on #3f6212   7.08:1  PASS AA
page title                        #3f6212 on #ffffff   7.08:1  PASS AA
dropdown item text                #374151 on #ffffff  10.31:1  PASS AA
badge text-bg-primary             #f9fafb on #3f6212   6.77:1  PASS AA
badge text-bg-warning             #f9fafb on #b45309   4.81:1  PASS AA
.alert-warning text               #374151 on #ffffff  10.31:1  PASS AA
public .accent-strong             #4d7c0f on #ffffff   4.99:1  PASS AA
public .rallye-date               #4d7c0f on #ffffff   4.99:1  PASS AA
```

Two things this caught that reading the CSS would not have:

- The public `#mainNav` rules live inside `@media (min-width: 992px)`, so the
  active-link colour measures as unset at a narrow viewport. Verified at
  1400px, where it resolves to `#4d7c0f`.
- The public site uses **no** Bootstrap buttons (zero `btn` in any template,
  and no `.btn-primary` rule in `styles.css` at all), so button contrast on
  that side is not a thing to check.

### Brand text: white in admin and members (2026-10-01)

The "GS Redan" text next to the logo is white. Both layouts build the brand as
an `<a>` inside `.navbar-brand`:

```html
<div class="navbar-brand navbar-brand-autodark ...">
  <a href="/admin" class="d-flex align-items-center gap-2">
    <img src="/logo.webp" class="navbar-brand-image" />
    <span>GS Redan</span>
  </a>
</div>
```

so the visible text takes the **anchor** colour, not the wrapper's. Tabler's
`a { color: rgba(var(--tblr-link-color-rgb), …) }` wins over
`--tblr-navbar-brand-color` on the parent, and with `--tblr-link-color` set to
the primary green the brand rendered **dark green on the dark green navbar** —
effectively invisible. This is invisible to a rule that only sets the wrapper:
`--tblr-navbar-brand-color` cannot fix it, because the property does not
inherit *into* the child's already-declared `color`.

`.navbar .navbar-brand, .navbar .navbar-brand a { color: #fff }` sets both, so
the rule holds whichever element paints the glyphs. Hover/focus goes to
`lamp-400` via `.navbar .navbar-brand a:hover` (0,3,1) — needed because
Tabler's own `a:hover` (0,1,1) and `.navbar-brand:hover` (0,2,0) would
otherwise take it.

Verified by computed style, base and simulated hover (`:hover` rewritten to a
class so the cascade decides in headless Chromium):

```
brand anchor text-decoration: none
.navbar-brand (div)        : rgb(255, 255, 255)
a inside navbar-brand     : rgb(255, 255, 255)
span (the "GS Redan" text) : rgb(255, 255, 255)
brand anchor  hovered     : rgb(240, 180, 41)   <- lamp-400
nav-link       hovered    : rgb(255, 255, 255)
nav-link.active hovered   : rgb(240, 180, 41)
```

### The logo keeps its own colours (2026-10-01)

Decided: **not** whitened, even though the brand text beside it is white.

This was applied and then reverted the same day, both at the user's request. The
sequence matters for anyone reading the diff, so it is kept rather than tidied
away.

`.navbar-brand-autodark` is already on the brand in both layouts, and its only
job in Tabler is `filter: brightness(0) invert(1)` on the image — but that rule
is scoped to `[data-bs-theme=dark]`, and this site runs the **light** theme (no
`data-bs-theme` attribute anywhere). So it had never fired, and the logo
rendered in its own colours: a tan, opaque pixels averaging `#94795b`, ~2.8:1
against the `#24401a` navbar mid-stop. That is under the 3:1 WCAG asks of
meaningful non-text graphics, and it is not a regression — the mark had exactly
the same contrast on the old purple navbar.

The first pass added the filter to the existing `.navbar-brand-image` rule,
scoped to `.navbar` so `layout.login.twig` (same `.navbar-brand-autodark`
markup, no navbar, light page) would keep the mark visible. Measured as passing:
1549 near-white pixels in the navbar band where the mark sits, zero tan pixels
left.

It was then reverted: `brightness(0) invert(1)` flattens the mark to a
**featureless silhouette**, and the logo is the club's identity, not an ornament.
The user's words: "Aah, no I want to see the logo." A flat white blob is not
seeing the logo.

The rule is back to sizing only:

```css
.navbar-brand-image {
	height: 2rem;
	width: auto;
}
```

So the navbar carries a white wordmark beside its natural tan mark. Accepted
trade: the mark is under 3:1 against the navbar, exactly as it was before the
caving theme. **If a future logo is genuinely illegible on the navbar, recolour
the source file at `app/front/media/image/logo.webp`** — a CSS filter cannot add
contrast, it can only discard the colour that carries the meaning. Do not
re-add the filter.

Verified after the revert, headless Chromium against the real `base.css` +
`tabler.min.css`, computed style plus a pixel audit of the rendered navbar band:

```
.navbar .navbar-brand-image  filter : none
.navbar .navbar-brand-image  height : 32px
.navbar .navbar-brand (div)  color  : rgb(255, 255, 255)   <- brand text still white
.navbar .navbar-brand a      color  : rgb(255, 255, 255)
navbar band pixels          : 336 tan pixels, mean rgb(140, 102, 69)
```
