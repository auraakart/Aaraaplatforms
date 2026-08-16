<?php
$check = false;
$attribute_taxonomies = wc_get_attribute_taxonomies();
$currency_symbol = get_woocommerce_currency_symbol();
if($attribute_taxonomies){
	foreach ( $attribute_taxonomies as $attribute ){
		$taxonomy     = wc_attribute_taxonomy_name( $attribute->attribute_name );
		if( isset( $chosen_attributes[ $taxonomy ]['terms'] ) && $chosen_attributes[ $taxonomy ]['terms'] ){$check = true;};
	}
}
if( isset( $chosen_attributes[ 'pa_brand' ]['terms'] ) && $chosen_attributes[ 'pa_brand' ]['terms'] ){$check = true;};
if( isset( $chosen_attributes[ 'pa_rating' ]['terms'] ) && $chosen_attributes[ 'pa_rating' ]['terms'] ){$check = true;};
if(($min_price && ($min_price != $default_min_price)) || ($max_price && ($max_price != $default_max_price))){$check = true;};
?>
<?php if($check): ?>
	<div class="bwp-filter bwp-filter-attribute">
		<h3><?php echo esc_html__('Active Filters','wpbingo'); ?></h3>
		<div class="filter-attribute">
			<?php if($attribute_taxonomies){ ?>
				<?php foreach ( $attribute_taxonomies as $attribute ) : $taxonomy     = wc_attribute_taxonomy_name( $attribute->attribute_name ); ?>
					<?php if( isset( $chosen_attributes[ $taxonomy ]['terms'] ) && $chosen_attributes[ $taxonomy ]['terms'] ): ?>
						<?php foreach( $chosen_attributes[ $taxonomy ]['terms'] as $term ): ?>
							<?php $value = get_term_by('slug', $term , $taxonomy); ?>
							<span data-name="<?php echo esc_attr($taxonomy); ?>" data-value="<?php echo esc_attr($term); ?>"><?php echo esc_html($value->name); ?></span>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php endforeach;
				}
			?>
			<?php if( isset( $chosen_attributes[ 'pa_brand' ]['terms'] ) && $chosen_attributes[ 'pa_brand' ]['terms'] ): ?>
				<?php foreach( $chosen_attributes[ 'pa_brand' ]['terms'] as $term ): ?>
					<?php $value = get_term_by('slug', $term , 'product_brand'); ?>
					<span data-name="pa_brand" data-value="<?php echo esc_attr($term); ?>"><?php echo esc_html($value->name); ?></span>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if( isset( $chosen_attributes[ 'pa_rating' ]['terms'] ) && $chosen_attributes[ 'pa_rating' ]['terms'] ): ?>
				<?php foreach( $chosen_attributes[ 'pa_rating' ]['terms'] as $term ): ?>
					<span data-name="pa_rating" data-value="<?php echo esc_attr($term); ?>"><?php echo esc_html__('Rated ','wpbingo'); ?><?php echo esc_attr($term); ?><?php echo esc_html__(' out of 5','wpbingo'); ?></span>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if(($min_price && ($min_price != $default_min_price)) || ($max_price && ($max_price != $default_max_price))): ?>
				<span class="text-price"><?php echo esc_html($currency_symbol.$min_price); ?> - <?php echo esc_html($currency_symbol.$max_price); ?></span>
			<?php endif; ?>
			<button class="filter_clear_all" type="button"><?php echo esc_html__( 'Clear All', 'wpbingo' ); ?></button>
		</div>
	</div>
<?php endif; ?>
