<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals
if (! defined('WPINC')) {
    die;
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );


$plugins_to_check = [
    'wt-woocommerce-addresslabel-addon/wt-woocommerce-addresslabel-addon.php',
    'wt-woocommerce-invoice-addon/wt-woocommerce-invoice-addon.php',
    'wt-woocommerce-picklist-addon/wt-woocommerce-picklist-addon.php',
    'wt-woocommerce-proforma-addon/wt-woocommerce-proforma-addon.php',
    'wt-woocommerce-shippinglabel-addon/wt-woocommerce-shippinglabel-addon.php'
];

$installed_plugins = get_plugins();
$i=0;
$plugins_checker = true;
foreach ($plugins_to_check as $plugin) {
    if (isset($installed_plugins[$plugin])) {
        $i++;
    }

}

if(5!=$i){
$image_path = WF_PKLIST_PLUGIN_URL . 'admin/partials/marketing-cta/assets/images/';
wp_enqueue_style('wt-bundle-styles', WF_PKLIST_PLUGIN_URL . 'admin/partials/marketing-cta/assets/css/marketing-cta.css', array(), WF_PKLIST_VERSION);
$bundle_url = 'https://www.webtoffee.com/pdf-invoices-packing-slips-suite-woocommerce/?utm_source=free_plugin_main_menu&utm_medium=pdf_basic&utm_campaign=Invoice_bundle';
?>

<div class="wt-bundle-container">
    <div class="wt-bundle-background">
        <figure>
            <img src="<?php echo esc_url($image_path); ?>background.png" alt="<?php esc_attr_e('Invoice Icons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>">
        </figure>
    </div>
    <div class="wt-bundle-content">
        <h2><?php esc_html_e('All-in-One WooCommerce Invoice & Shipping Documents Bundle', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></h2>
        <p><?php esc_html_e('Generate and print all essential WooCommerce documents, including invoices, packing slips, shipping labels, and more—all in one powerful invoice bundle.', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></p>

        <div class="wt-badge-container">
            <span class="badge"><?php esc_html_e('Invoices', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Packing Slips', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Credit Notes', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Address Labels', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Delivery Notes', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Dispatch Labels', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Shipping Labels', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Picklists', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <span class="badge"><?php esc_html_e('Proforma Invoices', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
        </div>

        <a href="<?php echo esc_url($bundle_url); ?>" class="wt-cta-button" target="_blank"><?php esc_html_e('Get Bundle Now', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></a>
    </div>

    <div class="wt-bundle-visual">
        <div class="wt-invoice-bundle">
            <span class="bundle-title">📄 <?php esc_html_e('Invoice Bundle', 'print-invoices-packing-slip-labels-for-woocommerce'); ?></span>
            <div class="bundle-icons">
                <figure>
                    <img src="<?php echo esc_url($image_path); ?>icons.png" alt="<?php esc_attr_e('Invoice Icons', 'print-invoices-packing-slip-labels-for-woocommerce'); ?>">
                </figure>
            </div>
        </div>
    </div>
</div>

<?php } ?>
