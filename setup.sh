#!/bin/sh
# WordPress + WooCommerce + Review Raffles auto-setup
# This runs inside the WP-CLI container on first launch.

set -e

echo "==> Waiting for WordPress to be ready..."
sleep 10

# Retry loop — WordPress may take a moment to initialize files
MAX_RETRIES=30
RETRY=0
until wp core is-installed 2>/dev/null || [ $RETRY -ge $MAX_RETRIES ]; do
    if ! wp core version 2>/dev/null; then
        echo "    WordPress files not ready yet... ($RETRY/$MAX_RETRIES)"
        sleep 3
        RETRY=$((RETRY + 1))
        continue
    fi

    echo "==> Running WordPress install..."
    wp core install \
        --url="http://localhost:8080" \
        --title="Review Raffles Test Site" \
        --admin_user=admin \
        --admin_password=admin \
        --admin_email=admin@test.local \
        --skip-email
    break
done

if ! wp core is-installed 2>/dev/null; then
    echo "ERROR: WordPress install failed after $MAX_RETRIES retries."
    exit 1
fi

echo "==> WordPress installed."

# Install and activate WooCommerce
if ! wp plugin is-installed woocommerce 2>/dev/null; then
    echo "==> Installing WooCommerce..."
    wp plugin install woocommerce --activate
else
    echo "==> Activating WooCommerce..."
    wp plugin activate woocommerce 2>/dev/null || true
fi

# Activate Review Raffles
echo "==> Activating Review Raffles plugin..."
wp plugin activate review-raffles 2>/dev/null || true

# Basic WooCommerce setup
echo "==> Configuring WooCommerce defaults..."
wp option update woocommerce_store_address "123 Test Street" 2>/dev/null || true
wp option update woocommerce_store_city "New York" 2>/dev/null || true
wp option update woocommerce_default_country "US:NY" 2>/dev/null || true
wp option update woocommerce_currency "USD" 2>/dev/null || true
wp option update woocommerce_calc_taxes "no" 2>/dev/null || true

# Create WooCommerce pages if they don't exist
wp wc --user=admin tool run install_pages 2>/dev/null || true

# Set permalink structure (required for WooCommerce)
wp rewrite structure "/%postname%/" 2>/dev/null || true
wp rewrite flush 2>/dev/null || true

echo ""
echo "============================================"
echo "  Setup complete!"
echo "============================================"
echo "  Site:        http://localhost:8080"
echo "  Admin:       http://localhost:8080/wp-admin"
echo "  phpMyAdmin:  http://localhost:8081"
echo ""
echo "  Admin login: admin / admin"
echo "============================================"
