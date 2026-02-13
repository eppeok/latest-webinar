<?php
/*
Plugin Name: Review Raffles
Description: Review Raffles – webinar and raffle management plugin for WooCommerce.
Version: 2.1
Author: Review Raffles Team
Author URI: https://www.reviewraffles.com/
Tags: ticket

Copyright (c) ReviewRaffles, LLC. All rights reserved.
This code is proprietary and subject to the licensing terms agreed upon at issuance.
Code may not be replicated, redistributed, or re-used without express written permission.
*/

define( 'TWWT_VERSION', '2.1' );
define( 'TWWT_PLUGIN', __FILE__ );
define( 'TWWT_PLUGIN_BASENAME', plugin_basename( TWWT_PLUGIN ) );
define( 'TWWT_PLUGIN_DIRPATH', plugin_dir_path( TWWT_PLUGIN ) );
// Seat hold duration – read from settings (default 10 minutes)
$twwt_opts = get_option( 'twwt_woo_settings' );
define( 'TWWT_SEAT_HOLD_SECONDS', max( 60, intval( isset($twwt_opts['seat_hold_minutes']) ? $twwt_opts['seat_hold_minutes'] : 10 ) * 60 ) );


register_activation_hook( __FILE__, 'twwt_plugin_activate' );
function twwt_plugin_activate() {
    // Schedule daily batch job
    if (function_exists('twwt_schedule_daily_batch')) {
        twwt_schedule_daily_batch();
    }
    // Schedule temp seat cleanup every 10 minutes
    if ( ! wp_next_scheduled( 'twwt_cleanup_temp_seats_hook' ) ) {
        wp_schedule_event( time(), 'twwt_every_10_min', 'twwt_cleanup_temp_seats_hook' );
    }
}

register_deactivation_hook( __FILE__, 'twwt_plugin_deactivate' );
function twwt_plugin_deactivate() {
    if (wp_next_scheduled('twwt_daily_batch_hook')) {
        wp_clear_scheduled_hook('twwt_daily_batch_hook');
    }
    wp_clear_scheduled_hook( 'twwt_cleanup_temp_seats_hook' );
}

// Custom cron interval: every 10 minutes
add_filter( 'cron_schedules', 'twwt_add_cron_intervals' );
function twwt_add_cron_intervals( $schedules ) {
    $schedules['twwt_every_10_min'] = array(
        'interval' => 600,
        'display'  => __( 'Every 10 Minutes' ),
    );
    return $schedules;
}

// Cron handler: delete all expired temp_booked_seat_* entries
add_action( 'twwt_cleanup_temp_seats_hook', 'twwt_cleanup_expired_temp_seats' );
function twwt_cleanup_expired_temp_seats() {
    global $wpdb;
    $cutoff = time() - TWWT_SEAT_HOLD_SECONDS;
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND CAST(meta_value AS UNSIGNED) < %d",
        'temp\_booked\_seat\_%',
        $cutoff
    ) );
}

// Also schedule on init if activation hook was missed (e.g. plugin already active)
add_action( 'init', 'twwt_ensure_temp_cleanup_scheduled' );
function twwt_ensure_temp_cleanup_scheduled() {
    if ( ! wp_next_scheduled( 'twwt_cleanup_temp_seats_hook' ) ) {
        wp_schedule_event( time(), 'twwt_every_10_min', 'twwt_cleanup_temp_seats_hook' );
    }
}


// One-time stock recalculation for all webinar variations (runs once on admin init)
add_action( 'admin_init', 'twwt_one_time_stock_recalc' );
function twwt_one_time_stock_recalc() {
    if ( get_option( 'twwt_stock_recalc_v4_done' ) ) { return; }
    update_option( 'twwt_stock_recalc_v4_done', 1 );

    global $wpdb;
    // Find all variations that have at least one perma_booked_seat
    $variation_ids = $wpdb->get_col(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key LIKE 'perma\_booked\_seat\_%'"
    );
    foreach ( $variation_ids as $vid ) {
        twwt_recalculate_variation_stock( intval( $vid ) );
    }
}

register_activation_hook( __FILE__, 'my_plugin_create_winner_page' );
function my_plugin_create_winner_page() {
    $winner_page = get_page_by_path( 'winner' );
    if ( ! $winner_page ) {
        $winner_page_id = wp_insert_post(
            array(
                'post_title'   => 'Winner',
                'post_content' => '<!-- wp:shortcode -->[my_shortcode]<!-- /wp:shortcode -->',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => 'winner'
            )
        );
    }

    // Create "Webinar" product category if it doesn't exist and set as default
    if ( taxonomy_exists( 'product_cat' ) || class_exists( 'WooCommerce' ) ) {
        $webinar_term = term_exists( 'Webinar', 'product_cat' );
        if ( ! $webinar_term ) {
            $webinar_term = wp_insert_term( 'Webinar', 'product_cat', array(
                'slug' => 'webinar',
            ) );
        }
        if ( ! is_wp_error( $webinar_term ) ) {
            $term_id = is_array( $webinar_term ) ? $webinar_term['term_id'] : $webinar_term;
            $settings = get_option( 'twwt_woo_settings', array() );
            if ( empty( $settings['default_webinar_category'] ) ) {
                $settings['default_webinar_category'] = intval( $term_id );
                update_option( 'twwt_woo_settings', $settings );
            }
        }
    }

    if ( function_exists('twwt_ensure_twilio_log_table_exists') ) {
        twwt_ensure_twilio_log_table_exists();
    } else {
        twwt_ensure_twilio_log_table_exists();
    }

}

// Ensure "Webinar" category exists (runs once, for sites where activation hook already fired)
add_action( 'init', 'twwt_ensure_webinar_category_exists' );
function twwt_ensure_webinar_category_exists() {
    if ( get_option( 'twwt_webinar_cat_created' ) ) {
        return;
    }
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return;
    }
    update_option( 'twwt_webinar_cat_created', 1 );

    $webinar_term = term_exists( 'Webinar', 'product_cat' );
    if ( ! $webinar_term ) {
        $webinar_term = wp_insert_term( 'Webinar', 'product_cat', array( 'slug' => 'webinar' ) );
    }
    if ( ! is_wp_error( $webinar_term ) ) {
        $term_id  = is_array( $webinar_term ) ? $webinar_term['term_id'] : $webinar_term;
        $settings = get_option( 'twwt_woo_settings', array() );
        if ( empty( $settings['default_webinar_category'] ) ) {
            $settings['default_webinar_category'] = intval( $term_id );
            update_option( 'twwt_woo_settings', $settings );
        }
    }
}

// Ensure winner page always contains the shortcode (handles block editor edits)
add_action( 'init', 'twwt_ensure_winner_page_shortcode' );
function twwt_ensure_winner_page_shortcode() {
    $page = get_page_by_path( 'winner' );
    if ( $page && strpos( $page->post_content, '[my_shortcode]' ) === false ) {
        wp_update_post( array(
            'ID'           => $page->ID,
            'post_content' => '<!-- wp:shortcode -->[my_shortcode]<!-- /wp:shortcode -->',
        ) );
    }
}

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

    $wpdb->query($sql);
}

function custom_add_to_cart_redirect($url) {
    if (!empty($_REQUEST['add-to-cart'])) {
        return wc_get_cart_url(); 
    }
    return $url;
}
add_filter('woocommerce_add_to_cart_redirect', 'custom_add_to_cart_redirect');

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
	if ( $page ) {
		$WinnerPageId = $page->ID;
		if ( $WinnerPageId ) {
			wp_delete_post( $WinnerPageId, true );
		}
	}
}
register_deactivation_hook( __FILE__, 'delete_custom_page_on_deactivation' );

function twwt_count_perma_booked_seats( $variation_id ){
    global $wpdb;
    $like = $wpdb->esc_like('perma_booked_seat_') . '%';
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
        $variation_id, $like
    ));
}

/**
 * HPOS-compatible helper: get all paid order IDs that contain a given product.
 * Works with both WC HPOS (wp_wc_orders) and legacy (wp_posts) storage.
 */
