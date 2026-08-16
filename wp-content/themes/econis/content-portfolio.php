<?php $econis_settings = econis_global_settings(); ?>
<article id="post-<?php esc_attr(the_ID()); ?>" <?php post_class(); ?>>
	<div class="row">
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 content-left">
		<?php if ( get_the_post_thumbnail() ){ ?>
			<div class="entry-thumb single-thumb">
				<?php the_post_thumbnail( 'full' ); ?>
			</div>
		<?php }; ?>
		</div>
		<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 content-right">
			<?php if ( is_search() ) : ?>
			<div class="entry-summary">
				<?php the_excerpt(); ?>
			</div><!-- .entry-summary -->
			<?php else : ?>
			<div class="portfolio-content">
				<?php 
					$show_post_title = econis_get_config('portfolio-title',true);
					if ($show_post_title){
						if ( is_single() ){
							the_title( '<h3 class="entry-title">', '</h3>' );
						}else {
							the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' );
						}
					}
				?>
				<div class="portfolio-excerpt clearfix">
				<?php
					the_content();
					wp_link_pages( array(
						'before'      => '<div class="page-links clearfix"><span class="page-links-title">' . esc_html__( 'Pages:', 'econis' ) . '</span>',
						'after'       => '</div>',
						'link_before' => '<span>',
						'link_after'  => '</span>',
					) );
				?>
				</div>
				<div class="portfolio-infomation">
					<?php $portfolio = get_the_terms(get_the_ID(),'category_portfolio'); ?>
					<div class="post-date">
						<strong><?php echo esc_html__("Date","econis") ?></strong>
						<span class="entry-date"><?php echo econis_time_link(); ?></span>
					</div>
					<?php if($portfolio){ ?>
					<div class="categories">
						<strong><?php echo esc_html__("Categories","econis") ?></strong>
						<ul class="list-categories">
							<?php foreach($portfolio as $portfolio){ ?>
							<li class="item-portfolio"><a href="<?php echo get_term_link($portfolio->term_id,'category_portfolio'); ?>"><?php echo esc_html($portfolio->name); ?></a></li>
							<?php } ?>
						</ul>
					</div>
					<?php } ?>
					<?php if(econis_get_config('show_share')) : ?>
					<div class="entry-single">
						<?php if ( shortcode_exists( 'social_share' ) ) : ?> 
						<div class="entry-social-share">
							<strong><?php echo esc_html__("share",'econis') ?></strong>
							<?php echo do_shortcode( "[social_share]" ); ?>	
						</div><?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			</div>
		</div>
	</div>
</article><!-- #post-## -->