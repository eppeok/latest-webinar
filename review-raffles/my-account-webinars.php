<?php
$user_id = get_current_user_id();
$removed_videos = get_user_meta($user_id, 'remove_webinar_video');
$removed_videos = array_unique( $removed_videos );
//print_r($removed_videos);
/*echo $user_id;*/
//$a = array_keys(wc_get_order_statuses());
//print_r($a);
?>
<h3>Webinar Videos</h3>
<p>Re-watch previously purchased webinars.</p>
<?php
$customer = wp_get_current_user();
// Get all customer orders

$mystatus = array();
if(!empty(wc_get_is_paid_statuses())){
	foreach(wc_get_is_paid_statuses() as $staus){
		$mystatus[] = "wc-".$staus;
	}
}

$customer_orders = get_posts(array(
	'numberposts' => -1,
	'meta_key' => '_customer_user',
	'orderby' => 'date',
	'order' => 'DESC',
	'meta_value' => $user_id,
	'post_type' => wc_get_order_types(),
	'post_status' => $mystatus //array('wc-completed'),
));

$product_ids = array();
foreach ($customer_orders as $customer_order) {
	$order = wc_get_order($customer_order);
	$items = $order->get_items();
	foreach ( $items as $item ) {
		$product_id = $item->get_product_id();
		if(!in_array($product_id, $removed_videos)){
			
			$woo_seat_show = get_post_meta( $product_id, 'woo_seat_show', true );
			$woo_seat_video = get_post_meta( $product_id, 'woo_seat_video', true );
			if($woo_seat_show==1 && $woo_seat_video!=""){
				$product_ids[] = $product_id;
			}
		}
	}
}
$product_ids = array_unique( $product_ids );
if(!empty($product_ids)){
	?>
<table class="woocommerce-webinars-table woocommerce-MyAccount-webinars shop_table shop_table_responsive my_account_webinars account-webinars-table">
  <thead>
    <tr>
      <th class="woocommerce-webinars-table__header woocommerce-webinars-table__header-webinar-name"><span class="nobr">Webinar Name</span></th>
      <th class="woocommerce-webinars-table__header woocommerce-webinars-table__header-webinar-actions"><span class="nobr">Actions</span></th>
    </tr>
  </thead>
  <tbody>
    <?php
	foreach($product_ids as $product_id){
		$product = wc_get_product( $product_id );
		$woo_seat_video = get_post_meta( $product_id, 'woo_seat_video', true );
		?>
    <tr>
      <td class="woocommerce-webinars-table__cell woocommerce-webinars-table__cell-webinar-name" data-title="Webinar Name">
	  <?php echo '#'.$product_id;?> 
	  <?php echo $product->get_title();?></td>
      <td class="woocommerce-webinars-table__cell woocommerce-webinars-table__cell-webinar-actions" data-title="Actions"><a href="<?php echo $woo_seat_video;?>" target="_blank" class="woocommerce-button button view">Play Video</a>&nbsp;	&nbsp; <a href="#" class="woocommerce-button button alt remove-webinar-video" data-id="<?php echo $product_id;?>">Remove</a></td>
    </tr>
    <?php
	}
	?>
  </tbody>
</table>
<?php
}else{
	?>
    <div class="woo-no-list"> No webinar videos available to watch. </div>
    <?php
}