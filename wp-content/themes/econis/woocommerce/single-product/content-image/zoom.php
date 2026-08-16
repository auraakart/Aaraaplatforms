<?php 
global $post, $woocommerce, $product;
$video_style = econis_get_config("video-style","inner");
$video  = (get_post_meta( $product->get_id(), 'video_product', true )) ? get_post_meta($product->get_id(), 'video_product', true ) : "";
$data_product = $product->get_data();
$image_id = $data_product['image_id'] ? $data_product['image_id'] : array();
$attachment_ids = $product->get_gallery_image_ids();
if($image_id )
	array_unshift ($attachment_ids,$image_id);
$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
$full_size_image   = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
$image_title       = get_post_field( 'post_excerpt', $post_thumbnail_id );
$placeholder       = has_post_thumbnail() ? 'with-images' : 'without-images';
$wrapper_classes   = apply_filters( 'woocommerce_single_product_image_gallery_classes', array(
	'woocommerce-product-gallery',
	'woocommerce-product-gallery--' . $placeholder,
	'images',
) );
$class= "";
if(econis_image_single_product()->show_thumb && (econis_image_single_product()->position == "left" || econis_image_single_product()->position == "right"))
	$class = "vertical";
?>
<div class="images <?php echo esc_attr($class); ?>">
	<figure class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>">
		<div class="row">
			<?php if(econis_image_single_product()->show_thumb && econis_image_single_product()->position == "left") : ?>
				<div class="<?php echo esc_attr(econis_image_single_product()->class_thumb); ?>">
				<?php do_action( 'woocommerce_product_thumbnails' ); ?>
				</div>
			<?php endif; ?>
			<div class="<?php echo esc_attr(econis_image_single_product()->class_image); ?>">
				<div class="image-additional active text-center">
				<?php
				if ( has_post_thumbnail() && $full_size_image ) {
					$attributes = array(
						'id'						=> "image", 	
						'title'                   => $image_title,
						'data-src'                => $full_size_image[0],
						'data-large_image'        => $full_size_image[0],
						'data-large_image_width'  => $full_size_image[1],
						'data-large_image_height' => $full_size_image[2],
					);
					$html  = '<div data-thumb="' . get_the_post_thumbnail_url( $post->ID, 'full' ) . '" class="woocommerce-product-gallery__image">';
					$html .= get_the_post_thumbnail( $post->ID, 'shop_single', $attributes );
					$html .= '</div>';
				} else {
					$html  = '<div class="woocommerce-product-gallery__image--placeholder">';
					$html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'econis' ) );
					$html .= '</div>';
				} 
				echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, get_post_thumbnail_id( $post->ID ) ); ?>
				<?php if($video_style == 'popup'){ econis_get_video_product(); } ?>
				<?php econis_view_product(); ?>
				<?php if( count($attachment_ids) > 1 && econis_image_single_product()->show_thumb ): ?>
				<div class="view-gallery"><i class="wpb-icon-expand" aria-hidden="true"></i></div>
				<?php endif; ?>
				</div>
				<?php if($video_style == 'inner' && $video){ ?>
					<div class="video-additional text-center">
						<?php econis_display_video_product($full_size_image); ?>
					</div>
				<?php } ?>
			</div>
			<?php if(econis_image_single_product()->show_thumb && (econis_image_single_product()->position == "right" || econis_image_single_product()->position == "bottom")) : ?>
				<div class="<?php echo esc_attr(econis_image_single_product()->class_thumb); ?>">
				<?php do_action( 'woocommerce_product_thumbnails' ); ?>
				</div>
			<?php endif; ?>	
		</div>
	</figure>
</div>