# GitHub Actions for WordPress.org Deployment

All three workflows in `.github/workflows/` use custom shell steps that talk to SVN directly. They require `SVN_USERNAME` and `SVN_PASSWORD` stored as repository secrets (Settings → Secrets and variables → Actions).

## Workflows

| File | Trigger | Purpose |
|------|---------|---------|
| `.github/workflows/deploy.yml` | Manual (`workflow_dispatch`) | Version bump → optional JS build → SVN deploy → tag → update `versions.json` → Git commit/PR → **GitHub tag + Release** → **AI release summary** |
| `.github/workflows/release-summary.yml` | Reusable (`workflow_call`) | Called by `deploy.yml` after a Release is created: gathers commits + PRs, asks a self-hosted LLM for a summary, and rewrites the release notes |
| `.github/workflows/asset-update.yml` | Manual (`workflow_dispatch`) | Sync readme + `.wordpress-org/` assets to SVN trunk and stable tag |
| `.github/workflows/version-check.yml` | Manual (`workflow_dispatch`) | Regenerate the pending-deploys table in `README.md` from the current state of `versions.json` |

### Required credentials

Configure these under Settings → Secrets and variables → Actions in `georgestephanis/plugins`:

| Name | Type | Purpose |
|------|------|---------|
| `SVN_USERNAME` | Variable | WordPress.org SVN username (not sensitive) |
| `SVN_PASSWORD` | Secret | WordPress.org SVN password |
| `GH_PAT` | Secret | Personal Access Token with `repo` scope. Used by the deploy workflow to push a version-bump branch and open a PR in the upstream repo when deploying a **submodule plugin**. Also used by `release-summary.yml` to write the resolved model back into the `LLM_MODEL` variable (the default `GITHUB_TOKEN` cannot manage Actions variables). Not required for direct-directory plugins; if absent, model auto-detection still runs each time but the result isn't cached. Must have write access to the target repo — for `bethinkstudio/restrict-block-content` and `chipbennett/update-control` the PAT owner must have been granted access by those orgs/users. |
| `LLM_URL` | Secret | Base URL of an OpenAI-compatible LLM endpoint (e.g. `http://home.example.me:36428/v1/`). Used by `release-summary.yml` to generate the AI release summary. If unset, the summary is skipped and the release keeps its changelog + PR notes. |
| `LLM_TOKEN` | Secret | Bearer token for the `LLM_URL` endpoint. |
| `LLM_MODEL` | Variable (optional) | Model id to request. Acts as a self-maintaining cache: each run validates it against `GET {LLM_URL}/models` and, if it's no longer served (or unset), picks the first available model and writes that choice back to this variable (requires `GH_PAT` — see below). You may also set it by hand to pin a model. |

## GitHub tags and Releases

After a successful (non-dry-run) deploy, `deploy.yml` also publishes a GitHub Release in this
monorepo — uniformly for both direct and submodule plugins:

1. Tags the monorepo `<slug>/v<version>` (e.g. `ndizi-project-management/v0.9.7.0`), targeting the
   version-bump commit.
2. Builds the distributable ZIP and attaches it as a release asset. The ZIP is built by
   `scripts/build-zip.sh`, which prefers `@wordpress/scripts plugin-zip` when the plugin has a
   `package.json` and otherwise falls back to an rsync build honouring `.distignore` /
   `.gitattributes` export-ignore.
3. Sets the release notes from the plugin's `readme.txt` (or `README.md`) `== Changelog ==` block
   for that version, via `scripts/extract-changelog.py`.
4. Triggers the `summarize` job (`release-summary.yml`), which gathers the commits scoped to the
   plugin's directory plus GitHub's auto-generated PR list, asks the LLM for a short overview, and
   rewrites the body as **Summary → Changelog → Pull requests**. This step is best-effort: if the
   LLM is unreachable the release simply keeps its Changelog + Pull-request sections.

For **submodule plugins** the tag/release live in the monorepo and point at the monorepo commit, so
the per-directory commit log shows only submodule-pointer bumps — those summaries lean on the PR
list and changelog rather than file-level history.

### `package.json` requirement for `plugin-zip`

`@wordpress/scripts plugin-zip` requires every plugin to carry a `package.json` whose `version` is a
**valid semver** value. WordPress plugin versions are often not semver (`1.1`, `2.0`, `0.9.7.0`), so
the direct-directory plugins use a minimal `package.json` with a static `"version": "0.0.0"`
placeholder — the real shipped version always lives in the PHP header and readme `Stable tag` and is
never read from `package.json`. These minimal manifests have no dependencies or lockfile; the deploy
build step is lock-aware (`npm ci` when a lockfile exists, otherwise `npm install`) and the ZIP is
built with `npx @wordpress/scripts plugin-zip`.

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
