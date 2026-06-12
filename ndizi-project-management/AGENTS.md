# AGENTS.md

Guidance for AI coding agents working on the **Ndizi Project Management** plugin.

## Technical Overview

Ndizi Project Management is a native WordPress project management and time tracking system. It combines WordPress Custom Post Types (CPTs) for high-level relational metadata with a custom database table to handle high-frequency time logs without bloating standard WordPress core tables.

Features are delivered through independently toggleable modules controlled by the `ndizi_active_modules` option. When a module is inactive its PHP class is never loaded, so hooks, CPTs, and admin pages it would have registered simply do not exist.

### Codebase Directory Layout

- `Ndizi.php` — Main bootstrap file. Defines constants, wires activation/deactivation hooks, and runs an auto-upgrade check. Exposes `get_module_registry()`, a single source of truth that declares every module's `name`, `desc`, `includes` file path(s), `init` callable(s), and optional `rest_routes` callable. `get_active_modules()` derives its default list from the registry keys. A dynamic loop over the registry handles `require_once` and `::init()` for all active modules, replacing what was previously a chain of `if ( self::is_module_active(...) )` blocks. `register_active_rest_routes()` iterates the same registry to call each active module's REST-route registration method. The auto-upgrade path re-runs `Ndizi_DB::create_table()` and updates `ndizi_db_version` whenever it does not match `NDIZI_VERSION`.
- `uninstall.php` — Removes the custom `wp_ndizi_time_entries` table and the plugin's roles/caps on uninstall (deactivation no longer tears down roles).
- `API-AUTHENTICATION.md` — Developer reference for authenticating external tools against the REST API, including Application Passwords, client token auth, and `ndizi_token` query-parameter usage.
- `composer.json` / `composer.lock` — PHPCS quality controls and local WordPress standards configuration.
- `package.json` / `package-lock.json` — Asset bundler scripts powered by `@wordpress/scripts`.
- `webpack.config.js` — Webpack bundle settings compiling separate CSS and JS for Admin and Client Portal.
- `phpcs.xml` — Coding standard rules tuned for custom DB tables and WordPress standards.
- `includes/`
    - `class-ndizi-admin.php` — Admin metabox fields and saving for all CPTs; the Reports dashboard (date/project/user filters, KPI cards, per-user breakdown table, CSV and QuickBooks CSV export buttons); Settings page (module toggles — driven by `get_module_registry()` — icon selection, lock date, webhook URLs, Google OAuth connect button, Stripe API keys); Google OAuth2 authorization-code callback handler (exchanges `?code=` for a refresh token and stores it); and list-table column customizations. Also exposes `init_gantt()` and `register_gantt_admin_page()`, which are called by the module registry for the `gantt` module rather than being bootstrapped unconditionally.
    - `class-ndizi-admin-bar.php` — Enqueues, registers, and renders the Admin Bar quick-timer widget. Detects timers running longer than 8 hours and injects an idle warning banner into both the admin bar panel and the standalone tracker page.
    - `class-ndizi-cli.php` — Registers the `wp ndizi time` WP-CLI command group with three subcommands: `start`, `stop`, and `status`. Resolves `--project` and `--task` by exact post title via `$wpdb->get_var()` as well as by ID; resolves `--user` by login or ID.
    - `class-ndizi-calendar.php` — Loaded when the `calendar` module is active (previously always-loaded). Hooks into `save_post_ndizi_task`, `ndizi_timer_stopped`, `ndizi_time_logged`, `ndizi_time_entry_updated`, and `ndizi_time_entry_deleted` to push changes to Google Calendar. Uses the Google Calendar REST API via `wp_remote_*`. Credentials (`ndizi_google_client_id`, `ndizi_google_client_secret`) and tokens (`ndizi_google_refresh_token`, `ndizi_google_access_token`, `ndizi_google_token_expiry`) are stored in WordPress options. Access tokens are auto-refreshed on the fly; if no refresh token is stored the sync silently no-ops. The `GET /calendar/ical` REST route is registered via `register_calendar_routes()`, called dynamically from `register_active_rest_routes()` when this module is active.
    - `class-ndizi-cpts.php` — Declares all post types (`ndizi_client`, `ndizi_project`, `ndizi_task`, `ndizi_invoice`, `ndizi_contact`, `ndizi_time_off`) and registers their REST metadata schemas. The `ndizi_invoice` CPT is only registered when the `invoicing` module is active. The `ndizi_time_off` CPT stores client-submitted time-off/absence requests with meta `_ndizi_time_off_start_date`, `_ndizi_time_off_end_date`, `_ndizi_time_off_type`, `_ndizi_time_off_status`, and `_ndizi_time_off_client_id`.
    - `class-ndizi-db.php` — All CRUD operations on `wp_ndizi_time_entries`. Enforces the lock date (`ndizi_lock_date` option) and entry approval status on every write path. Fires named action hooks (`ndizi_timer_started`, `ndizi_timer_stopped`, `ndizi_time_logged`, `ndizi_time_entry_updated`, `ndizi_time_entry_deleted`) after each successful write. `get_time_entries()` and `get_time_totals()` accept `project_id`, `user_id`, `start_date`, `end_date`, and `approved` filter args. Approved entries (`approved = 1`) cannot be edited or deleted through any non-approval write path.
    - `class-ndizi-invoicing.php` — Loaded when the `invoicing` module is active (previously named `class-ndizi-integrations.php`; class renamed from `Ndizi_Integrations` to `Ndizi_Invoicing`). Renders the printable invoice HTML template; handles invoice CSV and JSON exports from the invoice editor; handles filtered time-report exports (standard CSV and QuickBooks-format CSV) from the Reports dashboard. Exposes the `ndizi_export_invoice_data` filter for third-party customization of the invoice export payload. Stripe payment routes (`POST /invoices/<id>/pay`) are registered via `register_invoicing_routes()` in `Ndizi_REST`, called dynamically when this module is active.
    - `class-ndizi-notifications.php` — Loaded when the `notifications` module is active. Sends `wp_mail()` emails on task assignment (new or changed assignee) and task status changes. Uses the `update_post_metadata` filter to capture previous meta values before writes so status-change emails have accurate before/after context.
    - `class-ndizi-portal.php` — Loaded when the `portal` module is active. Handles shortcode execution, passwordless client token auth (`?ndizi_token=...`), client session cookies, discussion boards (filtered WordPress comments), file attachment uploads, time-off/absence request form submission (creates `ndizi_time_off` CPT posts), and Stripe "Pay Online" button rendering for unpaid invoices when `ndizi_stripe_publishable_key` is set. Passes `rest_url` to all enqueued portal scripts.
    - `class-ndizi-rest.php` — Registers all `/wp-json/ndizi/v1` routes. Core routes (`GET /projects`, `GET /tasks`, `GET /time/active`, `POST /time/start`, `POST /time/stop`, `POST /time/log`, `GET /time`, `PUT /time/<id>`, `DELETE /time/<id>`) are always registered via `init()`. Module-conditional routes are split into `register_invoicing_routes()` (`POST /invoices/<id>/pay`, `POST /stripe/webhook`) and `register_calendar_routes()` (`GET /calendar/ical`), both called by `Ndizi_Project_Management::register_active_rest_routes()` only when their respective modules are active. Portal-related calls are guarded with `class_exists( 'Ndizi_Portal' )` before dispatch. Permission callbacks map to Ndizi capabilities; `POST /stripe/webhook` and `GET /calendar/ical` use `'__return_true'` intentionally.
    - `class-ndizi-roles.php` — Registers the `ndizi_manager` and `ndizi_team_member` roles and their custom capabilities on activation. Capabilities are removed on uninstall (not deactivation).
    - `class-ndizi-standalone-tracker.php` — Loaded when the `tracker` module is active. `init()` only hooks `register_page()` when `is_admin()` is true; the page-rendering logic lives in the separate `register_page()` method. Registers the `admin.php?page=ndizi-tracker-standalone` PWA tracker page: a distraction-free dark glassmorphic interface with a ticking clock, today's entry list, and delete controls. Accepts a `?desc=` query parameter to pre-fill the description input (useful when launched from the Chrome extension). Responsive CSS at ≤480 px. Requests browser notification permission on load and fires a push notification after the active timer exceeds 8 hours.
    - `class-ndizi-webhooks.php` — Loaded when the `integrations` module is active. Listens to `Ndizi_DB` action hooks and WordPress post/meta hooks to dispatch JSON payloads to `ndizi_webhook_url` and formatted messages to `ndizi_slack_webhook_url`. Uses the `update_post_metadata` filter to capture previous meta values (same pattern as notifications) so old/new values are accurate in event payloads. Guards all handlers on `$meta_key` before calling `get_post_type()` to minimize overhead on global meta hooks.
