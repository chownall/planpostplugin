#!/usr/bin/env bash
set -euo pipefail

# Packaging script for WordPress plugin
# - Bumps the Version header in planpostplugin.php on each run
# - Updates readme.txt Stable tag if present
# - Produces a zip named <folder>-<version>.zip one directory up

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLUGIN_MAIN_FILE="$ROOT_DIR/planpostplugin.php"

if [[ ! -f "$PLUGIN_MAIN_FILE" ]]; then
  echo "Error: could not find plugin main file at $PLUGIN_MAIN_FILE" >&2
  exit 1
fi

# Extract current version from plugin header: "* Version: x.y.z"
current_version="$(
  awk '/^[[:space:]]*\*[[:space:]]*Version:/ {print; exit}' "$PLUGIN_MAIN_FILE" \
    | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([^[:space:]]+).*/\1/'
)"

if [[ -z "${current_version:-}" ]]; then
  echo "Error: could not detect current version from $PLUGIN_MAIN_FILE" >&2
  exit 1
fi

# Bump the last numeric segment (patch) of the version string
IFS='.' read -r -a parts <<< "$current_version"
last_idx=$((${#parts[@]} - 1))
last_part="${parts[$last_idx]}"

if [[ "$last_part" =~ ^[0-9]+$ ]]; then
  parts[$last_idx]=$((last_part + 1))
else
  # If it has a suffix, increment the numeric prefix and keep the suffix
  num_part="$(echo "$last_part" | sed -E 's/[^0-9].*$//')"
  suffix_part="$(echo "$last_part" | sed -E 's/^[0-9]+//')"
  if [[ -z "$num_part" ]]; then num_part=0; fi
  parts[$last_idx]="$((num_part + 1))$suffix_part"
fi

new_version="$(IFS='.'; echo "${parts[*]}")"

if [[ "$new_version" == "$current_version" ]]; then
  echo "Error: failed to compute a new version" >&2
  exit 1
fi

echo "Bumping version: $current_version -> $new_version"

# Update Version line in plugin main file (first occurrence only)
awk -v ver="$new_version" '
  BEGIN { done = 0 }
  {
    if (!done && $0 ~ /^[[:space:]]*\*[[:space:]]*Version:/) {
      sub(/(^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/, "\\1" ver)
      done = 1
    }
    print
  }
' "$PLUGIN_MAIN_FILE" > "$PLUGIN_MAIN_FILE.tmp"
mv "$PLUGIN_MAIN_FILE.tmp" "$PLUGIN_MAIN_FILE"

# Optionally update readme.txt Stable tag
if [[ -f "$ROOT_DIR/readme.txt" ]]; then
  awk -v ver="$new_version" '
    BEGIN { done = 0 }
    {
      if (!done && $0 ~ /^[[:space:]]*Stable tag:/) {
        sub(/(^[[:space:]]*Stable tag:[[:space:]]*).*/, "\\1" ver)
        done = 1
      }
      print
    }
  ' "$ROOT_DIR/readme.txt" > "$ROOT_DIR/readme.txt.tmp" && mv "$ROOT_DIR/readme.txt.tmp" "$ROOT_DIR/readme.txt"
fi

# Create the zip one directory up, including the plugin directory as the root folder inside the archive
plugin_slug="$(basename "$ROOT_DIR")"
output_zip="$ROOT_DIR/../${plugin_slug}-$new_version.zip"

(
  cd "$ROOT_DIR/.."
  # Exclude common junk and VCS directories
  zip -r -q "$output_zip" "$plugin_slug" \
    -x "$plugin_slug/.git/*" \
       "$plugin_slug/.DS_Store" \
       "$plugin_slug/**/.DS_Store" \
       "$plugin_slug/**/.git/*" \
       "$plugin_slug/*.zip"
)

echo "Created: $output_zip"

