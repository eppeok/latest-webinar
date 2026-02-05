<?php
function twwt_custom_endpoints() {
    add_rewrite_endpoint( 'webinars-videos', EP_ROOT | EP_PAGES );
}

add_action( 'init', 'twwt_custom_endpoints' );

// so you can use is_wc_endpoint_url( 'webinars' )
add_filter( 'woocommerce_get_query_vars', 'twwt_custom_woocommerce_query_vars', 0 );

function twwt_custom_woocommerce_query_vars( $vars ) {
	$vars['webinars'] = 'webinars';
	return $vars;
}

function twwt_custom_flush_rewrite_rules() {
    flush_rewrite_rules();
}

add_action( 'after_switch_theme', 'twwt_custom_flush_rewrite_rules' );

function twwt_custom_my_account_menu_items( $items ) {

	$new_item = array( 'webinars' => __( 'Raffle Videos', 'woocommerce' ) );
	
    // add item in 2nd place
	$items = array_slice($items, 0, 2, TRUE) + $new_item + array_slice($items, 2, NULL, TRUE);
	
	//$items['orders'] = 'Booking History';
    return $items;

}

add_filter( 'woocommerce_account_menu_items', 'twwt_custom_my_account_menu_items' );

function twwt_custom_endpoint_content() {
    wc_get_template( 'my-account-webinars.php', array(), '', TWWT_PLUGIN_DIRPATH);
	
}

add_action( 'woocommerce_account_webinars_endpoint', 'twwt_custom_endpoint_content' );