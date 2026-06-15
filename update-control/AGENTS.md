# AGENTS.md

Guidance for AI coding agents working on the **Update Control** plugin.

## Plugin Structure

This plugin is a single-file classically-structured PHP plugin utilizing separate script and style assets for settings interactions:
- **`update-control.php`** — Main entrypoint. Registers namespace `Stephanis\UpdateControl`, contains the static class `Update_Control` and bootstraps hook callbacks for the WordPress Settings API and auto-update filters.
- **`update-control.js`** — Enqueued on the admin `options-general.php` settings screen. Handles dynamic showing/hiding and enabling/disabling of dependent inputs.
- **`update-control.css`** — Declares simple stylesheet rules adjusting the opacity of disabled settings options.
- **`readme.txt`** — Standard WordPress.org metadata and changelog document.

## No Build Step

This plugin uses pure Vanilla JavaScript and CSS. There is **no compilation, bundler, or build pipeline** (no npm build steps).

## Database Options Schema

All configuration options are stored as a single associative array option in the database under the key `update_control_options`. Defaults are merged on retrieval via `get_options()`:
- `active` (`yes` | `no`) — Globally enables/disables automatic updates.
- `core` (`minor` | `major` | `dev`) — Core update level filters.
- `plugin` (bool) — Permit automatic plugin updates.
- `theme` (bool) — Permit automatic theme updates.
- `translation` (bool) — Permit automatic translation updates.
- `toggleadvanced` (`show` | `hide`) — Toggle visibility of advanced settings.
- `vcscheck` (bool) — Bypasses VCS directory checkups to force updates on version controlled codebases.
- `emailactive` (`yes` | `no`) — Toggle update emails.
- `successemail` (bool) — Send email for successful updates.
- `failureemail` (bool) — Send email for failed updates.
- `criticalemail` (bool) — Send email for critically failed updates.
- `debugemail` (bool) — Disable debug emails for development builds of WordPress.

## Coding Conventions & Linting

- PHP code must use PSR-4 namespacing under `Stephanis\UpdateControl` and follow WordPress Coding Standards (WPCS).
- Avoid calling `wp_get_wp_version()` to ensure full backward compatibility down to WordPress 6.0 without triggering Plugin Check static analysis errors. Access `$GLOBALS['wp_version']` directly instead.
- The `update_modification_detected` warning flagged by the WordPress Plugin Check tool is a false positive / expected behavior, as the plugin's primary design is to filter automatic updates via hooks like `auto_update_plugin`.
- Always verify changes against phpcs before proposing or committing edits:

```bash
# Lint code
../vendor/bin/phpcs --standard=phpcs.xml

# Auto-fix formatting issues
../vendor/bin/phpcbf --standard=phpcs.xml
```
