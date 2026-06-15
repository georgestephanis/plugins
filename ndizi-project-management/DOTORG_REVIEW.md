# DOTORG_REVIEW.md

Durable record of the decisions made to get this plugin through the **WordPress.org Plugin
Check** and ready for directory submission. Consult this before re-investigating a Plugin
Check finding — most of the recurring ones are already triaged below with the reasoning.

This file is **dev-only** (listed in [`.distignore`](.distignore)); it does not ship in the
WordPress.org ZIP.

## The one thing to understand first

**Plugin Check does not read `phpcs.xml`.** It runs its own bundled standard. Two consequences:

1. Running `vendor/bin/phpcs --standard=phpcs.xml` can pass while Plugin Check still reports
   findings (and vice versa). The authoritative pre-submission check is **Plugin Check run
   against the built ZIP / working dir**, not local phpcs alone.
2. Plugin Check **does honor inline `// phpcs:ignore` and `// phpcs:disable/enable`**
   annotations. So inline annotations are how we clear Plugin Check; `phpcs.xml` only governs
   the local `phpcs` run.

Because of this, `phpcs.xml` is deliberately kept **aligned with Plugin Check** — it enables
the same security/DB sniffs Plugin Check enforces so local linting surfaces the same issues,
rather than blanket-excluding them (which previously let findings slip through unseen).

## Inline `phpcs:ignore` triage — what stays and why

These categories are intrinsic to the plugin's design and are **expected** to carry
documented inline ignores. Do **not** try to "fix" them by removing the annotation — each was
evaluated and is safe. New code touching the custom table should follow the same pattern:
narrowly scoped ignore + a `-- Reason:` explaining why it is safe.

| Sniff | Where | Why it is ignored (not a bug) |
|-------|-------|-------------------------------|
| `WordPress.DB.DirectDatabaseQuery.DirectQuery` / `NoCaching` | every `$wpdb` call on `wp_ndizi_time_entries` (`class-ndizi-db.php`, `-invoicing.php`, `-settings.php`, `-meta-boxes.php`, `-admin-bar.php`, `-ajax.php`, `-list-tables.php`, `-cli.php`) | The custom time-entries table has no `WP_Query`/core-API equivalent. Adding `wp_cache_*` would silence `NoCaching` but `DirectQuery` still fires, so the ignore survives regardless — net-zero for real effort and cache-invalidation complexity. |
| `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | `"… FROM $table_name …"` interpolations | The table name derives from `$wpdb->prefix` and **cannot** be a `%s`/`%d` placeholder. WP 6.2's `%i` identifier placeholder could replace it, but those lines also carry `DirectQuery`/`NoCaching` (which `%i` does not address), so the ignore line stays regardless — and it would cost a min-WP bump (6.0 → 6.2). Not worth it. |
| `WordPress.DB.PreparedSQL.NotPrepared` | dynamic-`$sql` read tails in `get_time_entries()`, `get_time_entries_count()`, `get_time_totals()` (`class-ndizi-db.php`), `class-ndizi-settings.php` | The query string is assembled from a dynamic `WHERE`/`LIMIT` built only from hardcoded clauses + prepared placeholders. phpcs can't statically prove a variable `$sql` is safe; inlining literals would mean duplicating each query per filter combination. |
| `WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare` | `… IN ($placeholders)` built with `array_fill( 0, count(...), '%d' )` | Canonical false positive for dynamic `IN()` lists. The per-id `%d` placeholders **are** prepared against the id array. |
| `WordPress.PHP.DevelopmentFunctions.error_log_error_log` | `class-ndizi-calendar.php`, `class-ndizi-webhooks.php` | Genuine operational logging (OAuth token-refresh failures, SSRF-guard blocks). Deleting loses diagnostics; `WP_DEBUG`-guarding does **not** satisfy the sniff. |

### Removed rather than ignored

- **`fopen()`/`fputcsv()` to `php://output`** for CSV streaming still need `fopen` (no
  stream-free alternative), covered by the `phpcs.xml` `AlternativeFunctions` exclude. Plugin
  Check does **not** flag `fopen`/`fputcsv`, only `fclose`.
- **`fclose()` was deleted, not ignored.** All three calls in `class-ndizi-invoicing.php`
  immediately preceded `exit;`, and `php://output` closes automatically when the request
  ends — so the `fclose()` was redundant. Removing it eliminated the only `AlternativeFunctions`
  finding Plugin Check raised. (Comment left in place so nobody re-adds it.)

### Pre-existing security ignores (separate audit)

`WordPress.Security.NonceVerification`, `EscapeOutput`, and `ValidatedSanitizedInput` ignores
predate this review. Most are legitimate read-only admin-display patterns (list-table
filters/sorting). Confirming each is truly safe is a **security audit**, not lint cleanup —
track that separately (see `REVIEW.md`).

## Packaging checks (`.distignore`)

Plugin Check scans the **whole working dir**, but `.distignore` only applies at build/deploy
time, so dev files show up as findings locally yet never ship. `.distignore` is kept
**complete** so what Plugin Check would flag in the ZIP is actually stripped:

- **Hidden files** (`hidden_files`): `.eslintignore`, `.gitignore`, `.distignore`,
  `languages/.gitkeep` are all excluded. Keep `languages/` + the `.pot`; drop the `.gitkeep`.
- **Root markdown** (`unexpected_markdown_file`): only standard files may sit in the plugin
  root of the ZIP. `AGENTS.md`, `API-AUTHENTICATION.md`, `TODO.md`, `README.md`, `REVIEW.md`,
  and **this file (`DOTORG_REVIEW.md`)** are excluded. **Any new root `*.md` must be added to
  `.distignore`** or it will trip Plugin Check.
- Build sources (`/src`, `/node_modules`, `webpack.*.js`, `package*.json`), PHP dev tooling
  (`/vendor`, `composer.*`, `phpcs.xml`), and `/.wordpress-org` (deployed to SVN `assets/`, not
  the ZIP) are excluded.

## WordPress Playground (two blueprints)

Live-preview tooling is split so the same `mock-data.php` serves both contexts:

- **`playground/blueprint.json`** — ships in the ZIP; installs from the **GitHub** monorepo
  (`git:directory`). Reflects the last pushed commit; use while developing.
- **`.wordpress-org/blueprints/blueprint.json`** — deploys to SVN `assets/blueprints/` (not the
  ZIP); installs from the **WordPress.org** directory (`wordpress.org/plugins` slug). This is
  what WordPress.org's **Live Preview** button reads, and what `readme.txt` links to
  (`https://ps.w.org/ndizi-project-management/assets/blueprints/blueprint.json`).

`mock-data.php` ships in the ZIP so both blueprints can `require` it from `WP_PLUGIN_DIR`. Its
console output is wrapped in `esc_html()` to satisfy `EscapeOutput` even though it only runs in
CLI/Playground contexts.

Caveats to verify at release: the `wordpress.org/plugins` slug and the `ps.w.org` asset URL
only resolve **after** the first approved version is published; and the directory's Live
Preview may auto-inject a plugin-install step — if it conflicts with the explicit one, drop the
first `installPlugin` step from the wp.org blueprint.

## Pre-submission checklist

1. `composer run lint` (local `phpcs`) → 0 errors / 0 warnings.
2. Run **Plugin Check** against the working dir or built ZIP — the authoritative gate.
3. Any new finding: decide *fix vs. documented inline ignore* using the triage above, and
   record genuinely new categories here.
4. New root `*.md` file? Add it to `.distignore`.
5. Bump `Stable tag` / version headers and `Tested up to` per the release process.
