<?php
function twwt_get_csv($product_id){
	$product_id = intval($product_id);
	$order_ids = twwt_get_paid_order_ids_for_product( $product_id );
	
	$array = array();
		array_push($array, array('Order ID', 'Screen Name', 'Winner', 'First Name', 'Last Name', 'Email', 'Phone', 'Seats'));
	
	 
	// Print array on screen
	if(!empty($order_ids)){
		$winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
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
			 ###
			$screen_name_display = "";
			$screen_name = get_post_meta( $order_id, '_screen_name', true );
			if($screen_name!=""){
				$screen_name_display = $screen_name;
			}
			else{
				$cuser = $order->get_user();
				if(!empty($cuser->display_name)){
					$screen_name_display = $cuser->display_name;
				}
				else{
					$screen_name_display = $order->get_billing_first_name();
				}
			}
			$is_winner = "No";
			if($winner_id==$order_id){$is_winner = 'Yes';}
			
  
				 array_push($array, array(
					$order_id,
					$screen_name_display,
					$is_winner,
					$order->get_billing_first_name(),
					$order->get_billing_last_name(),
					$order->get_billing_email(),
					"' ".$order->get_billing_phone()." '",
					$seats
				 ));
			 }
			 
		}
	}
	download_send_headers("export-customer-". $product_id . "-" . date("Y-m-d") . ".csv");
	echo array2csv($array);
}

function array2csv(array &$array)
{
   if (count($array) == 0) {
	 return null;
   }
   ob_start();
   $df = fopen("php://output", 'w');
   //fputcsv($df, array_keys(reset($array)));
   foreach ($array as $row) {
	  fputcsv($df, $row);
   }
   fclose($df);
   return ob_get_clean();
}
function download_send_headers($filename) {
	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");

	header("Content-Disposition: attachment;filename={$filename}");
	header("Content-Transfer-Encoding: binary");
}