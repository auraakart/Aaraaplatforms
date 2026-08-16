(function ($) {
	'use strict';

	if (window.wbtf_top_header_close_initialized) {
		return;
	}
	window.wbtf_top_header_close_initialized = true;

	var BANNER_CONFIGS = [
		{
			banner: '.wbtf_top_header',
			header: '.wbte_pimpexp_header',
			getAjaxUrl: function () {
				return typeof wt_piew_params !== 'undefined' ? wt_piew_params.ajax_url : '';
			},
			getData: function () {
				return { action: 'wt_piew_top_header_loaded' };
			}
		},
		{
			banner: '.wbtf_order_top_header',
			header: '.wbte_oimpexp_header',
			getAjaxUrl: function () {
				if (typeof wt_oiew_header_params !== 'undefined') {
					return wt_oiew_header_params.ajax_url;
				}
				if (typeof wt_iew_basic_params !== 'undefined') {
					return wt_iew_basic_params.ajax_url;
				}
				return '';
			},
			getData: function () {
				var data = { action: 'wt_oiew_top_header_loaded' };
				if (typeof wt_oiew_header_params !== 'undefined' && wt_oiew_header_params.nonce) {
					data._wpnonce = wt_oiew_header_params.nonce;
				} else if (typeof wt_iew_basic_params !== 'undefined' && wt_iew_basic_params.nonces) {
					data._wpnonce = wt_iew_basic_params.nonces.main;
				}
				return data;
			}
		},
		{
			banner: '.wbtf_users_top_header',
			header: '.wbte_uimpexp_header',
			getAjaxUrl: function () {
				return typeof wt_uiew_params !== 'undefined' ? wt_uiew_params.ajax_url : '';
			},
			getData: function () {
				return { action: 'wt_uiew_top_header_loaded' };
			}
		}
	];

	function getBannerConfig($banner) {
		var matchedConfig = null;

		BANNER_CONFIGS.some(function (config) {
			if ($banner.is(config.banner)) {
				matchedConfig = config;
				return true;
			}
			return false;
		});

		return matchedConfig;
	}

	function dismissBanner($banner) {
		var config = getBannerConfig($banner);
		var ajaxUrl = config ? config.getAjaxUrl() : '';

		if (!config || !ajaxUrl) {
			return;
		}

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: config.getData(),
			success: function (response) {
				if (response.success) {
					$(config.banner).remove();
					$(config.header).css('top', '0');
					$('#wpbody-content').css('margin-top', '80px');
				}
			}
		});
	}

	function initTopHeaderClose() {
		$(document).off('click.wbtf_top_header_close', '.wbtf_close_btn').on('click.wbtf_top_header_close', '.wbtf_close_btn', function (e) {
			e.preventDefault();
			dismissBanner($(this).closest('.wbtf_top_header, .wbtf_order_top_header, .wbtf_users_top_header'));
		});

		window.closeTopHeader = function () {
			var $banner = $('.wbtf_top_header, .wbtf_users_top_header').first();
			if ($banner.length) {
				dismissBanner($banner);
			}
		};

		window.wt_oiew_closeTopHeader = function () {
			var $banner = $('.wbtf_order_top_header').first();
			if ($banner.length) {
				dismissBanner($banner);
			}
		};
	}

	$(document).ready(initTopHeaderClose);
	$(window).on('load', initTopHeaderClose);

})(jQuery);
