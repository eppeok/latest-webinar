<?php
/*
Plugin Name: Review Raffles
Description: Review Raffles – webinar and raffle management plugin for WooCommerce.
Version: 2.1
Author: Review Raffles Team
Author URI: https://www.reviewraffles.com/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: ticket
*/

define( 'TWWT_VERSION', '1.6' );
define( 'TWWT_PLUGIN', __FILE__ );
define( 'TWWT_PLUGIN_BASENAME', plugin_basename( TWWT_PLUGIN ) );
define( 'TWWT_PLUGIN_DIRPATH', plugin_dir_path( TWWT_PLUGIN ) );

/**
 * Ensure "Seat" WooCommerce attribute and term exist
 * Runs on plugin activation
 */
function twwt_ensure_seat_attribute() {

    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    global $wpdb;

    $attribute_label = 'Seat';
    $attribute_slug  = 'seat';
    $taxonomy        = 'pa_' . $attribute_slug;

    //Check if attribute exists
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
            $attribute_slug
        )
    );

    //Create attribute if missing
    if ( ! $exists ) {

        $wpdb->insert(
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            array(
                'attribute_name'    => $attribute_slug,
                'attribute_label'   => $attribute_label,
                'attribute_type'    => 'select',
                'attribute_orderby' => 'menu_order',
                'attribute_public'  => 0,
            )
        );

        // Clear attribute cache
        delete_transient( 'wc_attribute_taxonomies' );
    }

    //Register taxonomy if not registered yet
    if ( ! taxonomy_exists( $taxonomy ) ) {
        register_taxonomy(
            $taxonomy,
            array( 'product' ),
            array(
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            )
        );
    }

    //Create default term "Seat" if missing
    if ( ! term_exists( 'Seat', $taxonomy ) ) {
        wp_insert_term(
            'Seat',
            $taxonomy,
            array(
                'slug' => 'seat'
            )
        );
    }
}

/**
 * Set site timezone to America/New_York on plugin activation
 */
function twwt_set_default_timezone() {

    // Only set if not already defined
    $current_tz = get_option( 'timezone_string' );

    if ( empty( $current_tz ) || $current_tz === 'UTC' ) {
        update_option( 'timezone_string', 'America/New_York' );
    }
}
/**
 * Ensure WooCommerce hides out-of-stock products
 * Runs on plugin activation
 */
function twwt_enable_hide_out_of_stock_products() {

    // Make sure WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    if ( get_option('woocommerce_hide_out_of_stock_items') === false ) {
        update_option('woocommerce_hide_out_of_stock_items', 'yes');
    }

}
/**
 * Enable WooCommerce HPOS (High-Performance Order Storage)
 * with compatibility mode ON
 */
function twwt_enable_hpos_on_install() {

    // WooCommerce must be active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // HPOS support check (WooCommerce 7.1+)
    if ( ! function_exists( 'wc_get_container' ) ) {
        return;
    }

    // Do NOT override if store owner already chose something
    $hpos_enabled = get_option( 'woocommerce_custom_orders_table_enabled', null );

    if ( $hpos_enabled === null ) {
        // Enable HPOS
        update_option( 'woocommerce_custom_orders_table_enabled', 'yes' );

        // Enable compatibility mode (recommended)
        update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'yes' );
    }
}


register_activation_hook( __FILE__, 'twwt_plugin_activate' );
function twwt_plugin_activate() {
    // Set site timezone
    twwt_set_default_timezone();

    // Ensure Seat attribute exists
    twwt_ensure_seat_attribute();

    // Enable hide out-of-stock products
    twwt_enable_hide_out_of_stock_products();

    // Enable HPOS with compatibility mode
    twwt_enable_hpos_on_install();

    // Schedule daily batch job
    if (function_exists('twwt_schedule_daily_batch')) {
        twwt_schedule_daily_batch();
    }
}

register_deactivation_hook( __FILE__, 'twwt_plugin_deactivate' );
function twwt_plugin_deactivate() {
    if (wp_next_scheduled('twwt_daily_batch_hook')) {
        wp_clear_scheduled_hook('twwt_daily_batch_hook');
    }
}


register_activation_hook( __FILE__, 'my_plugin_create_winner_page' );
function my_plugin_create_winner_page() {
    $winner_page = get_page_by_path( 'winner' );
    if ( ! $winner_page ) {
        $winner_page_id = wp_insert_post(
            array(
                'post_title'   => 'Winner',
                'post_content' => '[my_shortcode]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'winner'
            )
        );
    }

    // Ensure the twilio_log table exists on activation
    if ( function_exists('twwt_ensure_twilio_log_table_exists') ) {
        twwt_ensure_twilio_log_table_exists();
    } else {
        // Fallback: attempt to call it (function is defined in this file so this should succeed)
        twwt_ensure_twilio_log_table_exists();
    }

}

/**
 * Ensure the twilio_log table exists. Safe to call multiple times.
 */
function twwt_ensure_twilio_log_table_exists() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'twilio_log';
    $collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      phone_number VARCHAR(64) DEFAULT '' NOT NULL,
      message TEXT,
      response LONGTEXT,
      type TINYINT(1) DEFAULT 0,
      provider VARCHAR(64) DEFAULT '' NOT NULL,
      date_added DATETIME DEFAULT '0000-00-00 00:00:00',
      PRIMARY KEY  (id)
    ) {$collate};";

    // Use direct query; dbDelta isn't required for a simple IF NOT EXISTS create
    $wpdb->query($sql);
}

function custom_add_to_cart_redirect($url) {
    if (!empty($_REQUEST['add-to-cart'])) {
        return wc_get_cart_url(); // Redirect to the cart page
    }
    return $url;
}
add_filter('woocommerce_add_to_cart_redirect', 'custom_add_to_cart_redirect');

// Disable AJAX add to cart buttons on archives
function custom_disable_ajax_add_to_cart($link, $product) {
    if (is_post_type_archive('product')) {
        return str_replace('ajax_add_to_cart', 'add_to_cart_button', $link);
    }
    return $link;
}
add_filter('woocommerce_loop_add_to_cart_link', 'custom_disable_ajax_add_to_cart', 10, 2);

function delete_custom_page_on_deactivation() {
    $page_slug = 'winner';
	$page = get_page_by_path($page_slug);
	$WinnerPageId = $page->ID;
    
    if ( $WinnerPageId ) {
        wp_delete_post( $WinnerPageId, true );
    }
}
register_deactivation_hook( __FILE__, 'delete_custom_page_on_deactivation' );

/* ---------------------------------------------------------------------------
   Seats/Stock helpers
--------------------------------------------------------------------------- */
function twwt_count_perma_booked_seats( $variation_id ){
    global $wpdb;
    $like = $wpdb->esc_like('perma_booked_seat_') . '%';
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
        $variation_id, $like
    ));
}

/**
 * Recalculate a variation's stock from:
 *   available = max_seats - perma_booked_seats
 * Uses _variable_text_field (your "Maximum Seats") as max_seats.
 */
function twwt_recalculate_variation_stock( $variation_id ){
    $max = (int) get_post_meta( $variation_id, '_variable_text_field', true );
    if ( $max <= 0 ) { return; }

    $booked    = twwt_count_perma_booked_seats( $variation_id );
    $available = max( 0, $max - $booked );

    $variation = wc_get_product( $variation_id );
    if ( $variation && $variation->is_type('variation') ) {
        $variation->set_manage_stock( true );
        $variation->set_backorders( 'no' );
        $variation->set_stock_quantity( $available );
        $variation->set_stock_status( $available > 0 ? 'instock' : 'outofstock' );
        $variation->save();
        wc_delete_product_transients( $variation_id );
    }
    return $available;
}

/** Human message for non-opted-in cases */
function twwt_ottertext_block_reason($code) {
    switch ((string)$code) {
        case '1': return 'OtterText: customer exists but has not confirmed yet (New Customer). Opt-in SMS was sent.';
        case '2': return 'OtterText: opt-in requested; awaiting confirmation reply.';
        case '4': return 'OtterText: customer opted out. You must obtain consent again.';
        case '5': return 'OtterText: invalid phone number.';
        default:  return 'OtterText: customer not found; opt-in SMS was sent.';
    }
}


/* ---------------------------------------------------------------------------
   OtterText sender
--------------------------------------------------------------------------- */
function wp_ottertext_sms($to_mobile, $msg){
    $settings = get_option('twwt_woo_settings');
    $api_key  = isset($settings['ottertext_api_key']) ? trim($settings['ottertext_api_key']) : '';
    $partner  = isset($settings['ottertext_partner']) ? trim($settings['ottertext_partner']) : 'WordPress';

    if ($api_key === '' || $to_mobile === '' || $msg === '') { return; }

    // Normalize to E.164 (+1XXXXXXXXXX). Your screenshot shows raw 6146491554.
    $e164 = twwt_normalize_us_phone($to_mobile);
    if (!$e164) {
        //twwt_twilio_log($to_mobile, $msg, 'Invalid US phone format', 0, 'ottertext');
        return;
    }

    // 1) Check current opt-in status
    $status = twwt_ottertext_get_optin_status($e164, $partner, $api_key); // '1'..'5' or ''
    // Store a quick stamp on the user if we can find them
    $user_query = new WP_User_Query([
        'meta_key'   => 'billing_phone',
        'meta_value' => $to_mobile,
        'number'     => 1,
        'fields'     => 'all',
    ]);
    $users = $user_query->get_results();
    $user  = !empty($users) ? $users[0] : null;

    if ($user) {
        update_user_meta($user->ID, 'ottertext_optincheck', $status);
        update_user_meta($user->ID, 'ottertext_phone', $e164);
        update_user_meta($user->ID, 'ottertext_optin_last_checked', current_time('mysql'));
    }

    // 2) If not opted-in, try to add/update the customer to trigger OtterText opt-in,
    //    then log and exit gracefully (no send).
    if ($status !== '3') {
        // Try to create/update customer on OtterText (fires their confirmation SMS)
        $first = $user ? get_user_meta($user->ID, 'billing_first_name', true) : '';
        $last  = $user ? get_user_meta($user->ID, 'billing_last_name', true)  : '';
        $email = $user ? ($user->user_email ?? '') : '';
        $zip   = $user ? get_user_meta($user->ID, 'billing_postcode', true)   : '';

        $add = twwt_ottertext_add_customer($e164, $first, $last, $email, $zip, $partner, $api_key);

        // Status/message to log
        $reason = twwt_ottertext_block_reason($status);
        $logMsg = $add['ok']
            ? $reason
            : $reason . ' | add_customer failed: ' . $add['body'];

        //twwt_twilio_log($e164, $msg, $logMsg, 0, 'ottertext');
        return; // DO NOT attempt to send (TCPA)
    }

    // 3) Opted-in → send now
    $endpoint = 'https://app.ottertext.com/api/customers/sendmessage';
    $body = array(
        'customer'   => $e164,
        'sms_or_mms' => '1',     // 1 = SMS
        'send_type'  => '1',     // 1 = instant
        'partner'    => $partner,
        'message'    => $msg
    );

    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ),
        'body'    => $body,
        'timeout' => 20,
    ));

    if (is_wp_error($response)) {
        //twwt_twilio_log($e164, $msg, $response->get_error_message(), 0, 'ottertext');
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    if ($code >= 200 && $code < 300) {
        //twwt_twilio_log($e164, $msg, $raw, 1, 'ottertext');
        return $raw;
    } else {
        //twwt_twilio_log($e164, $msg, $raw, 0, 'ottertext');
        return;
    }
}

#Twilio
require_once 'vendor/autoload.php';
use Twilio\Rest\Client;
function wp_twilio_sms($to_mobile, $msg){
    $settings = get_option( 'twwt_woo_settings' );

    // Route to OtterText if chosen
    if (isset($settings['sms_provider']) && $settings['sms_provider'] === 'ottertext') {
        return wp_ottertext_sms($to_mobile, $msg);
    }

    $sid   = isset($settings['twilio_sid'])   ? $settings['twilio_sid']   : '';
    $token = isset($settings['twilio_token']) ? $settings['twilio_token'] : '';

    if($sid==""){ return; }
    if($token==""){ return; }
    if($to_mobile==""){ return; }
    if($msg==""){ return; }

    $to_mobile = str_replace(array(' ', '-',')','('),'', $to_mobile);
    $to_mobile = str_replace('+1','', $to_mobile);
    $to_mobile = str_replace('+','', $to_mobile);
    $to_mobile = '+1'.$to_mobile;

    $twilio = new Client($sid, $token);
    try{
        $response = $twilio->messages->create(
            $to_mobile,
            array("body" => $msg, "from" => $settings['twilio_from'])
        );
        //twwt_twilio_log($to_mobile, $msg, $response, 1, 'twilio');
        return $response;
    } catch(Exception $ex){
        //twwt_twilio_log($to_mobile, $msg, $ex, 0, 'twilio');
        return;
    }
}
/**
 * === OtterText Bulk Sync (auto-push Woo customers & store opt-in) ===
 * Paste this block after the Twilio/OtterText sender functions.
 */

/** Normalize US numbers to +1XXXXXXXXXX (returns '' if invalid/non-US). */
function twwt_normalize_us_phone($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    // Keep only digits
    $digits = preg_replace('/\D+/', '', $raw);

    // If it starts with 1 and is longer than 10, keep the last 10 digits
    if (substr($digits, 0, 1) === '1' && strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }

    // If we still don't have exactly 10 digits, treat as invalid
    if (strlen($digits) !== 10) {
        return '';
    }

    // Return E.164 US format
    return '+1' . $digits;
}


