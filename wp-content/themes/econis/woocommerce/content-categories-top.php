<?php 
	$query_string = econis_get_query_string();
	parse_str($query_string, $params);
	$category_slug = isset( $params['product_cat'] ) ? $params['product_cat'] : '';
	$terms = get_terms( 'product_cat', array( 'parent' => 0, 'hide_empty' => false ) );
	$limit_children_shop 		= econis_get_config('limit_children_shop',5);
	$limit_catagories_top 		= econis_get_config('limit_catagories_top',4);
	$j=0;
?>
<div class="content-categories-top">
	<ul class="content-categories">
		<?php foreach( $terms as $cat ){?>
			<li class="items">
				<?php 
					$banner_category = get_term_meta( $cat->term_id, 'banner_category', true );
					$i = 0;
				?>
				<div class="item-product-cat-content"<?php if($banner_category) { ?>  style="background-image: url('<?php echo esc_url($banner_category); ?>');background-repeat:no-repeat;background-position:top center;background-size: cover;"<?php } ?>>
					<div class="content">
						<h2 class="item-title">
							<a href="<?php echo get_term_link( $cat->term_id, 'product_cat' ); ?>"><?php echo esc_html( $cat->name ); ?></a>
						</h2>
						<?php
							$args_childrens = array(
								'hide_empty' => 0,
								'parent' => $cat->term_id,
								'taxonomy' => 'product_cat'
							);
							$childrens = get_categories($args_childrens);
						?>
						<?php if($childrens): ?>
						<ul class="item-children">
							<?php foreach ($childrens as $children) { ?>
								<li><a href="<?php echo get_term_link( $children->slug, $children->taxonomy );?>"><?php echo esc_html($children->name); ?></a></li>
								<?php $i++; ?>
							<?php if($i == $limit_children_shop ) { break; } } ?>
						</ul>
						<?php endif; ?>
						<div class="item-btn">
							<a href="<?php echo get_term_link( $cat->term_id, 'product_cat' ); ?>"><?php echo esc_html__("View more",'econis'); ?></a>
						</div>
					</div>
				</div>
			</li>
		<?php $j++; ?>
		<?php if($j == $limit_catagories_top ) { break; } } ?>
	</ul>
</div>