<?php if (!have_posts()) : ?>
	<div class="alert alert-warning alert-dismissible" role="alert">
		<a class="close" data-dismiss="alert">&times;</a>
		<p><?php echo esc_html__('Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'econis'); ?></p>
	</div>
<?php endif; ?>