=== Big Emoji Comments ===
Contributors: georgestephanis
Tags: emoji, comments
Requires at least: 4.4
Tested up to: 7.1
Requires PHP: 7.2
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

If someone leaves a comment comprised entirely of emoji, make it bigger.

== Live Demo ==

Try the plugin instantly in your browser using [WordPress Playground](https://playground.wordpress.net/?blueprint=https://plugins.svn.wordpress.org/big-emoji-comments/trunk/blueprint.json)

== Description ==

It's all the rage. If someone sends a message comprised of _just_ emoji, make it bigger! Eye strain is dangerous!

Shorter emoji messages get bigger than longer emoji messages, and you can include spaces between your emoji and it will still count.

= Modern WordPress 7.1+ Icon Integration =
Adds support for registering custom emoji SVG icons via the new WordPress 7.1 Icon Registration API, making them available in the default WordPress Icon block.

= Emoji Reactions Block =
Insert an interactive "Emoji Reactions" block anywhere on posts or FSE templates. Readers can click on reaction buttons to asynchronously leave emoji comment reactions, which automatically count as big emoji comments!

= Custom Slackmojis Search & Import =
Search Slackmojis (via emojis.json) and import custom Slack/Discord emojis directly to your WordPress site. The plugin will parse custom emojis codes (e.g. `:excited:`) inline and count them towards big emoji comment thresholds.
*Privacy Note:* The plugin will only contact external servers (slackmojis.com) on-demand when the administrator explicitly enters a search query in the admin settings dashboard. No external calls or background syncs occur automatically.

== Changelog ==

= 1.2.0 =
* Implement WordPress 7.1+ Icon Registration API and default SVG emoji collection.
* Add interactive "Emoji Reactions" block with AJAX comment reactions.
* Add admin settings panel to search, import, and delete custom emojis from Slackmojis.
* Parse custom emoji codes (e.g. `:emoji_name:`) inside comment text and support custom emoji-only comments scaling.
* Document privacy-preserving on-demand Slackmojis API connection.

= 1.1.0 =
* Modernize regex to match Unicode 16.0 / Emoji 16.0 specifications.
* Implement grapheme cluster counting for multi-codepoint emoji accuracy.
* Add developer hooks for sizing percent and markup.
* Fix spacing, hoist hooks, and add constants.
* Improve output security using wp_kses_post().

= 1.0.0 =
* Emoji in our time.
