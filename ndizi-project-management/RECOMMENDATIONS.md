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

- [ ] **No sanitize callbacks on any `register_post_meta()` call.**
  Zero `sanitize_callback` arguments in [class-ndizi-cpts.php](includes/class-ndizi-cpts.php).
  Add `sanitize_text_field` / `absint` / `floatval` per field so meta is safe regardless
  of which entry point writes it.

- [ ] **Stripe webhook: check `payment_status` and add idempotency.**
  `handle_stripe_webhook()` ([class-ndizi-rest.php:824-832](includes/class-ndizi-rest.php#L824-L832))
  marks the invoice paid on `checkout.session.completed` without checking
  `session.payment_status === 'paid'` (async payment methods complete the session before
  funds settle) and without deduplicating events — a Stripe retry re-fires
  `ndizi_invoice_paid`, which can duplicate notifications/webhooks. Guard on
  `payment_status`, and skip processing when the invoice is already `paid` (or store the
  processed `event.id` in invoice meta). Signature verification itself is correct
  (HMAC-SHA256, `hash_equals`, 5-minute timestamp window).

- [ ] **Settings OAuth callback: verify capability/nonce before exchanging the code, and
  check the token response status.** In `Ndizi_Admin::save_settings_page()`
  (~[class-ndizi-admin.php:64-108](includes/class-ndizi-admin.php#L64-L108)) the Google
  OAuth `code` is extracted and exchanged before the `manage_options` check, and the
  `wp_remote_post()` token response is only checked with `is_wp_error()` — a 400/500 body
  can be stored as a token. Reorder: capability check → nonce/state check → exchange →
  validate HTTP 200 + presence of expected fields.

- [ ] **Google Calendar token refresh has no error handling and runs synchronously on save.**
  `Ndizi_Calendar::get_access_token()` ([class-ndizi-calendar.php:37-73](includes/class-ndizi-calendar.php#L37-L73))
  doesn't check `is_wp_error()` on the refresh call, and sync hooks fire inline on
  task/time-entry save — a slow Google endpoint stalls the save. Add error handling +
  logging now; consider moving sync to a queued/cron dispatch (see P2).

- [ ] **CLI commands have no permission gate and accept `--user`.**
  `wp ndizi time start --user=<anyone>` logs time as another user with no capability
  check ([class-ndizi-cli.php](includes/class-ndizi-cli.php)). CLI is trusted by
  convention, but since these commands honor `--user`, at minimum document the trust
  model in the command docblocks; ideally check `ndizi_manage_time` when acting on a
  user other than the current one.

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

- [ ] **Decouple portal-token authentication into one helper.** Client-token validation
  logic exists in `Ndizi_Portal::get_authenticated_client_id()`, in
  `Ndizi_REST::check_invoice_pay_permission()`
  ([class-ndizi-rest.php:635-660](includes/class-ndizi-rest.php#L635-L660)), and in the
  iCal feed's meta-query lookup ([class-ndizi-rest.php:856-873](includes/class-ndizi-rest.php#L856-L873)).
  One `Ndizi_Client_Auth::validate_token( $token )` used everywhere; also consider a
  separate per-client *calendar* token so the portal credential isn't reused in
  long-lived iCal subscription URLs, plus an admin UI to rotate keys.

- [ ] **Support defining secrets as constants.** Stripe secret/webhook keys and Google
  client secret + refresh token live unencrypted in `wp_options`. Standard WP practice:
  check `defined( 'NDIZI_STRIPE_SECRET_KEY' )` etc. before falling back to the option,
  so security-conscious installs can keep secrets in `wp-config.php` out of the DB and
  exports. Document it in API-AUTHENTICATION.md.

## P3 — Code quality, performance, reliability

- [ ] **N+1 queries in list endpoints.** `Ndizi_REST::get_projects()`/`get_tasks()` fetch
  client/project posts and meta per row in a loop
  ([class-ndizi-rest.php:326-340](includes/class-ndizi-rest.php#L326-L340)); the admin-bar
  tracker data AJAX does the same per-project client fetch. Collect IDs and prime caches
  (`_prime_post_caches()` / `update_meta_cache()`) before the loop.

- [ ] **Unbounded queries.** `posts_per_page => -1` throughout REST/iCal, and
  `Ndizi_DB::get_time_entries()` defaults to `number => -1`
  ([class-ndizi-db.php:360](includes/class-ndizi-db.php#L360)). Fine at boutique scale,
  but add sane defaults/pagination on REST endpoints before the API is documented as
  stable.

- [ ] **Outbound webhooks: add timeout, logging, and basic SSRF guard.**
  `Ndizi_Webhooks::dispatch()` is fire-and-forget (`blocking => false`) with no timeout
  and only `FILTER_VALIDATE_URL` on admin-entered URLs
  ([class-ndizi-webhooks.php:62-107](includes/class-ndizi-webhooks.php#L62-L107)).
  Use `wp_http_validate_url()` (rejects loopback/private ranges), set an explicit short
  timeout, and log failures so silent drops are diagnosable. Retries can wait.

- [ ] **Duplicate event fan-out between Notifications and Webhooks.** Both classes hook
  the same meta-update events independently; confirm the intended matrix (email vs Slack
  vs custom webhook per event) and centralize the event detection so a status change
  fires one canonical `do_action()` both consume.

- [ ] **Replace deprecated `meta_key`/`meta_value` query args with `meta_query`**
  (~[class-ndizi-admin.php:318](includes/class-ndizi-admin.php#L318)) and remove inline
  `onmouseover`/`onmouseout` style handlers on dashboard quick actions
  (~[class-ndizi-admin.php:442-472](includes/class-ndizi-admin.php#L442-L472)) in favor
  of CSS `:hover` — those break under a strict CSP.

- [ ] **Chrome extension: validate legacy credential migration and document storage risk.**
  Migration push at [chrome-extension/popup.js:72-86](chrome-extension/popup.js#L72-L86)
  doesn't validate `authHeader` before storing; Basic-auth credentials sit unencrypted in
  `chrome.storage.local` for every configured site. Add the validation guard and a note
  in API-AUTHENTICATION.md recommending dedicated, revocable Application Passwords.

- [ ] **`Ndizi_Roles::current_user_can()` admin special-case is redundant** — WordPress
  already grants admins everything when caps are added to the role (which activation
  does). Harmless, but it masks misconfiguration; consider removing the
  `manage_options` shortcut so missing caps surface in testing.

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