function twwt_get_paid_order_ids_for_product( $product_id ) {
    global $wpdb;
    $product_id = intval( $product_id );
    if ( ! $product_id ) { return array(); }

    // Get candidate order IDs from order items table (exists in both HPOS and legacy)
    $candidate_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT i.order_id
         FROM {$wpdb->prefix}woocommerce_order_items AS i
         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS im ON i.order_item_id = im.order_item_id
         WHERE im.meta_key = '_product_id' AND im.meta_value = %d",
        $product_id
    ) );

    if ( empty( $candidate_ids ) ) { return array(); }

    // Filter to only paid orders using WC API (works with HPOS and legacy)
    $paid_statuses = wc_get_is_paid_statuses();
    $order_ids = array();
    foreach ( $candidate_ids as $oid ) {
        $order = wc_get_order( $oid );
        if ( $order && in_array( $order->get_status(), $paid_statuses, true ) ) {
            $order_ids[] = (int) $oid;
        }
    }
    return $order_ids;
}

function twwt_recalculate_variation_stock( $variation_id ){
    global $wpdb;

    $max = (int) get_post_meta( $variation_id, '_variable_text_field', true );
    if ( $max <= 0 ) { return; }

    $booked    = twwt_count_perma_booked_seats( $variation_id );
    $available = max( 0, $max - $booked );

    // Update variation stock directly via post meta (bypasses product type issues)
    update_post_meta( $variation_id, '_manage_stock', 'yes' );
    update_post_meta( $variation_id, '_backorders', 'no' );
    update_post_meta( $variation_id, '_stock', $available );
    update_post_meta( $variation_id, '_stock_status', $available > 0 ? 'instock' : 'outofstock' );
    wc_delete_product_transients( $variation_id );

    // Find parent product
    $parent_id = wp_get_post_parent_id( $variation_id );
    if ( ! $parent_id ) { return $available; }

    // Ensure parent product has correct 'variable' product type term
    // (WC sometimes loads it as 'simple' due to stale term cache)
    if ( ! has_term( 'variable', 'product_type', $parent_id ) ) {
        wp_set_object_terms( $parent_id, 'variable', 'product_type', false );
        wc_delete_product_transients( $parent_id );
    }

    // Check if ALL variations for this parent are out of stock
    $in_stock_count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->postmeta}
         WHERE meta_key = '_stock_status' AND meta_value = 'instock'
         AND post_id IN (
            SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'
         )",
        $parent_id
    ) );

    $parent_status = $in_stock_count > 0 ? 'instock' : 'outofstock';
    update_post_meta( $parent_id, '_stock_status', $parent_status );
    // Also update via WC if possible
    WC_Product_Variable::sync_stock_status( $parent_id );
    wc_delete_product_transients( $parent_id );
    // Clear WC object cache so admin list picks up the change
    clean_post_cache( $parent_id );

    return $available;
}

function twwt_ottertext_block_reason($code) {
    switch ((string)$code) {
        case '1': return 'OtterText: customer exists but has not confirmed yet (New Customer). Opt-in SMS was sent.';
        case '2': return 'OtterText: opt-in requested; awaiting confirmation reply.';
        case '4': return 'OtterText: customer opted out. You must obtain consent again.';
        case '5': return 'OtterText: invalid phone number.';
        default:  return 'OtterText: customer not found; opt-in SMS was sent.';
    }
}


function wp_ottertext_sms($to_mobile, $msg){
    $settings = get_option('twwt_woo_settings');
    $api_key  = isset($settings['ottertext_api_key']) ? trim($settings['ottertext_api_key']) : '';
    $partner  = isset($settings['ottertext_partner']) ? trim($settings['ottertext_partner']) : 'WordPress';

    if ($api_key === '' || $to_mobile === '' || $msg === '') { return; }

    $e164 = twwt_normalize_us_phone($to_mobile);
    if (!$e164) {
        return;
    }

    $status = twwt_ottertext_get_optin_status($e164, $partner, $api_key);
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

    if ($status !== '3') {
        $first = $user ? get_user_meta($user->ID, 'billing_first_name', true) : '';
        $last  = $user ? get_user_meta($user->ID, 'billing_last_name', true)  : '';
        $email = $user ? ($user->user_email ?? '') : '';
        $zip   = $user ? get_user_meta($user->ID, 'billing_postcode', true)   : '';

        $add = twwt_ottertext_add_customer($e164, $first, $last, $email, $zip, $partner, $api_key);

        $reason = twwt_ottertext_block_reason($status);
        $logMsg = $add['ok']
            ? $reason
            : $reason . ' | add_customer failed: ' . $add['body'];
        return; // DO NOT attempt to send (TCPA)
    }

    $endpoint = 'https://app.ottertext.com/api/customers/sendmessage';
    $body = array(
        'customer'   => $e164,
        'sms_or_mms' => '1',
        'send_type'  => '1',
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
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);

    if ($code >= 200 && $code < 300) {
        return $raw;
    } else {
        return;
    }
}

function wp_aiq_sms($to_mobile, $msg) {

    $settings = get_option('twwt_woo_settings');
    $api_key  = isset($settings['aiq_api_key']) ? trim($settings['aiq_api_key']) : '';

    if (empty($api_key) || empty($to_mobile) || empty($msg)) {
        return false;
    }

    $phone = preg_replace('/\D+/', '', $to_mobile);
    if (strlen($phone) === 10) {
        $phone = '1' . $phone;
    }

    $endpoint = 'https://api.alpineiq.com/api/v2/sms';

    $response = wp_remote_post(
        $endpoint,
        array(
            'timeout' => 15,
            'headers' => array(
                'X-APIKEY'     => $api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'phone' => $phone,
                'body'  => $msg,
            )),
        )
    );

    if (is_wp_error($response)) {
        twwt_twilio_log($phone, $msg, $response->get_error_message(), 0, 'aiq');
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $body        = wp_remote_retrieve_body($response);

    if ($status_code >= 200 && $status_code < 300) {
        //twwt_twilio_log($phone, $msg, $body, 1, 'aiq');
    } else {
        //twwt_twilio_log($phone, $msg, $body, 0, 'aiq');
    }

    return $body;
}


#Twilio
require_once 'vendor/autoload.php';
use Twilio\Rest\Client;
function wp_twilio_sms($to_mobile, $msg){
    $settings = get_option( 'twwt_woo_settings' );

    if (!empty($settings['sms_provider'])) {

        if ($settings['sms_provider'] === 'ottertext') {
            if (function_exists('wp_ottertext_sms')) {
                error_log('SMS PROVIDER USED: ottertext');
                return wp_ottertext_sms($to_mobile, $msg);
            }
        }

        if ($settings['sms_provider'] === 'aiq') {
            error_log('SMS PROVIDER USED: aiq');
            return wp_aiq_sms($to_mobile, $msg);
        }
        error_log('SMS PROVIDER USED: twilio');

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
        //error_log('SMS PROVIDER USED: ' . ($settings['sms_provider'] ?? 'twilio'));
        return $response;
    } catch(Exception $ex){
        //twwt_twilio_log($to_mobile, $msg, $ex, 0, 'twilio');
        return;
    }
}

function twwt_normalize_us_phone($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $raw);

    if (substr($digits, 0, 1) === '1' && strlen($digits) > 10) {
        $digits = substr($digits, -10);
    }
    if (strlen($digits) !== 10) {
        return '';
    }
    return '+1' . $digits;
}

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

function twwt_ottertext_batch_sync() {
    $opts     = get_option('twwt_woo_settings');
    $api_key  = isset($opts['ottertext_api_key']) ? trim($opts['ottertext_api_key']) : '';
    $partner  = isset($opts['ottertext_partner']) ? trim($opts['ottertext_partner']) : 'WordPress';

    if (!$api_key || !$partner) {
        return array(
            'error' => 'Missing OtterText API key or partner. Please check Review Raffles → Settings → SMS Provider.'
        );
    }
    $stats = array(
        'total_users'           => 0,
        'eligible'              => 0,
        'skipped_local_optout'  => 0,
        'invalid_phone'         => 0,
        'already_opted_in'      => 0,
        'opted_out'             => 0,
        'optin_sms_triggered'   => 0,
        'errors'                => 0,
    );

    $user_query = new WP_User_Query(array(
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
            continue;
        }

        $first = get_user_meta($uid, 'billing_first_name', true);
        $last  = get_user_meta($uid, 'billing_last_name', true);
        $user_obj = get_userdata($uid);
        $email = $user_obj ? $user_obj->user_email : '';
        $zip   = get_user_meta($uid, 'billing_postcode', true);

        $status_before = twwt_ottertext_get_optin_status($e164, $partner, $api_key);

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
            $opt = twwt_ottertext_get_optin_status($e164, $partner, $api_key);
        } else {
            $add = array(
                'ok'   => true,
                'code' => 200,
                'body' => 'Skipped add_customer; status=' . $status_before,
            );
            $opt = $status_before;
        }

        if ($opt !== '') {
            update_user_meta($uid, 'ottertext_optincheck', $opt);
            update_user_meta($uid, 'ottertext_phone', $e164);
            update_user_meta($uid, 'ottertext_last_sync', current_time('mysql'));
        }

        if (function_exists('twwt_twilio_log')) {
            $log_msg = 'Bulk sync: status_before=' . $status_before . ' status_after=' . $opt;
            if (empty($add['ok'])) {
                $log_msg .= ' | add_customer_error=' . $add['body'];
            }
        }

        $processed++;
        if ($processed % 25 === 0) {
            sleep(1); 
        }
    }

    return $stats;
}


