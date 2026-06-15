# Big Emoji Comments

A lightweight, high-performance WordPress plugin that automatically scales up emojis in comments when they contain nothing else. Inspired by Slack's "Jumboji" and Twitter's direct messages.

## Features

- **Size Tiers**: Automatically scales emojis based on count:
  - **1 Emoji**: 500% font size (`BIG_EMOJI_SINGLE_SIZE`)
  - **2 to 4 Emojis**: 300% font size (`BIG_EMOJI_MULTI_SIZE`)
  - **5 or More Emojis**: 200% font size (`BIG_EMOJI_DEFAULT_SIZE`)
- **Modern Unicode Compatibility**: Fully recognizes modern emojis (up to Emoji 16.x / Unicode 16.0), including Zero Width Joiners (ZWJ) family/profession combinations, skin tone modifiers, regional indicator flags, and variation selectors.
- **Grapheme Accuracy**: Uses grapheme cluster metrics to accurately count emoji characters. This ensures complex multi-codepoint emojis (like 👨‍👩‍👧) count as a single emoji rather than being falsely inflated by codepoint length.
- **Extensible Hooks**: Offers WordPress filters to customize sizes and HTML output structure.
- **Standard Compliant**: Strictly adheres to WordPress Coding Standards (WPCS).

---

## Configuration & Extensibility

### Named Constants
You can define these in your `wp-config.php` or a custom utility plugin to override the default sizing percentages:

- `BIG_EMOJI_SINGLE_SIZE` (default `500`): Font size percentage for single-emoji comments.
- `BIG_EMOJI_MULTI_SIZE` (default `300`): Font size percentage for 2-4 emoji comments.
- `BIG_EMOJI_DEFAULT_SIZE` (default `200`): Font size percentage for comments with 5+ emojis.

### Filters

#### 1. Sizing Percentage
Customize the font size percentage programmatically:
```php
add_filter( 'big_emoji_comments_percent', function( $percent, $no_markup ) {
    // Custom sizing logic
    return $percent;
}, 10, 2 );
```

#### 2. Markup Output
Modify the HTML wrappers or output container:
```php
add_filter( 'big_emoji_comments_output', function( $output, $content, $percent, $no_markup ) {
    return '<div class="custom-emoji-wrapper">' . $content . '</div>';
}, 10, 4 );
```

---

## Developer Tooling

### Regenerating the Emoji Regex Table
The plugin contains a helper generator script, `regex-builder.php`, which downloads the latest official Unicode Emoji specification and builds/optimizes the character class ranges used in detection. 

To run it:
```bash
php regex-builder.php > compiled-regex.txt
```

### Code Style Checks
This repository runs `phpcs` using the local ruleset configuration. Run linting checks using:
```bash
# From inside the big-emoji-comments directory
../vendor/bin/phpcs --standard=phpcs.xml
```
