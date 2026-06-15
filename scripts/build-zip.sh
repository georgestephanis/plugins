#!/usr/bin/env bash
#
# Build a WordPress.org-style distribution ZIP for a plugin.
#
# The archive contains a single top-level <slug>/ directory (the layout
# WordPress expects). When the plugin has a package.json, the canonical
# @wordpress/scripts `plugin-zip` tool builds it (honouring .distignore or the
# package.json `files` field). Plugins without a package.json — or whose
# package.json trips plugin-zip (e.g. a non-semver version on a submodule we
# don't control) — fall back to an rsync build that mirrors the SVN deploy's
# exclusion logic. Either way a ZIP is always produced.
#
# Usage: scripts/build-zip.sh <slug> <output-zip-path>
set -euo pipefail

SLUG="$1"
# Resolve the output path to an absolute one without requiring it to exist
# yet (BSD realpath lacks -m), since we cd into the staging dir before zipping.
case "$2" in
  /*) OUT="$2" ;;
  *)  OUT="$PWD/$2" ;;
esac
SRC="./$SLUG"

build_with_wp_scripts() {
  # plugin-zip writes <package.name>.zip into the plugin directory; move it to
  # the requested output path. Returns non-zero on any failure so the caller
  # can fall back.
  local name
  name=$(cd "$SRC" && node -p "require('./package.json').name" 2>/dev/null) || return 1
  [ -n "$name" ] || return 1

  ( cd "$SRC" && rm -f "$name.zip" && npx --yes @wordpress/scripts plugin-zip ) || return 1
  [ -f "$SRC/$name.zip" ] || return 1

  rm -f "$OUT"
  mv "$SRC/$name.zip" "$OUT"
}

build_with_rsync() {
  local stage dest
  stage="$(mktemp -d)"
  dest="$stage/$SLUG"
  mkdir -p "$dest"

  # Mirror the exclusion logic used by the SVN deploy step.
  local excludes=(--exclude=.git/ --exclude=.github/ --exclude=node_modules/ --exclude=.distignore)
  if [ -f "$SRC/.distignore" ]; then
    while IFS= read -r line; do
      [[ -z "$line" || "$line" == \#* ]] && continue
      excludes+=(--exclude="$line")
    done < "$SRC/.distignore"
  elif [ -f "$SRC/.gitattributes" ]; then
    while IFS= read -r line; do
      [[ "$line" == *"export-ignore"* ]] || continue
      path=$(echo "$line" | awk '{print $1}')
      excludes+=(--exclude="$path")
    done < "$SRC/.gitattributes"
  fi

  rsync -rc "${excludes[@]}" "$SRC/" "$dest/"

  rm -f "$OUT"
  ( cd "$stage" && zip -rq "$OUT" "$SLUG" )
  rm -rf "$stage"
}

if [ -f "$SRC/package.json" ] && command -v node > /dev/null 2>&1; then
  if build_with_wp_scripts; then
    echo "Built $OUT (via @wordpress/scripts plugin-zip)"
    exit 0
  fi
  echo "plugin-zip unavailable or failed for $SLUG — falling back to rsync build." >&2
fi

build_with_rsync
echo "Built $OUT (via rsync)"
