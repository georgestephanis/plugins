=== Ndizi Project Management ===
Contributors: georgestephanis
Tags: project management, time tracking, clients, tasks, invoices
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0-alpha.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A scalable, beautiful, and native WordPress project management system to track clients, projects, tasks, timesheets, and invoices.

== Description ==

**Ndizi Project Management** is a professional, native WordPress system built for freelancers, designers, and small agencies to coordinate client work, manage tasks, record project hours, and generate invoices—all inside a single WordPress environment.

Decoupling high-frequency data from standard WordPress posts storage, Ndizi records all time logs in a dedicated custom SQL table (`wp_ndizi_time_entries`). This architectural choice keeps your database queries fast and completely avoids `wp_posts` and `wp_postmeta` database inflation.

### Key Features

*   **Premium Dashboards**: Interactive, responsive HTML/CSS dashboards for managers to analyze team time allocations, billable totals, and project status.
*   **Gantt Timelines**: Custom CSS Grid and SVG-based Gantt charts directly in your admin dashboard to visualize project schedules — no third-party chart library required.
*   **Decoupled Time Tracker**: Start, stop, and log timesheets directly in the admin bar, meta boxes, or standalone pages. Projects are neatly grouped by client, and input modes are gated (either/or) to prevent conflicts.
*   **Standalone PWA Companion App**: A distraction-free companion tracking page stripped of WordPress admin menus/bars. Chrome-installable as a borderless desktop application featuring a dark glassmorphic interface, a ticking digital clock, and today's logged entry feed. Fully responsive at small-screen widths. Supports a `?desc=` URL parameter to pre-fill the description input (used by the Chrome extension). Requests browser notification permission and fires a push notification when the active timer exceeds 8 hours.
*   **Idle Warning Banner**: When an active timer has been running for more than 8 hours, a warning banner appears in the admin bar panel and on the standalone tracker page prompting the user to verify their logged time.
*   **Lock Date / Time Entry Locking**: Configure a lock date on the Settings page to prevent creating, editing, or deleting any time entry dated on or before that date. Protects closed billing periods from accidental modification — enforced across the REST API, the DB layer, and the admin UI.
*   **Customizable Tracker Icons**: A settings dashboard allowing users to select and dynamically render their preferred tracker icon (Banana, Clock, Punch Clock, Hourglass).
*   **Hierarchical Billing Rates**: Billing rates are resolved in priority order — task override then user default then project default — at invoice and report generation time. An explicit rate of 0.00 at any level is honored for pro-bono entries.
*   **Date-Range Filtered Reports**: The Reports dashboard supports start date / end date, project, and team member filters with bookmarkable URLs. KPI cards surface total hours, billable hours, revenue, estimated salary cost, and profit margin.
*   **Invoice Generation & Exports**: Automatically aggregate un-invoiced billable hours into detailed project invoices. Export invoice line items to CSV or JSON formats, or print/save them using a clean, professional print stylesheet.
*   **QuickBooks CSV Export**: Export filtered time report data as a QuickBooks-compatible CSV file (Customer, Item, Date, Hours, Rate, Description) for direct import into accounting software.
*   **Glassmorphic Client Portal**: A premium front-end experience available as the `[ndizi_client_portal]` shortcode or the **Ndizi Client Portal** block in the block editor. Clients can review projects, verify tasks, download invoices, and submit new requests.
*   **Secure Passwordless Portal Auth**: Authorize client portal sessions using unique, secure client authentication keys, avoiding the need for clients to create standard WordPress user accounts.
*   **Collaborative Discussions**: Task and project comment boxes are filtered and embedded into the Client Portal, allowing team members and clients to exchange feedback and upload file attachments.
*   **Email Notifications**: Sends emails when a task is assigned or reassigned to a team member, and when a task's status changes.
*   **Outbound Webhooks & Slack**: Dispatches JSON event payloads to a configurable webhook endpoint and formatted messages to a Slack incoming webhook on timer events, time entry CRUD, CPT status transitions, and task/invoice metadata changes.
*   **Time Entry Approval Workflow**: Time entries carry `approved` and `approved_by` fields. Once approved, entries cannot be edited or deleted through normal write paths — only the approval status itself can be updated. Approval-only updates bypass lock-date enforcement.
*   **Google Calendar Sync**: When Google OAuth2 credentials are configured in Settings, tasks with due dates are synced to Google Calendar and time entries are pushed after each stop or manual log. An iCal subscription feed is available at `/wp-json/ndizi/v1/calendar/ical`.
*   **Stripe Online Payments**: Configure Stripe API keys in Settings to add a "Pay Online" button to unpaid invoices in the client portal. The plugin creates a Stripe Checkout session via the REST API and auto-marks invoices paid via the Stripe webhook endpoint.
*   **Client Portal Time-Off Requests**: Clients and team members can submit time-off and absence requests directly from the portal sidebar. Requests are stored as `ndizi_time_off` posts with start/end dates, type, and approval status.
*   **Browser Extension**: A companion Chrome extension (`chrome-extension/`) connects to the site's REST API to start/stop timers, browse projects and tasks, and open the standalone tracker — from any browser tab.
*   **REST API Integration**: Custom API routes under `/wp-json/ndizi/v1` let desktop widgets or mobile timekeepers start, stop, log, list, edit, and delete timer entries remotely.
*   **WP-CLI Commands**: Manage timers from the terminal with `wp ndizi time start`, `wp ndizi time stop`, and `wp ndizi time status`. Accepts project/task names or IDs, user login or ID, description, and billable flag.
*   **Modular Architecture**: Each major feature group (Invoicing, Client Portal, Admin Bar Tracker, Email Notifications, Gantt Charts, Webhooks) can be individually toggled on or off from the Settings page. Inactive modules are not loaded, reducing overhead on sites that don't need every feature.

