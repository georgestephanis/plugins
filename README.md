# georgestephanis/plugins

A monorepo of WordPress.org plugins. Most plugins live directly in this repo; some with more complex histories or active co-development are tracked as **git submodules** pointing to their own standalone repositories.

<!-- deploy-status-start -->

> **⚠ Pending deploys** — the following plugins have trunk versions ahead of the last WordPress.org release.
>
> | Plugin | Deployed | Trunk | Action |
> |--------|----------|-------|--------|
> | `ai-provider-for-openai-compatible-servers` | 0.0.0 | **1.0.0** | [Run deploy →](https://github.com/georgestephanis/plugins/actions/workflows/deploy.yml) |

<!-- deploy-status-end -->

## Plugins

| Plugin | GitHub | Description |
|--------|--------|-------------|
| [404-not-available](404-not-available/) | [georgestephanis/404-not-available](https://github.com/georgestephanis/404-not-available) ¹ ³ | Joke plugin (per [XKCD 1969](https://xkcd.com/1969/)) that replaces the theme's 404 page with a fake "not available in your country" screen. |
| [add-ids-to-header-tags](add-ids-to-header-tags/) | [georgestephanis/add-ids-to-header-tags](https://github.com/georgestephanis/add-ids-to-header-tags) ¹ | Adds `id` attributes to header tags in post content for deep linking. |
| [ai-provider-for-openai-compatible-servers](ai-provider-for-openai-compatible-servers/) | [georgestephanis/ai-provider-for-openai-compatible-servers](https://github.com/georgestephanis/ai-provider-for-openai-compatible-servers) ¹ | Connects self-hosted, OpenAI-compatible inference servers (Ollama, LM Studio, vLLM, llama.cpp, LocalAI) to the WordPress AI Client. |
| [automatic-internal-links](automatic-internal-links/) | [georgestephanis/automatic-internal-links](https://github.com/georgestephanis/automatic-internal-links) ¹ | Inserts callout blocks after paragraphs that link to other posts on the same site. |
| [big-emoji-comments](big-emoji-comments/) | [georgestephanis/big-emoji-comments](https://github.com/georgestephanis/big-emoji-comments) ¹ | Enlarges comments that consist entirely of emoji. |
| [blocks-against-wp](blocks-against-wp/) | [georgestephanis/blocks-against-wp](https://github.com/georgestephanis/blocks-against-wp) ¹ ³ | "Blocks Against WordPress" — a Cards Against Humanity–style party game built as editor blocks. |
| [category-posts-widget](category-posts-widget/) | — | Sidebar widget that lists the most recent posts from a single category. |
| [chat-room-redux](chat-room-redux/) | [georgestephanis/chat-room-redux](https://github.com/georgestephanis/chat-room-redux) ¹ ³ | Adds a native chatroom to WordPress. |
| [connector-priority](connector-priority/) | [georgestephanis/connector-priority](https://github.com/georgestephanis/connector-priority) ¹ ³ | Drag-and-drop priority ordering for AI connectors on Settings → Connectors. |
| [custom-content-width](custom-content-width/) | [georgestephanis/Custom-Content-Width](https://github.com/georgestephanis/Custom-Content-Width) ¹ | Adds a setting to Settings → Media to override the theme's content width. |
| [daily-digest](daily-digest/) | [georgestephanis/daily-digest](https://github.com/georgestephanis/daily-digest) ¹ ³ | Aggregates user activity from multiple external providers into a single digest. |
| [domain-restricted-registration](domain-restricted-registration/) | [georgestephanis/domain-restricted-registration](https://github.com/georgestephanis/domain-restricted-registration) ¹ ³ | Restricts user registration to a single email domain and requires email confirmation before login. |
| [footer-on-homepage](footer-on-homepage/) | — | Adds expandable copy to the homepage footer. |
| [go-dark](go-dark/) | — | Makes a site "go dark" on a scheduled date with a customizable message. |
| [google-tag-manager](google-tag-manager/) | — | Adds a GTM ID field to Settings → General and outputs the GTM snippet. |
| [hugh](hugh/) | — | Personal color consultant widget that can also follow peer pressure. |
| [legacy-jetpack-custom-css-editor](legacy-jetpack-custom-css-editor/) | [georgestephanis/legacy-jetpack-custom-css-editor](https://github.com/georgestephanis/legacy-jetpack-custom-css-editor) ¹ | Restores the full-page Custom CSS admin editor removed from Jetpack. |
| [ndizi-project-management](ndizi-project-management/) | — | Basecamp-style project management built on WordPress. [▶ Playground demo](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/ndizi-project-management/playground/blueprint.json) |
| [object-cache-flusher-button](object-cache-flusher-button/) | [georgestephanis/object-cache-flusher-button](https://github.com/georgestephanis/object-cache-flusher-button) ¹ ³ | Adds an admin-bar button that flushes the object cache. |
| [omnisearch](omnisearch/) | [georgestephanis/omnisearch](https://github.com/georgestephanis/omnisearch) ¹ | Unified search across all WordPress admin search providers. |
| [press-this-v2](press-this-v2/) | — | Rewrite of the Press This bookmarklet functionality from Core. |
| [random-blocks](random-blocks/) | [georgestephanis/random-blocks](https://github.com/georgestephanis/random-blocks) ¹ | Additional blocks for the WordPress block editor. |
| [restrict-block-content](restrict-block-content/) | [bethinkstudio/restrict-block-content](https://github.com/bethinkstudio/restrict-block-content) ¹ | Applies Restrict Content membership restrictions to specific blocks. Has JS build step (`npm run build`). |
| [reusable-block-count](reusable-block-count/) | [georgestephanis/Reusable-Block-Count](https://github.com/georgestephanis/Reusable-Block-Count) ¹ | Admin listing page showing reusable blocks and which posts contain each one. |
| [secret-santa](secret-santa/) | [georgestephanis/secret-santa](https://github.com/georgestephanis/secret-santa) ¹ ³ | Organizes Secret Santa groups (admin page + block). |
| [short](short/) | [georgestephanis/short](https://github.com/georgestephanis/short) ¹ ³ | Provides short-link redirection. |
| [simple-404-keyword-insertion](simple-404-keyword-insertion/) | — | Builds a custom 404 page based on the request URL keywords. |
| [snowfall](snowfall/) | [georgestephanis/snowfall](https://github.com/georgestephanis/snowfall) ¹ ³ | Adds a snowfall effect to the site. (The wp.org `snowfall` slug is held by an unrelated author.) |
| [support-widget](support-widget/) | [georgestephanis/support-widget](https://github.com/georgestephanis/support-widget) ¹ ³ | Dashboard widget enabling easier support communication to a dev agency. |
| [t3admin](t3admin/) | [georgestephanis/t3admin](https://github.com/georgestephanis/t3admin) ¹ ³ | "Temporary Titan Token" — grants a user a temporary role set that auto-expires after a set window. |
| [tarot](tarot/) | [georgestephanis/tarot](https://github.com/georgestephanis/tarot) ¹ | Gutenberg block that generates a three-card tarot spread. |
| [the](the/) | — | Adds a `[the]` shortcode with output driven by specific parameters. |
| [theme-downloader](theme-downloader/) | [georgestephanis/theme-downloader](https://github.com/georgestephanis/theme-downloader) ¹ | Lets admins download any installed theme as a ZIP file. |
| [tuft-feedback](tuft-feedback/) | [georgestephanis/tuft](https://github.com/georgestephanis/tuft) ¹ | Visual design feedback with click-to-annotate and screenshots. |
| [update-control](update-control/) | [chipbennett/update-control](https://github.com/chipbennett/update-control) ¹ ² | Adds options to configure WordPress auto-update behavior. |
| [wp-admin-css-editor](wp-admin-css-editor/) | [georgestephanis/wp-admin-css-editor](https://github.com/georgestephanis/wp-admin-css-editor) ¹ ³ | Adds a custom CSS editor for the wp-admin interface. |

¹ Tracked as a git submodule — the directory here mirrors the standalone repo.  
² Co-authored with chipbennett; source lives under their account.  
³ **GitHub-only** — not published on WordPress.org. Intentionally excluded from `versions.json` and the `deploy.yml` / `sync-trunk.yml` workflow dropdowns, so it cannot be deployed by the wp.org tooling.

## Repo structure

```
<plugin-slug>/          ← plugin source (some are submodules, others are in-repo)
.github/workflows/      ← deploy.yml, asset-update.yml, version-check.yml for WordPress.org publishing
```

Plugins that are submodules have their own commit history and default branch (often `trunk`). Non-submodule plugins are committed directly here.

## Deploying to WordPress.org

Releases are published via the `.github/workflows/deploy.yml` workflow (manually triggered). It bumps versions, syncs to SVN, opens a version-bump PR back against the plugin's default branch, and updates the deploy-status table at the top of this README.

See [ACTIONS.md](ACTIONS.md) for full workflow documentation, required credentials, and the version-tracking system.

**GitHub-only plugins** (marked ³ above) are deliberately kept out of `versions.json` and out of the `plugin` dropdowns in `deploy.yml` and `sync-trunk.yml`. The dropdowns are an allowlist, so a plugin absent from them cannot be dispatched to WordPress.org — do not add these slugs there.

## Working with this repo

See [AGENTS.md](AGENTS.md) for coding conventions, build tooling, and linting guidance.
