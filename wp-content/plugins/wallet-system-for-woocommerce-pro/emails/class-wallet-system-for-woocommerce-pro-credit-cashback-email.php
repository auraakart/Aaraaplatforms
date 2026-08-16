<?php
/**
 * Credited Email template
 *
 * @link       https://wpswing.com/
 * @since      1.0.0
 *
 * @package    Subscriptions_For_Woocommerce
 * @subpackage Subscriptions_For_Woocommerce/email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wallet_System_For_Woocommerce_Pro_Credit_Cashback_Email' ) ) {

	/**
	 * Cancelled Email template Class
	 *
	 * @link       https://wpswing.com/
	 * @since      1.0.0
	 *
	 * @package    Subscriptions_For_Woocommerce
	 * @subpackage Subscriptions_For_Woocommerce/email
	 */
	class Wallet_System_For_Woocommerce_Pro_Credit_Cashback_Email extends WC_Email {

		/**
		 * Plan object.
		 *
		 * @var [type]
		 */
		public $plan_obj;
		/**
		 * User Name.
		 *
		 * @var [type]
		 */
		public $user_name;
		/**
		 * User Name.
		 *
		 * @var [type]
		 */
		public $user_id;
		/**
		 * Wallet amount.
		 *
		 * @var [type]
		 */
		public $credit_amount;
		/**
		 * Create class for email notification.
		 *
		 * @access public
		 */
		public function __construct() {

			$this->id          = 'wps_wswp_wallet_credit_cashback';
			$this->title       = __( 'Wallet cashback credited Email Notification', 'wallet-system-for-woocommerce-pro' );
			$this->customer_email = true;
			$this->description = __( 'This Email Notification Send if any user wallet is credited', 'wallet-system-for-woocommerce-pro' );

			$this->template_html  = 'wps-wswp-credit-cashback-email-template.php';
			$this->template_plain = '';
			$this->template_base  = WALLET_SYSTEM_FOR_WOOCOMMERCE_PRO_DIR_PATH . 'emails/templates/';
			$this->placeholders   = array(
				'{site_title}'       => $this->get_blogname(),
			);
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @since  1.0.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Wallet Credited Email {site_title}', 'wallet-system-for-woocommerce-pro' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  1.0.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Wallet Cashback Credited', 'wallet-system-for-woocommerce-pro' );
		}

		/**
		 * This function is used to trigger for email.
		 *
		 * @param [type] $user_id is the id of user.
		 * @param [type] $user_name name of user.
		 * @param [type] $credit_amount amount debited.
		 * @param string $order_id is the order id.
		 * @return void
		 */
		public function trigger( $user_id, $user_name, $credit_amount, $order_id = '' ) {
			$attachment = '';

			if ( $user_id ) {
				$this->setup_locale();

				$user      = new WP_User( $user_id );
				$user_info = get_userdata( $user_id );
				if ( is_a( $user, 'WP_User' ) ) {
					$this->object        = $user;
					$this->user_id       = $user_id;
					$this->user_name     = $user_name;
					$this->credit_amount     = $credit_amount;
					$this->recipient     = $user_info->user_email;
					if ( $this->is_enabled() && $this->get_recipient() ) {
						$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content_html(), $this->get_headers(), ! empty( $attachment ) ? $attachment : $this->get_attachments() );
					}
				}
				$this->restore_locale();
			}
		}

		/**
		 * Get_content_html function.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'user'          => $this->object,
					'plan_obj'      => $this->plan_obj,
					'user_id'      => $this->user_id,
					'user_name'     => $this->user_name,
					'credit_amount'     => $this->credit_amount,
					'email_heading' => $this->get_heading(),
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Initialise Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'    => array(
					'title'   => __( 'Enable/Disable', 'wallet-system-for-woocommerce-pro' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'wallet-system-for-woocommerce-pro' ),
					'default' => 'no',
				),
				'recipient'  => array(
					'title'       => __( 'Recipient Email Address', 'wallet-system-for-woocommerce-pro' ),
					'type'        => 'text',
					// translators: %s: list of placeholders.
					'description' => sprintf( __( 'Enter recipient email address. Defaults to %s.', 'wallet-system-for-woocommerce-pro' ), '<code>' . esc_attr( get_option( 'admin_email' ) ) . '</code>' ),
					'placeholder' => '',
					'default'     => '',
					'desc_tip'    => true,
				),
				'subject'    => array(
					'title'       => __( 'Subject', 'wallet-system-for-woocommerce-pro' ),
					'type'        => 'text',
					'description' => __( 'Enter the email subject', 'wallet-system-for-woocommerce-pro' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
					'desc_tip'    => true,
				),
				'heading'    => array(
					'title'       => __( 'Email Heading', 'wallet-system-for-woocommerce-pro' ),
					'type'        => 'text',
					'description' => __( 'Email Heading', 'wallet-system-for-woocommerce-pro' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
					'desc_tip'    => true,
				),
				'email_type' => array(
					'title'       => __( 'Email type', 'wallet-system-for-woocommerce-pro' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'wallet-system-for-woocommerce-pro' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}

}

return new Wallet_System_For_Woocommerce_Pro_Credit_Cashback_Email();
