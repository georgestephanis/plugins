# PR #11 Review Compilation

Source: https://github.com/georgestephanis/plugins/pull/11 — "Ndizi Features Additions"

Reviews from: **Gemini Code Assist** and **GitHub Copilot**

---

## Critical / High Priority

### 1. `strtotime()` failure not handled — date locking broken for invalid input
**Files:** `includes/class-ndizi-db.php:485`
**Both reviewers flagged this.**

`strtotime()` returns `false` on an invalid date string. In PHP, `false` casts to `0`, so `$check_time <= $lock_time` evaluates to `true` — meaning an invalid date would be incorrectly treated as locked. Conversely, an invalid lock date setting could silently disable locking entirely.

**Fix:** Explicitly check both return values before comparing:
```php
$lock_time  = strtotime( $lock_date . ' 23:59:59' );
$check_time = strtotime( $date_string );

if ( false === $lock_time || false === $check_time ) {
    return false;
}

return $check_time <= $lock_time;
```

---

## Medium Priority

### 2. Falsy billing rate check blocks explicit `0` rates (PHP — 5 locations)
**Files:**
- `includes/class-ndizi-admin.php:1064` (`$resolved_rate`)
- `includes/class-ndizi-admin.php:1588` (`$entry_rate`)
- `includes/class-ndizi-integrations.php:440` (`$entry_rate`)
- `includes/class-ndizi-integrations.php:685` (`$billing_rate`)
- `includes/class-ndizi-integrations.php:762` (`$billing_rate`)
**Gemini flagged all five.**

The hierarchical rate resolution currently uses `! $resolved_rate` (or equivalent) to decide whether to fall through to the next level. Since `0` is falsy in PHP, a task or project explicitly set to a free (`0.00`) rate will incorrectly fall back to the user or project default rate.

**Fix:** Initialize to `''` and use strict `'' === $var` checks at each level, then resolve to float at the end:
```php
$billing_rate = '';
if ( $entry->task_id ) {
    $billing_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
}
if ( '' === $billing_rate && $entry->user_id ) {
    $billing_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
}
if ( '' === $billing_rate && $entry->project_id ) {
    $billing_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
}
$billing_rate = '' !== $billing_rate ? floatval( $billing_rate ) : 0.0;
```

### 3. Falsy billing rate check blocks explicit `0` rates (JavaScript)
**File:** `src/admin/index.js:316`
**Gemini flagged.**

`entryRate > 0` prevents a rate of `0` from being used, forcing fallback to `defaultRate` for any free/pro-bono task.

**Fix:** Check presence of the attribute rather than its value:
```javascript
const entryRateAttr = $( this ).attr( 'data-rate' );
const rate =
    entryRateAttr !== undefined && entryRateAttr !== ''
        ? parseFloat( entryRateAttr )
        : defaultRate;
```

### 4. Negative billing/salary rates not prevented (3 locations)
**Files:**
- `includes/class-ndizi-admin.php:1205` (project hourly rate)
- `includes/class-ndizi-admin.php:1230` (task hourly rate)
- `includes/class-ndizi-admin.php:2285` (user billing and salary rates)
**Gemini flagged.**

No floor validation prevents negative values from being saved, which would produce nonsensical invoice totals.

**Fix:** Wrap `floatval()` in `max( 0.0, ... )` at save time:
```php
update_post_meta( $post_id, '_ndizi_project_hourly_rate', max( 0.0, floatval( $_POST['ndizi_project_hourly_rate'] ) ) );
```

### 5. `updated_post_meta` hook registered with 4 args — `$_meta_value_prev` never populated
**Files:**
- `includes/class-ndizi-notifications.php:102` (status-change emails never send)
- `includes/class-ndizi-webhooks.php:308` (old/new value comparison broken; guard ineffective)
**Copilot flagged both.**

The `updated_post_meta` hook is registered with `accepted_args = 4`, but the WordPress hook signature for `updated_post_meta` is `( $meta_id, $object_id, $meta_key, $meta_value )` — the *previous* value is not passed by WordPress at this hook point. So `$_meta_value_prev` is always `''`:
- In notifications: `! empty( $old_status )` is always false → status-change emails never fire.
- In webhooks: `$_meta_value === $_meta_value_prev` is never true (both would need to be `''`) and old/new value payloads in dispatched events will be incorrect.

**Fix options:**
- Use `update_{meta-type}_metadata` (which fires before the update and can capture the previous value), or
- Fetch the previous value manually inside the handler via `get_post_meta()` before any update, or
- Restructure to hook into the DB layer where old values are already retrieved (e.g., from `class-ndizi-db.php` CRUD actions).

