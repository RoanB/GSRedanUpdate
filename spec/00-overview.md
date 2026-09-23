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
