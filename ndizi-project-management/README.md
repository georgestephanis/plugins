# Ndizi Project Management

Ndizi Project Management is a native WordPress plugin for freelancers and small agencies, providing a complete system to track clients, projects, tasks, time, and invoices. It is designed to combine WordPress's relational capability (Custom Post Types) with high-efficiency custom database structures, resulting in a scalable project tracker that won't bloat your database.

## 🚀 Try it live

Spin up a disposable WordPress with Ndizi PM installed and seeded with demo data — no install required:

**[▶ Launch in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/ndizi-project-management/playground/blueprint.json)**

The [blueprint](playground/blueprint.json) installs only this plugin from the monorepo via the `git:directory` resource and seeds sample clients, projects, tasks, invoices, and time entries. See [playground/README.md](playground/README.md) for details and local-iteration commands.

---

## Key Features

### 💎 Premium Responsive Design

- **Admin Dashboards**: Gorgeous, CSS-only reports and responsive visualizations representing team productivity, billable time distributions, and project health.
- **Client Portal**: A premium, glassmorphic client-facing interface utilizing CSS transitions, visual progress bars, and responsive layouts. Embed it with the `[ndizi_client_portal]` shortcode or the **Ndizi Client Portal** block (`ndizi/client-portal`).
- **Gantt Charts**: A custom CSS Grid and SVG-based Gantt chart system to render project timelines without loading bloated, slow third-party chart libraries.
- **Standalone PWA Companion App**: A distraction-free, installable utility page (`admin.php?page=ndizi-tracker-standalone`) that runs without the WordPress admin menu or admin bar. Built with a dark glassmorphic interface, a ticking digital clock, and live lists to review or delete today's entries. Fully responsive at small-screen sizes. Supports a `?desc=` URL parameter to pre-fill the description field (used by the Chrome extension). Requests browser notification permission on load and fires a push notification when the active timer exceeds 8 hours.
- **Chrome Extension**: A companion browser extension (`chrome-extension/`) that connects to the site's REST API and lets users start/stop timers, browse projects and tasks, and open the standalone tracker with a pre-filled description — from any browser tab.

### 📅 Relational Data Model

- **Clients**: Top-level containers that can hold multiple projects. Supports secret auth key verification for seamless logins.
- **Projects**: Central workspace containers enclosing tasks, time entries, messages, and project-specific invoices.
- **Tasks**: Granular tickets assignable to team members, complete with status tracking, priority flags, and due dates.
- **Contacts**: Stakeholder directories where individual contacts can be linked to multiple clients.

### ⏱️ Time Tracking

- Decouples raw, high-frequency time logs from post/postmeta storage into a custom database table (`wp_ndizi_time_entries`).
- **Nested Project Groupings**: Auto-groups projects under their client names (`<optgroup>`) in project selection lists for simplified, intuitive categorization.
- **Gated Timer and Manual Modes**: Prevents input conflicts by deactivating and hiding the active timer option when manual duration entry is open.
- **Customizable Tracker Icons**: Exposes a Settings page (`admin.php?page=ndizi-settings`) allowing managers to select preferred tracking icons (Banana, Clock, Punch Clock, Hourglass) with live-updating SVG renderings.
- **Idle Warning Banner**: When an active timer has been running for more than 8 hours, a warning banner appears in the admin bar panel and on the standalone tracker page prompting the user to verify their logged time.
- **Lock Date / Time Entry Locking**: A configurable lock date prevents creating, editing, or deleting time entries on or before that date — protecting finalized billing periods from accidental modification. Enforced across the REST API, the DB layer, and the admin UI.
- **Approval Workflow**: Time entries carry `approved` and `approved_by` fields. Once an entry is marked approved, substantive edits and deletion are blocked — only the approval fields themselves can be updated. Approval-only updates bypass the lock-date guard.
- **WP-CLI Integration**: Manage timers from the terminal using `wp ndizi time start`, `wp ndizi time stop`, and `wp ndizi time status`. Accepts `--project`, `--task`, `--user`, `--description`, and `--billable` flags; resolves names as well as IDs.

### 💰 Hierarchical Billing Rates

Billing rates are resolved in priority order at invoice and report generation time:

1. **Task-level override** (`_ndizi_task_hourly_rate`) — set on a per-task basis.
2. **User default rate** (`_ndizi_user_billing_rate`) — the assigned team member's profile rate.
3. **Project default rate** (`_ndizi_project_hourly_rate`) — the project-wide fallback rate.

