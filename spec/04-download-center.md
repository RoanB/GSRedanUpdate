# 04 — Download center → Members area

**2026-09-17: this spec is superseded by [07-members-calendar.md](07-members-calendar.md).**
Renaming decisions and everything new (categories, calendar, ICS/CalDAV, the
tabler layout of the page, the `/download` → `/members` redirect) live there.
Everything below still describes the original build.

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
- No delete/archive action was built. Files can be hidden with `visible = 0`;
  deleting rows is out of scope for now (would need the skeleton `Delete` trait
  and removal of the underlying `File`).
- `visible = 0` files are skipped on the public page but stay editable.

**Discrepancy flagged:** the original design included soft delete and the
`list.twig` + `edit.twig` template names; both changed as above.


## Public page

- `template/download.twig` renders either the gate or the list depending on
  `show_gate`. The list shows `[ File name, size, download button ]` for
  `Download_File::get_visible_ordered()` (size formatted by the module's
  `format_bytes()`).
- Same tabler styling as the rest of the public site so it feels consistent
  (`layout.public.twig`).
- Nothing about the content or the file list is HTML-cached.
- Hidden rows are simply absent from the list.

## Settings link

- Changing the shared password lives in `Admin/Settings → Download page` (see
  `03-admin.md`).
- The initial hash (seeded to `'OuEstLaCorde'`) is documented in
  `01-data-model.md` so the human tester knows what to type.
