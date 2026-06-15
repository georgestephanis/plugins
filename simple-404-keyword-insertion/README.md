# Simple 404 Keyword Insertion

Serves a custom, keyword-aware page whenever a visitor hits a 404 — inserting the search terms from the failed request URL into your own 404 content.

When a request can't be matched, WordPress would normally render the theme's "Nothing found" template. This plugin intercepts that, serves a page you control (slug `404-page`), and exposes a `[404-keywords]` shortcode that expands to the sanitized keywords parsed out of the request URI. The result is a friendlier, more relevant landing page for mistyped or broken links.

## Features

- **Editable 404 page** — on activation the plugin creates a normal Page (slug `404-page`) that you edit like any other content.
- **`[404-keywords]` shortcode** — drop it anywhere in that page's content to echo the keywords derived from the request URL, sanitized and HTML-escaped.
- **Soft 404 by design** — the response is sent with a `200 OK` status (not `404`) so the keyword page is served and indexable. See the note below.
- **Zero config** — no settings page. Activate, edit the page, done.

## How it works

The plugin hooks `404_template`. When that filter fires it:

1. Calls `status_header( 200 )` — an intentional soft 404 (see caveat).
2. Overrides the global `$wp_query` with a query for the `404-page` Page so the theme's main loop renders that content.
3. Registers the `[404-keywords]` shortcode.
4. Locates `page.php` (falling back to `index.php`) to render.

The shortcode reads `$_SERVER['REQUEST_URI']`, URL-decodes it, strips non-word characters to spaces, and returns the result escaped via `esc_html()`.

## Caveat: soft 404s and SEO

This plugin **intentionally returns `200 OK` for missing URLs**. That is the whole point — it turns dead links into served, indexable content. Be aware this is a "soft 404," which search engines generally discourage for genuinely missing content. Use it deliberately, on sites where keyword-aware landing pages are more valuable than strict 404 signaling.

## Requirements

- WordPress 3.0 or later
- PHP 7.4 or later

## Installation

1. Install from the [WordPress plugin directory](https://wordpress.org/plugins/simple-404-keyword-insertion/), or upload the `simple-404-keyword-insertion` folder to `/wp-content/plugins/`.
2. Activate via **Plugins → Installed Plugins**.
3. Edit the auto-created **404-page** Page, adding your message and placing `[404-keywords]` wherever you want the request keywords to appear.

## Development

Single-file plugin, no build step. PHP linting uses [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) via PHPCS — run from the root of [georgestephanis/plugins](https://github.com/georgestephanis/plugins):

```bash
composer install
vendor/bin/phpcs --standard=simple-404-keyword-insertion/phpcs.xml
```

## Deploying to WordPress.org SVN

Deployments are managed centrally through [georgestephanis/plugins](https://github.com/georgestephanis/plugins) via its `deploy` workflow. To release a new version:

1. Bump the version in `simple-404-keyword-insertion.php` (plugin header) and `readme.txt` (`Stable tag`).
2. Add a changelog entry in `readme.txt`.
3. Merge to `main`.
4. Trigger the **Deploy plugin to WordPress.org** workflow in `georgestephanis/plugins`, selecting `simple-404-keyword-insertion` and the new version number.

SVN history: https://plugins.trac.wordpress.org/browser/simple-404-keyword-insertion

## License

GPL-2.0-or-later.
