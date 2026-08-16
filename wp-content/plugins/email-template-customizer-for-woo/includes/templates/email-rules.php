<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currency       = get_woocommerce_currency_symbol( get_woocommerce_currency() );
$currency_label = esc_html__( 'Subtotal', 'viwec-email-template-customizer' ) . " ({$currency})";

?>
<div>
	<?php
	if ( function_exists( 'icl_get_languages' ) || class_exists( 'TRP_Translate_Press' ) ) {
		?>
        <div class="viwec-setting-row" data-attr="country">
            <div class="viwec-option-label"><?php esc_html_e( 'Apply to languages', 'viwec-email-template-customizer' ) ?></div>
			<?php viwec_get_pro_version() ?>
        </div>
		<?php
	}
	?>
    <div class="viwec-setting-row" data-attr="country">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to billing countries', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>
        <div class="viwec-option-label"><?php esc_html_e( 'Not apply to billing countries', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>
    </div>

    <div class="viwec-setting-row" data-attr="category">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to categories', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>
        <div class="viwec-option-label"><?php esc_html_e( 'Not apply to categories', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>

    </div>
    <div class="viwec-setting-row" data-attr="products">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to products', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>
        <div class="viwec-option-label"><?php esc_html_e( 'Not apply to products', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>

    </div>
    <div class="viwec-setting-row" data-attr="payment_methods">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to Payments methods', 'viwec-email-template-customizer' ) ?></div>
        <?php viwec_get_pro_version() ?>

    </div>

    <div class="viwec-setting-row" data-attr="min_order">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to min order', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>

    </div>

    <div class="viwec-setting-row" data-attr="max_order">
        <div class="viwec-option-label"><?php esc_html_e( 'Apply to max order', 'viwec-email-template-customizer' ) ?></div>
		<?php viwec_get_pro_version() ?>
    </div>
</div>
