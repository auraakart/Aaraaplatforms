<?php 
	global $instance;
	$format = get_post_format();
	$class = 'grid-post '.esc_attr(econis_get_class()->class_item_blog);
?>
<article id="post-<?php esc_attr(the_ID()); ?>" <?php post_class($class); ?>>
	<div class="entry-post">
		<?php if( empty($format) || $format == 'image' || $format == 'quote') : ?>	
			<?php if ( get_the_post_thumbnail() ){?>
			<div class="entry-thumb single-thumb">
				<a class="post-thumbnail" href="<?php echo esc_url(the_permalink()); ?>" title="<?php the_title_attribute(); ?>">
					<?php the_post_thumbnail( 'econis-blog-layout-1' )?>				
				</a>
				<?php econis_posted_on(); ?>
			</div>
			<?php } ?>
		<?php elseif( $format == 'video' || $format == 'audio' ) : ?>
			<div class="entry-thumb single-thumb">
				<a class="post-thumbnail" href="<?php esc_url(the_permalink()); ?>" title="<?php echo the_title_attribute(); ?>">
					<?php the_post_thumbnail( 'econis-blog-layout-1', array( 'alt' => get_the_title() ) );	?>      
				</a>
				<?php econis_posted_on(); ?>				
			</div>	
		<?php elseif( $format == 'gallery' ) : 
			$ids = "";	
			if(preg_match_all('/\[gallery(.*?)?\]/', get_post($instance['post_id'])->post_content, $matches)){
				$attrs = array();
					if (count($matches[1])>0){
						foreach ($matches[1] as $m){
							$attrs[] = shortcode_parse_atts($m);
						}
					}
					if (count($attrs)> 0){
						foreach ($attrs as $attr){
							if (is_array($attr) && array_key_exists('ids', $attr)){
								$ids = $attr['ids'];
								break;
							}
						}
					}
				?>
				<div class="entry-thumb">
					<div id="gallery_slider_<?php echo esc_attr($post->ID); ?>" class="gallery-slider">	
						<div class="slick-carousel" data-columns4="1" data-columns3="1" data-columns2="1" data-columns1="1" data-columns="1" data-nav="true">
								<?php
									if($ids){
										$ids = explode(',', $ids);						
										foreach ( $ids as $i => $id ){ ?>
											<div class="item">	
													<?php echo wp_get_attachment_image($id, 'econis-blog-layout-1'); ?>
											</div>
										<?php }	
									}
								?>
						</div>
					</div>
					<?php econis_posted_on(); ?>
				</div>
				<?php }	?>			
		<?php endif; ?>
		<div class="post-content">
        	<?php if( bwp_category_post()){ ?>
				<div class="post-categories">
					<a href="<?php echo esc_url(bwp_category_post()->cat_link);?>"><?php echo esc_html(bwp_category_post()->name); ?></a>
				</div>
			<?php } ?>
        	<h3 class="entry-title"><a href="<?php echo esc_url(the_permalink()) ?>"><?php echo the_title(); ?></a></h3>
			<div class="entry-meta-head">
				<?php if (econis_get_config('archives-author')) { ?>
					<div class="entry-author">
						<span class="entry-meta-link"><?php echo esc_html__("By : ",'econis') ?><?php echo the_author_posts_link(); ?></span>
					</div>
				<?php } ?>
				<span class="comments-link">
					<?php 
					$comment_count =  wp_count_comments(get_the_ID())->total_comments;
					if($comment_count > 0) {
					?>
						<?php if($comment_count == 1){?>
							<?php echo esc_attr($comment_count) .'<span>'. esc_html__(' Comment', 'econis').'</span>'; ?>
						<?php }else{ ?>
							<?php echo esc_attr($comment_count) .'<span>'. esc_html__(' Comments', 'econis').'</span>'; ?>
						<?php } ?>
					<?php }else{ ?>
						<?php echo esc_attr($comment_count) .'<span>'. esc_html__(' Comments', 'econis').'</span>'; ?>
					<?php } ?>
				</span>
			</div>
		</div>
	</div>
</article><!-- #post-## -->