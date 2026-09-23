# 06 — Testing & verification

Framework: skeleton-test. This repo has no tests yet; create the test path per
`skeleton-test` conventions (classes named `Some_Thing_Test` under the test
dir configured by skeleton-core, `util/bin/skeleton` runs them).

## Scoped commands (always run these for touched code)

```bash
util/bin/skeleton test:run User_Test
util/bin/skeleton test:run Block_Test
vendor/bin/phpinsights analyse lib/model/Block.php
```

## Tests to write

- `User_Test` — `authenticate()` success/failure/wrong password, after
  stripping the Organization code.
- `Block_Test` — `get_visible_ordered()` ordering, `save_order()` persistence,
  duplicate-`anchor` validation rejected.
- `Setting_Test` — `get_value` unknown key returns null, `set_value` upsert.
- `Download_Gate_Test` — POST with wrong password does not set
  `$_SESSION['download_access']`; correct password does; `/download/get` 403
  without the session flag.
- Manual template smoke: render `/` with FR/NL/EN and with a hidden block; run
  the drag & drop ordering endpoint against the local DB.

## Verification checklist (acceptance)

1. `/` looks pixel-close to the static site in FR/NL/EN (opened side by side).
2. `/admin` unreachable without login; login with a seeded admin works.
3. Blocks: reorder via drag & drop → menu order changes accordingly, persisted
   after reload.
4. Hide a block → it disappears from page and menu; unhide restores it.
5. Edit a block in all three languages → visible on the public site in the
   matching language, FR fallback works when a translation is empty.
6. Announcement block can be moved anywhere in the order and hidden; when
   visible it appears both on the page and in the menu.
7. Settings: contact email/addresses/social/footer changes appear on the
   public page. Download page password can be changed and the new value
   works, old value rejected.
8. `/download` gate accepts `OuEstLaCorde` out of the box; after logging in
   with the shared password the file list shows admin-uploaded files; download
   links stream successfully; hidden files are skipped.
9. `util/bin/skeleton migrate:up` + `migrate:down` + `migrate:up` on a scratch
   DB is idempotent and reversible.

## Human checkpoints (do not do alone)

- Running `migrate:up`/`migrate:down` on the real dev DB.
- Choosing the seeded admin password value (goes into gitignored
  `migration/data/admin_users.csv`, never in VCS).
- `composer update` (should be a no-op, everything needed is already required).
- Removing `die('l')` from `handler.php` when the project goes live.
