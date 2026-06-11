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
- **Client Portal**: A premium, glassmorphic client-facing interface utilizing CSS transitions, visual progress bars, and responsive layouts.
- **Gantt Charts**: A custom CSS Grid and SVG-based Gantt chart system to render project timelines without loading bloated, slow third-party chart libraries.

### 📅 Relational Data Model

- **Clients**: Top-level containers that can hold multiple projects. Supports secret auth key verification for seamless logins.
- **Projects**: Central workspace containers enclosing tasks, time entries, messages, and project-specific invoices.
- **Tasks**: Granular tickets assignable to team members, complete with status tracking, priority flags, and due dates.
- **Contacts**: Stakeholder directories where individual contacts can be linked to multiple clients.

### ⏱️ Decoupled Time Tracking

- Decouples raw, high-frequency time logs from post/postmeta storage into a custom database table (`wp_ndizi_time_entries`).
- Supports active start/stop timers (like Harvest/Clockify) and manual log inputs.
- Logs aggregate hourly tracking metadata, which billing managers can selectively convert into invoice line items.

### 🧾 Invoice Engine & Exports

- **Printable Invoices**: Professional, clean print-friendly template layouts that automatically hide interactive controls when printed or saved to PDF.
- **Data Exporters**: Streamlined export tools that serialize invoice details and line items into standard CSV or JSON downloads.

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

#### Task (`ndizi_task`)

- `_ndizi_project_id` (integer) — Project ID.
- `_ndizi_assigned_user_id` (integer) — WordPress User ID.
- `_ndizi_task_status` (string) — Status (`open`, `in_progress`, `completed`, `cancelled`).
- `_ndizi_task_priority` (string) — Priority (`low`, `medium`, `high`).
- `_ndizi_task_due_date` (string) — Task due date.

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

### 3. Custom REST API Routes

The plugin exposes WordPress Application Password authenticated endpoints under `/wp-json/ndizi/v1`:

| Method | Route | Description |
| :--- | :--- | :--- |
| `GET` | `/projects` | List active projects (requires project view permissions). |
| `GET` | `/tasks` | List active tasks (filtered by current user for team members). |
| `GET` | `/time/active` | Get the currently running timer for the authenticated user. |
| `POST` | `/time/start` | Start a new active timer (payload: `project_id`, `task_id`, `description`, `billable`). |
| `POST` | `/time/stop` | Stop the active timer (calculates duration and updates SQL). |
| `POST` | `/time/log` | Manually log a completed time entry (payload: `project_id`, `task_id`, `description`, `duration`, `billable`, `start_time`). |
| `GET` | `/time` | List history logs for the current user. |
| `PUT` | `/time/<id>` | Edit a specific historical time log. |
| `DELETE` | `/time/<id>` | Delete a specific historical time log. |

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
