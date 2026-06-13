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
- `package.json` / `package-lock.json` — Asset bundler scripts powered by `@wordpress/scripts`. Scripts: `build` (everyday app bundles), `build:vendor` (the shared DataViews bundle, run only on `@wordpress/dataviews` upgrades), `build:all` (both).
- `webpack.config.js` — Everyday Webpack config: compiles the app entries (Admin, Client Portal, Admin Bar, standalone tracker, Time Entries) and **externalizes** `@wordpress/dataviews` to the shared `window.ndiziDataViews` global, mapping it to the `ndizi-dataviews` script handle (via `requestToExternal`/`requestToHandle`) so it is kept out of every app rebuild.
- `webpack.vendor.js` — Separate config that **bundles** `@wordpress/dataviews` (the `/wp` entry point + its stylesheet) once into `build/vendor-dataviews.*`, exposed as `window.ndiziDataViews`. Core has no public `wp-dataviews` handle (see Gutenberg #63657), hence the local shared bundle. Run via `npm run build:vendor`.
- `webpack.shared.js` — Helpers shared by the two configs above: the `VENDOR_ARTIFACTS` list, `buildRules()` (silences Dart Sass legacy-API warnings; optionally flags the CSS rule with `sideEffects` so DataViews' `sideEffects:false` CSS is not tree-shaken), and `buildPlugins()` (swaps in the custom `DependencyExtractionWebpackPlugin` and a **scoped `CleanWebpackPlugin`** so the two builds do not delete each other's output — wp-scripts' default cleans the whole `build/` dir).
- `phpcs.xml` — Coding standard rules tuned for custom DB tables and WordPress standards.
- `includes/`
    - `class-ndizi-time-service.php` — Shared service/validation layer for all time-entry write operations. All four write entry points (REST, Admin AJAX, Admin Bar AJAX, Abilities, CLI) are thin adapters over three static methods: `start_timer()`, `stop_timer()`, and `log_time_manual()`. Each method runs `validate_time_project_access()` (project type/status/assignment + task ownership), the lock-date guard, and delegates to `Ndizi_DB`. `start_timer()` also auto-stops any already-running timer before inserting the new one; if the existing active timer started in a locked period it returns `WP_Error('active_timer_locked')` instead. All methods return the new entry ID / stopped row on success, or a `WP_Error` on failure.
    - `class-ndizi-admin.php` — Thin 26-line coordinator. `init()` calls `Ndizi_Settings::init()`, `Ndizi_Meta_Boxes::init()`, `Ndizi_List_Tables::init()`, `Ndizi_Ajax::init()`, and `Ndizi_Reports::init()` in sequence. `init_gantt()` proxies to `Ndizi_Settings::init_gantt()` and is called by the module registry for the `gantt` module. The full admin implementation lives in the five subclasses below.
    - `class-ndizi-settings.php` — Settings page (module toggles driven by `get_module_registry()`, icon selection, lock date, webhook URLs, Google OAuth connect button, Stripe API keys); admin asset enqueueing; `admin_menu` registration (priority 9); Reports submenu (delegates page render to `Ndizi_Reports`); the **Time Entries** submenu (`admin.php?page=ndizi-time-entries`) and its `render_time_entries_page()` DataViews app; user profile billing/salary rate fields; Google OAuth2 callback handler; and `init_gantt()` / `register_gantt_admin_page()`. `register_dataviews_bundle()` registers the shared `build/vendor-dataviews.js`/`.css` as the `ndizi-dataviews` script + style handles (reading deps/version from `build/vendor-dataviews.asset.php`); the Time Entries app script lists `ndizi-dataviews` as a dependency so WordPress loads the shared bundle first, and the matching style is enqueued on that page.
    - `class-ndizi-meta-boxes.php` — Meta box registration and rendering for all six CPTs (`ndizi_client`, `ndizi_project`, `ndizi_task`, `ndizi_invoice`, `ndizi_contact`, `ndizi_time_off`) and the `save_post` handler that persists all custom fields.
    - `class-ndizi-list-tables.php` — CPT list-table column additions (client, project, task, invoice), custom column rendering, and the `pre_get_posts` handler that restricts list queries for team-member users.
    - `class-ndizi-ajax.php` — All six `wp_ajax_*` handlers: `ajax_aggregate_invoice_time()`, `ajax_start_timer()`, `ajax_stop_timer()`, `ajax_delete_log()`, `ajax_check_active_timer()`, and `ajax_refresh_logs_table()`. Timer start/stop delegate to `Ndizi_Time_Service`.
    - `class-ndizi-reports.php` — `render_reports_page()` only: the full Reports dashboard with date/project/user filters, KPI cards, per-user breakdown table, profitability calculations, hierarchical billing-rate resolution, approval workflow, and CSV / QuickBooks CSV export buttons.
    - `class-ndizi-admin-bar.php` — Enqueues, registers, and renders the Admin Bar quick-timer widget. AJAX handlers for log-manual delegate to `Ndizi_Time_Service`. Detects timers running longer than 8 hours and injects an idle warning banner into both the admin bar panel and the standalone tracker page.
    - `class-ndizi-cli.php` — Registers the `wp ndizi time` WP-CLI command group with three subcommands: `start`, `stop`, and `status`. `start` and `stop` delegate to `Ndizi_Time_Service` (gaining project-access validation and the date-lock guard). Resolves `--project` and `--task` by exact post title via `$wpdb->get_var()` as well as by ID; resolves `--user` by login or ID.
    - `class-ndizi-calendar.php` — Loaded when the `calendar` module is active (previously always-loaded). Hooks into `save_post_ndizi_task`, `ndizi_timer_stopped`, `ndizi_time_logged`, `ndizi_time_entry_updated`, and `ndizi_time_entry_deleted` to push changes to Google Calendar. Uses the Google Calendar REST API via `wp_remote_*`. Credentials (`ndizi_google_client_id`, `ndizi_google_client_secret`) and tokens (`ndizi_google_refresh_token`, `ndizi_google_access_token`, `ndizi_google_token_expiry`) are stored in WordPress options. Access tokens are auto-refreshed on the fly; if no refresh token is stored the sync silently no-ops. The `GET /calendar/ical` REST route is registered via `register_calendar_routes()`, called dynamically from `register_active_rest_routes()` when this module is active.
    - `class-ndizi-cpts.php` — Declares all post types (`ndizi_client`, `ndizi_project`, `ndizi_task`, `ndizi_invoice`, `ndizi_contact`, `ndizi_time_off`) and registers their REST metadata schemas. The `ndizi_invoice` CPT is only registered when the `invoicing` module is active. The `ndizi_time_off` CPT stores client-submitted time-off/absence requests with meta `_ndizi_time_off_start_date`, `_ndizi_time_off_end_date`, `_ndizi_time_off_type`, `_ndizi_time_off_status`, and `_ndizi_time_off_client_id`.
    - `class-ndizi-db.php` — All CRUD operations on `wp_ndizi_time_entries`. Enforces the lock date (`ndizi_lock_date` option) and entry approval status on every write path. Fires named action hooks (`ndizi_timer_started`, `ndizi_timer_stopped`, `ndizi_time_logged`, `ndizi_time_entry_updated`, `ndizi_time_entry_deleted`) after each successful write. `get_time_entries()` and `get_time_totals()` accept `project_id`, `user_id`, `start_date`, `end_date`, and `approved` filter args. `get_time_entries()` whitelists `orderby` and, when ordering by `project_id`, appends `task_id` as a secondary sort key so the merged Project/Task column in the Time Entries screen groups by project then task. Approved entries (`approved = 1`) cannot be edited or deleted through any non-approval write path.
    - `class-ndizi-invoicing.php` — Loaded when the `invoicing` module is active (previously named `class-ndizi-integrations.php`; class renamed from `Ndizi_Integrations` to `Ndizi_Invoicing`). Renders the printable invoice HTML template; handles invoice CSV and JSON exports from the invoice editor; handles filtered time-report exports (standard CSV and QuickBooks-format CSV) from the Reports dashboard. Exposes the `ndizi_export_invoice_data` filter for third-party customization of the invoice export payload. Stripe payment routes (`POST /invoices/<id>/pay`) are registered via `register_invoicing_routes()` in `Ndizi_REST`, called dynamically when this module is active.
    - `class-ndizi-notifications.php` — Loaded when the `notifications` module is active. Sends `wp_mail()` emails on task assignment (new or changed assignee) and task status changes. Uses the `update_post_metadata` filter to capture previous meta values before writes so status-change emails have accurate before/after context.
    - `class-ndizi-portal.php` — Loaded when the `portal` module is active. Handles shortcode execution, passwordless client token auth (`?ndizi_token=...`), client session cookies, discussion boards (filtered WordPress comments), file attachment uploads, time-off/absence request form submission (creates `ndizi_time_off` CPT posts), and Stripe "Pay Online" button rendering for unpaid invoices when `ndizi_stripe_publishable_key` is set. Passes `rest_url` to all enqueued portal scripts.
    - `class-ndizi-rest.php` — Registers all `/wp-json/ndizi/v1` routes. Core routes (`GET /projects`, `GET /tasks`, `GET /time/active`, `POST /time/start`, `POST /time/stop`, `POST /time/log`, `GET /time`, `PUT /time/<id>`, `DELETE /time/<id>`) are always registered via `init()`. Write endpoints (`POST /time/start`, `POST /time/stop`, `POST /time/log`) delegate to `Ndizi_Time_Service` and map `WP_Error` codes to HTTP statuses (`invalid_project/invalid_task/date_locked` → 400, `db_error` → 500, others → 403). Module-conditional routes are split into `register_invoicing_routes()` (`POST /invoices/<id>/pay`, `POST /stripe/webhook`) and `register_calendar_routes()` (`GET /calendar/ical`), both called by `Ndizi_Project_Management::register_active_rest_routes()` only when their respective modules are active. Portal-related calls are guarded with `class_exists( 'Ndizi_Portal' )` before dispatch. Permission callbacks map to Ndizi capabilities; `POST /stripe/webhook` and `GET /calendar/ical` use `'__return_true'` intentionally.
    - `class-ndizi-roles.php` — Registers the `ndizi_manager` and `ndizi_team_member` roles and their custom capabilities on activation. Capabilities are removed on uninstall (not deactivation).
    - `class-ndizi-standalone-tracker.php` — Loaded when the `tracker` module is active. `init()` only hooks `register_page()` when `is_admin()` is true. Registers the `admin.php?page=ndizi-tracker-standalone` PWA tracker page. Page markup is loaded from `templates/standalone-tracker.php` (extracted from the former PHP heredoc); JS and SCSS live in `src/standalone/` and compile to `build/standalone.*`. Accepts a `?desc=` query parameter to pre-fill the description input. Requests browser notification permission on load and fires a push notification after the active timer exceeds 8 hours.
    - `class-ndizi-webhooks.php` — Loaded when the `integrations` module is active. Listens to `Ndizi_DB` action hooks and WordPress post/meta hooks to dispatch JSON payloads to `ndizi_webhook_url` and formatted messages to `ndizi_slack_webhook_url`. Uses the `update_post_metadata` filter to capture previous meta values (same pattern as notifications) so old/new values are accurate in event payloads. Guards all handlers on `$meta_key` before calling `get_post_type()` to minimize overhead on global meta hooks.
- `src/`
    - `shared/timer.js` — Shared timer utility module imported by `admin/index.js` and `adminbar/index.js`. Exports `formatTime()`, the ticking `setInterval` helper, and timer-state helpers, eliminating the copy-paste that previously existed between the two entry points.
    - `admin/` — Admin tracker controls (start/stop timer, manual log form), invoice amount calculation (respects the hierarchical billing rate resolution: task → user → project, using attribute-presence checks to allow explicit `0` rates), Gantt interactive scripts, and admin stylesheet modules. Imports from `../shared/timer.js`. Also `admin/time-entries.js` — the React app for the Time Entries screen, built on `@wordpress/dataviews` (v16 API: visible columns in `view.fields`, sorting in `view.sort`, filters declared per-field via `elements`/`filterBy`, per-column sizing/alignment in `view.layout.styles`). It imports `@wordpress/dataviews/wp`, which `webpack.config.js` externalizes to the shared `ndizi-dataviews` bundle. Data is fetched server-side through the core-data store (entity `ndizi`/`time-entry`). UI specifics: Project+Task render stacked in the `project_id` column (sortable — the server adds `task_id` as a secondary sort key, see `class-ndizi-db.php`); Start/End render stacked in the `start_time` "Date" column; `task_id`/`end_time` are therefore omitted from `view.fields`. The Description column wraps and is the only column left uncapped in `view.layout.styles` so it auto-expands (every other column has a `maxWidth`; Duration/Billable/Status are pinned narrow). `align: 'start'` is set explicitly on text columns because DataViews right-aligns `type: 'integer'` fields by default. Approve/Unapprove actions set `supportsBulk: true` (multi-select checkboxes, manager-only since they are `canManage`-gated); approval writes go through `apiFetch` PUT `/ndizi/v1/time/<id>` (not the core-data store) and then `invalidateResolution` to refresh.
    - `vendor/dataviews.js` — Source of the shared DataViews bundle: imports the DataViews stylesheet and re-exports `@wordpress/dataviews/wp`. Built **only** by `webpack.vendor.js` (`npm run build:vendor`) into `build/vendor-dataviews.*`; not part of the everyday build.
    - `adminbar/` — Admin bar panel JS (project/task selectors, timer controls, idle warning injection using `ndizi_adminbar.labels.idle_warning`, internal-client sort using the localized `internal_client` label) and SCSS (including pulse animation for the idle warning state). Imports from `../shared/timer.js`.
    - `standalone/` — JS and SCSS for the standalone PWA tracker page (`index.js`, `standalone-style.scss`). Compiled to `build/standalone.*` and enqueued by `Ndizi_Standalone_Tracker`. The HTML markup for this page lives in `templates/standalone-tracker.php`.
    - `portal/` — Tab controllers, attachment upload fields, and portal stylesheet modules.
    - `block/` — The `ndizi/client-portal` editor block (`block.json`, `index.js` edit UI, `render.php` dynamic frontend render) wrapping the portal shortcode.
- `templates/`
    - `standalone-tracker.php` — Full HTML template for the standalone PWA tracker page. Extracted from the former `Ndizi_Standalone_Tracker` PHP heredocs so it can be edited as a regular PHP/HTML file. PHP is limited to `esc_*` output and `wp_nonce_field()`; all behaviour lives in `src/standalone/index.js`.
- `build/` — Generated CSS/JS output from `@wordpress/scripts`, including `build/block/` (the copied `block.json` + `render.php` that `Ndizi_Portal::register_portal_block()` registers from), `build/standalone.*`, and the shared `build/vendor-dataviews.*` (JS + asset file + `style-vendor-dataviews.css`/`-rtl`). Committed to the repo so no build step is needed at install time. **Regenerate the app bundles with `npm run build` after any `src/` change; regenerate `vendor-dataviews.*` with `npm run build:vendor` only when `@wordpress/dataviews` is upgraded (or run `npm run build:all` for both).**
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
| `gantt` | `Ndizi_Admin::init_gantt()` → `Ndizi_Settings::init_gantt()` | Gantt chart views in the admin dashboard |
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
10. **`Ndizi_Time_Service` is the canonical write path for time entries**: All time-entry mutations (start, stop, manual log) must route through `Ndizi_Time_Service` rather than calling `Ndizi_DB` directly from an entry-point class. Business rules — project/task access validation, date-lock enforcement, auto-stop of an active timer — live in exactly one place. Entry-point classes (REST, AJAX, Abilities, CLI) are responsible only for parsing/validating their own input format and translating the returned `WP_Error` to the appropriate response type.

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

For active development, use the file watcher (app bundles only — it does not rebuild the vendor bundle):

```bash
npm run start
```

#### Shared DataViews vendor bundle (two-config split)

`@wordpress/dataviews` is built **once** into a shared bundle rather than re-bundled into every consumer, because WordPress core exposes no public `wp-dataviews` script handle (it ships DataViews only inside the editor packages — [Gutenberg #63657](https://github.com/WordPress/gutenberg/issues/63657)).

```bash
npm run build:vendor   # builds src/vendor/dataviews.js → build/vendor-dataviews.* (the ndizi-dataviews handle)
npm run build:all      # build:vendor + build, for a clean complete build
```

- `webpack.vendor.js` bundles DataViews and exposes it on `window.ndiziDataViews`; `webpack.config.js` externalizes `@wordpress/dataviews` to that global and maps it to the `ndizi-dataviews` handle, so app `.asset.php` files list it automatically.
- The two configs share helpers from `webpack.shared.js`, including a **scoped `CleanWebpackPlugin`**: wp-scripts' default wipes the entire `build/` dir before each build, so each config excludes the other's outputs (`VENDOR_ARTIFACTS`) from cleanup. Without this, an everyday `npm run build` would delete `vendor-dataviews.*` (and vice versa).
- **Run `npm run build:vendor` only when `@wordpress/dataviews` is upgraded.** The everyday `npm run build`/`npm start` cycle leaves the ~2 MB vendor bundle untouched.

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
