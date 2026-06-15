=== Simple 404 Keyword Insertion ===
Contributors: georgestephanis
Tags: 404, keywords, search
Requires at least: 3.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Builds a custom 404 page based on the request URL, inserting the keywords from the failed request into your 404-page content.

== Description ==

Simple 404 Keyword Insertion creates a page called "404-page" on activation. When a visitor hits a 404, the plugin serves that page with a `[404-keywords]` shortcode replaced by the sanitized keywords from the request URI. This helps create SEO-friendly 404 pages that reflect the content the visitor was looking for.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/`.
2. Activate it through the Plugins menu.
3. The plugin will automatically create a page with the slug `404-page`. Edit it to add your preferred 404 message, using the `[404-keywords]` shortcode wherever you want the request keywords to appear.

== Frequently Asked Questions ==

= Why does my 404 page return a 200 OK status instead of 404? =

This is intentional. To serve the keyword-aware page so search engines can index it, the plugin sends a `200 OK` ("soft 404") rather than a true `404` response. This is the plugin's core behavior. Note that search engines generally discourage soft 404s for genuinely missing content, so enable this deliberately on sites where keyword-aware landing pages are worth more than strict 404 signaling.

= How do I customize the 404 content? =

Edit the **404-page** Page that the plugin creates on activation, like any other Page. Place the `[404-keywords]` shortcode wherever you want the keywords from the failed request URL to appear.

== Changelog ==

= 1.0.1 =
* Security, escaping, and code quality fixes.

= 1.0 =
* Initial release.
