=== Ndizi Project Management ===
Contributors: georgestephanis
Tags: project management, time tracking, clients, tasks, invoices, gantt
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A scalable, beautiful, and native WordPress project management system to track clients, projects, tasks, timesheets, and invoices.

== Description ==

**Ndizi Project Management** is a professional, native WordPress system built for freelancers, designers, and small agencies to coordinate client work, manage tasks, record project hours, and generate invoices—all inside a single WordPress environment.

Decoupling high-frequency data from standard WordPress posts storage, Ndizi records all time logs in a dedicated custom SQL table (`wp_ndizi_time_entries`). This architectural choice keeps your database queries fast and completely avoids `wp_posts` and `wp_postmeta` database inflation.

### Key Features

*   **Premium Dashboards**: Interactive, responsive HTML/CSS dashboards for managers to analyze team time allocations, billable totals, and status rates.
*   **Gantt Timelines**: Custom CSS Grid and SVG-based Gantt charts directly in your admin dashboard to visualize project schedules.
*   **Decoupled Time Tracker**: Start, stop, and log timesheets directly in the admin bar or task meta boxes.
*   **Glassmorphic Client Portal**: A premium front-end experience using the `[ndizi_client_portal]` shortcode. Clients can review their projects, verify tasks, download invoices, and submit new requests.
*   **Secure Passwordless Portal Auth**: Authorize client portal sessions using unique, secure client authentication keys (e.g. `?ndizi_token=...`), avoiding the need for clients to create standard WordPress user accounts.
*   **Collaborative Discussions**: Task and project comment boxes are filtered and embedded into the Client Portal, allowing team members and clients to exchange feedback and upload file attachments.
*   **Invoice Generation & Exports**: Automatically aggregate un-invoiced billable hours into detailed project invoices. Export invoice logs to CSV or JSON formats, or print/save them using our clean, professional print stylesheet.
*   **REST API Integration**: Custom API routes under `/wp-json/ndizi/v1` let desktop widgets or mobile timekeepers start and stop timers remotely.

== Installation ==

1.  Upload the `ndizi-project-management` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress Admin Plugins dashboard.
2.  Activate the plugin. The database table `wp_ndizi_time_entries` and custom roles will be initialized automatically.
3.  Create a new WordPress Page for your client dashboard and embed the `[ndizi_client_portal]` shortcode.
4.  Navigate to **Projects** -> **Clients** in your admin panel, register a new client, and generate a portal access key.

== Frequently Asked Questions ==

= Where is the time tracking data stored? =
High-frequency time entries (timer starts, stops, descriptions, and durations) are logged in the dedicated `wp_ndizi_time_entries` table. Relational objects like Projects and Tasks utilize standard Custom Post Types to maintain editing workflows, list filters, and default REST support.

= Do clients need standard WordPress accounts to log in? =
No. While standard WordPress accounts are fully supported, you can generate a private **Client Auth Key** for any Client CPT. Navigating to the client portal with `?ndizi_token=YOUR_KEY` authorizes their session, setting a secure cookie that keeps them logged in.

= How does the file attachment system in discussions work? =
Discussion boards on tasks and projects utilize WordPress's native comments database but filter comments to only show portal discussions. If files are uploaded through the intake forms or discussion boxes, they are saved as secure media attachments in the uploads directory and associated with the comment meta.

= How do managers construct invoices? =
Inside any Invoice post, choose the parent Project. The dashboard will query the time logging database for all un-invoiced billable hours on that project. Specify an hourly rate, select the hours to include, and the editor will aggregate the line items, calculate the total, and lock those time entries to the invoice.

= Does the plugin support third-party REST integrations? =
Yes! Fully authenticated REST routes are exposed under `/wp-json/ndizi/v1/time` for starting, stopping, logging, and editing timer entries. This enables desktop timekeepers or browser extensions to communicate with the plugin.

== Screenshots ==

1.  **Reports Dashboard**: Interactive, responsive summaries of billable time allocations and user productivity.
2.  **Gantt Timelines**: native project schedules mapping project milestones and task completion rates.
3.  **Client Portal**: responsive frontend client portal featuring glassmorphic style controls.
4.  **Invoice Meta Box**: aggregating un-invoiced project logs into line-item details.

== Changelog ==

= 1.0.0 =
*   Initial release.
*   Decoupled SQL schema database initializations.
*   Custom Post Types and taxonomy metadata setups.
*   Integrated admin bar active timers.
*   Gantt charts and dashboard report enqueues.
*   Shortcode-driven frontend portal with passwordless tokens.
*   CSV/JSON invoice exports and printable layouts.
*   REST API controller for desktop integration.
