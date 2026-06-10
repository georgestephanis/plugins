=== Project Management Plugin ===

== Functionality Goals ==

A WordPress-native project management plugin for freelancers and small agencies to track client work. Core data model:

* **Clients** — top-level entities; each client can have multiple projects.
* **Projects** — belong to a client; serve as the container for tasks, time, invoices, messages, and file attachments.
* **Tasks** — belong to a project; can be assigned to a WordPress user; have a status (open/closed/etc.).
* **Time Entries** — belong to a project; can be assigned to a WordPress user; optionally linked to a specific task; can be marked billable or non-billable.
* **Invoices** — belong to a project; can aggregate billable time entries.
* **Messages** — belong to a project or task; support file attachments.
* **Contacts** — optional; can be associated with one or more clients or projects.

=== Admin capabilities ===

* Full CRUD for all data types via wp-admin.
* Assign tasks and time entries to WordPress users.
* Mark clients and projects as active or archived/inactive.
* Reports page: time totals and stats across projects and users.
* Gantt chart view for project timelines.
* Permission levels: granular roles beyond just admin (e.g. team members can log time, see their own tasks).

=== Client-facing front end ===

* Designated front-end page where clients authenticate (by key or WordPress account) and can:
  * View their projects (with aggregate time totals, not individual entries).
  * View tasks assigned to their projects, including task status.
  * View invoices.
  * Submit new tasks (which appear in wp-admin for the admin to triage, clarify, and assign).
* Email notifications to specified users when a client submits a task.

=== Integrations ===

* Export invoices to external invoicing services rather than requiring internal management.