/** Add/Update a customer on OtterText (triggers opt-in SMS for new). */
function twwt_ottertext_add_customer($phone_e164, $first, $last, $email, $zip, $partner, $api_key) {
    if (!$phone_e164 || !$api_key || !$partner) {
        return array('ok' => false, 'code' => 0, 'body' => 'Missing required params');
    }
    $resp = wp_remote_post('https://app.ottertext.com/api/customers/add', array(
        'timeout' => 20,
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ),
        'body' => array(
            'phone'      => $phone_e164,
            'first_name' => substr((string)$first, 0, 255),
            'last_name'  => substr((string)$last, 0, 255),
            'email'      => substr((string)$email, 0, 255),
            'zip'        => substr((string)$zip, 0, 10),
            'partner'    => $partner,
        ),
    ));
    if (is_wp_error($resp)) {
        return array('ok' => false, 'code' => 0, 'body' => $resp->get_error_message());
    }
    return array(
        'ok'   => wp_remote_retrieve_response_code($resp) >= 200 && wp_remote_retrieve_response_code($resp) < 300,
        'code' => wp_remote_retrieve_response_code($resp),
        'body' => wp_remote_retrieve_body($resp),
    );
}

/** Query the customer's current opt-in status; returns '1'..'5' or ''. */
function twwt_ottertext_get_optin_status($phone_e164, $partner, $api_key) {
    if (!$phone_e164 || !$api_key || !$partner) return '';
    $url = add_query_arg(array(
        'phone'   => $phone_e164,
        'partner' => $partner,
    ), 'https://app.ottertext.com/api/customers/optinstatus');

    $resp = wp_remote_get($url, array(
        'timeout' => 20,
        'headers' => array('Authorization' => 'Bearer ' . $api_key),
    ));
    if (is_wp_error($resp)) return '';
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    return !empty($body['customer']['optincheck']) ? (string)$body['customer']['optincheck'] : '';
}

/** Batch sync Woo users → OtterText (US numbers only). Saves opt-in on user. */
// new modification
/** Batch sync Woo users → OtterText (US numbers only).
 *  - Only users with twwy_opt_notification = 1
 *  - Every run re-triggers OtterText opt-in SMS for users NOT approved yet
 *    (status !== 3 and !== 4).
 *  - Returns an array of stats for the admin notice.
 */
function twwt_ottertext_batch_sync() {
    $opts     = get_option('twwt_woo_settings');
    $api_key  = isset($opts['ottertext_api_key']) ? trim($opts['ottertext_api_key']) : '';
    $partner  = isset($opts['ottertext_partner']) ? trim($opts['ottertext_partner']) : 'WordPress';

    // If misconfigured, bail with an error message
    if (!$api_key || !$partner) {
        return array(
            'error' => 'Missing OtterText API key or partner. Please check Review Raffles → Settings → SMS Provider.'
        );
    }

    // Stats we’ll show to the admin
    $stats = array(
        'total_users'           => 0,
        'eligible'              => 0,  // opted-in locally
        'skipped_local_optout'  => 0,
        'invalid_phone'         => 0,
        'already_opted_in'      => 0,
        'opted_out'             => 0,
        'optin_sms_triggered'   => 0,
        'errors'                => 0,
    );

    // Pull users in manageable chunks
    $user_query = new WP_User_Query(array(
        // 'role__in' => array('customer','subscriber'), // uncomment if you want to limit by role
        'fields'   => array('ID'),
        'number'   => 500,
        'paged'    => 1,
    ));
    $users = (array) $user_query->get_results();
    if (empty($users)) {
        return $stats;
    }

    $processed = 0;

    foreach ($users as $u) {
        $uid = is_object($u) ? $u->ID : (int) $u;
        $stats['total_users']++;

        // Only sync users who opted in on your site
        $local_optin = get_user_meta($uid, 'twwy_opt_notification', true);
        if ($local_optin != '1') {
            $stats['skipped_local_optout']++;
            continue;
        }
        $stats['eligible']++;

        $phone = get_user_meta($uid, 'billing_phone', true);
        $e164  = twwt_normalize_us_phone($phone);
        if (!$e164) {
            $stats['invalid_phone']++;
            continue; // skip invalid/non-US
        }

        $first = get_user_meta($uid, 'billing_first_name', true);
        $last  = get_user_meta($uid, 'billing_last_name', true);
        $user_obj = get_userdata($uid);
        $email = $user_obj ? $user_obj->user_email : '';
        $zip   = get_user_meta($uid, 'billing_postcode', true);

        // 1) Check current opt-in status in OtterText
        $status_before = twwt_ottertext_get_optin_status($e164, $partner, $api_key);

        // 2) Decide whether to call add_customer (which sends opt-in SMS)
        //    We ONLY re-trigger for users who are NOT approved:
        //    - Not Opted In (3) and not Opted Out (4)
        $should_add = ($status_before !== '3' && $status_before !== '4');

        if ($status_before === '3') {
            $stats['already_opted_in']++;
        } elseif ($status_before === '4') {
            $stats['opted_out']++;
        }

        if ($should_add) {
            $add = twwt_ottertext_add_customer($e164, $first, $last, $email, $zip, $partner, $api_key);

            if (!empty($add['ok'])) {
                $stats['optin_sms_triggered']++;
            } else {
                $stats['errors']++;
            }

            // Re-check status after add/update
            $opt = twwt_ottertext_get_optin_status($e164, $partner, $api_key);
        } else {
            // Already Opted In or Opted Out – do not send opt-in SMS again
            $add = array(
                'ok'   => true,
                'code' => 200,
                'body' => 'Skipped add_customer; status=' . $status_before,
            );
            $opt = $status_before;
        }

        // 3) Save the latest status to user meta for admin display
        if ($opt !== '') {
            update_user_meta($uid, 'ottertext_optincheck', $opt);
            update_user_meta($uid, 'ottertext_phone', $e164);
            update_user_meta($uid, 'ottertext_last_sync', current_time('mysql'));
        }

        // 4) Log what happened (for debugging in twilio_log table)
        if (function_exists('twwt_twilio_log')) {
            $log_msg = 'Bulk sync: status_before=' . $status_before . ' status_after=' . $opt;
            if (empty($add['ok'])) {
                $log_msg .= ' | add_customer_error=' . $add['body'];
            }
            //twwt_twilio_log($e164, 'OtterText bulk add', $log_msg, !empty($add['ok']) ? 1 : 0, 'ottertext-import');
        }

        $processed++;
        if ($processed % 25 === 0) {
            sleep(1); // gentle throttling
        }
    }

    return $stats;
}





/** Add custom cron schedule for every 5 minutes */
add_filter('cron_schedules', function($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every 5 Minutes')
    );
    return $schedules;
});

/** Schedule the event if not already scheduled */
if (!wp_next_scheduled('twwt_ottertext_cron')) {
    wp_schedule_event(time() + 300, 'every_five_minutes', 'twwt_ottertext_cron');
}

/** Hook the cron job to your function */
add_action('twwt_ottertext_cron', 'twwt_ottertext_batch_sync');