add_filter('cron_schedules', function($schedules) {
    $schedules['every_five_minutes'] = array(
        'interval' => 300, // 300 seconds = 5 minutes
        'display'  => __('Every 5 Minutes')
    );
    return $schedules;
});

if (!wp_next_scheduled('twwt_ottertext_cron')) {
    wp_schedule_event(time() + 300, 'every_five_minutes', 'twwt_ottertext_cron');
}


add_action('twwt_ottertext_cron', 'twwt_ottertext_batch_sync');

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
    if ( ! function_exists('twwt_ensure_twilio_log_table_exists') ) {
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

    $format = array('%s', '%s', '%s', '%d', '%s', '%s');
    $wpdb->insert($table, $data, $format);

    return $wpdb->insert_id;
}

function twwt_woo_add_custom_fields() {

	global $woocommerce, $post;

	echo '<div class="options_group">';

	woocommerce_wp_text_input(
		array(
			'id'          => '_text_field',
			'label'       => __( 'My Text Field', 'woocommerce' ),
			'placeholder' => 'http://',
			'desc_tip'    => true,
			'description' => __( "Here's some really helpful tooltip text.", "woocommerce" )
		)
 	);

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


function twwt_woo_add_custom_variation_fields_save( $post_id ){
	if ( ! isset($_POST['_variable_text_field'][ $post_id ]) ) {
		return;
	}
 	// Text Field
 	$woocommerce_text_field = sanitize_text_field( $_POST['_variable_text_field'][ $post_id ] );
	update_post_meta( $post_id, '_variable_text_field', $woocommerce_text_field );
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
			if( current_user_can('editor') || current_user_can('administrator') ) {
				$page_slug = 'winner';
				$page = get_page_by_path($page_slug);
				$Winnerpermalink = get_permalink($page->ID);
				?>
				<p class="mt-4 mb-0"><a target="_blank" href="<?php echo esc_url( add_query_arg( 'pid', $product_id, $Winnerpermalink ) ); ?>" class="btn btn-winner">Select Attendee</a></p>
				<?php
			}
		}
	}
}

function twwt_woo_scripts(){
	wp_enqueue_style( 'twwt_woo', plugins_url('asset/css/style.css',__FILE__ ), array(), TWWT_VERSION );
	wp_enqueue_script( 'twwt_woo_js', plugins_url('asset/js/main.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
	
	wp_localize_script('twwt_woo_js', 'twwtfa', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce('ajax_nonce'),
		'hold_minutes' => intval( TWWT_SEAT_HOLD_SECONDS / 60 ),
	));
}
add_action( 'wp_enqueue_scripts', 'twwt_woo_scripts' );

function twwt_woo_ajax_function(){
	check_ajax_referer('ajax_nonce', 'nonce');
}

add_action( 'wp_ajax_nopriv_twwt_woo_ajax_function', 'twwt_woo_ajax_function' );
add_action( 'wp_ajax_twwt_woo_ajax_function', 'twwt_woo_ajax_function' );

/**
 * AJAX endpoint: return all seat statuses for a product (used by live polling).
 * Response: { "variation_id": { "seat_number": "perma"|"temp"|"" }, ... }
 */
add_action( 'wp_ajax_twwt_seat_status', 'twwt_seat_status_ajax' );
add_action( 'wp_ajax_nopriv_twwt_seat_status', 'twwt_seat_status_ajax' );
function twwt_seat_status_ajax() {
	$product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
	if ( ! $product_id ) {
		wp_send_json_error('Missing product_id');
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		wp_send_json_error('Invalid product');
	}

	$variation_ids = $product->get_children();
	if ( empty($variation_ids) ) {
		$variation_ids = get_posts( array(
			'post_parent'  => $product_id,
			'post_type'    => 'product_variation',
			'post_status'  => array( 'publish', 'private' ),
			'fields'       => 'ids',
			'numberposts'  => -1,
		) );
	}

	$result = array();
	foreach ( $variation_ids as $vid ) {
		$max_seat = intval( get_post_meta( $vid, '_variable_text_field', true ) );
		$booked  = twwt_count_perma_booked_seats( $vid );
		$seats = array();
		for ( $i = 1; $i <= $max_seat; $i++ ) {
			$avail = twwt_woo_get_availability_v2( $vid, $i );
			$seats[ $i ] = isset($avail['type']) ? $avail['type'] : '';
		}
		$result[ $vid ] = array(
			'seats'     => $seats,
			'total'     => $max_seat,
			'available' => max( 0, $max_seat - $booked ),
		);
	}

	wp_send_json_success( $result );
}


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
	$display_regular_price = '';
	$display_price = '';
	$variation_name = '';
	foreach ($variations as $variation) {
		if($variation['variation_id'] == $variation_id){
			$display_regular_price = '<span class="currency">'. $currency_symbol .'</span>'.$variation['display_regular_price'];
			$display_price = '<span class="currency">'. $currency_symbol .'</span>'.$variation['display_price'];
			$variation_name = implode(' ', $variation['attributes']);
		}
	}

	// Compute available seats directly from perma_booked_seat meta (source of truth)
	$max = (int) get_post_meta( $variation_id, '_variable_text_field', true );
	$booked = twwt_count_perma_booked_seats( $variation_id );
	$ticket_left = max( 0, $max - $booked );

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
 * Atomically reserve a single seat using INSERT ... SELECT.
 * Returns true if the seat was successfully reserved, false if already taken.
 */
function twwt_atomic_reserve_seat( $variation_id, $seat ) {
    global $wpdb;

    $variation_id = intval( $variation_id );
    $meta_key_temp  = 'temp_booked_seat_' . $seat;
    $meta_key_perma = 'perma_booked_seat_' . $seat;
    $now            = time();
    $cutoff         = $now - TWWT_SEAT_HOLD_SECONDS;

    // First, clean up any expired temp booking for this specific seat
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta}
         WHERE post_id = %d AND meta_key = %s AND CAST(meta_value AS UNSIGNED) < %d",
        $variation_id, $meta_key_temp, $cutoff
    ) );

    // Atomic insert: only succeeds if neither a valid temp nor a perma booking exists.
    // Uses INSERT ... SELECT with a condition that returns 0 rows if seat is taken.
    $rows = $wpdb->query( $wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
         SELECT %d, %s, %s FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1 FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = %s
         )
         AND NOT EXISTS (
             SELECT 1 FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = %s AND CAST(meta_value AS UNSIGNED) >= %d
         )",
        $variation_id, $meta_key_temp, (string) $now,
        $variation_id, $meta_key_perma,
        $variation_id, $meta_key_temp, $cutoff
    ) );

    return $rows > 0;
}

