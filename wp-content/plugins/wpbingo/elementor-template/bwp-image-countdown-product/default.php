<?php $arr = array('br' => array(), 'p' => array()); ?>
<div class="bwp-image-product-countdown <?php echo esc_html( $layout ); ?>">
	<div class="bg-banner">
		<?php  if($product_id && $product = wc_get_product( $product_id )): ?>
			<?php if( $subtitle) : ?>
				<div class="bwp-image-subtitle">					
					<?php echo wp_kses( $subtitle,$arr); ?>							
				</div>	
			<?php endif;?>
			<?php if( $title1) : ?>
			<h2 class="title-banner"><?php echo wp_kses( $title1,$arr); ?></h2>
			<?php endif; ?>
			<div class="content">
				<h2 class="product-title"><a href="<?php echo get_permalink( $product_id );  ?>"><?php echo $product->get_title(); ?></a></h2>
				<div class="product-price"><?php echo $product->get_price_html(); ?></div>
				<div class="countdown">
					<?php if( $description) : ?>
					<div class="description-banner"><?php echo esc_html( $description ); ?></div>
					<?php endif; ?>
						<?php if( $time_deal) : ?>
						<div class="countdown-deal">
							<?php
								$start_time = time();
								$countdown_time = strtotime($time_deal);
								$date = bwp_timezone_offset( $countdown_time );
							?>
							<div class="product-countdown"  
								data-day="<?php echo esc_html__("Days","wpbingo"); ?>"
								data-hour="<?php echo esc_html__("Hours","wpbingo"); ?>"
								data-min="<?php echo esc_html__("Mins","wpbingo"); ?>"
								data-sec="<?php echo esc_html__("Secs","wpbingo"); ?>"	
								data-date="<?php echo esc_attr( $date ); ?>"  
								data-sttime="<?php echo esc_attr( $start_time ); ?>" 
								data-cdtime="<?php echo esc_attr( $countdown_time ); ?>" 
								data-id="<?php echo $widget_id; ?>">
							</div>
						</div>
					<?php endif;?>
				</div>
				<?php if( $label) : ?>
					<div class="button-banner">
						<a class="btn-banner" href="<?php echo esc_url($link);?>"><?php echo esc_html( $label ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		<?php endif ?>
	</div>
</div>
