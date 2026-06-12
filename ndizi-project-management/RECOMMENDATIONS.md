# Ndizi — Architecture & Code Review Recommendations

Running log of review findings and recommendations. Each item is independently actionable
and sized for handoff to an agent. Check items off (and note the commit) as they land;
add new findings at the appropriate priority tier rather than the bottom.

_Last full review: 2026-06-12 (Claude Code, full-plugin review at 1.0.0-alpha)._

---

## P1 — Security & correctness (fix before any public release)

- [x] **Portal auth key is exposed via the REST API.** _(verified fixed — already
  `show_in_rest => false` with a comment; `auth_callback` present.)_

- [x] **CPTs use `capability_type => 'post'`, so any Author/Editor on the site can edit
  clients, invoices, and projects.** Added explicit `capabilities` arrays mapped to the
  corresponding `ndizi_manage_*` cap + `map_meta_cap => true` for all six CPTs.
  ([class-ndizi-cpts.php](includes/class-ndizi-cpts.php)) _(branch: ndizi/fable-review)_

- [x] **REST write endpoints skip the project/task validation the Abilities API enforces.**
  Extracted shared `Ndizi_REST::validate_time_project_access()` helper and wired it into
  `Ndizi_REST::start_timer()`, `Ndizi_REST::log_time_manual()`, and both Abilities
  callbacks (replacing the duplicated code).
  ([class-ndizi-rest.php](includes/class-ndizi-rest.php), [class-ndizi-abilities.php](includes/class-ndizi-abilities.php))
  _(branch: ndizi/fable-review)_

- [x] **No sanitize callbacks on any `register_post_meta()` call.**
  Added per-type callbacks: `esc_url_raw` (website), `sanitize_email` (contact email),
  `absint` (integer IDs), `floatval` (budget/rates/amounts), `sanitize_text_field`
  (all other strings), array map of `absint` (associated clients).
  ([class-ndizi-cpts.php](includes/class-ndizi-cpts.php)) _(branch: ndizi/fable-review)_

- [x] **Stripe webhook: check `payment_status` and add idempotency.**
  Guard on `$session['payment_status'] === 'paid'` before processing (returns 200 to
  Stripe without acting when status is not yet `paid`). Check invoice status before
  updating — skip and return 200 if already `paid` to prevent duplicate
  `ndizi_invoice_paid` fires on Stripe retries.
  ([class-ndizi-rest.php](includes/class-ndizi-rest.php)) _(branch: ndizi/fable-review)_

- [x] **Settings OAuth callback: verify capability/nonce before exchanging the code, and
  check the token response status.** Capability + nonce checks were already correctly
  ordered before the exchange. Added `200 === wp_remote_retrieve_response_code()` guard
  so a 4xx/5xx Google response is never stored as a token.
  ([class-ndizi-admin.php](includes/class-ndizi-admin.php)) _(branch: ndizi/fable-review)_

- [x] **Google Calendar token refresh has no error handling and runs synchronously on save.**
  Added HTTP status code check and `error_log()` calls in `get_access_token()` for
  WP_Error, non-200 responses, and missing access_token — failures are now diagnosable.
  Async dispatch remains a P2 item.
  ([class-ndizi-calendar.php](includes/class-ndizi-calendar.php)) _(branch: ndizi/fable-review)_

- [x] **CLI commands have no permission gate and accept `--user`.**
  `start` and `stop` now check `ndizi_manage_time` when `--user` targets a different
  user than the caller; updated docblocks document the trust model.
  ([class-ndizi-cli.php](includes/class-ndizi-cli.php)) _(branch: ndizi/fable-review)_

## P2 — Architecture (do these while the plugin is still pre-1.0)

