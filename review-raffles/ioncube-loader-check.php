<?php
/**
 * ionCube Loader Check
 *
 * This file is included at the top of the main plugin file in encoded builds.
 * It checks if the ionCube Loader is installed and shows a friendly admin
 * notice if it's missing, instead of a fatal PHP error.
 *
 * This file itself is NOT encoded — it must remain plaintext so PHP can
 * execute it even without the loader installed.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!extension_loaded('ionCube Loader')) {
    // Show admin notice and prevent plugin from loading
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>Review Raffles:</strong> This plugin requires the ';
        echo '<a href="https://www.ioncube.com/loaders.php" target="_blank">ionCube Loader</a> ';
        echo 'PHP extension to be installed on your server. ';
        echo 'Most WordPress hosting providers include it by default — ';
        echo 'please contact your hosting provider to enable it.';
        echo '</p><p>';
        echo 'Your PHP version: <strong>' . PHP_VERSION . '</strong> | ';
        echo 'Required: ionCube Loader for PHP ' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
        echo '</p></div>';
    });

    // Prevent the rest of the plugin from loading
    return;
}
