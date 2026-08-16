<?php
/**
 * Show options for ordering
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/loop/orderby.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you (the theme developer).
 * will need to copy the new files to your theme to maintain compatibility. We try to do this.
 * as little as possible, but it does happen. When this occurs the version of the template file will.
 * be bumped and the readme will list any important changes.
 *
 * @see 	    http://docs.woothemes.com/document/template-structure/
 * @author 		WooThemes
 * @package 	WooCommerce/Templates
 * @version     9.7.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
$query_string = econis_get_query_string();
$query_string   = '?'.$query_string;
?>
<div class="woocommerce-ordering pwb-dropdown dropdown">
	<span class="pwb-dropdown-toggle dropdown-toggle" data-toggle="dropdown"><?php echo esc_html__("Default sorting","econis"); ?></span>
	<ul class="pwb-dropdown-menu dropdown-menu">
	<?php foreach ( $catalog_orderby_options as $id => $name ) :  ?>
		<li data-value="<?php echo esc_attr( $id ); ?>" <?php if ($orderby == $id){?> class="active" <?php } ?> ><a href="<?php echo add_query_arg('orderby', $id, $query_string); ?>"><?php echo esc_attr($name); ?></a></li>
	<?php endforeach;?>
	</ul>	
</div>