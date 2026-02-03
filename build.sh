#!/bin/bash
# Build an installable WordPress plugin zip file
set -e

PLUGIN_SLUG="product-handel"
BUILD_DIR="$(cd "$(dirname "$0")" && pwd)/build"
SRC_DIR="$(cd "$(dirname "$0")" && pwd)"

rm -f "$BUILD_DIR/$PLUGIN_SLUG.zip"
mkdir -p "$BUILD_DIR"

cd "$SRC_DIR"
ln -sfn . "$PLUGIN_SLUG"
zip -r "$BUILD_DIR/$PLUGIN_SLUG.zip" \
    "$PLUGIN_SLUG/product-handel.php" \
    "$PLUGIN_SLUG/includes/" \
    "$PLUGIN_SLUG/assets/" \
    "$PLUGIN_SLUG/admin/" \
    -x "$PLUGIN_SLUG/build/*" "*/.*"
rm "$PLUGIN_SLUG"

echo "Built: $BUILD_DIR/$PLUGIN_SLUG.zip"