### 6. Global `added_post_meta`/`updated_post_meta` hooks check post type before meta key
**Files:**
- `includes/class-ndizi-notifications.php:81` (`handle_added_post_meta`)
- `includes/class-ndizi-notifications.php:105` (`handle_updated_post_meta`)
- `includes/class-ndizi-webhooks.php:299` (`handle_added_post_meta`)
- `includes/class-ndizi-webhooks.php:309` (`handle_updated_post_meta`)
**Gemini flagged all four.**

These hooks fire on every meta operation across the entire site. The current implementation calls `get_post_type( $object_id )` first, which is a relatively expensive DB lookup. The meta key comparison is a cheap string check and should be the first guard.

**Fix:** Move the `$meta_key` check before the `get_post_type()` call:
```php
public static function handle_added_post_meta( $mid, $object_id, $meta_key, $_meta_value ) {
    if ( '_ndizi_assigned_user_id' !== $meta_key ) {
        return;
    }
    if ( 'ndizi_task' !== get_post_type( $object_id ) ) {
        return;
    }
    // ...
}
```

### 7. Invalid `viewport` meta tag in invoice print template
**File:** `includes/class-ndizi-integrations.php:117`
**Copilot flagged.**

`initial-scale=device-width` is not a valid value — `initial-scale` expects a numeric value. This can break responsive rendering in mobile browsers.

**Fix:**
```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

### 8. Idle warning banner uses hard-coded English text and inline styles
**File:** `src/adminbar/index.js:507`
**Copilot flagged.**

The 8-hour idle warning banner is built with concatenated HTML containing hard-coded English strings and inline styles, while the rest of the admin bar UI sources its labels from `ndizi_adminbar.labels`. This makes the warning untranslatable and harder to maintain.

**Fix:** Add the warning string to the `ndizi_adminbar` localized data (PHP side), then reference `ndizi_adminbar.labels.idle_warning` in JS. Render/toggle via a CSS class rather than injecting raw HTML.

### 9. WP-CLI `--project` and `--task` title lookups don't work
**File:** `includes/class-ndizi-cli.php:232, 264`
**Copilot flagged both.**

`get_posts()` / `WP_Query` does not support a `title` query argument — the key is silently ignored. As a result, `wp ndizi time start --project="My Project"` will return an unrelated or arbitrary post rather than the intended one, making the CLI commands unreliable unless an ID is passed.

**Fix options:**
- Use `wpdb->get_var()` with `WHERE post_title = %s AND post_type = 'ndizi_project'`, or
- Add a note in the CLI command docs that `--project` / `--task` only accept IDs, or
- Use `WP_Query` with `s` (search) if approximate matching is acceptable.

### 10. Internal client sort hard-codes `'Internal'` — breaks i18n
**File:** `src/adminbar/index.js:405`
**Copilot flagged.**

The `client_name` falls back to `ndizi_adminbar.labels.internal_client`, but the sort logic that pushes internal clients to the bottom of the list hard-codes the English string `'Internal'`. In non-English locales, or if the internal label is customized, the sort will break.

**Fix:** Compare `client_name` against `ndizi_adminbar.labels.internal_client` rather than the literal `'Internal'`:
```javascript
if ( client_name === ndizi_adminbar.labels.internal_client ) { ... }
```

---

## Summary Table

| # | Severity | File(s) | Issue | Source |
|---|----------|---------|-------|--------|
| 1 | High | `class-ndizi-db.php:485` | `strtotime()` false not guarded | Both |
| 2 | Medium | `class-ndizi-admin.php:1064,1588`, `class-ndizi-integrations.php:440,685,762` | Falsy rate check blocks rate=0 (PHP) | Gemini |
| 3 | Medium | `src/admin/index.js:316` | `entryRate > 0` blocks rate=0 (JS) | Gemini |
| 4 | Medium | `class-ndizi-admin.php:1205,1230,2285` | Negative rates not prevented | Gemini |
| 5 | Medium | `class-ndizi-notifications.php:102`, `class-ndizi-webhooks.php:308` | `updated_post_meta` prev value never set | Copilot |
| 6 | Medium | `class-ndizi-notifications.php:81,105`, `class-ndizi-webhooks.php:299,309` | Post type checked before meta key (perf) | Gemini |
| 7 | Medium | `class-ndizi-integrations.php:117` | Invalid `initial-scale=device-width` | Copilot |
| 8 | Medium | `src/adminbar/index.js:507` | Idle warning banner not localized | Copilot |
| 9 | Medium | `class-ndizi-cli.php:232,264` | `get_posts()` ignores `title` arg | Copilot |
| 10 | Low | `src/adminbar/index.js:405` | Internal client sort hard-codes `'Internal'` | Copilot |