function twwt_woo_add_to_cart_validation($passed, $product_id, $quantity, $variation_id = null) {
    // Ensure we have a variation_id — WC may not pass it if product loaded as simple
    if ( empty($variation_id) && !empty($_POST['variation_id']) ) {
        $variation_id = absint($_POST['variation_id']);
    }

    if (!empty($_POST['seat'])) {
        $booked_seats = array_map( 'sanitize_text_field', $_POST['seat'] );

        if (count($booked_seats) != $quantity) {
            $passed = false;
            wc_add_notice(__('Please try again later.', 'woocommerce'), 'error');
            return $passed;
        }

        // Attempt atomic reservation for each seat
        $reserved = array();
        foreach ($booked_seats as $seat) {
            if ( ! preg_match( '/^[a-zA-Z0-9_\-]+$/', $seat ) ) {
                $passed = false;
                wc_add_notice(__('Invalid seat identifier.', 'woocommerce'), 'error');
                break;
            }
            if ( ! twwt_atomic_reserve_seat( $variation_id, $seat ) ) {
                $passed = false;
                wc_add_notice(__('Selected seat "' . esc_html($seat) . '" is not available for booking.', 'woocommerce'), 'error');
            } else {
                $reserved[] = $seat;
            }
        }

        // If any seat failed, roll back all successfully reserved seats
        if ( ! $passed && ! empty( $reserved ) ) {
            foreach ( $reserved as $seat ) {
                delete_post_meta( $variation_id, 'temp_booked_seat_' . $seat );
            }
        }
    }

    if ($passed) {
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id_n = $cart_item['product_id'];

                if (twwt_woo_product_seat($product_id_n)) {
                    if ($product_id_n == $product_id) {
                        WC()->cart->remove_cart_item($cart_item_key);
                    }
                }
            }
        }

        setcookie('seat_selected', time() + TWWT_SEAT_HOLD_SECONDS, time() + TWWT_SEAT_HOLD_SECONDS, '/');
        setcookie("seat_selected_{$variation_id}", time() + TWWT_SEAT_HOLD_SECONDS, time() + TWWT_SEAT_HOLD_SECONDS, '/');
    }

    return $passed;
}

add_filter('woocommerce_add_to_cart_validation', 'twwt_woo_add_to_cart_validation', 10, 4);

function twwt_woo_check_cart_timing(){
	if(is_admin()){
		return;
	}

	if( ! WC()->cart->is_empty() ){
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if(!twwt_woo_product_seat($cart_item['product_id'])){
				continue;
			}

			// Check seat hold validity via the timestamp stored in the cart item
			if ( !empty($cart_item['twwt_added_at']) ) {
				if ( (time() - intval($cart_item['twwt_added_at'])) > TWWT_SEAT_HOLD_SECONDS ) {
					WC()->cart->remove_cart_item($cart_item_key);
				}
			}
		}
	}

}
add_action('wp', 'twwt_woo_check_cart_timing');

function twwt_woo_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
 if( isset( $_POST['seat'] ) && is_array( $_POST['seat'] ) ) {
	 $booked_seats = array_map( 'sanitize_text_field', $_POST['seat'] );
	 $cart_item_data['seat'] = implode(', ', $booked_seats );

	 // Store the actual variation_id (WC may pass 0 if product loaded as simple)
	 $actual_vid = $variation_id;
	 if ( empty($actual_vid) && !empty($_POST['variation_id']) ) {
		 $actual_vid = absint($_POST['variation_id']);
	 }
	 $cart_item_data['twwt_variation_id'] = $actual_vid;

	 // Timestamp for cart hold expiry check (independent of cookies/metadata)
	 $cart_item_data['twwt_added_at'] = time();
 }
 return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'twwt_woo_add_cart_item_data', 10, 3 );

/**
 * Release temp seat bookings when an item is removed from the cart.
 */
add_action( 'woocommerce_remove_cart_item', 'twwt_woo_release_temp_seats_on_remove', 10, 2 );
function twwt_woo_release_temp_seats_on_remove( $cart_item_key, $cart ) {
    $item = isset( $cart->removed_cart_contents[ $cart_item_key ] )
        ? $cart->removed_cart_contents[ $cart_item_key ]
        : ( isset( $cart->cart_contents[ $cart_item_key ] ) ? $cart->cart_contents[ $cart_item_key ] : null );

    if ( ! $item ) { return; }

    // Use twwt_variation_id (actual variation) first, then WC's variation_id
    $variation_id = ! empty( $item['twwt_variation_id'] ) ? intval( $item['twwt_variation_id'] ) : 0;
    if ( ! $variation_id ) {
        $variation_id = ! empty( $item['variation_id'] ) ? intval( $item['variation_id'] ) : 0;
    }
    if ( ! $variation_id ) { return; }

    if ( ! empty( $item['seat'] ) ) {
        $seats = array_map( 'trim', explode( ',', $item['seat'] ) );
        foreach ( $seats as $seat ) {
            if ( preg_match( '/^[a-zA-Z0-9_\-]+$/', $seat ) ) {
                delete_post_meta( $variation_id, 'temp_booked_seat_' . $seat );
            }
        }
    }
}

/**
 * Release all temp seat bookings when the entire cart is emptied.
 */
add_action( 'woocommerce_cart_emptied', 'twwt_woo_release_temp_seats_on_empty' );
function twwt_woo_release_temp_seats_on_empty() {
    // WC clears cart_contents before this hook, but we stored seats in session
    $cart = WC()->cart;
    if ( ! $cart ) { return; }

    // The cart contents are already cleared at this point, so we clean via DB
    // This is a safety net — the per-item handler above should catch most cases
    global $wpdb;
    // Clean any temp seats that belong to the current user's recent session (within 10 min)
    $cutoff = time() - 600;
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s AND meta_value > %d",
        'temp_booked_seat_%',
        $cutoff
    ) );
}


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


function twwt_woo_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
	if( isset( $values['seat'] ) ) {
		$item->add_meta_data(
			__( 'Seat(s)', 'woocommerce' ),
			$values['seat'],
			true
		);
	}
	// Store the actual variation_id (WC may have 0 if product loaded as simple)
	if ( ! empty( $values['twwt_variation_id'] ) ) {
		$item->add_meta_data( '_twwt_variation_id', intval( $values['twwt_variation_id'] ), true );
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'twwt_woo_checkout_create_order_line_item', 10, 4 );

function twwt_woo_get_availability_v2($item_id, $ticket_no){

	$perma_book = get_post_meta($item_id, 'perma_booked_seat_'.$ticket_no, true);
	if($perma_book!=""){
		return array("status" => true, "type" => "perma");
	}
	$temp_booking_time = get_post_meta($item_id, 'temp_booked_seat_'.$ticket_no, true);
	if($temp_booking_time!=""){
		$current_time = time();
		$time_diff = $current_time - $temp_booking_time;
		if($time_diff > TWWT_SEAT_HOLD_SECONDS){
			// Expired — clean up the orphaned meta
			delete_post_meta($item_id, 'temp_booked_seat_'.$ticket_no);
			return array("status" => false, "type" => "");
		}
		else{
			return array("status" => true, "type" => "temp");
		}
	}
}
function twwt_woo_get_availability($item_id, $ticket_no){
	$perma_book = get_post_meta($item_id, 'perma_booked_seat_'.$ticket_no, true);
	if($perma_book!=""){
		return true;
	}
	$temp_booking_time = get_post_meta($item_id, 'temp_booked_seat_'.$ticket_no, true);
	if($temp_booking_time!=""){
		$current_time = time();
		$time_diff = $current_time - $temp_booking_time;
		if($time_diff > TWWT_SEAT_HOLD_SECONDS){
			// Expired — clean up the orphaned meta
			delete_post_meta($item_id, 'temp_booked_seat_'.$ticket_no);
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
                // Legacy shortcode cart/checkout hooks
                add_action('woocommerce_before_cart', 'twwt_woo_display_notice', 5);
                add_action('woocommerce_before_checkout_form', 'twwt_woo_display_notice', 5);
                // Block-based cart/checkout fallback (wp_footer)
                if (is_cart() || is_checkout()) {
                    add_action('wp_footer', 'twwt_woo_display_notice_footer', 5);
                }
                break; // Add notice only once
            }
        }
    }
}

function twwt_woo_display_notice() {
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        // Use the actual variation_id (twwt_variation_id) which matches JS sessionStorage keys
        $variation_id = ! empty( $cart_item['twwt_variation_id'] ) ? $cart_item['twwt_variation_id'] : $cart_item['variation_id'];

        if (twwt_woo_product_seat($cart_item['product_id'])) {
            $pname = html_entity_decode(get_the_title($product_id));
            echo '<ul class="woocommerce-error" role="alert"><li><span class="twwt_woo_cc_notice" data-id="' . esc_attr($variation_id) . '" data-title="' . esc_attr($pname) . '">Please wait...</span></li></ul>';
        }
    }
}

