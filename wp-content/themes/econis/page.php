<?php get_header(); ?>
<div class="container">
	<div class="row">
		<div class="col-lg-12 col-md-12">    
			<div id="main-content" class="main-content">
				<div id="primary" class="content-area">
					<div id="content" class="site-content" role="main">
						<?php
							// Start the Loop.
							while ( have_posts() ) : the_post();
								// Include the page content template.
								get_template_part( 'templates/content/content', 'page');
								// If comments are open or we have at least one comment, load up the comment template.
								if ( comments_open() || get_comments_number() ) {
									comments_template();
								}
							endwhile;
						?>
					</div><!-- #content -->
				</div><!-- #primary -->
			</div><!-- #main-content -->
		</div>   
    </div>
</div>
<?php
get_footer();