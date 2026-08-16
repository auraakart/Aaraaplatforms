<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the html field for general tab.
 *
 * @link       https://wpswings.com/
 * @since      1.0.0
 *
 * @package    Wallet_System_For_Woocommerce_Pro
 * @subpackage Wallet_System_For_Woocommerce_Pro/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="wps-overview__wrapper">
	<div class="wps-overview__content">
		<div class="wps-overview__content-description">
			<h2><?php esc_html_e( 'What is Wallet System for WooCommerce Pro Plugin? ', 'wallet-system-for-woocommerce-pro' ); ?></h2>
			<p>
				<?php
				esc_html_e(
					'Wallet System for WooCommerce Pro is a digital wallet plugin. It allows your registered customers to create a digital wallet on your WooCommerce store. Customers can purchase your products and services using the digital wallet amount. With this plugin, you can add or remove funds from customers’ wallets in bulk, view & download wallet transaction history, and send email notifications to customers.',
					'wallet-system-for-woocommerce-pro'
				);
				?>
			</p>
			<h3><?php esc_html_e( 'With our Wallet System for WooCommerce Pro, You Can:', 'wallet-system-for-woocommerce-pro' ); ?></h3>
			<ul class="wps-overview__features">
				<li><?php esc_html_e( 'You can set the minimum/maximum top-up limit for customers.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'Let your customers make withdrawal requests by providing their details.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'Enable your customers to send Invites to their friends to join the Wallet System.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'Show customers their wallet amount in a widget.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'Allow customers to generate QR codes to receive payment from other wallet users.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'View and download the wallet transaction history.', 'wallet-system-for-woocommerce-pro' ); ?></li>
				<li><?php esc_html_e( 'Supports the Elementor page builder.', 'wallet-system-for-woocommerce-pro' ); ?></li>
			</ul>
		</div>
		<div class="wps-overview__keywords">
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Maximum-Minimum-Wallet-Recharge-Limit.png' ); ?>" alt="Maximum Minimum Wallet Recharge Limit ">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Maximum / Minimum Wallet Recharge Limit', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description">
							<?php
							esc_html_e(
								'You can set a maximum and minimum limit on wallet recharge. Your customers can top-up funds into their WooCommerce wallets within the allowed limit using the available payment method on your store.',
								'wallet-system-for-woocommerce-pro'
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Wallet-Amount-Widget.png' ); ?>" alt="Wallet Amount Widget">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Wallet Amount Widget', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description"><?php esc_html_e( 'You can use a widget to show customers their wallet amount after they log into their WooCommerce account. It helps customers keep track of their wallet amount.', 'wallet-system-for-woocommerce-pro' ); ?></p>
					</div>
				</div>
			</div>
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Wallet-User-Invite.png' ); ?>" alt="Wallet User Invite">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Wallet User Invite', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description">
							<?php
							esc_html_e(
								'You can allow wallet users to invite their friends to join the Wallet System for WooCommerce Pro. It will increase the wallet user base and add more customers to your online store.',
								'wallet-system-for-woocommerce-pro'
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Wallet-Amount-Withdrawal.png' ); ?>" alt="Wallet Amount Withdrawal">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Wallet Amount Withdrawal', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description">
							<?php
							esc_html_e(
								'Customers can withdraw their wallet amount into their bank account or any preferred payment apps. They have to file a withdrawal request and provide you their payment details.',
								'wallet-system-for-woocommerce-pro'
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card wps-card-support">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Generate-Wallet-QR-Codes.png' ); ?>" alt="Generate Wallet QR Codes">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Generate Wallet QR Codes', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description">
							<?php
							esc_html_e(
								'Customers can generate QR codes for their wallets and accept recharge from other wallet users. It makes the wallet recharge process easier and more flexible.',
								'wallet-system-for-woocommerce-pro'
							);
							?>
						</p>
					</div>
				</div>
			</div>
			<div class="wps-overview__keywords-item">
				<div class="wps-overview__keywords-card wps-card-support">
					<div class="wps-overview__keywords-image">
						<img src="<?php echo esc_html( WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_URL . 'admin/image/Download-the-Wallet-Transaction-History.png' ); ?>" alt="Download Wallet Transaction History">
					</div>
					<div class="wps-overview__keywords-text">
						<h3 class="wps-overview__keywords-heading"><?php esc_html_e( 'Download the Wallet Transaction History', 'wallet-system-for-woocommerce-pro' ); ?></h3>
						<p class="wps-overview__keywords-description">
							<?php
							esc_html_e(
								'You can view the wallet transactions of your customers in a tabular format and export the transaction history in an excel spreadsheet or CSV file.',
								'wallet-system-for-woocommerce-pro'
							);
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
