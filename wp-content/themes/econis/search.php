<?php 
	get_header();	
	$sidebar_blog = econis_blog_sidebar();
	$layout_blog = econis_blog_view();
	$class_content_blog = 'blog-content-'.esc_attr($layout_blog);
?>
<div class="container">
	<div class="category-posts row">
		<?php if($sidebar_blog == 'left' ||  $sidebar_blog == 'left_right'):?>			
		<div class="bwp-sidebar sidebar-blog <?php echo esc_attr(econis_get_class()->class_sidebar_left); ?>">
			<?php dynamic_sidebar( 'sidebar-blog' );?>	
		</div>				
		<?php endif; ?>
		<div class="cate-post-content <?php echo esc_attr($sidebar_blog); ?> <?php if(is_active_sidebar('sidebar-blog')){ echo esc_attr(econis_get_class()->class_blog_content); }else{ echo "col-lg-12 col-md-12 col-sm-12 col-xs-12"; } ?>">
			<section id="primary" class="content-area">
				<div id="content" class="site-content <?php echo esc_attr($class_content_blog);?>" role="main">
						<?php if ( have_posts() ) : ?>
						<?php
								// Start the Loop.
								while ( have_posts() ) : the_post();
								/*
								 * Include the post format-specific template for the content. If you want to
								 * use this in a child theme, then include a file called called content-___.php
								 * (where ___ is the post format) and that will be used instead.
								 */
								get_template_part( 'templates/content/content', 'search');
								endwhile;
								// Previous/next page navigation.
							else :
								// If no content, include the "No posts found" template.
								get_template_part( 'templates/content/content', 'none');
						endif;
					?>
				</div><!-- #content -->
				<?php 	econis_paging_nav(); ?>
			</section><!-- #primary -->
		</div>
		<?php if($sidebar_blog == 'right' || $sidebar_blog == 'left_right'):?>			
			<div class="bwp-sidebar sidebar-blog <?php echo esc_attr(econis_get_class()->class_sidebar_right); ?>">
				<?php dynamic_sidebar('sidebar-blog');?>	
			</div>				
		<?php endif; ?>
    </div>
</div>
<?php
get_footer();