/** Optional: Tools → OtterText Sync manual trigger (admin only). */
add_action('admin_menu', function () {
    add_submenu_page(
        'tools.php',
        'OtterText Sync',
        'OtterText Sync',
        'manage_options',
        'twwt-ottertext-sync',
        function () {
            if (!current_user_can('manage_options')) return;

            $stats = null;

            if (isset($_POST['twwt_run_sync']) && check_admin_referer('twwt_run_sync')) {
                $stats = twwt_ottertext_batch_sync();

                // Show a nice summary box
                if (isset($stats['error'])) {
                    echo '<div class="notice notice-error"><p><strong>OtterText Sync Error:</strong> ' . esc_html($stats['error']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>OtterText sync finished.</strong></p>';
                    echo '<ul style="margin-left:1.5em;list-style:disc;">';
                    echo '<li>Total users scanned: <strong>' . intval($stats['total_users']) . '</strong></li>';
                    echo '<li>Eligible (opted-in on site): <strong>' . intval($stats['eligible']) . '</strong></li>';
                    echo '<li>Local opt-outs skipped: <strong>' . intval($stats['skipped_local_optout']) . '</strong></li>';
                    echo '<li>Invalid / non-US phone numbers: <strong>' . intval($stats['invalid_phone']) . '</strong></li>';
                    echo '<li>Already Opted In (OtterText): <strong>' . intval($stats['already_opted_in']) . '</strong></li>';
                    echo '<li>Opted Out (OtterText): <strong>' . intval($stats['opted_out']) . '</strong></li>';
                    echo '<li>Opt-in SMS (re)triggered this run: <strong>' . intval($stats['optin_sms_triggered']) . '</strong></li>';
                    echo '<li>Errors when calling OtterText: <strong>' . intval($stats['errors']) . '</strong></li>';
                    echo '</ul>';
                    echo '<p><small>Note: Opt-in SMS is only re-sent for users who are not yet approved (not Opted In or Opted Out) on the OtterText side.</small></p>';
                    echo '</div>';
                }
            }

            echo '<div class="wrap"><h1>OtterText Bulk Sync</h1>';
            echo '<p>Click the button below to push eligible users to OtterText and (re)send opt-in SMS to anyone who is still not approved.</p>';
            echo '<form method="post">';
            wp_nonce_field('twwt_run_sync');
            submit_button('Run Sync Now', 'primary', 'twwt_run_sync');
            echo '</form></div>';
        }
    );
});


function twwt_twilio_log($phone_number, $message, $response, $type, $provider = 'twilio'){
    global $wpdb;
    $response = serialize($response);
    $table = $wpdb->prefix.'twilio_log';
    // Ensure the table exists (handles cases where plugin was not properly activated)
    if ( ! function_exists('twwt_ensure_twilio_log_table_exists') ) {
        // If helper somehow missing, attempt a safe create inline
        twwt_ensure_twilio_log_table_exists();
    } else {
        twwt_ensure_twilio_log_table_exists();
    }

    $data = array(
        'phone_number' => $phone_number,
        'message'      => $message,
        'response'     => $response,
        'type'         => $type,
        'provider'     => $provider,
        'date_added'   => @gmdate('Y-m-d H:i:s')
    );

    // Match formats to the $data columns (6 columns)
    $format = array('%s', '%s', '%s', '%d', '%s', '%s');
    $wpdb->insert($table, $data, $format);

    return $wpdb->insert_id;
}
#Twilio

/*CREATE ADMIN MENU*/
/*
 * Add our Custom Fields to simple products
 */
function twwt_woo_add_custom_fields() {

	global $woocommerce, $post;

	echo '<div class="options_group">';

 	// Text Field
	woocommerce_wp_text_input(
		array(
			'id'          => '_text_field',
			'label'       => __( 'My Text Field', 'woocommerce' ),
			'placeholder' => 'http://',
			'desc_tip'    => true,
			'description' => __( "Here's some really helpful tooltip text.", "woocommerce" )
		)
 	);

 	// Number Field
	woocommerce_wp_text_input(
 		array(
 			'id'                => '_number_field',
 			'label'             => __( 'My Number Field', 'woocommerce' ),
 			'placeholder'       => '',
			'desc_tip'    		=> false,
 			'description'       => __( "Here's some really helpful text that appears next to the field.", 'woocommerce' ),
 			'type'              => 'number',
 			'custom_attributes' => array(
 					'step' 	=> 'any',
 					'min'	=> '0'
 				)
 		)
 	);

 	// Textarea
 	woocommerce_wp_textarea_input(
 		array(
 			'id'          => '_textarea',
 			'label'       => __( 'My Textarea', 'woocommerce' ),
 			'placeholder' => '',
			'desc_tip'    => true,
			'description' => __( "Here's some really helpful tooltip text.", "woocommerce" )
 		)
 	);

 	// Select
 	woocommerce_wp_select(
 		array(
 			'id'      => '_select',
 			'label'   => __( 'My Select Field', 'woocommerce' ),
 			'options' => array(
 				'one'   => __( 'Option 1', 'woocommerce' ),
 				'two'   => __( 'Option 2', 'woocommerce' ),
 				'three' => __( 'Option 3', 'woocommerce' )
 				)
 		)
 	);

 	// Checkbox
 	woocommerce_wp_checkbox(
 		array(
 			'id'            => '_checkbox',
 			'wrapper_class' => 'show_if_simple',
 			'label'         => __('My Checkbox Field', 'woocommerce' ),
 			'description'   => __( 'Check me!', 'woocommerce' )
 		)
 	);

 	// Hidden field
 	woocommerce_wp_hidden_input(
 		array(
 			'id'    => '_hidden_field',
 			'value' => 'hidden_value'
 			)
 	);

 	// Custom field Type
 	?>
 	<p class="form-field custom_field_type">
 		<label for="custom_field_type"><?php echo __( 'Custom Field Type', 'woocommerce' ); ?></label>
 		<span class="wrap">
 			<?php $custom_field_type = get_post_meta( $post->ID, '_custom_field_type', true ); ?>
 			<input placeholder="<?php _e( 'Field One', 'woocommerce' ); ?>" class="" type="number" name="_field_one" value="<?php echo $custom_field_type[0]; ?>" step="any" min="0" style="width: 100px;" />
 			<input placeholder="<?php _e( 'Field Two', 'woocommerce' ); ?>" type="number" name="_field_two" value="<?php echo $custom_field_type[1]; ?>" step="any" min="0" style="width: 100px;" />
 		</span>
 		<span class="description"><?php _e( 'Place your own description here!', 'woocommerce' ); ?></span>
 	</p>
 	<?php

 	echo '</div>';

}


/*
 * Add our Custom Fields to variable products
 */
function twwt_woo_add_custom_variation_fields( $loop, $variation_data, $variation ) {


	$license_manager = new twwt_woo_settings_page();
   if ($license_manager->twwt_license_key_valid()) {

		echo '<div class="options_group form-row form-row-full">';

	 	// Text Field
		woocommerce_wp_text_input(
			array(
				'id'          => '_variable_text_field[' . $variation->ID . ']',
				'label'       => __( 'Maximum Seats', 'woocommerce' ),
				'type'		  => 'number',
				'placeholder' => 'Maximum Seats',
				'desc_tip'    => true,
				'description' => __( "Maximum number of seats for the variation", "woocommerce" ),
				'value' => get_post_meta( $variation->ID, '_variable_text_field', true )
			)
	 	);

		// Add extra custom fields here as necessary...

		echo '</div>';

	}

}
// Variations tab
//add_action( 'woocommerce_variation_options', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After variation Enabled/Downloadable/Virtual/Manage Stock checkboxes
add_action( 'woocommerce_variation_options_pricing', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After Price fields
//add_action( 'woocommerce_variation_options_inventory', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After Manage Stock fields
//add_action( 'woocommerce_variation_options_dimensions', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After Weight/Dimension fields
//add_action( 'woocommerce_variation_options_tax', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After Shipping/Tax Class fields
//add_action( 'woocommerce_variation_options_download', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After Download fields
//add_action( 'woocommerce_product_after_variable_attributes', 'twwt_woo_add_custom_variation_fields', 10, 3 ); // After all Variation fields

/*
 * Save our variable product fields
 */
function twwt_woo_add_custom_variation_fields_save( $post_id ){

 	// Text Field
 	$woocommerce_text_field = $_POST['_variable_text_field'][ $post_id ];
	update_post_meta( $post_id, '_variable_text_field', esc_attr( $woocommerce_text_field ) );

}
add_action( 'woocommerce_save_product_variation', 'twwt_woo_add_custom_variation_fields_save', 10, 2 );

add_action('woocommerce_before_add_to_cart_button', 'custom_product_meta_end');
function custom_product_meta_end(){

$license_manager = new twwt_woo_settings_page();
   if ($license_manager->twwt_license_key_valid()) {
	require plugin_dir_path( __FILE__ ) . 'ticket-layout.php';

}

}

add_action( 'woocommerce_single_product_summary', 'custom_single_product_summary', 45 );
function custom_single_product_summary() {
	global $product;
	$product_id = $product->get_id();
	if(twwt_woo_product_seat($product_id)){
		$winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
		if($winner_id>0){
			
		}
		else{
			if( current_user_can('editor') || current_user_can('admstrator') ) {
				$page_slug = 'winner';
				$page = get_page_by_path($page_slug);
				$Winnerpermalink = get_permalink($page->ID);
				?>
				<p class="mt-4 mb-0"><a target="_blank" href="<?php echo $Winnerpermalink;?>?pid=<?php echo $product_id;?>" class="btn btn-winner">Select Attendee</a></p>
				<?php
			}
		}
	}
}

function twwt_woo_scripts(){
	wp_enqueue_style( 'twwt_woo', plugins_url('asset/css/style.css',__FILE__ ), array(), TWWT_VERSION );
	wp_enqueue_script( 'twwt_woo_js', plugins_url('asset/js/main.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
	
	//passing variables to the javascript file
	wp_localize_script('twwt_woo_js', 'twwtfa', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce('ajax_nonce')
	));
}
add_action( 'wp_enqueue_scripts', 'twwt_woo_scripts' );

function twwt_woo_ajax_function(){
	check_ajax_referer('ajax_nonce', 'nonce');
}

add_action( 'wp_ajax_nopriv_twwt_woo_ajax_function', 'twwt_woo_ajax_function' );
add_action( 'wp_ajax_twwt_woo_ajax_function', 'twwt_woo_ajax_function' );


function get_available_ticket_count($product_id){
	$product = wc_get_product($product_id);
	$current_products = $product->get_children();
	//echo '<pre>';
	$ticket_left = 0;
	foreach($current_products as $item_id){
		
		$variation_price = get_variation_price_by_id($product_id, $item_id);
		$ticket_left += $variation_price->ticket_left;
	}
	return $ticket_left;
}


function get_variation_price_by_id($product_id, $variation_id){
	$currency_symbol = get_woocommerce_currency_symbol();
	$product = new WC_Product_Variable($product_id);
	$variations = $product->get_available_variations();
	$var_data = array();
	foreach ($variations as $variation) {
		if($variation['variation_id'] == $variation_id){
			
			$display_regular_price = '<span class="currency">'. $currency_symbol .'</span>'.$variation['display_regular_price'];
			$display_price = '<span class="currency">'. $currency_symbol .'</span>'.$variation['display_price'];
			$variation_name = implode(' ', $variation['attributes']);
			$ticket_left = $variation['max_qty'];
		}
	}
 
	//Check if Regular price is equal with Sale price (Display price)
	if ($display_regular_price == $display_price){
		$display_price = false;
	}
 
	$priceArray = array(
		'display_regular_price' => $display_regular_price,
		'display_price' => $display_price,
		'variation_name' => $variation_name,
		'ticket_left' => $ticket_left,
	);
	$priceObject = (object)$priceArray;
	return $priceObject;
}

/**
 * Validate our custom text input field value
 */
function twwt_woo_add_to_cart_validation($passed, $product_id, $quantity, $variation_id = null) {
    if (!empty($_POST['seat'])) {
        $booked_seats = $_POST['seat'];

        // Check if the count of booked seats matches the quantity
        if (count($booked_seats) == $quantity) {
            foreach ($booked_seats as $seat) {
                $availability = twwt_woo_get_availability($variation_id, $seat);

                // Check seat availability
                if ($availability) {
                    $passed = false;
                    wc_add_notice(__('Selected seat "' . $seat . '" is not available for booking.', 'woocommerce'), 'error');
                    // No need for the 'break' statement here
                }
            }
        } else {
            // Quantity and booked seats count don't match
            $passed = false;
            wc_add_notice(__('Please try again later.', 'woocommerce'), 'error');
        }
    }

    if ($passed) {
        if (!WC()->cart->is_empty()) {
            // Loop through the cart items and remove items with specific conditions
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id_n = $cart_item['product_id'];
                $variation_id_n = $cart_item['variation_id'];

                // Check if the product has specific conditions for removal
                if (twwt_woo_product_seat($product_id_n)) {
                    if ($product_id_n == $product_id) {
                        WC()->cart->remove_cart_item($cart_item_key);
                    }
                }
            }
        }

        // Set cookies for seat selection (not sure if this is necessary for debugging)
        setcookie('seat_selected', time() + 600, time() + 600, '/');
        setcookie("seat_selected_{$variation_id}", time() + 600, time() + 600, '/');
    }

    return $passed;
}

// Hook this validation function into WooCommerce
add_filter('woocommerce_add_to_cart_validation', 'twwt_woo_add_to_cart_validation', 10, 4);

function twwt_woo_check_cart_timing(){
	if(is_admin()){
		return;
	}
	
	if( ! WC()->cart->is_empty() ){
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			// get the data of the cart item
			$product_id         = $cart_item['product_id'];
			$variation_id       = $cart_item['variation_id'];
			if(twwt_woo_product_seat($cart_item['product_id'])){
				if(!isset($_COOKIE['seat_selected_'.$variation_id])) {
					WC()->cart->remove_cart_item($cart_item_key);
				}
			}
		}
	}
	
}
add_action('wp', 'twwt_woo_check_cart_timing');


/**
 * Add custom cart item data
 */
function twwt_woo_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
 if( isset( $_POST['seat'] ) ) {
	 $booked_seats = $_POST['seat'];
	 $cart_item_data['seat'] = implode(', ', $booked_seats );
	 
	 //$booking_details = array('seats' => $booked_seats, 'booking_time' => current_time( 'mysql' ));
	 foreach($booked_seats as $seat){
		 update_post_meta( $variation_id, 'temp_booked_seat_'.$seat, current_time( 'timestamp' ) );
	 }
 }
 return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'twwt_woo_add_cart_item_data', 10, 3 );

/**
 * Display custom item data in the cart
 */
function twwt_woo_get_item_data( $item_data, $cart_item_data ) {
	if( isset( $cart_item_data['seat'] ) ) {
		$item_data[] = array(
			'key' => __( 'Seat(s)', 'woocommerce' ),
			'value' => wc_clean( $cart_item_data['seat'] )
		);
	}
	return $item_data;
}
add_filter( 'woocommerce_get_item_data', 'twwt_woo_get_item_data', 10, 2 );

/**
 * Add custom meta to order
 */
function twwt_woo_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
	if( isset( $values['seat'] ) ) {
		$item->add_meta_data(
			__( 'Seat(s)', 'woocommerce' ),
			$values['seat'],
			true
		);
		//$booked_seats = explode(', ',$values['seat']);
		//$variation_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
		/*if ( $order->has_status( 'processing' ) ) {
			foreach($booked_seats as $seat){
				update_post_meta( $variation_id, 'perma_booked_seat_'.$seat, current_time( 'timestamp' ) );
			}
		}*/
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'twwt_woo_checkout_create_order_line_item', 10, 4 );

function twwt_woo_get_availability_v2($item_id, $ticket_no){
	#PERMANENT CHECKING
	$perma_book = get_post_meta($item_id, 'perma_booked_seat_'.$ticket_no, true);
	if($perma_book!=""){
		return array("status" => true, "type" => "perma");
	}
	#TEMP CHECKING
	$temp_booking_time = get_post_meta($item_id, 'temp_booked_seat_'.$ticket_no, true);
	if($temp_booking_time!=""){
		$current_time = current_time( 'timestamp' );
		$time_diff = $current_time - $temp_booking_time;
		if($time_diff>300){
			return array("status" => false, "type" => "");
		}
		else{
			return array("status" => true, "type" => "temp");
		}
	}
}
function twwt_woo_get_availability($item_id, $ticket_no){
	#PERMANENT CHECKING
	$perma_book = get_post_meta($item_id, 'perma_booked_seat_'.$ticket_no, true);
	if($perma_book!=""){
		return true;
	}
	#TEMP CHECKING
	$temp_booking_time = get_post_meta($item_id, 'temp_booked_seat_'.$ticket_no, true);
	if($temp_booking_time!=""){
		$current_time = current_time( 'timestamp' );
		$time_diff = $current_time - $temp_booking_time;
		if($time_diff>300){
			return false;
		}
		else{
			return true;
		}
	}
}

add_filter( 'woocommerce_cart_item_quantity', 'twwt_woo_cart_item_quantity', 10, 3 );
function twwt_woo_cart_item_quantity( $product_quantity, $cart_item_key, $cart_item ){
    if( is_cart() ){
		if(twwt_woo_product_seat($cart_item['product_id'])){
        	$product_quantity = sprintf( '%2$s <input type="hidden" name="cart[%1$s][qty]" value="%2$s" />', $cart_item_key, $cart_item['quantity'] );
		}
    }
    return $product_quantity;
}

function twwt_woo_product_seat($product_id){
	$woo_seat_show = get_post_meta( $product_id, 'woo_seat_show', true );
	if($woo_seat_show==1){
		return true;
	}
}
function twwt_woo_login_check(){
	$options = get_option( 'twwt_woo_settings' );
	if($options['login_restrict']==1){
		if ( !is_user_logged_in() ) {
			return array('status' => true, 'text' => $options['login_button_text']);
		}
	}
	return array('status' => false);
}


#SHOW NOTICE


/*add_action('template_redirect', 'twwt_woo_show_notice');
function twwt_woo_show_notice(){
	if( ! WC()->cart->is_empty() ){
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			// get the data of the cart item
			$product_id         = $cart_item['product_id'];
			$variation_id       = $cart_item['variation_id'];
			//wc_add_notice(  __('<div class="myseats" data-id="'.$variation_id.'">'.json_encode($cart_item).'</div>'), 'error' );
			if(twwt_woo_product_seat($cart_item['product_id'])){
				if ((is_cart() || is_checkout()) && ! is_wc_endpoint_url() ) {
					$pname = html_entity_decode( get_the_title($product_id));
					wc_add_notice(  __('<span class="twwt_woo_cc_notice" data-id="'.$variation_id.'" data-title="'.$pname.'">Please wait...</span>'), 'error' );
				}
				//break;
			}
		}
	}
}
*/



add_action('template_redirect', 'twwt_woo_show_notice');
function twwt_woo_show_notice() {
    if (!WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'];

            if (twwt_woo_product_seat($cart_item['product_id'])) {
                add_action('woocommerce_before_cart', 'twwt_woo_display_notice', 5);
                add_action('woocommerce_before_checkout_form', 'twwt_woo_display_notice', 5);
                break; // Add notice only once
            }
        }
    }
}

function twwt_woo_display_notice() {
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'];

        if (twwt_woo_product_seat($cart_item['product_id'])) {
            $pname = html_entity_decode(get_the_title($product_id));
            echo '<ul class="woocommerce-error" role="alert"><li><span class="twwt_woo_cc_notice" data-id="' . $variation_id . '" data-title="' . $pname . '">Please wait...</span></li></ul>';
        }
    }
}



add_action( 'woocommerce_after_shop_loop_item_title', 'twwt_woo_show_stock_shop', 10 );
  
function twwt_woo_show_stock_shop() {
    global $product;


  $license_manager = new twwt_woo_settings_page();
   if ($license_manager->twwt_license_key_valid()) {

		if ($product->is_type( 'variable' ))
	    {
			if(get_post_meta( $product->get_id(), 'woo_seat_show', true )==1){
				echo '<p class="list-ticket-avialble ">';
				$available_variations = $product->get_available_variations();
				foreach ($available_variations as $key => $value)
				{
					$variation_id = $value['variation_id'];
					//$attribute_pa_colour = $value['attributes']['attribute_pa_seat'];
                    // SAFE attribute access
                    $attribute_pa_seat = $value['attributes']['attribute_pa_seat'] ?? '';
					$variation_obj = new WC_Product_variation($variation_id);
					$stock = $variation_obj->get_stock_quantity();
					$totalMaxseat = get_post_meta( $variation_id, '_variable_text_field', true );
					echo "<span>Available Seats:</span> " . $stock . "/".$totalMaxseat."<br>";
					

				}
				echo '</p>';
			}
		}
	}


}

