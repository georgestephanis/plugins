# AGENTS.md

Guidance for AI coding agents working on the **GS Plugin Support Manager** plugin.

## Technical Overview

**GS Plugin Support Manager** is a WordPress plugin that monitors WordPress.org support forum RSS feeds across plugins and themes. It aggregates topics into a unified dashboard, tracks read/unread states, dispatches email/webhook alerts for new topics, and exposes a custom REST API endpoint for subscribing via external RSS readers (such as NetNewsWire, Feedly, or Apple Mail).

### Core Architecture

The plugin is structured with a central Singleton manager (`GS_Support_Manager`) that bootstraps component submodules:

1. **`GS_Support_Manager`** (`includes/class-gs-support-manager.php`) — Core Singleton orchestrator. Manages options storage, lifecycle hooks, WP-Cron scheduling, and helper methods.
2. **`GS_Support_Feed_Fetcher`** (`includes/class-gs-support-feed-fetcher.php`) — RSS parser leveraging WordPress core `fetch_feed()`. Builds WP.org plugin (`/support/plugin/{slug}/feed/`) and theme (`/support/theme/{slug}/feed/`) feed URLs, parses SimplePie items, identifies newly discovered topics, and dispatches notifications.
3. **`GS_Support_Notifier`** (`includes/class-gs-support-notifier.php`) — Handles email digests via `wp_mail()` and webhook HTTP POST notifications via `wp_remote_post()`.
4. **`GS_Support_Admin_UI`** (`includes/class-gs-support-admin-ui.php`) — Registers the admin page under **Tools > Plugin Support**. Provides the Unified Feed view, Monitored Plugins & Themes management, WP.org Profile Import form, Settings configuration, and AJAX read/unread toggle handler.
5. **`GS_Support_REST_API`** (`includes/class-gs-support-rest-api.php`) — Registers `/wp-json/gs-support-manager/v1/feed` providing RSS 2.0 XML or JSON output for feed reader subscriptions.

---

## Codebase Directory Layout

- **`gs-plugin-support-manager.php`** — Main entry point file. Defines constants (`GS_PSM_VERSION`, `GS_PSM_PATH`, `GS_PSM_URL`), requires class files, and initializes `gs_support_manager()`.
- **`readme.txt`** — WordPress.org standard documentation header, plugin description, installation steps, FAQs, and changelog.
- **`README.md`** — GitHub repository documentation with WordPress Playground launch badge, feature breakdown, and developer usage notes.
- **`AGENTS.md`** — Developer and AI agent reference guide (this file).
- **`phpcs.xml`** — Per-plugin PHPCS ruleset configured with `WordPress-Extra` and `WordPress-Docs` standards.
- **`playground/`**
  - **`blueprint.json`** — WordPress Playground blueprint configured with a `git:directory` resource pulling the plugin from the monorepo, populating sample monitored items (`woocommerce` plugin and `twentytwentyfour` theme), running an initial RSS sync, and opening directly on **Tools > Plugin Support**.
- **`includes/`**
  - **`class-gs-support-manager.php`** — Main orchestrator class (`GS_Support_Manager`).
  - **`class-gs-support-feed-fetcher.php`** — RSS feed fetcher and SimplePie parser (`GS_Support_Feed_Fetcher`).
  - **`class-gs-support-notifier.php`** — Email and Webhook notification dispatcher (`GS_Support_Notifier`).
  - **`class-gs-support-admin-ui.php`** — Admin menu pages, forms, tables, and AJAX handlers (`GS_Support_Admin_UI`).
  - **`class-gs-support-rest-api.php`** — Custom REST API endpoint provider for RSS XML and JSON (`GS_Support_REST_API`).

---

## Data Storage Strategy

Data is stored efficiently in standard WordPress options (`wp_options`), keeping DB overhead minimal:

1. **`gs_psm_monitored_plugins`** (Array) — List of monitored plugins and themes indexed by key (`type:slug`):
   ```php
   array(
       'plugin:woocommerce' => array(
           'key'        => 'plugin:woocommerce',
           'slug'       => 'woocommerce',
           'type'       => 'plugin',
           'label'      => 'WooCommerce',
           'added_at'   => 1753116300,
           'last_sync'  => 1753119900,
           'item_count' => 12,
       ),
   );
   ```

2. **`gs_psm_settings`** (Array) — Configuration options:
   ```php
   array(
       'sync_interval'    => 'hourly', // 'hourly', 'twicedaily', 'daily'
       'enable_email'     => 1,
       'email_recipients' => 'admin@example.com',
       'enable_webhook'   => 0,
       'webhook_url'      => 'https://hooks.slack.com/services/...',
       'max_stored_items' => 500,
   );
   ```

3. **`gs_psm_feed_items`** (Array) — Cached support topics map indexed by MD5 hash (`md5(type_slug_guid)`), automatically capped to `max_stored_items` (default 500) sorted by publication date:
   ```php
   array(
       'id_hash' => array(
           'id'          => 'hash_string',
           'guid'        => 'https://wordpress.org/support/topic/...',
           'plugin_slug' => 'woocommerce',
           'item_type'   => 'plugin',
           'title'       => 'Issue with checkout page',
           'link'        => 'https://wordpress.org/support/topic/...',
           'author'      => 'username',
           'pub_date'    => 1753119900,
           'description' => '<p>Topic description content...</p>',
           'read'        => false,
           'first_seen'  => 1753119900,
       ),
   );
   ```

---

## WordPress.org Profile Import Feature

Users can paste a WordPress.org Profile URL (e.g., `https://profiles.wordpress.org/username/`) or raw username into the Profile Import form.

1. **URL Parsing**: `GS_Support_Manager::extract_username_from_profile_url()` extracts the clean username string.
2. **Plugins API Query**: `wp_remote_get()` calls `https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[author]={username}&request[per_page]=100`.
3. **Themes API Query**: `wp_remote_get()` calls `https://api.wordpress.org/themes/info/1.1/?action=query_themes&request[author]={username}&request[per_page]=100`.
4. **Auto-Population**: All returned items are added to `gs_psm_monitored_plugins` and an immediate sync is executed via `GS_Support_Feed_Fetcher::sync_all()`.

---

## Coding Conventions & Quality Standards

1. **WordPress Coding Standards**: All PHP code strictly complies with WPCS (`WordPress-Extra`, `WordPress-Docs`).
2. **Security & Escaping**:
   - Capability checks using `current_user_can('manage_options')` for all admin actions.
   - Nonce checks using `wp_verify_nonce()` and `check_ajax_referer()`.
   - Output escaping using `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_kses_post()`.
   - Input sanitization using `sanitize_text_field()`, `sanitize_title()`, `sanitize_email()`, `esc_url_raw()`, and `absint()`.
3. **Internationalization (i18n)**:
   - Text domain: `'gs-plugin-support-manager'`.
   - All string translation calls containing placeholders (`%s`, `%d`) MUST include a `/* translators: ... */` comment directly above the function call.
4. **Date Formatting**:
   - Use `gmdate()` instead of `date()` for UTC timestamps to avoid timezone offset warnings.