An explicit rate of `0.00` at any level is honored (pro-bono entries), rather than falling through to the next tier. User salary costs (`_ndizi_user_salary_rate`) are separately tracked for internal profitability/margin reports.

### 🧾 Invoice Engine & Exports

- **Printable Invoices**: Professional, clean print-friendly template layouts that automatically hide interactive controls when printed or saved to PDF.
- **Stripe Online Payment**: When `ndizi_stripe_publishable_key` and `ndizi_stripe_secret_key` are configured in Settings, a "Pay Online" button appears on unpaid invoices in the client portal. Clicking it calls `POST /wp-json/ndizi/v1/invoices/<id>/pay` to create a Stripe Checkout session. A `POST /wp-json/ndizi/v1/stripe/webhook` endpoint listens for `checkout.session.completed` events and marks the invoice paid automatically.
- **Invoice Exports**: Export individual invoice line items as CSV or JSON directly from the invoice editor screen.
- **Time Report Exports**: From the Reports dashboard, export filtered time entries (by project, team member, and date range) as a standard CSV or as a **QuickBooks-compatible CSV** (`Customer`, `Item`, `Date`, `Hours`, `Rate`, `Description` columns) for direct import into accounting software.
- **Extensible Export Data**: The `ndizi_export_invoice_data` filter lets third-party plugins customize the invoice export dataset before it is serialized.

### 📊 Reports Dashboard

- Date-range filtering (start date / end date) via bookmarkable URL parameters.
- Filterable by project and by team member.
- KPI summary cards: total hours, billable hours, non-billable hours, billable revenue, estimated salary costs, and profit margin.
- Per-user breakdown table of hours and billing totals.
- Direct export buttons for standard CSV and QuickBooks CSV — active filters carry through to the export.

### 🔔 Notifications & Webhooks

- **Email Notifications**: Sends assignment emails when a task is assigned (or reassigned) to a team member, and status-change emails when a task's status is updated.
- **Google Calendar Sync**: When Google OAuth credentials are configured in Settings (`ndizi_google_client_id` / `ndizi_google_client_secret`), tasks with due dates are automatically created/updated/deleted in Google Calendar. Completed time entries (stopped, manually logged, or updated) are also synced. Access tokens are refreshed automatically using the stored refresh token. Provides a `GET /wp-json/ndizi/v1/calendar/ical` endpoint for a public iCal subscription feed.
- **Outbound Webhooks**: Posts a JSON event payload to a configurable endpoint URL on timer start/stop, manual time log, time entry CRUD, CPT status transitions, and task/invoice metadata changes.
- **Slack Integration**: Sends formatted alert messages to a Slack incoming webhook URL on the same events.

### ⚙️ Modular Architecture

Features are independently toggleable from the Settings page (`admin.php?page=ndizi-settings`) using the `ndizi_active_modules` option. Disabled modules are not loaded — their PHP classes, hooks, and CPTs are not registered — reducing overhead on sites that don't need every feature.

| Module slug | What it controls |
| :--- | :--- |
| `invoicing` | Invoice CPT, printable invoice template, invoice CSV/JSON exports, QuickBooks report CSV |
| `portal` | Client Portal shortcode/block, passwordless token auth, discussion boards |
| `tracker` | Admin bar quick-timer, standalone PWA tracker page |
| `notifications` | Email notifications for task assignment and status changes |
| `gantt` | Gantt chart timeline views in the admin |
| `integrations` | Outbound webhooks and Slack alerts |

All modules default to **active** on a fresh install; no configuration required to use the full feature set.

---

## Technical Architecture

### 1. Database Schema (`wp_ndizi_time_entries`)

Transactional logs are recorded in a dedicated table to prevent `wp_posts` database bloat:

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `bigint(20)` | Primary Key, Auto-increment. |
| `project_id` | `bigint(20)` | Target project ID. |
| `task_id` | `bigint(20)` | Linked task ID (0 if logged to project level). |
| `user_id` | `bigint(20)` | WP User ID of the logger. |
| `description` | `text` | Work log description. |
| `start_time` | `datetime` | Timer start time. |
| `end_time` | `datetime` | Timer end time (NULL if running). |
| `duration` | `int(11)` | Tracked duration in seconds. |
| `billable` | `tinyint(1)` | `1` if billable, `0` if non-billable. |
| `invoice_id` | `bigint(20)` | Associated Invoice ID (0 if un-invoiced). |
| `approved` | `tinyint(1)` | `1` if approved by a manager, `0` if pending. |
| `approved_by` | `bigint(20)` | WP User ID of the approver (0 if unapproved). |
| `created_at` | `datetime` | Timestamp of log creation. |
| `updated_at` | `datetime` | Timestamp of last modification. |

