#!/usr/bin/env python3
"""Extract the changelog entry for a single version from a WordPress readme.

WordPress readme.txt / README.md changelogs use this shape:

    == Changelog ==

    = 1.2.3 =
    *   Did a thing.
    *   Fixed another thing.

    = 1.2.2 =
    ...

Usage: extract-changelog.py <readme-path> <version>

Prints the body of the matching `= <version> =` block to stdout (with the
WordPress `*   ` bullet markers converted to Markdown `- `), or nothing if no
matching entry is found.
"""
import re
import sys


def main():
    if len(sys.argv) != 3:
        sys.stderr.write("usage: extract-changelog.py <readme-path> <version>\n")
        sys.exit(2)

    path, version = sys.argv[1], sys.argv[2]
    with open(path, encoding="utf-8") as f:
        text = f.read()

    # Isolate the Changelog section: everything up to the next top-level
    # `== Heading ==` or end of file.
    section = re.search(r"==\s*Changelog\s*==(.*?)(?=\n==\s|\Z)", text, re.S | re.I)
    body = section.group(1) if section else text

    # Find the block for this exact version header, up to the next `= ... =`.
    pattern = re.compile(
        r"^=\s*" + re.escape(version) + r"\s*=\s*$(.*?)(?=^=\s|\Z)",
        re.S | re.M,
    )
    match = pattern.search(body)
    if not match:
        return

    entry = match.group(1).strip()
    lines = [re.sub(r"^\*\s+", "- ", ln) for ln in entry.splitlines()]
    print("\n".join(lines).strip())


if __name__ == "__main__":
    main()
