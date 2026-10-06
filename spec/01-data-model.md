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
  `email` is UNIQUE, which is why the duplicate-email check on save must see
  archived rows too (an archived user keeps owning its address).

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
| `download_password` | `OuEstLaCorde` (plaintext mirror, added 2026-09-23) |

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

### `user_password_reset_token`
One row per reset link handed out to a user (2026-09-28).

```sql
CREATE TABLE `user_password_reset_token` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`uuid` varchar(36) NULL,
	`user_id` int NOT NULL,
	`token` varchar(64) NOT NULL,          -- SHA-256 of the raw token, never the raw token
	`expires_at` datetime NOT NULL,
	`used` tinyint NOT NULL DEFAULT '0',
	`created` datetime NOT NULL,
	`updated` datetime NULL,
	`archived` datetime NULL,
	UNIQUE KEY `token` (`token`),
	FOREIGN KEY (`user_id`) REFERENCES `user` (`id`)
);
```

The table is `user_password_reset_token` while the class is
`Password_Reset_Token`: the autoloader derives `password_reset_token` from the
class name, so the model sets `database_table` explicitly.

The raw token is `bin2hex(random_bytes(32))` and only exists in the mail. The
row holds its SHA-256 hash, so a database dump cannot be replayed against
`/login?action=reset_password`. Issuing a new link marks every earlier one
`used`, and setting a new password does the same — at most one live link per
user at any time. Validity is 24 hours
(`Password_Reset_Token::VALIDITY_SECONDS`).

`created` does double duty as the throttle for the self-service reset form
(`is_in_resend_cooldown()`, `RESEND_COOLDOWN_SECONDS = 600`): an account with a
live token younger than ten minutes gets no second mail. It counts rows in this
table rather than a session counter, so clearing cookies or a private window
does not reset it. No index is needed for it — the table holds one live row per
user by the invariant above.

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
- `lib/model/Password/Reset/Token.php` (`Password_Reset_Token`, table
  `user_password_reset_token`)
  - `create_for_user(User $user, string $raw_token): self` — invalidates the
    user's open tokens, stores the SHA-256 hash of `$raw_token`.
  - `get_by_raw_token(string $raw_token): ?self` — only a row that is unused,
    unarchived and unexpired; `null` otherwise.
  - `invalidate_all_for_user(User $user): void` — spends every open token.
  - `is_in_resend_cooldown(User $user): bool` — true when the user already has
    a live token younger than `RESEND_COOLDOWN_SECONDS`. Added with the
    self-service reset form on the login screen; see the table notes above.
  - `get_user(): User`, `mark_used(): void`, `is_valid(): bool`.
  - `hash_token()` is private: hashing is the only way in and out.
- `lib/model/Country.php` — **removed** 2026-09-28. The model queried a
  `country` table that no migration in this project creates, and its only
  caller was the `select_country` macro in the deleted `macro.base.twig`.
- Validation error vocabulary: `required`, `duplicate`, `invalid`, plus the
  one-offs `before_start` (calendar) and `type_must_be_gallery` (pictures).
  `required` was normalised from the earlier `mandatory` spelling (User,
  Download_File) on 2026-09-28, matching the majority of the models and the
  `required` HTML attribute on the form inputs. The values are shown as-is by
  `tabler.show_errors()` (`key: value`), they are not translated msgids.

`lib/model/User.php` already exists: strip its Organization-specific parts
(`get_organizations`, `has_organization`, `get_organization_user`,
`send_verify_email`, `Transaction_*`) and the vvsjongeren cookie references in
`Login`; keep `authenticate`, `set_password`, `validate` (reduce mandatories to
`email`, `password`, `firstname`, `lastname`).

User additions for the admin user management (2026-09-28):

- `get_all_ordered(): array` — active users, `lastname, firstname, email`.
  Archived users are left out: the admin list works on usable accounts.
- `count_all(): int` — active count for the dashboard tile.
- `get_active_by_email(string $email): self` — login lookup. `get_by_email()`
  stays the unfiltered one because the duplicate-email check on save has to see
  archived rows: `user.email` is unique, so an archived user still owns its
  address.
- `authenticate()` goes through `get_active_by_email()`, so archiving an account
  takes effect on the next login attempt instead of at session expiry.
- `issue_password_reset_token(): string` — returns the **raw** token for the
  mail; only the hash is stored (see `user_password_reset_token`).
- `set_new_password(string $password): void` — hashes, saves, and spends every
  open reset token of that user.
- `get_name(): string` — `firstname lastname`, falling back to the email.
- `MINIMUM_PASSWORD_LENGTH = 8` and
  `validate_password_strength(string $password): ?string` — returns `too_short`
  or `null`. Shared by the admin add form and the reset form, so both entry
  points enforce the same rule.

## Migration list

All new migrations in `migration/`: the 2026-09-16 batch is `20260916_*`, the
2026-09-23 additions are `20260923_*`, and the reset-token table is
`20260928_100000_password_reset_token.php`.

1. `20260916_*_init.php` — drops the skeleton-core `user` table and recreates
   with `uuid`, `admin`, `verified`, `language_id` FK + unique email; alters
   `file` to add `updated`; `CREATE TABLE`s: `block`, `block_translation`,
   `setting`, `download_file`. Working `down()` drops all in reverse. The old
   `20251017_170550_init.php` is neutralized to no-op.
2. `20260916_*_seed_users.php` — admin users from `migration/data/admin_users.csv`
   (gitignored; `email,firstname,lastname,password`). Mirrors
   `vvsjongeren/migration/20260814_000002_seed.php` approach.
3. `20260916_*_seed_settings.php` — setting rows from the table above.
4. `20260923_*_seed_download_password.php` (2026-09-23) — seeds the plaintext
   mirror `download_password` for the admin share links (the hash cannot be
   read back; see 07).
5. `20260916_*_seed_blocks.php` — the 10 blocks in the order of `../redan` and
   their FR/NL/EN `block_translation` rows, **prefilled with the existing HTML
   content** (see 01 note below).
6. `20260928_100000_password_reset_token.php` (2026-09-28) — `CREATE TABLE
   user_password_reset_token`; `down()` drops it. A rollback intentionally
   kills every outstanding reset link rather than leaving usable credentials
   behind. Not yet run against a database (see 03-admin.md, reset flow).

Content prefill rule: block `body` for FR = the section's inner HTML from
`../redan/index.html`; NL from `nl/index.html`; EN from `en/index.html`, with
the static mailto/maps/social links replaced by placeholders that the template
renders from `setting` values instead (see 02-public-site.md).

Every `up()` must have a working `down()` (drop tables / delete seeds) per the
migration skill: reversible, and rerunnable.

## Autoloader

`lib/model/`, `lib/base/` are on the include path (skeleton bootstrap).
`Class_Name` naming, one class per file.
