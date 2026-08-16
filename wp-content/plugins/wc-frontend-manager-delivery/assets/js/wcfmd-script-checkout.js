var wcfmdCheckoutHandler = (function ($) {
    var $checkoutForm, $deliveryLocation, $reqLocationField, $address, $lat, $lng, $deliveryMap, $shipping_methods;
    var timeSlot = {};
    var location = {
        address: '',
        lat    : '',
        lng    : '',
    };
    var reqTitle          = wc_address_i18n_params.i18n_required_text || 'required';
    var required          = '<abbr class="required" title="' + reqTitle + '">*</abbr>';
    var hideOnLocalPickup = wcfmd_delivery_time_options.hide_rule === 'yes';
    var _pvt              = {
        cacheDOM: function cacheDOM() {
            $checkoutForm     = $('form.woocommerce-checkout');
            $reqLocationField = $checkoutForm.find('#wcfmmp_user_location_is_required');
            $deliveryLocation = $checkoutForm.find('#wcfmmp_user_location_field');
            $address          = $checkoutForm.find('#wcfmmp_user_location');
            $lat              = $checkoutForm.find('#wcfmmp_user_location_lat');
            $lng              = $checkoutForm.find('#wcfmmp_user_location_lng');
            $deliveryMap      = $checkoutForm.find('div#wcfmmp-user-locaton-map');
            $shipping_methods = $checkoutForm.find('ul.woocommerce-shipping-methods .shipping_method');
            return this;
        },
        bindEvents: function bindEvents() {
            $checkoutForm.on('change', 'input[name^="shipping_method"]', this.onShippingMethodChange);
            return this;
        },
        onShippingMethodChange: function shippingMethodChanged() {
            var method = $(this).val();
            var vendor = $(this).data('index');
            if (!vendor) return;
            if (method.match("^local_pickup")) {
                if (hideOnLocalPickup) {
                    _pvt.hideDeliveryTime(vendor);
                }
                _pvt.mayHideDeliveryLocation();
            } else {
                if (hideOnLocalPickup) {
                    _pvt.showDeliveryTime(vendor);
                }
                _pvt.showDeliveryLocation();
            }
        },
        hideDeliveryTime: function hideDeliveryTime(vendor) {
            var $timeSlotField = $checkoutForm.find('#wcfmd_delvery_time_' + vendor + '_field');
            if ($timeSlotField.length) {
                timeSlot[vendor] = $timeSlotField.find('select').val();
                $timeSlotField.find('select').val('');
                $timeSlotField.removeClass('validate-required').fadeOut();
                $timeSlotField.find('> label > .required').remove();
                $checkoutForm.find('#_wcfmd_delvery_time_' + vendor + '_is_required').val('no');
            }
        },
        showDeliveryTime: function showDeliveryTime(vendor) {
            var $timeSlotField = $checkoutForm.find('#wcfmd_delvery_time_' + vendor + '_field');
            if ($timeSlotField.length && !$timeSlotField.hasClass('validate-required')) {
                $timeSlotField.find('select').val(timeSlot[vendor] || '');
                $timeSlotField.find('> label').append(required);
                delete timeSlot[vendor];
            }
            $checkoutForm.find('#_wcfmd_delvery_time_' + vendor + '_is_required').val('yes');
            $timeSlotField.addClass('validate-required').fadeIn();
        },
        mayHideDeliveryLocation: function mayHideDeliveryLocation() {
            if (!$deliveryLocation.length) return false;
            $nonLocalShippingMethods = $checkoutForm.find('ul.woocommerce-shipping-methods .shipping_method:not([id*="_local_pickup"])');
            if (!$nonLocalShippingMethods.filter(':checked').length) {
                location.address = $address.val() || '';
                location.lat     = $lat.val() || '';
                location.lng     = $lng.val() || '';
                $address.val('');
                $lat.val('');
                $lng.val('');
                $reqLocationField.val('no');
                $deliveryLocation.removeClass('validate-required').fadeOut();
                $deliveryLocation.find('> label > .required').remove();
                $deliveryMap.fadeOut();
            }
        },
        showDeliveryLocation: function showDeliveryLocation() {
            if (!$deliveryLocation.length) return false;
            if (!$deliveryLocation.hasClass('validate-required')) {
                $deliveryLocation.addClass('validate-required').fadeIn();
                $address.val(location.address);
                $lat.val(location.lat);
                $lng.val(location.lng);
                $reqLocationField.val('yes');
                $deliveryLocation.find('> label').append(required);
                $deliveryMap.fadeIn();
                location = { address: '', lat: '', lng: '', };
            }
        },
        setupEnv: function () {
            $shipping_methods.filter('[type="radio"]:checked, [type="hidden"]').each(_pvt.onShippingMethodChange);
            $deliveryLocation.find('> label > span').remove();
            if (hideOnLocalPickup) {
                $checkoutForm.find('[id^="wcfmd_delvery_time_"] > label > span').remove();
            }
        }
    };
    var _pub = {
        init: function init() {
            _pvt.cacheDOM().bindEvents().setupEnv();
        }
    }
    return _pub;
})(jQuery);
jQuery(wcfmdCheckoutHandler.init.bind(wcfmdCheckoutHandler));