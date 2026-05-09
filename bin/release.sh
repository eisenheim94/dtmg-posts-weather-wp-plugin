#!/usr/bin/env bash
#
# Build a release-ready zip for the DTMG Posts + Weather Block.
#
# Resulting file: dist/dtmg-posts-weather-block-<version>.zip
# Version is parsed from the "Version:" field in the plugin header file —
# WordPress's own source of truth — so you only update the version in one
# place (the plugin file) and this script picks it up.
#
# Pipeline:
#   1. Rebuild vendor/ as production-only (no PHPCS, no PHPCompatibility).
#   2. Reinstall node_modules deterministically (npm ci).
#   3. Run the wp-scripts production build into build/.
#   4. Rsync the runtime payload into dist/<slug>/ with explicit excludes.
#   5. Zip from dist/ so the archive's root is the slug folder — required
#      by WordPress's "Upload Plugin" extractor, which uses the inner
#      folder name as the installed plugin's directory name.
#   6. Restore vendor/ to its dev state so PHPCS etc. continue to work.
#
# Usage: from the plugin root, `npm run release:zip` (or invoke directly).

set -euo pipefail

# Resolve the plugin root regardless of where this script is invoked from.
SCRIPT_DIR="$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
PLUGIN_DIR="$( cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd )"
cd "${PLUGIN_DIR}"

SLUG="dtmg-posts-weather-block"
PLUGIN_FILE="${SLUG}.php"

if [ ! -f "${PLUGIN_FILE}" ]; then
    echo "✗ Cannot find ${PLUGIN_FILE} in $( pwd ). Aborting." >&2
    exit 1
fi

# Parse version from the plugin file header. Match `* Version: 1.2.3` and
# print the last whitespace-separated field; tolerates extra spaces and
# the leading PHP-doc asterisk.
VERSION="$(
    grep -oE '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]+[^[:space:]]+' "${PLUGIN_FILE}" \
        | head -n1 \
        | awk '{ print $NF }'
)"
if [ -z "${VERSION}" ]; then
    echo "✗ Could not parse Version: from ${PLUGIN_FILE}. Aborting." >&2
    exit 1
fi

DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/${SLUG}"
ZIP_NAME="${SLUG}-${VERSION}.zip"
ZIP_PATH="${DIST_DIR}/${ZIP_NAME}"

echo "→ Building ${SLUG} v${VERSION}"

# 1. Production composer install. Strips dev tools (PHPCS, PHPCompatibility,
#    composer-installer) and produces a classmap autoloader for faster boot.
echo "  · composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --quiet

# 2. Deterministic node_modules so the build is reproducible from the lockfile.
echo "  · npm ci"
npm ci --silent

# 3. Production wp-scripts build → writes block/ → build/.
echo "  · npm run build"
npm run build --silent

# 4. Stage payload under a folder named exactly the slug.
echo "  · staging ${STAGE_DIR}"
rm -rf "${DIST_DIR}"
mkdir -p "${STAGE_DIR}"

# rsync with explicit excludes. Anything not listed here ships in the zip.
# Trailing slashes on directory excludes prevent accidental file matches.
rsync -a \
    --exclude='.git/' \
    --exclude='.gitignore' \
    --exclude='.idea/' \
    --exclude='.vscode/' \
    --exclude='.editorconfig' \
    --exclude='node_modules/' \
    --exclude='dist/' \
    --exclude='block/' \
    --exclude='bin/' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    --exclude='webpack.config.js' \
    --exclude='phpcs.xml.dist' \
    --exclude='composer.lock' \
    --exclude='*.zip' \
    --exclude='*.log' \
    "${PLUGIN_DIR}/" "${STAGE_DIR}/"

# 5. Zip with the slug folder as root, so unzipping yields ./<slug>/...
echo "  · zipping → ${ZIP_PATH}"
( cd "${DIST_DIR}" && zip -rq "${ZIP_NAME}" "${SLUG}" )

# Tidy: drop the staging tree, keep only the zip.
rm -rf "${STAGE_DIR}"

# 6. Restore dev composer deps so phpcs/etc. work again locally.
echo "  · restoring dev composer deps"
composer install --quiet

SIZE="$( du -h "${ZIP_PATH}" | awk '{ print $1 }' )"
echo "✓ ${ZIP_PATH} (${SIZE})"
