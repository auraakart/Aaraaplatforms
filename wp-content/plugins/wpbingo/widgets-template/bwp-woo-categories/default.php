<?php
	$id_category =  is_tax("product_cat") ? get_queried_object()->term_id : 0;
	$terms =	get_terms( 'product_cat',
	array(
		'hide_empty' => $show_empty ? false : true,
		'parent' => $id_category
	));
?>
<?php if(isset($title1) && $title1) { ?>
	<h3 class="widget-title"><?php echo esc_html($title1); ?></h3>
<?php } ?>
<div class="block_content">
	<ul class="product-categories<?php if( $id_category != 0 ): ?> sub-categories<?php endif; ?>">
		<?php if( $id_category != 0 ): ?>
			<li class="back-shop"><a href="<?php echo wc_get_page_permalink( 'shop' ); ?>"><?php echo esc_html__('All Categories','wpbingo'); ?></a></li>
			<?php $parent_cat_id = get_queried_object()->parent; ?>
			<?php if( $parent_cat_id != 0 ): ?>
			<li class="current-parent">
				<a href="<?php echo get_term_link( $parent_cat_id, 'product_cat' ); ?>">
					<?php echo get_the_category_by_ID($parent_cat_id); ?>
				<?php if($show_count): ?>
					<?php $term = get_term_by('id', $parent_cat_id, 'product_cat'); ?>
					<span>(<?php echo esc_attr($term->count); ?>)</span>
				<?php endif; ?>
				</a>
			</li>
			<?php endif; ?>
			<li class="current-category">
				<a href="<?php echo get_term_link( $id_category, 'product_cat' ); ?>"><?php echo get_the_category_by_ID($id_category); ?>
					<?php if($show_count): ?>
						<?php $term = get_term_by('id', $id_category, 'product_cat'); ?>
						<span>(<?php echo esc_attr($term->count); ?>)</span>
					<?php endif; ?>
				</a>
			</li>
		<?php endif; ?>
		<?php if($terms): ?>
			<?php foreach($terms as $term){ ?>
				<?php
					$terms_vl1 =	get_terms( 'product_cat', 
					array( 
						'parent' => '', 
						'hide_empty' => false,
						'parent' 		=> $term->term_id,
					));
				?>
				<li class="cat-item <?php if($terms_vl1){echo "cat-parent";}?>">
					<a href="<?php echo get_term_link( $term->term_id, 'product_cat' ); ?>"><?php echo esc_html( $term->name ); ?>
						<?php if($show_count): ?>
							<span>(<?php echo esc_attr($term->count); ?>)</span>
						<?php endif; ?>
					</a>
					<?php if($terms_vl1): ?>
						<ul class="children">
							<?php foreach($terms_vl1 as $term_vl1){ ?>
								<?php
									$terms_vl2 =	get_terms( 'product_cat',
									array( 
											'parent' => '', 
											'hide_empty' => false,
											'parent' 		=> $term_vl1->term_id,
									));
								?>
								<li class="cat-item <?php if($terms_vl2){echo "cat-parent";}?>">
									<a href="<?php echo get_term_link( $term_vl1->term_id, 'product_cat' ); ?>"><?php echo esc_html( $term_vl1->name ); ?>
										<?php if($show_count): ?>
											<span>(<?php echo esc_attr($term_vl1->count); ?>)</span>
										<?php endif; ?>
									</a>
									<?php if($terms_vl2): ?>
									<ul class="children">
										<?php foreach($terms_vl2 as $term_vl2){ ?>
											<li class="cat-item">
												<a href="<?php echo get_term_link( $term_vl2->term_id, 'product_cat' ); ?>"><?php echo esc_html( $term_vl2->name ); ?>
													<?php if($show_count): ?>
														<span>(<?php echo esc_attr($term_vl2->count); ?>)</span>
													<?php endif; ?>
												</a>
											</li>
										<?php } ?>
									</ul>
								<?php endif; ?>
								</li>
							<?php } ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php } ?>
		<?php endif; ?>
	</ul>
</div>