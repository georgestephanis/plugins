# AGENTS.md

Guidance for AI coding agents working on the **Go Dark** plugin.

## Plugin Structure

This plugin is a single-file, classically-structured PHP plugin:
- **`go-dark.php`** — Contains the main static class `go_dark` and its bootstrap logic.
- **`wood.jpg`** — The background texture used in the Classic theme.
- **`screenshot-*.png`** — Screenshots for the WordPress.org plugin directory.

## No Build Step

This plugin uses pure Vanilla CSS and Vanilla JavaScript. There is **no compilation, bundler, or build pipeline** (no npm build steps).
- All admin styling is output inline within `page_go_dark()`.
- All splash page layout styling is output inline within `show_page()`.
- Client-side countdown logic and Media Library integration scripts are pure Vanilla JS written inline inside script blocks within `page_go_dark()` and `show_page()`.

## Database Options Schema

All configuration options are stored as standard WordPress options:
- `go_dark_status` — `inactive` (offline/online), `scheduled` (active within window), `active` (manually forced dark).
- `go_dark_start` — Start window Unix timestamp (UTC).
- `go_dark_end` — End window Unix timestamp (UTC). Set to `0` for no expiration.
- `go_dark_title` — Main splash page heading string.
- `go_dark_text` — Splash description (supports rich text/iframes, sanitized via `wp_kses`).
- `go_dark_theme` — Chosen layout preset: `minimalist`, `glassmorphism`, `classic`.
- `go_dark_accent_color` — Hex color code for glows, icons, and action buttons.
- `go_dark_custom_img_url` — URL of custom image/logo to display.
- `go_dark_show_countdown` — `yes` or `no`.
- `go_dark_link_url` — Custom action/petition URL.
- `go_dark_link_text` — Action button label string.

## Coding Conventions & Linting

- All code must comply with WordPress Coding Standards (WPCS).
- Exclusion rules for legacy filenames (`go-dark.php`) and the legacy class name (`go_dark`) are configured in `phpcs.xml`. Do not rename them.
- Always run the linter from the plugin directory before committing changes:

```bash
# Lint code
../vendor/bin/phpcs --standard=phpcs.xml

# Auto-fix formatting issues
../vendor/bin/phpcbf --standard=phpcs.xml
```
