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

If you maintain, support, or keep track of multiple WordPress plugins or themes, GS Plugin Support Manager saves you from checking individual support forums manually.

### Key Features

* **WordPress.org Profile Import**: Plug in a WordPress.org profile URL (e.g. `https://profiles.wordpress.org/georgestephanis/`) or username to automatically discover and monitor all plugins and themes published by that author.
* **Monitored Plugins & Themes**: Track support feeds for any plugin or theme hosted on WordPress.org by slug.
* **Auto-Discovery**: One-click import for all WordPress.org-hosted plugins installed on your local WordPress site.
* **Unified Dashboard**: View, search, filter, and mark support topics as read or unread across all plugins and themes in one place.
* **Background WP-Cron Sync**: Automatically fetches new support items hourly, twice daily, or daily.
* **Email Notifications**: Receive customizable email digests or alerts whenever new support topics are flagged.
* **Webhook Notifications**: Send instant JSON POST payloads to Slack, Discord, Zapier, Make, or custom HTTP endpoints.
* **Unified RSS & JSON Export Endpoint**: Subscribe to `/wp-json/gs-support-manager/v1/feed?format=rss` in your feed reader of choice.

== Installation ==

1. Upload the `gs-plugin-support-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Tools > Plugin Support** to add monitored items, import from a WordPress.org profile URL, and configure notification options.

== Frequently Asked Questions ==

= How do I import items from a WordPress.org profile? =
Go to **Tools > Plugin Support > Monitored Plugins & Themes** and enter a profile URL (such as `https://profiles.wordpress.org/georgestephanis/`) into the Profile Import form.

= Where can I access the aggregated RSS feed? =
Your site provides an RSS endpoint at `https://your-site.com/wp-json/gs-support-manager/v1/feed?format=rss`.

== Changelog ==

= 1.0.0 =
* Initial release with support for plugin/theme forum monitoring, profile URL import, email/webhook alerts, and REST API RSS export.
