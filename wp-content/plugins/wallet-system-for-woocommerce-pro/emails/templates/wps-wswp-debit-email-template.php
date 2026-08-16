<?php
/**
 * Debited Email template
 *
 * @link       https://wpswing.com/
 * @since      1.0.0
 *
 * @package    Subscriptions_For_Woocommerce
 * @subpackage Subscriptions_For_Woocommerce/email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

do_action( 'woocommerce_email_header', $email_heading, $email );
$blogname_wallet = get_option( 'blogname' );
$wallet_bal = get_user_meta( $user_id, 'wps_wallet', true );
$currency  = get_woocommerce_currency();
$wallet_bal = $currency . ' ' . $wallet_bal;
?>
<table>

<tr>
	<td style="padding:0;">
		<div>
			<p style="margin: 0 0 16px;">
			<?php
			echo esc_html__( 'Hi ', 'wallet-system-for-woocommerce-pro' );
			echo esc_html( $user_name );
			?>
			,</p>
			<p style="margin: 0 0 16px;"><?php echo esc_html__( 'We would like to notify you that amount ', 'wallet-system-for-woocommerce-pro' ); ?><span style="color: red;"><?php echo wp_kses_post( $debit_amount ); ?></span> <?php echo esc_html__( ' has been debited successfully from your wallet.', 'wallet-system-for-woocommerce-pro' ); ?></p>
			<p style="margin: 0 0 16px;"><?php echo esc_html__( 'Here is the account summary:', 'wallet-system-for-woocommerce-pro' ); ?></p>
		</div>

		<div>
			<table style="color: #636363; border: 1px solid #e5e5e5; border-collapse: collapse; width: 600px">
			<tbody>
					<tr>
						<th style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px"><?php echo esc_html__( 'Customer Name', 'wallet-system-for-woocommerce-pro' ); ?></th>
						<td style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px"><?php echo esc_html( $user_name ); ?></td>
					</tr>
					<tr>
						<th style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px"><?php echo esc_html__( 'Debit Amount', 'wallet-system-for-woocommerce-pro' ); ?></th>
						<td style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px; color:red; padding-left: 10px" >-<?php echo wp_kses_post( $debit_amount ); ?></td>
					</tr>
					<tr>
						<th style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px"><?php echo esc_html__( 'Total Balance', 'wallet-system-for-woocommerce-pro' ); ?></th>
						<td style="border: 1px solid #e5e5e5; border-collapse: collapse; width: 300px; color:#00f; padding-left: 10px"><?php echo wp_kses_post( $wallet_bal ); ?></td>
					</tr>
			</tbody>
			</table>
		</div>

		<div style="padding: 20px 0 0">
			<p style="margin: 0 0 16px"><?php echo esc_html__( 'If you haven’t made this transaction, please get in touch with us.', 'wallet-system-for-woocommerce-pro' ); ?></p>
			<p style="margin: 0 0 16px;"> <?php echo esc_html__( 'Thank you for choosing ', 'wallet-system-for-woocommerce-pro' ); ?><?php echo esc_html( $blogname_wallet ); ?>.</p>
			<p style="margin: 0 0 16px"><?php echo esc_html__( 'Best regards,', 'wallet-system-for-woocommerce-pro' ); ?></p>
			<p style="margin: 0 0 16px"><?php echo esc_html( $blogname_wallet ); ?> <?php echo esc_html__( 'Team', 'wallet-system-for-woocommerce-pro' ); ?></p>
		</div>
	</td>
</tr>

</table>
<?php
do_action( 'woocommerce_email_footer', $email );
