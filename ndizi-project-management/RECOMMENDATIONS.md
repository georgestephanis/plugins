# Ndizi — Architecture & Code Review Recommendations

Running log of review findings and recommendations. Each item is independently actionable
and sized for handoff to an agent. Check items off (and note the commit) as they land;
add new findings at the appropriate priority tier rather than the bottom.

_Last full review: 2026-06-12 (Claude Code, full-plugin review at 1.0.0-alpha)._

---

## P2 — Architecture (do these while the plugin is still pre-1.0)

- [ ] **Introduce a shared service/validation layer for time operations.** There are four
  write paths today — REST, Abilities, admin AJAX, CLI — each implementing (or omitting)
  its own validation and authorization on top of `Ndizi_DB`, which is pure persistence.
  This is the root cause of the P1 REST-validation gap and the duplicated permission
  logic the Abilities class borrows from `Ndizi_REST`. Extract a `Ndizi_Time_Service`
  (validate project active / user assignment / lock date / approval state, then call
  `Ndizi_DB`) and make all four entry points thin adapters over it.

- [ ] **Split `Ndizi_Admin` (2,766 lines, ~8 responsibilities).** It currently owns
  settings + Google OAuth, list-table columns for 4 CPTs, six meta boxes and their save
  logic, 8+ AJAX handlers, dashboard/reports/gantt rendering, and query scoping. Natural
  seams: `Ndizi_Settings` (incl. OAuth), `Ndizi_Meta_Boxes`, `Ndizi_List_Tables`,
  `Ndizi_Ajax` (or fold timer AJAX into the time service), `Ndizi_Reports`.

- [ ] **Move the standalone tracker out of inline heredocs.** `Ndizi_Standalone_Tracker`
  embeds ~1,200 lines of HTML/CSS/JS (including the service worker) directly in PHP
  ([class-ndizi-standalone-tracker.php](includes/class-ndizi-standalone-tracker.php)).
  Move markup to template files and CSS/JS into the existing `@wordpress/scripts` build
  (`src/standalone/`), which also enables minification, RTL generation, and linting that
  every other surface already gets. The PWA approach itself (manifest + service worker +
  installable page) is sound and worth keeping.

- [ ] **Deduplicate the timer front-end.** `formatTime()`, the ticking interval logic,
  and timer state are copy-pasted between [src/admin/index.js](src/admin/index.js) and
  [src/adminbar/index.js](src/adminbar/index.js) (and conceptually re-implemented in the
  standalone tracker and chrome extension). Extract a shared `src/shared/timer.js`
  module; webpack already supports it.

## P4 — Packaging / wp.org release readiness

- [ ] **Add automated tests.** Nothing exists today. Highest-value first targets:
  `Ndizi_DB` lock-date/approval/duration logic (pure, easy to test), Stripe webhook
  signature verification, and the REST permission callbacks. `wp-env` + PHPUnit or the
  Playground blueprint already in-repo can host integration runs.

---

## Resolved

_(branch: ndizi/fable-review, 2026-06-12 unless noted)_

**P1 — Security & correctness**
- Portal auth key exposure via REST — `show_in_rest => false` + `auth_callback` verified present.
- CPT `capability_type => 'post'` — added explicit `capabilities` arrays mapped to `ndizi_manage_*` caps + `map_meta_cap`.
- REST write endpoints skipping Abilities validation — extracted `Ndizi_REST::validate_time_project_access()`, wired into `start_timer` and `log_time_manual`.
- No `sanitize_callback` on `register_post_meta()` — added per-type callbacks across all CPTs.
- Stripe webhook `payment_status` check + idempotency — guard on `paid` status; skip if invoice already paid.
- Settings OAuth callback: verify capability/nonce before code exchange; check token response status code.
- Google Calendar token refresh error handling — HTTP status check + `error_log()` for WP_Error, non-200, missing token.
- CLI commands unguarded for `--user` — added `ndizi_manage_time` check when targeting another user.

**P2 — Architecture**
- Store time entries in UTC — replaced all `current_time('mysql')` with `current_time('mysql', true)` across all write paths.
- Module system owns its pieces — `get_module_registry()` as single source of truth; dynamic include/init/rest-route loops; `calendar` module; `class_exists('Ndizi_Portal')` guards; new-module activation in upgrade path. ([Ndizi.php](Ndizi.php), [class-ndizi-rest.php](includes/class-ndizi-rest.php), [class-ndizi-admin.php](includes/class-ndizi-admin.php), [class-ndizi-standalone-tracker.php](includes/class-ndizi-standalone-tracker.php))
- Decouple portal-token auth — `Ndizi_Portal::get_client_id_by_token()` made public; iCal feed and invoice pay permission now share it.
- Support defining secrets as constants — `get_secret()` checks `NDIZI_*` constants before `get_option()` for all six secrets.

**P3 — Code quality, performance, reliability**
- N+1 queries in list endpoints — `update_meta_cache()` and `_prime_post_caches()` added in REST list handlers and admin-bar AJAX.
- Unbounded queries — `per_page`/`page` params + `X-WP-Total` headers on `/projects` and `/tasks`; `Ndizi_DB` default changed from `-1` to `500`.
- Outbound webhooks SSRF guard — replaced `filter_var` with `wp_http_validate_url()`; added `error_log()` on blocked URLs.
- Duplicate event fan-out (Notifications + Webhooks) — canonical `ndizi_task_assigned` and `ndizi_task_status_changed` actions; Notifications now listens to those instead of raw meta hooks.
- Deprecated `meta_key`/`meta_value` query args replaced with `meta_query`; inline `onmouseover`/`onmouseout` replaced with CSS `:hover`.
- Chrome extension: legacy credential migration validation + storage risk documented in API-AUTHENTICATION.md.
- `Ndizi_Roles::current_user_can()` redundant admin special-case removed.

**P4 — Packaging**
- Stable tag mismatch — aligned `readme.txt` to `1.0.0-alpha.2`.
- No `.pot` file — generated `languages/ndizi-project-management.pot`.
- No LICENSE file — added full GPL-2.0 text as `LICENSE`.
