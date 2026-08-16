<?php
/**
 * The Sidebar containing the main widget area
 *
 * @package Wpbingo
 * @subpackage Econis
 * @since Wpbingo Econis 1.0
 */
?>
<div id="secondary">
	<?php if ( has_nav_menu( 'secondary' ) ) : ?>
	<nav role="navigation" class="navigation site-navigation secondary-navigation">
		<?php wp_nav_menu( array( 'theme_location' => 'secondary' ) ); ?>
	</nav>
	<?php endif; ?>
	<?php if ( is_active_sidebar( 'sidebar-product' ) ) : ?>
	<div id="primary-sidebar" class="primary-sidebar widget-area" role="complementary">
		<?php dynamic_sidebar( 'sidebar-product' ); ?>
	</div><!-- #primary-sidebar -->
	<?php endif; ?>
</div><!-- #secondary -->