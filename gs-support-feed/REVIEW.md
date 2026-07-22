# Code Review — GS Support Feed

Reviewed against the [WordPress.org Plugin Guidelines](https://github.com/WordPress/wporg-plugin-guidelines) (the 18 detailed guidelines) plus general WPCS/security/Plugin Check expectations. Version reviewed: 1.0.0.

## Guideline compliance — summary

Strong compliance overall. Highlights:

- **License (Guideline 1)**: `GPL-2.0-or-later` header matches readme. No bundled third-party libraries; jQuery is enqueued via `wp_enqueue_script( 'jquery' )` rather than bundled (Guideline 13).
- **No obfuscated/unreadable code (Guideline 4)**: clean, readable, well-documented PHP throughout.
- **External Services disclosure (Guidelines 6/7)**: `readme.txt` already has a proper `== External Services ==` section covering the wp.org support-feed fetches, the wp.org Plugins/Themes API author lookups, and user-configured webhooks — including what data is transmitted. This is done better than most submissions.
- **No remote code execution**: no `eval()`, `create_function()`, `base64_decode()`-as-obfuscation, or dynamic `include`/`extract()` patterns found anywhere in the plugin.
- **No admin dashboard hijacking via iframes (Guideline 11)**: uses native WP APIs (`fetch_feed()`, `wp_remote_get()`, REST API) exclusively.
- **Trademark (Guideline 17)**: plugin name/slug don't infringe on WordPress or third-party trademarks.
- **Stable version (Guideline 3)**: `Stable tag: 1.0.0` in readme matches the `Version:` header.

No hard guideline violations found.

## Findings / recommendations

All findings below have been resolved.

### 1. ~~Inline `<script>` and heavy inline CSS should move to enqueued assets~~ RESOLVED
`includes/class-gs-support-admin-ui.php`

- `render_inline_assets()` (~L742-790) echoed a full jQuery block (select-all checkbox, AJAX toggle-read, profile-import spinner) directly into the page, with the nonce and translated strings interpolated via `esc_js()`.
- Dozens of `style="..."` attributes across `render_dashboard_tab()`, `render_plugins_tab()`, and `render_settings_tab()` (flex layouts, badge colors, table widths, etc.).

**Fix applied**: `render_inline_assets()` removed entirely; script moved to `assets/js/admin.js`, enqueued via `wp_enqueue_script()` in `enqueue_assets()`, with the nonce/strings passed via `wp_localize_script( 'gs-support-feed-admin', 'gsSupportFeed', ... )`. All inline `style="..."` attributes replaced with classes in `assets/css/admin.css`.

### 2. ~~Webhook URL has no SSRF hardening~~ RESOLVED
`includes/class-gs-support-notifier.php::send_webhook_notification()`, `includes/class-gs-support-admin-ui.php` (`webhook_url` setting)

The admin-configured webhook URL was sanitized with `esc_url_raw()` but not validated against scheme (`http`/`https` only) or against resolving to internal/loopback/link-local addresses before `wp_safe_remote_post()` fires on every cron sync.

**Fix applied**: new `GS_Support_Manager::is_safe_webhook_url()` validates scheme (http/https only), resolves the hostname via `dns_get_record()`, and rejects private/reserved-range IPs (`FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`). Enforced both at save time (`save_settings` rejects unsafe URLs and surfaces a `webhook_invalid` admin notice) and at send time in the notifier, for defense in depth.

### 3. ~~Feed items option is unbounded-until-synced and autoloaded~~ RESOLVED
`includes/class-gs-support-manager.php::get_feed_items()` / `save_feed_items()`

`gs_sf_feed_items` is a single option that can grow up to `max_stored_items` (configurable to 2000) entries, each carrying full HTML descriptions.

**Fix applied**: both `get_feed_items()` and `save_feed_items()` now call `update_option( self::ITEMS_OPTION, $items, false )` so the option is never autoloaded.

### 4. ~~Auto-discovered/added items aren't verified as wp.org-hosted before first sync~~ RESOLVED
`includes/class-gs-support-admin-ui.php` (`import_installed` action)

`import_installed` added every locally installed plugin/theme folder slug as a monitored item without checking whether a matching wp.org support feed actually exists.

**Fix applied**: new `GS_Support_Manager::is_plugin_hosted_on_wporg()` / `is_theme_hosted_on_wporg()` (via `plugins_api()` / `themes_api()`) gate `import_installed`; skipped items are counted and surfaced via a "N installed items are not hosted on WordPress.org and were not added" admin notice.

### 5. ~~Minor: `email_recipients` not validated at save time~~ RESOLVED
`includes/class-gs-support-admin-ui.php` (`save_settings` action)

Recipients were only filtered through `is_email()` at send time, not when saved.

**Fix applied**: `save_settings` now filters submitted recipients through `is_email()` before persisting and surfaces an "N email address(es) were invalid and were not saved" admin notice when any are dropped.

## Re-check against the official `wp-plugin-directory-guidelines` skill

Re-audited using the official [wp-plugin-directory-guidelines](https://github.com/WordPress/agent-skills/blob/trunk/skills/wp-plugin-directory-guidelines/SKILL.md) checklist after the fixes above. No new violations found:

- **Guideline 1 (GPL)**: main file header has valid `License: GPL-2.0-or-later` + `License URI`; no bundled third-party code.
- **Guideline 5 (Trialware)**: no license/upgrade/premium gating of any kind — plugin has no paid tier.
- **Guideline 6/7 (SaaS/data collection)**: `activate()` only initializes local options and schedules cron — no outbound request fires on activation without explicit admin action. All outbound endpoints (wp.org feeds, wp.org Plugins/Themes API, user-configured webhook) are documented in the `== External Services ==` section, including the `plugins_api()`/`themes_api()` calls added for the wp.org-hosting check in this pass.
- **Guideline 8 (No remotely loaded executable code)**: `admin.css`/`admin.js` are both enqueued from `plugins_url()` (local, bundled), not a CDN; no dynamic `<script>`/`eval()`/remote-include patterns found.
- **Guideline 10 (No forced external links)**: no "Powered by" credit links or undismissable upsell UI.
- **Guideline 11 (No iframe hijacking)**: no `<iframe>` usage anywhere in the plugin.
- **Guideline 13 (No bundled core-library duplicates)**: jQuery is enqueued via the core `jquery` handle, not a bundled copy; no other core-shipped libraries duplicated.
- **Guideline 14/15 (SVN/Stable version)**: `Stable tag: 1.0.0` matches `Version: 1.0.0`.
- **Guideline 17 (Trademarks/naming)**: name/slug don't reference or infringe third-party trademarks.

## Not an issue (checked, no action needed)

- Nonce + capability checks present on all state-changing admin actions and the AJAX handler (`check_ajax_referer` + `current_user_can`).
- All dynamic output is escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, `esc_xml` in the REST/RSS output) — including the one `phpcs:ignore` for the static, non-user-controlled SVG icon, which is correctly justified inline.
- `ABSPATH` guard present in every file.
- Text domain matches plugin slug (`gs-support-feed`); no manual `load_plugin_textdomain()` call, which is correct/unnecessary since WP 4.6 auto-loads translations for wp.org-hosted plugins.
- `readme.txt` `Tested up to: 7.0` is accurate for current WP core.
