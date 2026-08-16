jQuery(document).ready(function($) {
	// Subscription Status Update
	$('#wcfm_modify_subscription_status').click(function(event) {
		event.preventDefault();
		modifyWCFMSubscriptionStatus();
		return false;
	});
		
	function modifyWCFMSubscriptionStatus() {
		$('#subscriptions_details_general_expander').block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
		var data = {
			action              : 'wcfm_modify_subscription_status',
			subscription_status : $('#wcfm_subscription_status').val(),
			subscription_id     : $('#wcfm_modify_subscription_status').data('subscriptionid'),
			wcfm_ajax_nonce 	: wcfm_params.wcfm_ajax_nonce,
		}	
		$.ajax({
			type: 'POST',
			url: wcfm_params.ajax_url,
			data: data,
			success:	function(response) {
				$response_json = $.parseJSON(response);
				$('.wcfm-message').html('').removeClass('wcfm-error').removeClass('wcfm-success').slideUp();
				if($response_json.status) {
					wcfm_notification_sound.play();
					$('#wcfm_subscription_status_update_wrapper .wcfm-message').html('<span class="wcicon-status-completed"></span>' + $response_json.message).addClass('wcfm-success').slideDown( "slow" );
				}
				$('#subscriptions_details_general_expander').unblock();
			}
		});
	}
	
	// Subscription BillingSchedule Update
	$('#wcfm_subscription_billing_button').click(function(event) {
	  event.preventDefault();
	  
	  // Validations
		$('.wcfm-message').html('').removeClass('wcfm-error').removeClass('wcfm-success').slideUp();
		$wcfm_is_valid_form = true;
		$( document.body ).trigger( 'wcfm_form_validate', $('#wcfm_wcs_billing_schedule_update_form') );
		$is_valid = $wcfm_is_valid_form;
	  
	  if($is_valid) {
			$('#subscriptions_details_billing_schedule_expander').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
			var data = {
				action                                : 'wcfm_ajax_controller',
				controller                            : 'wcfm-subscriptions-manage',
				wcfm_wcs_billing_schedule_update_form : $('#wcfm_wcs_billing_schedule_update_form').serialize(),
				wcfm_ajax_nonce 						: wcfm_params.wcfm_ajax_nonce,
			}	
			$.post(wcfm_params.ajax_url, data, function(response) {
				if(response) {
					$response_json = $.parseJSON(response);
					$('.wcfm-message').html('').removeClass('wcfm-error').removeClass('wcfm-success').slideUp();
					if($response_json.status) {
						wcfm_notification_sound.play();
						$('#subscriptions_details_billing_schedule_expander .wcfm-message').html('<span class="wcicon-status-completed"></span>' + $response_json.message).addClass('wcfm-success').slideDown();
					} else {
						wcfm_notification_sound.play();
						$('#subscriptions_details_billing_schedule_expander .wcfm-message').html('<span class="wcicon-status-cancelled"></span>' + $response_json.message).addClass('wcfm-error').slideDown();
					}
					$('#subscriptions_details_billing_schedule_expander').unblock();
				}
			});	
		}
	});
	
	$('.date-picker').each(function() {
	  $(this).datepicker({
      dateFormat : 'yy-mm-dd',
      changeMonth: true,
      changeYear: true
    });
  });
	
	var timezone = jstz.determine();

	// Display the timezone for date changes
	$( '#wcs-timezone' ).text( timezone.name() );
	
	// Display times in client's timezone (based on UTC)
	$( '.woocommerce-subscriptions.date-picker' ).each(function() {
		var $date_input   = $(this),
			date_type     = $date_input.attr( 'id' ),
			$hour_input   = $( '#'+date_type+'_hour' ),
			$minute_input = $( '#'+date_type+'_minute' ),
			time          = $('#'+date_type+'_timestamp_utc').val(),
			date          = moment.unix(time);
			
		if ( time > 0 ) {
			date.local();
			$date_input.val( date.year() + '-' + ( zeroise( date.months() + 1 ) ) + '-' + ( date.format( 'DD' ) ) );
			$hour_input.val( date.format( 'HH' ) );
			$minute_input.val( date.format( 'mm' ) );
		}
	});

	// Make sure date pickers are in the future
	$( '.woocommerce-subscriptions.date-picker:not(#start)' ).datepicker( 'option','minDate',moment().add(1,'hours').toDate());

	// Validate date when hour/minute inputs change
	$( '[name$="_hour"], [name$="_minute"]' ).on( 'change', function() {
		$( '#' + $(this).attr( 'name' ).replace( '_hour', '' ).replace( '_minute', '' ) ).change();
	});

	// Validate entire date
	$( '.woocommerce-subscriptions.date-picker' ).on( 'change',function(){

		// The date was deleted, clear hour/minute inputs values and set the UTC timestamp to 0
		if( '' == $(this).val() ) {
			$( '#' + $(this).attr( 'id' ) + '_hour' ).val('');
			$( '#' + $(this).attr( 'id' ) + '_minute' ).val('');
			$( '#' + $(this).attr( 'id' ) + '_timestamp_utc' ).val(0);
			return;
		}

		var time_now          = moment(),
			one_hour_from_now = moment().add(1,'hours' ),
			$date_input   = $(this),
			date_type     = $date_input.attr( 'id' ),
			date_pieces   = $date_input.val().split( '-' ),
			$hour_input   = $( '#'+date_type+'_hour' ),
			$minute_input = $( '#'+date_type+'_minute' ),
			chosen_hour   = (0 == $hour_input.val().length) ? one_hour_from_now.format( 'HH' ) : $hour_input.val(),
			chosen_minute = (0 == $minute_input.val().length) ? one_hour_from_now.format( 'mm' ) : $minute_input.val(),
			chosen_date   = moment({
				years:   date_pieces[0],
				months: (date_pieces[1] - 1),
				date:   (date_pieces[2]),
				hours:   chosen_hour,
				minutes: chosen_minute,
				seconds: one_hour_from_now.format( 'ss' )
			});


		// Make sure start date is before now
		if ( 'start' == date_type ) {

			if ( false === chosen_date.isBefore( time_now ) ) {
				alert( wcs_admin_meta_boxes.i18n_start_date_notice );
				$date_input.val( time_now.year() + '-' + ( zeroise( time_now.months() + 1 ) ) + '-' + ( time_now.format( 'DD' ) ) );
				$hour_input.val( time_now.format( 'HH' ) );
				$minute_input.val( time_now.format( 'mm' ) );
			}

		}

		// Make sure trial end and next payment are after start date
		else if ( ( 'trial_end' == date_type || 'next_payment' == date_type ) && '' != $( '#start_timestamp_utc' ).val() ) {
			var change_date = false,
				start       = moment.unix( $('#start_timestamp_utc').val() );

			// Make sure trial end is after start date
			if ( 'trial_end' == date_type && chosen_date.isBefore( start, 'minute' ) ) {

				if ( 'trial_end' == date_type ) {
					alert( wcs_admin_meta_boxes.i18n_trial_end_start_notice );
				} else if ( 'next_payment' == date_type ) {
					alert( wcs_admin_meta_boxes.i18n_next_payment_start_notice );
				}

				// Change the date
				$date_input.val( start.year() + '-' + ( zeroise( start.months() + 1 ) ) + '-' + ( start.format( 'DD' ) ) );
				$hour_input.val( start.format( 'HH' ) );
				$minute_input.val( start.format( 'mm' ) );
			}
		}

		// Make sure next payment is after trial end
		if ( 'next_payment' == date_type && '' != $( '#trial_end_timestamp_utc' ).val() ) {
			var trial_end = moment.unix( $('#trial_end_timestamp_utc').val() );

			if ( chosen_date.isBefore( trial_end, 'minute' ) ) {
				alert( wcs_admin_meta_boxes.i18n_next_payment_trial_notice );
				$date_input.val( trial_end.year() + '-' + ( zeroise( trial_end.months() + 1 ) ) + '-' + ( trial_end.format( 'DD' ) ) );
				$hour_input.val( trial_end.format( 'HH' ) );
				$minute_input.val( trial_end.format( 'mm' ) );
			}
		}

		// Make sure trial end is before next payment and expiration is after next payment date
		else if ( ( 'trial_end' == date_type || 'end' == date_type ) && '' != $( '#next_payment' ).val() ) {
			var change_date  = false,
				next_payment = moment.unix( $('#next_payment_timestamp_utc').val() );

			// Make sure trial end is before or equal to next payment
			if ( 'trial_end' == date_type && next_payment.isBefore( chosen_date, 'minute' ) ) {
				alert( wcs_admin_meta_boxes.i18n_trial_end_next_notice );
				change_date = true;
			}
			// Make sure end date is after next payment date
			else if ( 'end' == date_type && chosen_date.isBefore( next_payment, 'minute' ) ) {
				alert( wcs_admin_meta_boxes.i18n_end_date_notice );
				change_date = true;
			}

			if ( true === change_date ) {
				$date_input.val( next_payment.year() + '-' + ( zeroise( next_payment.months() + 1 ) ) + '-' + ( next_payment.format( 'DD' ) ) );
				$hour_input.val( next_payment.format( 'HH' ) );
				$minute_input.val( next_payment.format( 'mm' ) );
			}
		}

		// Make sure the date is more than an hour in the future
		if ( 'trial_end' != date_type && 'start' != date_type && chosen_date.unix() < one_hour_from_now.unix() ) {

			alert( wcs_admin_meta_boxes.i18n_past_date_notice );

			// Set date to current day
			$date_input.val( one_hour_from_now.year() + '-' + ( zeroise( one_hour_from_now.months() + 1 ) ) + '-' + ( one_hour_from_now.format( 'DD' ) ) );

			// Set time if current time is in the past
			if ( chosen_date.hours() < one_hour_from_now.hours() || ( chosen_date.hours() == one_hour_from_now.hours() && chosen_date.minutes() < one_hour_from_now.minutes() ) ) {
				$hour_input.val( one_hour_from_now.format( 'HH' ) );
				$minute_input.val( one_hour_from_now.format( 'mm' ) );
			}
		}

		if( 0 == $hour_input.val().length ){
			$hour_input.val(one_hour_from_now.format( 'HH' ));
		}

		if( 0 == $minute_input.val().length ){
			$minute_input.val(one_hour_from_now.format( 'mm' ));
		}

		// Update the UTC timestamp sent to the server
		date_pieces = $date_input.val().split( '-' );

		$('#'+date_type+'_timestamp_utc').val(moment({
			years:   date_pieces[0],
			months: (date_pieces[1] - 1),
			date:   (date_pieces[2]),
			hours:   $hour_input.val(),
			minutes: $minute_input.val(),
			seconds: one_hour_from_now.format( 'ss' )
		}).utc().unix());

		$( 'body' ).trigger( 'wcs-updated-date',date_type);
	});

	function zeroise( val ) {
		return (val > 9 ) ? val : '0' + val;
	}

	/* ── Pause / Resume ─────────────────────────────────────────── */

	var _pauseFp     = null;
	var _pauseMode   = 'multiple';

	function initPausePicker(mode) {
		_pauseMode = mode;
		if (_pauseFp) { _pauseFp.destroy(); }
		_pauseFp = flatpickr('#wcfmu-pause-picker', {
			mode       : mode,
			dateFormat : 'Y-m-d',
			minDate    : 'today',
			onChange   : function(selectedDates) {
				var hint = '';
				if (mode === 'range' && selectedDates.length === 2) {
					hint = 'From ' + flatpickr.formatDate(selectedDates[0], 'Y-m-d') +
					       ' to ' + flatpickr.formatDate(selectedDates[1], 'Y-m-d');
				} else if (mode === 'multiple' && selectedDates.length) {
					hint = selectedDates.length + ' date(s) selected';
				}
				$('#wcfmu-pause-hint').text(hint);
			}
		});
	}

	// Mode toggle tabs
	$(document).on('click', '.wcfmu-mode-tab', function() {
		$('.wcfmu-mode-tab').removeClass('wcfmu-mode-active');
		$(this).addClass('wcfmu-mode-active');
		initPausePicker($(this).data('mode'));
		$('#wcfmu-pause-picker').val('');
		$('#wcfmu-pause-hint').text('');
	});

	// Open pause modal
	$(document).on('click', '#wcfm_sub_pause_btn', function() {
		$('#wcfmu-pause-picker').val('');
		$('#wcfmu-pause-hint').text('');
		$('.wcfmu-mode-tab').removeClass('wcfmu-mode-active').filter('[data-mode="multiple"]').addClass('wcfmu-mode-active');
		initPausePicker('multiple');
		$('#wcfmu-pause-overlay').fadeIn(200);
	});

	// Close modal
	$(document).on('click', '#wcfmu-pause-cancel, #wcfmu-pause-overlay', function(e) {
		if (e.target === this) {
			$('#wcfmu-pause-overlay').fadeOut(200);
		}
	});
	$(document).on('click', '#wcfmu-pause-modal', function(e) {
		e.stopPropagation();
	});

	// Confirm pause
	$(document).on('click', '#wcfmu-pause-confirm', function() {
		if (!_pauseFp) return;
		var dates = _pauseFp.selectedDates;
		if (!dates || !dates.length) {
			alert('Please select at least one date.');
			return;
		}

		var pauseDates = [];
		if (_pauseMode === 'range' && dates.length === 2) {
			// expand range to individual dates
			var cur = new Date(dates[0]);
			var end = new Date(dates[1]);
			while (cur <= end) {
				pauseDates.push(flatpickr.formatDate(cur, 'Y-m-d'));
				cur.setDate(cur.getDate() + 1);
			}
		} else {
			dates.forEach(function(d) { pauseDates.push(flatpickr.formatDate(d, 'Y-m-d')); });
		}

		var subId = $(this).data('subscriptionid');
		$(this).prop('disabled', true).text('Saving…');

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_pause_subscription',
			subscription_id : subId,
			pause_dates     : pauseDates,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce,
		}, function(resp) {
			$('#wcfmu-pause-confirm').prop('disabled', false).text('Confirm Pause');
			if (resp.success) {
				$('#wcfmu-pause-overlay').fadeOut(200);
				$('#wcfm_sub_pause_btn').hide();
				$('#wcfm_sub_resume_btn').show();
				// Update status badge
				var badge = $('.subscription-status');
				badge.attr('class', 'subscription-status subscription-status-pause').text('Paused');
				$('#wcfm_subscription_status').val('pause');
			} else {
				alert(resp.data || 'Error pausing subscription.');
			}
		}).fail(function() {
			$('#wcfmu-pause-confirm').prop('disabled', false).text('Confirm Pause');
			alert('Network error. Please try again.');
		});
	});

	// Manual resume
	$(document).on('click', '#wcfm_sub_resume_btn', function() {
		if (!confirm('Resume this subscription? It will activate if wallet is sufficient, or go on-hold otherwise.')) return;
		var subId = $(this).data('subscriptionid');
		$(this).prop('disabled', true);

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_resume_subscription',
			subscription_id : subId,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce,
		}, function(resp) {
			$('#wcfm_sub_resume_btn').prop('disabled', false);
			if (resp.success) {
				var newStatus = resp.data.status;
				$('#wcfm_sub_resume_btn').hide();
				if (newStatus !== 'pause') $('#wcfm_sub_pause_btn').show();
				var badge = $('.subscription-status');
				badge.attr('class', 'subscription-status subscription-status-' + newStatus)
				     .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));
				$('#wcfm_subscription_status').val(newStatus);
			} else {
				alert(resp.data || 'Error resuming subscription.');
			}
		}).fail(function() {
			$('#wcfm_sub_resume_btn').prop('disabled', false);
			alert('Network error. Please try again.');
		});
	});

	/* ── Delivery Dates ─────────────────────────────────────────── */

	var _ddFp    = null;
	var _ddDates = [];

	function initDeliveryPicker() {
		try {
			var saved = JSON.parse($('#wcfmu_dd_saved_json').val() || '[]');
			_ddDates  = Array.isArray(saved) ? saved : [];
		} catch(e) { _ddDates = []; }

		_ddFp = flatpickr('#wcfmu-delivery-picker', {
			mode       : 'multiple',
			dateFormat : 'Y-m-d',
			minDate    : 'today',
			defaultDate: _ddDates,
			onChange   : function(selectedDates) {
				_ddDates = selectedDates.map(function(d) { return flatpickr.formatDate(d, 'Y-m-d'); });
				renderDdChips();
			}
		});
	}

	function renderDdChips() {
		var $chips = $('#wcfmu-dd-chips');
		$chips.empty();
		_ddDates.forEach(function(d) {
			$chips.append(
				'<span class="wcfmu-dd-chip" data-date="' + d + '">' + d +
				'<button type="button" class="wcfmu-dd-chip-remove" data-date="' + d + '">&times;</button></span>'
			);
		});
	}

	// Remove chip
	$(document).on('click', '.wcfmu-dd-chip-remove', function() {
		var rem = $(this).data('date');
		_ddDates = _ddDates.filter(function(d) { return d !== rem; });
		if (_ddFp) _ddFp.setDate(_ddDates, false);
		renderDdChips();
	});

	// Save dates
	$(document).on('click', '#wcfmu-dd-save', function() {
		var subId = $(this).data('subscriptionid');
		var $msg  = $('.wcfmu-dd-message');
		$(this).prop('disabled', true);

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_save_sub_delivery_dates',
			subscription_id : subId,
			delivery_dates  : _ddDates,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce,
		}, function(resp) {
			$('#wcfmu-dd-save').prop('disabled', false);
			$msg.html('').removeClass('wcfm-error wcfm-success').slideUp();
			if (resp.success) {
				$msg.html('<span class="wcicon-status-completed"></span> Delivery dates saved.').addClass('wcfm-success').slideDown('slow');
				$('#wcfmu_dd_saved_json').val(JSON.stringify(resp.data.delivery_dates));
			} else {
				$msg.html('<span class="wcicon-status-cancelled"></span> ' + (resp.data || 'Error saving.')).addClass('wcfm-error').slideDown('slow');
			}
		}).fail(function() {
			$('#wcfmu-dd-save').prop('disabled', false);
			$msg.html('Network error.').addClass('wcfm-error').slideDown('slow');
		});
	});

	// Clear all
	$(document).on('click', '#wcfmu-dd-clear', function() {
		_ddDates = [];
		if (_ddFp) _ddFp.clear();
		renderDdChips();
	});

	// Init delivery picker when section expands
	$(document).on('click', '.subscriptions_details_delivery_dates', function() {
		if (!_ddFp) initDeliveryPicker();
	});

	/* ── Delivery Schedule Edit ─────────────────────────────────── */

	// Show/hide custom day checkboxes
	$(document).on('change', 'input[name="wcfmu_manage_schedule"]', function() {
		if ($(this).val() === 'custom') {
			$('#wcfmu-schedule-custom-days').slideDown(150);
		} else {
			$('#wcfmu-schedule-custom-days').slideUp(150);
		}
	});

	// Save schedule
	$(document).on('click', '#wcfmu-schedule-save', function() {
		var subId    = $(this).data('subscriptionid');
		var schedule = $('input[name="wcfmu_manage_schedule"]:checked').val();
		var days     = [];
		var $msg     = $('#wcfmu-schedule-msg');

		if (!schedule) {
			alert('Please select a schedule type.');
			return;
		}
		if (schedule === 'custom') {
			$('.wcfmu-schedule-day-check:checked').each(function() {
				days.push(parseInt($(this).val(), 10));
			});
			if (!days.length) {
				alert('Please select at least one delivery day.');
				return;
			}
		}

		$(this).prop('disabled', true);
		$msg.html('').removeClass('wcfm-error wcfm-success').slideUp();

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_update_subscription_schedule',
			subscription_id : subId,
			schedule_type   : schedule,
			custom_days     : days,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce,
		}, function(resp) {
			$('#wcfmu-schedule-save').prop('disabled', false);
			if (resp.success) {
				$msg.html('<span class="wcicon-status-completed"></span> ' + resp.data.message)
				    .addClass('wcfm-success').slideDown('slow');
			} else {
				$msg.html('<span class="wcicon-status-cancelled"></span> ' + (resp.data || 'Error updating schedule.'))
				    .addClass('wcfm-error').slideDown('slow');
			}
		}).fail(function() {
			$('#wcfmu-schedule-save').prop('disabled', false);
			$msg.html('Network error. Please try again.').addClass('wcfm-error').slideDown('slow');
		});
	});

	/* ── Subscription Item Edit / Remove / Add ──────────────────── */

	// Toggle inline edit row
	$(document).on('click', '.wcfmu-sub-edit-toggle', function(e) {
		e.preventDefault();
		e.stopPropagation();
		var itemId = $(this).data('itemid');
		var qty    = $(this).attr('data-qty');
		var price  = $(this).attr('data-price');
		var $row   = $('#wcfmu-edit-row-' + itemId);
		if ($row.css('display') !== 'none') {
			$row.css('display', 'none');
		} else {
			$('.wcfmu-sub-item-edit-row').css('display', 'none');
			$row.find('.wcfmu-edit-qty').val(qty);
			$row.find('.wcfmu-edit-price').val(price);
			$row.find('.wcfmu-item-save-msg').text('').css('color', '');
			var total = (parseFloat(qty) * parseFloat(price)).toFixed(2);
			$row.find('.wcfmu-edit-total-preview').text('= Total: ' + total);
			$row.css('display', 'table-row');
		}
	});

	// Live total preview on qty or price change
	$(document).on('input change', '.wcfmu-edit-qty, .wcfmu-edit-price', function() {
		var $row   = $(this).closest('.wcfmu-sub-item-edit-row');
		var qty    = parseFloat($row.find('.wcfmu-edit-qty').val()) || 0;
		var price  = parseFloat($row.find('.wcfmu-edit-price').val()) || 0;
		var total  = (qty * price).toFixed(2);
		$row.find('.wcfmu-edit-total-preview').text(qty && price ? '= Total: ' + total : '');
	});

	$(document).on('click', '.wcfmu-sub-cancel-edit', function() {
		$('#wcfmu-edit-row-' + $(this).data('itemid')).css('display', 'none');
	});

	// Save item changes
	$(document).on('click', '.wcfmu-sub-save-item', function() {
		var $btn   = $(this);
		var itemId = $btn.data('itemid');
		var subId  = $btn.data('subscriptionid');
		var qty    = parseInt($('#wcfmu-edit-row-' + itemId + ' .wcfmu-edit-qty').val()) || 0;
		var price  = parseFloat($('#wcfmu-edit-row-' + itemId + ' .wcfmu-edit-price').val()) || 0;
		var total  = (qty * price).toFixed(2);
		var $msg   = $('#wcfmu-item-msg-' + itemId);

		if (!qty || qty < 1) {
			$msg.text('Invalid qty.').css('color', '#dc2626');
			return;
		}
		if (price <= 0) {
			$msg.text('Invalid price.').css('color', '#dc2626');
			return;
		}
		$btn.prop('disabled', true);
		$msg.text('Saving…').css('color', '#6b7280');

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_sub_save_item',
			subscription_id : subId,
			item_id         : itemId,
			qty             : qty,
			total           : total,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
		}, function(resp) {
			$btn.prop('disabled', false);
			if (resp.success) {
				$msg.text(resp.data.message).css('color', '#16a34a');
				// Update data attrs so next Edit open reflects new values
				$('.wcfmu-sub-edit-toggle[data-itemid="' + itemId + '"]')
					.attr('data-qty', qty)
					.attr('data-price', price);
				setTimeout(function() { location.reload(); }, 1200);
			} else {
				$msg.text(resp.data || 'Error saving item.').css('color', '#dc2626');
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$msg.text('Network error.').css('color', '#dc2626');
		});
	});

	// Remove item
	$(document).on('click', '.wcfmu-sub-remove-item', function(e) {
		e.preventDefault();
		if (!confirm('Remove this item from the subscription?')) return;
		var $btn   = $(this);
		var itemId = $btn.data('itemid');
		var subId  = $btn.data('subscriptionid');
		$btn.prop('disabled', true).text('Removing…');

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_sub_remove_item',
			subscription_id : subId,
			item_id         : itemId,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
		}, function(resp) {
			if (resp.success) {
				$('tr[data-order_item_id="' + itemId + '"]').fadeOut(300, function() { $(this).remove(); });
				$('#wcfmu-edit-row-' + itemId).remove();
				setTimeout(function() { location.reload(); }, 800);
			} else {
				$btn.prop('disabled', false).text('× Remove');
				alert(resp.data || 'Error removing item.');
			}
		}).fail(function() {
			$btn.prop('disabled', false).text('× Remove');
			alert('Network error.');
		});
	});

	// Init product search select2 (lazy — also fires when the items section is first opened)
	var _wcfmuProductSelect2Inited = false;
	function wcfmuInitProductSelect2() {
		if (_wcfmuProductSelect2Inited) return;
		if (typeof $.fn.select2 === 'undefined') return;
		var $sel = $('#wcfmu-sub-add-product');
		if (!$sel.length) return;
		_wcfmuProductSelect2Inited = true;
		$sel.select2({
			width: '100%',
			placeholder: 'Search for a product…',
			allowClear: true,
			minimumInputLength: 2,
			ajax: {
				url      : wcfm_params.ajax_url,
				type     : 'GET',
				dataType : 'json',
				delay    : 300,
				data: function(params) {
					return {
						term            : params.term,
						action          : 'wcfm_json_search_products_and_variations',
						wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
					};
				},
				processResults: function(data) {
					var opts = [];
					if (data) {
						$.each(data, function(id, text) { opts.push({ id: id, text: text }); });
					}
					return { results: opts };
				},
				cache: true
			}
		});
	}

	// Try immediately
	wcfmuInitProductSelect2();

	// Retry when the Subscription Items collapsible section is opened
	$(document).on('click', '.subscriptions_details_subscription', function() {
		setTimeout(wcfmuInitProductSelect2, 150);
	});

	// Add item
	$(document).on('click', '#wcfmu-sub-add-item-btn', function() {
		var $btn      = $(this);
		var subId     = $btn.data('subscriptionid');
		var productId = $('#wcfmu-sub-add-product').val();
		var qty       = $('#wcfmu-sub-add-qty').val() || 1;
		var price     = $('#wcfmu-sub-add-price').val();
		var $msg      = $('#wcfmu-sub-add-item-msg');

		if (!productId) {
			$msg.html('<span style="color:#dc2626;">Please select a product.</span>').show();
			return;
		}
		$btn.prop('disabled', true);
		$msg.html('<span style="color:#6b7280;">Adding…</span>').show();

		$.post(wcfm_params.ajax_url, {
			action          : 'wcfm_sub_add_item',
			subscription_id : subId,
			product_id      : productId,
			qty             : qty,
			price           : price,
			wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
		}, function(resp) {
			$btn.prop('disabled', false);
			if (resp.success) {
				$msg.html('<span style="color:#16a34a;">' + resp.data.message + '</span>').show();
				setTimeout(function() { location.reload(); }, 1200);
			} else {
				$msg.html('<span style="color:#dc2626;">' + (resp.data || 'Error adding item.') + '</span>').show();
			}
		}).fail(function() {
			$btn.prop('disabled', false);
			$msg.html('<span style="color:#dc2626;">Network error.</span>').show();
		});
	});

});