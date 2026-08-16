<?php 
global $post, $woocommerce, $product;
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
<div class="images">
	<figure class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>">
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
			$html  = '<div data-thumb="' . get_the_post_thumbnail_url( $post->ID, 'shop_thumbnail' ) . '" class="img-thumbnail woocommerce-product-gallery__image">
			<div>';
				$html .= get_the_post_thumbnail( $post->ID, 'shop_single', $attributes );
				$html .= '</div>
			</div>';
		} else {
			$html  = '<div class="img-thumbnail woocommerce-product-gallery__image--placeholder">';
			$html .= sprintf( '<img src="%s" alt="%s" class="wp-post-image" />', esc_url( wc_placeholder_img_src() ), esc_html__( 'Awaiting product image', 'econis' ) );
			$html .= '</div>';
		} 		
		echo apply_filters( 'woocommerce_single_product_image_thumbnail_html', $html, get_post_thumbnail_id( $post->ID ) ); ?>
	</figure>
</div>
<?php 
	$attachment_ids = $product->get_gallery_image_ids();
	$video  	= (get_post_meta( $product->get_id(), 'video_product', true )) ? get_post_meta($product->get_id(), 'video_product', true ) : "";
	$shipping  	= (get_post_meta( $product->get_id(), 'shipping_product', true )) ? get_post_meta($product->get_id(), 'shipping_product', true ) : "";
	$view  		= (get_post_meta( $product->get_id(), 'view_product', true )) ? get_post_meta($product->get_id(), 'view_product', true ) : "";
 ?>
<div class="content-special hidden-xs">
	<?php if ( $attachment_ids ) { ?>
		<div class="content-thumbs-scroll-special">
			<div class="show-scroll-special">
				<div class="content">
					<i class="wpb-icon-gallery"></i>
					<h2><?php echo esc_html__("Gallery","econis") ?></h2>
				</div>
			</div>
			<div class="thumbs-scroll-special">
				<div class="show-scroll-special">
				</div>
				<?php do_action( 'woocommerce_product_thumbnails' ); ?>
			</div>
		</div>
	<?php } ?>
	<?php if($video){ ?>
		<div class="special-btn-video">
			<?php econis_get_video_product(); ?>
		</div>
	<?php } ?>
	<?php if($view == 'true'){ ?>
		<div class="special-btn-360">
			<?php econis_view_product(); ?>
		</div>
	<?php } ?>
	<?php if($shipping){ ?>
		<div class="special-shipping">
			<?php econis_get_shipping_product(); ?>
		</div>
	<?php } ?>
</div>