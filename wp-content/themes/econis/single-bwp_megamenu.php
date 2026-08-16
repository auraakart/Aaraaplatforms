<?php 
	get_header();
	global $post;
	$post_single_layout = econis_post_sidebar();
    $post_slug = $post->post_name;
?>
<div id="bwp-megamenu" class="<?php echo esc_attr($post_slug); ?>">
	<?php
		while ( have_posts() ) : the_post();
			the_content();
		endwhile;
	?>
</div>
<?php
get_footer();