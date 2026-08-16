<?php
global $wp_query;
$total    = $wp_query->found_posts;
?>
<div class="woocommerce-found-posts pull-left">
	<?php echo esc_html__("There are ","econis").esc_attr($total).esc_html__(" products","econis"); ?>
</div>