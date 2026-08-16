<?php
/*
Plugin Name: Milk Delivery Schedule
Description: Adds delivery schedule fields to WooCommerce products and saves them to cart, order and subscriptions.
Version: 1.0.0
*/
if(!defined('ABSPATH')) exit;

add_action('woocommerce_before_add_to_cart_button', function(){
 global $product;
 // Only render for products that offer a subscription option. The block is
 // hidden by default and revealed by JS only when "Subscribe for" is chosen.
 if( ! class_exists('WCS_ATT_Product_Schemes') || ! WCS_ATT_Product_Schemes::has_subscription_schemes( $product ) ) return;
 ?>
<div class="mds-box" style="display:none">
<h5>Delivery Schedule</h5>
<p><label><input type="radio" name="delivery_schedule" value="everyday" checked> Every Day</label></p>
<p><label><input type="radio" name="delivery_schedule" value="alternate"> Alternate Day</label></p>
<p><label><input type="radio" name="delivery_schedule" value="weekend"> Every Weekend (Sat & Sun)</label></p>
<p><label><input type="radio" name="delivery_schedule" value="custom"> Custom</label></p>

<div id="mds-custom-days" style="display:none">
<label><input type="checkbox" name="delivery_days[]" value="1"> Monday</label>
<label><input type="checkbox" name="delivery_days[]" value="2"> Tuesday</label>
<label><input type="checkbox" name="delivery_days[]" value="3"> Wednesday</label>
<label><input type="checkbox" name="delivery_days[]" value="4"> Thursday</label>
<label><input type="checkbox" name="delivery_days[]" value="5"> Friday</label>
<label><input type="checkbox" name="delivery_days[]" value="6"> Saturday</label>
<label><input type="checkbox" name="delivery_days[]" value="0"> Sunday</label>
</div>
<?php
 $mds_slots = class_exists('\\Aaraa\\Admin\\Delivery_Slots_API') ? \Aaraa\Admin\Delivery_Slots_API::slots_payload('active') : array();
 if( ! empty( $mds_slots ) ) : ?>
 <div class="mds-slot">
  <label for="mds-delivery-slot"><strong>Delivery Slot</strong></label>
  <select name="delivery_slot" id="mds-delivery-slot">
   <option value=""><?php echo esc_html__( 'Select a delivery slot', 'woocommerce' ); ?></option>
   <?php foreach( $mds_slots as $mds_slot ) : ?>
    <option value="<?php echo esc_attr( $mds_slot['id'] ); ?>"><?php echo esc_html( $mds_slot['label'] ); ?></option>
   <?php endforeach; ?>
  </select>
 </div>
 <?php endif; ?>
</div>
<?php
});

/*
 * PHP `w` weekday index (0=Sun..6=Sat) => readable day name.
 */