#REFUND,Cancel,failed
add_action('woocommerce_order_status_failed', 'twwt_handle_order_failed', 100, 2);
function twwt_handle_order_failed($order_id, $order_obj = null) {
    // avoid double processing
    if ( get_post_meta( $order_id, '_twwt_failed_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_failed_handled', 1 );

    $order = is_object($order_obj) ? $order_obj : wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_items() as $item ) {
        $variation_id = $item->get_variation_id();
        if ( ! $variation_id ) { continue; }

        $seats = $item->get_meta('Seat(s)', true);
        if ( empty( $seats ) ) { continue; }

        $seats = array_map('trim', explode(',', $seats));
        // remove both perma and temp keys
        custom_delete_seats($variation_id, $seats);
        // authoritative stock recalculation
        twwt_recalculate_variation_stock( $variation_id );
    }

    update_post_meta($order_id, 'epp_is_seats_removed', 1);
}


// Delete seats meta helpers
function custom_delete_seats($item_id, $seats) {
    global $wpdb;
    foreach ($seats as $seat) {
        $seat = trim($seat);
        $meta_key_perma = 'perma_booked_seat_' . $seat;
        $meta_key_temp  = 'temp_booked_seat_' . $seat;

        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $item_id, $meta_key_perma
        ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $item_id, $meta_key_temp
        ) );
    }
}
// Refunded → remove seats + recalc stock (fixed priority & duplication)
add_action('woocommerce_order_refunded', 'twwt_handle_order_refunded', 100, 2);
function twwt_handle_order_refunded($order_id, $refund_id) {
    // Respect WooCommerce’s “restock refunded items” checkbox
    $restock = isset($_POST['restock_refunded_items'])
        ? filter_var($_POST['restock_refunded_items'], FILTER_VALIDATE_BOOLEAN)
        : true;

    if (!$restock) return;

    // Avoid duplicate handling
    if ( get_post_meta( $order_id, '_twwt_refund_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_refund_handled', 1 );

    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $variation_id = $item->get_variation_id();
        if (!$variation_id) continue;

        $seats = $item->get_meta('Seat(s)', true);
        if (!$seats) continue;

        $seats = array_map('trim', explode(',', $seats));

        // Delete both perma & temp seats for safety
        custom_delete_seats($variation_id, $seats);

        // Recalculate available seats (authoritative)
        twwt_recalculate_variation_stock($variation_id);
    }

    update_post_meta($order_id, 'epp_is_refunded', 1);
}


// Cancelled → remove seats + recalc stock (run after core handlers)
add_action('woocommerce_order_status_cancelled', 'custom_handle_order_cancellation', 100, 2);
function custom_handle_order_cancellation($order_id, $order) {
    // prevent double handling
    if ( get_post_meta( $order_id, '_twwt_cancel_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_cancel_handled', 1 );

    if (!custom_has_refunds($order)) {
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            if ( ! $variation_id ) { continue; }
            $seats = $item->get_meta('Seat(s)', true);
            if (!empty($seats)) {
                $seats = array_map('trim', explode(',', $seats));
                custom_delete_seats($variation_id, $seats);
                twwt_recalculate_variation_stock( $variation_id );
            }
        }
        update_post_meta($order_id, 'epp_is_refunded', 1);
    }
}

function custom_has_refunds($order) {
    $refunds = $order->get_refunds();
    return count($refunds) > 0;
}

function twwt_delete_seat($item_id, $seats) {
    global $wpdb;
    foreach ($seats as $seat) {
        $seat = trim($seat);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s",
            $item_id, 'perma_booked_seat_' . $seat
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s",
            $item_id, 'temp_booked_seat_' . $seat
        ));
    }
}

/* When order becomes paid/permanent → write perma seat + recalc stock */
function twwt_update_seat($order_id) {
    $order = wc_get_order($order_id);
    $items = $order->get_items();

    foreach ($items as $item) {
        $variation_id = $item->get_variation_id();
        if ( ! $variation_id ) { continue; }

        $seats = $item->get_meta('Seat(s)', true);
        $booked_seats = array_filter(array_map('trim', explode(',', (string) $seats)));

        foreach ($booked_seats as $seat) {
            update_post_meta($variation_id, 'perma_booked_seat_' . $seat, current_time('timestamp'));
        }

        // ensure stock reflects those seats
        //twwt_recalculate_variation_stock( $variation_id );
    }
}
add_action('woocommerce_order_status_processing', 'twwt_update_seat');
add_action('woocommerce_order_status_completed', 'twwt_update_seat');
add_action('woocommerce_order_status_on-hold', 'twwt_update_seat');

add_action( 'woocommerce_before_checkout_billing_form', 'twwt_add_custom_checkout_field' );
function twwt_add_custom_checkout_field( $checkout ) { 
   $current_user = wp_get_current_user();
   $saved_screen_name = $current_user->display_name;
   woocommerce_form_field( 'screen_name', array(        
      'type' => 'text',        
      'class' => array( 'form-row-wide' ),        
      'label' => 'Screen Name',        
      'placeholder' => 'Screen Name',        
      'required' => true,        
      'default' => $saved_screen_name,        
   ), $checkout->get_value( 'screen_name' ) ); 
}
add_action( 'woocommerce_checkout_process', 'twwt_validate_new_checkout_field' );
  
function twwt_validate_new_checkout_field() {    
   if ( ! $_POST['screen_name'] ) {
      wc_add_notice( 'Please enter your Screen Name', 'error' );
   }
}

##
add_action( 'woocommerce_checkout_update_order_meta', 'twwt_save_new_checkout_field' );
  
function twwt_save_new_checkout_field( $order_id ) { 
    if ( isset($_POST['screen_name']) ) {
        update_post_meta( $order_id, '_screen_name', esc_attr( $_POST['screen_name'] ) );
    }
}

add_action( 'woocommerce_checkout_update_user_meta', 'checkout_update_user_display_name', 10, 2 );
function checkout_update_user_display_name( $customer_id, $data ) {
    if ( isset($_POST['screen_name']) ) {
        $user_id = wp_update_user( array( 'ID' => $customer_id, 'display_name' => sanitize_text_field($_POST['screen_name']) ) );
    }
}
  
add_action( 'woocommerce_admin_order_data_after_billing_address', 'twwt_show_new_checkout_field_order', 10, 1 );
   
function twwt_show_new_checkout_field_order( $order ) {    
   $order_id = $order->get_id();
   if ( get_post_meta( $order_id, '_screen_name', true ) ) {
       echo '<p><strong>Screen Name:</strong> ' . esc_html( get_post_meta( $order_id, '_screen_name', true ) ) . '</p>';
   }
}
 
add_action( 'woocommerce_email_after_order_table', 'twwt_show_new_checkout_field_emails', 20, 4 );
  
function twwt_show_new_checkout_field_emails( $order, $sent_to_admin, $plain_text, $email ) {
    if ( get_post_meta( $order->get_id(), '_screen_name', true ) ) echo '<p><strong>Screen Name:</strong> ' . get_post_meta( $order->get_id(), '_screen_name', true ) . '</p>';
}

add_filter( 'woocommerce_add_to_cart_redirect', 'twwt_add_to_cart_redirect', 10, 1 );
function twwt_add_to_cart_redirect( $url ) {

    // Safety check for PHP 8+
    if ( ! isset( $_REQUEST['add-to-cart'] ) ) {
        return $url;
    }

    $product_id = apply_filters(
        'woocommerce_add_to_cart_product_id',
        absint( $_REQUEST['add-to-cart'] )
    );

    if ( $product_id && twwt_woo_product_seat( $product_id ) ) {
        $url = wc_get_cart_url(); // WooCommerce-safe
    }

    return $url;
}

##

##SELECT WINNER##
add_action('init', 'twwt_select_winner');
function twwt_select_winner(){
	// 1) When admin clicks "Select Winner"
    if ( isset($_GET['myaction']) && $_GET['myaction'] === "selectwinner" ) {

        $product_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
        $order_id   = isset($_GET['oid']) ? intval($_GET['oid']) : 0;
        $seat       = isset($_GET['seat']) ? sanitize_text_field($_GET['seat']) : '';

        if ( ! $product_id || ! $order_id ) {
            exit; // safety: missing data
        }

        // Current time in site timezone
        $current_timestamp  = current_time( 'timestamp', 0 );
        $winner_selected_at = date( 'Y-m-d H:i:s', $current_timestamp );

        /**
         * Read product go-live / starting time.
         * This should already be stored by your "Start Webinar" screen
         * in meta key: tww_event_s_date
         */
        $go_live_raw = get_post_meta( $product_id, 'tww_event_s_date', true );
        $go_live_ts  = $go_live_raw ? strtotime( $go_live_raw ) : 0;

        // Default: no delay (send as soon as cron runs)
        $delay_seconds = 0;

        if ( $go_live_ts ) {
            $diff = $go_live_ts - $current_timestamp; // seconds until go-live

            // If go-live is more than 1 hour in the future → delay by 2 hours
            if ( $diff > 3600 ) { // 3600s = 1 hour
                $delay_seconds = 2 * 3600; // 2 hours
            }
        }
        // If go-live is within 1 hour, missing, or invalid → delay stays 0

        // Final scheduled time for BOTH winner + "didn't win" notifications
        $winner_notification_ts = $current_timestamp + $delay_seconds;
        $winner_notification_at = date( 'Y-m-d H:i:s', $winner_notification_ts );

        // Save winner meta
        update_post_meta( $product_id, 'tww_winner_id',          $order_id );
        update_post_meta( $product_id, 'tww_winner_seat_id',     $seat );
        update_post_meta( $product_id, 'tww_winner_s_date',      $winner_selected_at );
        update_post_meta( $product_id, 'tww_winner_n_date',      $winner_notification_at );
        update_post_meta( $product_id, 'tww_winner_n_send',      0 );
        update_post_meta( $product_id, 'tww_winner_form_id',     md5( mt_rand() ) );
        update_post_meta( $product_id, 'tww_winner_form_submit', 0 );

        // IMPORTANT:
        // We do NOT send any notifications here anymore.
        // Both the winner and "didn't win" notifications will be sent
        // by the cron function twwt_send_msg_winner() when the
        // tww_winner_n_date time is reached.

        exit;
    }
	else if(@$_GET['myaction']=="createevent"){
		$event_name = $_GET['evn'];
		$event_time = $_GET['evd'].':00';
		$timestampm = strtotime($event_time);	
		$formattedDatem = date("F j, Y, g:i A", $timestampm);
		$product_id = $_GET['pid'];
		$event_created = get_post_meta( $product_id, 'event_created', true );
		
		if($event_created!=1){
			
			$event = twwt_create_event($event_name, $event_time);
			
			if($event['status']==1){
				$response = json_decode($event['response']);
				update_post_meta($product_id, 'event_data', $response);
				if($response->errors){
					update_post_meta($product_id, 'event_created', 0);
					
				}
				else{
					update_post_meta($product_id, 'event_created', 1);
					update_post_meta($product_id, '_event_name', $event_name);
					update_post_meta($product_id, '_event_ls_time', $event_time);
					#For Notification
					$current_timestamp = current_time( 'timestamp', 0 );
					$event_start_at = $event_time;
					$sub_10min = @strtotime($event_time) - (60*10);
					$event_notification_at = date( 'Y-m-d H:i:s', $sub_10min );
					$event_time_m = $formattedDatem.' ('.get_option('timezone_string').')';

					update_post_meta($product_id, 'tww_event_s_date', $event_start_at);
					update_post_meta($product_id, 'tww_event_n_date', $event_notification_at);
					update_post_meta($product_id, 'tww_event_n_send', 0);
				}
				echo $event['response'];
			}
		}
		exit;
	}
	else if ( isset($_GET['myaction']) && $_GET['myaction'] === "zoomnotification" ) {

        $event_url      = isset($_GET['evn']) ? esc_url_raw($_GET['evn']) : '';
        $event_password = isset($_GET['evp']) ? sanitize_text_field($_GET['evp']) : '';
        $event_time_raw = isset($_GET['evd']) ? sanitize_text_field($_GET['evd']) : ''; // e.g. 2025-11-26 19:50
        $product_id     = isset($_GET['pid']) ? intval($_GET['pid']) : 0;

        if ( ! $product_id || ! $event_url || ! $event_time_raw ) {
            exit;
        }

        // Add seconds for a full datetime string
        $event_time_string = $event_time_raw . ':00';

        // Use WordPress timezone, not server timezone
        if ( function_exists( 'wp_timezone' ) ) {
            $tz = wp_timezone();
        } else {
            // Fallback for very old WP versions
            $tz_string = get_option( 'timezone_string' );
            $tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
        }

        // Create a DateTime object in the WP timezone
        try {
            $dt = new DateTime( $event_time_string, $tz );
        } catch ( Exception $e ) {
            // If parsing fails, bail out safely
            exit;
        }

        $event_ts = $dt->getTimestamp();

        // Nicely formatted string for emails/SMS, in WP timezone
        $formattedDatem = wp_date( 'F j, Y, g:i A', $event_ts, $tz );
        $event_created  = get_post_meta( $product_id, 'event_created', true );

        if ( $event_created != 1 ) {

            // This is the string sent to customers in the Zoom notification
            $event_time_m = $formattedDatem . ' (' . get_option( 'timezone_string' ) . ')';

            // Send Zoom notification (unchanged)
            $event = twwt_send_zoom_notification( $product_id, $event_url, $event_time_m, $event_password );

            // Mark event as created and store Zoom data
            update_post_meta( $product_id, 'event_created',   1 );
            update_post_meta( $product_id, '_event_name',     $event_url );
            update_post_meta( $product_id, '_event_password', $event_password );

            // For winner/attendee reminder scheduling:
            // store start time and "10 minutes before" time in WP timezone
            $event_start_at_ts  = $event_ts;                // exact start
            $event_notify_ts    = $event_ts - 10 * 60;      // 10 minutes before

            $event_start_at     = wp_date( 'Y-m-d H:i:s', $event_start_at_ts, $tz );
            $event_notification = wp_date( 'Y-m-d H:i:s', $event_notify_ts,   $tz );

            update_post_meta( $product_id, 'tww_event_s_date', $event_start_at );
            update_post_meta( $product_id, 'tww_event_n_date', $event_notification );
            update_post_meta( $product_id, 'tww_event_n_send', 0 );
        }

        exit;
    }

	else if(@$_GET['myaction']=="sendcustomer"){
		$product_id = $_GET['pid'];
		twwt_send_customer($product_id);
		exit;
	}
	else if(@$_GET['myaction']=="remove-webinar"){
		$rdata = array();
		if ( is_user_logged_in() ) {
			$product_id = $_GET['pid'];
			add_user_meta( get_current_user_id(), 'remove_webinar_video', $product_id);
			$rdata['status'] = 'success';
		}
		else{
			$rdata['status'] = 'error';
		}
		echo json_encode($rdata);
		exit;
	}
	else if(@$_GET['myaction']=="export_csv"){
		twwt_get_csv($_GET['pid']);
		exit;
	}
}

