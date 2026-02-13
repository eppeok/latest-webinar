<?php
add_action( 'add_meta_boxes', 'twwt_add_post_meta_boxes' );

function twwt_add_post_meta_boxes() {
	add_meta_box(
		'twwt-wc-product-customer-list',
		 __( 'Customer List', 'woocommerce' ),
		'twwt_add_post_meta_boxes_callback',
		'product',
		'normal',
		'default'
	);
	add_meta_box(
		'twwt-wc-product-seat',
		 __( 'Raffle Seat Settings', 'woocommerce' ),
		'twwt_add_post_meta_boxes_seat_callback',
		'product',
		'side',
		'default'
	);
}

function twwt_add_post_meta_boxes_seat_callback(){
	global $post;

	wp_nonce_field( basename( __FILE__ ), 'product_fields' );
	$woo_seat_show = get_post_meta( $post->ID, 'woo_seat_show', true );
	$selected = "";
	if($woo_seat_show==1){
		$selected = "selected";
		
	}
	else{
		echo '<style>#twwt-wc-product-customer-list{display:none;}</style>';
	}

	// Output the field
	/*echo '<select name="woo_seat_show"class="widefat">';
	echo '<option value="0">Disable</option>';
	echo '<option value="1" '.$selected.'>Enable</option>';
	echo '</select>';*/
	//modified code
	echo '<p>';
	echo '<label for="woo_seat_show">Status</label><br>';
	echo '<select name="woo_seat_show" id="woo_seat_show" class="widefat">';
	echo '<option value="0">Disable</option>';
	echo '<option value="1" '.$selected.'>Enable</option>';
	echo '</select>';
	echo '</p>';
	if(!$woo_seat_show){
	    echo '<style>#acf-group_6437b3e5b86d6{display:none;}</style>';
	}
}

