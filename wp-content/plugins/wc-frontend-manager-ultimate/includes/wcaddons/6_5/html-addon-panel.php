<?php
if (! defined('ABSPATH')) {
    exit;
}

$has_addons = ( ! empty($product_addons) && 0 < count($product_addons) ) ? 'wc-pao-has-addons' : '';
?>
<div id="product_addons_data" class="panel woocommerce_options_panel wc-metaboxes-wrapper">
    <?php do_action('woocommerce_product_addons_panel_start'); ?>
    <h2><?php esc_html_e('Add-on fields', 'wc-frontend-manager-ultimate'); ?></h2>
    <div class="wcfm-clearfix"></div>
    <?php if (!$exists) : ?>
        <div class="wc-pao-product-global-addon">
            <p>
                <label for="_product_addons_exclude_global"><?php esc_html_e('Exclude add-ons', 'wc-frontend-manager-ultimate'); ?>&nbsp;&nbsp;<input id="_product_addons_exclude_global" name="_product_addons_exclude_global" class="checkbox" type="checkbox" value="1" <?php checked($exclude_global, 1); ?>/></label>&nbsp;&nbsp;
                <em><?php esc_html_e('Hide additional add-ons that may apply to this product.', 'wc-frontend-manager-ultimate'); ?></em>
            </p>
        </div>
    <?php endif; ?>
    <div class="hr-section hr-section-addons"><?php echo esc_html__( 'Product Add-Ons', 'woocommerce-product-addons' ); ?></div>
    <div class="wc-pao-field-header">
        <p class="toolbar wc-pao-toolbar <?php echo esc_attr( $has_addons ); ?>">
            <a href="#" class="wc-pao-import-addons"><?php esc_html_e( 'Import', 'woocommerce-product-addons' ); ?></a>
            <a href="#" class="wc-pao-export-addons"><?php esc_html_e( 'Export', 'woocommerce-product-addons' ); ?></a>
            <a href="#" class="wc-pao-close-all"><?php esc_html_e( 'Close all', 'woocommerce-product-addons' ); ?></a>
            <a href="#" class="wc-pao-expand-all"><?php esc_html_e( 'Expand all', 'woocommerce-product-addons' ); ?></a>&nbsp;
            <input type="hidden" name="product_addons_export_string" class="product_addons_export_string" value="<?php echo esc_textarea( serialize( $product_addons ) ); ?>" />
        </p>
    </div>

    <div class="wc-pao-addons <?php echo esc_attr($has_addons); ?>">
        <?php
        $loop = 0;
        foreach ($product_addons as $addon) {
            include 'html-addon.php';
            $loop++;
        }
        ?>
    </div>

    <div class="pao_boarding__addons addon_fields_container widefat" style="width: 100%;">
		<div class="pao_boarding__addons__message">
			<p>
			<?php
				esc_html_e( 'Use add-ons to add free or paid options to this product.', 'woocommerce-product-addons' );
				?><br><?php
				echo wp_kses_post( __( 'Choose a field type to add below, or <a href="#" class="wc-pao-import-addons">click here</a> to import data.', 'woocommerce-product-addons' ) );
			?>
			</p>
		</div>
		<div class="addon_fields_add addon_fields_row">
			<div class="addon_fields_select">
				<p class="sw-enhanced-select">
					<select class="addon_field_type" style="width: 30%;">
						<option value="add" selected="selected"><?php esc_html_e( 'Add field&hellip;', 'woocommerce-product-addons' ); ?></option>
						<option value="multiple_choice"><?php esc_html_e( 'Multiple Choice', 'woocommerce-product-addons' ); ?></option>
						<option value="checkbox"><?php esc_html_e( 'Checkboxes', 'woocommerce-product-addons' ); ?></option>
						<option value="custom_text"><?php esc_html_e( 'Short Text', 'woocommerce-product-addons' ); ?></option>
						<option value="custom_textarea"><?php esc_html_e( 'Long Text', 'woocommerce-product-addons' ); ?></option>
						<option value="file_upload"><?php esc_html_e( 'File Upload', 'woocommerce-product-addons' ); ?></option>
						<option value="custom_price"><?php esc_html_e( 'Customer Defined Price', 'woocommerce-product-addons' ); ?></option>
						<option value="input_multiplier"><?php esc_html_e( 'Quantity', 'woocommerce-product-addons' ); ?></option>
						<option  value="heading"><?php esc_html_e( 'Heading', 'woocommerce-product-addons' ); ?></option>
					</select>
				</p>
			</div>
		</div>
	</div>
	<?php do_action( 'woocommerce_product_addons_panel_end' ); ?>
</div>
