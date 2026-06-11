# Ndizi PM — WordPress Playground

Dev-only tooling for spinning up a disposable WordPress with Ndizi Project Management
installed and seeded with demo data. **Not shipped in the WordPress.org package** (see
[`.distignore`](../.distignore)).

## What's here

- **`blueprint.json`** — a [Playground Blueprint](https://wordpress.github.io/wordpress-playground/blueprints/)
  that installs this plugin straight from its subdirectory in the monorepo via the
  `git:directory` resource, activates it, then seeds mock data.
- **`mock-data.php`** — wipes all `ndizi_*` posts and the custom time-entry table, then
  re-seeds three clients, four contacts, five projects, tasks, invoices, time entries, and
  a Client Portal page. **Destructive** — it is meant only for throwaway Playground/staging
  installs. (Relocated here from the plugin root so it can never be triggered in a shipped
  install.)

## Run it in the browser

The blueprint installs only this plugin out of the monorepo by pointing `git:directory` at
the repo with `path: "ndizi-project-management"`. Open:

<https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/georgestephanis/plugins/main/ndizi-project-management/playground/blueprint.json>

This fetches the plugin from `main` on GitHub, so it reflects the last pushed commit — not
your local working tree.

## Iterate locally against your working tree

`git:directory` always pulls from the remote, so for local changes mount the plugin
directly and run the seeder yourself:

```bash
# from the plugin root (one level up)
npx @wp-playground/cli@latest server --auto-mount --php=8.3

# then, in the Playground shell / browser PHP console, or via run-blueprint, run:
#   require WP_PLUGIN_DIR . '/ndizi-project-management/playground/mock-data.php';
```

Or point the CLI at the blueprint (still fetches the plugin from GitHub `main`):

```bash
npx @wp-playground/cli@latest run-blueprint \
  --blueprint=ndizi-project-management/playground/blueprint.json
```

## Notes

- The monorepo (`github.com/georgestephanis/plugins`) must stay **public** for
  `git:directory` to work in the hosted Playground.
- Build artifacts under `build/` are committed to the repo, so no JS build step is needed
  for the blueprint to work.
- Default login is `admin` / `password` (Playground default).