- `src/`
    - `admin/` — Admin tracker controls (start/stop timer, manual log form), invoice amount calculation (respects the hierarchical billing rate resolution: task → user → project, using attribute-presence checks to allow explicit `0` rates), Gantt interactive scripts, and admin stylesheet modules.
    - `adminbar/` — Admin bar panel JS (project/task selectors, timer controls, idle warning injection using `ndizi_adminbar.labels.idle_warning`, internal-client sort using the localized `internal_client` label) and SCSS (including pulse animation for the idle warning state).
    - `portal/` — Tab controllers, attachment upload fields, and portal stylesheet modules.
    - `block/` — The `ndizi/client-portal` editor block (`block.json`, `index.js` edit UI, `render.php` dynamic frontend render) wrapping the portal shortcode.
- `build/` — Generated CSS/JS output from `@wordpress/scripts`, including `build/block/` (the copied `block.json` + `render.php` that `Ndizi_Portal::register_portal_block()` registers from). Committed to the repo so no build step is needed at install time. **Regenerate with `npm run build` after any `src/` change.**
- `chrome-extension/` — A standalone Chrome extension (`manifest.json`, `popup.html`, `popup.js`) that connects to the site's REST API. Allows users to start/stop timers, browse projects and tasks, and open the standalone tracker (with `?desc=` pre-fill) from any browser tab. Excluded from the shipped plugin ZIP via `.distignore`.
- `playground/` — Dev-only WordPress Playground blueprint and `mock-data.php` seeder. Excluded from the shipped package via `.distignore`; see `playground/README.md`.
- `languages/` — Destination for `.po`/`.mo`/`.json` translation files; `Ndizi.php` calls `load_plugin_textdomain( 'ndizi-project-management', …, '…/languages' )` for non-WP.org installs.

