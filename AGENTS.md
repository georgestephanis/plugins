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

The repo root ships a `composer.json` that pins phpcs, WPCS, and the PHPCSStandards utility packages as dev tooling. Install once at the root:

```bash
composer install
```

Lint everything against the root `phpcs.xml.dist`:

```bash
vendor/bin/phpcs          # lint all plugins
vendor/bin/phpcbf         # auto-fix what it can
```

Most plugins also ship their own `phpcs.xml`, pre-tuned with `minimum_supported_wp_version` and `testVersion` ranges appropriate to that plugin. To lint a single plugin against its own config, run from inside the plugin directory (the per-plugin `<file>` paths are relative to it):

```bash
cd google-tag-manager
../vendor/bin/phpcs --standard=phpcs.xml
../vendor/bin/phpcbf --standard=phpcs.xml
```

Note: the per-plugin `testVersion` lines are advisory — PHPCompatibility is not in the dev requirements, so PHP cross-version sniffs do not fire. Add `phpcompatibility/phpcompatibility-wp` and a `<rule ref="PHPCompatibilityWP"/>` if you want them enforced (a few older plugins have known PHP 8 issues, so this is opt-in).

`restrict-block-content/` has its own `composer.json` for its JS build toolchain (`@wordpress/scripts`); it is unrelated to phpcs.

## Submodules

Several plugins are git submodules pointing to their own standalone repositories. When you need to make changes to a submodule plugin, work in the standalone repo and update the submodule pointer here. The `.gitmodules` file lists all submodule URLs.

## GitHub Actions

See [ACTIONS.md](ACTIONS.md) for full documentation. Summary:

- **`deploy.yml`** — manually triggered; bumps plugin version, publishes to WordPress.org SVN, opens a version-bump PR against the plugin's default branch, then updates `versions.json` and the README deploy-status table.
- **`asset-update.yml`** — syncs WordPress.org banner/icon assets between releases.
- **`version-check.yml`** — manually triggered; regenerates the pending-deploys table in `README.md` from the current state of `versions.json` without doing a full deploy.

The deploy workflow uses `npm run build --if-present` so plugins with a `package.json` but no `build` script (like `tuft-feedback`) are handled gracefully.

## Git hygiene

Never force-push to any branch in this repository. Maintaining full version history — including mistakes — is preferable to the risk of merge conflicts or lost history that force pushes can cause. If a commit needs to be undone, use a revert commit instead.

## Code reviews

Each plugin has a `REVIEW.md` at its root documenting known issues: security gaps (nonces, escaping, capability checks), deprecated APIs, i18n completeness, and WPCS concerns. Consult it before making changes, and update it when issues are fixed or new ones are found.
