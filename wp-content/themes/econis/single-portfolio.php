<?php 
	get_header();
?>
<div class="container">
	<div class="single-portfolio-content">
		<div class="cate-content-single ">
			<div class="portfolio-single">
				<?php
					// Start the Loop.
					while ( have_posts() ) : the_post();
						get_template_part( 'content-portfolio');
						// If comments are open or we have at least one comment, load up the comment template.
						if ( comments_open() || get_comments_number() ) {
							comments_template();
						}
					endwhile;
				?>
			</div>
		</div>
    </div>
</div>
<?php
get_footer();