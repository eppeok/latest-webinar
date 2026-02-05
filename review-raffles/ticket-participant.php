<?php
global $wpdb;
if(isset($_GET['productid'])){
	$product_id = $_GET['productid'];
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
		?>
        <table>
        	<thead>
				<tr>
                <th width="300">Name</th>
                
                <th>Seats</th>
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
			 $winner_id = get_post_meta($product_id, 'tww_winner_id', true);
			 ?>
             <tr <?php if($winner_id==$order_id){echo 'bgcolor="#d1e4dd"';}?>>
             	
                <td><?php echo $order->get_billing_first_name().' '.$order->get_billing_last_name(); ?></td>
                <td><?php echo $seats;?></td>
               
             </tr>
             <?php
			 }
			 
		}
		?>
        </table>
        <?php
	}
}