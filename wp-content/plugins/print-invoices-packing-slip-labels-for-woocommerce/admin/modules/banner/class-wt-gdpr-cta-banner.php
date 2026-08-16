<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

namespace Wtpdf\Banners;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Wt_GDPR_Cta_Banner
 *
 * @since 4.8.5
 * Shows GDPR CTA 
 * only when (a) no GDPR plugins active, (b) store should comply with GDPR.
 * 
 * GDPR Compliance Detection:
 * - Store base country is in EU or US (original functionality)
 * - Store is selling to EU or US customers (new functionality)
 * 
 * This ensures that stores selling to EU/US customers are prompted to implement 
 * GDPR compliance measures even if the store itself is not located in the EU/US.
 * GDPR applies to EU residents and US privacy laws apply to US residents regardless 
 * of where the business is located.
 * 
 * Implementation Notes:
 * - Uses 'woocommerce_loaded' hook to ensure WooCommerce is fully loaded before checking
 * - Falls back to 'admin_init' hook if WooCommerce is not available
 * - Prevents duplicate initialization using WordPress action tracking
 */
if ( ! class_exists( '\\Wtpdf\\Banners\\Wt_GDPR_Cta_Banner' ) ) {
	class Wt_GDPR_Cta_Banner {

		/**
		 * GDPR/Cookie plugins to check (common slugs / main files).
		 * Extend this list as needed.
		 * Format: 'plugin-dir/plugin-main-file.php'
		 *
		 * @var array
		 */
		protected $gdpr_plugins = array(
			// Popular GDPR/Cookie plugins
			'webtoffee-gdpr-cookie-consent/cookie-law-info.php',     // WebToffee legacy
            'webtoffee-cookie-consent/webtoffee-cookie-consent.php', // WebToffee revamp
            'gdpr-cookie-consent-wp/webtoffee-cookie-consent.php',   // WebToffee marketplace version
			'cookie-law-info/cookie-law-info.php',                   // Legacy Cookie Law Info
			'complianz-gdpr/complianz-gdpr.php',                     // Complianz
			'cookiebot/cookiebot.php',                               // Cookiebot
			'iubenda-cookie-law-solution/iubenda_cookie_law.php',    // iubenda
			'cookie-notice/cookie-notice.php',                       // Cookie Notice & Compliance
			'quantcast-choices/quantcast-choices.php',               // Quantcast Choices
			'gdpr-cookie-compliance/moove-gdpr.php',                 // Moove GDPR
		);
        /**
         * Clarity plugin slug
         * 
         * @var string
         */
        protected $clarity_plugin = 'microsoft-clarity/clarity.php';

        /**
         * Google Site Kit plugin slug
         *
         * @var string
         */
        protected $sitekit_plugin = 'google-site-kit/google-site-kit.php';

        /**
         * Promotion link for when Clarity is active
         * 
         * @var string 
         */
        private static $clarity_promotion_link = "https://www.webtoffee.com/product/gdpr-cookie-consent/?utm_source=free_plugin_pdf_invoice&utm_medium=pdf_invoice_premium&utm_campaign=GDPR_Clarity";

        /**
         * Default promotion link
         * 
         * @var string
         */
        private static $default_promotion_link = "https://www.webtoffee.com/product/gdpr-cookie-consent/?utm_source=free_plugin_pdf_invoice&utm_medium=pdf_invoice_premium&utm_campaign=GDPR";

        /**
         * Promotion link for when Google Site Kit is active
         *
         * @var string
         */
        private static $sitekit_promotion_link = "https://www.webtoffee.com/product/gdpr-cookie-consent/?utm_source=free_plugin_pdf_invoice&utm_medium=pdf_invoice_premium&utm_campaign=GDPR_SiteKit";

        /**
         * Current promotion link (set after conditions are checked)
         * 
         * @var string
         */
        protected $promotion_link = '';

        /**
         * Banner text (set based on Clarity/Site Kit status)
         *
         * @var string
         */
        protected $banner_text = '';

        /**
         * Is Clarity active flag
         *
         * @var bool|null
         */
        protected $is_clarity_active = null;

        /**
         * Is Google Site Kit active flag
         *
         * @var bool|null
         */
        protected $is_sitekit_active = null;

        /**
         * Check if Microsoft Clarity plugin is active
         *
         * @return bool
         */
        protected function is_clarity_active() {
            if ( $this->is_clarity_active === null ) {
                $this->is_clarity_active = $this->is_plugin_active_anywhere( $this->clarity_plugin );
            }
            return $this->is_clarity_active;
        }

        /**
         * Check if Google Site Kit plugin is active
         *
         * @return bool
         */
        protected function is_sitekit_active() {
            if ( $this->is_sitekit_active === null ) {
                $this->is_sitekit_active = $this->is_plugin_active_anywhere( $this->sitekit_plugin );
            }
            return $this->is_sitekit_active;
        }

        /**
         * Set the appropriate promotion link and banner text based on Clarity/Site Kit status.
         * Priority: Site Kit (IAB TCF) > Clarity > default.
         * Called after all banner conditions are met.
         *
         * @return void
         */
        protected function set_banner_contents() {
            if ( $this->is_sitekit_active() ) {
                $this->promotion_link = self::$sitekit_promotion_link;
                $this->banner_text = __( 'Under IAB Europe’s TCF policy, publishers participating in TCF must upgrade to TCF v2.3 by <b>28 February 2026</b> to remain compliant. <br>Ensure uninterrupted ad monetization with the <b>WebToffee GDPR Cookie Consent Plugin</b> – fully supporting <b>TCF v2.3</b>.', 'print-invoices-packing-slip-labels-for-woocommerce' );
            } elseif ( $this->is_clarity_active() ) {
                $this->promotion_link = self::$clarity_promotion_link;
                $this->banner_text = __( '<b>Important Update:</b> Starting October 31, 2025, Microsoft requires websites to use Clarity Consent V2 to continue collecting analytical data. <br>Ensure compliance by upgrading to the GDPR Cookie Consent Plugin, now fully compatible with <b>Clarity Consent V2</b> and <b>Google Consent Mode</b>.', 'print-invoices-packing-slip-labels-for-woocommerce' );
            } else {
                $this->promotion_link = self::$default_promotion_link;
                $this->banner_text = __( 'Stay compliant with GDPR and US Privacy Laws using our Google-certified CMP plugin—now with <b>Microsoft Clarity Consent V2</b>, <br><b>UET Consent Mode</b> and <b>Google Consent Mode</b> support.', 'print-invoices-packing-slip-labels-for-woocommerce' );
            }
        }

		public function __construct() {
			// Wait for WooCommerce to be fully loaded before checking conditions
			add_action( 'woocommerce_loaded', array( $this, 'init_banner' ) );
			
			// Also check on admin_init as fallback
			add_action( 'admin_init', array( $this, 'init_banner' ) );
			
			// AJAX dismiss (always available)
			add_action( 'wp_ajax_wt_dismiss_gdpr_cta_banner', array( $this, 'dismiss_banner' ) );
		}

		/**
		 * Initialize banner after WooCommerce is loaded
		 */
		public function init_banner() {
			// Check if we've already initialized to avoid duplicate calls
			if ( did_action( 'wt_gdpr_banner_initialized' ) ) {
				return;
			}

			// Check conditions - only proceed if banner should be shown
			if ( ! $this->should_show_banner() ) {
				return;
			}

			// Mark as initialized
			do_action( 'wt_gdpr_banner_initialized' );

			// Render as admin notice instead of meta box
			add_action( 'admin_notices', array( $this, 'render_banner_notice' ) );
			add_action( 'network_admin_notices', array( $this, 'render_banner_notice' ) );
			
			// Enqueue scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		/**
		 * Enqueue required scripts/styles for GDPR banner.
		 *
		 * @param string $hook
		 */
		public function enqueue_scripts( $hook ) {
			// Enqueue GDPR banner specific CSS
			wp_enqueue_style(
				'wt-gdpr-cta-banner',
				plugin_dir_url( __FILE__ ) . 'assets/css/wt-gdpr-promotion-banner-cta.css',
				array(),
				defined( 'WF_PKLIST_VERSION' ) ? WF_PKLIST_VERSION : '1.0.0'
			);

			// Enqueue GDPR banner specific JavaScript
			wp_enqueue_script(
				'wt-gdpr-cta-banner',
				plugin_dir_url( __FILE__ ) . 'assets/js/wt-gdpr-cta-banner.js',
				array( 'jquery' ),
				defined( 'WF_PKLIST_VERSION' ) ? WF_PKLIST_VERSION : '1.0.0',
				true
			);

			// Localize script for AJAX
			wp_localize_script(
				'wt-gdpr-cta-banner',
				'wt_gdpr_cta_banner_ajax',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'wt_dismiss_gdpr_cta_banner_nonce' ),
					'action'   => 'wt_dismiss_gdpr_cta_banner',
				)
			);
		}

	/**
	 * Check if banner should be shown based on all conditions.
	 *
	 * @return bool
	 */
	protected function should_show_banner() {
		// Check if banner was dismissed
		if ( get_option( 'wt_hide_gdpr_cta_banner', false ) ) {
			return false;
		}
		// Check if GDPR promotion banner state is closed by user
		if ( 2 === get_option( 'wt_gdpr_promotion_banner_state' ) ) {
			return false;
		}

		// Check if we're on the right pages using multiple methods
		global $pagenow;
		
		// Get page from $_GET instead of global $page
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		$is_plugins_page = ($pagenow === 'plugins.php');
		$is_invoice_page = ($page === 'wf_woocommerce_packing_list' || 
							$page === 'wf_woocommerce_packing_list_invoice' || 
							$page === 'wf_woocommerce_packing_list_packinglist' || 
							$page === 'wf_woocommerce_packing_list_deliverynote' || 
							$page === 'wf_woocommerce_packing_list_shippinglabel' || 
							$page === 'wf_woocommerce_packing_list_dispatchlabel' || 
							$page === 'wf_woocommerce_packing_list_picklist' || 
							$page === 'wf_woocommerce_packing_list_creditnote' || 
							$page === 'wf_woocommerce_packing_list_proformainvoice');
		$is_admin_page = is_admin();
		
		// Show banner only on plugins page OR invoice page (and must be in admin)
		if (!$is_admin_page || (!$is_plugins_page && !$is_invoice_page)) {
			return false;
		}
		// Check if any GDPR plugin is active
		if ( $this->is_any_gdpr_plugin_active() ) {
			return false;
		}
		
		// Check if store should comply with GDPR (either store in EU or selling to EU)
		if ( ! $this->should_comply_with_gdpr() ) {
			return false;
		}
		
		// All conditions met, store the appropriate promotion link based on Clarity status
		// This ensures we only check for Clarity when banner will actually be shown
		$this->set_banner_contents();
		
		return true;
	}

		/**
		 * Show the GDPR CTA as an admin notice (instead of meta box).
		 * Only prints markup when all gating conditions pass.
		 */
		public function render_banner_notice() {
			// Determine banner variant class based on Clarity status
			$banner_variant_class = $this->is_clarity_active() ? 'wt-banner-clarity-active' : 'wt-banner-clarity-inactive';
			?>
			<div id="wt_gdpr_cta_banner" class="wt-gdpr-promotion-banner-cta notice notice-info is-dismissible <?php echo esc_attr($banner_variant_class); ?>">
                    <div class="wt-gdpr-promotion-banner-content-wrap">
                        <div class="wt-header-section">
                            <p class="wt-header-title"><?php echo esc_html( $this->is_sitekit_active() ? __( 'Are you running third-party ads?', 'print-invoices-packing-slip-labels-for-woocommerce' ) : __( 'Ensure Cookie Compliance for Your WordPress Website', 'print-invoices-packing-slip-labels-for-woocommerce' ) ); ?></p>
                        </div>
                        <div class="wt-body-section">
                            <div class="wt-body-content">

                                <p class="wt-body-text">
                                    <?php echo wp_kses_post($this->banner_text); ?>
                                </p>
                            </div>

                            <div class="wt-button-wrap">
                                <div class="wt-button">
                                    <a href="<?php echo esc_url($this->promotion_link); ?>" class="product-page-btn" target="_blank"><?php esc_html_e('Get plugin now', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
                                </div>
                                <div class="certificate-section-wrap">
                                    <div class="certificate-image">
                                        <img src="<?php echo esc_url(plugin_dir_url( __FILE__ ) . 'assets/images/gdpr.svg'); ?>" alt="<?php echo esc_attr__('Certified Partner', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
			<?php
		}

		/**
		 * AJAX dismiss handler (same behavior; now used by notice).
		 */
		public function dismiss_banner() {

			if (
				! isset( $_POST['nonce'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wt_dismiss_gdpr_cta_banner_nonce' )
			) {
				wp_send_json_error( 'Invalid nonce' );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( 'Insufficient permissions' );
			}

			update_option( 'wt_hide_gdpr_cta_banner', true );

			wp_send_json_success( 'Banner dismissed successfully' );
		}

		/* ----------------------- Helpers ----------------------- */


		/**
		 * Returns true if any known GDPR/cookie plugin is active.
		 *
		 * @return bool
		 */
		protected function is_any_gdpr_plugin_active() {
			foreach ( $this->gdpr_plugins as $slug ) {
				if ( $this->is_plugin_active_anywhere( $slug ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Network-aware plugin active check.
		 *
		 * @param string $plugin_file
		 * @return bool
		 */
		protected function is_plugin_active_anywhere( $plugin_file ) {
			$active_plugins = (array) get_option( 'active_plugins', array() );

			if ( is_multisite() ) {
				$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
				if ( isset( $network_plugins[ $plugin_file ] ) ) {
					return true;
				}
			}

			return in_array( $plugin_file, $active_plugins, true );
		}

		/**
		 * Check if the store base country is in the EU or US region.
		 *
		 * @return bool
		 */
		protected function is_store_in_eu_or_us() {
			// Ensure WooCommerce is loaded
			if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
				return false;
			}

			$base_country = WC()->countries->get_base_country();
			$eu_countries = $this->get_eu_countries_list();
			$us_country = array( 'US' );

			// Check if store is in EU or US
			$is_eu = $base_country && in_array( strtoupper( $base_country ), $eu_countries, true );
			$is_us = $base_country && in_array( strtoupper( $base_country ), $us_country, true );

			return $is_eu || $is_us;
		}

		/**
		 * Check if the store is selling to EU or US customers.
		 * This is more relevant for GDPR compliance as GDPR applies to EU residents
		 * and US privacy laws apply to US residents regardless of where the business is located.
		 *
		 * @return bool
		 */
		protected function is_selling_to_eu_or_us() {
			// Ensure WooCommerce is loaded
			if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
				return false;
			}

			$eu_countries = $this->get_eu_countries_list();
			$us_country = array( 'US' );
			$target_countries = array_merge( $eu_countries, $us_country );
			
			$allowed_countries_setting = get_option( 'woocommerce_allowed_countries', 'all' );
			
			// If selling to all countries, assume EU and US are included
			if ( 'all' === $allowed_countries_setting ) {
				return true;
			}

			// If specific countries are set, check if any EU or US countries are included
			if ( 'specific' === $allowed_countries_setting ) {
				$specific_countries = get_option( 'woocommerce_specific_allowed_countries', array() );
				
				foreach ( $target_countries as $target_country ) {
					if ( in_array( $target_country, $specific_countries, true ) ) {
						return true;
					}
				}
			}

			// If all except certain countries, check if EU or US is not excluded
			if ( 'all_except' === $allowed_countries_setting ) {
				$excluded_countries = get_option( 'woocommerce_all_except_countries', array() );
				
				foreach ( $target_countries as $target_country ) {
					if ( ! in_array( $target_country, $excluded_countries, true ) ) {
						return true;
					}
				}
			}

			return false;
		}

		/**
		 * Get the list of EU countries.
		 * Uses WooCommerce's method if available, otherwise falls back to static list.
		 *
		 * @return array
		 */
		protected function get_eu_countries_list() {
			$eu_countries = array();

			// Try to get WooCommerce's EU list first
			if ( function_exists( 'WC' ) && WC()->countries && method_exists( WC()->countries, 'get_european_union_countries' ) ) {
				$eu_countries = WC()->countries->get_european_union_countries();
			}

			// Fallback to static list if WooCommerce method not available
			if ( empty( $eu_countries ) ) {
				$eu_countries = array( 'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE' );
			}

			return $eu_countries;
		}


		/**
		 * Check if the store should comply with GDPR based on either:
		 * 1. Store is located in EU or US, OR
		 * 2. Store is selling to EU or US customers
		 *
		 * @return bool
		 */
		protected function should_comply_with_gdpr() {
			// Check if store is in EU/US or selling to EU/US
			if ( $this->is_store_in_eu_or_us() || $this->is_selling_to_eu_or_us() ) {
				return true;
			}

			return false;
		}
	}

	new \Wtpdf\Banners\Wt_GDPR_Cta_Banner();
}
