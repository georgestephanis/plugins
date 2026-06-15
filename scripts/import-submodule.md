# Git Submodule to Subtree Converter

This tool automates the process of converting a Git submodule in a monorepo into a standard subdirectory, importing its full git history directly (using `git subtree`), and archiving the original source repository on GitHub.

## Requirements

- **Python 3**: The script uses only standard library components (no external dependencies required).
- **Git**: `git subtree` support must be installed (included in standard Git distributions).
- **GitHub CLI (`gh`)**: Required if archiving the original remote repository (not needed if `--no-archive` is passed).
  - The script will automatically unset any invalid `GITHUB_TOKEN` environment variables to fall back to the active user keyring authenticated session.

## Usage

Run the script from anywhere in the repository (it automatically resolves the repository root relative to its file path):

```bash
./scripts/import-submodule.py <submodule_path> [options]
```

### Positional Arguments
- `submodule_path`: The directory path of the submodule to convert (e.g., `tarot`, `add-ids-to-header-tags`). Trailing slashes are handled automatically.

### Optional Flags
- `-h`, `--help`: Show the help message and exit.
- `--branch BRANCH`: The remote branch to import history from. If not specified, the script automatically queries the remote to detect the default branch (e.g., `master`, `main`, or `trunk`) using `git ls-remote`.
- `--no-archive`: Skip the GitHub repository archiving step. Use this if the source repository is not hosted on GitHub or if you want to keep the source repository active.
- `--dry-run`, `-n`: Preview all actions and Git commands without executing them.
- `--yes`, `-y`: Skip the interactive confirmation prompt and proceed immediately.
- `--force`, `-f`: Run the conversion even if you have uncommitted changes in the repository.

---

## Examples

### 1. Dry Run (Preview Changes)
Always run a dry-run first to verify paths, detected default branches, and parsed GitHub slugs:
```bash
./scripts/import-submodule.py tarot --dry-run
```

### 2. Standard Conversion (Subtree Import + GitHub Archive)
To perform the full conversion on the `tarot` submodule:
```bash
./scripts/import-submodule.py tarot
```
This will:
1. Fetch the remote repository's default branch name.
2. Prompt you to confirm the path, remote URL, and archive repository slug.
3. Remove the submodule from `.gitmodules` and stage/commit the removal.
4. Clean up the local metadata in `.git/modules/tarot`.
5. Import all history from the source remote under `tarot/` as a subtree merge.
6. Archive the `georgestephanis/tarot` repository on GitHub.

### 3. Subtree Import Only (No Archive)
To import history for a repository you don't own or want to keep active on GitHub:
```bash
./scripts/import-submodule.py update-control --no-archive
```

---

## How It Works Under the Hood

The script performs the following sequence of operations:

1. **Working Directory Cleanliness**: Runs `git status --porcelain` to prevent losing local unstaged work.
2. **Configuration Lookup**: Queries `.gitmodules` for the URL and name associated with the target folder.
3. **Submodule Deinitialization**: Runs `git submodule deinit -f <path>` to remove local configuration references.
4. **Git Tracking Removal**: Runs `git rm -f <path>` to remove the submodule entry from index tracking.
5. **Metadata Cleanup**: Deletes the Git metadata folder inside `.git/modules/<submodule_name>` and any leftover files.
6. **Submodule Removal Commit**: Commits the removal of the submodule.
7. **History Integration**: Runs `git subtree add --prefix=<path> <url> <branch>` to fetch and integrate the full git history of the remote branch under the directory path.
8. **GitHub Archiving**: Clears any overriding `GITHUB_TOKEN` from the shell process environment (enabling keychain authentication fallback) and executes `gh repo archive <owner/repo> --yes`.