== Installation ==

1.  Upload the `ndizi-project-management` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress Admin Plugins dashboard.
2.  Activate the plugin. The database table `wp_ndizi_time_entries` and custom roles will be initialized automatically.
3.  Create a new WordPress Page for your client dashboard and add the **Ndizi Client Portal** block, or embed the `[ndizi_client_portal]` shortcode.
4.  Navigate to **Ndizi PM** -> **Clients** in your admin panel, register a new client, and generate a portal access key.

== Frequently Asked Questions ==

= Where is the time tracking data stored? =
High-frequency time entries (timer starts, stops, descriptions, and durations) are logged in the dedicated `wp_ndizi_time_entries` table. Relational objects like Projects and Tasks utilize standard Custom Post Types to maintain editing workflows, list filters, and default REST support.

= Do clients need standard WordPress accounts to log in? =
No. While standard WordPress accounts are fully supported, you can generate a private **Client Auth Key** for any Client CPT. Navigating to the client portal with `?ndizi_token=YOUR_KEY` authorizes their session, setting a secure cookie that keeps them logged in.

= How does the file attachment system in discussions work? =
Discussion boards on tasks and projects utilize WordPress's native comments database but filter comments to only show portal discussions. If files are uploaded through the intake forms or discussion boxes, they are saved as secure media attachments in the uploads directory and associated with the comment meta.

= How do managers construct invoices? =
Inside any Invoice post, choose the parent Project. The dashboard will query the time logging database for all un-invoiced billable hours on that project. The billing rate for each time entry is resolved hierarchically: task-level override first, then the assigned user's default rate, then the project's default rate. Select the hours to include and the editor will aggregate the line items, calculate the total, and lock those time entries to the invoice.

= What is the lock date used for? =
The lock date (configured on the Settings page) prevents any time entry dated on or before that date from being created, edited, or deleted. Use it to protect finalized billing periods once invoices have been sent. The lock is enforced across the REST API, direct DB operations, and the admin UI.

= How does the hierarchical billing rate work? =
When generating an invoice or report, Ndizi resolves the billing rate in this order: (1) the task's own hourly rate override, (2) the assigned user's billing rate from their profile, (3) the project's default hourly rate. An explicit rate of 0.00 at any level is honored rather than falling through to the next tier, making it possible to mark individual tasks or projects as pro-bono.

= Can I export time data for accounting software? =
Yes. The Reports dashboard has an "Export QuickBooks CSV" button that downloads a CSV formatted for direct import into QuickBooks (Customer, Item, Date, Hours, Rate, Description columns). The active date range, project, and user filters carry through to the export. A standard CSV export is also available from the same dashboard. Individual invoice line items can be exported as CSV or JSON from the invoice editor screen.

