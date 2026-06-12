# Ndizi Project Management — Feature Gaps & Future Considerations

Features identified as missing when comparing Ndizi to established time-tracking SaaS tools
(Clockify, Toggl Track, Timely). Sourced from Bethink Studio's May 2026 evaluation post.
Items are grouped by area and roughly ordered by how often the gap appeared across those tools.

---

## Billing & Invoicing

- [ ] **PayPal / additional payment processors** — Stripe Checkout is live; extending to PayPal or other gateways would close the remaining gap for sites that don't use Stripe.

---

## Integrations

- [ ] **Zapier / Make connector** — Timely includes this on all plans; useful for connecting to
  tools like Slack, Notion, or accounting software without custom code.

---

## Reporting & Analytics

- [ ] **Per-user / per-project utilization reports** — the SaaS tools all surface "what percentage
  of this person's tracked time is billable" and similar metrics. Ndizi's admin dashboard shows
  summary charts but lacks drill-down utilization views.

---

## Team & Workflow

- [ ] **Approval workflow admin UI** — `approved` / `approved_by` DB columns and write guards are in place; a manager-facing UI to review and approve individual entries (e.g. a list-table with bulk approve action) still needs to be built.

---

## Mobile & Access

- [ ] **Full native mobile app** — Clockify, Toggl, and Timely all have dedicated iOS/Android apps.
  The standalone PWA is now responsive at ≤480 px and supports push notifications, but a native
  app experience (offline support, home-screen install prompts, app store distribution) would
  close the remaining gap.

---

## Infrastructure

---

## Completed

### Billing & Invoicing
- [x] **Payment processing / payment links** — Stripe Checkout integration: "Pay Online" button in the client portal creates a Checkout session; webhook auto-marks the invoice paid on `checkout.session.completed`.
- [x] **Hourly rate configuration** — per-project and/or per-user billing rates, so invoices can be auto-calculated from tracked hours.
- [x] **Invoice total auto-calculation** — compute the invoice amount from `billable_duration × hourly_rate` and populate the invoice automatically.
- [x] **PDF invoice export** — print-ready templates/PDF styling for invoice exports.

### Integrations
- [x] **Calendar sync (Google / iCal)** — Google Calendar OAuth2 integration syncs tasks and time entries; iCal subscription feed at `GET /wp-json/ndizi/v1/calendar/ical`.
- [x] **Browser extension** — Chrome extension (`chrome-extension/`) for one-click timer control and project browsing from any tab.
- [x] **Webhooks** — outbound HTTP callbacks when time is logged, invoices change status, tasks are created, etc.
- [x] **Slack notifications** — post task assignments, timer reminders, or invoice state changes directly to Slack channels.
- [x] **WordPress Abilities API & MCP Support** — registered core capabilities as native WordPress Core Abilities for agentic workflows using the standalone MCP Adapter plugin.

### Reporting & Analytics
- [x] **Individual salary rates tracking** — track internal employee hourly salary rates to calculate project costs and margins.
- [x] **Profitability reports** — compare budget vs. actual (time cost vs. project budget).
- [x] **Exportable time reports** — CSV/PDF exports of filtered time entries.
- [x] **Date-range filtering in the admin dashboard** — select custom time windows (this week / this month / custom) for reports.

### Team & Workflow
- [x] **Time entry locking / period close** — prevent edits to time logged before a given date.
- [x] **Approval workflow (DB layer)** — `approved` / `approved_by` fields added; approved entries block edits and deletion via all write paths.
- [x] **Time-off / absence tracking** — time-off request form in the client portal sidebar creates `ndizi_time_off` CPT posts with type, dates, and status.

### Notifications
- [x] **Email notifications for more events** — automated email notifications for task assignment and status updates.
- [x] **Idle timer detection** — alert users when a timer has been running longer than 8 hours.
- [x] **Browser push notifications** — standalone tracker requests notification permission and fires a push notification after the active timer exceeds 8 hours.

### Infrastructure
- [x] **CLI / WP-CLI commands** — `wp ndizi time start`, `wp ndizi time stop`, and `wp ndizi time status` subcommands.
- [x] **Application Password scope documentation** — `API-AUTHENTICATION.md` documents authenticating external tools via Application Passwords, client token auth, and the `ndizi_token` query parameter.
- [x] **Mobile-responsive PWA** — standalone tracker now fully responsive at ≤480 px.

---

## Out of Scope (noted for completeness)

The following features were differentiators for the SaaS tools but are unlikely to be a fit for a
self-hosted WordPress plugin:

- **AI-assisted automatic time tracking** (Timely's primary differentiator) — requires always-on
  background agents and cloud ML infrastructure.
- **Employee monitoring / nannyware** (Timely) — screenshots, app usage tracking. Philosophically
  misaligned with a privacy-respecting self-hosted tool.
- **Multi-tenant SaaS architecture** — Ndizi is intentionally single-site/self-hosted; SaaS-style
  account management is out of scope.
