#!/usr/bin/env bash
#
# Build installable WordPress zips for each SEO Sprinkler edition.
#
#   ./build-plugin-zips.sh
#   -> dist/seo-sprinkler-free-<ver>.zip
#      dist/seo-sprinkler-pro-<ver>.zip
#      dist/seo-sprinkler-expert-<ver>.zip
#
# The plugin is a single codebase; edition is resolved at runtime by
# SPR_Edition::current() with priority: SPR_EDITION constant > stored option >
# free. The Free build ships no constant (defaults to free + the 25-page grace);
# the Pro/Expert builds pin the edition by defining SPR_EDITION, so they unlock
# out of the box without a licence key.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
SLUG="seo-sprinkler"
SRC="$ROOT/$SLUG"
OUT="$ROOT/dist"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Parse the version straight from the plugin header so names stay in sync.
VERSION="$(grep -m1 -oE 'Version:[[:space:]]*[0-9.]+' "$SRC/$SLUG.php" | grep -oE '[0-9.]+')"
[ -n "$VERSION" ] || { echo "ERROR: could not parse version from $SLUG.php" >&2; exit 1; }

# Dev / internal files that should never ship to a customer.
EXCLUDES=(tests MONETIZATION.md PRICING.md README.md)

mkdir -p "$OUT"
rm -f "$OUT/$SLUG"-*.zip

build() {
  local edition="$1"
  local dest="$STAGE/$SLUG"
  rm -rf "$dest"
  cp -R "$SRC" "$dest"

  local e
  for e in "${EXCLUDES[@]}"; do rm -rf "$dest/$e"; done
  find "$dest" -name '.DS_Store' -delete 2>/dev/null || true
  find "$dest" -type d -name '__pycache__' -prune -exec rm -rf {} + 2>/dev/null || true

  if [ "$edition" != "free" ]; then
    # Pin the edition by defining SPR_EDITION right after the path constants.
    php -r '
      $f = $argv[1]; $ed = $argv[2];
      $s = file_get_contents($f);
      $anchor = "define( \x27SPR_PLUGIN_BASENAME\x27, plugin_basename( __FILE__ ) );";
      $inject = $anchor . "\n\n" .
        "// Edition pinned by this distribution build (overrides licence/option).\n" .
        "define( \x27SPR_EDITION\x27, \x27" . $ed . "\x27 );";
      $s = str_replace($anchor, $inject, $s, $count);
      if ($count !== 1) { fwrite(STDERR, "anchor matched $count times\n"); exit(1); }
      file_put_contents($f, $s);
    ' "$dest/$SLUG.php" "$edition"
  fi

  php -l "$dest/$SLUG.php" >/dev/null

  local zip="$OUT/$SLUG-$edition-$VERSION.zip"
  ( cd "$STAGE" && zip -qr "$zip" "$SLUG" )
  echo "  built $(basename "$zip")"
}

echo "Packaging SEO Sprinkler $VERSION:"
for ed in free pro expert; do build "$ed"; done

echo "---"
for z in "$OUT/$SLUG"-*-"$VERSION".zip; do
  printf '%-40s %s\n' "$(basename "$z")" "$(du -h "$z" | cut -f1)"
done
