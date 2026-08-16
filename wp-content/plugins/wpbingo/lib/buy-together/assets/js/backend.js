jQuery(document).ready(function ($) {
    "use strict";
    
    var products_data_array = {};
    
    // Auto complete
    function bwp_auto_complete_search_instant() {
        if (!$('#bwp_keyword').length) {
            return false;
        }
        $('#bwp_keyword').on('focus', function (e) {
            $('.buy-together-search-product-wrapper').addClass('show-search-results');
            var cur_key = $.trim($(this).val());
            if (cur_key == '') {
                $('#buy-together-results').html('<div class="results-inner"></div>');
            }
            bwp_update_products_ids();
            if ($.isEmptyObject(products_data_array)) {
                if ($('#bwp_keyword').is('.loading')) {
                    return false;
                }
                $('#bwp_keyword').addClass('loading');
                var data = {
                    action: 'bwp_auto_complete_search_data_via_ajax',
                    security: buy_together['security']
                };
                $.post(buy_together.ajaxurl, data, function (response) {
                    $('#bwp_keyword').focus();
                    products_data_array = response['array'];
                    if ($.trim(response['success']) == 'yes') {
                        $(document).on('keyup', '#bwp_keyword', function (e) {
                            bwp_destroy_sortable();
                            var $this = $(this);
                            var $searchResult = $('#buy-together-results');
                            var $searchWrap = $this.closest('.buy-together-inner-wrapper');
                            var search_key = $.trim($this.val());
                            if (products_data_array && search_key != '') {
                                var search_results = bwp_search_json(search_key, products_data_array);
                                if (search_results) {
                                    $searchResult.html('<div class="results-inner"></div>');
                                    var max_instant_search_results = 9999; // parseInt(wpbingo['max_instant_search_results']);
                                    if (isNaN(max_instant_search_results) || max_instant_search_results <= 0) {
                                        max_instant_search_results = 9999;
                                    }
                                    for (var i = 0; i < search_results.length && i < max_instant_search_results; i++) {
                                        $searchWrap.find('.results-inner').append(search_results[i]['post_html']);
                                    }
                                }
                            }
                            else {
                                $searchResult.html('');
                            }
                            bwp_sortable();
                            bwp_update_products_ids();
                        });
                        
                        $('#bwp_keyword').trigger('keyup');
                    }
                    
                    $('#bwp_keyword').removeClass('loading');
                });
            }
        });
    }
    
    bwp_auto_complete_search_instant();
    
    // Click on search result items
    $(document).on('click', '.buy-together-results .product-item', function (e) {
        var $this = $(this);
        var product_id = $this.attr('data-product_id');
        var min_price = $this.attr('data-min_price');
        var max_price = $this.attr('data-max_price');
        var inner_html = $this.find('.product-inner').html();
        
        if (!$('.buy-together-selected-products-list .selected-product-item[data-product_id="' + product_id + '"]').length) {
            var product_html = '<div class="selected-product-item" data-product_id="' + product_id + '" data-min_price="' + min_price + '" data-max_price="' + max_price + '">' +
                '<div class="product-inner">' + inner_html + '</div>' +
                '<a href="#" class="remove-btn" title="Remove">x</a>' +
                '</div>';
            $('.buy-together-selected-products-list').append(product_html);
            $this.addClass('buy-together-hidden');
        }
        bwp_update_products_ids();
    });
    
    function bwp_update_products_ids() {
        if ($('.buy-together-selected-products-list .selected-product-item').length) {
            var product_ids = '';
            
            $('.buy-together-selected-products-list .selected-product-item').each(function () {
                var this_product_id = $(this).attr('data-product_id');
                if (product_ids == '') {
                    product_ids += this_product_id;
                }
                else {
                    product_ids += ',' + this_product_id;
                }
                $('.buy-together-results .product-item[data-product_id="' + this_product_id + '"]').addClass('buy-together-hidden');
            });
            
            $('input[name="bwp_ids"]').val(product_ids);
        }
        else {
            $('input[name="bwp_ids"]').val('');
        }
    }
    
    bwp_update_products_ids();
    
    // Remove selected product
    $(document).on('click', '.buy-together-selected-products-list .selected-product-item .remove-btn', function (e) {
        var product_id = $(this).closest('.selected-product-item').attr('data-product_id');
        $('.buy-together-results .product-item[data-product_id="' + product_id + '"]').removeClass('buy-together-hidden');
        bwp_destroy_sortable();
        $(this).closest('.selected-product-item').remove();
        bwp_sortable();
        bwp_update_products_ids();
        e.preventDefault();
    });
    
    // Tabs
    function bwp_show_active_tab_content() {
        $('.buy-together-tabs').each(function () {
            var $thisTabs = $(this);
            var tab_id = $thisTabs.find('.nav-tab.nav-tab-active').attr('data-tab_id');
            $thisTabs.find('.tab-content').removeClass('tab-content-active');
            $thisTabs.find('.tab-content#' + tab_id).addClass('tab-content-active');
        });
    }
    
    bwp_show_active_tab_content();
    
    $(document).on('click', '.buy-together-tabs .nav-tab', function (e) {
        var $this = $(this);
        var $thisTabs = $this.closest('.buy-together-tabs');
        if ($this.is('.nav-tab-active')) {
            return false;
        }
        $thisTabs.find('.nav-tab').removeClass('nav-tab-active');
        $this.addClass('nav-tab-active');
        bwp_show_active_tab_content();
        e.preventDefault();
    });
    
    // Save all settings
    $(document).on('click', '.buy-together-save-all-settings', function (e) {
        var $this = $(this);
        if ($this.is('.processing')) {
            return false;
        }
        $this.addClass('processing disabled').prop('disabled', true);
        var all_settings = Array();
        $('.bwp-all-settings-form .buy-together-field').each(function () {
            var setting_key = $(this).attr('name');
            var setting_val = $(this).val();
            all_settings.push(
                {
                    'setting_key': setting_key,
                    'setting_val': setting_val
                }
            );
        });
        
        var data = {
            action: 'bwp_save_all_settings_via_ajax',
            all_settings: all_settings,
            nonce: buy_together['security']
        };
        
        $.post(buy_together['ajaxurl'], data, function (response) {
            bwp_display_multi_messages($('.bwp-all-settings-form'), response, 'bottom');
            $this.removeClass('processing disabled').prop('disabled', false);
        });
        
        e.preventDefault();
    });
    
    // Sortable
    function bwp_sortable() {
        if ($('.buy-together-sortable').length) {
            bwp_destroy_sortable();
            $('.buy-together-sortable').addClass('buy-together-already-init-sort').sortable({
                update: function (event, ui) {
                    bwp_update_products_ids();
                }
            });
            $('.buy-together-sortable').disableSelection();
        }
    }
    
    bwp_sortable();
    
    function bwp_destroy_sortable() {
        $('.buy-together-sortable.buy-together-already-init-sort').sortable('destroy').removeClass('buy-together-already-init-sort');
    }
    
    /**
     *
     * @param $form
     * @param response
     * @param position  top or bottom.
     */
    function bwp_display_multi_messages($form, response, position) {
        $form.find('.buy-together-message').remove();
        
        var msg_class = '';
        
        if (response['err'] === 'yes') {
            msg_class += 'alert-danger notice notice-error';
        }
        else {
            msg_class += 'alert-success updated notice notice-success';
        }
        
        if ($.type(response['message']) === 'string') {
            if (response['message'] !== '') {
                if (position === 'top') {
                    $form.prepend('<div class="buy-together-message alert ' + msg_class + '"><p>' + response['message'] + '</p></div>');
                }
                else {
                    $form.append('<div class="buy-together-message alert ' + msg_class + '"><p>' + response['message'] + '</p></div>');
                }
            }
        }
        else {
            $.each(response['message'], function (index, item) {
                if (position === 'top') {
                    $form.prepend('<div class="buy-together-message alert ' + msg_class + '"><p>' + item + '</p></div>');
                }
                else {
                    $form.append('<div class="buy-together-message alert ' + msg_class + '"><p>' + item + '</p></div>');
                }
            });
        }
    }
    
    // Click outside the search
    $(document).on('click', function (e) {
        var target = e.target;
        if (!$(target).is('.buy-together-search-product-wrapper') && !$(target).closest('.buy-together-search-product-wrapper').length) {
            $('.buy-together-search-product-wrapper').removeClass('show-search-results');
        }
    });
    
    function bwp_search_json(search_key, json_args) {
        var all_results = Array();
        $.each(json_args, function (i, v) {
            var regex = new RegExp(search_key, "i");
            if (v.post_title.search(new RegExp(regex)) != -1) {
                // Don't show current product in the results
                if (v.post_id != buy_together['editing_product_id']) {
                    all_results.push(v);
                }
                else {
                    // Do nothing
                }
            }
        });
        
        return all_results;
    }
    
});