# AGENTS.md

Guidance for AI coding agents working in this repository.

## Plugin styles

Most plugins are **single-file, classically-structured PHP** — one static class with a `::go()` method that bootstraps all hooks. A few are more involved:

- **`restrict-block-content`** — modern: namespaced PHP (`Bethink\RestrictBlockContent`), `@wordpress/scripts` build pipeline, JSX block editor sidebar (`src/index.jsx`). Requires `npm run build`.
- **`tarot`** — block plugin with SCSS (`tarot.scss` → `tarot.css.map`), block assets under `blocks/`.
- **`omnisearch`** — multi-file admin plugin with a `wp-admin/` subdirectory.
- **`press-this-v2`** — multi-file, no build step.
- **`tuft-feedback`** — has a `package.json` for vendored JS (copied via `postinstall`), no compilation step.

## Build tooling

Only `restrict-block-content` has a JS build step:

```bash
cd restrict-block-content
npm install
npm run build    # production build
npm run start    # watch mode
npm run lint:js  # ESLint via wp-scripts
npm run lint:css # Stylelint via wp-scripts
```

No repo-wide build system. Each plugin is self-contained.

## Coding conventions

- Older plugins use procedural static classes without namespaces.
- Newer plugins (`restrict-block-content`) use PSR-4 namespacing under `Bethink\`.
- All plugins are GPL-2.0-or-later.
- Default branch for this monorepo is `main`. Some submodule plugins use `trunk` as their default branch — check before branching.

## PHP linting (phpcs / WPCS)

A root-level `composer.json` + `phpcs.xml.dist` cover the entire repo:

```bash
composer install          # installs phpcs, WPCS, and auto-registers the standard
vendor/bin/phpcs          # lint all plugins
vendor/bin/phpcbf         # auto-fix what phpcs can
```

Per-plugin `phpcs.xml` files are pre-tuned with `minimum_supported_wp_version` and `testVersion` ranges appropriate to each plugin. `restrict-block-content/` has its own `composer.json` for its JS build; the root one is dev-tooling only and does not conflict.

## Submodules

Several plugins are git submodules pointing to their own standalone repositories. When you need to make changes to a submodule plugin, work in the standalone repo and update the submodule pointer here. The `.gitmodules` file lists all submodule URLs.

## GitHub Actions

Two workflows live in `.github/workflows/`:

- **`deploy.yml`** — manually triggered; bumps plugin version, publishes to WordPress.org SVN, opens a version-bump PR against the plugin's default branch.
- **`asset-update.yml`** — syncs WordPress.org banner/icon assets.

The deploy workflow uses `npm run build --if-present` so plugins with a `package.json` but no `build` script (like `tuft-feedback`) are handled gracefully.

## Code reviews

Each plugin has a `REVIEW.md` at its root documenting known issues: security gaps (nonces, escaping, capability checks), deprecated APIs, i18n completeness, and WPCS concerns. Consult it before making changes, and update it when issues are fixed or new ones are found.
