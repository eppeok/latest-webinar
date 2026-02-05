<?php
global $product;
$product_id = $product->get_id();
if ( $product->is_purchasable() ) { 
	if(twwt_woo_product_seat($product_id)){
?>
        <style>
table.variations, .woocommerce-variation.single_variation, .single_variation_wrap div.quantity, .single_variation_wrap .single_add_to_cart_button{
	border: 0;
	clip: rect(1px, 1px, 1px, 1px);
	-webkit-clip-path: inset(50%);
	clip-path: inset(50%);
	height: 1px;
	margin: -1px;
	overflow: hidden;
	padding: 0;
	position: absolute !important;
	width: 1px;
	word-wrap: normal !important;
	word-break: normal;
}
.randomGenerator input[type=number] {
    max-width: 100%;
}
.randomGenerator{
	display: grid;
    grid-template-columns: 63% 33%;
    column-gap: 3%;
}
#randomGenerator{
    padding: 10px;
}
.rseat-container{
    padding: 10px;
}
#generate-random {
    padding: 10px;
    margin-left: 1.5%;
}
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button {  
   opacity: 1;
}
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button {  
   -webkit-appearance: auto;
}
</style>
		<?php 
		$login_check = twwt_woo_login_check();
		if($login_check['status']){
			?>
            <a href="<?php echo get_permalink( get_option('woocommerce_myaccount_page_id') ); ?>" class="button alt"><?php echo $login_check['text'];?></a>
            <?php
		}
		else{
		?>
		<div class="ticket-dashboard">
        <div class="ticket-wrapper">
        <?php
		//$variations = $product->get_available_variations();
		//print_r($variations);exit;
		$product = wc_get_product($product_id);
		$current_products = $product->get_children();
		
		foreach($current_products as $item_id){
			
			$variation_price = get_variation_price_by_id($product_id, $item_id);
			$totalMaxseat = get_post_meta( $item_id, '_variable_text_field', true );
			$options = get_option( 'twwt_woo_settings' );
			if($options['randomGenerator_restrict']==1){
			echo '<div class="randomGenerator" id="randomGenerator">';
			echo '<input type="number" class="input-text" id="random-generator" placeholder="Random Seat Quantity" min="1" max="'.$variation_price->ticket_left.'">';
			echo '<button id="generate-random">Generate</button></div>';
			?>
			<style>
			.prc-qty{
				display: none;
			}
			</style>
			<?php
			}
			echo '<div class="rseat-container"><h4>';
			echo $variation_price->variation_name;
			echo '<strong>'.$variation_price->display_regular_price.'</strong>';
			echo '</h4>';
			echo '<div class="seat-available"><strong>Available Seats:</strong> '.$variation_price->ticket_left.'/'.$totalMaxseat.'</div>';
			echo '<div class="clear"></div>';
			echo '<ul class="my-tickets ticket-id-'.$item_id.'">';
			$max_ticket = get_post_meta( $item_id, '_variable_text_field', true);
			for($i=1; $i<=$max_ticket; $i++){
				$availability = twwt_woo_get_availability_v2($item_id, $i);
				
				$availability_status = @$availability['status'];
				$availability_type = @$availability['type'];
				?>
                <li><label><input <?php if($availability_status){echo 'disabled="disabled"';}?> data-value="<?php echo $variation_price->variation_name;?>" data-vid="<?php echo $item_id;?>" class="ticket-box rbtn-tt-<?php echo $availability_type;?>" type="checkbox" name="seat[]" value="<?php echo $i;?>"> <span><?php echo $i;?></span></label></li>
                <?php
			}
			echo '</ul></div>';
			echo '<div class="clear"></div>';
		}
		?>
        <div class="availabilitycheck-msg"></div>
        </div>
		<div class="proceed-cart-info">
	<ul>
    	<li><span class="tbtn tbtn-sold"></span> Taken</li>
    	<li><span class="tbtn tbtn-available"></span> Available</li>
        <li><span class="tbtn tbtn-selected"></span> Reserved</li>
    </ul>
</div>
        <div class="proceed-cart">
            <div class="prc-type"></div>
            <div class="prc-qty"></div>
            <div class="prc-btn"><button type="button" class="tbtn-button button alt">Proceed</button></div>
        </div>
        </div>
        <?php } ?>
        <?php 
		$winner_id = get_post_meta( $product_id, 'tww_winner_id', true );
		if($winner_id>0){
		}
		else{
		if( current_user_can('editor') || current_user_can('administrator') ) {  ?>
        	
            <?php /*?><p class="mt-4 mb-0"><a target="_blank" href="<?php echo get_permalink(2613);?>?pid=<?php echo $product_id;?>" class="btn ">Select Winner</a></p><?php */?>
        <?php }
		}?>
<?php 
	}
}