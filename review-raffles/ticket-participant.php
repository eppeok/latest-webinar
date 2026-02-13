<?php
if(isset($_GET['productid'])){
	$product_id = intval($_GET['productid']);
	$order_ids = twwt_get_paid_order_ids_for_product( $product_id );
	 
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
             	
                <td><?php echo esc_html($order->get_billing_first_name().' '.$order->get_billing_last_name()); ?></td>
                <td><?php echo esc_html($seats);?></td>
               
             </tr>
             <?php
			 }
			 
		}
		?>
        </table>
        <?php
	}
}