function twwt_save_product_seat_meta( $post_id, $post ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	if ( ! isset( $_POST['woo_seat_show'] ) || ! wp_verify_nonce( $_POST['product_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	
  if ( wp_is_post_autosave( $post_id ) ){
	  return 'autosave';
  }

  if ( wp_is_post_revision( $post_id ) ){
      return 'revision';
  }
	$key = 'woo_seat_show';
	$value = $_POST['woo_seat_show'];
	if ( get_post_meta( $post_id, $key, true ) ) {
		update_post_meta( $post_id, $key, $value );
	} else {
		add_post_meta( $post_id, $key, $value);
	}
	
	if ( ! $value ) {
		delete_post_meta( $post_id, $key );
	}
}
add_action( 'save_post', 'twwt_save_product_seat_meta', 1, 2 );

function twwt_add_post_meta_boxes_callback() {
	global $post;
	global $wpdb;
	
	$options = get_option( 'twwt_woo_settings' );
	$winner_selection = $options['winner'];
	
	$product_id = $post->ID;
	$product = wc_get_product( $product_id );
	$random_arr = array();
	
	$order_ids = twwt_get_paid_order_ids_for_product( $product_id );
	 
	// Print array on screen
	if(!empty($order_ids)){
		$winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
		?>
		<style>
		.highlighted-row {
			background-color: #d1e4dd !important;
		}
		</style>
        <p style="text-align:right"><a target="_blank" href="<?php echo esc_url( wp_nonce_url( home_url('?myaction=export_csv&pid=' . intval($product_id)), 'twwt_export_csv' ) ); ?>" class="button button-primary">Export</a></p>
        <table style="width:100%" class="wp-list-table widefat fixed striped table-view-list pages">
        	<thead>
            	<tr><th width="56">Order ID</th>
                <th>Screen Name</th>
                <th>Fist Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th width="100">Phone</th>
                <th width="100">Seats</th>
                <th width="100">Zoom URL Sent</th>
                </tr>
            </thead>
        <?php
		foreach($order_ids as $order_id){
			$random_arr[] = $order_id;
			 $order = wc_get_order( $order_id );
			 if(!twwy_has_refunds_v2($order_id)){
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
			 ?>
             <tr <?php if($winner_id==$order_id){echo 'bgcolor="#d1e4dd"'; echo 'class="highlighted-row"';}?>>
             	<td><a target="_blank" href="post.php?post=<?php echo $order_id;?>&action=edit"><?php echo $order_id;?></a></td>
                <td><?php 
				
				$screen_name = get_post_meta( $order_id, '_screen_name', true );
				if($screen_name!=""){
					echo $screen_name;
				}
				else{
					$cuser = $order->get_user();
					if(!empty($cuser->display_name)){
						echo $cuser->display_name;
					}
					else{
						echo $order->get_billing_first_name();
					}
				}
				if($winner_id==$order_id){echo '<i class="dashicons dashicons-awards"></i>';}
				?></td>
                <td><?php echo $order->get_billing_first_name();?></td>
                <td><?php echo $order->get_billing_last_name();?></td>
                <td><?php echo $order->get_billing_email();?></td>
                <td><?php echo $order->get_billing_phone();?></td>
                <td><?php echo $seats;?></td>
                <td>
                <?php
				$sent_zoom_link = get_post_meta($order_id, '_sent_zoom_link_' . $product_id, true);
				if($sent_zoom_link==1){
					echo 'Sent';
				}
				else{
					echo 'Pending';
				}
				?>
                </td>
             </tr>
             <?php
			 }
			 
		}
		?>
        </table>
        <div class="notice" id="tww_notice">
        </div>
        
        <?php 
		
		##echo '<pre>';
		##print_r( get_post_meta( $product_id, 'event_data', true ) );
		##echo '</pre>';
		$event_created = get_post_meta( $product_id, 'event_created', true ) == 1;
		if($winner_selection==1 || $event_created){
		$winner_selected_at = get_post_meta( $product_id, 'tww_winner_s_date', true );
		$winner_notification_at = get_post_meta( $product_id, 'tww_winner_n_date', true );


		shuffle($random_arr);
		?>
        <?php if($winner_id>0){
			echo '<p><strong>Winner selected at:</strong> '.$winner_selected_at.'<br>';
			echo '<strong>Notification sent at:</strong> '.$winner_notification_at.'</p>';
		}
		else{ ?>
        <?php
			$page_slug = 'winner';
			$page = get_page_by_path($page_slug);
			$Winnerpermalink = get_permalink($page->ID);
		?>
        <p>
        <a href="<?php echo esc_url( add_query_arg( 'pid', $product_id, $Winnerpermalink ) ); ?>" target="_blank" class="button button-primary">Select Attendee</a>
        </p>
        <script>
		function SelectWinner(){
			jQuery("#btn_select_winner").html('Please wait...').attr('disabled', 'disabled');
			jQuery.get('<?php echo home_url('/');?>?myaction=selectwinner&pid=<?php echo $product_id;?>&oid=<?php echo $random_arr[0];?>', function(data){
				location.reload();
			});
		}
		</script>

        <?php }
		}
		?>
        <?php
		{
			if($event_created){
			}
			else{
				 if(!$product->is_in_stock()){	
				?>
                <h3>Start Webinar</h3>
                <p style="color:#666;">Go to <a href="https://zoom.us/" target="_blank">zoom</a>, start zoom meeting, copy zoom link and paste it here.</p>
				<p><input type="url" size="100" id="tww_event_name" placeholder="Event URL (Zoom)" /></p>
                <p><input type="text" size="30" id="tww_event_password" placeholder="Event Passcode (Zoom)" /> <small>Optional</small></p>
				<p><input type="text" size="30" id="tww_event_time" placeholder="Starting Time (<?php echo esc_html( get_option('timezone_string') ); ?>)" /> <br /><strong>Timezone:</strong> <?php echo get_option('timezone_string');?></p>
				
				<p>
				<button type="button" class="button button-primary" onclick="SendCustomer();" id="btn_send_customer">Send Notification</button>
				</p>
				<?php 
					wp_enqueue_style( 'twwt_woo_date', plugins_url('asset/css/jquery-ui.min.css',__FILE__ ), array(), TWWT_VERSION );
					wp_enqueue_style( 'twwt_woo_time', plugins_url('asset/css/jquery-ui-timepicker-addon.min.css',__FILE__ ), array(), TWWT_VERSION );
					wp_enqueue_script( 'twwt_woo_timepicker', plugins_url('asset/js/jquery-ui-timepicker-addon.min.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
					 // Get "now" in WordPress timezone
						if ( function_exists( 'wp_timezone' ) ) {
							$tz = wp_timezone();
						} else {
							$tz_string = get_option( 'timezone_string' );
							$tz = $tz_string ? new DateTimeZone( $tz_string ) : new DateTimeZone( 'UTC' );
						}
						$now     = new DateTime( 'now', $tz );
						$now_str = $now->format( 'Y-m-d H:i:s' );
				?>
			   <script>
				jQuery(document).ready(function($) {

					var wpNowString = '<?php echo esc_js( $now_str ); ?>';
					var parts = wpNowString.split(/[- :]/);
					var wpNow = new Date(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]);

					$('#tww_event_time').datetimepicker({
						dateFormat: 'yy-mm-dd',
						controlType: 'select',
						minDate: new Date(wpNow.getTime())
					});
				});
				function SendCustomer(){
					var _n = jQuery('#tww_event_name').val();
					var _t = jQuery('#tww_event_time').val();
					var _p = jQuery('#tww_event_password').val();
					if(_n==""){
						alert('Please enter event url');
						jQuery('#tww_event_name').focus();
						return false;
					}
					if(_t==""){
						alert('Please enter event time');
						jQuery('#tww_event_time').focus();
						return false;
					}
					
					jQuery("#btn_send_customer").html('Sending...').attr('disabled', 'disabled');
					jQuery.get('<?php echo home_url('/');?>?myaction=zoomnotification&pid=<?php echo $product_id;?>&_wpnonce=<?php echo wp_create_nonce('twwt_zoom_notification'); ?>&evn='+encodeURIComponent(_n)+'&evd='+encodeURIComponent(_t)+'&evp='+encodeURIComponent(_p))
					.done(function(data){
						location.reload();
					})
					.fail(function(){
						alert('Failed to send notification. Please check your email/SMS settings and try again.');
						jQuery("#btn_send_customer").html('Send').removeAttr('disabled');
					});
					
				}
				</script>
                <?php
				 }
			}
		}
		?>
        
        <?php
	}
	else{
		echo '<p>No one bought this webinar.</p>';
	}
	?>
    <?php
}