# GS Plugin Support Manager

[![Try in WordPress Playground](https://img.shields.io/badge/Try%20in-WordPress%20Playground-3858e9?style=for-the-badge&logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/gs-plugin-support-manager/playground/blueprint.json)

**[▶ Launch Live Demo in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/gs-plugin-support-manager/playground/blueprint.json)**

**GS Plugin Support Manager** aggregates WordPress.org support forum RSS feeds across any number of monitored plugins and themes into a single, unified admin feed, email/webhook alert system, and RSS/JSON export endpoint.

If you maintain, support, or keep track of multiple WordPress plugins or themes, GS Plugin Support Manager saves you from checking individual support forums manually.

---

## Features

- **WordPress.org Profile Import**: Plug in any WordPress.org profile URL (e.g. `https://profiles.wordpress.org/username/`) or username to automatically discover and monitor all plugins and themes published by that author.
- **Monitored Plugins & Themes**: Track support feeds for any plugin (`https://wordpress.org/support/plugin/{slug}/feed/`) or theme (`https://wordpress.org/support/theme/{slug}/feed/`) hosted on WordPress.org by slug.
- **Auto-Discovery of Local Installed Items**: One-click import for all WordPress.org-hosted plugins (`get_plugins()`) and themes (`wp_get_themes()`) installed on your local WordPress site.
- **Unified Feed Dashboard**: Located under **Tools > Plugin Support**. View, search, filter by plugin/theme or read status, toggle read/unread states via AJAX without reloading, and apply bulk actions.
- **Background WP-Cron Sync**: Automatically fetches new support items on a configurable schedule (Hourly, Twice Daily, Daily) with a manual "Sync All Feeds Now" button.
- **Email Notifications**: Receive formatted HTML email digests sent via `wp_mail()` whenever new support topics are flagged.
- **Webhook Notifications**: Post instant HTTP POST JSON payloads to Slack, Discord, Zapier, Make, or custom HTTP endpoints.
- **Unified RSS & JSON Export Endpoint**: Subscribe to `/wp-json/gs-support-manager/v1/feed?format=rss` in your feed reader of choice (NetNewsWire, Feedly, Apple Mail, etc.).

---

## Interactive WordPress Playground Demo

Test out the plugin instantly in an isolated, in-browser WordPress environment without installing anything locally:

**[Launch Live Demo in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/gs-plugin-support-manager/playground/blueprint.json)**

The Playground blueprint:
1. Installs and activates `gs-plugin-support-manager` directly from the monorepo.
2. Pre-populates monitored items (`woocommerce` plugin and `twentytwentyfour` theme).
3. Executes an initial RSS feed fetch across WordPress.org support forums.
4. Redirects directly to **Tools > Plugin Support**.

---

## Configuration & Usage Guide

### 1. Adding Monitored Plugins & Themes

Navigate to **Tools > Plugin Support** and switch to the **Monitored Plugins & Themes** tab:

- **Import from Profile**: Paste a WordPress.org profile URL (`https://profiles.wordpress.org/username/`) or enter a username to import all plugins and themes authored by that user.
- **Add Single Item**: Select the type (**Plugin** or **Theme**), enter the slug (e.g. `woocommerce`, `twentytwentyfour`), and optionally set a custom display label.
- **Auto-Discover Installed**: Click **Import Installed Plugins & Themes** to automatically detect and monitor all WordPress.org items installed on the local site.

### 2. Notifications & Settings

Navigate to **Tools > Plugin Support > Settings & Notifications**:

- **Sync Frequency**: Choose how often WP-Cron runs the background feed sync (Hourly, Twice Daily, or Daily).
- **Max Stored Feed Items**: Limit how many cached topics to retain (default: 500 items).
- **Email Alerts**: Enable email notifications and provide comma-separated recipient addresses.
- **Webhook Alerts**: Enable webhooks and provide your HTTP/HTTPS webhook endpoint URL.

---

## REST API & Webhook Specifications

### REST API Feed Endpoint

Subscribers can consume their aggregated feed via the public REST API endpoint:

- **RSS 2.0 XML Feed**:  
  `GET /wp-json/gs-support-manager/v1/feed?format=rss`
- **JSON Feed**:  
  `GET /wp-json/gs-support-manager/v1/feed?format=json`

#### Query Parameters:
- `format`: `rss` (default) or `json`.
- `type`: Filter by `plugin` or `theme`.
- `plugin`: Filter by specific plugin or theme slug (e.g., `woocommerce`).
- `limit`: Maximum number of items to return (default: `50`).

### Webhook JSON Payload Schema

When new support topics are flagged, the plugin sends an HTTP POST request with `Content-Type: application/json` to your configured webhook URL:

```json
{
  "event": "gs_support_manager_new_items",
  "site_name": "My WordPress Site",
  "site_url": "https://example.com",
  "timestamp": "2026-07-21T16:50:00+00:00",
  "count": 1,
  "items": [
    {
      "id": "a1b2c3d4e5f6...",
      "item_type": "plugin",
      "plugin_slug": "woocommerce",
      "title": "Error on checkout page",
      "link": "https://wordpress.org/support/topic/error-on-checkout-page/",
      "author": "john_doe",
      "pub_date": "2026-07-21T16:45:00+00:00",
      "description": "I am experiencing an issue when placing an order..."
    }
  ]
}
```

---

## Code Quality & Development

The plugin adheres strictly to the WordPress Coding Standards (WPCS):

- **PHPCS Compliance**: Verified against `WordPress-Extra` and `WordPress-Docs` with zero errors and zero warnings.
- **Security**: Full input sanitization, output escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), capability checks (`manage_options`), and nonce verification (`wp_verify_nonce`, `check_ajax_referer`).

To run linting locally:

```bash
vendor/bin/phpcs --standard=gs-plugin-support-manager/phpcs.xml
```