/*WP Cron*/
//Schedule an action if it's not already scheduled
add_filter( 'cron_schedules', function ( $schedules ) {
   $schedules['twwt_per_one_minute'] = array(
       'interval' => 600,
       'display' => __( 'Every 1 mins' )
   );
   return $schedules;
} );

add_filter('cron_schedules', function ($schedules) {
    $schedules['twwt_per_twenty_minute'] = [
        'interval' => 1200, // 20 minutes
        'display' => __('Every 20 Minutes')
    ];
    return $schedules;
});

if ( ! wp_next_scheduled( 'twwt_cron_hook' ) ) {
    wp_schedule_event( time(), 'twwt_per_one_minute', 'twwt_cron_hook' );
	//Every five minutes
}
///Hook into that action that'll fire every day
add_action( 'twwt_cron_hook', 'twwt_send_msg_winner' );

if (!wp_next_scheduled('twwt_cron_hook1')) {
    wp_schedule_event(time(), 'twwt_per_twenty_minute', 'twwt_cron_hook1');
}

add_action('twwt_cron_hook1', 'twwt_send_np_notification');

add_action( 'init', 'tww_mycall' );
function tww_mycall(){
	if(@$_GET['m']==1){
		twwt_send_msg_winner();
		twwt_send_np_notification();
	}
	
}
add_action( 'wp', 'tww_joinevent' );
function tww_joinevent(){
	if(@$_GET['joinevent']){
		if(is_product()){
			$product_id = get_the_ID();
			$event_created = get_post_meta($product_id, 'event_created', true);
			if($event_created==1){
				$event_data = get_post_meta($product_id, 'event_data', true);
				$event_url = "https://app.livestorm.co/p/".$event_data->data->id;
				echo '<h1 style="text-align:center">Please wait...</h1>';
				echo '<script>window.location.href="'.$event_url.'";</script>';
				exit;
			}
			
		}
	}
}

//create your function, that runs on cron
function twwt_send_msg_winner() {
    global $wpdb;

    $current_timestamp = current_time( 'timestamp', 0 );

    // Find products where winner notification has not yet been sent
    $results = $wpdb->get_results(
        "SELECT post_id, meta_key 
         FROM $wpdb->postmeta 
         WHERE meta_key ='tww_winner_n_send' 
           AND meta_value = '0'"
    );

    if ( empty( $results ) ) {
        return;
    }

    foreach ( $results as $result ) {
        $product_id   = $result->post_id;
        $product_name = get_the_title( $product_id );

        $winner_id              = get_post_meta( $product_id, 'tww_winner_id', true );
        $winner_form_id         = get_post_meta( $product_id, 'tww_winner_form_id', true );
        $winner_notification_at = get_post_meta( $product_id, 'tww_winner_n_date', true );
        $winner_notification_ts = $winner_notification_at ? strtotime( $winner_notification_at ) : 0;

        if ( ! $winner_notification_ts ) {
            // If somehow missing/invalid, send immediately and mark done
            $winner_notification_ts = $current_timestamp;
        }

        $time_diff = $winner_notification_ts - $current_timestamp;

        // Not yet time → skip this product for now
        if ( $time_diff > 0 ) {
            continue;
        }

        // === Time reached → send winner notification ===
        $order_id = $winner_id;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            // No valid order? Mark as sent to avoid infinite loop
            update_post_meta( $product_id, 'tww_winner_n_send', 1 );
            continue;
        }

        // Get winner's seats (for completeness, though not used in message)
        $seats = "";
        foreach ( $order->get_items() as $item_id => $item ) {
            foreach ( $item->get_meta_data() as $itemvariation ) {
                if ( ! is_array( ( $itemvariation->value ) ) ) {
                    $key = wc_attribute_label( $itemvariation->key );
                    if ( $key == "Seat(s)" ) {
                        $seats = '' . wc_attribute_label( $itemvariation->value ) . '';
                    }
                }
            }
        }

        // Get screen name
        $screen_name = get_post_meta( $order_id, '_screen_name', true );
        if ( $screen_name == "" ) {
            $cuser = $order->get_user();
            if ( $cuser && ! empty( $cuser->display_name ) ) {
                $screen_name = $cuser->display_name;
            } else {
                $screen_name = $order->get_billing_first_name();
            }
        }

        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $email      = $order->get_billing_email();
        $phone      = $order->get_billing_phone();
        $user_id    = $order->get_user_id();

        $form_link  = get_permalink(91).'?formid='.$winner_form_id.'&pid='.$product_id.'&uid='.$user_id;

        // 1) Email + SMS to the winner
        twwt_email_send( $email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link );

        $settings   = get_option( 'twwt_woo_settings' );
        $smscontent = $settings['sms_wwinner_notification'];
        $smscontent = str_replace('%first_name%',   $first_name,   $smscontent);
        $smscontent = str_replace('%product_name%', $product_name, $smscontent);
        $smscontent = str_replace('%screen_name%',  $screen_name,  $smscontent);
        $twilio_msg = $smscontent;
        wp_twilio_sms($phone, $twilio_msg);

        // 2) "Didn't win" notifications (to all other attendees) at the SAME time
        if ( ! empty( $settings['winner_noto_others'] ) && $settings['winner_noto_others'] == 1 ) {
            // This function already skips the winner user.
            twwt_send_msg_winnerselected($product_id);
        }

        // 3) Mark notifications as sent for this product
        update_post_meta($product_id, 'tww_winner_n_send', 1);
    }
}


#Send Event Notification before start

function twwt_send_msg_event() {
	global $wpdb;
	$current_timestamp = current_time( 'timestamp', 0 );
	$results = $wpdb->get_results( "select post_id, meta_key from $wpdb->postmeta where meta_key ='tww_event_n_send' AND meta_value = '0'" );
	if(!empty($results)){
		foreach($results as $result){
			$product_id = $result->post_id;
			
			$event_notification_at = get_post_meta( $product_id, 'tww_event_n_date', true );
			$event_notification_at = strtotime($winner_notification_at);
						
			$time_diff = $event_notification_at - $current_timestamp;
			
			if($time_diff<=0){
				###
				#GET ALL CUSTOMER
				
	
				$statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );
				//print_r($statuses);
				$order_ids = $wpdb->get_col("
				   SELECT p.ID, pm.meta_value FROM {$wpdb->posts} AS p
				   INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
				   INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
				   INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
				   WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
				   AND pm.meta_key IN ( '_billing_email' )
				   AND im.meta_key IN ( '_product_id', '_variation_id' )
				   AND im.meta_value = $product_id
				");
				 
				// Print array on screen
				if(!empty($order_ids)){
					$product_url = get_permalink($product_id).'?joinevent=true';
					$event_name = get_post_meta( $product_id, '_event_name', true );
					$event_time = get_post_meta($product_id, '_event_ls_time', $event_time);
					$timestampm = strtotime($event_time);
					$formattedDatem = date("F j, Y, g:i A", $timestampm);
					$event_time_m = $formattedDatem.' ('.get_option('timezone_string').')';
					foreach($order_ids as $order_id){
						if(!twwy_has_refunds_v2($order_id)){
							$order = wc_get_order( $order_id );
							$first_name = $order->get_billing_first_name();
							$last_name = $order->get_billing_last_name();
							$email = $order->get_billing_email();
							$phone = $order->get_billing_phone();
							twwt_lsemail_send($email, $product_name, $first_name, $last_name, $event_name, $phone, $event_time_m, $product_url);
							
							$settings = get_option( 'twwt_woo_settings' );
							$smscontent = $settings['sms_livestrom_notification'];
							// Replace placeholders with actual values
							$smscontent = str_replace('%first_name%', $first_name, $smscontent);
							$smscontent = str_replace('%event_name%', $event_name, $smscontent);
							$smscontent = str_replace('%product_url%', $product_url, $smscontent);
							$twilio_msg = $smscontent;
							wp_twilio_sms($phone, $twilio_msg);
						}
					}
				}
				update_post_meta($product_id, 'tww_event_n_send', 1);
			}
			###
		}
	}
	
}


#SEND WINNER SELECTED MESSAGE TO ALL
function twwt_send_msg_winnerselected( $product_id ) {
    global $wpdb;

    /** ===============================
     *  GET WINNER USER
     *  =============================== */
    $winner_order_id = get_post_meta( $product_id, 'tww_winner_id', true );
    if ( ! $winner_order_id ) {
        return;
    }

    $winner_order = wc_get_order( $winner_order_id );
    if ( ! $winner_order ) {
        return;
    }

    $winner_user = $winner_order->get_user();

    // Resolve screen name
    $screen_name = get_post_meta( $winner_order_id, '_screen_name', true );
    if ( empty( $screen_name ) ) {
        if ( $winner_user && ! empty( $winner_user->display_name ) ) {
            $screen_name = $winner_user->display_name;
        } else {
            $screen_name = $winner_order->get_billing_first_name();
        }
    }

    /** ===============================
     *  GET ALL RELATED ORDERS
     *  =============================== */
    $statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );

    $order_ids = $wpdb->get_col("
        SELECT DISTINCT p.ID
        FROM {$wpdb->posts} AS p
        INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
        INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
        WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
        AND im.meta_key IN ( '_product_id', '_variation_id' )
        AND im.meta_value = {$product_id}
    ");

    if ( empty( $order_ids ) ) {
        return;
    }

    $product_name    = get_the_title( $product_id );
    $settings        = get_option( 'twwt_woo_settings' );
    $sms_template    = isset( $settings['sms_winner_noti_others'] ) ? $settings['sms_winner_noti_others'] : '';

    /** ===============================
     *  DEDUPLICATION TRACKERS
     *  =============================== */
    $notified_users  = array();
    $notified_emails = array();

    /** ===============================
     *  SEND NOTIFICATIONS
     *  =============================== */
    foreach ( $order_ids as $order_id ) {

        if ( twwy_has_refunds_v2( $order_id ) ) {
            continue;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            continue;
        }

        $user  = $order->get_user();
        $email = $order->get_billing_email();
        $phone = $order->get_billing_phone();

        // Skip winner user
        if ( $user && $winner_user && $user->ID === $winner_user->ID ) {
            continue;
        }

        // Prevent duplicate notifications (user-based)
        if ( $user && isset( $notified_users[ $user->ID ] ) ) {
            continue;
        }

        // Prevent duplicate notifications (guest/email-based)
        if ( ! $user && $email && isset( $notified_emails[ $email ] ) ) {
            continue;
        }

        // Mark as notified
        if ( $user ) {
            $notified_users[ $user->ID ] = true;
        } elseif ( $email ) {
            $notified_emails[ $email ] = true;
        }

        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();

        // Send email
        twwt_email_winner_send(
            $email,
            $product_name,
            $first_name,
            $last_name,
            $screen_name,
            $phone
        );

        // Send SMS
        if ( $phone && $sms_template ) {
            $sms_content = str_replace(
                array( '%first_name%', '%product_name%', '%screen_name%' ),
                array( $first_name, $product_name, $screen_name ),
                $sms_template
            );

            wp_twilio_sms( $phone, $sms_content );
        }
    }
}


function twwt_email_send($email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link=""){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = $settings['wwinner_notification_sub'];
	$emailcontent = $settings['wwinner_notification'];
	
	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
	$emailsubject = str_replace('%screen_name%', $screen_name, $emailsubject);
    $emailsubject = str_replace('%phone%', $phone, $emailsubject);
	$emailcontent = str_replace('%first_name%', $first_name, $emailcontent);
    $emailcontent = str_replace('%product_name%', $product_name, $emailcontent);
    $emailcontent = str_replace('%last_name%', $last_name, $emailcontent);
	$emailcontent = str_replace('%screen_name%', $screen_name, $emailcontent);
    $emailcontent = str_replace('%phone%', $phone, $emailcontent);
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	wc_get_template( 'emails/email-footer.php' );
	$message = ob_get_clean();
	$subject = $email_heading;
	$admin_foot_notes = "<p><small>Originally sent to {$first_name}</small></p>";
	//$mailer->send('info@marksmanreview.com', $subject, $message.$admin_foot_notes); // To Admin
	return $mailer->send( $email, $subject, $message);
	//return true;
}

