# GitHub Actions for WordPress.org Deployment

All three workflows in `.github/workflows/` use custom shell steps that talk to SVN directly. They require `SVN_USERNAME` and `SVN_PASSWORD` stored as repository secrets (Settings → Secrets and variables → Actions).

## Workflows

| File | Trigger | Purpose |
|------|---------|---------|
| `.github/workflows/deploy.yml` | Manual (`workflow_dispatch`) | Version bump → optional JS build → SVN deploy → tag → update `versions.json` → Git commit/PR |
| `.github/workflows/asset-update.yml` | Manual (`workflow_dispatch`) | Sync readme + `.wordpress-org/` assets to SVN trunk and stable tag |
| `.github/workflows/version-check.yml` | Manual (`workflow_dispatch`) | Regenerate the pending-deploys table in `README.md` from the current state of `versions.json` |

### Required credentials

Configure these under Settings → Secrets and variables → Actions in `georgestephanis/plugins`:

| Name | Type | Purpose |
|------|------|---------|
| `SVN_USERNAME` | Variable | WordPress.org SVN username (not sensitive) |
| `SVN_PASSWORD` | Secret | WordPress.org SVN password |
| `GH_PAT` | Secret | Personal Access Token with `repo` scope. Used by the deploy workflow to push a version-bump branch and open a PR in the upstream repo when deploying a **submodule plugin**. Not required for direct-directory plugins. Must have write access to the target repo — for `bethinkstudio/restrict-block-content` and `chipbennett/update-control` the PAT owner must have been granted access by those orgs/users. |

### Submodule version bump flow

When deploying a submodule plugin (one with a standalone GitHub repo), the deploy workflow:

1. Applies the version bump to the checked-out submodule files (used for the SVN commit).
2. Creates a branch `bump-vX.Y.Z` in the **upstream repo**.
3. Pushes the version-bumped files to that branch.
4. Opens a PR against the upstream repo's default branch via `gh pr create`.

The SVN release goes out immediately; the PR gives you a record and a merge path to keep the upstream repo in sync.

---

## Version tracking

`versions.json` at the repo root maps each plugin slug to the version last successfully deployed to WordPress.org via `deploy.yml`. It is committed back to the repo automatically after every successful deploy.

`scripts/update-readme-status.py` reads `versions.json`, greps each plugin's main PHP file for its current `Version:` header, and rewrites the `<!-- deploy-status-start --> … <!-- deploy-status-end -->` block near the top of `README.md`. Plugins whose PHP header version is ahead of `versions.json` appear in a pending-deploys table with a link to the deploy workflow; when everything is in sync the block is empty.

### Normal release flow

1. Bump `Version:` in the plugin's main PHP file and `Stable tag:` in its `readme.txt`.
2. The pending-deploys table in `README.md` will reflect the gap the next time `version-check.yml` runs or a deploy completes.
3. Trigger `deploy.yml` with the plugin slug and new version number.
4. After the SVN commit succeeds, `versions.json` is updated and the README status is cleared automatically.

### Manual refresh

Trigger `version-check.yml` any time you want to regenerate the README status without doing a full deploy — for example, after editing `versions.json` directly to correct a stale entry, or to verify the current state.

### `versions.json` format

```json
{
  "plugin-slug": "1.2.3",
  "omnisearch": "trunk"
}
```

`"trunk"` is a sentinel value for plugins that are always deployed from SVN trunk rather than a tagged version — the version check skips them.
