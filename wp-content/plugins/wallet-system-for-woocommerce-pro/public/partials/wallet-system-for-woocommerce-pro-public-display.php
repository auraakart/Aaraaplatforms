<?php
/**
 * Provide a public-facing view for the plugin
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link        https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/public/partials
 */

$user_id = get_current_user_id();

global $wp;




	/**
	 * Show message on qrcode generation.
	 *
	 * @param string $wpg_message message.
	 * @param string $type message type.
	 * @return void
	 */
function show_message_on_qrcode_generation( $wpg_message, $type = 'error' ) {
	$wpg_notice = '<div class="woocommerce"><p class="' . esc_attr( $type ) . '">' . $wpg_message . '</p>	</div>';
	echo wp_kses_post( $wpg_notice );
}




?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<?php
/**
 * Get the unique qr code.
 *
 * @param  string $str           qrcode whole string.
 * @param  string $starting_word starting_word.
 * @param  string $ending_word   ending_word.
 * @return string
 */
function wps_string_between_two_string( $str, $starting_word, $ending_word ) {
	$arr = explode( $starting_word, $str );
	if ( isset( $arr[1] ) ) {
		$arr = explode( $ending_word, $arr[1] );
		return $arr[0];
	}
	return '';
}
	$start          = 'qr-';
	$end            = '.png';
	$upload_dir     = wp_upload_dir();
	$wallet_qr_code = get_user_meta( $user_id, 'wallet_qr_code_image', true );
	$qrcode         = wps_string_between_two_string( $wallet_qr_code, $start, $end );
	$image_path     = $upload_dir['basedir'];
	$wp_content_url = content_url() . '/uploads';
	$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
	$page_url       = get_permalink( $wallet_page_id );
if ( get_option( 'permalink_structure' ) ) {
	if ( substr( $page_url, -1 ) == '/' ) {
		$qr_code_url = $page_url . 'wps-qrcode-image/' . $qrcode . '/';
	} else {
		$qr_code_url = $page_url . '/wps-qrcode-image/' . $qrcode;
	}
} else {
	$qr_code_url = add_query_arg( 'wps-qrcode-image', $qrcode, $page_url );
}
if ( $wallet_qr_code && file_exists( $image_path . $wallet_qr_code ) ) {
	?>
<div class="wps-wallet-qr-container">
	<div class="item wps_qbg_qr_code_img_wrap">
		<img class="wps_qbg_qr_code_img" src="<?php echo esc_attr( $wp_content_url . $wallet_qr_code ); ?>" >
	</div>
	<div class="item item-copycode">
		<input type="hidden" value="<?php echo esc_attr( $qr_code_url ); ?>" id="copyQrCodeUrl">
		<button id="copyQRCode"><span class="tooltiptext" id="myTooltip"><?php esc_html_e( 'Copy to clipboard ', 'wallet-system-for-woocommerce-pro' ); ?> </span><?php esc_html_e( 'Copy QR Code', 'wallet-system-for-woocommerce-pro' ); ?></button>
	</div>
</div>
<?php } else { ?>
<form  action="" method="post" class="wps_qr_code_form" > 
	<p class="wps-wallet-field-container form-row wps_qr_code_form_generate_qr_wap">
		<input type="hidden" name="wsfwp_qr_code_nonce" value="<?php echo esc_html( wp_create_nonce( 'wsfwp-qr-code-nonce' ) ); ?>" />
		<input type="submit" class="wps-btn__filled button wps_generate_qr_code" name="wps_generate_qr_code" id='wps_generate_qr_code' data-user-id="<?php echo esc_attr( $user_id ); ?>" value="<?php esc_attr_e( 'Generate QR Code', 'wallet-system-for-woocommerce-pro' ); ?>" >
		<svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M7.5 16.25C7.83152 16.25 8.14946 16.1183 8.38388 15.8839C8.6183 15.6495 8.75 15.3315 8.75 15V8.75H15C15.3315 8.75 15.6495 8.6183 15.8839 8.38388C16.1183 8.14946 16.25 7.83152 16.25 7.5C16.25 7.16848 16.1183 6.85054 15.8839 6.61612C15.6495 6.3817 15.3315 6.25 15 6.25H7.5C7.16848 6.25 6.85054 6.3817 6.61612 6.61612C6.3817 6.85054 6.25 7.16848 6.25 7.5V15C6.25 15.3315 6.3817 15.6495 6.61612 15.8839C6.85054 16.1183 7.16848 16.25 7.5 16.25Z" fill="white"/>
		<path d="M25 8.75H31.25V15C31.25 15.3315 31.3817 15.6495 31.6161 15.8839C31.8505 16.1183 32.1685 16.25 32.5 16.25C32.8315 16.25 33.1495 16.1183 33.3839 15.8839C33.6183 15.6495 33.75 15.3315 33.75 15V7.5C33.75 7.16848 33.6183 6.85054 33.3839 6.61612C33.1495 6.3817 32.8315 6.25 32.5 6.25H25C24.6685 6.25 24.3505 6.3817 24.1161 6.61612C23.8817 6.85054 23.75 7.16848 23.75 7.5C23.75 7.83152 23.8817 8.14946 24.1161 8.38388C24.3505 8.6183 24.6685 8.75 25 8.75Z" fill="white"/>
		<path d="M15 31.25H8.75V25C8.75 24.6685 8.6183 24.3505 8.38388 24.1161C8.14946 23.8817 7.83152 23.75 7.5 23.75C7.16848 23.75 6.85054 23.8817 6.61612 24.1161C6.3817 24.3505 6.25 24.6685 6.25 25V32.5C6.25 32.8315 6.3817 33.1495 6.61612 33.3839C6.85054 33.6183 7.16848 33.75 7.5 33.75H15C15.3315 33.75 15.6495 33.6183 15.8839 33.3839C16.1183 33.1495 16.25 32.8315 16.25 32.5C16.25 32.1685 16.1183 31.8505 15.8839 31.6161C15.6495 31.3817 15.3315 31.25 15 31.25Z" fill="white"/>
		<path d="M32.5 23.75C32.1685 23.75 31.8505 23.8817 31.6161 24.1161C31.3817 24.3505 31.25 24.6685 31.25 25V31.25H25C24.6685 31.25 24.3505 31.3817 24.1161 31.6161C23.8817 31.8505 23.75 32.1685 23.75 32.5C23.75 32.8315 23.8817 33.1495 24.1161 33.3839C24.3505 33.6183 24.6685 33.75 25 33.75H32.5C32.8315 33.75 33.1495 33.6183 33.3839 33.3839C33.6183 33.1495 33.75 32.8315 33.75 32.5V25C33.75 24.6685 33.6183 24.3505 33.3839 24.1161C33.1495 23.8817 32.8315 23.75 32.5 23.75Z" fill="white"/>
		<path d="M37.5 18.75H2.5C2.16848 18.75 1.85054 18.8817 1.61612 19.1161C1.3817 19.3505 1.25 19.6685 1.25 20C1.25 20.3315 1.3817 20.6495 1.61612 20.8839C1.85054 21.1183 2.16848 21.25 2.5 21.25H37.5C37.8315 21.25 38.1495 21.1183 38.3839 20.8839C38.6183 20.6495 38.75 20.3315 38.75 20C38.75 19.6685 38.6183 19.3505 38.3839 19.1161C38.1495 18.8817 37.8315 18.75 37.5 18.75Z" fill="white"/>
		</svg>
	</p>
</form>
	<?php
}
?>
