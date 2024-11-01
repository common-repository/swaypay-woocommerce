<?php
/**
 * Plugin Name: SwayPay WooCommerce
 * Description: SwayPay extension for WooCommerce.
 * Version: 1.0.0
 * Author: SwayPay
 * Author URI: http://www.swaypay.io/
 * Developer: SwayPay
 * *
 * Copyright: © 2017 SwayPay
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    exit;
}

if ( ! function_exists( 'swaypay_template_add_to_cart' ) ) {	
	function swaypay_template_add_to_cart() {
		global $post, $product;
		$thumbnail_size    = apply_filters( 'woocommerce_product_thumbnails_large_size', 'full' );
		$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
		$full_size_image   = wp_get_attachment_image_src( $post_thumbnail_id, $thumbnail_size );		
		$quantity = 'jQuery(\'[name="quantity"]\').val()';
		$itemsJs = '{ id: '. $product->id .', name: "'.$product->name.'", price: '. $product->price .', quantity: ' .$quantity. ', imagePath: "'.$full_size_image[0].'"}';
		echo '<div sway-pay></div>';
		swaypay_get_script_html($itemsJs, $product->price);
	}
}

if ( ! function_exists( 'swaypay_cart_add_script' ) ) {	
	function swaypay_cart_add_script() {
		global $woocommerce;
		$items = $woocommerce->cart->get_cart();
		$total = 0;
		$itemsJs = "";
        foreach($items as $item => $values) { 
			$product =  wc_get_product( $values['data']->get_id());	
            $price = get_post_meta($values['product_id'] , '_price', true);			
			$total += $price * $values['quantity'];
			$images = wp_get_attachment_image_src( get_post_thumbnail_id( $product->id));
			$itemsJs = $itemsJs . '{ id: '. $values['data']->get_id() .', name: "'.$product->name.'", price: '.$price.', quantity: ' .$values['quantity']. ', imagePath: "'.$images[0].'"},';
		}		
		echo '<div sway-pay></div>';
		swaypay_get_script_html($itemsJs, $total);
	}
}

function swaypay_get_shippings_js() {
	$shippingMethodsJs = "";
	global $woocommerce;
    $shipping_methods = $woocommerce->shipping->load_shipping_methods();
   	foreach ($shipping_methods as $shipping_method) {
		if( 'yes' === $shipping_method->enabled ){
			if ( 'free_shipping' === $shipping_method->id )
			{
				$shippingMethodsJs = $shippingMethodsJs. '{ description: "'.$shipping_method->method_title.'", price: '.$shipping_method->min_amount.', deliveryService: "'.$shipping_method->id.'"},';
			}
			if ( 'flat_rate' === $shipping_method->id && $shipping_method->cost_per_order != '')
			{
				$shippingMethodsJs = $shippingMethodsJs. '{ description: "'.$shipping_method->method_title.'", price: '.$shipping_method->cost_per_order.', deliveryService: "'.$shipping_method->id.'"},';
			}
		}
	}
	return $shippingMethodsJs;
}

function swaypay_check_if_admin(){
	if( current_user_can('editor') || current_user_can('administrator') ) {
		return 'SwayPay.app.injectButtonRules = [{pass: () => Promise.resolve(true)}];';
	}
	return '';
}

function swaypay_get_script_html($itemsJs, $total) {		
	$merchantId = get_option('swaypay_merchant_id');	
    wp_enqueue_script( 'swaypay-script', "https://code.swaypay.net/swaypay/script.js", array(), null );		
	$data = swaypay_check_if_admin(). '
		SwayPay.init({
			merchantId: ' . $merchantId . ',
			purchases: [
				{
					source: function(){ return { items: [' .$itemsJs. '], shippingMethods: ['. swaypay_get_shippings_js() .'], price: { total: '.$total.' } }; },
					button: { parent: "[sway-pay]" }
				}
			],
			onConfirmed: (confirmData) => {
				if(confirmData.additionalData){
					window.location.href = confirmData.additionalData.redirectUrl;
				}
			},
			onError: (error) => { console.log(error); }
		});';		
	wp_add_inline_script( 'swaypay-script', $data );
}

function swaypay_plugin_page() {
	?>
	  <div class="wrap">
		<form action="options.php" method="post">   
		  <?php
			settings_fields( 'swaypay-plugin-settings' );
			do_settings_sections( 'swaypay-plugin-settings' );
		  ?>
		  <table>
			   
			  <tr>
				<th>Merchant Id</th>
				<td>
				  	<input type="number" placeholder="Merchant Id" name="swaypay_merchant_id" value="<?php echo esc_attr( get_option('swaypay_merchant_id') ); ?>" size="10" />
				</td>
			  </tr>		     
			  <tr>
				  <td><?php submit_button(); ?></td>
			  </tr>
   
		  </table>
   
		</form>
	  </div>
	<?php
  }

  add_action( 'woocommerce_single_product_summary', 'swaypay_template_add_to_cart', 35 );
  add_action( 'woocommerce_after_cart_totals', 'swaypay_cart_add_script', 1 );  
  add_action( 'woocommerce_checkout_order_review', 'swaypay_cart_add_script', 100 );

  add_action('admin_menu', function() {
	  add_options_page( 'Swaypay settings', 'SwayPay', 'manage_options', 'swaypay-plugin', 'swaypay_plugin_page' );
  });
  
  add_action( 'admin_init', function() {
	  register_setting( 'swaypay-plugin-settings', 'swaypay_merchant_id' );
  });