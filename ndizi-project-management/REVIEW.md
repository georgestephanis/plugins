# Ndizi Project Management — Code Review

Review date: 2026-06-10 (original) · 2026-06-11 (PR #9 bot-review round, see addendum)
Reviewed version: `1.0.0-alpha` (plugin header) / `1.0.0-alpha` (readme `Stable tag`)
Scope: full plugin (`Ndizi.php`, `includes/*`, `playground/mock-data.php`, `readme.txt`)

This document catalogs issues found during review, grouped by severity. Each item lists
file/line references and a suggested remedy. Check items off as they are addressed.

---

## Resolution status (updated 2026-06-10)

A remediation pass has addressed most items. `vendor/bin/phpcs --standard=phpcs.xml`
now passes clean (exit 0) with the previously-excluded security sniffs re-enabled.

| # | Item | Status |
|---|------|--------|
| 1 | Destructive `create-mock-data.php` shipped | ✅ Fixed — moved to `playground/mock-data.php` and excluded from the package via `.distignore` |
| 2 | Text domain mismatch / not loaded | ✅ Fixed |
| 3 | Auth key exposed via REST | ✅ Fixed |
| 4 | Meta-box saves missing capability check | ✅ Fixed |
| 5 | Portal cookie not HttpOnly | ✅ Fixed |
| 6 | Token-gated frontend uploads | ✅ Fixed — MIME whitelist + 10 MB/file + max-5 limits (PR #9) |
| 7 | `restrict_posts_query` clobbers meta_query | ✅ Fixed |
| 8 | REST `get_projects` unscoped | ✅ Fixed |
| 9 | Header version/metadata inconsistencies | ✅ Fixed |
| 10 | No `uninstall.php`; roles removed on deactivate | ✅ Fixed |
| 11 | Missing `wp_unslash`/sanitization | ✅ Fixed |
| 12 | `is_main_query` guard | ✅ Verified OK (no change needed) |
| 13 | `posts_per_page => -1` counts | 🟨 Partial — dashboard counts now use `'fields' => 'ids'` (PR #9); column/Gantt/report/portal queries remain |
| 14 | Output escaping gaps | ✅ Fixed |
| 15 | Dead `_ndizi_client_wp_user_id` branch | ✅ Removed |
| 16 | Token comparison / plaintext key | ✅ Fixed (kept retrievable per owner; REST exposure removed, `hash_equals` used) |
| 17 | Comment author email = website URL | ✅ Fixed |
| — | Capability-vs-role checks (Low) | ✅ Fixed |
| — | Bonus bugs found: `$log->user_id !== $user_id` type mismatch (edit/delete/AJAX), `comment_time()` misuse | ✅ Fixed |

**Still open / intentionally deferred:**
- **#13** — the dashboard stat counts now use `'fields' => 'ids'`, but the same
  `posts_per_page => -1` pattern remains in list columns, the Gantt view, reports, and
  the portal; a broader performance pass (`'fields' => 'ids'` / `found_posts`) is still
  worthwhile on large sites.
- The remaining 🔵 **Low** polish items below remain as-is unless marked otherwise
  (CSV-injection hardening from that list was completed in PR #9).

---

## Legend

- 🔴 **Blocker** — security issue or WordPress.org guideline violation that should be fixed before release.
- 🟠 **High** — correctness/security weakness worth fixing soon.
- 🟡 **Medium** — best-practice / robustness / WPCS concern.
- 🔵 **Low** — polish, consistency, or nice-to-have.

---

## 🔴 Blockers

### 1. `create-mock-data.php` is shipped in the plugin and is destructive — ✅ RESOLVED
- **Resolution:** Relocated to [playground/mock-data.php](playground/mock-data.php) and excluded
  from the distributed package via [.distignore](.distignore). It now only runs as part of the
  WordPress Playground blueprint ([playground/blueprint.json](playground/blueprint.json)) against
  throwaway installs. Original finding below for history.
- File: `create-mock-data.php` (lines 10–24 run at file load).
- It is not wrapped in a function or a capability/nonce check — merely including the file
  **deletes every `ndizi_*` post (`post_status => any`, `force_delete = true`)** and then seeds
  data with hardcoded portal tokens (e.g. `'acme-token-123'` at line 40).
- Risks: accidental/triggered inclusion wipes all client/project/invoice data; hardcoded
  predictable auth keys; `echo` output; it is dev-only tooling that does not belong in a
  distributed plugin.
- **Remedy:** Remove this file from the distributed package. Keep it only in the dev repo,
  or convert it to a WP-CLI command guarded by `WP_CLI` and an explicit `--yes` flag. At
  minimum it must never run hardcoded tokens.

### 2. Text domain does not match the plugin slug and is never loaded
- All strings use the text domain `'ndizi'` (e.g. [includes/class-ndizi-cpts.php:29](includes/class-ndizi-cpts.php#L29)),
  but the plugin slug is `ndizi-project-management`. WordPress.org requires the text domain
  to equal the slug, and translations will not load otherwise.
- There is no `Text Domain:` header in [Ndizi.php](Ndizi.php#L2-L9) and no
  `load_plugin_textdomain()` call.
- **Remedy:** Rename the text domain to `ndizi-project-management` across all files, add the
  `Text Domain:` header, and (for older WP targets) call `load_plugin_textdomain()` on `init`.
  Since WP 4.6 wp.org-hosted plugins auto-load translations once the slug/domain match.

### 3. The client portal auth key (a passwordless credential) is exposed via REST
- Meta key `_ndizi_client_auth_key` is registered with `show_in_rest => true`
  ([includes/class-ndizi-cpts.php:188-196](includes/class-ndizi-cpts.php#L188-L196)).
- This key is the entire authentication secret for the client portal
  ([includes/class-ndizi-portal.php:368-387](includes/class-ndizi-portal.php#L368-L387)). Exposing
  it through the REST API (and in the admin list column at
  [includes/class-ndizi-admin.php:304-307](includes/class-ndizi-admin.php#L304-L307)) widens the
  attack surface for anyone who can read the client object.
- **Remedy:** Do not expose the auth key in REST (`show_in_rest => false`). Store a hash of the
  key rather than the plaintext, compare with `hash_equals()`, and only display the key once at
  generation time. Treat it like an application password.

---

## 🟠 High

### 4. Meta-box saves have no capability or per-post authorization check
- [includes/class-ndizi-admin.php:973-1089](includes/class-ndizi-admin.php#L973) verifies the
  nonce but never calls `current_user_can( 'edit_post', $post_id )`.
- A nonce proves intent/CSRF protection, not authorization. Combined with `save_post` firing for
  all post types, this is weaker than it should be.
- **Remedy:** At the top of `save_meta_boxes()`, bail unless
  `current_user_can( 'edit_post', $post_id )`. Also bail on `wp_is_post_revision()` /
  `wp_is_post_autosave()`.

### 5. Portal cookie stores the raw credential and is not HttpOnly
- [includes/class-ndizi-portal.php:399](includes/class-ndizi-portal.php#L399) and
  [:412](includes/class-ndizi-portal.php#L412) call `setcookie( 'ndizi_client_token', $token, …, is_ssl() )`
  — the 7th `httponly` argument is omitted, so it defaults to `false` and the credential is
  readable by JavaScript (XSS → credential theft).
- The cookie value is the plaintext token itself, persistent for 30 days.
- **Remedy:** Pass `httponly = true`. Prefer storing a hashed/opaque session identifier instead
  of the raw key. Consider the `samesite` attribute (use the array form of `setcookie()` on PHP 7.3+).

### 6. Frontend portal allows file uploads gated only by the client token — ✅ RESOLVED (PR #9)
- **Resolution:** The upload loop now enforces an explicit MIME whitelist (images, PDF,
  common office docs, txt/csv), a 10 MB per-file size cap, and a maximum of 5 files per
  submission, passing those limits to `media_handle_upload()` via the `mimes` override.
- Original finding below for history. The portal still auto-approves the associated comment
  (`comment_approved => 1`); revisit if that is not acceptable for your threat model.
- [includes/class-ndizi-portal.php](includes/class-ndizi-portal.php) calls
  `media_handle_upload()` for token-authenticated portal users.
- While WordPress restricts MIME types for users without `unfiltered_upload`, this still lets any
  holder of a client token write to the Media Library, and comments are auto-approved
  (`comment_approved => 1`).
- **Remedy:** Restrict allowed file types explicitly, cap file size/count, consider not
  auto-approving, and confirm uploads are acceptable for your threat model. Validate
  `$_FILES` structure defensively.

### 7. `restrict_posts_query()` clobbers any existing meta_query
- [includes/class-ndizi-admin.php:51-71](includes/class-ndizi-admin.php#L51) overwrites
  `meta_query` wholesale for team members instead of merging, which can break other plugins'
  list filtering and the built-in status/date filters.
- The same restriction logic is duplicated (and could drift) between this method and the REST
  `get_tasks()` at [includes/class-ndizi-rest.php:253-263](includes/class-ndizi-rest.php#L253).
- **Remedy:** Merge into the existing `meta_query` (preserve `relation`), and centralize the
  "tasks visible to current user" logic in one helper.

### 8. REST read endpoints leak all projects/tasks regardless of ownership
- `get_projects()` ([includes/class-ndizi-rest.php:196](includes/class-ndizi-rest.php#L196)) returns
  **every** active project to any user with `ndizi_view_projects`; there is no per-user scoping,
  unlike `get_tasks()`.
- **Remedy:** Decide intended visibility. If team members should only see their own
  projects/tasks, scope the query the same way `get_tasks()` does; otherwise document that
  `ndizi_view_projects` is an org-wide read capability.

### 9. Version / metadata inconsistencies in the plugin header
- [Ndizi.php:8](Ndizi.php#L8) declares `Version: 1.0` and `NDIZI_VERSION = '1.0'`
  ([:18](Ndizi.php#L18)), but `readme.txt` `Stable tag` is `1.0.0`. WordPress.org compares these.
- The header is missing `License`, `License URI`, `Text Domain`, `Requires at least`, and
  `Requires PHP`. `Plugin URI` uses the deprecated `wordpress.org/extend/plugins/...` form.
- The main file is named `Ndizi.php` (capitalized) rather than the conventional
  `ndizi-project-management.php`.
- **Remedy:** Align versions, add the missing headers, update the Plugin URI, and rename the
  bootstrap file to match the slug.

---

## 🟡 Medium

### 10. No `uninstall.php` — orphaned data on delete
- Activation creates the `wp_ndizi_time_entries` table ([includes/class-ndizi-db.php:23-50](includes/class-ndizi-db.php#L23))
  and a large amount of postmeta/CPT content, but nothing removes the custom table, options, or
  postmeta on uninstall.
- Conversely, **roles/caps are removed on _deactivation_**
  ([Ndizi.php:75-83](Ndizi.php#L75)), which is the wrong lifecycle hook — a user temporarily
  deactivating loses role configuration.
- **Remedy:** Move destructive cleanup (drop table optionally, remove roles/caps) into
  `uninstall.php`. Deactivation should generally only flush rewrite rules.

### 11. Nonce/`$_POST` reads lack `wp_unslash()` and sanitization
- e.g. [includes/class-ndizi-admin.php:980](includes/class-ndizi-admin.php#L980):
  `wp_verify_nonce( $_POST['ndizi_client_nonce'], … )` — should be
  `wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_client_nonce'] ) ), … )`.
- `$_GET['page']` at [:84](includes/class-ndizi-admin.php#L84), and many `$_POST`/`$_GET`/`$_COOKIE`
  reads in the portal, are used without `wp_unslash()`. WPCS (`WordPress.Security`) flags these.
- **Remedy:** Wrap all superglobal reads in `wp_unslash()` + an appropriate `sanitize_*` before use.

### 12. `is_main_query()` checks can run on non-`WP_Query` objects
- [includes/class-ndizi-admin.php:52](includes/class-ndizi-admin.php#L52) is fine, but verify all
  `pre_get_posts` callers guard against admin sub-queries. Currently OK because it checks the
  `post_type`, but `get_posts()` calls within columns can recurse — confirm no infinite loops.

### 13. Heavy `posts_per_page => -1` queries throughout
- Dashboard stat counts ([includes/class-ndizi-admin.php:147-193](includes/class-ndizi-admin.php#L147))
  load **all** posts just to `count()` them; the same pattern appears in columns, Gantt, reports,
  and the portal.
- **Remedy:** Use `'fields' => 'ids'` + `count()`, or `WP_Query` with `'posts_per_page' => 1` and
  read `found_posts`, or `wp_count_posts()` / direct counts. This matters on large datasets.

### 14. Output escaping gaps for i18n echoes
- Many labels use `_e()` / `__()` echoed directly (e.g. meta-box `<label>`s at
  [includes/class-ndizi-admin.php:501-522](includes/class-ndizi-admin.php#L501)) instead of
  `esc_html_e()`. Several emails/print views interpolate values that are escaped, which is good,
  but the admin pages contain large inline-styled blocks where consistency matters.
- The reports/dashboard pages emit substantial inline `style="..."` and inline `onmouseover`
  handlers ([includes/class-ndizi-admin.php:229-244](includes/class-ndizi-admin.php#L229)). This
  is allowed but discouraged; it complicates CSP and review.
- **Remedy:** Prefer `esc_html_e()`/`esc_attr_e()`, and move inline styles/handlers into the
  enqueued `build/admin.css` / `build/admin.js`.

### 15. Incomplete / dead feature: `_ndizi_client_wp_user_id`
- `get_authenticated_client_id()` queries `_ndizi_client_wp_user_id`
  ([includes/class-ndizi-portal.php:351](includes/class-ndizi-portal.php#L351)), but no code ever
  writes this meta. The logged-in-user fallback can never match.
- **Remedy:** Either implement linking a WP user to a client (and add a meta box field) or remove
  the dead branch.

### 16. Token lookup is not constant-time and matches on plaintext meta
- `get_client_id_by_token()` ([includes/class-ndizi-portal.php:368](includes/class-ndizi-portal.php#L368))
  does a `meta_query` equality match on the raw token. Combined with item #3, the token is a
  long-lived plaintext secret.
- **Remedy:** Store a hash, look up by hash, and `hash_equals()` compare (as the print handler
  already does at [includes/class-ndizi-integrations.php:69](includes/class-ndizi-integrations.php#L69)).

### 17. Comment author email fallback uses the client website meta
- [includes/class-ndizi-portal.php:501](includes/class-ndizi-portal.php#L501) uses the client
  website URL as the comment author email (falling back to `client@portal.local`). A URL is not
  an email; this pollutes comment data and may fail validation.
- **Remedy:** Add a dedicated client email field or omit the email.

---

## 🔵 Low / polish

- **Bootstrap on `init`:** CPTs are registered on `init` via `bootstrap()`
  ([Ndizi.php:88-108](Ndizi.php#L88)) — good. But `Ndizi_Portal::init()` calls
  `handle_portal_actions()` immediately at `init` (per the portal `init`), meaning `setcookie()`
  and `wp_safe_redirect()` run during `init`. That works, but consider hooking portal form
  handling to `template_redirect` to ensure headers aren't already sent and conditional tags work.
- **`current_user_can( 'administrator' )` / `current_user_can( 'ndizi_manager' )`:** checking a
  *role name* via `current_user_can()` is unreliable (e.g.
  [includes/class-ndizi-rest.php:254](includes/class-ndizi-rest.php#L254),
  [includes/class-ndizi-admin.php:59](includes/class-ndizi-admin.php#L59)). Check a *capability*
  instead (e.g. `ndizi_manage_tasks` or `manage_options`). The `Ndizi_Roles::current_user_can()`
  helper exists but is unused.
- **`Tested up to: 6.5`** in [readme.txt:5](readme.txt#L5) is stale; bump after testing on current WP.
- **Dashboard `$wpdb->get_var( "SELECT SUM(duration) FROM $table_name" )`**
  ([includes/class-ndizi-admin.php:176](includes/class-ndizi-admin.php#L176)) is safe (table name
  from prefix) but uncached; consider a transient for the dashboard KPIs.
- **`number_format()` without locale** for currency throughout (e.g.
  [includes/class-ndizi-admin.php:350](includes/class-ndizi-admin.php#L350)); consider
  `number_format_i18n()` and a configurable currency symbol (currently hardcoded `$`).
- **`fputcsv` raw output** — ✅ Fixed (PR #9): `Ndizi_Integrations::escape_csv_field()` now
  prefixes a single quote to any cell starting with `=`, `+`, `-`, `@`, tab, or CR before it is
  written, neutralizing spreadsheet formula injection.
- **Mixed line-item source of truth:** invoice `amount` is a free-form meta that can drift from
  the linked time entries; the print/export shows logged hours next to a manual total. Document
  this intentional decoupling for users.
- **No automated tests** and no `Requires Plugins`/dependency declarations; consider basic
  PHPUnit coverage for `Ndizi_DB` duration math and the invoice relinking logic
  ([includes/class-ndizi-admin.php:1051-1072](includes/class-ndizi-admin.php#L1051)).

---

## Positives (worth keeping)

- Custom table queries consistently use `$wpdb->prepare()` with whitelisted `ORDER BY`/`GROUP BY`
  columns ([includes/class-ndizi-db.php:332-348](includes/class-ndizi-db.php#L332)) — no SQL
  injection found in the DB layer.
- REST routes declare `args` with `sanitize_callback`s and per-object ownership checks on
  edit/delete ([includes/class-ndizi-rest.php:422-485](includes/class-ndizi-rest.php#L422)).
- Invoice print authorization correctly uses `hash_equals()` for the token path
  ([includes/class-ndizi-integrations.php:69](includes/class-ndizi-integrations.php#L69)).
- Export and AJAX handlers check nonces and capabilities before acting.
- Output in user-facing tables is escaped (`esc_html`, `esc_attr`, `esc_url`) consistently.

---

## Suggested priority order

1. Remove `create-mock-data.php` from the package (#1).
2. Fix the text domain + headers (#2, #9) — required for wp.org.
3. Stop exposing/storing the portal key in the clear; hash it; HttpOnly cookie (#3, #5, #16).
4. Add capability checks to meta-box saves (#4).
5. Add `uninstall.php` and move role cleanup off deactivation (#10).
6. Address query merging (#7), REST scoping (#8), and the remaining WPCS/i18n items.

---

## Addendum — 2026-06-11 PR #9 bot-review round

The full plugin rebuild was opened as [PR #9](https://github.com/georgestephanis/plugins/pull/9)
and reviewed by Gemini Code Assist and GitHub Copilot. All findings were resolved:

| Source | Finding | Resolution |
|--------|---------|------------|
| Gemini | `save_meta_boxes` saved metadata without verifying the post type | Each save block now guards on `get_post_type( $post_id )` so a nonce from one CPT can't write to another |
| Gemini | Portal uploads unrestricted (item #6 above) | MIME whitelist + 10 MB/file + max-5 limits via `media_handle_upload()` overrides |
| Gemini | Invoice relinking ran one `UPDATE` per entry | Single bulk `UPDATE … WHERE id IN (…)` query |
| Gemini | Dashboard counts hydrated full post objects (item #13) | `'fields' => 'ids'` on the count queries |
| Gemini | Print-invoice `href` contained embedded whitespace/newlines | URL built into a variable and echoed inline (also keeps WPCS array formatting) |
| Gemini | Shortcode didn't enqueue portal assets | `render_portal_shortcode()` registers + enqueues the portal style/script directly |
| Gemini | CSV export vulnerable to formula injection | `escape_csv_field()` prefixes `'` to cells starting with `= + - @` / tab / CR |
| Gemini | `/time/<id>` REST routes lacked `args`/`sanitize_callback` | EDITABLE and DELETABLE routes now register sanitized args |
| Copilot | `$wpdb->insert()` format array passed `null` for `end_time` | Uses `'%s'`; SQL `NULL` still inserted via the data array |
| Copilot | Manual time derivation mixed `current_time()` with `gmdate()` | Single site-local timestamp basis formatted with `gmdate()` |
| Copilot | `DROP TABLE` table name not quoted | Backtick-quoted in `uninstall.php` |
| Copilot | Client auth key generated with `Math.random()` | Uses `window.crypto.getRandomValues()` |
| Copilot | Portal AJAX relied on `wp.ajax` (needs `wp-util`/`ajaxurl`) | Uses jQuery against the localized `ndizi_portal.ajax_url`, handling the `wp_send_json_*` envelope |
| Copilot | Block JS/`block.json` used text domain `ndizi` | Uses the declared `ndizi-project-management` throughout |

Also fixed while addressing the above: the generated `build/block/` metadata
(`block.json` + `render.php`), which `register_portal_block()` registers from, was missing
from the tree and is now committed.
