<?php 
/**
 * Template Name: Portfolio Page
 *
 * @package Wpbingo
 * @subpackage Econis
 * @since Wpbingo Econis 1.0
 */
?>
<?php get_header(); ?>    
<div class="portfolio-page">
	<div class="container" id="container">
		<div id="primary" class="content-area">
			<div id="content" class="site-content" role="main">
				<?php
					// Start the Loop.
					while ( have_posts() ) : the_post();
						// Include the page content template.
						get_template_part( 'templates/content/content', 'page');
					endwhile;
				?>
			</div><!-- #content --> 
		</div><!-- #primary -->
	</div>		
</div>
<?php
get_footer();