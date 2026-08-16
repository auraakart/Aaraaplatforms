
<div class="bwp-policy <?php echo esc_attr($layout); ?>">
	<?php if( $icon) : ?>
		<div class="policy-icon">
			<div class="item-icon">
				<i class="<?php echo esc_html( $icon ); ?>"></i>
			</div>
		</div>
	<?php endif;?>
	<?php if($desc) : ?>
		<div class="policy-info">
			<?php if(isset($title1) && $title1) : ?>
				<h3 class="title-policy"><?php echo esc_html( $title1 ); ?></h3>
			<?php endif; ?>
			<?php if( $desc) : ?>
				<div class="desc-policy"><?php echo esc_html( $desc ); ?></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div><!-- .bwp-policy -->
