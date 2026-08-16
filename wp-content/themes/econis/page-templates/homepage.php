<?php
/**
 * Template Name: Home Page
 *
 * @package Wpbingo
 * @subpackage Econis
 * @since Wpbingo Econis 1.0
 */
get_header(); ?>
<div id="main-content" class="main-content">
	<div id="primary" class="content-area container">
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
</div><!-- #main-content -->
<?php
get_footer();