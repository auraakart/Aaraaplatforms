<div class="bwp-widget-video <?php echo esc_attr($layout); ?>">
	<?php  if($image): ?>
	<div class="bg-video">		
		<div class="video-wrapper videos">
			<div class="bwp-image">
				<div class="videoThumb">
					<img class="img-responsive" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr__("Image Video","wpbingo"); ?>" />
				</div>
			</div>
		</div>
	</div>
	<?php endif;?>
	<?php  if($link): ?>
	<div class="content">
		<?php
			$youtube_id = econis_get_youtube_video_id($link);
			$vimeo_id = econis_get_vimeo_video_id($link);
			$url_video = "#";
			if($youtube_id){
				$url_video = "https://www.youtube.com/embed/".esc_attr($youtube_id);
			}elseif($vimeo_id){
				$url_video = "https://player.vimeo.com/video/".esc_attr($vimeo_id);
			}
		?>
		<div class="bwp-video modal" data-src="<?php echo esc_attr($url_video); ?>">
			<i class="fa fa-play" aria-hidden="true"></i>
		</div>
		<div class="content-video modal fade" id="myModal">
			<div class="remove-show-modal"></div>
			<div class="modal-dialog modal-dialog-centered">
				<?php if($youtube_id){ ?>
					<iframe id="video" src="https://www.youtube.com/embed/<?php echo esc_attr($youtube_id); ?>" title="<?php echo esc_html__("YouTube video player","wpbingo"); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
				<?php }elseif($vimeo_id){?>
					<iframe id="video" src="https://player.vimeo.com/video/<?php echo esc_attr($vimeo_id); ?>"  frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
				<?php } ?>
			</div>
		</div>
	</div>
	<?php endif;?>
</div>
