<?php $tag_id = 'testimonial_' .rand().time(); 
	$args = array(
		'post_type' => 'testimonial',
		'posts_per_page' => -1,
		'post_status' => 'publish'
	);
	$query = new WP_Query($args);
	$count = $query->post_count;
	$j=0;
?>
<?php if($query->have_posts()):?>
<div class="bwp-testimonial <?php echo esc_attr($layout); ?>">
	<div class="block">
		<div class="testimonial-title">
			<?php
				if (isset($title1) && $title1)
				echo '<h2>'. $title1 .'</h2>';
			?>
		</div>
	<div class="block_content">
		<div id="<?php echo esc_attr($tag_id); ?>" class="slick-carousel " data-slidesToScroll="true" data-nav="<?php echo esc_attr($show_nav);?>" data-dots="<?php echo esc_attr($show_pag);?>" data-columns4="<?php echo $columns4; ?>" data-columns3="<?php echo $columns3; ?>" data-columns2="<?php echo $columns2; ?>" data-columns1="<?php echo $columns1; ?>" data-columns="<?php echo $columns; ?>">
			<?php while($query->have_posts()):$query->the_post(); ?>
				<?php	if( ( $j % $item_row ) == 0 ) { ?>
					<div class="testimonial-content">
				<?php } ?>
					<?php 
						$testimonial_job  = get_post_meta( get_the_ID(), 'testimonial_job',true) ? get_post_meta( get_the_ID(), 'testimonial_job',true) : '';
						$testimonial_star  = get_post_meta( get_the_ID(), 'testimonial_star',true) ? get_post_meta( get_the_ID(), 'testimonial_star',true) : ''; ?>
					<div class="item">
						<div class="testimonial-item">
							<span aria-hidden="true" class="wpb-icon icon-right-quote"></span>
							<div class="star star-<?php echo esc_attr($testimonial_star); ?>"></div>
							<div class="testimonial-customer-position"><?php echo wpbingo_get_excerpt( $length, false ); ?></div>								
						</div>
						<div class="testimonial-image image-position-<?php echo esc_attr($postion_image); ?>">
							<div class="thumbnail">
								<?php the_post_thumbnail( 'thumbnail' ); ?>
							</div>
							<div class="testimonial-info">
								<h2 class="testimonial-customer-name"><?php the_title(); ?></h2>
								<?php if($testimonial_job): ?>	
									<div class="testimonial-job"><?php echo esc_html($testimonial_job); ?></div>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<!-- Wrapper for slides -->
				<?php if(( $j+1 ) % $item_row == 0 || ( $j+1 ) == $count ){?> 
				</div>
				<?php  } ?>
			<?php $j++; endwhile; ?>
		</div>
	</div>
 </div>
</div>
<?php endif;?>