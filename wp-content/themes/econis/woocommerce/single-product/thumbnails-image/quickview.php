<?php 
global $post, $woocommerce, $product;
remove_action( 'woocommerce_single_product_summary', 'econis_add_thumb_single_product', 40 );
remove_action( 'woocommerce_after_single_product', 'econis_sticky_cart', 50 );
remove_action( 'woocommerce_single_product_summary', 'econis_get_countdown', 20 );
remove_action( 'woocommerce_single_product_summary', 'econis_add_social', 45 );
remove_action( 'woocommerce_single_product_summary', 'econis_add_loop_wishlist_link', 35 );
remove_action( 'woocommerce_single_product_summary', 'econis_add_loop_compare_link', 36 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
$video_style = econis_get_config("video-style","inner");
$video  = (get_post_meta( $product->get_id(), 'video_product', true )) ? get_post_meta($product->get_id(), 'video_product', true ) : "";
$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
$full_size_image   = wp_get_attachment_image_src( $post_thumbnail_id, 'full' );
$image_title       = get_post_field( 'post_excerpt', $post_thumbnail_id );
$placeholder       = has_post_thumbnail() ? 'with-images' : 'without-images';
$wrapper_classes   = apply_filters( 'woocommerce_single_product_image_gallery_classes', array(
	'woocommerce-product-gallery',
	'woocommerce-product-gallery--' . $placeholder,
	'images',
) );
$attachment_ids = $product->get_gallery_image_ids();
?>
<div id="quickview-slick-carousel">
	<div class="col-sm-12">
		<div class="image-additional slick-carousel" data-dots="true" data-nav="true" data-focusonselect="true" data-columns4="1" data-columns3="1" data-columns2="1" data-columns1="1" data-columns="1">
			<?php
			$attributes = array(
				'id'						=> "image", 	
				'title'                   => $image_title,
				'data-src'                => $full_size_image[0],
				'data-large_image'        => $full_size_image[0],
				'data-large_image_width'  => $full_size_image[1],
				'data-large_image_height' => $full_size_image[2],
			);
			if ( has_post_thumbnail() ) {
				$html  = '<div data-thumb="' . get_the_post_thumbnail_url( $post->ID, 'shop_thumbnail' ) . '" class="img-thumbnail woocommerce-product-gallery__image">
				<a data-elementor-open-lightbox="default" data-elementor-lightbox-slideshow="image-additional" href="' . esc_url( $full_size_image[0] ) . '">';
					$html .= get_the_post_thumbnail( $post->ID, 'shop_single', $attributes );
					$html .= '</a>
				</div>';
			} else {
				$html  = '<div class="img-thumbnail woocommerce-product-gallery__image--placeholder">';
				$html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'econis' ) );
				$html .= '</div>';
			} 		
			echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, get_post_thumbnail_id( $post->ID ) ); ?>
			<?php
				if ( $attachment_ids ) {
					$loop 		= 0;
					foreach ( $attachment_ids as $attachment_id ) { ?>
						<div class="img-thumbnail">
						<?php $image_link = wp_get_attachment_url( $attachment_id );
						if ( ! $image_link )
							continue;
						$image_title 	= esc_attr( get_the_title( $attachment_id ) );
						$image_caption 	= esc_attr( get_post_field( 'post_excerpt', $attachment_id ) );
						$image       = wp_get_attachment_image( $attachment_id, apply_filters( 'single_product_small_thumbnail_size', 'shop_single' ), 0, $attr = array(
							'title' => $image_title,
							'alt'   => $image_title,
							) );
						$image_class = "image-scroll";
						echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', sprintf( '<a href="%s" data-elementor-open-lightbox="default" data-elementor-lightbox-slideshow="image-additional"  data-image="%s" class="%s" title="%s">%s</a>', $image_link, $image_link, $image_class, $image_caption, $image ), $attachment_id, $post->ID, $image_class );
						?>
						</div>
						<?php $loop++;
					}							
				}
			?>
			<?php if($video_style == 'inner' && $video ){ ?>
				<div class="video-additional text-center">
					<?php econis_display_video_product($full_size_image); ?>
				</div>
			<?php } ?>
		</div>	
	</div>
</div>