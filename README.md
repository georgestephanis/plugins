# georgestephanis/plugins

A monorepo of WordPress.org plugins. Most plugins live directly in this repo; some with more complex histories or active co-development are tracked as **git submodules** pointing to their own standalone repositories.

<!-- deploy-status-start -->

> **⚠ Pending deploys** — the following plugins have trunk versions ahead of the last WordPress.org release.
>
> | Plugin | Deployed | Trunk | Action |
> |--------|----------|-------|--------|
> | `category-posts-widget` | 2.0 | **2.0.1** | [Run deploy →](https://github.com/georgestephanis/plugins/actions/workflows/deploy.yml) |

<!-- deploy-status-end -->

## Plugins

| Plugin | GitHub | Description |
|--------|--------|-------------|
| [add-ids-to-header-tags](add-ids-to-header-tags/) | [georgestephanis/add-ids-to-header-tags](https://github.com/georgestephanis/add-ids-to-header-tags) ¹ | Adds `id` attributes to header tags in post content for deep linking. |
| [automatic-internal-links](automatic-internal-links/) | [georgestephanis/automatic-internal-links](https://github.com/georgestephanis/automatic-internal-links) ¹ | Inserts callout blocks after paragraphs that link to other posts on the same site. |
| [big-emoji-comments](big-emoji-comments/) | [georgestephanis/big-emoji-comments](https://github.com/georgestephanis/big-emoji-comments) ¹ | Enlarges comments that consist entirely of emoji. |
| [category-posts-widget](category-posts-widget/) | — | Sidebar widget that lists the most recent posts from a single category. |
| [custom-content-width](custom-content-width/) | [georgestephanis/Custom-Content-Width](https://github.com/georgestephanis/Custom-Content-Width) ¹ | Adds a setting to Settings → Media to override the theme's content width. |
| [footer-on-homepage](footer-on-homepage/) | — | Adds expandable copy to the homepage footer. |
| [go-dark](go-dark/) | — | Makes a site "go dark" on a scheduled date with a customizable message. |
| [google-tag-manager](google-tag-manager/) | — | Adds a GTM ID field to Settings → General and outputs the GTM snippet. |
| [hugh](hugh/) | — | Personal color consultant widget that can also follow peer pressure. |
| [legacy-jetpack-custom-css-editor](legacy-jetpack-custom-css-editor/) | [georgestephanis/legacy-jetpack-custom-css-editor](https://github.com/georgestephanis/legacy-jetpack-custom-css-editor) ¹ | Restores the full-page Custom CSS admin editor removed from Jetpack. |
| [ndizi-project-management](ndizi-project-management/) | — | Basecamp-style project management built on WordPress. [▶ Playground demo](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/ndizi-project-management/playground/blueprint.json) |
| [omnisearch](omnisearch/) | [georgestephanis/omnisearch](https://github.com/georgestephanis/omnisearch) ¹ | Unified search across all WordPress admin search providers. |
| [press-this-v2](press-this-v2/) | — | Rewrite of the Press This bookmarklet functionality from Core. |
| [random-blocks](random-blocks/) | [georgestephanis/random-blocks](https://github.com/georgestephanis/random-blocks) ¹ | Additional blocks for the WordPress block editor. |
| [restrict-block-content](restrict-block-content/) | [bethinkstudio/restrict-block-content](https://github.com/bethinkstudio/restrict-block-content) ¹ | Applies Restrict Content membership restrictions to specific blocks. Has JS build step (`npm run build`). |
| [reusable-block-count](reusable-block-count/) | [georgestephanis/Reusable-Block-Count](https://github.com/georgestephanis/Reusable-Block-Count) ¹ | Admin listing page showing reusable blocks and which posts contain each one. |
| [simple-404-keyword-insertion](simple-404-keyword-insertion/) | — | Builds a custom 404 page based on the request URL keywords. |
| [tarot](tarot/) | [georgestephanis/tarot](https://github.com/georgestephanis/tarot) ¹ | Gutenberg block that generates a three-card tarot spread. |
| [the](the/) | — | Adds a `[the]` shortcode with output driven by specific parameters. |
| [theme-downloader](theme-downloader/) | [georgestephanis/theme-downloader](https://github.com/georgestephanis/theme-downloader) ¹ | Lets admins download any installed theme as a ZIP file. |
| [tuft-feedback](tuft-feedback/) | [georgestephanis/tuft](https://github.com/georgestephanis/tuft) ¹ | Visual design feedback with click-to-annotate and screenshots. |
| [update-control](update-control/) | [chipbennett/update-control](https://github.com/chipbennett/update-control) ¹ ² | Adds options to configure WordPress auto-update behavior. |

¹ Tracked as a git submodule — the directory here mirrors the standalone repo.  
² Co-authored with chipbennett; source lives under their account.

## Repo structure

```
<plugin-slug>/          ← plugin source (some are submodules, others are in-repo)
.github/workflows/      ← deploy.yml, asset-update.yml, version-check.yml for WordPress.org publishing
```

Plugins that are submodules have their own commit history and default branch (often `trunk`). Non-submodule plugins are committed directly here.

## Deploying to WordPress.org

Releases are published via the `.github/workflows/deploy.yml` workflow (manually triggered). It bumps versions, syncs to SVN, opens a version-bump PR back against the plugin's default branch, and updates the deploy-status table at the top of this README.

See [ACTIONS.md](ACTIONS.md) for full workflow documentation, required credentials, and the version-tracking system.

## Working with this repo

See [AGENTS.md](AGENTS.md) for coding conventions, build tooling, and linting guidance.
