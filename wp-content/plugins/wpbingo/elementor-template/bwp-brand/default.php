	<?php 
	$widget_id = 'bwp_brand_'.rand().time();
	$term_brands = array();
	if( !is_array( $category ) ){
		$category = explode( ',', $category );
	}
	if($category){
	$term_brands = $category;
	?>
	<div id="<?php echo esc_attr( $widget_id ) ?>" class="bwp-brand <?php echo esc_attr($layout); ?>">
		<?php if( $title1 != '') { ?>
				<div class="title_brand"><?php echo ( $title1 != '' ) ? '<h2>'. esc_html( $title1 ) .'</h2>' : ''; ?></div>
		<?php }?>
		<div class="slider slick-carousel" data-nav="<?php echo esc_attr($show_nav);?>" data-columns4="<?php echo $columns4; ?>" data-columns3="<?php echo $columns3; ?>" data-columns2="<?php echo $columns2; ?>" data-columns1="<?php echo $columns1; ?>" data-columns1440="<?php echo $columns1440; ?>" data-columns="<?php echo $columns; ?>">	
			<?php 
				foreach( $term_brands as $j => $term_brand ) {
					$term = get_term_by( 'slug', $term_brand, 'product_brand' );
					if( $term ) :
					$thumb 	= ( get_term_meta( $term->term_id, 'thumbnail_bid', true ) );
					$thubnail = !empty($thumb) ? $thumb : "http://placehold.it/200x200";
			?>
			<div class="item">
				<div class="item-image">
					<a href="<?php echo get_term_link( $term->term_id, 'product_brand' ); ?> "> <img class="<?php if ($image_hover) { ?> elementor-animation-<?php echo esc_attr($image_hover); } ?>" src="<?php echo esc_url($thubnail) ?>" alt="<?php echo esc_html($term->name) ?>"></a>
				</div>
			</div>
				<?php endif; ?>
			<?php } ?>
		</div>
	</div>
	<?php } ?>