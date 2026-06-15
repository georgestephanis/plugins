# Go Dark

"Go Dark" is a general-purpose, SEO-friendly protest and blackout utility for WordPress. It allows website owners to temporarily take their site offline with a highly customizable splash screen in protest of digital rights, climate change, net neutrality, or other custom causes.

When active, the plugin returns a `503 Service Temporarily Unavailable` HTTP status code to preserve search engine rankings and sends a `Retry-After` header telling search engines when to return.

---

## Features

- **Flexible Status Modes**: Keep the plugin inactive, schedule a precise blackout start/end window, or force dark mode active immediately.
- **Dynamic datetime-local inputs**: Set scheduling options easily using native browser date/time picker dialogs.
- **Modern Design Presets**:
  - *Minimalist Blackout*: A sleek, modern dark mode design with custom glow accents.
  - *Glassmorphism Alert*: A backdrop-blurred glass card over vibrant ambient glowing shapes.
  - *Classic Protest*: An updated stencil/typewriter style utilizing a dark wood texture.
- **Cause Presets**: Quick-load templates for Net Neutrality, Climate Action, censorship opposition, and standard maintenance.
- **Interactive Live Countdown**: Real-time Javascript-powered countdown clock showing visitors exactly when your website will return.
- **WordPress Media Library Integration**: Directly upload or select custom logos and images to display on the splash page.
- **Call to Action**: Insert custom URLs and labels (e.g. to sign a petition or learn more).

---

## Live Demo

You can test this plugin in a sandbox environment instantly using WordPress Playground:

[Launch WordPress Playground Demo](https://playground.wordpress.net/?blueprint=https://plugins.svn.wordpress.org/go-dark/trunk/blueprint.json)

---

## Installation

1. Upload the `go-dark` directory to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the **Go Dark** menu in your WordPress dashboard to configure status, presets, content, and themes.

---

## Development & Linting

The plugin is structured as a single-file classically bootstrapped class. To run linting locally and ensure compliance with WordPress Coding Standards (WPCS):

```bash
# Run phpcs from the plugin directory
../vendor/bin/phpcs --standard=phpcs.xml

# Auto-fix linting errors where possible
../vendor/bin/phpcbf --standard=phpcs.xml
```
