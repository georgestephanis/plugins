#!/usr/bin/env python3
"""
Update the deploy-status section in README.md.

Compares each plugin's current trunk version (PHP header) against the last
deployed version recorded in versions.json, then rewrites the
<!-- deploy-status-start --> ... <!-- deploy-status-end --> block in README.md.

Run from the repo root (where versions.json lives).
"""

import json
import os
import re
import subprocess
import sys

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
    for slug in sorted(deployed_versions.keys()):
        deployed = deployed_versions[slug]
        if deployed == 'trunk':
            continue

        trunk = get_trunk_version(slug)
        if trunk is None:
            continue

        if version_tuple(trunk) > version_tuple(deployed):
            pending.append((slug, deployed, trunk))

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
