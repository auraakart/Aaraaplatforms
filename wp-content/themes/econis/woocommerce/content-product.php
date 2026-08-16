<?php
/**
 * The template for displaying product content within loops
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 9.4.0
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
global $product, $woocommerce_loop, $post;
if ( ! $product || ! $product->is_visible() ) {
	return;
}
$category_view_mode = econis_category_view();
if($category_view_mode == 'list')
	$class_item_product	= "col-xl-6 col-lg-12 col-xs-12";
else	
	$class_item_product = econis_get_class_item_product();
?>
<li <?php post_class($class_item_product); ?>>
	<?php wc_get_template_part( 'content-'.esc_attr($category_view_mode), 'product' ); ?>
</li>