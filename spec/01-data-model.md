# 01 — Data model

Conventions (see repo-root AGENTS.md): singular table names, auto-increment
`id`, magic columns `uuid`, `created`, `updated`, `archived` (order: `id`,
`uuid`, foreign keys, data, timing), FKs named `target_table_id`, trailing
commas on multi-line arrays, no floats.

## Tables

Existing (created by earlier migrations / packages, keep as is):

- `language` — skeleton-i18n, seeded with EN; seed migration adds FR + NL.
- `user` — `email`, `password` (hash), `firstname`, `lastname`, `admin`
  (tinyint), `language_id` FK. Only admins exist; there is no public
  registration. No country_id or address fields — this project's user is
  simpler than vvsjongeren's. The skeleton-core base `user` table (no
  uuid/admin/verified) is dropped and recreated in the 20260916 init migration.

The existing `migration/20251017_170550_init.php` was a broken copy-paste from
another project (tries to ALTER user with non-existent columns, inserts
Domibus junk). It has been **neutralized** (no-op); the real schema lives in
`20260916_*_init.php` below.

New:

### `block`
The homepage order. One row per block.

```sql
CREATE TABLE `block` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`uuid` varchar(36) NULL,
	`type` varchar(32) NOT NULL,           -- see block types below
	`anchor` varchar(64) NOT NULL,         -- html id + url anchor, e.g. 'rallye'
	`sort_order` int NOT NULL DEFAULT '0',
	`visible` tinyint(4) NOT NULL DEFAULT '1',
	`created` datetime NOT NULL,
	`updated` datetime NULL,
	`archived` datetime NULL
);
```

Block `type` values: `masthead`, `club`, `activities`, `gallery`,
`announcement`, `contact`, `footer`.
`contact`, `footer` also get rows in `block` so they can be ordered/hidden too,
but their content partly lives in `setting` (see below).

**2026-09-17 cards evolution** (migration `20260917_113000_cards`): the visual
row blocks are unified into generic, creatable "cards":

- `rallye`, `salle`, `training`, `youth` → `activities`, with their
  previously hard-coded partial images uploaded as real `File`/`Picture`
  rows (`block.file_id`); `rallye_gallery` → `gallery`, its three raw
  `<img>` body columns transplanted into `block_picture` rows (rallye-1/2/3
  webp files registered as pictures) and the translation `body` emptied —
  the content now lives in the card structures, not in raw HTML.
- New columns on `block`: `file_id` int unsigned NULL FK→file (one image for
  an activity card), `file_id_2` int unsigned NULL FK→file (second image of
  a 2-row pair card).
- **2026-09-17 cards rework** (migration `20260917_160000_card_types`): the
  layout knobs moved into the card type. The columns `image_layout` and
  `group_pair` were **dropped** (image_side stays: it still drives the photo
  column of light cards and the Monday openings card). Conversion:
  `image_layout = featured` → `activities_light`; single `image_layout =
  project` → `activities_dark`; the welded project pair (Entraînement +
  Activités jeunes) became ONE `activities_pair_dark` block: the second
  block was archived, its image moved to `block.file_id_2` of the first and
  its body appended behind the pair marker `<!--pair:<second block id>-->`
  in every `block_translation.body` (down() splits the marker and
  un-archives the row). New creatable types (`Block::CREATABLE_TYPES`):
  `activities_light`, `activities_dark`, `activities_pair_light`,
  `activities_pair_dark`, `gallery`, `announcement`. New SINGULAR type
  `monday_openings` (locked: not creatable, not deletable — but draggable
  and editable; seeded with anchor `openings`, side `right`, menu labels
  Ouvertures du lundi / Maandagopeningen / Monday openings). The `footer`
  block type was **removed entirely**: the footer is permanent page chrome.
- New table `block_picture` (`id`, `uuid`, `block_id` FK, `file_id` FK,
  `sort_order`, `visible`, timing columns) — ordered picture list of a
  gallery card, matching the download_file pattern.
- Pair bodies: `Block::split_pair_body()` / `stitch_pair_body()` split on
  `<!--pair[:<id>]-->`; the top row keeps everything before, the bottom row
  after it.
- **2026-09-23 pair-flag fix** (migration `20260917_130500_card_pair` + repair
  `20260923_120000_repair_orphan_activities_pair`): the pair-flagger
  originally set `group_pair = 1` on **both** halves of the project 2-pack.
  The welder in `20260917_160000_card_types` searches for a **preceding**
  row with `group_pair = 0` as its merge target, so with both halves
  flagged it welded nothing and left Entraînement + Activités jeunes as two
  orphaned `activities` rows (no template → homepage error). Decision: only
  the SECOND row carries `group_pair = 1`; the repair migration welds any
  orphaned `activities` pair into one `activities_pair_dark` card (second
  row archived, image → `file_id_2`, body appended behind the marker) and
  maps an odd leftover to `activities_dark`. It is idempotent: welded rows
  no longer match the `activities` selector.

### `block_picture`
Localizable block content, exactly one row per (block, language).

```sql
CREATE TABLE `block_translation` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`uuid` varchar(36) NULL,
	`block_id` int NOT NULL,               -- FK block.id
	`language_id` int NOT NULL,            -- FK language.id
	`title` varchar(255) NOT NULL DEFAULT '',
	`body` text NULL,                      -- rich HTML
	`created` datetime NOT NULL,
	`updated` datetime NULL,
	`archived` datetime NULL,
	FOREIGN KEY (`block_id`) REFERENCES `block` (`id`),
	FOREIGN KEY (`language_id`) REFERENCES `language` (`id`),
	UNIQUE KEY `block_language` (`block_id`, `language_id`)
);
```

