<?php
/**
 * Vendor List Template
 *
 * This template can be overridden by copying it to yourtheme/wc-vendors/front/vendors-list.php
 *
 * @author		Jamie Madden, WC Vendors
 * @package 	WCVendors/Templates/Emails/HTML
 * @version 	2.0.0
 * 
 *	Template Variables available
 *  $shop_name : pv_shop_name
 *  $shop_description : pv_shop_description (completely sanitized)
 *  $shop_link : the vendor shop link
 *  $vendor_id  : current vendor id for customization

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
<li class="vendor_list">
	<div class="row">
		<div class="vendor-list-image col-md-4 col-sm-4 col-xs-12">
			<a class="shop-avatar" href="<?php echo esc_url($shop_link); ?>"><?php echo get_avatar($vendor_id, 200); ?></a>
		</div>
		<div class="vendor-list-infor col-md-8 col-sm-8 col-xs-12">
			<a class="shop-name" href="<?php echo esc_url($shop_link); ?>" class="button"><?php echo esc_html($shop_name); ?></a>
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
</li>