### 2. Custom Post Type Metadata Keys

#### Client (`ndizi_client`)

- `_ndizi_client_website` (string) — Website URL.
- `_ndizi_client_address` (string) — Billing Address.
- `_ndizi_client_auth_key` (string) — Unique secret authentication token.
- `_ndizi_client_status` (string) — Client status (`active`, `archived`).

#### Project (`ndizi_project`)

- `_ndizi_client_id` (integer) — Client ID.
- `_ndizi_project_start_date` (string) — Target start date.
- `_ndizi_project_end_date` (string) — Target end date.
- `_ndizi_project_budget` (number) — Budget amount.
- `_ndizi_project_status` (string) — Status (`active`, `archived`).
- `_ndizi_project_hourly_rate` (number) — Default project hourly billing rate (floor: 0.00).

#### Task (`ndizi_task`)

- `_ndizi_project_id` (integer) — Project ID.
- `_ndizi_assigned_user_id` (integer) — WordPress User ID.
- `_ndizi_task_status` (string) — Status (`open`, `in_progress`, `completed`, `cancelled`).
- `_ndizi_task_priority` (string) — Priority (`low`, `medium`, `high`).
- `_ndizi_task_due_date` (string) — Task due date.
- `_ndizi_task_hourly_rate` (number) — Override task hourly billing rate (floor: 0.00).

#### Invoice (`ndizi_invoice`)

- `_ndizi_project_id` (integer) — Project ID.
- `_ndizi_invoice_date` (string) — Invoice date.
- `_ndizi_invoice_due_date` (string) — Invoice due date.
- `_ndizi_invoice_amount` (number) — Calculated invoice total.
- `_ndizi_invoice_status` (string) — Status (`draft`, `sent`, `paid`, `void`).

#### Contact (`ndizi_contact`)

- `_ndizi_contact_email` (string) — Email address.
- `_ndizi_contact_phone` (string) — Phone number.
- `_ndizi_contact_role` (string) — Role (e.g. "Primary Contact", "Billing").
- `_ndizi_associated_clients` (array of integers) — List of client IDs.

#### User Profile Meta Keys

- `_ndizi_user_billing_rate` (number) — User default billing hourly rate (floor: 0.00).
- `_ndizi_user_salary_rate` (number) — User internal salary cost hourly rate (floor: 0.00).

### 3. WordPress Database Options

Options configured in the settings dashboard:

- `ndizi_active_modules` (array of strings) — List of active module slugs (`invoicing`, `portal`, `tracker`, `notifications`, `gantt`, `integrations`). Defaults to all modules active when not set.
- `ndizi_adminbar_icon` (string) — Admin bar quick-timer icon (`banana`, `clock`, `punch_clock`, `hourglass`).
- `ndizi_lock_date` (string) — Date string; time entries on or before this date are locked and cannot be created, edited, or deleted.
- `ndizi_webhook_url` (string) — Endpoint URL for outbound event payload POST requests.
- `ndizi_slack_webhook_url` (string) — Target endpoint for formatted Slack incoming webhook alerts.
- `ndizi_google_client_id` / `ndizi_google_client_secret` (string) — Google OAuth2 application credentials for Calendar sync.
- `ndizi_google_refresh_token` / `ndizi_google_access_token` / `ndizi_google_token_expiry` (string/int) — Managed automatically after the OAuth flow completes; do not edit manually.
- `ndizi_stripe_publishable_key` / `ndizi_stripe_secret_key` (string) — Stripe API keys enabling the "Pay Online" button and Checkout session creation.
- `ndizi_db_version` (string) — Tracks the installed DB schema version; updated to `NDIZI_VERSION` after each `dbDelta()` run.

### 4. Custom REST API Routes

The plugin exposes capability-gated endpoints under `/wp-json/ndizi/v1`. Each route's `permission_callback` checks an Ndizi capability (e.g. `ndizi_view_projects`, `ndizi_log_time`), so they work with any standard WordPress authentication — cookie + nonce for in-browser requests, or Application Passwords for external clients. All write operations respect the lock date.

