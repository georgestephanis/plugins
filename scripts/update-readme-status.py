#!/usr/bin/env python3
"""
Update the deploy-status section in README.md.

Compares each plugin's current trunk version (PHP header) against the deployed
version on WordPress.org (queried live from the API), then rewrites the
<!-- deploy-status-start --> ... <!-- deploy-status-end --> block in README.md.

Also updates versions.json to reflect whatever the API reports, so local records
stay in sync. Falls back to the value already in versions.json if the API is
unreachable or hasn't caught up after a fresh deploy.

Run from the repo root (where versions.json lives).
"""

import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
VERSIONS_FILE = os.path.join(REPO_ROOT, 'versions.json')
README_FILE = os.path.join(REPO_ROOT, 'README.md')
WORKFLOW_URL = 'https://github.com/georgestephanis/plugins/actions/workflows/deploy.yml'

MARKER_START = '<!-- deploy-status-start -->'
MARKER_END = '<!-- deploy-status-end -->'


def version_tuple(v):
    """Convert a version string to a comparable tuple of ints."""
    try:
        return tuple(int(x) for x in str(v).split('.'))
    except ValueError:
        return (0,)


def get_wporg_version(slug):
    """Query the WP.org API for the current stable version of a plugin."""
    url = f'https://api.wordpress.org/plugins/info/1.0/{slug}.json'
    try:
        with urllib.request.urlopen(url, timeout=5) as response:
            data = json.loads(response.read())
            return data.get('version')
    except (urllib.error.URLError, json.JSONDecodeError, OSError):
        return None


def resolve_deployed_version(slug, json_version):
    """
    Return the version we treat as deployed, and whether it came from the API.

    Uses the WP.org API as the source of truth. If versions.json records a
    higher version (i.e. we just deployed and the API hasn't caught up), we
    prefer versions.json so a freshly-deployed plugin doesn't appear pending.
    """
    api_version = get_wporg_version(slug)

    if api_version is None:
        return json_version, False

    if json_version and version_tuple(json_version) > version_tuple(api_version):
        # versions.json is ahead — API latency after a recent deploy.
        return json_version, False

    return api_version, True


def get_trunk_version(slug):
    """Return the Version: value from the plugin's main PHP file, or None."""
    slug_dir = os.path.join(REPO_ROOT, slug)
    if not os.path.isdir(slug_dir):
        return None

    try:
        result = subprocess.run(
            ['grep', '-rl', 'Plugin Name:', '--include=*.php', slug_dir],
            capture_output=True, text=True, check=True,
        )
    except subprocess.CalledProcessError:
        return None

    php_files = [f for f in result.stdout.strip().splitlines() if f]
    if not php_files:
        return None

    try:
        with open(php_files[0]) as f:
            content = f.read(8192)  # header is always in the first few KB
    except OSError:
        return None

    match = re.search(r'^\s*\*?\s*Version:\s*(.+?)\s*$', content, re.MULTILINE)
    if match:
        return match.group(1).rstrip('*').strip()
    return None


def build_status_block(pending):
    """Return the markdown content (without markers) for the deploy-status section."""
    if not pending:
        return ''

    lines = [
        '',
        '> **⚠ Pending deploys** — the following plugins have trunk versions ahead of the last WordPress.org release.',
        '>',
        '> | Plugin | Deployed | Trunk | Action |',
        '> |--------|----------|-------|--------|',
    ]
    for slug, deployed, trunk in pending:
        lines.append(
            f'> | `{slug}` | {deployed} | **{trunk}** |'
            f' [Run deploy →]({WORKFLOW_URL}) |'
        )
    lines.append('')
    return '\n'.join(lines)


def main():
    with open(VERSIONS_FILE) as f:
        deployed_versions = json.load(f)

    pending = []
    versions_changed = False

    for slug in sorted(deployed_versions.keys()):
        json_version = deployed_versions[slug]
        if json_version == 'trunk':
            continue

        deployed, from_api = resolve_deployed_version(slug, json_version)

        if from_api and deployed != json_version:
            print(f'  {slug}: versions.json had {json_version}, WP.org API reports {deployed} — updating')
            deployed_versions[slug] = deployed
            versions_changed = True

        trunk = get_trunk_version(slug)
        if trunk is None:
            continue

        if version_tuple(trunk) > version_tuple(deployed):
            pending.append((slug, deployed, trunk))

    if versions_changed:
        with open(VERSIONS_FILE, 'w') as f:
            json.dump(deployed_versions, f, indent=2, sort_keys=True)
            f.write('\n')
        print('Updated versions.json to match WP.org API.')

    status_block = build_status_block(pending)

    with open(README_FILE) as f:
        readme = f.read()

    pattern = rf'{re.escape(MARKER_START)}.*?{re.escape(MARKER_END)}'
    replacement = f'{MARKER_START}\n{status_block}\n{MARKER_END}'
    new_readme, count = re.subn(pattern, replacement, readme, flags=re.DOTALL)

    if count == 0:
        print(f'ERROR: markers not found in {README_FILE}', file=sys.stderr)
        sys.exit(1)

    with open(README_FILE, 'w') as f:
        f.write(new_readme)

    if pending:
        print(f'Wrote {len(pending)} pending deploy(s) to README.md:')
        for slug, deployed, trunk in pending:
            print(f'  {slug}: {deployed} → {trunk}')
    else:
        print('All plugins up to date — cleared deploy-status block.')


if __name__ == '__main__':
    main()
