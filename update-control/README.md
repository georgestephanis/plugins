# Update Control

"Update Control" adds a manual toggle and configuration options directly to the native WordPress Settings > General interface for managing automatic core, plugin, theme, and translation updates. It provides a simple, UI-driven way to customize auto-update rules without requiring hardcoded PHP constants or custom filters in `functions.php`.

---

## Features

- **Global Toggle**: Enable or disable all automatic updates entirely with a single select field.
- **Granular Update Control**:
  - **Core Update Level**: Limit auto-updates to *Minor Updates* (default), *Major Updates*, or opt-in to *Development Updates* (bleeding-edge nightlies).
  - **Plugins**: Toggle automatic updates for all plugins.
  - **Themes**: Toggle automatic updates for all themes.
  - **Translations**: Choose whether translation files should auto-update.
- **Advanced Options**:
  - **VCS Check Bypass**: Force WordPress auto-updates even if version control files (e.g. Git, SVN) are detected in the installation directory.
  - **Notification Control**:
    - Selectively enable or disable update result emails for *Successful*, *Failed*, or *Critically Failed* updates.
    - Disable WordPress debug emails when running development/nightly builds of WordPress.
- **Dynamic Settings UI**: Fields dynamically enable, disable, and hide/show using jQuery depending on your toggle selections.

---

## Installation

1. Upload the `update-control` directory to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** menu in the WordPress dashboard.
3. Configure settings under **Settings > General** -> **Automatic Updates**.

---

## Development & Linting

The plugin is structured as a classically bootstrapped single-file PHP plugin (with a stylesheet and script for settings page interactions). To run linting locally and ensure compliance with WordPress Coding Standards (WPCS):

```bash
# Run phpcs from the plugin directory
../vendor/bin/phpcs --standard=phpcs.xml

# Auto-fix linting errors where possible
../vendor/bin/phpcbf --standard=phpcs.xml
```
