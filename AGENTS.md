# AGENTS.md

Guidance for AI coding agents working in this repository. This complements
`README.md` (which is for humans) — it's the machine-readable onboarding: how to
set up, which commands to run, the conventions to follow, and what needs a human
in the loop.

Baseline coding standard: [tigron/skeleton-coding-standard](https://github.com/tigron/skeleton-coding-standard)
(built on [PSR-12](https://www.php-fig.org/psr/psr-12/) / PSR-1). This file
summarises the parts an agent needs and adds personal conventions; follow the
linked standard for exhaustive examples.

## Working style

- Be direct. Explain the underlying idea, not just the surface fix. Concrete
  examples over abstractions. Skip hedging filler.
- Prose over bullet points when it flows; lists only when structure helps.
- English for technical work.

## Technology stack

- **Language**: PHP (PSR-12 base), plus JavaScript/jQuery and Twig templates
- **Framework**: skeleton (`tigron/skeleton-*`, loosely coupled Composer packages)
- **Database**: MySQL (via PDO); skeleton object layer for models
- **Package manager**: Composer
- **Testing**: skeleton-test
- **Validation**: PHP Insights (enforces the coding standard)
- **Environment**: Manjaro Linux, VS Code over Remote SSH, Claude Code CLI

## Environment setup

```bash
composer create-project tigron/skeleton   # new project
composer update                           # install/update dependencies
# create config/environment.php with at least the database DSN (see Security)
util/bin/skeleton migrate:up              # apply database migrations
```

Point the web server's document root at `webroot/`; all requests route through
`webroot/handler.php`.

## Commands

Prefer file- or class-scoped commands for fast feedback. Run the full suite only
when explicitly asked.

```bash
# Scoped (preferred)
util/bin/skeleton test:run My_Test_Class          # one test class
util/bin/skeleton test:run My_Test_A,My_Test_B    # a list
util/bin/skeleton test:intense My_Test_Class      # run a class 10x
vendor/bin/phpinsights analyse lib/model/Some_Class.php   # lint one path

# Full suite (only when requested)
util/bin/skeleton test:all
vendor/bin/phpinsights                            # validate whole project
```

## Project structure

skeleton is intentionally minimal: it suggests structure without enforcing much.
"If you need it twice, make it a package."

```
project/
├── app/                  one subdirectory per application (matched by host/URI)
│   └── admin/            example app: config/, event/, module/, template/
├── config/               global config, loaded alphabetically
│   └── environment.php    DSN + secrets; loaded last; keep OUT of VCS
├── lib/                  application classes (autoloader paths below)
│   ├── base/  component/  model/
├── migration/            database migrations
├── util/                 CLI tooling (util/bin/skeleton ...)
└── webroot/handler.php   single front controller
```

Autoloader include paths are typically `lib/model/`, `lib/base/`, `lib/component/`.
Combined with `Class_Name` naming and one-class-per-file, `Customer_Project`
lives in its own file on an include path and loads without a manual `require`.
Application events live in `\App\{APP_NAME}\Event\{Context}`, extending
`\Skeleton\Core\Application\Event\{Context}`. Start from `app/admin` as a reference.

## Code conventions

Full detail and examples: the [skeleton-coding-standard](https://github.com/tigron/skeleton-coding-standard).
The rules below are the ones agents most need, plus personal deltas.

### Highest priority

- **Never use inline control structures.** Always braces, body on its own line —
  PHP and JS alike. (Personal rule.)
- **Prefer longer, readable code over compact or clever code.** Meaningful names
  over shorthand; an explicit step over hidden nesting.
- **`===` / `!==` only**, never `==` / `!=` — the codebase targets strict typing.

### PHP

- Files start with `<?php`; no closing `?>`.
- Indent with **tabs**. 1TBS bracing (opening brace on the same line).
- Class member order: properties → public → protected → private → then the same
  order for static members.
- Naming: `snake_case` for properties, methods, and functions; `Class_Name` for
  classes; lowercase parameter types (`string`, `int`, `bool`).
- No useless `else` after `return`/`break`/`continue`; no empty `if`/`catch`; no
  function calls in `for` conditions (resolve once before the loop); no useless
  intermediate variable that's only returned.
- Arrays: short syntax `[]`; trailing comma on multi-line arrays only; single
  space around `=>`, no column alignment.
- Increment/decrement is its own statement, not folded into a call.
- Prefer single quotes. `use` imports sorted alphabetically. One class per file.
- Doc block on every class, property, and method; method body starts on the line
  directly after its doc block.

### JavaScript / jQuery

- Main JS file is `base.js`.
- `snake_case` for variables and functions (matches PHP, not JS convention —
  personal delta). Tabs, no inline control structures.

### Database

- Table names singular; every table has auto-increment `id`; foreign keys named
  `target_table_id`; link tables are `table_a_table_b`; parent/child is
  `table` / `table_item`.
- `uuid`, `created`, `updated`, `archived` are magic columns — skeleton manages
  them, don't set them by hand.
- Currency is `decimal(10,2)` — never float (matters for Exact Online / Peppol
  amounts). Tables must have foreign keys for all linked objects. Column order:
  `id`, `uuid`, foreign keys, data, then timing columns (`created`, `updated`,
  `archived`).
- Keep `ALTER` statements narrow (don't add a column and a foreign key at once).
  Deprecated tables/columns get a leading underscore (`_customer`).

### API

- OpenAPI, via `skeleton-application-api`. **Casing exception**: API classnames,
  endpoints, and variables use **camelCase**, not `snake_case`. Keep domain code
  (`snake_case`) and the API surface (`camelCase`) straight.
- Only uniquely identifiable properties go in the path; respect object hierarchy
  (`/product/{productCategory}/{product}`). Exact lookup is `getByProperty`;
  partial search is `/search`. Page large lists with `?page=X`.

## Testing

- Framework: skeleton-test. Test classes are named `Some_Thing_Test` and live
  under the configured test path.
- Write a test for new behaviour. Run the scoped command for the class you're
  touching (see Commands) before the full suite.

## Security and secrets

- **Never commit credentials.** `config/environment.php` holds the database DSN
  and secrets and must stay out of version control (`.gitignore`).
- Exact Online API credentials (client ID/secret, OAuth tokens) and any Peppol
  access keys live in gitignored config, never in code or committed config.
- Never log secret values or pass them as command-line arguments.
- A minimal `environment.php`:

```php
<?php
return [
	'database' => 'mysqli://username:password@localhost/database',
];
```

## Decision tracking

Every design decision made during implementation **must** be written back into
the relevant `spec/*.md` file, in the same session, before any related code is
considered finished. The spec is the single source of truth: what was decided,
why, and the consequences. This includes — but is not limited to — schema
choices, password/seed values, URL shapes, editor choices (e.g. plain
`<textarea>` vs a WYSIWYG bundle), fallback behaviour, cache-busting values,
and framework behaviours discovered during exploration. If a decision would
contradict an existing spec statement, amend the spec and flag the discrepancy
to the user. Never leave a decision only in the chat log.

## Good and bad patterns

- **Follow**: `app/admin` for application layout (module/event/template); skeleton
  object models in `lib/model/` for persistence patterns.
- **Avoid**: loose comparison (`==`), empty `catch` blocks, inline control
  structures, floats for money, hardcoded credentials or DSNs.
- *(Add real repo paths here as canonical good/bad references once known.)*

## Permissions

**Allowed without asking**: read files; run PHP Insights on a file or path; run a
specific test class; inspect the database schema.

**Ask first**: `composer require` / `composer update`; `git commit` / `git push`;
running migrations (`migrate:up` / `migrate:down` — they mutate the database);
`ALTER TABLE` or other schema changes; deleting files; running the full test
suite; anything that writes to Exact Online (bookings) or emits Peppol/UBL output.

## Commit and PR guidelines

*(Confirm these against the actual team workflow — placeholder defaults:)*

- Keep diffs small and focused.
- Run `vendor/bin/phpinsights` and the relevant tests before committing.
- Write a clear, imperative commit subject.

## When unsure

- If a convention here conflicts with the surrounding code in a file you're
  editing, match the existing code and flag the discrepancy rather than silently
  reformatting.
- Stop and ask before schema changes, migrations, or anything touching Exact
  Online bookings or Peppol output — those have real financial/legal effects.
- If tests fail repeatedly or the intent is ambiguous, ask a clarifying question
  instead of guessing.

## Worked example

Touches most PHP rules at once — member layout, doc blocks, `snake_case`,
`Class_Name`, tabs + 1TBS, `===`, no useless else, no empty catch, short arrays
with trailing commas, single quotes.

```php
<?php
/**
 * Sales_Entry
 *
 * Represents a single sales entry to be booked in Exact Online.
 *
 * @author Roan Buysse <roan@tigron.be>
 */

use \Exact\Client;
use \Exact\Exception\Booking_Failed;

class Sales_Entry {
	/**
	 * Gross amount, VAT included
	 *
	 * @var float $gross_amount
	 */
	protected $gross_amount = 0.0;

	/**
	 * Book this entry via the Exact Online API
	 *
	 * Sends the gross amount in the header so the API does not report a
	 * Verschil between the header total and the summed lines.
	 *
	 * @access public
	 * @param Client $client
	 * @return string The booked entry ID
	 */
	public function book(Client $client): string {
		if ($this->gross_amount <= 0.0) {
			throw new Booking_Failed('Gross amount must be positive');
		}

		$payload = [
			'amount' => $this->gross_amount,
			'currency' => 'EUR',
		];

		try {
			$entry_id = $client->post('salesentry', $payload);
		} catch (Booking_Failed $e) {
			throw new Booking_Failed('Exact rejected the entry: ' . $e->getMessage());
		}

		return $entry_id;
	}
}
```

## File naming and tool setup

This file is `AGENTS.md` at the repository root — the cross-tool standard read by
Codex, Cursor, GitHub Copilot, and others. For tools that look for their own
filename, symlink rather than duplicating:

```bash
ln -s AGENTS.md CLAUDE.md
ln -s AGENTS.md .github/copilot-instructions.md
```

Treat this file as living documentation: update it in the same PR when commands,
structure, or conventions change.