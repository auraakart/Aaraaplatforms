<?php

if (!defined('ABSPATH')) {
    exit;
}

class WCFMu_ShipStation_v2_REST_API_Loader {
    /**
     * Register ShipStation REST hooks.
     *
     * @return void
     */
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register ShipStation REST routes.
     *
     * @return void
     */
    public function register_routes() {
        global $WCFMu;

        include_once $WCFMu->plugin_path . 'includes/shipstation_v2/class-wcfmu-shipstation-rest-orders-controller.php';

        $orders_controller = new WCFMu_ShipStation_v2_REST_Orders_Controller();
        $orders_controller->register_routes();
    }
}