| Method | Route | Description |
| :--- | :--- | :--- |
| `GET` | `/projects` | List active projects (requires `ndizi_view_projects`). |
| `GET` | `/tasks` | List tasks (filtered to the current user for team members). |
| `GET` | `/time/active` | Get the currently running timer for the authenticated user. |
| `POST` | `/time/start` | Start a new active timer (`project_id`, `task_id`, `description`, `billable`). |
| `POST` | `/time/stop` | Stop the active timer (calculates duration and writes to SQL). |
| `POST` | `/time/log` | Manually log a completed time entry (`project_id`, `task_id`, `description`, `duration`, `billable`, optional `start_time` / `end_time`). |
| `GET` | `/time` | List time log history for the current user. |
| `PUT` | `/time/<id>` | Edit a specific historical time entry. |
| `DELETE` | `/time/<id>` | Delete a specific historical time entry. |
| `POST` | `/invoices/<id>/pay` | Create a Stripe Checkout session for an invoice (authenticated user or valid `token` param). |
| `POST` | `/stripe/webhook` | Stripe webhook receiver — marks invoice paid on `checkout.session.completed` (public). |
| `GET` | `/calendar/ical` | Returns an iCal (`.ics`) feed of tasks and time entries (public). |

### 5. WP-CLI Commands

`wp ndizi time` subcommands for terminal-based timer management:

| Command | Description |
| :--- | :--- |
| `wp ndizi time start` | Start a timer. Accepts `--project=<id\|name>`, `--task=<id\|name>`, `--user=<id\|login>`, `--description=<text>`, `--billable=<0\|1>`. |
| `wp ndizi time stop` | Stop the active timer for a user. Accepts `--user=<id\|login>`. |
| `wp ndizi time status` | Show the currently running timer. Accepts `--user=<id\|login>`. |

`--project` and `--task` accept either a post ID or an exact post title; `--user` accepts a WP user ID or login.

### 6. Action Hooks

`Ndizi_DB` fires these actions on each successful write, which `Ndizi_Webhooks` and other consumers listen to:

| Hook | Arguments | Fires when |
| :--- | :--- | :--- |
| `ndizi_timer_started` | `$entry_id, $user_id, $project_id, $task_id, $description, $billable` | A new active timer is created. |
| `ndizi_timer_stopped` | `$entry_id, $user_id, $duration` | An active timer is stopped. |
| `ndizi_time_logged` | `$entry_id, $user_id, $project_id, $task_id, $description, $duration, $billable` | A time entry is logged manually. |
| `ndizi_time_entry_updated` | `$id, $updated_data` | An existing time entry is edited. |
| `ndizi_time_entry_deleted` | `$id` | A time entry is deleted. |

---

## Developer Quickstart

### Prerequisites

- Node.js (v18+)
- Composer (v2.0+)
- phpcs and WordPress Coding Standards globally registered or accessible.

### Installation & Build

1. Clone the repository into your WordPress plugins directory:

   ```bash
   cd wp-content/plugins/ndizi-project-management
   ```

2. Install Node dependencies and run build scripts:

   ```bash
   npm install
   npm run build
   ```

3. Install PHPCS coding standards dependencies:

   ```bash
   composer install
   ```

### Working with Assets

We transpile JS (JSX) and compile Sass (SCSS) using Webpack.

- Use `npm run start` to spin up a hot-reloading development watcher.
- Use `npm run build` to compile minimized production bundles.
- Styles and scripts compile from `src/` into `build/` (e.g. `build/admin.js`, `build/portal.css`).

### Running Linters

Keep code quality pristine by checking formatting and standards before checking in code:

- **JS, Styles, and Markdown**:
    - `npm run lint` (runs ESLint, Stylelint, and Markdownlint).
    - `npm run format` (auto-formats style sheets and source scripts).
- **PHP**:
    - `composer run lint` (runs `phpcs`).
    - `composer run format` (runs `phpcbf` to auto-fix styling errors).

---

## Feature Comparison

