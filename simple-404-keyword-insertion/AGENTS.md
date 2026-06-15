# AGENTS.md

Guidance for AI coding agents working on **Simple 404 Keyword Insertion**.

## What this plugin is

A single-file, classically-structured WordPress plugin: one static class
(`Simple_404_Keyword_Insertion`) whose `::go()` method bootstraps all hooks.
It serves an editable Page (slug `404-page`) on 404 responses and exposes a
`[404-keywords]` shortcode that injects sanitized keywords from the request URI.

Everything lives in [simple-404-keyword-insertion.php](simple-404-keyword-insertion.php).
There is no build step. `package.json` exists only to run `wp-scripts plugin-zip`
when packaging the WordPress.org distribution ZIP.

## Behavior to preserve (these are intentional, not bugs)

- **Soft 404 / `status_header( 200 )`** — the plugin deliberately returns
  `200 OK` for missing URLs so the keyword page is served and indexable. Do not
  "fix" this to a real 404 without an explicit request; it is the plugin's reason
  to exist. It is documented as a caveat in `README.md`.
- **Global `$wp_query` override** — `filter_404_template()` replaces the global
  `$wp_query` so the theme's main loop renders the `404-page` post. Do **not**
  call `the_post()` here; the theme's own loop advances the cursor, and
  pre-advancing leaves the loop empty (regression caught in PR review).
- **Keyword insertion lives in `post_content`**, via the `[404-keywords]`
  shortcode — not in `post_title`. Titles do not run shortcodes, so the page is
  created with a human-readable title ("Page Not Found").

## Conventions

- Procedural static class, no namespace. Match the existing style.
- GPL-2.0-or-later.
- Default branch in the GitHub monorepo is `main`.
- All user-facing output is escaped (`esc_html()`); `$_SERVER['REQUEST_URI']` is
  guarded with `isset()` before use.

## Linting

From the root of the [georgestephanis/plugins](https://github.com/georgestephanis/plugins) monorepo:

```bash
composer install
vendor/bin/phpcs --standard=simple-404-keyword-insertion/phpcs.xml
```

The `WordPress.Files.FileName.InvalidClassFileName` warning on the main plugin
file is expected and acceptable — the main file is named per the wp.org slug.

## Releasing

WordPress.org reads `Stable tag:` from `readme.txt`. Both the plugin header
version and `Stable tag` must match. Releases are deployed centrally via the
monorepo's `deploy` workflow — see the repo root `ACTIONS.md`. Keep `readme.txt`
(wp.org format) and `README.md` (GitHub) in sync manually.
