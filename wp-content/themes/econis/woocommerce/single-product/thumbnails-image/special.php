<?php
global $post, $product, $woocommerce;
$columns = 	econis_image_single_product()->product_count_thumb;
$attachment_ids = $product->get_gallery_image_ids();
$data_product = $product->get_data();
$image_id = $data_product['image_id'] ? $data_product['image_id'] : array();
$product_thumbs = econis_get_config("product-thumbs",true);
if($image_id )
	array_unshift ($attachment_ids,$image_id);
if ( $attachment_ids ) {
	$loop 		= 0;
	?>
	<div class="content-image-additional">
		<div class="image-additional slick-carousel" data-fade="true" data-draggable="true" data-nav="true" data-asnavfor=".image-thumbnail" data-columns4="1" data-columns3="1" data-columns2="1" data-columns1="1" data-columns="1">
			<?php
				foreach ( $attachment_ids as $attachment_id ) {		
					$image_link = wp_get_attachment_url( $attachment_id );
					if ( ! $image_link )
						continue;
					$image_title 	= esc_attr( get_the_title( $attachment_id ) );
					$image_caption 	= esc_attr( get_post_field( 'post_excerpt', $attachment_id ) );
					$image       = wp_get_attachment_image( $attachment_id, apply_filters( 'single_product_small_thumbnail_size', 'shop_catalog' ), 0, $attr = array(
						'title' => $image_title,
						'alt'   => $image_title,
						'data-zoom-image'=> $image_link
						) ); ?>
					<div class="img-thumbnail">
						<span class="img-thumbnail-scroll">
						<?php echo wp_kses($image,'social'); ?>
						</span>
					</div>
					<?php $loop++;
				}
			?>
		</div>
	</div>
	<?php if($loop >= 10 ){ $loop = 10; } ?>
	<div class="content-image-thumbnail">
		<div class="image-thumbnail slick-carousel <?php if($loop < 10 ){ ?>max-thumbnail<?php } ?>" data-asnavfor=".image-additional" data-focusonselect="true" data-columns4="<?php echo esc_attr($loop); ?>" data-columns3="<?php echo esc_attr($loop); ?>" data-columns2="<?php echo esc_attr($loop); ?>" data-columns1="<?php echo esc_attr($loop); ?>" data-columns="<?php echo esc_attr($loop); ?>">
		<?php
			foreach ( $attachment_ids as $attachment_id ) {		
				$image_link = wp_get_attachment_url( $attachment_id );
				if ( ! $image_link )
					continue;
				$image_title 	= esc_attr( get_the_title( $attachment_id ) );
				$image_caption 	= esc_attr( get_post_field( 'post_excerpt', $attachment_id ) );
				$image       = wp_get_attachment_image( $attachment_id, apply_filters( 'single_product_small_thumbnail_size', 'shop_catalog' ), 0, $attr = array(
					'title' => $image_title,
					'alt'   => $image_title,
					'data-zoom-image'=> $image_link
					) ); ?>
				<div class="img-thumbnail">
					<span class="img-thumbnail-scroll">
					<?php echo wp_kses($image,'social'); ?>
					</span>
				</div>
		<?php } ?>
		</div>
	</div>
	<?php
}