//livestrom event email
function twwt_lsemail_send($email, $product_name, $first_name, $last_name, $event_name, $phone, $event_time_m, $product_url){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = $settings['elivestrom_notification_sub'];
	$emailcontent = $settings['elivestrom_notification'];
	
	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
	$emailsubject = str_replace('%event_name%', $event_name, $emailsubject);
    $emailsubject = str_replace('%event_time%', $event_time_m, $emailsubject);
	$emailcontent = str_replace('%first_name%', $first_name, $emailcontent);
    $emailcontent = str_replace('%product_name%', $product_name, $emailcontent);
    $emailcontent = str_replace('%last_name%', $last_name, $emailcontent);
	$emailcontent = str_replace('%event_name%', $event_name, $emailcontent);
    $emailcontent = str_replace('%event_time%', $event_time_m, $emailcontent);
	$emailcontent = str_replace('%product_url%', $product_url, $emailcontent);
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	wc_get_template( 'emails/email-footer.php' );
	$message = ob_get_clean();
	$subject = $email_heading;
	$admin_foot_notes = "<p><small>Originally sent to {$first_name}</small></p>";
	//$mailer->send('info@marksmanreview.com', $subject, $message.$admin_foot_notes); // To Admin
	return $mailer->send( $email, $subject, $message);
	//return true;
}

function twwt_email_winner_send($email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link=""){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = $settings['winner_noti_others_sub'];
	$emailcontent = $settings['winner_noti_others'];
	
	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
	$emailsubject = str_replace('%screen_name%', $screen_name, $emailsubject);
    $emailsubject = str_replace('%phone%', $phone, $emailsubject);
	$emailcontent = str_replace('%first_name%', $first_name, $emailcontent);
    $emailcontent = str_replace('%product_name%', $product_name, $emailcontent);
    $emailcontent = str_replace('%last_name%', $last_name, $emailcontent);
	$emailcontent = str_replace('%screen_name%', $screen_name, $emailcontent);
    $emailcontent = str_replace('%phone%', $phone, $emailcontent);
	
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	wc_get_template( 'emails/email-footer.php' );
	$message = ob_get_clean();
	$subject = $email_heading;
	$admin_foot_notes = "<p><small>Originally sent to {$first_name}</small></p>";
	//$mailer->send('info@marksmanreview.com', $subject, $message.$admin_foot_notes); // To Admin
	return $mailer->send( $email, $subject, $message);
	//return true;
}

function twwy_has_refunds( $order ) {
    return sizeof( $order->get_refunds() ) > 0 ? true : false;
}
function twwy_has_refunds_v2($order_id){
	if(get_post_meta( $order_id, 'epp_is_refunded', true)==1){
		return true;
	}
	else{
		return false;
	}
}
/*function twwy_extra_register_fields(){
	?>
<p class="form-row form-row-wide">
<label><input type="checkbox" name="twwy_opt_notification" value="1" checked="checked"><span>Sign up to receive notifications when a new webinar is available.</span></label>
</p>
	<?php
}
add_action( 'woocommerce_register_form', 'twwy_extra_register_fields' );*/
//
function twwy_modify_user_table( $column ) {
    $column['optin'] = 'OPT-in';
    return $column;
}
add_filter( 'manage_users_columns', 'twwy_modify_user_table' );

function twwy_modify_user_table_row( $val, $column_name, $user_id ) {
    switch ($column_name) {
        case 'optin' :
            return get_the_author_meta( 'twwy_opt_notification', $user_id )==1?"Yes":"No";
        default:
    }
    return $val;
}
add_filter( 'manage_users_custom_column', 'twwy_modify_user_table_row', 10, 3 );  

/**
 * -------------------------------------------------
 * 2. ADD CUSTOM REGISTRATION FIELDS (ORDERED)
 * -------------------------------------------------
 */
