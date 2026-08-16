<?php 
	get_header(); 
	$econis_settings = econis_global_settings();
?>
<div class="page-404">
	<div class="content-page-404">
		<div class="title-error">
			<?php if(isset($econis_settings['title-error']) && $econis_settings['title-error']){
				echo esc_html($econis_settings['title-error']);
			}else{
				echo esc_html__('404', 'econis');
			}?>
		</div>
		<div class="sub-title">
			<?php if(isset($econis_settings['sub-title']) && $econis_settings['sub-title']){
				echo esc_html($econis_settings['sub-title']);
			}else{
				echo esc_html__("Oops! That page can't be found.", "econis");
			}?>
		</div>
		<div class="sub-error">
			<?php if(isset($econis_settings['sub-error']) && $econis_settings['sub-error']){
				echo esc_html($econis_settings['sub-error']);
			}else{
				echo esc_html__("We're really sorry but we can't seem to find the page you were looking for.", 'econis');
			}?>
		</div>
		<a class="btn" href="<?php echo esc_url( home_url('/') ); ?>">
			<?php if(isset($econis_settings['btn-error']) && $econis_settings['btn-error']){
				echo esc_html($econis_settings['btn-error']);}
			else{
				echo esc_html__('Back The Homepage', 'econis');
			}?>
		</a>
	</div>
</div>
<?php
get_footer();