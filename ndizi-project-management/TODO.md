# Ndizi Project Management — Feature Gaps & Future Considerations

Features identified as missing when comparing Ndizi to established time-tracking SaaS tools
(Clockify, Toggl Track, Timely). Sourced from Bethink Studio's May 2026 evaluation post.
Items are grouped by area and roughly ordered by how often the gap appeared across those tools.

---

## Billing & Invoicing

- [x] **Hourly rate configuration** — per-project and/or per-user billing rates, so invoices can be
  auto-calculated from tracked hours rather than requiring a manually entered total.
- [x] **Invoice total auto-calculation** — once rates exist, compute the invoice amount from
  `billable_duration × hourly_rate` and populate the invoice automatically.
- [ ] **Payment processing / payment links** — mark invoices paid after online payment (Stripe,
  PayPal, etc.). None of the surveyed SaaS tools offer this either, but it is a common ask.
- [x] **PDF invoice export** — Clockify and Toggl export print-ready PDFs; Ndizi currently exports
  CSV and JSON only. A generated PDF (or print-stylesheet polish) would be a direct gap.

---

## Integrations

- [ ] **Webhooks** — outbound HTTP callbacks when time is logged, invoices change status, tasks are
  created, etc. Clockify, Toggl, and Timely all offer webhooks on free or base plans. This would
  unlock Zapier/Make compatibility without a native connector.
- [ ] **Zapier / Make connector** — Timely includes this on all plans; useful for connecting to
  tools like Slack, Notion, or accounting software without custom code.
- [ ] **QuickBooks / accounting export** — Clockify (Standard+) and Toggl (Starter+) both sync to
  QuickBooks. Even a structured CSV targeted at QuickBooks import format would narrow the gap.
- [ ] **Slack notifications** — Toggl notifies on free/all plans. Task assignments, timer reminders,
  or invoice state changes posted to a Slack channel are high-value for teams.
- [ ] **Calendar sync (Google / iCal)** — Toggl Track and Timely both support calendar integration.
  Useful for comparing scheduled vs. actual time or pre-populating time entries from meetings.
- [ ] **Browser extension / bookmarklet** — Toggl and Clockify offer one-click tracking from any
  browser tab (100+ app integrations). Not a requirement, but reduces friction significantly.

---

## Reporting & Analytics

- [ ] **Per-user / per-project utilization reports** — the SaaS tools all surface "what percentage
  of this person's tracked time is billable" and similar metrics. Ndizi's admin dashboard shows
  summary charts but lacks drill-down utilization views.
- [x] **Individual salary rates tracking** — track internal employee hourly salary rates to calculate project costs and margins.
- [x] **Profitability reports** — once billing rates exist, a report comparing budget vs. actual
  (time cost vs. project budget) is a natural next step.
- [x] **Exportable time reports** — a CSV/PDF export of filtered time entries (by date range,
  project, user, billable flag) is expected by accountants and clients.
- [x] **Date-range filtering in the admin dashboard** — currently reports appear to be all-time; a
  selectable time window (this week / this month / custom) is table-stakes for billing workflows.

---

## Team & Workflow

- [ ] **Approval workflow for time entries** — Clockify (Pro plan) requires manager sign-off before
  time is locked for invoicing. Useful for agencies to catch errors before billing.
- [x] **Time entry locking / period close** — prevent edits to time logged before a given date
  (after an invoice is issued or a pay period closes).
- [ ] **Time-off / absence tracking** — not covered by any surveyed tool natively but commonly
  requested alongside time tracking for small agencies.

---

## Notifications

- [x] **Email notifications for more events** — added task assignment and task status change notifications.
- [ ] **Idle timer detection** — prompt user when a timer has been running for an unusually long
  period (e.g. > 8 hours), which is a common data-quality issue.

---

## Mobile & Access

- [ ] **Mobile-optimized PWA or native app** — Clockify, Toggl, and Timely all have dedicated
  mobile apps. The current standalone PWA is desktop-focused; a responsive small-screen layout
  for logging time on the go would close part of this gap.
- [ ] **Native push notifications** — pairs with an improved PWA or app for timer reminders.

---

## Infrastructure

- [x] **CLI / WP-CLI commands** — `wp ndizi time start --project=X`, `wp ndizi time stop`, etc.,
  for developers or automated environments.
- [ ] **Application Password scope documentation** — the REST API supports Application Passwords,
  but there is no documented example of authenticating external tools against it.

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