### Module → Class Mapping

| Module slug | Class(es) loaded / method called | What it adds |
| :--- | :--- | :--- |
| `invoicing` | `Ndizi_Invoicing` | Invoice CPT, printable template, CSV/JSON invoice exports, report CSV exports, Stripe payment routes |
| `portal` | `Ndizi_Portal` | Client portal shortcode/block, token auth, discussion boards |
| `tracker` | `Ndizi_Admin_Bar`, `Ndizi_Standalone_Tracker` | Admin bar quick-timer, standalone PWA page |
| `notifications` | `Ndizi_Notifications` | Email notifications for assignment and status changes |
| `gantt` | `Ndizi_Admin::init_gantt()` | Gantt chart views in the admin dashboard |
| `integrations` | `Ndizi_Webhooks` | Outbound webhooks and Slack alerts |
| `calendar` | `Ndizi_Calendar` | Google Calendar sync, iCal REST feed |

All modules are **active by default** when `ndizi_active_modules` is not set. The default list is derived from `Ndizi_Project_Management::get_module_registry()` keys — add a new entry there to introduce a new module.

---

## Coding Conventions

1. **Procedural Static Classes**: All files are structured as procedural static classes (e.g. `Ndizi_DB`, `Ndizi_REST`) with `::init()` or static helpers, rather than instantiated namespaces. Do not introduce namespaces or class instantiation unless requested.
2. **Hook Bootstrapping**: All hooks are wired up during the `init` action. `Ndizi_Project_Management::bootstrap()` in `Ndizi.php` calls each component's `::init()` in order. Module classes are only loaded if their module is active.
3. **Escaping & Security**:
   - Sanitize all data before it reaches database functions.
   - All dynamic browser output must go through WordPress escaping (`esc_html`, `esc_attr`, `esc_url`). For i18n, use `esc_html__`, `esc_html_e`, `esc_attr__`, or `esc_attr_e`.
