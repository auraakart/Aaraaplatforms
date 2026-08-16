<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

/**
 * Packinglist section of the plugin
 *
 * @link
 * @since 2.5.0
 *
 * @package  Wf_Woocommerce_Packing_List
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wf_Woocommerce_Packing_List_Packinglist {

	public $module_id               = '';
	public static $module_id_static = '';
	public $module_base             = 'packinglist';
	public $module_title            = '';
	public $customizer              = null;

	public function __construct() {
		$this->module_id        = Wf_Woocommerce_Packing_List::get_module_id( $this->module_base );
		self::$module_id_static = $this->module_id;
		add_action( 'init', array( $this, 'load_translations_and_strings' ) );

		/* @since 4.0.0 add admin menu */
		add_filter( 'wt_admin_menu', array( $this, 'add_admin_pages' ), 10, 1 );

		add_filter( 'wf_module_default_settings', array( $this, 'default_settings' ), 10, 2 );

		add_filter( 'wf_module_customizable_items', array( $this, 'get_customizable_items' ), 10, 2 );
		add_filter( 'wf_module_non_options_fields', array( $this, 'get_non_options_fields' ), 10, 2 );
		add_filter( 'wf_module_non_disable_fields', array( $this, 'get_non_disable_fields' ), 10, 2 );

		add_filter( 'wt_pklist_customizer_preview_only', array( $this, 'enable_preview_only_mode' ), 10, 2 );

		add_filter( 'wf_module_convert_to_design_view_html_for_' . $this->module_base, array( $this, 'convert_to_design_view_html' ), 10, 3 );

		// hook to generate template html
		add_filter( 'wf_module_generate_template_html_for_' . $this->module_base, array( $this, 'generate_template_html' ), 10, 6 );
		add_action( 'wt_print_doc', array( $this, 'print_it' ), 10, 2 );

		// hide empty fields on template
		add_filter( 'wf_pklist_alter_hide_empty', array( $this, 'hide_empty_elements' ), 10, 6 );

		// filter to alter settings
		add_filter( 'wf_pklist_alter_settings', array( $this, 'alter_settings' ), 10, 2 );
		add_filter( 'wf_pklist_alter_option', array( $this, 'alter_option' ), 10, 4 );

		// alter product table column
		add_filter( 'wf_pklist_alter_product_table_head', array( $this, 'alter_product_table_head' ), 10, 3 );

		// initializing customizer
		$this->customizer = Wf_Woocommerce_Packing_List::load_modules( 'customizer' );

		// add print packingslip buttons
		add_filter( 'wt_print_actions', array( $this, 'add_print_buttons' ), 10, 4 );
		add_filter( 'wt_print_bulk_actions', array( $this, 'add_bulk_print_buttons' ) );

		add_filter( 'wf_pklist_alter_find_replace', array( $this, 'alter_find_replace' ), 10, 5 );
		add_filter( 'wt_pklist_alter_tooltip_data', array( $this, 'register_tooltips' ), 1 );
		add_filter( 'wt_pklist_individual_print_button_for_document_types', array( $this, 'add_individual_print_button_in_admin_order_listing_page' ), 10, 1 );
		add_filter( 'woocommerce_admin_order_actions_end', array( $this, 'document_print_btn_on_wc_order_listing_action_column' ), 10, 1 );
	}

	public function load_translations_and_strings() {
		$this->module_title = __( 'Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' );
	}

	/**
	 *  @since 2.5.8
	 *  Hook the tooltip data to main tooltip array
	 */
	public function register_tooltips( $tooltip_arr ) {
		include plugin_dir_path( __FILE__ ) . 'data/data.tooltip.php';
		$tooltip_arr[ $this->module_id ] = $arr;
		return $tooltip_arr;
	}

	/**
	 *  Add admin menu
	 *
	 *  @since  4.0.0
	 */
	public function add_admin_pages( $menus ) {
		$menus[] = array(
			'submenu',
			WF_PKLIST_POST_TYPE,
			__( 'Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
			__( 'Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
			'manage_woocommerce',
			$this->module_id,
			array( $this, 'admin_settings_page' ),
		);
		return $menus;
	}

	public function alter_find_replace( $find_replace, $template_type, $order, $box_packing, $order_package ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( $template_type === $this->module_base && ! $is_pro_customizer ) {
			$is_footer = Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_footer_pk', $this->module_id );
			if ( 'Yes' !== $is_footer ) {
				$find_replace['wfte_footer'] = 'wfte_hidden';
			}
		}
		return $find_replace;
	}

	public function alter_product_table_head( $columns_list_arr, $template_type, $order ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( $template_type === $this->module_base && ! $is_pro_customizer ) {
			$columns_config = array(
				'image' => array(
					'option'  => 'woocommerce_wf_attach_image_',
					'default' => 'Image',
				),
				'sku'   => array(
					'option'  => 'woocommerce_wf_attach_sku_',
					'default' => 'SKU',
				),
			);

			foreach ( $columns_config as $column_key => $config ) {
				$is_enabled = Wf_Woocommerce_Packing_List::get_option( $config['option'] . $this->module_base, $this->module_id );
				$hidden_key = '-' . $column_key;
				$from_key   = ( 'No' === $is_enabled ) ? $column_key : $hidden_key;
				$to_key     = ( 'No' === $is_enabled ) ? $hidden_key : $column_key;

				if ( isset( $columns_list_arr[ $from_key ] ) ) {
					// Rebuild array to preserve position when swapping keys
					$new_arr = array();
					foreach ( $columns_list_arr as $k => $v ) {
						$new_arr[ $k === $from_key ? $to_key : $k ] = $v;
					}
					$columns_list_arr = $new_arr;
				} elseif ( 'No' !== $is_enabled && ! isset( $columns_list_arr[ $column_key ] ) ) {
					$columns_list_arr[ $column_key ] = $config['default'];
				}
			}
		}
		return $columns_list_arr;
	}

	public function alter_option( $vl, $settings, $option_name, $base_id ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( $base_id === $this->module_id && ! $is_pro_customizer ) {
			if ( 'wf_' . $this->module_base . '_contactno_email' === $option_name && is_array( $vl ) ) {
				if ( 'Yes' === $settings[ 'woocommerce_wf_add_customer_note_in_' . $this->module_base ] ) {
					if ( false === array_search( 'cus_note', $vl ) ) {
						$vl[] = 'cus_note';
					}
				} elseif ( false !== ( $key = array_search( 'cus_note', $vl ) ) ) {
						unset( $vl[ $key ] );
				}
			}
		}
		return $vl;
	}
	public function alter_settings( $settings, $base_id ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( ! $is_pro_customizer && $base_id === $this->module_id ) {
			$vl = $settings[ 'wf_' . $this->module_base . '_contactno_email' ];
			$vl = is_array( $vl ) ? $vl : array();
			if ( 'Yes' === $settings[ 'woocommerce_wf_add_customer_note_in_' . $this->module_base ] ) {
				if ( false === array_search( 'cus_note', $vl ) ) {
					$vl[] = 'cus_note';
				}
			} elseif ( false !== ( $key = array_search( 'cus_note', $vl ) ) ) {
					unset( $vl[ $key ] );
			}
			$settings[ 'wf_' . $this->module_base . '_contactno_email' ] = $vl;
		}
		return $settings;
	}

	public function hide_empty_elements( $hide_on_empty_fields, $template_type ) {
		if ( $template_type === $this->module_base ) {
			$hide_on_empty_fields[] = 'wfte_qr_code';
			$hide_on_empty_fields[] = 'wfte_box_name';
		}
		return $hide_on_empty_fields;
	}

	public function convert_to_design_view_html( $find_replace, $html, $template_type ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $template_type, false, $template_type );
		if ( $template_type === $this->module_base && ! $is_pro_customizer ) {
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_billing_address( $find_replace, $template_type );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_shipping_address( $find_replace, $template_type );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_default_order_fields( $find_replace, $template_type, $html );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_product_table( $find_replace, $template_type, $html );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_other_data( $find_replace, $template_type, $html );
		}
		return $find_replace;
	}

	public function get_customizable_items( $settings, $base_id ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( $base_id === $this->module_id && ! $is_pro_customizer ) {
			$only_pro_html = '<span class="wt_customizer_pro_text" style="color:red;"> (' . __( 'Pro version', 'print-invoices-packing-slip-labels-for-woocommerce' ) . ')</span>';
			// these fields are the classname in template Eg: `company_logo` will point to `wfte_company_logo`
			$settings = array(
				'product_table'               => __( 'Product Table', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'shipping_address'            => __( 'Shipping Address', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'customer_note'               => __( 'Customer note', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'footer'                      => __( 'Footer', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				// Working features with Pro label (fully functional)
				'ssn_number_pro_element'      => __( 'SSN number', 'print-invoices-packing-slip-labels-for-woocommerce' ) . $only_pro_html,
				'vat_number_pro_element'      => __( 'VAT number', 'print-invoices-packing-slip-labels-for-woocommerce' ) . $only_pro_html,
				'tracking_number_pro_element' => __( 'Tracking Number', 'print-invoices-packing-slip-labels-for-woocommerce' ) . $only_pro_html,
			);
		}
		return $settings;
	}

	public function get_non_options_fields( $settings, $base_id ) {
		if ( $base_id === $this->module_id ) {
			return array(
				'footer',
			);
		}
		return $settings;
	}

	public function get_non_disable_fields( $settings, $base_id ) {
		if ( $base_id === $this->module_id ) {
			return array();
		}
		return $settings;
	}

	public function enable_preview_only_mode( $preview_only, $template_type ) {
		if ( $template_type === $this->module_base ) {
			$pro_invoice_path = 'wt-woocommerce-invoice-addon/wt-woocommerce-invoice-addon.php';
			if ( ! is_plugin_active( $pro_invoice_path ) ) {
				return true;
			}
			return false;
		}
		return $preview_only;
	}

	public function admin_settings_page() {
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WF_PKLIST_VERSION );
		wp_enqueue_media();
		do_action( 'wt_pklist_customizer_enable', $this->module_id, $this->module_base );
		$template_type = $this->module_base;
		include_once WF_PKLIST_PLUGIN_PATH . '/admin/views/premium_extension_listing.php';

		if ( ! is_null( $this->customizer ) && true === apply_filters( 'wt_pklist_switch_to_classic_customizer_' . $this->module_base, true, $this->module_base ) ) {
			$this->customizer->init( $this->module_base );
		}

		include plugin_dir_path( __FILE__ ) . 'views/admin-settings.php';
	}

	/**
	 *  Items needed to be converted to HTML for print
	 */
	public function generate_template_html( $find_replace, $html, $template_type, $order, $box_packing = null, $order_package = null ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( $template_type === $this->module_base && ! $is_pro_customizer ) {
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_billing_address( $find_replace, $template_type, $order );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_shipping_address( $find_replace, $template_type, $order );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_default_order_fields( $find_replace, $template_type, $html, $order );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_product_table( $find_replace, $template_type, $html, $order, $box_packing, $order_package );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_other_data( $find_replace, $template_type, $html, $order );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_order_data( $find_replace, $template_type, $html, $order );
			$find_replace = Wf_Woocommerce_Packing_List_CustomizerLib::set_extra_fields( $find_replace, $template_type, $html, $order );
		}
		return $find_replace;
	}

	/**
	 *  Module default settings
	 */
	public function default_settings( $settings, $base_id ) {
		if ( $base_id === $this->module_id ) {
			return array(
				'woocommerce_wf_attach_image_packinglist' => 'Yes',
				'woocommerce_wf_attach_sku_packinglist'   => 'Yes',
				'woocommerce_wf_add_customer_note_in_packinglist' => 'No',
				'woocommerce_wf_packinglist_footer_pk'    => 'No',
				'woocommerce_wf_packinglist_variation_data' => 'Yes', // Add product variation data
				'wf_' . $this->module_base . '_contactno_email' => array( 'contact_number', 'email' ),
			);
		} else {
			return $settings;
		}
	}

	public function add_bulk_print_buttons( $actions ) {
		$actions['print_packinglist'] = __( 'Print Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' );
		return $actions;
	}

	/**
	 *  Adding print/download options in Order list/detail page
	 */
	public function add_print_buttons( $item_arr, $order, $order_id, $button_location ) {
		if ( 'detail_page' === $button_location ) {
			$item_arr['packinglist_details_actions'] = array(
				'button_type'     => 'aggregate',
				'button_key'      => 'packinglist_actions', // unique if multiple on same page
				'button_location' => $button_location,
				'action'          => '',
				'label'           => __( 'Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'tooltip'         => __( 'Print/Download Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'is_show_prompt'  => 0, // always 0
				'items'           => array(
					'print_packinglist'    => array(
						'action'          => 'print_packinglist',
						'label'           => __( 'Print', 'print-invoices-packing-slip-labels-for-woocommerce' ),
						'tooltip'         => __( 'Print Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
						'is_show_prompt'  => 0,
						'button_location' => $button_location,
					),
					'download_packinglist' => array(
						'action'          => 'download_packinglist',
						'label'           => __( 'Download', 'print-invoices-packing-slip-labels-for-woocommerce' ),
						'tooltip'         => __( 'Download Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
						'is_show_prompt'  => 0,
						'button_location' => $button_location,
					),
				),
				'exist'           => Wf_Woocommerce_Packing_List_Admin::check_doc_already_created( $order, $order_id, 'packinglist' ),
			);
		} else {
			$item_arr[] = array(
				'action'          => 'print_packinglist',
				'label'           => __( 'Print Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'tooltip'         => __( 'Print Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'is_show_prompt'  => 0,
				'button_location' => $button_location,
			);
			$item_arr[] = array(
				'action'          => 'download_packinglist',
				'label'           => __( 'Download Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'tooltip'         => __( 'Download Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' ),
				'is_show_prompt'  => 0,
				'button_location' => $button_location,
			);
		}

		return $item_arr;
	}
	/*
	* Print_window for packinglist
	* @param $orders : order ids
	*/
	public function print_it( $order_ids, $action ) {
		$is_pro_customizer = apply_filters( 'wt_pklist_pro_customizer_' . $this->module_base, false, $this->module_base );
		if ( ! $is_pro_customizer ) {
			if ( 'print_packinglist' === $action || 'download_packinglist' === $action ) {
				if ( ! is_array( $order_ids ) ) {
					return;
				}
				if ( ! is_null( $this->customizer ) ) {
					if ( count( $order_ids ) > 1 ) {
						$sort_order = apply_filters( 'wt_pklist_sort_orders', 'desc', $this->module_base, $action ); // To choose the sorting of the orders when doing bulk print or download.
						if ( 'asc' === $sort_order ) {
							sort( $order_ids );
						}
					}

					$pdf_name                           = $this->customizer->generate_pdf_name( $this->module_base, $order_ids );
					$this->customizer->template_for_pdf = ( 'download_packinglist' === $action ? true : false );
					$html                               = $this->generate_order_template( $order_ids, $pdf_name );
					if ( 'download_packinglist' === $action ) {
						$this->customizer->generate_template_pdf( $html, $this->module_base, $pdf_name, 'download' );
					} else {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted HTML generated by packinglist module
						echo $html;
					}
				}
				exit();
			}
		}
	}
	public function generate_order_template( $orders, $page_title ) {
		$template_type = $this->module_base;
		// taking active template html
		$html         = $this->customizer->get_template_html( $template_type );
		$style_blocks = $this->customizer->get_style_blocks( $html );
		$html         = $this->customizer->remove_style_blocks( $html, $style_blocks );
		$out          = '';
		if ( '' !== $html ) {
			if ( ! class_exists( 'Wf_Woocommerce_Packing_List_Box_packing_Basic' ) ) {
				include_once WF_PKLIST_PLUGIN_PATH . 'includes/class-wf-woocommerce-packing-list-box_packing.php';
			}
			$box_packing = new Wf_Woocommerce_Packing_List_Box_packing_Basic();
			$out_arr     = array();
			foreach ( $orders as $order_id ) {
				$order = wc_get_order( $order_id );

				/**
				 * @since 4.6.0 - Added filter to add before preparing the order package and rendering the html.
				 */
				$pdf_filters = apply_filters( 'wt_pklist_add_filters_before_rendering_pdf', array(), $this->module_base, $order );
				Wt_Pklist_Common::wt_pklist_pdf_add_filters( $pdf_filters );

				$order_packages          = null;
				$order_packages          = $box_packing->wf_pklist_create_order_single_package( $order );
				$number_of_order_package = count( $order_packages );
				if ( ! empty( $order_packages ) ) {
					$order_pack_inc = 0;
					foreach ( $order_packages as $order_package_id => $order_package ) {
						++$order_pack_inc;
						$order     = wc_get_order( $order_id );
						$out_arr[] = $this->customizer->generate_template_html( $html, $template_type, $order, $box_packing, $order_package );
					}
					$document_created = Wf_Woocommerce_Packing_List_Admin::created_document_count( $order_id, $template_type );
				} else {
					$no_item_error_message = __( 'Unable to print packling slip. Please check the items in the order.', 'print-invoices-packing-slip-labels-for-woocommerce' );
					if ( 'No' === Wf_Woocommerce_Packing_List::get_option( 'woocommerce_wf_packinglist_preview' ) && 'print_packinglist' === $action ) {
						header( 'Content-Type: text/plain' );
						// Sanitize the error message to avoid potential issues.
						$no_item_error_message = htmlspecialchars( $no_item_error_message, ENT_QUOTES, 'UTF-8' );
						// Return the error message.
						echo esc_html( $no_item_error_message );
						// Make sure to stop further execution.
						exit();
					} else {
						wp_die( esc_html( $no_item_error_message ), '', array() );
					}
				}

				/**
				 * @since 4.6.0 - Remove the filters which were added before preparing the order package and rendering the html.
				 */
				Wt_Pklist_Common::wt_pklist_pdf_remove_filters( $pdf_filters );
			}
			$out = implode( '<p class="pagebreak"></p>', $out_arr ) . '<p class="no-page-break"></p>';

			$out = $this->customizer->append_style_blocks( $out, $style_blocks );
			// adding header and footer
			$out = $this->customizer->append_header_and_footer_html( $out, $template_type, $page_title );
		}
		return $out;
	}

	/**
	 * Add the document type as one of the options for the individual print button access
	 *
	 * @param array $documents
	 * @return array
	 */
	public function add_individual_print_button_in_admin_order_listing_page( $documents ) {
		if ( ! in_array( $this->module_base, $documents ) ) {
			$documents[ $this->module_base ] = __( 'Packing slip', 'print-invoices-packing-slip-labels-for-woocommerce' );
		}
		return $documents;
	}

	/**
	 * Add document print button as per the 'wt_pklist_separate_print_button_enable' value
	 *
	 * @since 4.2.0
	 * @param object $order
	 * @return void
	 */
	public function document_print_btn_on_wc_order_listing_action_column( $order ) {
		$show_print_button = apply_filters( 'wt_pklist_show_document_print_button_action_column_free', true, $this->module_base, $order );

		if ( ! empty( $order ) && true === $show_print_button ) {
			$order_id = $order->get_id();

			if ( in_array( $this->module_base, Wf_Woocommerce_Packing_List::get_option( 'wt_pklist_separate_print_button_enable' ) ) ) {
				$btn_action_name = 'wt_pklist_print_document_' . $this->module_base . '_not_yet';
				$img_url         = WF_PKLIST_PLUGIN_URL . 'admin/images/' . $this->module_base . '.png';
				$order_docs      = Wt_Pklist_Common::get_order_meta( $order_id, '_created_document', true );
				$order_docs_old  = Wt_Pklist_Common::get_order_meta( $order_id, '_created_document_old', true );

				if ( ( ! empty( $order_docs ) && is_array( $order_docs ) && in_array( $this->module_base, $order_docs ) ) || ( ! empty( $order_docs_old ) && is_array( $order_docs_old ) && in_array( $this->module_base, $order_docs_old ) ) ) {
					$btn_action_name = 'wt_pklist_print_document_' . $this->module_base;
					$img_url         = WF_PKLIST_PLUGIN_URL . 'admin/images/' . $this->module_base . '_logo.png';
				}

				$action       = 'print_' . $this->module_base;
				$action_title = sprintf(
					'%1$s %2$s',
					__( 'Print', 'print-invoices-packing-slip-labels-for-woocommerce' ),
					$this->module_title
				);
				$print_url    = Wf_Woocommerce_Packing_List_Admin::get_print_url( $order_id, $action );
				echo '<a title="' . esc_attr( $action_title ) . '" class="button wc-action-button wc-action-button-' . esc_attr( $btn_action_name ) . ' ' . esc_attr( $btn_action_name ) . ' wt_pklist_action_btn wt_pklist_admin_print_document_btn" href="' . esc_url_raw( $print_url ) . '" aria-label="' . esc_attr( $action_title ) . '" target="_blank" style="padding:5px;"><img src="' . esc_url( $img_url ) . '"></a>';
			}
		}
	}
}
new Wf_Woocommerce_Packing_List_Packinglist();
