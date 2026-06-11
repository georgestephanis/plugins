# REVIEW.md — ndizi-project-management

Issues identified in PR [#10](https://github.com/georgestephanis/plugins/pull/10) reviews (Gemini Code Assist + GitHub Copilot, 2026-06-11).

---

## Critical / High

### 1. Fatal: wrong callback name for Standalone Tracker submenu
**File:** `includes/class-ndizi-admin.php:187`
**Reviewer:** Gemini, Copilot

`register_admin_pages()` registers the submenu with callback `Ndizi_Standalone_Tracker::render_standalone_tracker_page`, but the method is named `render_standalone_page`. This causes a PHP fatal error when WordPress tries to render the page (even if an `admin_init` redirect usually intercepts it — that interception can change).

**Fix:** Change the callback to `array( 'Ndizi_Standalone_Tracker', 'render_standalone_page' )`.

---

### 2. Fatal: `render_standalone_page` is `private`
**File:** `includes/class-ndizi-standalone-tracker.php:184`
**Reviewer:** Gemini

The method is declared `private static`, preventing WordPress from calling it as a page callback from outside the class.

**Fix:** Change to `public static`.

---

### 3. N+1 query pattern in `ajax_get_tracker_data()`
**File:** `includes/class-ndizi-admin-bar.php:377`
**Reviewer:** Gemini, Copilot (both flagged this independently)

A `SELECT SUM(duration) ... WHERE task_id = %d` query is executed inside a per-task loop. For projects with many tasks this degrades performance significantly.

**Fix:** Collect all `$task_ids` from the task loop, then fetch totals in a single batched query (`WHERE task_id IN (...) GROUP BY task_id`), and map results back to tasks before building the response array. Gemini's suggested code is a good reference.

---

### 4. Missing project-level authorization in `ajax_log_time()`
**File:** `includes/class-ndizi-admin-bar.php:427`
**Reviewer:** Gemini (security-high)

Non-manager users can log time against any `$project_id` by manipulating the AJAX payload — there is no check that the project is one the user is actually assigned to.

**Fix:** If the current user lacks `ndizi_manage_projects`, verify that they have at least one task on the target project (via `_ndizi_project_id` meta). Gemini's suggested implementation covers this correctly.

---

### 5. JS-in-PHP `esc_html_e` inside single-quoted string literal
**File:** `includes/class-ndizi-standalone-tracker.php:1226`
**Reviewer:** Gemini

```php
if (!confirm('<?php esc_html_e( 'Are you sure...', 'ndizi-project-management' ); ?>')) {
```

A translated string containing a single quote (French, Italian, etc.) breaks the JS syntax.

**Fix:** Use `echo esc_js( __( '...', 'ndizi-project-management' ) )` instead of `esc_html_e`.

---

### 6. Broken PWA icon URLs — wrong base path in `plugins_url()`
**File:** `includes/class-ndizi-standalone-tracker.php:73`
**Reviewer:** Gemini

`plugins_url( 'build/icon-192.png', __FILE__ )` resolves relative to `includes/`, producing a URL like `.../includes/build/icon-192.png` instead of `.../build/icon-192.png`.

**Fix:** Use the `NDIZI_PLUGIN_URL` constant: `NDIZI_PLUGIN_URL . 'build/icon-192.png'`.

---

## Medium

### 7. XSS risk — unescaped server data interpolated into innerHTML
**File:** `includes/class-ndizi-standalone-tracker.php:1293`
**Reviewer:** Copilot

`itemHtml` is built with template literals that interpolate server-provided strings (project name, task name, description, time) directly into HTML via `.append(itemHtml)`. Values containing HTML characters will be interpreted as markup.

**Fix:** Escape interpolated values (e.g. via a small `escHtml()` helper), or construct DOM elements with `.text()` / `.textContent` for user-supplied values, keeping the `<em>` fallback for the empty-description case.

---

### 8. Zero budget treated as falsy — hidden UI row
**File:** `includes/class-ndizi-admin-bar.php:395` (PHP) and `src/adminbar/index.js:443` (JS)
**Reviewer:** Copilot (both files)

The PHP side uses `$budget ? $budget : null`, and the JS side uses `if ( selectedProject.budget )` — both treat `0` as absent, hiding the budget row on zero-budget projects.

**Fix (PHP):** Use `$budget !== '' ? $budget : null` (or whatever the empty-value sentinel is).
**Fix (JS):** Use `selectedProject.budget != null` (or `!== null && selectedProject.budget !== undefined`) instead of a truthiness check.

---

### 9. Missing `wp_unslash()` before sanitizing `$_POST['ndizi_adminbar_icon']`
**File:** `includes/class-ndizi-admin.php:62`
**Reviewer:** Copilot

WordPress adds magic slashes to request input. The nonce above this line is handled correctly, but the icon value is sanitized without first unslashing, which can store a subtly wrong value.

**Fix:** `sanitize_text_field( wp_unslash( $_POST['ndizi_adminbar_icon'] ) )`.

---

## Low / Polish

### 10. Hard-coded untranslatable strings in admin-bar JS
**File:** `src/adminbar/index.js:191`
**Reviewer:** Copilot

Strings like "Starting…", "No description", "Start Timer", and "Log Time" are hard-coded in the JS while other labels are already localized via `ndizi_adminbar.labels`.

**Fix:** Move these into the `labels` map (localized via `wp_localize_script` or `wp_add_inline_script`) or use `wp.i18n.__()`.

---

## Status

| # | Severity | Status |
|---|----------|--------|
| 1 | High (Fatal) | Fixed — 4a6bf37 |
| 2 | High (Fatal) | Fixed — 4a6bf37 |
| 3 | High (Perf) | Fixed — 2a1c0bc |
| 4 | Security-High | Fixed — a646f90 |
| 5 | High | Fixed — c9a2feb |
| 6 | High | Fixed — c9a2feb |
| 7 | Medium (XSS) | Fixed — c8c28fb |
| 8 | Medium | Fixed — 2f563f1 |
| 9 | Medium | Fixed — ffd5654 |
| 10 | Low | Fixed — 53af962 |
