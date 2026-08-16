<?php
	require_once WPBINGO_CONTENT_TYPES_LIB . 'buy-together/menu-scripts-styles.php';
	require_once WPBINGO_CONTENT_TYPES_LIB . 'buy-together/helpers.php';
	require_once WPBINGO_CONTENT_TYPES_LIB . 'buy-together/load-products-data.php';
	require_once WPBINGO_CONTENT_TYPES_LIB . 'buy-together/backend.php';
	require_once WPBINGO_CONTENT_TYPES_LIB . 'buy-together/frontend.php';
	add_filter( 'woocommerce_product_data_tabs', 'product_data_tabs');
	add_action( 'woocommerce_product_data_panels', 'product_data_panels' );
	add_action( 'woocommerce_process_product_meta_simple','process_product_meta_wpbingo');

	function product_data_tabs( $tabs ) {
		$tabs['wpbingo'] = array(
			'label'  => esc_html__( 'Buy Together', 'wpbingo' ),
			'target' => 'bwp_settings',
			'class'  => array( 'show_if_simple' ),
		);
		
		return $tabs;
	}
	
	function product_data_panels() {
		global $post;
		$post_id                    = $post->ID;	
		?>
		<div id='bwp_settings' class='panel woocommerce_options_panel buy-together-table-wrap'>

			<table class="buy-together-table">
				<tr>
					<td width="250"><?php esc_html_e( 'Search', 'wpbingo' ); ?></td>
					<td>
						<div class="buy-together-inner-wrapper">
							<div class="buy-together-search-product-wrapper">
								<input type="text" id="bwp_keyword"
									   placeholder="<?php esc_html_e( 'Type any keyword to search', 'wpbingo' ); ?>"/>
								<div id="buy-together-results" class="buy-together-results"></div>
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<td width="250"><?php esc_html_e( 'Selected', 'wpbingo' ); ?></td>
					<td>
						<div class="buy-together-inner-wrapper">
							<input type="hidden" id="bwp_ids" class="bwp_ids" name="bwp_ids"
								   value="<?php echo get_post_meta( $post_id, 'bwp_ids', true ); ?>"
								   readonly/>
							<div class="buy-together-selected-products-list-wrap">
								<div class="buy-together-selected-products-list buy-together-sortable">
									<?php
									$bwp_total_price = 0;
									if ( get_post_meta( $post_id, 'bwp_ids', true ) ) {
										$bwp_ids = get_post_meta( $post_id, 'bwp_ids', true );
										if ( trim( $bwp_ids ) != '' ) {
											$bwp_ids = explode( ',', $bwp_ids );
											if ( is_array( $bwp_ids ) ) {
												foreach ( $bwp_ids as $bwp_id ) {
													$bwp_id      = absint( $bwp_id );
													$bwp_product = wc_get_product( $bwp_id );
													if ( ! $bwp_product || ! $bwp_product->is_type( 'simple' ) ) {
														continue;
													}
													$min_price          = $bwp_product->get_price();
													$max_price          = $min_price;
													$bwp_total_price += $bwp_product->get_price();
													if ( $bwp_product->is_type( 'variable' ) ) {
														$min_price = $bwp_product->get_variation_price( 'min' );
														$max_price = $bwp_product->get_variation_price( 'max' );
													}
													?>
													<div class="selected-product-item"
														 data-product_id="<?php echo esc_attr( $bwp_id ); ?>"
														 data-min_price="<?php echo $min_price; ?>"
														 data-max_price="<?php echo $max_price; ?>">
														<div class="product-inner">
															<div class="post-thumb">
																<?php
																$image = bwp_resize_image( get_post_thumbnail_id( $bwp_id ), null, 60, 60, true, true, false );
																?>
																<img width="<?php echo esc_attr( $image['width'] ); ?>"
																	 height="<?php echo esc_attr( $image['height'] ); ?>"
																	 class="attachment-post-thumbnail wp-post-image"
																	 src="<?php echo esc_url( $image['url'] ); ?>"
																	 alt="<?php echo esc_attr( $bwp_product->get_name() ); ?>"/>
															</div>
															<div class="product-info">
																<span class="product-title"><?php echo $bwp_product->get_name(); ?></span>
																<span>(<?php echo '#' . $bwp_id . ' - ' . $bwp_product->get_price_html(); ?>
																	)</span>
															</div>
														</div>
														<a href="#" class="remove-btn" title="Remove">x</a>
													</div>
													<?php
												}
											}
										}
									}
									?>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}
	
	/*
	 * Save bundle products data
	 */
	function process_product_meta_wpbingo( $post_id ) {
		if ( isset( $_POST['bwp_ids'] ) ) {
			update_post_meta( $post_id, 'bwp_ids', $_POST['bwp_ids'] );
		}
	}