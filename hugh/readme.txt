=== Hugh ===
Contributors: michael-arestad, georgestephanis
Tags: colors, widget, fun, social, interactive
Requires at least: 4.4
Tested up to: 7.0
Stable tag: 1.0.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Hugh is your personal color consultant. However, Hugh also easily gives in to peer pressure.

== Description ==

Hugh is a social color experiment for WordPress. Add the Hugh widget to any sidebar and visitors to your site can pick a color — background, text, the whole page — and broadcast it to everyone else viewing the site at the same time.

**How it works:**

1. A visitor opens the color picker in the Hugh widget, chooses a hex color, and optionally leaves a short note.
2. Hugh pushes that color via the REST API and stores it (up to 100 recent entries).
3. The page background and text color transition smoothly to the new color for all active visitors.
4. A row of color swatches shows the history of recent choices — click any swatch to jump back to that color.

It's part toy, part social experiment: can your visitors agree on a color, or will it descend into a color battle?

**Technical details:**

* Colors are stored via the WP object cache when an external cache (Memcached, Redis) is available, making it fast and ephemeral. Without an external cache it falls back to a WP option for persistence.
* Color history is capped at 100 entries, sorted by timestamp.
* REST endpoints at `hugh/v1/colors` (GET) and `hugh/v1/colors/add` (POST) are public and unauthenticated by design — this is intentional for the live-sharing mechanic.
* The `hugh_css` filter lets themes register custom CSS overrides. Twenty Seventeen support is built in; other themes can hook in via `add_filter( 'hugh_css', ... )`.
* Colors are rendered as smooth CSS transitions so the page doesn't flash when a new color arrives.

**Note:** Hugh works best on sites with an external object cache. Without one, colors are persisted to the database and shared state may lag under concurrent visitors.

== Installation ==

1. Upload the `hugh` folder to `/wp-content/plugins/` or install directly through the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in wp-admin.
3. Go to **Appearance → Widgets** and drag the **Hugh Widget** into any sidebar.
4. Visit the front end and try it out.

== Frequently Asked Questions ==

= Will this slow down my site? =

On sites with an external object cache (Redis, Memcached) Hugh is very fast — color reads and writes go straight to cache. On sites without one, each color submission writes to the database, which may not suit high-traffic sites.

= Can I control which themes Hugh styles? =

Hugh applies a full-page background and text color change by default, with additional theme-specific rules for Twenty Seventeen. Other themes can add their own overrides by hooking into the `hugh_css` filter.

= Can visitors submit any color they want? =

Hugh validates that submitted colors are valid 6-digit hex values (`#rrggbb`) before storing them. Labels are sanitized and capped at 255 characters.

= How many colors does Hugh remember? =

Up to 100 recent colors are stored. Older entries are dropped automatically when the cap is reached.

= Is the REST API endpoint protected? =

The `hugh/v1/colors/add` endpoint is intentionally public so any visitor can submit a color without logging in — that's the whole point. If you want to lock it down you can remove or replace the `permission_callback` via a custom plugin or mu-plugin.

== Screenshots ==

1. The Hugh widget with color picker and recent color swatches.
2. The full page responding to a color choice in real time.

== Changelog ==

= 1.0.4 =
* Tested up to WordPress 7.0.

= 1.0.3 =
* Add `limit` parameter support to the `colors` REST endpoint.

= 1.0.2 =
* Add theme-specific CSS support for Twenty Seventeen.

= 1.0.1 =
* Minor tidying. Updated button text, added missing i18n function calls.

= 1.0 =
* Initial release.