= Can I use WP-CLI to manage timers? =
Yes. Use `wp ndizi time start --project="My Project" --description="Working on feature X"` to start a timer, `wp ndizi time stop` to stop it, and `wp ndizi time status` to check what's running. All commands accept `--user=<login|id>` to target a specific team member. `--project` and `--task` accept either an exact post title or a post ID.

= Does the plugin support third-party REST integrations? =
Yes. Fully authenticated REST routes are exposed under `/wp-json/ndizi/v1/time` for starting, stopping, logging, listing, editing, and deleting timer entries. This enables desktop timekeepers, browser extensions, or mobile apps to communicate with the plugin. All write routes enforce the lock date.

= Can I receive notifications when timer events or task updates occur? =
Yes. The Integrations module posts JSON webhook payloads to a configurable URL on timer CRUD operations, CPT status transitions, and task/invoice metadata changes. A separate Slack webhook URL field sends formatted messages to any Slack channel. The Notifications module sends email to assigned team members when a task is assigned or when its status changes.

= Can I turn off features I don't need? =
Yes. The Settings page (Ndizi PM → Settings) lists all feature modules. Uncheck any module to disable it. Inactive modules are not loaded by the plugin, so their CPTs, hooks, and admin pages simply don't exist — useful for keeping things lean on sites that only need time tracking without the full feature set.

== Screenshots ==

1.  **Reports Dashboard**: Interactive, responsive summaries of billable time allocations and user productivity, with date-range and project/user filters.
2.  **Gantt Timelines**: Native project schedules mapping project milestones and task completion rates.
3.  **Client Portal**: Responsive frontend client portal featuring glassmorphic style controls.
4.  **Invoice Meta Box**: Aggregating un-invoiced project logs into line-item details with hierarchical billing rate resolution.

== Changelog ==

= 1.0.0-alpha.2 =
*   Google Calendar integration: tasks and time entries synced via OAuth2; iCal subscription feed at `/wp-json/ndizi/v1/calendar/ical`.
*   Stripe online payment: "Pay Online" button in client portal, Stripe Checkout session REST endpoint, and webhook auto-mark-paid handler.
*   Client portal time-off/absence request form (creates `ndizi_time_off` CPT posts).
*   Time entry approval workflow: `approved` / `approved_by` DB columns; approved entries block edits and deletion.
*   Chrome browser extension for timer control from any browser tab.
*   Standalone tracker: responsive CSS at ≤480 px, browser push notifications for idle timer, `?desc=` pre-fill parameter.
*   Auto DB schema upgrade on plugin init via `ndizi_db_version` version check.
*   `GET /calendar/ical`, `POST /invoices/<id>/pay`, `POST /stripe/webhook` REST routes added.
*   Portal scripts now receive `rest_url` for client-side REST calls.
*   WordPress Abilities API: Ndizi capabilities exposed via `Ndizi_Abilities` for agentic/MCP workflows.
*   Settings page: Google OAuth connect button, Stripe API key fields.

= 1.0.0-alpha =
*   Initial release.
*   Decoupled SQL schema database initialization (`wp_ndizi_time_entries`).
*   Custom Post Types and taxonomy metadata setups.
*   Integrated admin bar active timer with customizable icons and idle warning banner.
*   Lock date / time entry locking enforced across REST API, DB layer, and admin UI.
*   Hierarchical billing rates (task then user then project) on invoices and reports.
*   Gantt charts and dashboard report pages with date-range, project, and user filters.
*   Date-range filtered time reports with standard CSV and QuickBooks CSV export.
*   Invoice line-item CSV and JSON exports.
*   Shortcode- and block-driven frontend portal with passwordless token auth.
*   Email notifications for task assignment and status changes.
*   Outbound webhooks and Slack incoming webhook integration.
*   WP-CLI `wp ndizi time` command group (start, stop, status).
*   Modular architecture: feature modules toggleable from the Settings page.
*   REST API controller for external timer integration.
*   Standalone PWA dark glassmorphic tracker page.
*   Hardening: per-post-type verification on meta-box saves, MIME/size/count limits on portal uploads, CSV formula-injection escaping on exports, sanitize callbacks on time-entry REST routes, cryptographically secure client auth-key generator, non-negative rate validation.