4. **Meta key guards on global hooks**: Handlers attached to `added_post_meta` and `updated_post_meta` must check `$meta_key` first — before calling `get_post_type()` — because these hooks fire on every meta operation site-wide.
5. **Previous meta value capture**: When a handler needs the value that existed before an update, hook into `update_post_metadata` (which fires pre-write) to cache it. Do not rely on `updated_post_meta` passing a previous value — WordPress does not supply one to that hook.
6. **Billing rate checks**: Hierarchical billing rate resolution uses `'' === $var` guards (not `! $var`) so an explicit `0.00` rate at a higher-priority tier does not fall through to a lower one.
7. **Approval-aware write paths**: In `Ndizi_DB`, when updating a time entry, determine whether the update is approval-only (only `approved`/`approved_by` keys) before applying lock-date or approval-status guards. Do not conflate the approval operation with other write paths.
8. **Open REST endpoints**: `POST /stripe/webhook` and `GET /calendar/ical` use `'permission_callback' => '__return_true'` intentionally — Stripe webhook payloads carry their own signature and the iCal feed is designed to be publicly subscribable. Do not add nonce checks to these routes.
9. **Module registry is the single source of truth**: When adding a new toggleable feature, add it to `get_module_registry()` in `Ndizi.php` (with `name`, `desc`, `includes`, `init`, and optionally `rest_routes` keys). Do not add a new `if ( self::is_module_active(...) )` block in `bootstrap()` or hardcode the module slug in `get_active_modules()`; the dynamic loops handle both automatically.

---

## Database Schema (`wp_ndizi_time_entries`)

To prevent post and postmeta inflation, time tracker logs use a dedicated SQL table:

```sql
CREATE TABLE wp_ndizi_time_entries (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  project_id bigint(20) NOT NULL,
  task_id bigint(20) DEFAULT 0,
  user_id bigint(20) NOT NULL,
  description text NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime DEFAULT NULL,
  duration int(11) DEFAULT 0,
  billable tinyint(1) DEFAULT 1,
  invoice_id bigint(20) DEFAULT 0,
  approved tinyint(1) NOT NULL DEFAULT 0,
  approved_by bigint(20) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id)
);
```

Schema changes are applied automatically on plugin init when `ndizi_db_version` does not match `NDIZI_VERSION` — `dbDelta()` handles adding new columns to existing tables.

All interactions with this table must go through the CRUD helper methods in `Ndizi_DB`. Direct `$wpdb` queries against this table outside of `class-ndizi-db.php` are not permitted.

### `Ndizi_DB::get_time_entries( $args )` filter args

| Arg | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `project_id` | int\|null | `null` | Filter to a specific project. |
| `task_id` | int\|null | `null` | Filter to a specific task. |
| `user_id` | int\|null | `null` | Filter to a specific user. |
| `invoice_id` | int\|null | `null` | Filter to entries linked to a specific invoice. |
| `billable` | bool\|null | `null` | Filter by billable flag. |
| `approved` | bool\|null | `null` | Filter by approval status. |
| `start_date` | string | `''` | ISO date string; returns entries with `start_time >= start_date 00:00:00`. |
| `end_date` | string | `''` | ISO date string; returns entries with `start_time <= end_date 23:59:59`. |
| `number` | int | `50` | Entries to return; `-1` for all. |
| `offset` | int | `0` | Pagination offset. |

`get_time_totals()` accepts the same `project_id`, `user_id`, `start_date`, and `end_date` args.

### Lock Date Enforcement

`Ndizi_DB::is_date_locked( $date_string )` compares a datetime string against the `ndizi_lock_date` option. If either `strtotime()` call returns `false` (invalid date string or no lock date set), the function returns `false` (not locked). This is called on every write path — `start_timer`, `stop_timer`, `log_time_manual`, `update_time_entry`, and `delete_time_entry` — so the lock is enforced regardless of the entry point (admin UI, REST API, or WP-CLI).

`update_time_entry` additionally distinguishes between approval-only updates (`approved` / `approved_by` fields) and substantive edits. Approval-only updates bypass the lock-date and approved-status guards; all other updates are blocked if the existing entry is already approved or falls in a locked period. `delete_time_entry` is blocked if the entry is approved.

---

## Custom Post Types & Meta Relations

CPTs handle relational entities. They are registered with `'show_in_rest' => true` to support standard REST editing:

