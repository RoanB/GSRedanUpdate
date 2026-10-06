# 04 — Download center → Members area

**2026-09-17: this spec is superseded by [07-members-calendar.md](07-members-calendar.md).**
Renaming decisions and everything new (categories, calendar, ICS/CalDAV, the
tabler layout of the page, the `/download` → `/members` redirect) live there.
Everything below still describes the original build.

**2026-09-28: the leftovers of that original build are gone.** The page below no
longer exists under `/download`; it is the members area (`members.twig`, module
`App\Front\Module\Members`, gate + file list), and `App\Front\Module\Download`
is a shim that redirects `/download` to `/members` so old links keep working.
`template/download.twig` was therefore dead — nothing could render it, and its
form posted to `/download?action=gate`, an action the shim does not have. It
was deleted, together with the last `?failed=1` query flag (the gate error is a
sticky session key now, like every other flash message in the project). Read
the access flow below as history; the live one is in 07.

## Goal

A semi-public page under `/download` where a "member" enters a shared website
password (not tied to an account) and then sees + downloads files that admins
manage in the normal admin.

## Access flow

`App\Front\Module\Download` (`login_required = false`). Actions are dispatched
with `?action=` (skeleton's native mechanism), **not** path segments — the
original text's `/download/gate` etc. would require extra route entries and
`Module::resolve` would look for a `Download\Gate` class. Decided in favour of
`?action=gate|get|logout`.

1. `GET /download` —
   - If `$_SESSION['download_authenticated'] === true`: render the file list.
   - Else render the password gate. One template (`template/download.twig`)
     covers both states via the `show_gate` flag.
2. `POST /download?action=gate` — password check against
   `Setting::get_by_name('download_password_hash')` using `password_verify`.
   - On match: `$_SESSION['download_authenticated'] = true`, redirect to
     `/download`.
   - On mismatch: redirect to `/download?failed=1`; the gate template shows an
     error. No `sleep()` throttle was implemented (the page is intentionally
     semi-public).
3. Actual download: `GET /download?action=get&id=$id` — checks
   `$_SESSION['download_authenticated']`, resolves `\Download_File` to its
   `\Skeleton\File\File` and streams it with `File::client_download()` (no raw
   filesystem read). `client_download()` takes only an optional
   cache-seconds argument; the filename comes from the stored `File` row.
   `client_download()` does **not** exit, and the module keeps
   `$this->template = 'download.twig'`, so the action must `exit` itself right
   after streaming — otherwise `handle_request()` renders the template and
   appends HTML to the file bytes.
4. Logging out: `GET /download?action=logout` —
   `unset($_SESSION['download_authenticated'])` + redirect to `/download`.

No rate limiter, no captcha: this page is "semi-public" by design; the shared
password is the only gate.

**Discrepancy flagged:** the session key is `download_authenticated`, not
`download_access`, and no sticky message is used (query flag instead).


## Admin management

`App\Front\Module\Admin\Download` (`template/admin/download.twig` +
`template/admin/download_edit.twig`):

- Upload: file via plain `<input type="file">` stored with `Skeleton\File` (it
  already lives under `store/file/` per `config/core.php`); only the file
  metadata is stored in `Download_File`.
- Fields: `name`, `sort_order`, `visible`.
- Same drag & drop + visibility AJAX pattern as `Admin\Block`, posting to
  `/admin/download?action=order` (repeated `ordered[]`) and
  `/admin/download?action=visibility` (JSON `{visible}`), reusing `base.js`.
- **2026-10-01 — file deletion added:** `GET /admin/download?action=delete&id=X`
  removes the `Download_File` row and the underlying `\File` (and its stored
  bytes) so no orphan file is left behind. The admin file list shows a red
  Delete button per row with a JS `confirm()` dialog.
- `visible = 0` files are skipped on the public page but stay editable.

**Discrepancy flagged:** the original design included soft delete and the
`list.twig` + `edit.twig` template names; both changed as above.


## Public page

- Superseded (2026-09-28): `template/download.twig` is deleted. The members
  area renders both states from `template/members.twig` through the
  `show_gate` flag, and lists `Download_File::get_visible_ordered()` per
  category (size formatted by `Members::format_bytes()`).
- Same tabler styling as the rest of the public site so it feels consistent
  (`layout.public.twig`).
- Nothing about the content or the file list is HTML-cached.
- Hidden rows are simply absent from the list.

## Settings link

- Changing the shared password lives in `Admin/Settings → Download page` (see
  `03-admin.md`).
- The initial hash (seeded to `'OuEstLaCorde'`) is documented in
  `01-data-model.md` so the human tester knows what to type.
