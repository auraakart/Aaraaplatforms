<?php
global $post, $product, $woocommerce;
$columns = 	econis_image_single_product()->product_count_thumb;
$video_style = econis_get_config("video-style","inner");
$attachment_ids = $product->get_gallery_image_ids();
$data_product = $product->get_data();
$image_id = $data_product['image_id'] ? $data_product['image_id'] : array();
if($image_id )
	array_unshift ($attachment_ids,$image_id);
if ( $attachment_ids ) {
	$loop 		= 0;
	?>
	<div id="image-thumbnail" class="image-thumbnail slick-carousel"  data-infinite="true" data-columns4="<?php echo esc_attr($columns); ?>" data-columns3="<?php echo esc_attr($columns); ?>" data-columns2="<?php echo esc_attr($columns); ?>" data-columns1="<?php echo esc_attr($columns); ?>" data-columns="<?php echo esc_attr($columns); ?>" data-nav="true" <?php echo esc_attr(econis_image_single_product()->class_data_image); ?>>
	<?php
		foreach ( $attachment_ids as $attachment_id ) { ?>
			<div class="img-thumbnail"> 
			<?php $classes 	= array("img-thumbnail");
			if ( $loop === 0 || $loop % $columns === 0 )
				$classes[] = 'first';
			if ( ( $loop + 1 ) % $columns === 0 )
				$classes[] = 'last';
			if ( $image_id && ($attachment_id ==  $image_id))
				$classes[] = 'active';			
			$image_link = wp_get_attachment_url( $attachment_id );
			if ( ! $image_link )
				continue;
			$image_title 	= esc_attr( get_the_title( $attachment_id ) );
			$image_caption 	= esc_attr( get_post_field( 'post_excerpt', $attachment_id ) );
            $image       = wp_get_attachment_image( $attachment_id, apply_filters( 'single_product_small_thumbnail_size', 'shop_catalog' ), 0, $attr = array(
                'title' => $image_title,
                'alt'   => $image_title,
                'data-zoom-image'=> $image_link
                ) );
			$image_class = esc_attr( implode( ' ', $classes ) );
			echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', sprintf( '<a href="%s"   data-image="%s" class="%s" title="%s">%s</a>', $image_link, $image_link, $image_class, $image_caption, $image ), $attachment_id, $post->ID, $image_class );
			?>
			</div> 
			<?php	$loop++;
		}
		if($video_style == 'inner'){
			econis_display_thumb_video();
		}
	?>
	</div>
	<?php
}