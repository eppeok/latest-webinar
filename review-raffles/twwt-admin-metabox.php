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

	// Nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'product_fields' );
	// Get the location data if it's already been entered
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
	$woo_seat_video = get_post_meta( $post->ID, 'woo_seat_video', true );
	if($woo_seat_show==1){
		echo '<p>';
		echo '<label for="woo_seat_video">Video URL</label><br>';
		echo '<input type="url" placeholder="Video URL" value="'.$woo_seat_video.'" name="woo_seat_video" id="woo_seat_video" class="widefat" >';
		echo '<p>';
	}
	else{
	    echo '<style>#acf-group_6437b3e5b86d6{display:none;}</style>';
	}
}

function twwt_save_product_seat_meta( $post_id, $post ) {
	// Return if the user doesn't have edit permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}
	// Verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times.
	if ( ! isset( $_POST['woo_seat_show'] ) || ! wp_verify_nonce( $_POST['product_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	
	// check autosave
  if ( wp_is_post_autosave( $post_id ) ){
	  return 'autosave';
  }

  //check post revision
  if ( wp_is_post_revision( $post_id ) ){
      return 'revision';
  }
	$key = 'woo_seat_show';
	$value = $_POST['woo_seat_show'];
	if ( get_post_meta( $post_id, $key, true ) ) {
		// If the custom field already has a value, update it.
		update_post_meta( $post_id, $key, $value );
	} else {
		// If the custom field doesn't have a value, add it.
		add_post_meta( $post_id, $key, $value);
	}
	
	update_post_meta( $post_id, 'woo_seat_video', $_POST['woo_seat_video'] );

	if ( ! $value ) {
		// Delete the meta key if there's no value
		delete_post_meta( $post_id, $key );
	}
}
add_action( 'save_post', 'twwt_save_product_seat_meta', 1, 2 );

function twwt_add_post_meta_boxes_callback() {
	global $post;
	global $wpdb;
	
	$options = get_option( 'twwt_woo_settings' );
	$winner_selection = $options['winner'];
	$enable_livestrom = $options['enable_livestrom'];
	
	$product_id = $post->ID;
	$product = wc_get_product( $product_id );
	$random_arr = array();
	
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
		$winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
		?>
		<style>
		.highlighted-row {
			background-color: #d1e4dd !important;
		}
		</style>
        <p style="text-align:right"><a target="_blank" href="<?php echo home_url();?>?myaction=export_csv&pid=<?php echo $product_id;?>" class="button button-primary">Export</a></p>
        <table style="width:100%" class="wp-list-table widefat fixed striped table-view-list pages">
        	<thead>
            	<tr><th width="56">Order ID</th>
                <th>Screen Name</th>
                <th>Fist Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th width="100">Phone</th>
                <th width="100">Seats</th>
                <?php if($enable_livestrom==1){?>
                <th width="100">Livestrom Status</th>
                <?php }else{ ?>
                <th width="100">Zoom URL Sent</th>
                <?php } ?>
                </tr>
            </thead>
        <?php
		foreach($order_ids as $order_id){
			$random_arr[] = $order_id;
			 $order = wc_get_order( $order_id );
			 if(!twwy_has_refunds_v2($order_id)){
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
                <?php if($enable_livestrom==1){ ?>
                <td>
                <?php 
				$sent = get_post_meta( $order_id, '_sent_to_livestrom', true );
				if($sent==1){
					echo 'Sent';
				}
				else if($sent==-1){
					$participant_data = get_post_meta( $order_id, 'participant_data', true );
					echo $participant_data->errors[0]->title;
				}
				else{
					echo 'Pending';
				}
				?>
                </td>
                <?php }else{ ?>
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
                <?php } ?>
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
		if($winner_selection==1){
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
        <a href="<?php echo $Winnerpermalink;?>?pid=<?php echo $product_id;?>" target="_blank" class="button button-primary">Select Attendee</a>
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
		if($enable_livestrom==1){
			$event_data = get_post_meta( $product_id, 'event_data', true );
			if(!empty($event_data)){
			echo '<p><strong>Event Name:</strong> '.$event_data->data->attributes->title.'<br>';
			echo '<strong>Event Time:</strong> '.date("Y-m-d H:i:s", $event_data->included[0]->attributes->estimated_started_at).'</p>';
			}
			if(get_post_meta( $product_id, 'event_created', true )==1){
			}
			else{
 if(!$product->is_in_stock()){				
				?>
				<h3>Send Webinar to LiveStorm.</h3>
				<p><input type="text" size="50" id="tww_event_name" placeholder="Event Name" /></p>
				<p><input type="text" size="50" id="tww_event_time" placeholder="Starting Time" /> <br /><strong>Timezone:</strong> <?php echo get_option('timezone_string');?></p>
				
				<p>
				<button type="button" class="button button-primary" onclick="SendCustomer();" id="btn_send_customer">Send Webinar to LiveStorm</button>
				</p>
				<?php 
					wp_enqueue_style( 'twwt_woo_date', plugins_url('asset/css/jquery-ui.min.css',__FILE__ ), array(), TWWT_VERSION );
					wp_enqueue_style( 'twwt_woo_time', plugins_url('asset/css/jquery-ui-timepicker-addon.min.css',__FILE__ ), array(), TWWT_VERSION );
					wp_enqueue_script( 'twwt_woo_timepicker', plugins_url('asset/js/jquery-ui-timepicker-addon.min.js',__FILE__ ), array('jquery'), TWWT_VERSION, true );
				?>
			   <script>
				jQuery(document).ready(function(e) {
					jQuery(document).ready(function(){
						var date = new Date();
						jQuery('#tww_event_time').datetimepicker({ dateFormat: 'yy-mm-dd', controlType: 'select', minDate:new Date(new Date(date).setHours(date.getHours() + 1)) });
					});
				});
				function SendCustomer(){
					var _n = jQuery('#tww_event_name').val();
					var _t = jQuery('#tww_event_time').val();
					if(_n==""){
						alert('Please enter event name');
						jQuery('#tww_event_name').focus();
						return false;
					}
					if(_t==""){
						alert('Please enter event time');
						jQuery('#tww_event_time').focus();
						return false;
					}
					
					
					jQuery("#btn_send_customer").html('Sending...').attr('disabled', 'disabled');
					jQuery.get('<?php echo home_url('/');?>?myaction=createevent&pid=<?php echo $product_id;?>&evn='+encodeURIComponent(_n)+'&evd='+encodeURIComponent(_t), function(data){
						var obj = JSON.parse(data);
						if(obj.errors){
							//alert(obj.errors[0].title);
							jQuery("#tww_notice").html(obj.errors[0].title).removeClass('notice-success').addClass('notice-error');
							jQuery("#btn_send_customer").html('Send Webinar to LiveStorm').removeAttr('disabled');
							return;
						}
						else{
							jQuery("#tww_notice").html('Event successfully created. Registering a participant to the event.').removeClass('notice-error').addClass('notice-success');
							SendCustomerToLiveStrom(<?php echo $product_id;?>);
						}
						///location.reload();
					});
				}
				
			   </script> 
				<?php
 }
			}
		}else{
			if(get_post_meta( $product_id, 'event_created', true )==1){
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
						$now_str = $now->format( 'Y-m-d H:i:s' ); // e.g. 2025-11-26 10:23:58
				?>
			   <script>
				jQuery(document).ready(function($) {

					// "Now" in WordPress timezone, passed from PHP
					var wpNowString = '<?php echo esc_js( $now_str ); ?>'; // "2025-11-26 10:23:58"
					var parts = wpNowString.split(/[- :]/);
					// year, month-1, day, hour, minute, second
					var wpNow = new Date(parts[0], parts[1]-1, parts[2], parts[3], parts[4], parts[5]);

					$('#tww_event_time').datetimepicker({
						dateFormat: 'yy-mm-dd',
						controlType: 'select',
						// min date/time = WP now
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
					jQuery.get('<?php echo home_url('/');?>?myaction=zoomnotification&pid=<?php echo $product_id;?>&evn='+encodeURIComponent(_n)+'&evd='+encodeURIComponent(_t)+'&evp='+encodeURIComponent(_p), function(data){
						location.reload();
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
    <script>
	function SendCustomerToLiveStrom(_product_id){
		//var _lC = setInterval(function(){
			jQuery.get('<?php echo home_url('/');?>?myaction=sendcustomer&pid=<?php echo $product_id;?>', function(data){
				if(data==0){
					//clearInterval(_lC);
					location.reload();
				}
				else{
					setTimeout(function(){
						SendCustomerToLiveStrom(_product_id);
					},1000);
				}
			});
		//},1000);
	}
	</script>
    <?php
}