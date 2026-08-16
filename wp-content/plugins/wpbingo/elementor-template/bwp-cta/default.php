<div class="bwp-cta <?php echo esc_attr($layout); ?>">
	<?php if($image) :?>
		<div class="box-image">
			<img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr__("counter up","wpbingo"); ?>">
		</div>
	<?php endif; ?>
	<?php if( $count) : ?>
		<div class="content">
			<h2 class="count-cta"><?php echo esc_attr( $count ); ?></h2>
			<?php if( $title1) : ?>
			<div class="title-cta"><?php echo esc_html( $title1 ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div><!-- .bwp-cta -->