function twwt_woo_display_notice_footer() {
    // Only render if the notice wasn't already rendered by legacy hooks
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;

    $notices = '';
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        // Use the actual variation_id (twwt_variation_id) which matches JS sessionStorage keys
        $variation_id = ! empty( $cart_item['twwt_variation_id'] ) ? $cart_item['twwt_variation_id'] : $cart_item['variation_id'];

        if (twwt_woo_product_seat($cart_item['product_id'])) {
            $pname = html_entity_decode(get_the_title($product_id));
            $notices .= '<li><span class="twwt_woo_cc_notice" data-id="' . esc_attr($variation_id) . '" data-title="' . esc_attr($pname) . '">Please wait...</span></li>';
        }
    }
    if ($notices) {
        echo '<div id="twwt-timer-notice" style="display:none"><ul class="woocommerce-error" role="alert">' . $notices . '</ul></div>';
        echo '<script>
(function(){
    var n = document.getElementById("twwt-timer-notice");
    if (!n) return;
    // If legacy notice already exists on page, remove the footer duplicate
    if (document.querySelector(".woocommerce-notices-wrapper .twwt_woo_cc_notice, .woocommerce-error .twwt_woo_cc_notice:not(#twwt-timer-notice .twwt_woo_cc_notice)")) {
        n.remove(); return;
    }
    // Insert before the cart block or main content
    var target = document.querySelector(".wp-block-woocommerce-cart, .wp-block-woocommerce-checkout, .woocommerce-cart, .woocommerce-checkout, .entry-content, main");
    if (target) {
        n.style.display = "";
        target.insertBefore(n, target.firstChild);
    } else {
        n.style.display = "";
    }
    // Re-init timer
    if (typeof twwt_reinit_timer === "function") { setTimeout(twwt_reinit_timer, 200); }
})();
</script>';
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

add_action('woocommerce_order_status_failed', 'twwt_handle_order_failed', 100, 2);
function twwt_handle_order_failed($order_id, $order_obj = null) {
    if ( get_post_meta( $order_id, '_twwt_failed_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_failed_handled', 1 );

    $order = is_object($order_obj) ? $order_obj : wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_items() as $item ) {
        $variation_id = $item->get_variation_id();
        if ( ! $variation_id ) {
            $variation_id = (int) $item->get_meta('_twwt_variation_id', true);
        }
        if ( ! $variation_id ) { continue; }

        $seats = $item->get_meta('Seat(s)', true);
        if ( empty( $seats ) ) { continue; }

        $seats = array_map('trim', explode(',', $seats));
        custom_delete_seats($variation_id, $seats);
        twwt_recalculate_variation_stock( $variation_id );
    }

    update_post_meta($order_id, 'epp_is_seats_removed', 1);
}

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

add_action('woocommerce_order_refunded', 'twwt_handle_order_refunded', 100, 2);
function twwt_handle_order_refunded($order_id, $refund_id) {
    $restock = isset($_POST['restock_refunded_items'])
        ? filter_var($_POST['restock_refunded_items'], FILTER_VALIDATE_BOOLEAN)
        : true;

    if (!$restock) return;

    if ( get_post_meta( $order_id, '_twwt_refund_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_refund_handled', 1 );

    $order = wc_get_order($order_id);
    if (!$order) return;

    foreach ($order->get_items() as $item) {
        $variation_id = $item->get_variation_id();
        if ( ! $variation_id ) {
            $variation_id = (int) $item->get_meta('_twwt_variation_id', true);
        }
        if (!$variation_id) continue;

        $seats = $item->get_meta('Seat(s)', true);
        if (!$seats) continue;

        $seats = array_map('trim', explode(',', $seats));

        custom_delete_seats($variation_id, $seats);

        twwt_recalculate_variation_stock($variation_id);
    }

    update_post_meta($order_id, 'epp_is_refunded', 1);
}


add_action('woocommerce_order_status_cancelled', 'custom_handle_order_cancellation', 100, 2);
function custom_handle_order_cancellation($order_id, $order) {
    if ( get_post_meta( $order_id, '_twwt_cancel_handled', true ) ) {
        return;
    }
    update_post_meta( $order_id, '_twwt_cancel_handled', 1 );

    if (!custom_has_refunds($order)) {
        foreach ($order->get_items() as $item) {
            $variation_id = $item->get_variation_id();
            if ( ! $variation_id ) {
                $variation_id = (int) $item->get_meta('_twwt_variation_id', true);
            }
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

function twwt_update_seat($order_id) {
    $order = wc_get_order($order_id);
    $items = $order->get_items();

    foreach ($items as $item) {
        // Use WC's variation_id, or fall back to our stored twwt_variation_id
        $variation_id = $item->get_variation_id();
        if ( ! $variation_id ) {
            $variation_id = (int) $item->get_meta('_twwt_variation_id', true);
        }
        if ( ! $variation_id ) { continue; }

        $seats = $item->get_meta('Seat(s)', true);
        $booked_seats = array_filter(array_map('trim', explode(',', (string) $seats)));

        foreach ($booked_seats as $seat) {
            update_post_meta($variation_id, 'perma_booked_seat_' . $seat, time());
            // Clean up the corresponding temp booking now that it's permanent
            delete_post_meta($variation_id, 'temp_booked_seat_' . $seat);
        }

        // Update WC stock to reflect the newly booked seats
        if ( ! empty( $booked_seats ) ) {
            twwt_recalculate_variation_stock( $variation_id );
        }
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
   if ( empty( $_POST['screen_name'] ) ) {
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
    $screen_name = get_post_meta( $order->get_id(), '_screen_name', true );
    if ( $screen_name ) echo '<p><strong>Screen Name:</strong> ' . esc_html( $screen_name ) . '</p>';
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


add_action('init', 'twwt_select_winner');
function twwt_select_winner(){
	// 1) When admin clicks "Select Winner"
    if ( isset($_GET['myaction']) && $_GET['myaction'] === "selectwinner" ) {

        if ( ! current_user_can('manage_woocommerce') || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'twwt_select_winner') ) {
            wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
        }

        $product_id = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
        $order_id   = isset($_GET['oid']) ? intval($_GET['oid']) : 0;
        $seat       = isset($_GET['seat']) ? sanitize_text_field($_GET['seat']) : '';

        if ( ! $product_id || ! $order_id ) {
            exit;
        }

        $current_timestamp  = time();
        $winner_selected_at = date( 'Y-m-d H:i:s', $current_timestamp );

        $go_live_raw = get_post_meta( $product_id, 'tww_event_s_date', true );
        $go_live_ts  = $go_live_raw ? strtotime( $go_live_raw ) : 0;

        $delay_seconds = 0;

        if ( $go_live_ts ) {
            $diff = $go_live_ts - $current_timestamp;

            if ( $diff > 3600 ) {
                $delay_seconds = 2 * 3600;
            }
        }
        $winner_notification_ts = $current_timestamp + $delay_seconds;
        $winner_notification_at = date( 'Y-m-d H:i:s', $winner_notification_ts );

        update_post_meta( $product_id, 'tww_winner_id',          $order_id );
        update_post_meta( $product_id, 'tww_winner_seat_id',     $seat );
        update_post_meta( $product_id, 'tww_winner_s_date',      $winner_selected_at );
        update_post_meta( $product_id, 'tww_winner_n_date',      $winner_notification_at );
        update_post_meta( $product_id, 'tww_winner_n_send',      0 );
        update_post_meta( $product_id, 'tww_winner_form_id',     md5( mt_rand() ) );
        update_post_meta( $product_id, 'tww_winner_form_submit', 0 );

        exit;
    }
	else if ( isset($_GET['myaction']) && $_GET['myaction'] === "zoomnotification" ) {

        if ( ! current_user_can('manage_woocommerce') || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'twwt_zoom_notification') ) {
            wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
        }

        $event_url      = isset($_GET['evn']) ? esc_url_raw($_GET['evn']) : '';
        $event_password = isset($_GET['evp']) ? sanitize_text_field($_GET['evp']) : '';
        $event_time_raw = isset($_GET['evd']) ? sanitize_text_field($_GET['evd']) : ''; // e.g. 2025-11-26 19:50
        $product_id     = isset($_GET['pid']) ? intval($_GET['pid']) : 0;

        if ( ! $product_id || ! $event_url || ! $event_time_raw ) {
            exit;
        }

        $event_time_string = $event_time_raw . ':00';

        if ( function_exists( 'wp_timezone' ) ) {
            $tz = wp_timezone();
        } else {
            $tz_string = get_option( 'timezone_string' );
            $tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
        }

        try {
            $dt = new DateTime( $event_time_string, $tz );
        } catch ( Exception $e ) {
            exit;
        }

        $event_ts = $dt->getTimestamp();

        $formattedDatem = wp_date( 'F j, Y, g:i A', $event_ts, $tz );
        $event_created  = get_post_meta( $product_id, 'event_created', true );

        if ( $event_created != 1 ) {

            $event_time_m = $formattedDatem . ' (' . get_option( 'timezone_string' ) . ')';

            $event = twwt_send_zoom_notification( $product_id, $event_url, $event_time_m, $event_password );

            update_post_meta( $product_id, 'event_created',   1 );
            update_post_meta( $product_id, '_event_name',     $event_url );
            update_post_meta( $product_id, '_event_password', $event_password );

            $event_start_at_ts  = $event_ts;
            $event_notify_ts    = $event_ts - 10 * 60;

            $event_start_at     = wp_date( 'Y-m-d H:i:s', $event_start_at_ts, $tz );
            $event_notification = wp_date( 'Y-m-d H:i:s', $event_notify_ts,   $tz );

            update_post_meta( $product_id, 'tww_event_s_date', $event_start_at );
            update_post_meta( $product_id, 'tww_event_n_date', $event_notification );
            update_post_meta( $product_id, 'tww_event_n_send', 0 );
        }

        exit;
    }

	else if(@$_GET['myaction']=="remove-webinar"){
		$rdata = array();
		if ( is_user_logged_in() && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'twwt_remove_webinar') ) {
			$product_id = intval($_GET['pid']);
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
		if ( ! current_user_can('manage_woocommerce') || ! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'twwt_export_csv') ) {
			wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
		}
		twwt_get_csv(intval($_GET['pid']));
		exit;
	}
}


add_filter( 'cron_schedules', function ( $schedules ) {
   $schedules['twwt_per_one_minute'] = array(
       'interval' => 600,
       'display' => __( 'Every 10 minutes' )
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
}

add_action( 'twwt_cron_hook', 'twwt_send_msg_winner' );

if (!wp_next_scheduled('twwt_cron_hook1')) {
    wp_schedule_event(time(), 'twwt_per_twenty_minute', 'twwt_cron_hook1');
}

add_action('twwt_cron_hook1', 'twwt_send_np_notification');

add_action( 'init', 'tww_mycall' );
function tww_mycall(){
	if( isset($_GET['m']) && $_GET['m']==1 ){
		if ( ! current_user_can('manage_options') ) {
			wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
		}
		twwt_send_msg_winner();
		twwt_send_np_notification();
	}
}
function twwt_send_msg_winner() {
    global $wpdb;

    $current_timestamp = time();

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
            $winner_notification_ts = $current_timestamp;
        }

        $time_diff = $winner_notification_ts - $current_timestamp;

        if ( $time_diff > 0 ) {
            continue;
        }

        $order_id = $winner_id;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            update_post_meta( $product_id, 'tww_winner_n_send', 1 );
            continue;
        }

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

        twwt_email_send( $email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link );

        $settings   = get_option( 'twwt_woo_settings' );
        $smscontent = $settings['sms_wwinner_notification'];
        $smscontent = str_replace('%first_name%',   $first_name,   $smscontent);
        $smscontent = str_replace('%product_name%', $product_name, $smscontent);
        $smscontent = str_replace('%screen_name%',  $screen_name,  $smscontent);
        $twilio_msg = $smscontent;
        wp_twilio_sms($phone, $twilio_msg);

        if ( ! empty( $settings['winner_noto_others'] ) && $settings['winner_noto_others'] == 1 ) {
            twwt_send_msg_winnerselected($product_id);
        }
        update_post_meta($product_id, 'tww_winner_n_send', 1);
    }
}



function twwt_send_msg_winnerselected( $product_id ) {
    global $wpdb;
    $winner_order_id = get_post_meta( $product_id, 'tww_winner_id', true );
    if ( ! $winner_order_id ) {
        return;
    }

    $winner_order = wc_get_order( $winner_order_id );
    if ( ! $winner_order ) {
        return;
    }

    $winner_user = $winner_order->get_user();

    $screen_name = get_post_meta( $winner_order_id, '_screen_name', true );
    if ( empty( $screen_name ) ) {
        if ( $winner_user && ! empty( $winner_user->display_name ) ) {
            $screen_name = $winner_user->display_name;
        } else {
            $screen_name = $winner_order->get_billing_first_name();
        }
    }

    $product_id = intval($product_id);
    $order_ids = twwt_get_paid_order_ids_for_product( $product_id );

    if ( empty( $order_ids ) ) {
        return;
    }

    $product_name    = get_the_title( $product_id );
    $settings        = get_option( 'twwt_woo_settings' );
    $sms_template    = isset( $settings['sms_winner_noti_others'] ) ? $settings['sms_winner_noti_others'] : '';

    $notified_users  = array();
    $notified_emails = array();
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

        if ( $user && $winner_user && $user->ID === $winner_user->ID ) {
            continue;
        }

        if ( $user && isset( $notified_users[ $user->ID ] ) ) {
            continue;
        }
        if ( ! $user && $email && isset( $notified_emails[ $email ] ) ) {
            continue;
        }

        if ( $user ) {
            $notified_users[ $user->ID ] = true;
        } elseif ( $email ) {
            $notified_emails[ $email ] = true;
        }

        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();

        // Send email
        try {
            twwt_email_winner_send(
                $email,
                $product_name,
                $first_name,
                $last_name,
                $screen_name,
                $phone
            );
        } catch ( \Exception $e ) {
            error_log( '[TWWT] Winner email failed for order ' . $order_id . ': ' . $e->getMessage() );
        }

        // Send SMS
        if ( $phone && $sms_template ) {
            $sms_content = str_replace(
                array( '%first_name%', '%product_name%', '%screen_name%' ),
                array( $first_name, $product_name, $screen_name ),
                $sms_template
            );

            try {
                wp_twilio_sms( $phone, $sms_content );
            } catch ( \Exception $e ) {
                error_log( '[TWWT] Winner SMS failed for order ' . $order_id . ': ' . $e->getMessage() );
            }
        }
    }
}


function twwt_email_send($email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link=""){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = isset($settings['wwinner_notification_sub']) ? $settings['wwinner_notification_sub'] : '';
	$emailcontent = isset($settings['wwinner_notification']) ? $settings['wwinner_notification'] : '';

	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
	$emailsubject = str_replace('%screen_name%', $screen_name, $emailsubject);
    $emailsubject = str_replace('%phone%', $phone, $emailsubject);
	$emailcontent = str_replace('%first_name%', esc_html($first_name), $emailcontent);
    $emailcontent = str_replace('%product_name%', esc_html($product_name), $emailcontent);
    $emailcontent = str_replace('%last_name%', esc_html($last_name), $emailcontent);
	$emailcontent = str_replace('%screen_name%', esc_html($screen_name), $emailcontent);
    $emailcontent = str_replace('%phone%', esc_html($phone), $emailcontent);
	$email_heading = $emailsubject;
	ob_start();
	wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
	echo wpautop($emailcontent);
	wc_get_template( 'emails/email-footer.php' );
	$message = ob_get_clean();
	$subject = $email_heading;
	$admin_foot_notes = "<p><small>Originally sent to {$first_name}</small></p>";
	return $mailer->send( $email, $subject, $message);
	//return true;
}


function twwt_email_winner_send($email, $product_name, $first_name, $last_name, $screen_name, $phone, $form_link=""){
	global $woocommerce;
	$mailer = $woocommerce->mailer();
	$settings = get_option( 'twwt_woo_settings' );
	$emailsubject = isset($settings['winner_noti_others_sub']) ? $settings['winner_noti_others_sub'] : '';
	$emailcontent = isset($settings['winner_noti_others']) ? $settings['winner_noti_others'] : '';

	$emailsubject = str_replace('%first_name%', $first_name, $emailsubject);
    $emailsubject = str_replace('%product_name%', $product_name, $emailsubject);
    $emailsubject = str_replace('%last_name%', $last_name, $emailsubject);
	$emailsubject = str_replace('%screen_name%', $screen_name, $emailsubject);
    $emailsubject = str_replace('%phone%', $phone, $emailsubject);
	$emailcontent = str_replace('%first_name%', esc_html($first_name), $emailcontent);
    $emailcontent = str_replace('%product_name%', esc_html($product_name), $emailcontent);
    $emailcontent = str_replace('%last_name%', esc_html($last_name), $emailcontent);
	$emailcontent = str_replace('%screen_name%', esc_html($screen_name), $emailcontent);
    $emailcontent = str_replace('%phone%', esc_html($phone), $emailcontent);
	
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

add_action( 'woocommerce_register_form_start', 'review_raffle_custom_register_fields' );
function review_raffle_custom_register_fields() {
    ?>

    <p class="form-row form-row-wide">
        <label for="reg_billing_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_first_name" id="reg_billing_first_name"
            value="<?php echo ! empty( $_POST['billing_first_name'] ) ? esc_attr( $_POST['billing_first_name'] ) : ''; ?>" required/>
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_billing_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="billing_last_name" id="reg_billing_last_name"
            value="<?php echo ! empty( $_POST['billing_last_name'] ) ? esc_attr( $_POST['billing_last_name'] ) : ''; ?>" required/>
    </p>

    <p class="form-row form-row-wide">
        <label for="reg_screen_name"><?php esc_html_e( 'Screen name', 'woocommerce' ); ?> <span class="required">*</span></label>
        <input type="text" class="input-text" name="screen_name" id="reg_screen_name"
            value="<?php echo ! empty( $_POST['screen_name'] ) ? esc_attr( $_POST['screen_name'] ) : ''; ?>" required/>
    </p>

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
    <p class="form-row form-row-wide">
        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
            <input type="checkbox" name="twwy_opt_notification" value="1"
                <?php checked( ! empty( $_POST['twwy_opt_notification'] ) ); ?> />
            <span><?php esc_html_e( 'Sign up to receive notifications when a new webinar is available.', 'woocommerce' ); ?></span>
        </label>
    </p>
<?php
}

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


add_action( 'woocommerce_save_account_details', 'save_phone_number_field_value_on_account_page' );
function save_phone_number_field_value_on_account_page( $user_id ) {
    if ( isset( $_POST['account_phone_number'] ) ) {
        update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $_POST['account_phone_number'] ) );
    }
	if ( isset( $_POST['account_screen_name'] ) ) {
        update_user_meta( $user_id, 'screen_name', sanitize_text_field( $_POST['account_screen_name'] ) );
    }
    if ( isset( $_POST['account_twwy_opt_notification'] ) && $_POST['account_twwy_opt_notification'] === '1' ) {
        update_user_meta( $user_id, 'twwy_opt_notification', '1' );
    } else {
        update_user_meta( $user_id, 'twwy_opt_notification', '0' );
    }
}

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

add_action( 'personal_options_update', 'save_job_title_field' );
add_action( 'edit_user_profile_update', 'save_job_title_field' );
function save_job_title_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }
    update_user_meta( $user_id, 'screen_name', sanitize_text_field( $_POST['screen_name'] ) );
}