- [ ] **Store time entries in UTC, not site-local time.** `Ndizi_DB` writes
  `current_time( 'mysql' )` (site-local) into `start_time`/`end_time`
  ([class-ndizi-db.php:83-98](includes/class-ndizi-db.php#L83-L98)), then code elsewhere
  mixes `strtotime()` (server TZ) and `gmdate()` over those values (iCal feed, manual-log
  estimation). This is a schema-level convention that gets exponentially harder to change
  once real billing data accumulates — switch to UTC storage
  (`current_time( 'mysql', true )`) with conversion at the display layer **before 1.0**.

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

- [ ] **Make the module system own its pieces.** Module gating is currently scattered and
  leaky: Stripe payment/webhook routes register unconditionally in the always-loaded
  `Ndizi_REST` even when the `invoicing` module is off
  ([class-ndizi-rest.php:202-233](includes/class-ndizi-rest.php#L202-L233)); the `gantt`
  module flag exists but gantt code lives unconditionally in `Ndizi_Admin`;
  `Ndizi_Calendar` (a Google integration) is always loaded. Define a small module
  registry where each module registers its own includes, hooks, and REST routes, so
  `is_module_active()` checks live in one place.

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

- [x] **Decouple portal-token authentication into one helper.**
  Made `Ndizi_Portal::get_client_id_by_token()` public (was private). Replaced the
  inline `get_posts` meta-query in the iCal feed and the manual
  get_post_meta/hash_equals chain in `check_invoice_pay_permission()` with calls to
  this shared method. All three lookup paths now go through one function.
  A separate calendar token and admin key-rotation UI remain future work.
  ([class-ndizi-portal.php](includes/class-ndizi-portal.php), [class-ndizi-rest.php](includes/class-ndizi-rest.php))
  _(branch: ndizi/fable-review)_

- [x] **Support defining secrets as constants.** Added `Ndizi_Project_Management::get_secret()`
  which checks `defined('NDIZI_<OPTION_NAME_UPPERCASED>')` before falling back to
  `get_option()`. Wired it into all six secret reads: `ndizi_stripe_secret_key`,
  `ndizi_stripe_publishable_key`, `ndizi_stripe_webhook_secret`,
  `ndizi_google_client_id`, `ndizi_google_client_secret`, `ndizi_google_refresh_token`.
  Settings UI shows a "Set via constant" notice (and hides the input) when a constant
  is defined. Documented in API-AUTHENTICATION.md.
  ([Ndizi.php](Ndizi.php), [class-ndizi-rest.php](includes/class-ndizi-rest.php),
  [class-ndizi-calendar.php](includes/class-ndizi-calendar.php),
  [class-ndizi-portal.php](includes/class-ndizi-portal.php),
  [class-ndizi-admin.php](includes/class-ndizi-admin.php))
  _(branch: ndizi/fable-review)_

## P3 — Code quality, performance, reliability

- [x] **N+1 queries in list endpoints.** Added `update_meta_cache()` before all three
  per-row meta loops and `_prime_post_caches()` before per-row `get_post()` calls in
  `Ndizi_REST::get_projects()`, `Ndizi_REST::get_tasks()`, and the admin-bar tracker
  data AJAX (including the assigned-tasks → allowed-projects loop in
  `ajax_log_time_manual()`).
  ([class-ndizi-rest.php](includes/class-ndizi-rest.php), [class-ndizi-admin-bar.php](includes/class-ndizi-admin-bar.php))
  _(branch: ndizi/fable-review)_

- [ ] **Unbounded queries.** `posts_per_page => -1` throughout REST/iCal, and
  `Ndizi_DB::get_time_entries()` defaults to `number => -1`
  ([class-ndizi-db.php:360](includes/class-ndizi-db.php#L360)). Fine at boutique scale,
  but add sane defaults/pagination on REST endpoints before the API is documented as
  stable.

- [x] **Outbound webhooks: add timeout, logging, and basic SSRF guard.**
  Replaced `filter_var(..., FILTER_VALIDATE_URL)` with `wp_http_validate_url()` on both
  custom webhook and Slack URLs — rejects loopback/private-range targets. Timeout (5 s)
  was already present. Added `error_log()` when a URL is blocked so silent drops are
  diagnosable. (Non-blocking fire-and-forget is preserved; retries remain P2.)
  ([class-ndizi-webhooks.php](includes/class-ndizi-webhooks.php)) _(branch: ndizi/fable-review)_

- [x] **Duplicate event fan-out between Notifications and Webhooks.** After each webhook
  dispatch in `handle_meta_change()`, `Ndizi_Webhooks` now fires canonical actions:
  `ndizi_task_assigned($task_id, $assignee_id)` and
  `ndizi_task_status_changed($task_id, $new_status_key, $old_status_key)`.
  `Ndizi_Notifications` no longer hooks `added_post_meta`/`updated_post_meta` directly;
  it listens to these canonical actions instead, eliminating the duplicate
  old-value capture and meta-key detection.
  ([class-ndizi-webhooks.php](includes/class-ndizi-webhooks.php), [class-ndizi-notifications.php](includes/class-ndizi-notifications.php))
  _(branch: ndizi/fable-review)_

- [x] **Replace deprecated `meta_key`/`meta_value` query args with `meta_query`**
  (~[class-ndizi-admin.php:318](includes/class-ndizi-admin.php#L318)) and remove inline
  `onmouseover`/`onmouseout` style handlers on dashboard quick actions and icon-picker
  cards in favor of CSS `:hover` / `input:checked + .ndizi-icon-card` — those break
  under a strict CSP. SCSS compiled to `build/admin.css`.
  ([class-ndizi-admin.php](includes/class-ndizi-admin.php), [src/admin/admin-style.scss](src/admin/admin-style.scss))
  _(branch: ndizi/fable-review)_

- [x] **Chrome extension: validate legacy credential migration and document storage risk.**
  Migration path at [chrome-extension/popup.js:72-86](chrome-extension/popup.js#L72-L86)
  now validates `authHeader` is a non-empty, well-formed `Basic <base64>` string before
  migrating. Added section 3 to API-AUTHENTICATION.md documenting that `chrome.storage.local`
  is unencrypted, recommending dedicated revocable Application Passwords, and noting the
  future `wp-config.php` constant override for secrets.
  ([chrome-extension/popup.js](chrome-extension/popup.js), [API-AUTHENTICATION.md](API-AUTHENTICATION.md))
  _(branch: ndizi/fable-review)_

- [x] **`Ndizi_Roles::current_user_can()` admin special-case is redundant** — removed
  the `manage_options` early-return; activation already grants all ndizi caps to the
  administrator role, so WordPress handles the shortcut naturally and missing caps now
  surface in testing.
  ([class-ndizi-roles.php](includes/class-ndizi-roles.php)) _(branch: ndizi/fable-review)_

## P4 — Packaging / wp.org release readiness

- [ ] **Stable tag mismatch.** `readme.txt` says `Stable tag: 1.0.0-alpha` while the
  changelog's latest entry is `1.0.0-alpha.2`. Align them; for the actual wp.org launch,
  ship a clean `1.0.0` (the directory treats stable tag as a literal SVN tag name).

- [ ] **No `.pot` file in `languages/`.** Add `wp i18n make-pot . languages/ndizi-project-management.pot`
  to the build script (i18n coverage in the code itself is already excellent).

- [ ] **Add a LICENSE file** (GPL-2.0-or-later is declared in headers/composer but no
  full-text file ships).

- [ ] **Add automated tests.** Nothing exists today. Highest-value first targets:
  `Ndizi_DB` lock-date/approval/duration logic (pure, easy to test), Stripe webhook
  signature verification, and the REST permission callbacks. `wp-env` + PHPUnit or the
  Playground blueprint already in-repo can host integration runs.

---

## Resolved

_(move checked items here with date + commit)_