function mds_day_name( $index ){
 $names = array( 0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday' );
 $index = (int) $index;
 return isset( $names[ $index ] ) ? $names[ $index ] : '';
}

/*
 * Normalise a posted/stored delivery-days list to unique `w` indices (0-6).
 * Accepts numeric indices or day names, so old and new data both work.
 */
function mds_normalize_days( $days ){
 $short = array( 'sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6 );
 $out   = array();
 foreach( (array) $days as $d ){
   if( is_numeric( $d ) ){
     $i = (int) $d;
     if( $i >= 0 && $i <= 6 ) $out[] = $i;
   } else {
     $key = substr( strtolower( trim( (string) $d ) ), 0, 3 );
     if( isset( $short[ $key ] ) ) $out[] = $short[ $key ];
   }
 }
 return array_values( array_unique( $out ) );
}

/*
 * Resolve a delivery slot id to its readable label.
 */
function mds_delivery_slot_label( $slot_id ){
 if( class_exists('\\Aaraa\\Admin\\Delivery_Slots_API') ){
  $slot = \Aaraa\Admin\Delivery_Slots_API::slot_by_id( $slot_id );
  if( $slot ) return $slot['label'];
 }
 return '';
}

add_action('wp_enqueue_scripts', function(){
 $css = plugin_dir_path(__FILE__).'assets/css/style.css';
 $js  = plugin_dir_path(__FILE__).'assets/js/script.js';
 wp_enqueue_style('mds-css', plugins_url('assets/css/style.css', __FILE__), [], file_exists($css) ? filemtime($css) : null);
 wp_enqueue_script('mds-js', plugins_url('assets/js/script.js', __FILE__), ['jquery'], file_exists($js) ? filemtime($js) : null, true);
});

add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id = 0){
 if( ! class_exists('WCS_ATT_Product_Schemes') ) return $passed;

 $product = $product_id ? wc_get_product($product_id) : null;
 if( ! $product || ! WCS_ATT_Product_Schemes::has_subscription_schemes($product) ) return $passed;

 // Only enforce when the customer chose "Subscribe for" (a scheme key is
 // posted). One-time purchases skip these fields.
 $scheme_key = WCS_ATT_Product_Schemes::get_posted_subscription_scheme($product_id);
 if( empty($scheme_key) ) return $passed;

 // Delivery schedule is mandatory.
 $schedule = isset($_POST['delivery_schedule']) ? sanitize_text_field(wp_unslash($_POST['delivery_schedule'])) : '';
 if( '' === $schedule ){
   wc_add_notice('Please choose a delivery schedule.','error');
   $passed = false;
 } elseif( 'custom' === $schedule && empty($_POST['delivery_days']) ){
   wc_add_notice('Please select at least one delivery day.','error');
   $passed = false;
 }

 // Delivery slot is mandatory when active slots are configured.
 $slots = class_exists('\\Aaraa\\Admin\\Delivery_Slots_API') ? \Aaraa\Admin\Delivery_Slots_API::slots_payload('active') : array();
 if( ! empty($slots) && empty($_POST['delivery_slot']) ){
   wc_add_notice('Please choose a delivery slot.','error');
   $passed = false;
 }

 return $passed;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function($data){
 if(isset($_POST['delivery_schedule']))
   $data['delivery_schedule']=sanitize_text_field($_POST['delivery_schedule']);
 if(!empty($_POST['delivery_days']))
   $data['delivery_days']=mds_normalize_days( wp_unslash( $_POST['delivery_days'] ) ); // stored as `w` indices 0-6.
 if(!empty($_POST['delivery_slot']))
   $data['delivery_slot']=absint($_POST['delivery_slot']);
 return $data;
});

add_filter('woocommerce_get_item_data', function($item,$cart){
 if(isset($cart['delivery_schedule']))
   $item[]=['key'=>'Delivery Schedule','value'=>ucwords(str_replace('_',' ',$cart['delivery_schedule']))];
 if(!empty($cart['delivery_days'])){
   $names = array_filter( array_map( 'mds_day_name', mds_normalize_days( $cart['delivery_days'] ) ) );
   if( $names ) $item[]=['key'=>'Delivery Days','value'=>implode(', ',$names)];
 }
 if(!empty($cart['delivery_slot'])){
   $slot_label = mds_delivery_slot_label($cart['delivery_slot']);
   if($slot_label) $item[]=['key'=>'Delivery Slot','value'=>$slot_label];
 }
 return $item;
},10,2);

add_action('woocommerce_checkout_create_order_line_item', function($item,$cart_key,$values){
 if(isset($values['delivery_schedule']))
   $item->add_meta_data('Delivery Schedule',$values['delivery_schedule']);
 if(!empty($values['delivery_days'])){
   $names = array_filter( array_map( 'mds_day_name', mds_normalize_days( $values['delivery_days'] ) ) );
   if( $names ) $item->add_meta_data('Delivery Days',implode(', ',$names));
 }
 if(!empty($values['delivery_slot'])){
   $item->add_meta_data('_delivery_slot_id',$values['delivery_slot']);
   $slot_label = mds_delivery_slot_label($values['delivery_slot']);
   $item->add_meta_data('Delivery Slot', $slot_label ?: $values['delivery_slot']);
 }
},10,3);

if(class_exists('WC_Subscriptions_Order')){
 /*
  * Copy the delivery schedule + slot onto the subscription, in the meta keys the
  * Aaraa metabox + REST API read: _wcfm_delivery_schedule (daily|alternate|
  * weekend|custom), _wcfm_delivery_days (int[] Mon=1..Sat=6, Sun=0) and
  * _aaraa_delivery_slot. The recurring cart ($recurring_cart, the 3rd arg) is the
  * reliable source — WCS only copies order-LEVEL meta, not line-item meta — with
  * the parent order line items as a fallback.
  */
 add_action('woocommerce_checkout_subscription_created', function($subscription,$order,$recurring_cart=null){

   $schedule_map = array('everyday'=>'daily','daily'=>'daily','alternate'=>'alternate','weekend'=>'weekend','custom'=>'custom');
   $sub_id       = $subscription->get_id();

   $raw_schedule = ''; $raw_days = array(); $slot_id = 0; $found = false;

   // 1) Prefer the recurring cart — it carries the raw cart-item data.
   if( $recurring_cart && ! empty( $recurring_cart->cart_contents ) ){
     foreach( $recurring_cart->cart_contents as $ci ){
       if( isset($ci['delivery_schedule']) || isset($ci['delivery_slot']) ){
         $raw_schedule = isset($ci['delivery_schedule']) ? $ci['delivery_schedule'] : '';
         $raw_days     = isset($ci['delivery_days']) ? (array) $ci['delivery_days'] : array();
         $slot_id      = isset($ci['delivery_slot']) ? absint($ci['delivery_slot']) : 0;
         $found        = true;
         break;
       }
     }
   }

   // 2) Fallback — read from the parent order line items.
   if( ! $found ){
     foreach( $order->get_items() as $order_item ){
       $rs = $order_item->get_meta('Delivery Schedule');
       if( $rs || $order_item->get_meta('_delivery_slot_id') ){
         $raw_schedule = $rs;
         $rd           = $order_item->get_meta('Delivery Days');
         $raw_days     = $rd ? array_map('trim', explode(',', $rd)) : array();
         $slot_id      = absint($order_item->get_meta('_delivery_slot_id'));
         $found        = true;
         break;
       }
     }
   }

   if( ! $found ) return;

   $schedule = isset($schedule_map[$raw_schedule]) ? $schedule_map[$raw_schedule] : '';

   $days = array();
   if( 'weekend' === $schedule ){
     $days = array(0,6);
   } elseif( 'custom' === $schedule && ! empty($raw_days) ){
     // $raw_days may be numeric indices (new carts) or day names (legacy/order
     // fallback); mds_normalize_days() handles both → unique `w` indices 0-6.
     $days = mds_normalize_days( $raw_days );
   }

   // Dual-write (CRUD object + post_meta) so the values are present under both
   // HPOS order storage and the legacy postmeta the metabox reads.
   if( $schedule ){
     $subscription->update_meta_data('_wcfm_delivery_schedule', $schedule);
     update_post_meta($sub_id, '_wcfm_delivery_schedule', $schedule);
   }
   if( ! empty($days) ){
     $subscription->update_meta_data('_wcfm_delivery_days', $days);
     update_post_meta($sub_id, '_wcfm_delivery_days', $days);
   } else {
     $subscription->delete_meta_data('_wcfm_delivery_days');
     delete_post_meta($sub_id, '_wcfm_delivery_days');
   }
   if( $slot_id ){
     $subscription->update_meta_data('_aaraa_delivery_slot', $slot_id);
     update_post_meta($sub_id, '_aaraa_delivery_slot', $slot_id);
   }

   $subscription->save();
 },10,3);
}
