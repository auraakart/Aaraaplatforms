<?php if($settings['list_tab']){ ?>
	<?php $j = 0; ?>
	<div class="bwp-slider <?php echo esc_attr($layout); ?>">
		<?php if($title1) { ?>
		<div class="content-title">
			<h2><?php echo wp_kses($title1,'social'); ?></h2>
		</div>
		<?php } ?>
		<div class="slick-carousel slick-carousel-center" data-dots="<?php echo esc_attr($show_pag);?>"  data-nav="<?php echo esc_attr($show_nav);?>" data-columns4="<?php echo $columns4; ?>" data-columns3="<?php echo $columns3; ?>" data-columns2="<?php echo $columns2; ?>" data-columns1="<?php echo $columns1; ?>" data-columns1440="<?php echo $columns1440; ?>" data-columns="<?php echo $columns; ?>">
			<?php foreach ($settings['list_tab'] as  $item){ ?>
				<div class="item">
					<div class="item-content">
						<div class="content-image">
							<?php if( $item['image'] && $item['image']['url'] ){ ?>
								<a href="<?php echo wp_kses_post($item['link_slider']); ?>"><img <?php if ($image_hover) { ?> class="elementor-animation-<?php echo esc_attr($image_hover); } ?>" src="<?php echo esc_url($item['image']['url']); ?>" alt="<?php echo esc_attr__('Image Slider','wpbingo'); ?>"></a>
							<?php } ?>
						</div>
						<?php if( $item['title_slider'] && $item['title_slider'] ){ ?>
							<h2 class="title-slider"><a href="<?php echo wp_kses_post($item['link_slider']); ?>"><?php echo esc_html($item['title_slider']); ?></a></h2>
						<?php } ?>
					</div>
				</div>
			<?php } ?>
		</div>
	</div>
<?php }?>