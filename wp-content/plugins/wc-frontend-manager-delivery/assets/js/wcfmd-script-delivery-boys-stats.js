$wcfm_delivery_boy_stats_table = '';

// Global — called by inline onclick on filter tabs; defined before ready() so it's always available
window.wcfmdFilterTab = function (btn) {
	document.querySelectorAll('.wcfmd-filter-tab').forEach(function (t) {
		t.classList.remove('wcfmd-tab-active');
	});
	btn.classList.add('wcfmd-tab-active');
	var status = btn.getAttribute('data-status');
	$status_type = (status !== null) ? String(status) : '';
	if ($wcfm_delivery_boy_stats_table) {
		$wcfm_delivery_boy_stats_table.ajax.reload();
	}
};

jQuery(document).ready(function ($) {

	$status_type       = 'pending';
	$wcfm_delivery_boy = $('#wcfm_delivery_boy_id').val();

	$wcfm_delivery_boy_stats_table = $('#wcfm_delivery_boy_stats').DataTable({
		"processing": true,
		"serverSide": true,
		"aFilter"   : false,
		"bFilter"   : false,
		"responsive": true,
		"language"  : $.parseJSON(dataTables_language),
		"columns"   : [
			{ responsivePriority: 1  },  // Status icon
			{ responsivePriority: 10 },  // Empty order col — hide first
			{ responsivePriority: 1  },  // Item (order# + product)
			{ responsivePriority: 5  },  // Store
			{ responsivePriority: 3  },  // Customer (name/phone)
			{ responsivePriority: 2  },  // Delivery address
			{ responsivePriority: 1  },  // Actions
		],
		"columnDefs": [{ "targets": 0, "orderable": false },
		{ "targets": 1, "orderable": false },
		{ "targets": 2, "orderable": false },
		{ "targets": 3, "orderable": false },
		{ "targets": 4, "orderable": false },
		{ "targets": 5, "orderable": false },
		{ "targets": 6, "orderable": false },
		],
		'ajax': {
			"type": "POST",
			"url" : wcfm_params.ajax_url,
			"data": function (d) {
				d.action            = 'wcfm_ajax_controller',
				d.controller        = 'wcfm-delivery-boys-stats',
				d.status_type       = $status_type,
				d.wcfm_delivery_boy = $wcfm_delivery_boy,
				d.wcfm_ajax_nonce   = wcfm_params.wcfm_ajax_nonce
			},
			"complete": function () {
				initiateTip();

				$('.show_order_items').click(function (e) {
					e.preventDefault();
					$(this).next('div.order_items').toggleClass("order_items_visible");
					return false;
				});

				// Fire wcfm-delivery-boys table refresh complete
				$(document.body).trigger('updated_wcfm_delivery_boy_stats');
			}
		}
	});

	if ($('#dropdown_status_type').length > 0) {
		$('#dropdown_status_type').on('change', function () {
			$status_type = $('#dropdown_status_type').val();
			$wcfm_delivery_boy_stats_table.ajax.reload();
		});
	}


	// Delete Staff
	$(document.body).on('updated_wcfm_delivery_boy_stats', function () {
		$('.wcfm_order_mark_delivered').each(function () {
			$(this).click(function (event) {
				event.preventDefault();
				var rconfirm = confirm(wcfm_delivery_boy_stats_messages.delivery_confirm);
				if (rconfirm) markWCFMDelivered($(this));
				return false;
			});
		});
	});

	function markWCFMDelivered(item) {
		jQuery('#wcfm_delivery_boy_stats_listing_expander').block({
			message   : null,
			overlayCSS: {
				background: '#fff',
				opacity   : 0.6
			}
		});
		var data = {
			action         : 'mark_wcfm_order_delivered',
			delivery_id    : item.data('delivery_id'),
			wcfm_ajax_nonce: wcfm_params.wcfm_ajax_nonce
		}
		jQuery.ajax({
			type   : 'POST',
			url    : wcfm_params.ajax_url,
			data   : data,
			success: function (response) {
				if ($wcfm_delivery_boy_stats_table) $wcfm_delivery_boy_stats_table.ajax.reload();
				jQuery('#wcfm_delivery_boy_stats_listing_expander').unblock();
			}
		});
	}

	// Screen Manager
	$(document.body).on('updated_wcfm_delivery_boy_stats', function () {
		$.each(wcfm_delivery_boy_stats_screen_manage, function (column, column_val) {
			$wcfm_delivery_boy_stats_table.column(column).visible(false);
		});
	});

	// Dashboard FIlter
	if ($('.wcfm_filters_wrap').length > 0) {
		$('.dataTable').before($('.wcfm_filters_wrap'));
		$('.wcfm_filters_wrap').css('display', 'inline-block');
	}
});