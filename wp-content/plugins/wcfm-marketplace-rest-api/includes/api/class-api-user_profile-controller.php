<?php

use Automattic\WooCommerce\Utilities\OrderUtil;

class WCFM_REST_User_Profile_Controller extends WC_REST_Customers_Controller {
  /**
   * Endpoint namespace
   *
   * @var string
   */
  protected $namespace = 'wcfmmp/v1';

  /**
   * Route name
   *
   * @var string
   */
  protected $base = 'user-profile';

  /**
   * Stores the request.
   * @var array
   */
  protected $request = array();

  /**
   * Load autometically when class initiate
   *
   * @since 1.0.0
   *
   * @return array
   */
  public function __construct() {
  }

  /**
   * Register the routes for settings.
   */
  public function register_routes() {
    register_rest_route($this->namespace, '/' . $this->base, array(
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array($this, 'get_user_profile'),
        'permission_callback' => array($this, 'get_user_profile_permissions_check'),
        'args'                => $this->get_collection_params(),
      ),
      array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => array($this, 'update_user_profile'),
        'permission_callback' => array($this, 'get_user_profile_permissions_check'),
        'args'                => $this->get_collection_params(),
      ),
      'schema' => array($this, 'get_public_item_schema'),
    ));
    register_rest_route($this->namespace, '/' . $this->base . '/my-orders', array(
      array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => array($this, 'get_current_user_orders'),
        'permission_callback' => array($this, 'get_user_profile_permissions_check'),
        'args'                => $this->get_collection_params_customer_order(),
      )
    ));
  }

  public function get_user_profile() {
    global $WCFM;
    //$user_profile = array();
    $current_user_id = get_current_user_id();
    $id        = (int) $current_user_id;
    $user_data = get_userdata($id);

    if (empty($id) || empty($user_data->ID)) {
      return new WP_Error('woocommerce_rest_invalid_id', __('Invalid resource ID.', 'woocommerce'), array('status' => 404)); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
    }

    $customer = $this->prepare_item_for_response($user_data, $request);

    return $customer;
  }

  public function get_user_profile_permissions_check() {
    if (!is_user_logged_in())
      return false;

    return true;
  }

  /**
   * Update a single user.
   *
   * @param WP_REST_Request $request Full details about the request.
   * @return WP_Error|WP_REST_Response
   */
  public function update_user_profile($request) {
    try {

      $current_user_id = get_current_user_id();
      $customer = new WC_Customer($current_user_id);


      if (!$customer->get_id()) {
        throw new WC_REST_Exception('woocommerce_rest_invalid_id', __('Invalid resource ID.', 'woocommerce'), 400); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      }

      if (!empty($request['email']) && email_exists($request['email']) && $request['email'] !== $customer->get_email()) {
        throw new WC_REST_Exception('woocommerce_rest_customer_invalid_email', __('Email address is invalid.', 'woocommerce'), 400); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      }

      if (!empty($request['username']) && $request['username'] !== $customer->get_username()) {
        throw new WC_REST_Exception('woocommerce_rest_customer_invalid_argument', __("Username isn't editable.", 'woocommerce'), 400); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      }

      // Customer email.
      if (isset($request['email'])) {
        $customer->set_email(sanitize_email($request['email']));
      }

      // Customer password.
      if (isset($request['password'])) {
        $customer->set_password($request['password']);
      }

      $this->update_customer_meta_fields($customer, $request);
      $customer->save();

      $user_data = get_userdata($customer->get_id());
      $this->update_additional_fields_for_object($user_data, $request);

      if (!is_user_member_of_blog($user_data->ID)) {
        $user_data->add_role('customer');
      }

      /**
       * Fires after a customer is created or updated via the REST API.
       *
       * @param WP_User         $customer  Data used to create the customer.
       * @param WP_REST_Request $request   Request object.
       * @param boolean         $creating  True when creating customer, false when updating customer.
       */
      do_action('woocommerce_rest_insert_customer', $user_data, $request, false);

      $request->set_param('context', 'edit');
      $response = $this->prepare_item_for_response($user_data, $request);
      $response = rest_ensure_response($response);
      return $response;
    } catch (Exception $e) {
      return new WP_Error($e->getErrorCode(), $e->getMessage(), array('status' => $e->getCode()));
    }
  }

  /**
   * Update customer meta fields.
   *
   * @param WC_Customer $customer
   * @param WP_REST_Request $request
   */
  protected function update_customer_meta_fields($customer, $request) {
    $schema = $this->get_item_schema();

    // Customer first name.
    if (isset($request['first_name'])) {
      $customer->set_first_name(wc_clean($request['first_name']));
    }

    // Customer last name.
    if (isset($request['last_name'])) {
      $customer->set_last_name(wc_clean($request['last_name']));
    }

    // Customer billing address.
    if (isset($request['billing'])) {
      foreach (array_keys($schema['properties']['billing']['properties']) as $field) {
        if (isset($request['billing'][$field]) && is_callable(array($customer, "set_billing_{$field}"))) {
          $customer->{"set_billing_{$field}"}($request['billing'][$field]);
        }
      }
    }

    // Customer shipping address.
    if (isset($request['shipping'])) {
      foreach (array_keys($schema['properties']['shipping']['properties']) as $field) {
        if (isset($request['shipping'][$field]) && is_callable(array($customer, "set_shipping_{$field}"))) {
          $customer->{"set_shipping_{$field}"}($request['shipping'][$field]);
        }
      }
    }
  }

  public function get_current_user_orders($request) {
    $args = array(
      'posts_per_page' => !empty($request['per_page']) ? intval($request['per_page']) : 1,
      'paged' => !empty($request['page']) ? intval($request['page']) : 1,
      'orderby' => !empty($request['orderby']) ? $request['orderby'] : 'registered_date',
      'order' => !empty($request['order']) ? $request['order'] : 'desc',
      'meta_key' => '_customer_user',
      'meta_value' => get_current_user_id(),
      'post_type' => wc_get_order_types('view-orders'),
      'post_status' => array_keys(wc_get_order_statuses()),
    );

    if (OrderUtil::custom_orders_table_usage_is_enabled()) {
      unset($args['meta_key']);
      unset($args['meta_value']);
      $args['customer_id'] = get_current_user_id();

      $customer_orders = wc_get_orders($args);
    } else {
      $customer_orders = get_posts($args);
    }

    $order_array = array();
    foreach ($customer_orders as $customer_order) {
      $order = wc_get_order($customer_order);
      $item_count = $order->get_item_count();
      $order_array[] = array(
        "id" => $order->get_order_number(),
        "date" => wc_format_datetime($order->get_date_created()), //$order->get_date_created()->date_i18n('Y-m-d'),
        "status" => wc_get_order_status_name($order->get_status()),
        "total" => $order->get_total(),
        "item_count" => $item_count,
        /* translators: %1$s is the formatted order total, %2$s is the item count. */
        "total_html" => sprintf(_n('%1$s for %2$s item', '%1$s for %2$s items', $item_count, 'woocommerce'), $order->get_formatted_order_total(), $item_count), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      );
    }
    return $order_array;
  }

  /**
   * Get the query params for collections.
   *
   * @return array
   */
  public function get_collection_params_customer_order() {
    $params = parent::get_collection_params();

    $params['order'] = array(
      'description'        => __('Order sort attribute ascending or descending.', 'woocommerce'), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      'type'               => 'string',
      'default'            => 'desc',
      'enum'               => array('asc', 'desc'),
      'validate_callback'  => 'rest_validate_request_arg',
    );
    $params['orderby'] = array(
      'description'        => __('Sort collection by object attribute.', 'woocommerce'), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Intentional reuse of existing translation from woocommerce
      'type'               => 'string',
      'default'            => 'date',
      'enum'               => array(
        'date',
        'id',
        'include',
        'title',
        'slug',
        'modified',
      ),
      'validate_callback'  => 'rest_validate_request_arg',
    );

    return $params;
  }
}