add_action( 'woocommerce_register_form_start', 'review_raffle_custom_register_fields' );
function review_raffle_custom_register_fields() {
    ?>

    <!-- First Name -->
    <p class="form-row form-row-wide">
        <label for="reg_billing_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name"
            value="<?php echo ! empty( $_POST['billing_first_name'] ) ? esc_attr( $_POST['billing_first_name'] ) : ''; ?>" required/>
    </p>

    <!-- Last Name -->
    <p class="form-row form-row-wide">
        <label for="reg_billing_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name"
            value="<?php echo ! empty( $_POST['billing_last_name'] ) ? esc_attr( $_POST['billing_last_name'] ) : ''; ?>" required/>
    </p>

    <!-- Screen Name -->
    <p class="form-row form-row-wide">
        <label for="reg_screen_name"><?php esc_html_e( 'Screen name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="screen_name" id="reg_screen_name"
            value="<?php echo ! empty( $_POST['screen_name'] ) ? esc_attr( $_POST['screen_name'] ) : ''; ?>" required/>
    </p>

    <!-- Phone Number -->
    <p class="form-row form-row-wide">
        <label for="reg_phone_number"><?php esc_html_e( 'Phone number', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="phone_number" id="reg_phone_number"
            value="<?php echo ! empty( $_POST['phone_number'] ) ? esc_attr( $_POST['phone_number'] ) : ''; ?>" required/>
    </p>   

    <?php
}

add_action( 'woocommerce_register_form', 'review_raffle_custom_register_checknotufields' );
function review_raffle_custom_register_checknotufields() {
?>
<!-- Notification Opt-in -->
    <p class="form-row form-row-wide">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
            <input type="checkbox" name="twwy_opt_notification" value="1"
                <?php checked( ! empty( $_POST['twwy_opt_notification'] ) ); ?> />
            <span><?php esc_html_e( 'Sign up to receive notifications when a new webinar is available.', 'woocommerce' ); ?></span>
        </label>
    </p>
<?php
}

/**
 * -------------------------------------------------
 * 3. VALIDATION
 * -------------------------------------------------
 */
add_filter( 'woocommerce_registration_errors', 'review_raffle_validate_register_fields', 10, 3 );
function review_raffle_validate_register_fields( $errors, $username, $email ) {

    if ( empty( $_POST['billing_first_name'] ) ) {
        $errors->add( 'first_name_error', __( 'First name is required.', 'woocommerce' ) );
    }

    if ( empty( $_POST['billing_last_name'] ) ) {
        $errors->add( 'last_name_error', __( 'Last name is required.', 'woocommerce' ) );
    }

    if ( empty( $_POST['screen_name'] ) ) {
        $errors->add( 'screen_name_error', __( 'Screen name is required.', 'woocommerce' ) );
    }

    if ( empty( $_POST['phone_number'] ) ) {
        $errors->add( 'phone_error', __( 'Phone number is required.', 'woocommerce' ) );
    }

    return $errors;
}


/**
 * -------------------------------------------------
 * 4. SAVE USER META
 * -------------------------------------------------
 */
add_action( 'woocommerce_created_customer', 'review_raffle_save_register_fields' );
function review_raffle_save_register_fields( $customer_id ) {

    update_user_meta( $customer_id, 'billing_first_name', sanitize_text_field( $_POST['billing_first_name'] ) );
    update_user_meta( $customer_id, 'first_name', sanitize_text_field( $_POST['billing_first_name'] ) );

    update_user_meta( $customer_id, 'billing_last_name', sanitize_text_field( $_POST['billing_last_name'] ) );
    update_user_meta( $customer_id, 'last_name', sanitize_text_field( $_POST['billing_last_name'] ) );

    update_user_meta( $customer_id, 'screen_name', sanitize_text_field( $_POST['screen_name'] ) );
    update_user_meta( $customer_id, 'billing_phone', sanitize_text_field( $_POST['phone_number'] ) );

    if ( isset( $_POST['twwy_opt_notification'] ) ) {
        update_user_meta( $customer_id, 'twwy_opt_notification', 1 );
    }
}
/**
 * -------------------------------------------------
 * 5. MODIFY ACCOUNT DETAILS EMAIL
 * -------------------------------------------------
 */
// Add custom checkbox field to WooCommerce checkout for logged-out customers
/*add_action('woocommerce_after_order_notes', 'add_custom_checkbox_to_checkout');
function add_custom_checkbox_to_checkout($checkout) {
    if (is_user_logged_in()) {
        return; // Do not display for logged-in customers
    }
    
    echo '<div id="custom_checkbox_field" class="form-row form-row-wide">';
    
    woocommerce_form_field('twwy_opt_notification', array(
        'type'          => 'checkbox',
        'class'         => array('input-checkbox'),
        'label_class'   => array('woocommerce-form__label', 'woocommerce-form__label-for-checkbox', 'checkbox'),
        'input_class'   => array('woocommerce-form__input', 'woocommerce-form__input-checkbox'),
        'label'         => __('Sign up to receive notifications when a new webinar is available.', 'woocommerce'),
        'default'       => 1, // Checked by default
    ), $checkout->get_value('twwy_opt_notification'));
    
    echo '</div>';
}

// Save custom checkbox field value to order meta for logged-out customers
add_action('woocommerce_checkout_update_order_meta', 'save_custom_checkbox_field_value');
function save_custom_checkbox_field_value($order_id) {
    if (is_user_logged_in()) {
        return; // Do not save for logged-in customers
    }
    
    if ($_POST['twwy_opt_notification']) {
        update_post_meta($order_id, 'Notification Checkbox', __('1', 'woocommerce'));
    }
}*/

// Add phone number field to account edit form
add_action( 'woocommerce_edit_account_form', 'add_phone_number_to_edit_account_form');
function add_phone_number_to_edit_account_form() {
    $user_id = get_current_user_id();
	$opt_notification = get_user_meta( $user_id, 'twwy_opt_notification', true );
    ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_screen_name"><?php _e( 'Screen Name', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_screen_name" id="account_screen_name" value="<?php echo esc_attr( get_user_meta( $user_id, 'screen_name', true ) ); ?>" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_phone_number"><?php _e( 'Phone Number', 'woocommerce' ); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_phone_number" id="account_phone_number" value="<?php echo esc_attr( get_user_meta( $user_id, 'billing_phone', true ) ); ?>" />
        </p>
        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
            <label for="account_twwy_opt_notification">
                <input type="checkbox" name="account_twwy_opt_notification" id="account_twwy_opt_notification" value="1" <?php checked( $opt_notification, '1' ); ?>/>
                <?php _e( 'Sign up to receive notifications when a new webinar is available.', 'woocommerce' ); ?>
            </label>
        </p>
    <?php
}

// Save phone number field value to user meta
add_action( 'woocommerce_save_account_details', 'save_phone_number_field_value_on_account_page' );
function save_phone_number_field_value_on_account_page( $user_id ) {
    if ( isset( $_POST['account_phone_number'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_phone_number'] ) );
    }
	if ( isset( $_POST['account_screen_name'] ) ) {
        update_user_meta( $user_id, 'screen_name', sanitize_text_field( $_POST['account_screen_name'] ) );
    }
	// opt-in checkbox: if present store '1', otherwise store '0' (so user can opt-out)
    if ( isset( $_POST['account_twwy_opt_notification'] ) && $_POST['account_twwy_opt_notification'] === '1' ) {
        update_user_meta( $user_id, 'twwy_opt_notification', '1' );
    } else {
        // ensure we explicitly save 0 when unchecked
        update_user_meta( $user_id, 'twwy_opt_notification', '0' );
    }
}
// Add Screen name field to admin profile page
add_action( 'show_user_profile', 'add_job_title_field' );
add_action( 'edit_user_profile', 'add_job_title_field' );
function add_job_title_field( $user ) {
    ?>
    <h3><?php _e( 'Screen Name', 'woocommerce' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="screen_name"><?php _e( 'Screen Name', 'woocommerce' ); ?></label></th>
            <td>
                <input type="text" name="screen_name" id="screen_name" value="<?php echo esc_attr( get_the_author_meta( 'screen_name', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description"><?php _e( 'Please enter your Screen Name.', 'woocommerce' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
}

// Save field data
add_action( 'personal_options_update', 'save_job_title_field' );
add_action( 'edit_user_profile_update', 'save_job_title_field' );
function save_job_title_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    update_user_meta( $user_id, 'screen_name', sanitize_text_field( $_POST['screen_name'] ) );
}

/** 
 * === Admin: allow editing user OPT-IN (twwy_opt_notification) ===
 */

// Add the checkbox to the profile edit form
add_action('show_user_profile', 'twwy_add_optin_field_admin');
add_action('edit_user_profile', 'twwy_add_optin_field_admin');
function twwy_add_optin_field_admin( $user ) {
    // Only admins or editors can change it
    if ( ! current_user_can('edit_users') ) {
        return;
    }

    $optin = get_user_meta( $user->ID, 'twwy_opt_notification', true );
    ?>
    <h3><?php _e( 'Webinar Notifications', 'woocommerce' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="twwy_opt_notification"><?php _e( 'Receive Webinar Notifications', 'woocommerce' ); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="twwy_opt_notification" id="twwy_opt_notification" value="1" <?php checked( $optin, '1' ); ?> />
                    <?php _e( 'Sign up to receive notifications when a new webinar is available.', 'woocommerce' ); ?>
                </label>
                <br><span class="description">Admins can manually opt in/out users here.</span>
            </td>
        </tr>
    </table>
    <?php
}

// Save the field when profile is updated
add_action('personal_options_update', 'twwy_save_optin_field_admin');
add_action('edit_user_profile_update', 'twwy_save_optin_field_admin');
function twwy_save_optin_field_admin( $user_id ) {
    if ( ! current_user_can('edit_user', $user_id) ) {
        return false;
    }

    // If checked → 1, else 0
    if ( isset($_POST['twwy_opt_notification']) && $_POST['twwy_opt_notification'] == '1' ) {
        update_user_meta( $user_id, 'twwy_opt_notification', '1' );
    } else {
        update_user_meta( $user_id, 'twwy_opt_notification', '0' );
    }
}

//require file
require_once('twwt-admin-settings.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-product-notification.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-order-csv.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-livestrom.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-myaccount-videos.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-admin-add-webinar-simple.php');

// licence key conditional validation file
function twwt_plugin_control_functionality() {
$license_manager = new twwt_woo_settings_page();
   if (!$license_manager->twwt_license_key_valid()) {
        add_action('admin_notices', 'twwt_plugin_license_key_invalid_notice');
    }
}
add_action('admin_init', 'twwt_plugin_control_functionality');


function twwt_plugin_license_key_invalid_notice() {
    echo '<div class="notice notice-error"><p>Invalid License Key. Please enter a valid license key in the <a href="' . esc_url(admin_url('admin.php?page=twwt-plugin-license')) . '">license settings</a> to use this plugin.</p></div>';
}


function valid_files_licence(){
$license_manager_api_validation = new twwt_woo_settings_page();
if ($license_manager_api_validation->twwt_license_key_valid()) {


    require_once(plugin_dir_path(__FILE__) . 'twwt-admin-metabox.php');
    //require_once(plugin_dir_path(__FILE__) . 'twwt-myaccount-videos.php');
    
}

}
add_action('admin_init', 'valid_files_licence'); 


// Add new tab
$taboptions = get_option( 'twwt_woo_settings' );	
if($taboptions['producttab']==1){
add_filter( 'woocommerce_product_tabs', 'twwt_woo_new_product_tab' );
function twwt_woo_new_product_tab( $tabs ) { 
global $post;
if(get_post_meta( $post->ID, 'woo_seat_show', true )==1){	
  $tabs['new_tab'] = array(
    'title' 	=> __( 'Participants', 'woocommerce' ),
    'priority' 	=> 50,
    'callback' 	=> 'twwt_woo_new_product_tab_content'
  );

  	return $tabs;
} else{
	return $tabs;
}
}


function twwt_woo_new_product_tab_content() {
	global $post;
	if(get_post_meta( $post->ID, 'woo_seat_show', true )==1){
		echo '<section class="tab-participant" data-id="'.$post->ID.'"></section>';
	}
}


add_action('init', 'twwt_get_participant');
function twwt_get_participant(){
	if(isset($_GET['participant'])){
		require plugin_dir_path( __FILE__ ) . 'ticket-participant.php';
		exit;
	}
}
}
add_action('init', 'twwt_get_availability_check');
function twwt_get_availability_check(){
	if(isset($_GET['availabilitycheck'])){
		$variation_id = $_GET['variationid'];
		$seat = $_GET['seat'];
		$availability = twwt_woo_get_availability($variation_id, $seat);
		if($availability){
			 echo 'Selected seat "'.$seat.'" is not available for booking.';
		}
		else{
			echo 1;
		}
		exit;
	}
}

/*add_filter( 'woocommerce_product_tabs', 'tw_woo_customize_product_tabs', 100 );
function tw_woo_customize_product_tabs( $tabs ) {
    global $post;
	if(get_post_meta( $post->ID, 'woo_seat_show', true )==1){
		$options = get_option( 'twwt_woo_settings' );
		if($options['video_show']==0){
			if ( ! is_user_logged_in() ) { 
				unset( $tabs['description'] ); // remove tab
			}
			else{
				if( current_user_can('editor') || current_user_can('administrator') ) {
					//do noothing
				}
				else{
					if(!twwt_customer_purchased(get_current_user_id(), $post->ID)){
						unset( $tabs['description'] );
					}
				}
			}
		}
	}
	else{
		unset( $tabs['new_tab'] ); // remove tab
	}

    return $tabs;
}*/

function twwt_customer_purchased($customer_id, $product_id){
    global $wpdb;

    $statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );
    //print_r($statuses);
    $order_ids = $wpdb->get_col("
       SELECT p.ID, pm.meta_value FROM {$wpdb->posts} AS p
       INNER JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id
       INNER JOIN {$wpdb->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
       INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
       WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
       AND pm.meta_key IN ( '_billing_email' )
       AND im.meta_key IN ( '_product_id', '_variation_id' )
       AND im.meta_value = $product_id
    ");
    if(!empty($order_ids)){
    	foreach($order_ids as $order_id){
    		if(!twwy_has_refunds_v2($order_id)){
    			$order = wc_get_order( $order_id );
    			if($order->get_user_id()==$customer_id){
    			    return true;
    			    break;
    			}
    		}
    	}
    }
}

//shortcode
function winner_page_shortcode( $atts ) {
    ob_start();
	$woocommerce;
    $product_id = isset( $_GET['pid'] ) ? intval( $_GET['pid'] ) : 0;
    $product = wc_get_product( $product_id );
    $variations = $product ? $product->get_available_variations() : array();

    if ( ! empty( $product_id ) ) {
        $winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
        $winner_seat_id = get_post_meta( $product_id, 'tww_winner_seat_id', true );
        $statuses = array_map( 'esc_sql', wc_get_is_paid_statuses() );
        $order_ids = $GLOBALS['wpdb']->get_col( "
            SELECT p.ID, pm.meta_value FROM {$GLOBALS['wpdb']->posts} AS p
            INNER JOIN {$GLOBALS['wpdb']->postmeta} AS pm ON p.ID = pm.post_id
            INNER JOIN {$GLOBALS['wpdb']->prefix}woocommerce_order_items AS i ON p.ID = i.order_id
            INNER JOIN {$GLOBALS['wpdb']->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
            WHERE p.post_status IN ( 'wc-" . implode( "','wc-", $statuses ) . "' )
            AND pm.meta_key IN ( '_billing_email' )
            AND im.meta_key IN ( '_product_id', '_variation_id' )
            AND im.meta_value = $product_id
        " );
    ?>
        <div class="enterChance p-90">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="headingFnt winnerheading text-center">
                            <h3><?php echo get_the_title( $product_id ); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container pt-4 pb-5">
            <?php if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) { ?>
            <div class="row winnerwrap_row">
                <div class="col-lg-6 attendeewiner">
                    <h4 class="text-center mb-2">Attendee </h4>
                    <form method="post" id="tw_mywinnerform">
                        <table class="table table-bordered table-striped table-hover" id="mytable">
                            <thead>
                                <tr>
                                    <?php if ( empty( $variations ) ) { ?>
                                        <th width="56">&nbsp;</th>
                                    <?php } else { ?>
                                        <th class="sr-only">&nbsp;</th>
                                    <?php } ?>
                                    <th width="150">Seat Number</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cnn = 0;
                                foreach ( $order_ids as $order_id ) {
                                    $order = wc_get_order( $order_id );
                                    if ( ! twwy_has_refunds_v2( $order_id ) ) {
                                        $screen_name = get_post_meta( $order_id, '_screen_name', true );
										$billing_first_name = $order->get_billing_first_name();
										$billing_last_name = $order->get_billing_last_name();
										$full_name = $billing_first_name . ' ' . $billing_last_name;
                                        if ( $screen_name == "" ) {
                                            $cuser = $order->get_user();
                                            if ( ! empty( $cuser->display_name ) ) {
												}
						else{
							$screen_name = $order->get_billing_first_name();
						}
					}
					
					 $seats = "";
					 foreach ( $order->get_items() as $item_id => $item ) {
						 //$allmeta = $item->get_meta_data();
						 if( $item->get_product_id() == $product_id){
							 foreach ( $item->get_meta_data() as $itemvariation ) {
								if ( ! is_array( ( $itemvariation->value ) ) ) {
									$key = wc_attribute_label( $itemvariation->key );
									if($key == "Seat(s)"){
										$seats = '' . wc_attribute_label( $itemvariation->value ) . '';
									}
								}
							}
						 }
					 }
					 $seat_array = explode(', ', $seats);
					 
					 foreach($seat_array as $seat){
						 $cnn++;
						 ?>
                         <tr>
                         <?php if(empty($variations)){ ?>
                         <td class="text-center">
                         
                         <?php if($winner_id>0){ if($winner_seat_id==$seat){ echo '<i class="dashicons dashicons-awards"></i>';}}else{?><input type="radio" name="rbtnseats" value="<?php echo $seat;?>" data-id="<?php echo $order_id;?>" class="tw_rbtn" <?php if($cnn==1){echo 'required="required"';}?> /><?php } ?></td>
                         <?php }else{echo '<td class="sr-only"></td>';} ?>
                         <td class="pts"><?php echo $seat;?></td>
                         <td id="wsnm_<?php echo $seat;?>"><?php echo $full_name;?><?php //echo $screen_name;?></td>
                         </tr>
                         <?php
					 }
					}
				}
				?>
                </tbody>
                </table>
                <?php if(empty($variations)){?>
                <div class="text-center">
                <?php if($winner_id>0){ }else{ ?>
                <input type="hidden" name="orderid" id="tw_orderid" value="0" />
                <input type="hidden" name="pid" id="tw_pid" value="<?php echo $product_id;?>" />
                <button type="submit" class="btn btn-winner" id="btn_select_winnerf"><i class="dashicons dashicons-awards"></i> <span>Select a Winner</span></button>
                <?php } ?>
                </div>
                <?php }else{ ?>
                <div class="alert alert-danger text-center"><p>Seats are still available in this webinar.</p> <p><a href="<?php echo get_permalink($product_id);?>" class="btn btn-danger">Go back</a></p></div>
				<?php }?>
                </form>
                </div>
                <div class="col-lg-6 generatornum">
                <?php if(empty($variations)){?>
                <h4 class="text-left mb-2">Generate Number</h4>
                <iframe src="https://www.random.org/widgets/integers/iframe.php?title=True+Random+Number+Generator&amp;buttontxt=Generate&amp;width=100%&amp;height=250&amp;border=on&amp;bgcolor=%23FFFFFF&amp;txtcolor=%23777777&amp;altbgcolor=%23e42c2c&amp;alttxtcolor=%23FFFFFF&amp;defaultmin=1&amp;defaultmax=&amp;fixed=off" frameborder="0" width="100%" height="250" style="min-height:250px;" scrolling="no" longdesc="https://www.random.org/integers/">
The numbers generated by this widget come from RANDOM.ORGs true random number generator.
</iframe>
<?php } ?>
</div>
</div>
</div>
<?php
			}
		}
$output = ob_get_clean();
return $output;			
 } 
add_shortcode( 'my_shortcode', 'winner_page_shortcode' );

function my_custom_footer_message() {
$options = get_option( 'twwt_woo_settings' );
if($options['bootstrap_show']==1){ 
if ( is_page( 'winner' ) ) {
wp_enqueue_style( 'bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css' );
wp_enqueue_script( 'bootstrap', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', array( 'jquery' ), '', true );
}
 } 
if ( is_page( 'winner' ) ) {	
?>
<!-- Modal -->
<div class="modal" id="sbWinner" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="sbWinnerLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <h3>Congratulations to <em id="wsnm"></em></h3>
        <p>You have been selected for the door prize!<?php /*?><?php echo get_the_title(@$_GET['pid']);?><?php $_GET['seat'];*/?></p>
      </div>
    </div>
  </div>
</div>
<?php } ?>
<script>
function sortTable(){
  var rows = jQuery('#mytable tbody  tr').get();

  rows.sort(function(a, b) {

  var A = jQuery(a).children('td').eq(1).text().toUpperCase();
  var B = jQuery(b).children('td').eq(1).text().toUpperCase();
  A = parseInt(A);
  B = parseInt(B);
  if(A < B) {
    return -1;
  }

  if(A > B) {
    return 1;
  }

  return 0;

  });

  jQuery.each(rows, function(index, row) {
    jQuery('#mytable').children('tbody').append(row);
  });
}
jQuery(document).ready(function(e) {
    sortTable();
});
</script>
<?php
if (is_account_page()) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('label[for="account_display_name"]').parent('p').addClass('custom-displayname');
            });
        </script>
<style>
.custom-displayname	{
	display: none;
}
</style>
  <?php
}

}
add_action( 'wp_footer', 'my_custom_footer_message' );

// Hook to modify the order actions for failed orders
add_filter( 'woocommerce_my_account_my_orders_actions', 'custom_my_account_failed_order_actions', 10, 2 );

function custom_my_account_failed_order_actions( $actions, $order ) {
    // Check if the order status is 'failed'
    if ( 'failed' === $order->get_status() ) {
        // Remove all actions except the "View" button
        foreach ( $actions as $key => $action ) {
            if ( 'view' !== $key ) {
                unset( $actions[$key] );
            }
        }
    }

    return $actions;
}

// === Admin: show provider-aware SMS info on user profile ===

/**
 * Helper: current active SMS provider
 */
function twwt_get_active_sms_provider() {
    $opts = get_option('twwt_woo_settings', []);
    return isset($opts['sms_provider']) ? $opts['sms_provider'] : 'twilio';
}

/**
 * Helper: map OtterText optincheck to label
 * 1 = NewCustomer, 2 = OptinRequested, 3 = OptedIn, 4 = OptedOut, 5 = InvalidNumber
 */
function twwt_ottertext_optin_label($code) {
    switch ((string) $code) {
        case '1': return 'New Customer';
        case '2': return 'Opt-in Requested';
        case '3': return '✅ Opted In';
        case '4': return '❌ Opted Out';
        case '5': return '⚠ Invalid Number';
        default:  return '—';
    }
}

/**
 * Render section on user profile (admin)
 */
add_action('show_user_profile', 'twwt_show_sms_provider_user_panel');
add_action('edit_user_profile', 'twwt_show_sms_provider_user_panel');
function twwt_show_sms_provider_user_panel($user) {
    // Only admins/editors should see the provider panel
    if ( ! current_user_can('list_users') ) {
        return;
    }

    $provider = twwt_get_active_sms_provider();
    $opts     = get_option('twwt_woo_settings', []);
    ?>
    <h3>SMS Provider</h3>
    <table class="form-table">
        <tr>
            <th><label>Active Provider</label></th>
            <td><strong><?php echo esc_html( ucfirst($provider) ); ?></strong></td>
        </tr>

        <?php if ($provider === 'ottertext') : 
            $partner   = isset($opts['ottertext_partner']) ? $opts['ottertext_partner'] : '';

            // Raw phone from Woo profile
            $raw_phone = get_user_meta($user->ID, 'billing_phone', true);
            // Normalized phone saved during sync/send (if available)
            $ot_phone  = get_user_meta($user->ID, 'ottertext_phone', true);
            // Prefer the OtterText-normalized phone; fall back to raw
            $display_phone = $ot_phone ? $ot_phone : $raw_phone;

            $optin     = get_user_meta($user->ID, 'ottertext_optincheck', true);
            $lastcheck = get_user_meta($user->ID, 'ottertext_optin_last_checked', true);
        ?>
            <tr>
                <th><label>User Phone (profile)</label></th>
                <td><?php echo $display_phone ? esc_html($display_phone) : '<em>—</em>'; ?></td>
            </tr>
            <tr>
                <th><label>OtterText Partner</label></th>
                <td><?php echo $partner ? esc_html($partner) : '<em>not set</em>'; ?></td>
            </tr>
            <tr>
                <th><label>OtterText Opt-in Status</label></th>
                <td>
                    <?php echo esc_html( twwt_ottertext_optin_label($optin) ); ?>
                    <?php if ($lastcheck): ?>
                        <br><small>Last checked: <?php echo esc_html($lastcheck); ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <small>
                        Status shown here is what your last sync stored.<br>
                        To refresh, run your OtterText sync (cron or manual trigger from the Tools page).
                    </small>
                </td>
            </tr>
        <?php else : ?>
            <tr>
                <th><label>Twilio</label></th>
                <td>
                    Twilio is active. Messages will send using your Twilio settings.<br>
                    <small>Switch to OtterText in Review Raffles → Settings to see OtterText status here.</small>
                </td>
            </tr>
        <?php endif; ?>
    </table>
    <?php
}

/**
 * Admin meta box: show winner notification schedule on product edit screen.
 */
add_action( 'add_meta_boxes', 'twwt_add_winner_schedule_metabox' );
function twwt_add_winner_schedule_metabox() {
    add_meta_box(
        'twwt_winner_schedule_metabox',
        __( 'Winner Notification Schedule', 'twwt' ),
        'twwt_winner_schedule_metabox_cb',
        'product',
        'side',
        'default'
    );
}

/**
 * Meta box callback: prints "Winner selected at" and "Notifications scheduled for".
 */
function twwt_winner_schedule_metabox_cb( $post ) {

    // Raw meta values stored when winner is selected
    $winner_selected_at     = get_post_meta( $post->ID, 'tww_winner_s_date', true );
    $winner_notification_at = get_post_meta( $post->ID, 'tww_winner_n_date', true );

    $display_selected  = '—';
    $display_scheduled = '—';

    if ( $winner_selected_at ) {
        $ts = strtotime( $winner_selected_at );
        if ( $ts ) {
            $display_selected = date_i18n( 'F j, Y g:i A', $ts );
        }
    }

    if ( $winner_notification_at ) {
        $ts = strtotime( $winner_notification_at );
        if ( $ts ) {
            $display_scheduled = date_i18n( 'F j, Y g:i A', $ts );
        }
    }

    $tz = get_option( 'timezone_string' );
    if ( ! $tz ) {
        $tz = 'UTC';
    }

    ?>
    <p>
        <strong><?php esc_html_e( 'Winner selected at:', 'twwt' ); ?></strong><br>
        <span><?php echo esc_html( $display_selected ); ?></span>
    </p>
    <p>
        <strong><?php esc_html_e( 'Notifications scheduled for:', 'twwt' ); ?></strong><br>
        <span><?php echo esc_html( $display_scheduled ); ?></span>
    </p>
    <p style="font-size:11px;color:#666;margin-top:10px;">
        <?php printf(
            esc_html__( 'Times shown in site timezone: %s', 'twwt' ),
            esc_html( $tz )
        ); ?>
    </p>
    <?php
}

// Register the dashboard widget
add_action('wp_dashboard_setup', 'twwt_register_notification_widget');
function twwt_register_notification_widget() {
    wp_add_dashboard_widget(
        'twwt_notification_status_widget',
        '📣 Product Notification Status',
        'twwt_display_notification_widget'
    );
}

// Display the widget content (updated for Immediate + Daily batch)
function twwt_display_notification_widget() {
    // Basic parts
    $settings = get_option('twwt_woo_settings', array());
    $mode = isset($settings['notification_mode']) ? $settings['notification_mode'] : 'immediate';

    // WP timezone for date displays
    if ( function_exists('wp_timezone') ) {
        $tz = wp_timezone();
    } else {
        $tz_string = get_option('timezone_string');
        $tz = new DateTimeZone( $tz_string ? $tz_string : 'UTC' );
    }

    // Helper: format timestamp in site tz
    $fmt_ts = function($ts) use ($tz) {
        if (!$ts) return '—';
        $d = new DateTime('@' . intval($ts));
        $d->setTimezone($tz);
        return $d->format('Y-m-d H:i:s');
    };

    // Count of opted-in users (twwy_opt_notification == 1)
    $opted_in_count = 0;
    $total_customers = 0;
    // We'll attempt a light-weight count using WP_User_Query
    $user_count_args = array(
        'role' => 'customer',
        'fields' => 'ID',
        'number' => 1,
    );
    // Count total customers quickly:
    $user_query_total = new WP_User_Query(array_merge($user_count_args, array('number' => 1)));
    $total_customers = (int) $user_query_total->get_total();

    // Count opted-in customers - attempt WP_User_Query with meta query and count.
    $opt_query = new WP_User_Query(array(
        'role' => 'customer',
        'meta_query' => array(
            array(
                'key' => 'twwy_opt_notification',
                'value' => '1',
                'compare' => '='
            )
        ),
        'fields' => 'ID',
        'number' => 1,
    ));
    $opted_in_count = (int) $opt_query->get_total();

    echo '<div style="font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif">';

    // Mode header
    echo '<p><strong>Mode:</strong> ' . esc_html( ucfirst($mode) ) . '</p>';
    echo '<p><strong>Total customers:</strong> ' . number_format_i18n($total_customers) . ' &nbsp; <strong>Opted-in:</strong> ' . number_format_i18n($opted_in_count) . '</p>';

    if ($mode === 'immediate') {
        // Immediate mode: show single active notification post
        $post_id = get_option('twwt_np_notification_auto_start_post_id');
        if (!$post_id) {
            echo '<p style="color:green;">✅ No active notification process.</p>';
        } else {
            $post = get_post($post_id);
            if (!$post) {
                echo '<p style="color:orange;">⚠️ Notification post not found. Clean up may be needed.</p>';
            } else {
                echo '<ul>';
                echo '<li><strong>🔗 Product:</strong> <a href="' . esc_url(get_edit_post_link($post_id)) . '">' . esc_html($post->post_title) . '</a> (ID: ' . intval($post_id) . ')</li>';

                // Count users already marked as notified for this product.
                $notified_count = 0;

                // If site small, simple loop; otherwise use paged loop
                $threshold = 2000;
                if ($total_customers <= $threshold) {
                    $users = get_users(array('role' => 'customer', 'fields' => 'ID'));
                    foreach ($users as $uid) {
                        $notified_products = get_user_meta($uid, 'twwt_notified_products', true);
                        if (is_array($notified_products) && in_array($post_id, $notified_products)) {
                            $notified_count++;
                        }
                    }
                    echo '<li><strong>👥 Users Notified:</strong> ' . number_format_i18n($notified_count) . '</li>';
                } else {
                    // Use paged WP_User_Query to avoid memory spikes
                    $per_page = 500;
                    $paged = 1;
                    $found = 0;
                    do {
                        $uq = new WP_User_Query(array(
                            'role' => 'customer',
                            'fields' => 'ID',
                            'number' => $per_page,
                            'paged' => $paged,
                        ));
                        $res = $uq->get_results();
                        if (empty($res)) break;
                        foreach ($res as $uid) {
                            $notified_products = get_user_meta($uid, 'twwt_notified_products', true);
                            if (is_array($notified_products) && in_array($post_id, $notified_products)) {
                                $found++;
                            }
                        }
                        $paged++;
                    } while (count($res) === $per_page);
                    echo '<li><strong>👥 Users Notified:</strong> ' . number_format_i18n($found) . ' (paged count)</li>';
                }

                echo '</ul>';
            }
        }
    } else { // daily mode
        $queue = get_option('twwt_batch_queue', array());
        if (!is_array($queue)) $queue = array();

        $queued_count = count($queue);
        echo '<p><strong>📥 Queue:</strong> ' . number_format_i18n($queued_count) . ' post(s) queued.</p>';

        if ($queued_count > 0) {
            echo '<ul style="margin-left:0;padding-left:1.1rem;">';
            // To avoid timeouts, if > 2000 users we avoid per-post detailed counting unless admin requests it.
            $do_counts = ($total_customers <= 2000);

            foreach ($queue as $pid) {
                $p = get_post($pid);
                if (!$p) {
                    echo '<li style="color:orange;">⚠️ Missing post ID ' . intval($pid) . '</li>';
                    continue;
                }
                echo '<li><a href="' . esc_url(get_edit_post_link($pid)) . '">' . esc_html($p->post_title) . '</a> (ID: ' . intval($pid) . ')';
                if ($do_counts) {
                    // count how many opted-in users are already marked notified for this product
                    $count = 0;
                    $users = get_users(array('role' => 'customer', 'fields' => 'ID'));
                    foreach ($users as $uid) {
                        $notified_products = get_user_meta($uid, 'twwt_notified_products', true);
                        if (is_array($notified_products) && in_array($pid, $notified_products)) {
                            $count++;
                        }
                    }
                    echo ' — <strong>Notified:</strong> ' . number_format_i18n($count);
                } else {
                    echo ' — <em>Per-post notified counts skipped for sites with large user base (>' . number_format_i18n(2000) . ' users).</em>';
                }
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color:green;">✅ Nothing queued for the next batch.</p>';
        }

        // Show scheduling info
        $next_ts = wp_next_scheduled('twwt_daily_batch_hook');
        $last_sent = get_option('twwt_last_batch_sent', 0);
        echo '<p><strong>⏱️ Next scheduled run:</strong> ' . esc_html($fmt_ts($next_ts)) . ' (site timezone)</p>';
        if ($last_sent) {
            echo '<p><strong>✅ Last batch sent:</strong> ' . esc_html($fmt_ts($last_sent)) . '</p>';
        }
        // Provide quick action links (run now) if WP Cron control plugin available - admin can use Tools -> Cron Events
        echo '<p style="font-size:90%;color:#666;">Tip: to run immediately for testing use your manual batch-run helper or Tools → Cron Events → Run Now.</p>';
    }

    echo '</div>';
}


add_action('woocommerce_save_product_variation', 'twwt_debug_variation_save', 99, 2);
function twwt_debug_variation_save($variation_id, $i) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) return;
    $before = array(
        '_stock'       => get_post_meta($variation_id, '_stock', true),
        '_manage_stock'=> get_post_meta($variation_id, '_manage_stock', true),
        '_stock_status'=> get_post_meta($variation_id, '_stock_status', true),
    );
    //error_log("TWWT DEBUG BEFORE SAVE: var={$variation_id} idx={$i} " . print_r($before, true));

    // log posted admin values (common names; may vary by WC version)
    $posted = array(
        'variable_manage_stock' => isset($_POST['variable_manage_stock'][$i]) ? $_POST['variable_manage_stock'][$i] : null,
        'variable_stock'        => isset($_POST['variable_stock'][$i]) ? $_POST['variable_stock'][$i] : null,
        'variable_stock_status' => isset($_POST['variable_stock_status'][$i]) ? $_POST['variable_stock_status'][$i] : null,
    );
    //error_log("TWWT DEBUG POST: " . print_r($posted, true));

    // do a tiny delay to allow other hooks to run, then read again
    add_action('shutdown', function() use ($variation_id) {
        $after = array(
            '_stock'       => get_post_meta($variation_id, '_stock', true),
            '_manage_stock'=> get_post_meta($variation_id, '_manage_stock', true),
            '_stock_status'=> get_post_meta($variation_id, '_stock_status', true),
        );
        //error_log("TWWT DEBUG AFTER SAVE (shutdown): var={$variation_id} " . print_r($after, true));
    });
}
/* CRON HANDLES */ 
add_action( 'init', 'mycronhandle' ); 
function mycronhandle(){ 
    if(@$_GET['mycron']=="winner"){
         twwt_send_msg_winner(); 
         exit;
        } else if(@$_GET['mycron']=="notification"){ 
            twwt_send_np_notification(); 
            exit; 
        } else if(@$_GET['mycron']=="ottertext_batch_sync"){ 
            twwt_ottertext_batch_sync(); 
            exit; 
        }
}

add_action('admin_head', function () {
    ?>
    <style>
        .twwt-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
        }
        .twwt-badge-success {
            background: #e6f4ea;
            color: #1e7e34;
            border: 1px solid #b7e1c1;
        }
    </style>
    <?php
});