<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'bwpBuyTogetherFrontend' ) ) {
	class bwpBuyTogetherFrontend {
		public $bwp_hook = 'woocommerce_product_tabs'; // Default hook
		
		public function __construct() {
			switch ( $this->bwp_hook ) {
				case 'woocommerce_product_tabs':
					add_filter( $this->bwp_hook, array( $this, 'bwp_woo_product_tab' ) );
					break;
				default:
					add_action( $this->bwp_hook, array( $this, 'bwp_content' ) );
					break;
			}
		}
		
		/**
		 * Add a custom product data tab
		 */
		
		public function bwp_woo_product_tab( $tabs ) {
			
			$default_tab_title = esc_html__( 'Frequently Bought Together', 'wpbingo' );
			$tab_title         = $default_tab_title;
			if ( is_singular( 'product' ) ) {
				global $product;
				$product_id                 = $product->get_id();
				$bwp_ids = get_post_meta( $product_id, 'bwp_ids', true );
				if ( ! $product->is_type( 'simple' ) ||  trim( $bwp_ids ) == '' ) {
					return $tabs;
				}
				$tab_title = get_post_meta( $product_id, 'bwp_title', true );
				if ( trim( $tab_title ) == '' ) {
					$tab_title = $default_tab_title;
				}
			}
			
			// Adds the new tab
			$tabs['bwp_tab'] = array(
				'title'    => $tab_title,
				'priority' => 1,
				'callback' => array( $this, 'bwp_woo_product_tab_content' )
			);
			
			return $tabs;
			
		}
		
		public function bwp_woo_product_tab_content() {
			$this->bwp_content();
		}
		
		public function bwp_content() {
		if ( is_singular( 'product' ) ) {
		global $product;
		if ( ! $product->is_type( 'simple' ) ) {
			return;
		}	
		$product_id       = $product->get_id();
		$bwp_ids = get_post_meta( $product_id, 'bwp_ids', true );
		if ( trim( $bwp_ids ) != '' ) {
			$add_to_cart_text = esc_html__( 'Add All To Cart', 'wpbingo' );
			$thumb_w = 300;
			$thumb_h = 300;
			$thumb_w = apply_filters( 'bwp_thumb_w', $thumb_w );
			$thumb_h = apply_filters( 'bwp_thumb_h', $thumb_h );
			$total_price = 0;
			$total_items = 0;
				
			// Check stock availability
			$availability       = $product->get_availability();
			$disabled           = '';
			$avai_text          = isset( $availability['availability'] ) ? $availability['availability'] : '';
			$avai_class         = isset( $availability['class'] ) ? $availability['class'] : '';
			$avai_class_product = $avai_class;
			$avai_text_html     = '';
			if ( ! $product->is_in_stock() ) {
				$avai_text_html     = '<span class="buy-together-avai-text buy-together-out-of-stock-splash out-of-stock-splash">' . $avai_text . '</span>';
				$avai_class         .= ' buy-together-out-of-stock';
				$avai_class_product .= ' buy-together-out-of-stock';
				$disabled           = 'disabled';
			} else {
				$total_items ++;
				$total_price += floatval( $product->get_price() );
			}
				
			$title      = get_post_meta( $product_id, 'bwp_title', true );
			$short_desc = get_post_meta( $product_id, 'bwp_short_desc', true );
			$after_text = get_post_meta( $product_id, 'bwp_after_text', true );
			
			$title_html                 = '';
			$short_desc_html            = '';
			$after_text_html            = '';
			$left_part_html             = '';
			$right_part_html            = '';
			$bwp_products_list_html  = '';
			$bwp_checkboxs_list_html = '';
			
				if ( trim( $title ) != '' && $this->bwp_hook != 'woocommerce_product_tabs' ) {
					$title_html = '<h3 class="buy-together-title">' . esc_html( $title ) . '</h3>';
				}
				
				if ( trim( $short_desc ) != '' ) {
					$short_desc_html = '<div class="buy-together-short-desc">' . wpautop( do_shortcode( $short_desc ) ) . '</div>';
				}
				if ( trim( $after_text ) != '' ) {
					$after_text_html = '<div class="buy-together-after-text">' . wpautop( do_shortcode( $after_text ) ) . '</div>';
				}
				
				$main_product_thumb        = bwp_resize_image( get_post_thumbnail_id( $product_id ), null, $thumb_w, $thumb_h, true, true, false );
				$bwp_products_list_html .= '<div data-product_id="' . esc_attr( $product_id ) . '" class="item-product buy-together-content ' . $avai_class_product . '">
												<div class="item-product-inner">
													<a href="' . esc_url( get_permalink( $product_id ) ) . '">
														<div class="thumbnail-wrap">
															' . bwp_img_output( $main_product_thumb, 'thumbnail' ) . '
														</div>
														<h3 class="item-product-title">' . get_the_title( $product_id ) . '</h3>
													</a>
													<div class="item-product-info">
														<div class="buy-together-price">' . $product->get_price_html() . '</div>
													</div>
													' . $avai_text_html . '
												</div>
											</div>';
				
				$bwp_checkboxs_list_html .= '<div data-product_id="' . esc_attr( $product_id ) . '" class="item-product buy-together-main-item ' . $avai_class . '">
										<label>
											<input data-price="' . floatval( $product->get_price() ) . '" data-product_id="' . esc_attr( $product_id ) . '" type="checkbox" ' . checked( true, $product->is_in_stock(), false ) . ' disabled />
											<span class="item-product-title"><strong>' . esc_html__( 'This product: ', 'wpbingo' ) . '</strong> ' . get_the_title( $product_id ) . '</span>
											<span class="buy-together-price">' . $product->get_price_html() . '</span>
										</label>
										' . $avai_text_html . '
									</div>';
				$bwp_ids = explode( ',', $bwp_ids );
				if ( ! empty( $bwp_ids ) ) {
					foreach ( $bwp_ids as $bwp_id ) {
						$bwp_product = wc_get_product( $bwp_id );
						if ( ! $bwp_product || $bwp_id == $product_id ) {
							continue;
						}
						if ( ! $bwp_product->is_type( 'simple' ) ) {
							continue;
						}
						
						// Check stock availability
						$availability       = $bwp_product->get_availability();
						$disabled           = '';
						$avai_text          = isset( $availability['availability'] ) ? $availability['availability'] : '';
						$avai_class         = isset( $availability['class'] ) ? $availability['class'] : '';
						$avai_class_product = $avai_class;
						$avai_text_html     = '';
						if ( ! $bwp_product->is_in_stock() ) {
							$avai_text_html     = '<span class="buy-together-avai-text buy-together-out-of-stock-splash out-of-stock-splash">' . $avai_text . '</span>';
							$avai_class         .= ' buy-together-out-of-stock';
							$avai_class_product .= ' buy-together-out-of-stock buy-together-hidden';
							$disabled           = 'disabled';
						} else {
							$total_items ++;
							$total_price += floatval( $bwp_product->get_price() );
						}
						
						$bwp_thumb = bwp_resize_image( get_post_thumbnail_id( $bwp_id ), null, $thumb_w, $thumb_h, true, true, false );
						
						$bwp_products_list_html .= '<div data-product_id="' . esc_attr( $bwp_id ) . '" class="item-product ' . $avai_class_product . '">
												<div class="item-product-inner">
													<a href="' . esc_url( get_permalink( $bwp_id ) ) . '">
														<div class="thumbnail-wrap">
															' . bwp_img_output( $bwp_thumb, 'thumbnail' ) . '
														</div>
														<h3 class="item-product-title">' . get_the_title( $bwp_id ) . '</h3>
													</a>
													<div class="item-product-info">
														<div class="buy-together-price">' . $bwp_product->get_price_html() . '</div>
													</div>
													' . $avai_text_html . '
												</div>
											</div>';
						
						$bwp_checkboxs_list_html .= '<div data-product_id="' . esc_attr( $bwp_id ) . '" class="item-product ' . $avai_class . '">
												<label>
													<input data-price="' . floatval( $bwp_product->get_price() ) . '" data-product_id="' . esc_attr( $bwp_id ) . '" type="checkbox" ' . checked( true, $bwp_product->is_in_stock(), false ) . ' ' . $disabled . ' />
													<span class="item-product-title">' . get_the_title( $bwp_id ) . '</span>
													<span class="buy-together-price">' . $bwp_product->get_price_html() . '</span>
												</label>
												' . $avai_text_html . '
											</div>';
						
					}
				}
				$total_price_html = '<div class="total-price-wrap">
										<div class="total-price-html">' . wc_price( $total_price ) . '</div>
										<span class="for-items-text">' . sprintf( esc_html__( 'For %s item(s)', 'wpbingo' ), $total_items ) . '</span>
									</div>';
				
				$add_all_to_cart_btn_html = '<div class="buy-together-add-all-to-cart-btn-wrap"> <button data-count_success="0" data-count_fail="0" type="button" class="button btn btn-primary buy-together-add-all-to-cart">' . $add_to_cart_text . '</button></div>';
			
				$right_part_html          = '<div class="buy-together-right-part">' . $total_price_html . $add_all_to_cart_btn_html . '</div>';
				$bwp_products_list_html  = '<div class="item-products-wrap"><div class="row">' . $bwp_products_list_html . $right_part_html . '</div></div>';
				$bwp_checkboxs_list_html = '<div class="item-products-wrap wpb-check">' . $bwp_checkboxs_list_html . '</div>';
				$left_part_html             = '<div class="buy-together-products">' . $bwp_products_list_html . $bwp_checkboxs_list_html . '</div>';
				
				$buy_together_html = '<div class="buy-together-wrap">
								' . $title_html . '
								' . $short_desc_html . '
								<div class="row">
									<div class="col-xs-12 col-sm-12">' . $left_part_html . '</div>
								 </div>
								 ' . $after_text_html . '
								</div>';
				
				echo $buy_together_html;
			}
		}
	}
}
	
	new bwpBuyTogetherFrontend();
}

