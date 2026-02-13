#!/bin/bash
# =============================================================================
# ionCube Build Script for Review Raffles Plugin
# =============================================================================
# This script creates an encoded distribution ZIP of the plugin.
#
# Prerequisites:
#   - ionCube Encoder installed (https://www.ioncube.com/encoder_eval_download.php)
#   - ioncube_encoder binary accessible in PATH or set IONCUBE_ENCODER below
#
# Usage:
#   ./build-encoded.sh                    # Build with default settings
#   ./build-encoded.sh --version 2.2      # Build with version number
# =============================================================================

set -e

# --- Configuration -----------------------------------------------------------

# Path to ionCube Encoder binary
# Update this to match your installation
IONCUBE_ENCODER="${IONCUBE_ENCODER:-ioncube_encoder}"

# Plugin source directory
PLUGIN_DIR="review-raffles"

# Output directory for encoded builds
BUILD_DIR="dist"

# Version (override with --version flag)
VERSION="2.1"

# PHP target version
PHP_TARGET="8.2"

# Files to encode (core logic + licensing)
ENCODE_FILES=(
    "review-raffles.php"
    "twwt-admin-settings.php"
    "twwt-product-notification.php"
    "twwt-order-csv.php"
    "twwt-admin-metabox.php"
    "twwt-admin-add-webinar-simple.php"
)

# Files to leave as plaintext (templates with HTML output)
PLAINTEXT_FILES=(
    "ticket-layout.php"
    "ticket-participant.php"
    "my-account-webinars.php"
    "twwt-myaccount-videos.php"
)

# Directories to copy as-is (not encoded)
COPY_DIRS=(
    "asset"
    "vendor"
)

# --- Parse arguments ---------------------------------------------------------

while [[ $# -gt 0 ]]; do
    case $1 in
        --version)
            VERSION="$2"
            shift 2
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# --- Preflight checks --------------------------------------------------------

echo "==> Review Raffles ionCube Build Script"
echo "    Version: $VERSION"
echo "    PHP Target: $PHP_TARGET"
echo ""

# Check if ionCube Encoder is available
if ! command -v "$IONCUBE_ENCODER" &> /dev/null; then
    echo "ERROR: ionCube Encoder not found at '$IONCUBE_ENCODER'"
    echo ""
    echo "Options:"
    echo "  1. Install ionCube Encoder and add to PATH"
    echo "  2. Set IONCUBE_ENCODER environment variable:"
    echo "     export IONCUBE_ENCODER=/path/to/ioncube_encoder"
    echo ""
    echo "Download from: https://www.ioncube.com/encoder_eval_download.php"
    exit 1
fi

# Check plugin source exists
if [ ! -d "$PLUGIN_DIR" ]; then
    echo "ERROR: Plugin directory '$PLUGIN_DIR' not found."
    echo "Run this script from the repository root."
    exit 1
fi

# --- Build -------------------------------------------------------------------

# Clean previous build
DIST_PLUGIN_DIR="$BUILD_DIR/review-raffles"
rm -rf "$BUILD_DIR"
mkdir -p "$DIST_PLUGIN_DIR"

echo "==> Encoding PHP files..."

for file in "${ENCODE_FILES[@]}"; do
    if [ ! -f "$PLUGIN_DIR/$file" ]; then
        echo "    WARNING: $file not found, skipping"
        continue
    fi

    echo "    Encoding: $file"
    "$IONCUBE_ENCODER" \
        --php "$PHP_TARGET" \
        --without-loader-check \
        --allow-encoding-into-source \
        --optimize max \
        --no-short-open-tags \
        --callback-file "$PLUGIN_DIR/$file" \
        -o "$DIST_PLUGIN_DIR/$file" \
        "$PLUGIN_DIR/$file"
done

echo "==> Copying plaintext files..."

for file in "${PLAINTEXT_FILES[@]}"; do
    if [ -f "$PLUGIN_DIR/$file" ]; then
        echo "    Copying: $file"
        cp "$PLUGIN_DIR/$file" "$DIST_PLUGIN_DIR/$file"
    fi
done

echo "==> Copying asset directories..."

for dir in "${COPY_DIRS[@]}"; do
    if [ -d "$PLUGIN_DIR/$dir" ]; then
        echo "    Copying: $dir/"
        cp -r "$PLUGIN_DIR/$dir" "$DIST_PLUGIN_DIR/$dir"
    fi
done

# --- Create ZIP --------------------------------------------------------------

echo "==> Creating distribution ZIP..."

ZIP_NAME="review-raffles-v${VERSION}-encoded.zip"
cd "$BUILD_DIR"
zip -r "../$ZIP_NAME" "review-raffles/" -x "*.DS_Store" "*__MACOSX*"
cd ..

# --- Summary -----------------------------------------------------------------

echo ""
echo "============================================"
echo "  Build complete!"
echo "============================================"
echo "  Output: $ZIP_NAME"
echo "  Size:   $(du -h "$ZIP_NAME" | cut -f1)"
echo ""
echo "  Encoded files:"
for file in "${ENCODE_FILES[@]}"; do
    echo "    - $file"
done
echo ""
echo "  Plaintext files:"
for file in "${PLAINTEXT_FILES[@]}"; do
    echo "    - $file"
done
echo ""
echo "  Customer's server needs: ionCube Loader for PHP $PHP_TARGET"
echo "  (Pre-installed on most WordPress hosts)"
echo "============================================"