add_action('show_user_profile', 'twwy_add_optin_field_admin');
add_action('edit_user_profile', 'twwy_add_optin_field_admin');
function twwy_add_optin_field_admin( $user ) {
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
add_action('personal_options_update', 'twwy_save_optin_field_admin');
add_action('edit_user_profile_update', 'twwy_save_optin_field_admin');
function twwy_save_optin_field_admin( $user_id ) {
    if ( ! current_user_can('edit_user', $user_id) ) {
        return false;
    }

    if ( isset($_POST['twwy_opt_notification']) && $_POST['twwy_opt_notification'] == '1' ) {
        update_user_meta( $user_id, 'twwy_opt_notification', '1' );
    } else {
        update_user_meta( $user_id, 'twwy_opt_notification', '0' );
    }
}


require_once('twwt-admin-settings.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-product-notification.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-order-csv.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-myaccount-videos.php');
require_once(plugin_dir_path(__FILE__) . 'twwt-admin-add-webinar-simple.php');

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
    
}

}
add_action('admin_init', 'valid_files_licence'); 


$taboptions = get_option( 'twwt_woo_settings' );
if(is_array($taboptions) && !empty($taboptions['producttab']) && $taboptions['producttab']==1){
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
		echo '<section class="tab-participant" data-id="'.intval($post->ID).'"></section>';
	}
}


