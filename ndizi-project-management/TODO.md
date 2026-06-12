# Ndizi Project Management — Feature Gaps & Future Considerations

Features identified as missing when comparing Ndizi to established time-tracking SaaS tools
(Clockify, Toggl Track, Timely). Sourced from Bethink Studio's May 2026 evaluation post.
Items are grouped by area and roughly ordered by how often the gap appeared across those tools.

---

## Billing & Invoicing

- [ ] **Payment processing / payment links** — mark invoices paid after online payment (Stripe,
  PayPal, etc.). None of the surveyed SaaS tools offer this either, but it is a common ask.

---

## Integrations

- [ ] **Zapier / Make connector** — Timely includes this on all plans; useful for connecting to
  tools like Slack, Notion, or accounting software without custom code.
- [ ] **Calendar sync (Google / iCal)** — Toggl Track and Timely both support calendar integration.
  Useful for comparing scheduled vs. actual time or pre-populating time entries from meetings.
- [ ] **Browser extension / bookmarklet** — Toggl and Clockify offer one-click tracking from any
  browser tab (100+ app integrations). Not a requirement, but reduces friction significantly.

---

## Reporting & Analytics

- [ ] **Per-user / per-project utilization reports** — the SaaS tools all surface "what percentage
  of this person's tracked time is billable" and similar metrics. Ndizi's admin dashboard shows
  summary charts but lacks drill-down utilization views.

---

## Team & Workflow

- [ ] **Approval workflow for time entries** — Clockify (Pro plan) requires manager sign-off before
  time is locked for invoicing. Useful for agencies to catch errors before billing.
- [ ] **Time-off / absence tracking** — not covered by any surveyed tool natively but commonly
  requested alongside time tracking for small agencies.

---

## Mobile & Access

- [ ] **Mobile-optimized PWA or native app** — Clockify, Toggl, and Timely all have dedicated
  mobile apps. The current standalone PWA is desktop-focused; a responsive small-screen layout
  for logging time on the go would close part of this gap.
- [ ] **Native push notifications** — pairs with an improved PWA or app for timer reminders.

---

## Infrastructure

- [ ] **Application Password scope documentation** — the REST API supports Application Passwords,
  but there is no documented example of authenticating external tools against it.

---

## Completed

### Billing & Invoicing
- [x] **Hourly rate configuration** — per-project and/or per-user billing rates, so invoices can be auto-calculated from tracked hours.
- [x] **Invoice total auto-calculation** — compute the invoice amount from `billable_duration × hourly_rate` and populate the invoice automatically.
- [x] **PDF invoice export** — print-ready templates/PDF styling for invoice exports.

### Integrations
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

### Notifications
- [x] **Email notifications for more events** — automated email notifications for task assignment and status updates.
- [x] **Idle timer detection** — alert users when a timer has been running longer than 8 hours.

### Infrastructure
- [x] **CLI / WP-CLI commands** — `wp ndizi time start`, `wp ndizi time stop`, and `wp ndizi time status` subcommands.

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
