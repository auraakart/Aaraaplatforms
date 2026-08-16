<?php
	$econis_settings = econis_global_settings();
	$post_single_layout = econis_post_sidebar();
	$econis_settings = econis_global_settings();
	$featured_img_url = get_the_post_thumbnail_url(get_the_ID(),'full');
 ?>
<div class="content-single-prallax_image">
	<div class="content-image-single">
		<?php if ( get_the_post_thumbnail() ){ ?>
			<div class="entry-thumb single-thumb" style="background-image:url('<?php echo esc_url($featured_img_url); ?>');">	
			</div>
		<?php }; ?>
		<div class="content-info">
			<?php if ( in_array( 'category', get_object_taxonomies( get_post_type() ) ) && econis_categorized_blog() ) : ?>
				<div class="cat-links"><?php echo get_the_category_list(', '); ?></div>
			<?php endif; ?>	
			<?php
				$show_post_title = econis_get_config('post-title',true);
				if ($show_post_title){
					if ( is_single() ){
						the_title( '<h3 class="entry-title">', '</h3>' );
					}else {
						the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );
					}
				}
			?>
			<div class="entry-by entry-meta">
				<?php econis_single_posted_on_2(); ?>
			</div>
		</div>
	</div>
	<div class="container">
		<div class="single-post-content ">
			<div class="post-single <?php echo esc_attr($post_single_layout); ?>">
				<article id="post-<?php esc_attr(the_ID()); ?>" <?php post_class(); ?>>
					<?php if ( is_search() ) : ?>
					<div class="entry-summary">
						<?php the_excerpt(); ?>
					</div><!-- .entry-summary -->
					<?php else : ?>
					<div class="post-content">
						<div class="post-excerpt clearfix">
							<?php
								/* translators: %s: Name of current post */
								the_content( sprintf(
									the_title( '<span class="screen-reader-text">', '</span>', false )
								) );
								wp_link_pages( array(
									'before'      => '<div class="page-links clearfix"><span class="page-links-title">' . esc_html__( 'Pages:', 'econis' ) . '</span>',
									'after'       => '</div>',
									'link_before' => '<span>',
									'link_after'  => '</span>',
								) );
							?>
						</div>
						<div class="clearfix"></div>
					</div><!-- .entry-content -->
					<div class="post-content-entry">
						<!-- Tag -->
						<?php
						if ( 'post' === get_post_type() ) {
							$tags_list = get_the_tag_list( '', esc_html_x( '', 'list item separator', 'econis' ) );
							if ( $tags_list ) {
								printf( '<div class="tags-links"><label>' . esc_html__( 'Tags :', 'econis' ) . '</label>%1$s</div>', $tags_list ); // WPCS: XSS OK.
							}
						}		
						?>
						<!-- Social Share -->
						<?php if ( shortcode_exists( 'social_share' ) ) : ?> 
							<div class="entry-social-share">
								<label><?php echo esc_html__("Share :","econis"); ?></label>
								<?php echo do_shortcode( "[social_share]" ); ?>	
							</div>
						<?php endif; ?>
					</div>
					<?php econis_post_nav(); ?>
					<!-- Previous/next post navigation. -->
					<div class="clearfix"></div>
					<?php econis_entry_footer(); ?>	
					<?php endif; ?>
				</article><!-- #post-## -->
				<?php
					// If comments are open or we have at least one comment, load up the comment template.
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
				?>
			</div>
		</div>
	</div>
</div>