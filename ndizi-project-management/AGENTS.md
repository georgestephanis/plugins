# AGENTS.md

Guidance for AI coding agents working on the **Ndizi Project Management** plugin.

## Technical Overview

Ndizi Project Management is a native WordPress project management and time tracking system. It combines WordPress Custom Post Types (CPTs) for high-level relational metadata with a custom database table to handle high-frequency time logs without bloating standard WordPress core tables.

### Codebase Directory Layout

- `Ndizi.php` — Main bootstrap file. Handles hooks setup, table upgrades, and component initializations.
- `composer.json` / `composer.lock` — PHPCS quality controls and local WordPress standards configuration.
- `package.json` / `package-lock.json` — Asset bundler scripts powered by `@wordpress/scripts`.
- `webpack.config.js` — Webpack bundle settings compiling separate CSS and JS for Admin and Client Portal.
- `phpcs.xml` — Coding standard rules tuned for custom DB tables and WordPress standards.
- `includes/`
    - `class-ndizi-admin.php` — Metabox layout fields, saving, admin report layouts, and Gantt charts.
    - `class-ndizi-cpts.php` — Declares all post types and registers REST metadata schemas.
    - `class-ndizi-db.php` — Coordinates table operations on the custom SQL logging table.
    - `class-ndizi-integrations.php` — Compiles printable HTML invoice formats and prints CSV/JSON exports.
    - `class-ndizi-notifications.php` — Coordinates `wp_mail()` triage alerts for new tasks.
    - `class-ndizi-portal.php` — Coordinates shortcode execution, URL authentication keys, client session security, and comments-discussion logs.
    - `class-ndizi-rest.php` — Handles API route registrations for external timekeepers.
    - `class-ndizi-roles.php` — Registers custom capabilities and workspace roles.
- `src/`
    - `admin/` — Admin tracker controls, Gantt interactive scripts, and admin stylesheet modules.
    - `portal/` — Tab controllers, attachment upload fields, and portal dark-mode stylesheet modules.
- `build/` — Generated CSS/JS output from `@wordpress/scripts`.

---

## Coding Conventions

1. **Procedural Static Classes**:
   Following the project monorepo structure, all files are structured as procedural static classes (e.g. `Ndizi_DB`, `Ndizi_REST`) with `::init()` or static helpers, rather than instantiated namespaces. Do not introduce namespaces or class instantiation unless requested.
2. **Hook Bootstrapping**:
   All hooks are wired up during the init sequence. Main loader classes boot static `::init()` hooks inside the main `Ndizi_Project_Management` class in `Ndizi.php`.
3. **Escaping & Security**:
   - Sanitization is required for all data before hitting database functions.
   - Any dynamic browser output must be ran through standard WordPress escaping (`esc_html`, `esc_attr`, `esc_url`). For internationalization, use `esc_html__`, `esc_html_e`, `esc_attr__`, or `esc_attr_e`.

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
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY (id)
);
```

All interactions with this table must go through the CRUD helper methods in `Ndizi_DB`.

---

## Custom Post Types & Meta Relations

CPTs handle relational entities. They are registered with `'show_in_rest' => true` to support standard REST editing:

- `ndizi_client` — Contains client metadata (`_ndizi_client_website`, `_ndizi_client_address`, `_ndizi_client_status`, `_ndizi_client_auth_key`).
- `ndizi_project` — Belongs to a client (`_ndizi_client_id`). Has `_ndizi_project_start_date`, `_ndizi_project_end_date`, `_ndizi_project_budget`, and `_ndizi_project_status`.
- `ndizi_task` — Linked to a project (`_ndizi_project_id`), assignable to a WP user (`_ndizi_assigned_user_id`), status (`_ndizi_task_status`: `open`/`in_progress`/`completed`/`cancelled`), priority (`_ndizi_task_priority`), and `_ndizi_task_due_date`.
- `ndizi_invoice` — Linked to a project (`_ndizi_project_id`), date (`_ndizi_invoice_date`), due date (`_ndizi_invoice_due_date`), total amount (`_ndizi_invoice_amount`), and status (`_ndizi_invoice_status`).
- `ndizi_contact` — Belongs to multiple clients via an array list (`_ndizi_associated_clients`), with phone (`_ndizi_contact_phone`), email (`_ndizi_contact_email`), and role details (`_ndizi_contact_role`).

---

## Build System & Quality Controls

### Asset Compilations

Asset compilation is powered by `@wordpress/scripts`. The config file `webpack.config.js` directs input from `src/` into compiled scripts/styles inside `build/`.

```bash
# Start development file watcher
npm run start

# Generate production bundles
npm run build
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

Due to the nature of custom table queries and the frontend client portal, specific sniffs have been tuned or ignored:

- `WordPress.Files.FileName` — Ignored to support the main `Ndizi.php` file format.
- `WordPress.PHP.YodaConditions` — Disabled for readable conditional structures.
- `WordPress.DB.DirectDatabaseQuery` / `WordPress.DB.PreparedSQL` — Ignored since querying the custom SQL table directly using `$wpdb` is standard.
- `WordPress.DB.PreparedSQLPlaceholders` — Excluded to support dynamic SQL preparation loops (e.g. `IN()` lists).
- `WordPress.WP.AlternativeFunctions` — Excluded to allow standard file operations (e.g. `fclose()`, `fputcsv()`) required for browser CSV streaming.
- `Squiz.Commenting` — Severities disabled to prevent bloated inline parameter/class/file doc block warnings.
- `Universal.Operators.DisallowShortTernary` — Excluded to allow clean inline ternary structures.
