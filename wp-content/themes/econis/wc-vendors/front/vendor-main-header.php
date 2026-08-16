<?php
/**
 * Vendor Main Header Template
 *
 * THIS FILE WILL LOAD ON VENDORS STORE URLs (such as yourdomain.com/vendors/bobs-store/)
 *
 * This template can be overridden by copying it to yourtheme/wc-vendors/front/vendor-main-header.php
 *
 * @author		Jamie Madden, WC Vendors
 * @package 	WCVendors/Templates/Emails/HTML
 * @version 	2.0.0
 *
 *
 * Template Variables available
 * $vendor : 			For pulling additional user details from vendor account.  This is an array.
 * $vendor_id  : 		current vendor user id number
 * $shop_name : 		Store/Shop Name (From Vendor Dashboard Shop Settings)
 * $shop_description : Shop Description (completely sanitized) (From Vendor Dashboard Shop Settings)
 * $seller_info : 		Seller Info(From Vendor Dashboard Shop Settings)
 * $vendor_email :		Vendors email address
 * $vendor_login : 	Vendors user_login name
 * $vendor_shop_link : URL to the vendors store
 */

 if ( ! defined( 'ABSPATH' ) ) {
 	exit;
 }
$phone	 			= get_user_meta( $vendor_id , 'billing_phone', true );
$email	 			= get_user_meta( $vendor_id , 'billing_email', true ); 
$address = array();
if(get_user_meta( $vendor_id , 'billing_address_1', true )){
	$address[] 			= get_user_meta( $vendor_id , 'billing_address_1', true );
}
$address2 			= get_user_meta( $vendor_id , 'billing_address_2', true );
if(get_user_meta( $vendor_id , 'billing_city', true )){
	$address[]	 		= get_user_meta( $vendor_id , 'billing_city', true );
}
if(get_user_meta( $vendor_id , 'billing_state', true )){
	$address[]		    = get_user_meta( $vendor_id , 'billing_state', true );
}
if(get_user_meta( $vendor_id , 'billing_postcode', true )){
	$address[]			= get_user_meta( $vendor_id , 'billing_postcode', true );
}
$address 			= $address ? implode($address,",") : "";

?>
<div class="vender-main-header">
	<div class="container">
		<div class="vendor-list-top">
			<div class="row">
				<div class="vendor-list-image col-md-2 col-sm-3 col-xs-12">
					<a class="shop-avatar" href="<?php echo esc_url($shop_link); ?>"><?php echo get_avatar($vendor_id, 200); ?></a>
				</div>
				<div class="vendor-list-infor col-md-10 col-sm-9 col-xs-12">
					<h2 class="shop-name" class="button"><?php echo esc_html($shop_name); ?></h2>
					<?php if(!empty($address)){ ?>
						<div class="shop-address"><i class="fa fa-map-marker"></i><?php echo esc_attr($address); ?></div>
					<?php } ?>
					<?php if(!empty($phone)){ ?>
					<div class="shop-phone"><i class="fa fa-phone"></i><?php echo esc_attr($phone); ?></div>
					<?php } ?>
					<?php if(!empty($email)){ ?>
					<div class="shop-email"><i class="fa fa-envelope-o"></i><?php echo esc_attr($email); ?></div>
					<?php } ?>
				</div>
			</div>
		</div>
		<div class="wcv_shop_description">
		<?php echo wp_kses($shop_description,'social'); ?>
		</div>
	</div>
</div>