add_action('init', 'twwt_get_participant');
function twwt_get_participant(){
	if(isset($_GET['participant'])){
		if ( ! current_user_can('manage_woocommerce') ) {
			wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
		}
		require plugin_dir_path( __FILE__ ) . 'ticket-participant.php';
		exit;
	}
}
}
add_action('init', 'twwt_get_availability_check');
function twwt_get_availability_check(){
	if(isset($_GET['availabilitycheck'])){
		$variation_id = intval($_GET['variationid']);
		$seat = sanitize_text_field($_GET['seat']);
		$availability = twwt_woo_get_availability($variation_id, $seat);
		if($availability){
			 echo 'Selected seat "' . esc_html($seat) . '" is not available for booking.';
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
    $product_id = intval($product_id);
    $order_ids = twwt_get_paid_order_ids_for_product( $product_id );
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

function winner_page_shortcode( $atts ) {
    ob_start();
    try {
    $product_id = isset( $_GET['pid'] ) ? intval( $_GET['pid'] ) : 0;

    if ( empty( $product_id ) ) {
        echo '<p>No product selected.</p>';
        return ob_get_clean();
    }

    $product = wc_get_product( $product_id );

    // Determine if winner selection should be enabled:
    // Use event_created meta (set when zoom notification is sent) as the reliable indicator
    $event_created    = get_post_meta( $product_id, 'event_created', true ) == 1;
    $allow_selection  = $event_created || ( $product && ! $product->is_in_stock() );

    // Read color settings with fallback defaults
    $twwt_settings    = get_option( 'twwt_woo_settings', array() );
    $clr_primary      = ! empty( $twwt_settings['winner_primary_color'] )   ? $twwt_settings['winner_primary_color']   : '#d63638';
    $clr_hover        = ! empty( $twwt_settings['winner_primary_hover'] )   ? $twwt_settings['winner_primary_hover']   : '#b32d2f';
    $clr_table_hdr    = ! empty( $twwt_settings['winner_table_header_bg'] ) ? $twwt_settings['winner_table_header_bg'] : '#f5f5f5';
    $clr_btn_text     = ! empty( $twwt_settings['winner_button_text'] )     ? $twwt_settings['winner_button_text']     : '#ffffff';

    {
        $winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
        $winner_seat_id = get_post_meta( $product_id, 'tww_winner_seat_id', true );
        $order_ids = twwt_get_paid_order_ids_for_product( $product_id );
    ?>
        <style>
        .twwt-winner-wrap { max-width: 960px; margin: 0 auto; padding: 0 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .twwt-winner-title { text-align: center; margin: 30px 0 24px; font-size: 1.6em; font-weight: 600; }
        .twwt-winner-grid { display: flex; flex-wrap: wrap; gap: 30px; }
        .twwt-winner-col { flex: 1; min-width: 300px; }
        .twwt-winner-col h4 { text-align: center; margin-bottom: 12px; font-size: 1.15em; font-weight: 600; }
        .twwt-winner-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .twwt-winner-table th,
        .twwt-winner-table td { border: 1px solid #ddd; padding: 10px 14px; text-align: left; }
        .twwt-winner-table th { background: <?php echo esc_attr( $clr_table_hdr ); ?>; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.03em; }
        .twwt-winner-table tbody tr:nth-child(even) { background: #fafafa; }
        .twwt-winner-table tbody tr:hover { background: #f0f0f0; }
        .twwt-winner-actions { text-align: center; margin-top: 16px; }
        .twwt-btn-winner { display: inline-block; background: <?php echo esc_attr( $clr_primary ); ?>; color: <?php echo esc_attr( $clr_btn_text ); ?>; border: none; padding: 12px 28px; font-size: 1em; font-weight: 600; border-radius: 4px; cursor: pointer; text-decoration: none; }
        .twwt-btn-winner:hover { background: <?php echo esc_attr( $clr_hover ); ?>; color: <?php echo esc_attr( $clr_btn_text ); ?>; }
        .twwt-btn-winner .dashicons { vertical-align: middle; margin-right: 4px; }
        .twwt-alert { background: #fcebea; border: 1px solid <?php echo esc_attr( $clr_primary ); ?>; color: #8b1a1c; padding: 16px 20px; border-radius: 4px; text-align: center; margin-top: 16px; }
        .twwt-alert a { display: inline-block; margin-top: 8px; background: <?php echo esc_attr( $clr_primary ); ?>; color: <?php echo esc_attr( $clr_btn_text ); ?>; padding: 8px 20px; border-radius: 4px; text-decoration: none; font-weight: 600; }
        .twwt-alert a:hover { background: <?php echo esc_attr( $clr_hover ); ?>; color: <?php echo esc_attr( $clr_btn_text ); ?>; }
        .twwt-generator { margin-top: 10px; overflow: hidden; }
        .twwt-generator iframe { border: none; transform: scale(1.2); transform-origin: top left; width: 84%; }
        .twwt-radio-cell { text-align: center; width: 56px; }
        .twwt-radio-cell input[type="radio"] { width: 18px; height: 18px; cursor: pointer; }
        @media (max-width: 700px) { .twwt-winner-grid { flex-direction: column; } }
        </style>
        <div class="twwt-winner-wrap">
            <h3 class="twwt-winner-title"><?php echo esc_html( get_the_title( $product_id ) ); ?></h3>
            <?php if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) { ?>
            <div class="twwt-winner-grid">
                <div class="twwt-winner-col">
                    <h4>Attendee</h4>
                    <form method="post" id="tw_mywinnerform">
                        <table class="twwt-winner-table" id="mytable">
                            <thead>
                                <tr>
                                    <?php if ( $allow_selection ) { ?>
                                        <th class="twwt-radio-cell">&nbsp;</th>
                                    <?php } ?>
                                    <th>Seat Number</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $cnn = 0;
                                foreach ( $order_ids as $order_id ) {
                                    $order = wc_get_order( $order_id );
                                    if ( ! $order ) { continue; }
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
                         <?php if( $allow_selection ){ ?>
                         <td class="twwt-radio-cell">
                         <?php if($winner_id>0){ if($winner_seat_id==$seat){ echo '<i class="dashicons dashicons-awards"></i>';}}else{?><input type="radio" name="rbtnseats" value="<?php echo $seat;?>" data-id="<?php echo $order_id;?>" class="tw_rbtn" <?php if($cnn==1){echo 'required="required"';}?> /><?php } ?></td>
                         <?php } ?>
                         <td><?php echo $seat;?></td>
                         <td id="wsnm_<?php echo $seat;?>"><?php echo esc_html($full_name);?></td>
                         </tr>
                         <?php
					 }
					}
				}
				?>
                </tbody>
                </table>
                <?php if( $allow_selection ){?>
                <div class="twwt-winner-actions">
                <?php if($winner_id>0){ }else{ ?>
                <input type="hidden" name="orderid" id="tw_orderid" value="0" />
                <input type="hidden" name="pid" id="tw_pid" value="<?php echo $product_id;?>" />
                <input type="hidden" id="tw_winner_nonce" value="<?php echo wp_create_nonce('twwt_select_winner'); ?>" />
                <button type="submit" class="twwt-btn-winner" id="btn_select_winnerf"><i class="dashicons dashicons-awards"></i> <span>Select a Winner</span></button>
                <?php } ?>
                </div>
                <?php }else{ ?>
                <div class="twwt-alert"><p>Seats are still available in this webinar.</p> <p><a href="<?php echo esc_url(get_permalink($product_id));?>">Go back</a></p></div>
				<?php }?>
                </form>
                </div>
                <div class="twwt-winner-col">
                <?php if( $allow_selection ){?>
                <h4>Generate Number</h4>
                <div class="twwt-generator">
                <iframe src="https://www.random.org/widgets/integers/iframe.php?title=True+Random+Number+Generator&amp;buttontxt=Generate&amp;width=360&amp;height=360&amp;border=on&amp;bgcolor=%23FFFFFF&amp;txtcolor=%23777777&amp;altbgcolor=%23e42c2c&amp;alttxtcolor=%23FFFFFF&amp;defaultmin=1&amp;defaultmax=<?php echo $cnn; ?>&amp;fixed=off" frameborder="0" width="100%" height="380" scrolling="auto" longdesc="https://www.random.org/integers/">
The numbers generated by this widget come from RANDOM.ORG's true random number generator.
</iframe>
</div>
<?php } ?>
</div>
</div>
</div>
<?php
			}
		}
    } catch ( \Throwable $e ) {
        if ( current_user_can( 'manage_options' ) ) {
            echo '<div style="color:red;padding:20px;border:1px solid red;margin:20px 0;">';
            echo '<strong>Winner page error:</strong> ' . esc_html( $e->getMessage() );
            echo '<br><small>' . esc_html( $e->getFile() ) . ':' . $e->getLine() . '</small>';
            echo '</div>';
        }
        error_log( '[TWWT] Winner page error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
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

function twwt_get_active_sms_provider() {
    $opts = get_option('twwt_woo_settings', []);
    return isset($opts['sms_provider']) ? $opts['sms_provider'] : 'twilio';
}


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


add_action('show_user_profile', 'twwt_show_sms_provider_user_panel');
add_action('edit_user_profile', 'twwt_show_sms_provider_user_panel');
function twwt_show_sms_provider_user_panel($user) {
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

            $raw_phone = get_user_meta($user->ID, 'billing_phone', true);
            $ot_phone  = get_user_meta($user->ID, 'ottertext_phone', true);
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

function twwt_winner_schedule_metabox_cb( $post ) {

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

add_action('wp_dashboard_setup', 'twwt_register_notification_widget');
function twwt_register_notification_widget() {
    wp_add_dashboard_widget(
        'twwt_notification_status_widget',
        '📣 Product Notification Status',
        'twwt_display_notification_widget'
    );
}

function twwt_display_notification_widget() {
    $settings = get_option('twwt_woo_settings', array());
    $mode = isset($settings['notification_mode']) ? $settings['notification_mode'] : 'immediate';

    if ( function_exists('wp_timezone') ) {
        $tz = wp_timezone();
    } else {
        $tz_string = get_option('timezone_string');
        $tz = new DateTimeZone( $tz_string ? $tz_string : 'UTC' );
    }
    $fmt_ts = function($ts) use ($tz) {
        if (!$ts) return '—';
        $d = new DateTime('@' . intval($ts));
        $d->setTimezone($tz);
        return $d->format('Y-m-d H:i:s');
    };

    $opted_in_count = 0;
    $total_customers = 0;
    $user_count_args = array(
        'role' => 'customer',
        'fields' => 'ID',
        'number' => 1,
    );
    $user_query_total = new WP_User_Query(array_merge($user_count_args, array('number' => 1)));
    $total_customers = (int) $user_query_total->get_total();

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

                $notified_count = 0;

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

        $next_ts = wp_next_scheduled('twwt_daily_batch_hook');
        $last_sent = get_option('twwt_last_batch_sent', 0);
        echo '<p><strong>⏱️ Next scheduled run:</strong> ' . esc_html($fmt_ts($next_ts)) . ' (site timezone)</p>';
        if ($last_sent) {
            echo '<p><strong>✅ Last batch sent:</strong> ' . esc_html($fmt_ts($last_sent)) . '</p>';
        }
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
    $posted = array(
        'variable_manage_stock' => isset($_POST['variable_manage_stock'][$i]) ? $_POST['variable_manage_stock'][$i] : null,
        'variable_stock'        => isset($_POST['variable_stock'][$i]) ? $_POST['variable_stock'][$i] : null,
        'variable_stock_status' => isset($_POST['variable_stock_status'][$i]) ? $_POST['variable_stock_status'][$i] : null,
    );
    add_action('shutdown', function() use ($variation_id) {
        $after = array(
            '_stock'       => get_post_meta($variation_id, '_stock', true),
            '_manage_stock'=> get_post_meta($variation_id, '_manage_stock', true),
            '_stock_status'=> get_post_meta($variation_id, '_stock_status', true),
        );

    });
}
/* CRON HANDLES */
add_action( 'init', 'mycronhandle' );
function mycronhandle(){
    if ( ! isset($_GET['mycron']) ) {
        return;
    }
    if ( ! current_user_can('manage_options') ) {
        wp_die('Unauthorized request.', 'Forbidden', array('response' => 403));
    }
    if($_GET['mycron']=="winner"){
         twwt_send_msg_winner();
         exit;
        } else if($_GET['mycron']=="notification"){
            twwt_send_np_notification();
            exit;
        } else if($_GET['mycron']=="ottertext_batch_sync"){
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