- `ndizi_client` — Contains client metadata (`_ndizi_client_website`, `_ndizi_client_address`, `_ndizi_client_status`, `_ndizi_client_auth_key`).
- `ndizi_project` — Belongs to a client (`_ndizi_client_id`). Has `_ndizi_project_start_date`, `_ndizi_project_end_date`, `_ndizi_project_budget`, `_ndizi_project_status`, and `_ndizi_project_hourly_rate` (the project-level billing rate floor).
- `ndizi_task` — Linked to a project (`_ndizi_project_id`), assignable to a WP user (`_ndizi_assigned_user_id`), status (`_ndizi_task_status`: `open`/`in_progress`/`completed`/`cancelled`), priority (`_ndizi_task_priority`), `_ndizi_task_due_date`, and `_ndizi_task_hourly_rate` (overrides user/project rates when set).
- `ndizi_invoice` — Linked to a project (`_ndizi_project_id`), date (`_ndizi_invoice_date`), due date (`_ndizi_invoice_due_date`), total amount (`_ndizi_invoice_amount`), and status (`_ndizi_invoice_status`). Only registered when the `invoicing` module is active.
- `ndizi_contact` — Belongs to multiple clients via an array list (`_ndizi_associated_clients`), with phone (`_ndizi_contact_phone`), email (`_ndizi_contact_email`), and role details (`_ndizi_contact_role`).
- `ndizi_time_off` — Client-submitted absence/time-off requests created from the portal. Meta: `_ndizi_time_off_start_date`, `_ndizi_time_off_end_date`, `_ndizi_time_off_type` (`vacation`, `sick_leave`, `personal`, `other`), `_ndizi_time_off_status` (`pending`/`approved`/`denied`), `_ndizi_time_off_client_id`.

User profile meta:
- `_ndizi_user_billing_rate` — Billing hourly rate (floor: 0.00).
- `_ndizi_user_salary_rate` — Internal salary cost hourly rate (floor: 0.00).

---

## Build System & Quality Controls

### Asset Compilation

Asset compilation is powered by `@wordpress/scripts`. The config file `webpack.config.js` directs input from `src/` into compiled scripts/styles inside `build/`. **The `build/` directory is committed** so that a build step is not required at install time. After any change to `src/`, run:

```bash
npm run build
```

For active development, use the file watcher:

```bash
npm run start
```

### Formatting and Linting

Before checking in code changes, both linters must pass with **0 errors and 0 warnings**:

```bash
# Lint JavaScript, Sass, and Markdown assets
npm run lint

# Auto-format JS, SCSS, and Markdown
npm run format

# Lint PHP assets
composer run lint

# Auto-format PHP assets
composer run format
```

### PHPCS Ruleset Overrides (`phpcs.xml`)

The ruleset extends `WordPress` and runs against `Ndizi.php` and `includes/`. The
**`WordPress.Security` sniffs (escaping, nonce, sanitization) are intentionally left
enabled** — `vendor/bin/phpcs --standard=phpcs.xml` passes clean with them on, and
new code is expected to keep it that way. Where a security sniff is a genuine false
positive (e.g. an already-`esc_url()`'d value echoed inline), use a narrowly scoped
inline `// phpcs:ignore` with a reason rather than excluding the sniff globally.

Only the following are tuned, due to the custom table and CSV streaming:

- `WordPress.Files.FileName` — Excluded to support the main `Ndizi.php` bootstrap filename.
- `WordPress.PHP.YodaConditions` — Excluded for readable conditional structures.
- `WordPress.DB.DirectDatabaseQuery`, `WordPress.DB.PreparedSQL`,
  `WordPress.DB.PreparedSQLPlaceholders`, `WordPress.DB.SlowDBQuery` — Excluded because
  querying the custom `wp_ndizi_time_entries` table directly via `$wpdb` (including
  dynamic `IN()` placeholder lists) is inherent; individual queries still carry inline
  `phpcs:ignore` annotations where needed.
- `WordPress.WP.AlternativeFunctions` — Excluded to allow `fopen()`/`fputcsv()`/`fclose()`
  to `php://output` for browser CSV streaming.
- `Squiz.Commenting.FileComment` / `ClassComment` / `FunctionComment` / `InlineComment`
  — Severity set to `0` to avoid verbose doc-block warnings.
- `Universal.Operators.DisallowShortTernary` — Severity `0` to allow short ternaries.

Additionally, the ruleset registers the plugin's custom capabilities with
`WordPress.WP.Capabilities` (so capability checks like `ndizi_manage_time` aren't flagged
as typos) and sets `minimum_supported_wp_version` to `6.0`.
