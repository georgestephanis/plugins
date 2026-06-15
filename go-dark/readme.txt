=== Go Dark ===
Contributors: georgestephanis
Donate link: https://supporters.eff.org/donate
Tags: Protest, Internet Freedom, Maintenance, Blackout, Go Dark
Requires at least: 5.3
Tested up to: 7.0
Stable tag: 1.1.0

A general-purpose, SEO-friendly protest and blackout utility for WordPress. Schedule a period or manually force your website to go dark with customizable messages and premium theme designs.

== Description ==

"Go Dark" is a general-purpose, SEO-friendly utility that allows you to easily take your website offline (blackout) in protest of political issues, climate advocacy, net neutrality, censorship opposition, or other custom causes.

When your website is "dark", it returns a `503 Service Temporarily Unavailable` HTTP status code. This informs search engines that the offline state is temporary, preserving your SEO rankings, and provides browsers with a `Retry-After` header.

== Live Demo ==

Try the plugin instantly in your browser using [WordPress Playground](https://playground.wordpress.net/?blueprint=https://plugins.svn.wordpress.org/go-dark/trunk/blueprint.json)

= Features =
* **Flexible Status Modes**: Keep the plugin inactive, schedule a precise start/end blackout window, or force dark mode active immediately.
* **Modern Premium Themes**: Choose from three highly polished design presets:
  1. *Minimalist Blackout*: A sleek, modern dark mode design with custom glow accents.
  2. *Glassmorphism Alert*: A stunning backdrop-blurred glass card over vibrant ambient glowing shapes.
  3. *Classic Protest*: An updated typewriter aesthetic utilizing a dark wood texture.
* **Cause Presets**: Quick-load templates for Net Neutrality, Climate Action, censorship opposition, and standard maintenance.
* **Interactive Live Countdown**: Real-time Javascript-powered countdown clock showing visitors exactly when your website will return.
* **WordPress Media Library Integration**: Directly upload or select custom logos and images to display on the splash page.
* **Call to Action**: Insert custom URLs and labels (e.g. to sign a petition or learn more).

= Credits =

* Design and legacy Sign/Seal was created by Cheryl Eisenhard ( http://cheryleisenhard.com/ )
* Development by George Stephanis ( http://stephanis.info/ )
* Wood Grain Background taken from http://webtreats.mysitemyway.com/8-tileable-dark-wood-texture-patterns/

== Installation ==

1. Upload the `go-dark` directory to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'Go Dark' menu in your WordPress dashboard to configure status, presets, content, and themes.

== Changelog ==

= 1.1.0 =
* Complete modernization and rewrite of the plugin.
* Removed 2012 SOPA/PIPA specific hardcodings in favor of general-purpose protest templates.
* Removed legacy preset sign and seal images.
* Integrated the WordPress Media Library to support custom image selection and uploads.
* Added 3 premium design themes: Minimalist Blackout, Glassmorphism Alert, and Classic Protest.
* Added live client-side JavaScript countdown timer.
* Added quick-load template presets for popular causes.
* Redesigned backend settings dashboard for a cleaner, modern interface.
* Enhanced input sanitization (`sanitize_hex_color`, `esc_url_raw`, type casting).

= 1.0.7 =
* Adding nonce for further security.

= 1.0.6 =
* Added vimeo video to default message, and link to admin panel sidebar for convenience.

= 1.0.5 =
* Minor CSS change -- html,body {height:100%;} becomes {min-height:100%;}

= 1.0.4 =
* Fixed a syntax error.

= 1.0.3 =
* Added backwards compatibility for WP < 3.1.

= 1.0.2 =
* Added `503 Service Temporarily Unavailable` status code so as to not damage website SEO.

= 1.0.1 =
* Added screenshots.

= 1.0 =
* Initial Release.
