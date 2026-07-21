=== GS Plugin Support Manager ===
Contributors: georgestephanis
Tags: support, rss, forum, plugin manager, notifications, profile import
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Monitored plugin and theme support forum aggregator for WordPress.org with profile import, email, webhook, and unified RSS feed notifications.

== Description ==

**GS Plugin Support Manager** aggregates WordPress.org support forum RSS feeds across any number of monitored plugins and themes into a single, unified admin feed and RSS/JSON endpoint.

Try the plugin instantly in your browser using [WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/gs-plugin-support-manager/playground/blueprint.json).

If you maintain, support, or keep track of multiple WordPress plugins or themes, GS Plugin Support Manager saves you from checking individual support forums manually.

### Key Features

* **WordPress.org Profile Import**: Plug in a WordPress.org profile URL (e.g. `https://profiles.wordpress.org/username/`) or username to automatically discover and monitor all plugins and themes published by that author.
* **Monitored Plugins & Themes**: Track support feeds for any plugin or theme hosted on WordPress.org by slug (`https://wordpress.org/support/plugin/{slug}/feed/` and `https://wordpress.org/support/theme/{slug}/feed/`).
* **Auto-Discovery**: One-click import for all WordPress.org-hosted plugins and themes installed on your local WordPress site.
* **Unified Dashboard**: View, search, filter by plugin/theme or status, and mark support topics as read or unread across all plugins and themes in one place.
* **Background WP-Cron Sync**: Automatically fetches new support items hourly, twice daily, or daily.
* **Email Notifications**: Receive customizable email digests sent via `wp_mail()` whenever new support topics are flagged.
* **Webhook Notifications**: Send instant JSON POST payloads to Slack, Discord, Zapier, Make, or custom HTTP endpoints.
* **Unified RSS & JSON Export Endpoint**: Subscribe to `/wp-json/gs-support-manager/v1/feed?format=rss` in your feed reader of choice (NetNewsWire, Feedly, Apple Mail, etc.).

== External Services ==

This plugin connects to external services to retrieve support forum feeds and author plugin listings:

1. **WordPress.org Support Forum Feeds**:
   - **URL**: `https://wordpress.org/support/plugin/{slug}/feed/` and `https://wordpress.org/support/theme/{slug}/feed/`
   - **Purpose**: Fetches public RSS feeds for monitored plugins and themes to populate the support feed.
   - **Data Transmitted**: Standard HTTP request headers (User-Agent, IP address). No site data or credentials are transmitted.
   - **Service Privacy Policy**: [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/)

2. **WordPress.org Plugins & Themes API**:
   - **URL**: `https://api.wordpress.org/plugins/info/1.2/` and `https://api.wordpress.org/themes/info/1.1/`
   - **Purpose**: Used during Profile Import to look up published plugins and themes for a specified author username.
   - **Data Transmitted**: Author username parameter.
   - **Service Privacy Policy**: [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/)

3. **User-Configured Notification Webhooks (Optional)**:
   - **URL**: Administrator-configured HTTP/HTTPS webhook URL (e.g., Slack, Discord, Zapier).
   - **Purpose**: Sends JSON POST payloads containing summary details of newly flagged support topics.
   - **Data Transmitted**: Site name, site URL, event timestamp, and summary data of newly flagged support topics.

== Installation ==

1. Upload the `gs-plugin-support-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Tools > Plugin Support** to add monitored items, import from a WordPress.org profile URL, and configure notification options.

== Frequently Asked Questions ==

= How do I import items from a WordPress.org profile? =
Go to **Tools > Plugin Support > Monitored Plugins & Themes** and enter a profile URL (such as `https://profiles.wordpress.org/username/`) into the Profile Import form.

= Where can I access the aggregated RSS feed? =
Your site provides an RSS endpoint at `https://your-site.com/wp-json/gs-support-manager/v1/feed?format=rss`. You can also get a JSON feed at `https://your-site.com/wp-json/gs-support-manager/v1/feed?format=json`.

= How do webhook notifications work? =
When enabled under **Settings & Notifications**, the plugin sends an HTTP POST request with a JSON payload containing details about all newly discovered support topics whenever the background sync runs.

= Can I trigger a feed sync manually? =
Yes! Click the **Sync All Feeds Now** button on the Unified Support Feed tab in your WordPress dashboard.

== Changelog ==

= 1.0.0 =
* Initial release with support for plugin/theme forum monitoring, profile URL import, email/webhook alerts, and REST API RSS export.
