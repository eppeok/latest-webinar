<?php
function twwt_create_event($title, $time){
	$options = get_option( 'twwt_woo_settings' );
	$token = $options['token'];
	$copy_from_event_id = $options['copy_from_event_id'];
	$owner_id = $options['owner_id'];
	$timezone = get_option('timezone_string');
	
	
	$curl = curl_init();
	curl_setopt_array($curl, [
	  CURLOPT_URL => "https://api.livestorm.co/v1/events",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => "{\"data\":{\"type\":\"events\",\"attributes\":{\"copy_from_event_id\":\"{$copy_from_event_id}\",\"owner_id\":\"{$owner_id}\",\"title\":\"{$title}\",\"status\":\"published\"},\"relationships\":{\"sessions\":[{\"attributes\":{\"estimated_started_at\":\"{$time}\",\"timezone\":\"{$timezone}\"},\"type\":\"sessions\"}]}}}",
	  CURLOPT_HTTPHEADER => [
		"Accept: application/vnd.api+json",
		"Authorization: {$token}",
		"Content-Type: application/vnd.api+json"
	  ],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	
	if ($err) {
		return array("status" => 0, "error" => $err);
	} else {
		return array("status" => 1, "response" => $response);
	}
}
function twwt_send_customer($product_id){
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
	$done = 0;
	$scounter = 0;
	foreach($order_ids as $order_id){
		if(!twwy_has_refunds_v2($order_id)){
		if($scounter>4){
			break;
		}
		$sent = get_post_meta( $order_id, '_sent_to_livestrom', true );
		if($sent==1){
		}
		else if($sent==-1){
		}
		else{
			$scounter++;
			$done = 1;
			$order = wc_get_order( $order_id );
			$screen_name = get_post_meta( $order_id, '_screen_name', true );
			if($screen_name!=""){
			}
			else{
				$cuser = $order->get_user();
				if(!empty($cuser->display_name)){
					$screen_name = $cuser->display_name;
				}
				else{
					$screen_name = $order->get_billing_first_name();
				}
			}
			$first_name = $order->get_billing_first_name();
			$last_name = $order->get_billing_last_name();
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
			$event_data = get_post_meta( $product_id, 'event_data', true );
			$session_id = $event_data->data->relationships->sessions->data[0]->id;
			
			###
			//echo $screen_name.'<br>'.$first_name.'<br>'.$last_name.'<br>'.$email.'<br>'.$phone.'<br>';
			//echo '<pre>';
			//print_r($session_id);
			//exit;
			
			###
			$send_ls = twwt_send_customer_ls($screen_name, $first_name, $last_name, $email, $phone, $session_id);
			if($send_ls['status']==1){
				$response = json_decode($send_ls['response']);
				update_post_meta($order_id, 'participant_data', $response);
				if($response->errors){
					update_post_meta($order_id, '_sent_to_livestrom', -1);
				}
				else{
					update_post_meta($order_id, '_sent_to_livestrom', 1);
				}
			}
			else{
				update_post_meta($order_id, '_sent_to_livestrom', 0);
			}
			
			###
		}
	}
	}
	echo $done;
}

function twwt_send_customer_ls($screen_name, $first_name, $last_name, $email, $phone, $session_id){
	$options = get_option( 'twwt_woo_settings' );
	$token = $options['token'];
	
	$curl = curl_init();

	curl_setopt_array($curl, [
	  CURLOPT_URL => "https://api.livestorm.co/v1/sessions/{$session_id}/people",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => "{\"data\":{\"type\":\"people\",\"attributes\":{\"fields\":[{\"id\":\"email\",\"value\":\"{$email}\"},{\"id\":\"first_name\",\"value\":\"{$first_name}\"},{\"id\":\"last_name\",\"value\":\"{$last_name}\"},{\"id\":\"screen_name\",\"value\":\"{$screen_name}\"}]}}}",
	  CURLOPT_HTTPHEADER => [
		"Accept: application/vnd.api+json",
		"Authorization: {$token}",
		"Content-Type: application/vnd.api+json"
	  ],
	]);
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	
	curl_close($curl);
	if ($err) {
		return array("status" => 0, "error" => $err);
	} else {
		return array("status" => 1, "response" => $response);
	}
}