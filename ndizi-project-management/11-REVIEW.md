# PR #11 Review Compilation

Source: https://github.com/georgestephanis/plugins/pull/11 — "Ndizi Features Additions"

Reviews from: **Gemini Code Assist** and **GitHub Copilot**

All items below have been resolved in the codebase.

---

## Resolved Items

### 1. `strtotime()` failure not handled — date locking broken for invalid input ✅
**Files:** `includes/class-ndizi-db.php:485`
**Both reviewers flagged this.**

**Fix applied:** `is_date_locked()` now explicitly checks both `strtotime()` return values and returns `false` when either parse fails, preventing invalid dates from being incorrectly treated as locked.

---

### 2. Falsy billing rate check blocks explicit `0` rates (PHP — 5 locations) ✅
**Files:**
- `includes/class-ndizi-admin.php:1064` (`$resolved_rate`)
- `includes/class-ndizi-admin.php:1588` (`$entry_rate`)
- `includes/class-ndizi-integrations.php:440` (`$entry_rate`)
- `includes/class-ndizi-integrations.php:685` (`$billing_rate`)
- `includes/class-ndizi-integrations.php:762` (`$billing_rate`)
**Gemini flagged all five.**

**Fix applied:** All five locations initialize the rate to `''` and use strict `'' === $var` checks at each hierarchy level, allowing an explicit `0.00` rate to propagate correctly instead of falling back to the next level.

---

### 3. Falsy billing rate check blocks explicit `0` rates (JavaScript) ✅
**File:** `src/admin/index.js:313`
**Gemini flagged.**

**Fix applied:** Rate resolution now checks attribute presence (`entryRateAttr !== undefined && entryRateAttr !== ''`) rather than truthiness, so a `data-rate="0"` entry correctly uses `0` instead of falling back to `defaultRate`.

---

### 4. Negative billing/salary rates not prevented (3 locations) ✅
**Files:**
- `includes/class-ndizi-admin.php:1204` (project hourly rate)
- `includes/class-ndizi-admin.php:1229` (task hourly rate)
- `includes/class-ndizi-admin.php:2278` (user billing and salary rates)
**Gemini flagged.**

**Fix applied:** All three save paths now wrap `floatval()` in `max( 0.0, ... )` to prevent negative values from being persisted.

---

### 5. `updated_post_meta` hook — previous value not available via 4-arg signature ✅
**Files:**
- `includes/class-ndizi-notifications.php`
- `includes/class-ndizi-webhooks.php`
**Copilot flagged both.**

**Fix applied:** Both classes hook into `update_post_metadata` (the pre-update filter) via `capture_old_task_meta()` to cache the existing value before the write, then retrieve it from the cache in `handle_updated_post_meta()`. Status-change emails and webhook old/new-value payloads now receive accurate previous values.

---

### 6. Global `added_post_meta`/`updated_post_meta` hooks check post type before meta key ✅
**Files:**
- `includes/class-ndizi-notifications.php` (`handle_added_post_meta`, `handle_updated_post_meta`)
- `includes/class-ndizi-webhooks.php` (`handle_added_post_meta`, `handle_updated_post_meta`)
**Gemini flagged all four.**

**Fix applied:** Both classes now guard on `$meta_key` as the very first check before calling `get_post_type()`, avoiding the relatively expensive post-type lookup for unrelated meta operations.

---

### 7. Invalid `viewport` meta tag in invoice print template ✅
**File:** `includes/class-ndizi-integrations.php:117`
**Copilot flagged.**

**Fix applied:** Viewport meta now correctly uses `initial-scale=1`.

---

### 8. Idle warning banner uses hard-coded English text and inline styles ✅
**File:** `src/adminbar/index.js:507`
**Copilot flagged.**

**Fix applied:** The JS-injected banner now uses `ndizi_adminbar.labels.idle_warning` (localized via PHP's `wp_localize_script`) and builds the DOM via jQuery node construction rather than raw HTML concatenation.

---

### 9. WP-CLI `--project` and `--task` title lookups don't work ✅
**File:** `includes/class-ndizi-cli.php`
**Copilot flagged both.**

**Fix applied:** Both lookups now use `$wpdb->get_var()` with a prepared `WHERE post_title = %s AND post_type = '...'` query, replacing the `get_posts()` call that silently ignored the `title` argument.

---

### 10. Internal client sort hard-codes `'Internal'` — breaks i18n ✅
**File:** `src/adminbar/index.js:405`
**Copilot flagged.**

**Fix applied:** The sort now captures `ndizi_adminbar.labels.internal_client` into a local `internalLabel` variable and compares against that instead of the hard-coded English string `'Internal'`.

---

## Summary Table

| # | Severity | File(s) | Issue | Status |
|---|----------|---------|-------|--------|
| 1 | High | `class-ndizi-db.php` | `strtotime()` false not guarded | ✅ Fixed |
| 2 | Medium | `class-ndizi-admin.php`, `class-ndizi-integrations.php` | Falsy rate check blocks rate=0 (PHP) | ✅ Fixed |
| 3 | Medium | `src/admin/index.js` | `entryRate > 0` blocks rate=0 (JS) | ✅ Fixed |
| 4 | Medium | `class-ndizi-admin.php` | Negative rates not prevented | ✅ Fixed |
| 5 | Medium | `class-ndizi-notifications.php`, `class-ndizi-webhooks.php` | `updated_post_meta` prev value unavailable | ✅ Fixed |
| 6 | Medium | `class-ndizi-notifications.php`, `class-ndizi-webhooks.php` | Post type checked before meta key (perf) | ✅ Fixed |
| 7 | Medium | `class-ndizi-integrations.php` | Invalid `initial-scale=device-width` | ✅ Fixed |
| 8 | Medium | `src/adminbar/index.js` | Idle warning banner not localized | ✅ Fixed |
| 9 | Medium | `class-ndizi-cli.php` | `get_posts()` ignores `title` arg | ✅ Fixed |
| 10 | Low | `src/adminbar/index.js` | Internal client sort hard-codes `'Internal'` | ✅ Fixed |