This matrix compares Ndizi against three SaaS time-tracking tools reviewed by Bethink Studio in their [May 2026 time-tracking recommendations post](https://bethink.studio/time-tracking-app-recommendations/). The post evaluated tools they considered when leaving Harvest after an unexpected 10x price increase.

| Feature | Ndizi PM | Clockify | Toggl Track | Timely |
| :--- | :---: | :---: | :---: | :---: |
| **Pricing** | Free (GPL, self-hosted) | Free–$14.99/seat/mo | Free–$20/seat/mo | $11–$28/seat/mo |
| **Free tier** | ✅ (unlimited users) | ✅ (up to 5 users) | ✅ (up to 5 users) | ❌ (2-week trial only) |
| **Self-hosted / data ownership** | ✅ | ❌ | ❌ | ❌ |
| **Timer-based time tracking** | ✅ | ✅ | ✅ | ✅ |
| **Manual time entry** | ✅ | ✅ | ✅ | ✅ |
| **Edit / delete past entries** | ✅ (REST API + admin UI) | ✅ | ✅ | ✅ |
| **Billable / non-billable flag per entry** | ✅ | ✅ | ✅ | ✅ |
| **Lock date / protected billing periods** | ✅ | ❌ | ❌ | ❌ |
| **Project & task hierarchy** | ✅ (client → project → task) | ✅ | ✅ | Add-on |
| **Team roles / permissions** | ✅ (Manager, Team Member) | ✅ | ✅ | ✅ |
| **Invoice creation** | ✅ | Standard plan+ | All plans | ❌ |
| **Hierarchical billing rates** | ✅ (task → user → project) | ✅ | Starter plan+ | ✅ |
| **Payment processing** | ✅ (Stripe Checkout) | ❌ | ❌ | ❌ |
| **Invoice export (CSV / JSON)** | ✅ | CSV/PDF | PDF | N/A |
| **Time report export (CSV)** | ✅ | ✅ | ✅ | ✅ |
| **QuickBooks CSV export** | ✅ | Standard plan+ | Starter plan+ | Some plans |
| **Date-range filtered reports** | ✅ | ✅ | ✅ | ✅ |
| **Profitability / margin reports** | ✅ (salary cost tracking) | ✅ | ✅ | ✅ |
| **Client-facing portal** | ✅ (shortcode/block) | ❌ | ❌ | ❌ |
| **Admin bar quick logger** | ✅ | ❌ | ❌ | ❌ |
| **Standalone PWA tracker** | ✅ | Mobile apps | Mobile apps | Mobile apps |
| **Gantt charts** | ✅ (CSS/SVG, no library) | ❌ | ❌ | ❌ |
| **REST API** | ✅ | ✅ | ✅ | ✅ |
| **WP-CLI** | ✅ | ❌ | ❌ | ❌ |
| **Webhooks** | ✅ | All plans | All plans | All plans |
| **Slack integration** | ✅ (webhooks) | ❌ | All plans | ❌ |
| **Email notifications** | ✅ (assignment + status) | ✅ | ✅ | ✅ |
| **Modular on/off toggles** | ✅ | ❌ | ❌ | ❌ |
| **Zapier integration** | ❌ | ❌ | ❌ | All plans |
| **Jira / Asana / project tool integrations** | ❌ | ❌ | Premium plan+ | Some plans |
| **Browser extension** | ✅ (Chrome) | ✅ | ✅ | ❌ |
| **100+ app browser integrations** | ❌ | ✅ | ✅ | ❌ |
| **Calendar sync** | ✅ (Google Calendar + iCal) | ❌ | ✅ | ✅ |
| **AI-assisted time tracking** | ❌ | ❌ | ❌ | ✅ |
| **Employee monitoring (nannyware)** | ❌ | ❌ | ❌ | ✅ |
| **Approval workflows** | ✅ (DB-layer enforcement) | Pro plan+ | ❌ | ❌ |
| **Time-off requests** | ✅ (via client portal) | ❌ | ❌ | ❌ |
| **Mobile app** | ❌ (responsive PWA) | ✅ | ✅ | ✅ |
| **Requires WordPress** | ✅ | ❌ | ❌ | ❌ |

### Notes

- **Ndizi's fundamental difference** from the SaaS tools above is that it runs inside your own WordPress installation. There are no per-seat fees or subscription tiers, but you are responsible for hosting and maintenance.
- **Billing rates**: Ndizi resolves billing rates hierarchically — task override → user default → project default. An explicit `0.00` rate is honored at every tier for pro-bono entries.
- **Lock date**: A lock date setting prevents modifications to any time entry dated on or before that date, protecting closed billing periods site-wide.
- **Mobile**: The standalone tracker is a PWA optimized for desktop use. There is no dedicated native mobile app.
- **Integrations**: Includes outbound webhooks (event callbacks on timer CRUD, CPT status transitions, and metadata changes), a Slack incoming webhook integration, and a QuickBooks-compatible CSV report exporter.
- **Modular Architecture**: Features like Invoicing & Billing, Client Portal, Admin Bar Tracker, Email Notifications, Gantt charts, and Webhooks can be toggled on/off on the settings page to optimize performance.
