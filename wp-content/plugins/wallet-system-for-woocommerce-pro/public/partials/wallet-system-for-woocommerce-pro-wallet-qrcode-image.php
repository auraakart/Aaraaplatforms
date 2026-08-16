<?php
/**
 * Template Name: ShowQRCode
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 */

$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
$request_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_url; // phpcs:ignore
/**
 * Get the unique qr code.
 *
 * @param  string $str           qrcode whole string.
 * @param  string $starting_word starting_word.
 * @param  string $ending_word   ending_word.
 * @return string
 */
function string_between_two_string( $str, $starting_word, $ending_word ) {
	$arr = explode( $starting_word, $str );
	if ( isset( $arr[1] ) ) {
		$arr = explode( $ending_word, $arr[1] );
		return $arr[0];
	}
	return '';
}

$start         = 'qr-';
$end           = '.png';
$qr_code_exist = false;
$users         = get_users();
foreach ( $users as $user ) {
	$user_id        = $user->ID;
	$wallet_qr_code = get_user_meta( $user_id, 'wallet_qr_code_image', true );
	$qrcode         = string_between_two_string( $wallet_qr_code, $start, $end );
	$wallet_page_id = get_option( 'wps_wsfwp_wallet_top_page_id' );
	$page_url       = get_permalink( $wallet_page_id );
	if ( get_option( 'permalink_structure' ) ) {
		if ( substr( $page_url, -1 ) == '/' ) {
			$qr_code_url = $page_url . 'wps-qrcode-image/' . $qrcode . '/';
		} else {
			$qr_code_url = $page_url . '/wps-qrcode-image/' . $qrcode;
		}
	} else {
		$qr_code_url = add_query_arg( 'wps-qrcode-image/', $qrcode, $page_url );
	}
	if ( $current_url === $qr_code_url ) {
		$upload_dir     = wp_upload_dir();
		$image_path     = $upload_dir['basedir'];
		$wp_content_url = content_url() . '/uploads';

		if ( $wallet_qr_code && file_exists( $image_path . $wallet_qr_code ) ) {
			$qr_code_exist = true;
			?>
		<img class="wps_qbg_qr_code_img" src="<?php echo esc_attr( $wp_content_url . $wallet_qr_code ); ?>" >
			<?php
		}
	}
}
if ( ! $qr_code_exist ) {
	?>
	<p class="wsfwp_error"><?php esc_html_e( 'QR Code does not exists.', 'wallet-system-for-woocommerce-pro' ); ?></p>
	<?php
}

