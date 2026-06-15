=== Big Emoji Comments ===
Contributors: georgestephanis
Tags: emoji, comments
Requires at least: 4.4
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

If someone leaves a comment comprised entirely of emoji, make it bigger.

== Live Demo ==

Try the plugin instantly in your browser using [WordPress Playground](https://playground.wordpress.net/?blueprint=https://plugins.svn.wordpress.org/big-emoji-comments/trunk/blueprint.json)

== Description ==

It's all the rage.  If someone sends a message comprised of _just_ emoji, make it bigger!  Eye strain is dangerous!

Shorter emoji messages get bigger than longer emoji messages, and you can include spaces between your emoji and it will still count.

== Changelog ==

= 1.1.0 =
* Modernize regex to match Unicode 16.0 / Emoji 16.0 specifications.
* Implement grapheme cluster counting for multi-codepoint emoji accuracy.
* Add developer hooks for sizing percent and markup.
* Fix spacing, hoist hooks, and add constants.
* Improve output security using wp_kses_post().

= 1.0.0 =
* Emoji in our time.