The `block`/`block_translation` pair also powers the menu: menu label =
`block_translation.title` of the visible blocks in `sort_order`.

### `setting`
Key/value store (copy of `vvsjongeren/lib/model/Setting.php`, which already has
`Setting::get_by_name`, `get_value`, `set_value`).

```sql
CREATE TABLE `setting` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`uuid` varchar(36) NULL,
	`name` varchar(64) NOT NULL,
	`value` text NULL,
	`created` datetime NOT NULL,
	`updated` datetime NULL,
	`archived` datetime NULL,
	UNIQUE KEY `name` (`name`)
);
```

Seeded keys:

| key | initial value (seed migration) |
| --- | --- |
| `contact_email` | `contact@gsredan.be` |
| `address_local_street` | `Parvis de la Basilique 1, porte 7` |
| `address_local_zip` | `1083` |
| `address_local_city` | `Ganshoren` |
| `address_local_maps` | `https://maps.app.goo.gl/USRK75XeW6wMEvk9A` |
| `address_club_street` | `Bd de Smet de Naeyer 21` |
| `address_club_zip` | `1090` |
| `address_club_city` | `Jette` |
| `address_club_maps` | `https://maps.app.goo.gl/FBkR26S1usDrRFsA9` |
| `social_instagram` | `https://www.instagram.com/gs_redan` |
| `social_facebook` | `https://www.facebook.com/GroupeSpeleoRedan/` |
| `footer_company_number` | `0474.156.883` |
| `download_password_hash` | `password_hash('OuEstLaCorde', PASSWORD_DEFAULT)` |

### `download_file`
Files shown in the download center.

```sql
CREATE TABLE `download_file` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`uuid` varchar(36) NULL,
	`file_id` int unsigned NOT NULL,       -- FK file.id (skeleton-file id is int(11) unsigned)
	`name` varchar(128) NOT NULL,          -- display name
	`sort_order` int NOT NULL DEFAULT '0',
	`visible` tinyint(4) NOT NULL DEFAULT '1',
	`created` datetime NOT NULL,
	`updated` datetime NULL,
	`archived` datetime NULL,
	FOREIGN KEY (`file_id`) REFERENCES `file` (`id`)
);
```

## Models (`lib/model/`)

Following skeleton object style (traits `Model`, `Uuid`, `Get`, `Save`,
`Delete`), one class per file, doc block on everything:

- `lib/model/Block.php`
  - `get_all_ordered(): array` — visible + hidden, ordered by `sort_order`.
  - `get_visible_ordered(): array` — public site.
  - `save_order(array $ids): void` — static: loop ids and persist positions.
  - validation on `save`: known `type`, `anchor` unique **when set**. An empty
    anchor is valid: masthead, gallery, youth and footer have no HTML id in the
    source site, so `anchor` must not be mandatory or those blocks could never
    be saved.
- `lib/model/Block/Translation.php` (`Block_Translation`, table
  `block_translation`)
  - `get_by_block_language(Block $block, Language $language): Block_Translation`
    (fallback handled by callers, not here).
- `lib/model/Setting.php` — copy class from vvsjongeren unchanged (it is
  self-contained).
- `lib/model/Download/File.php` (`Download_File`, table `download_file`)
  - `get_all_ordered(): array`, `get_visible_ordered(): array`,
    `save_order(array $ids): void`, and
    `get_file(): \Skeleton\File\File`.
  - `get_file()` returns the generic `\Skeleton\File\File`, **not** the project
    `\File`: skeleton-file resolves picture uploads to
    `\Skeleton\File\Picture\Picture` (a `\Skeleton\File\File` but not a
    `\File`), so a `\File` return type would break on images.
- `lib/model/File.php` (`File`, table `file`) — required because
  `Bootstrap::boot()` sets `\Skeleton\File\Config::$file_interface = 'File'`
  and the admin calls `\File::upload()`. It is an empty subclass of
  `\Skeleton\File\File` (no behaviour of its own).

`lib/model/User.php` already exists: strip its Organization-specific parts
(`get_organizations`, `has_organization`, `get_organization_user`,
`send_verify_email`, `Transaction_*`) and the vvsjongeren cookie references in
`Login`; keep `authenticate`, `set_password`, `validate` (reduce mandatories to
`email`, `password`, `firstname`, `lastname`).

## Migration list

All new migrations in `migration/`, timestamped `20260916_HHMMSS_*`:

1. `20260916_*_init.php` — drops the skeleton-core `user` table and recreates
   with `uuid`, `admin`, `verified`, `language_id` FK + unique email; alters
   `file` to add `updated`; `CREATE TABLE`s: `block`, `block_translation`,
   `setting`, `download_file`. Working `down()` drops all in reverse. The old
   `20251017_170550_init.php` is neutralized to no-op.
2. `20260916_*_seed_users.php` — admin users from `migration/data/admin_users.csv`
   (gitignored; `email,firstname,lastname,password`). Mirrors
   `vvsjongeren/migration/20260814_000002_seed.php` approach.
3. `20260916_*_seed_settings.php` — setting rows from the table above.
4. `20260916_*_seed_blocks.php` — the 10 blocks in the order of `../redan` and
   their FR/NL/EN `block_translation` rows, **prefilled with the existing HTML
   content** (see 01 note below).

Content prefill rule: block `body` for FR = the section's inner HTML from
`../redan/index.html`; NL from `nl/index.html`; EN from `en/index.html`, with
the static mailto/maps/social links replaced by placeholders that the template
renders from `setting` values instead (see 02-public-site.md).

Every `up()` must have a working `down()` (drop tables / delete seeds) per the
migration skill: reversible, and rerunnable.

## Autoloader

`lib/model/`, `lib/base/` are on the include path (skeleton bootstrap).
`Class_Name` naming, one class per file.
