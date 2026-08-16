/**
 * Design System Extensions
 * 
 * This script extends the design system library functionality.
 * 
 * Extensions included:
 * - Help widget: Extends widget selector to support all basic plugins (order, product, user)
 */
(function($) {
	'use strict';
	
	function extendHelpWidget() {
		var widgetObjects = [];
		if (typeof wbte_oimpexp_help_widget !== 'undefined') widgetObjects.push(wbte_oimpexp_help_widget);
		if (typeof wbte_pimpexp_help_widget !== 'undefined') widgetObjects.push(wbte_pimpexp_help_widget);
		if (typeof wbte_uimpexp_help_widget !== 'undefined') widgetObjects.push(wbte_uimpexp_help_widget);
		
		if (widgetObjects.length === 0) return;
		
		// Create unified Set function once globally
		if (!window.wt_ds_unified_help_widget_set) {
			window.wt_ds_unified_help_widget_set = function() {
				$(document).off('click.wt_ds_help_widget');
				
				// Disable original handlers
				try {
					var events = $._data(document, 'events');
					if (events && events.click) {
						$.each(events.click, function(index, handler) {
							if (handler && handler.handler) {
								var handlerStr = handler.handler.toString();
								if ((handlerStr.indexOf('.wbte_oimpexp_help-widget') !== -1 || 
								     handlerStr.indexOf('.wbte_pimpexp_help-widget') !== -1 || 
								     handlerStr.indexOf('.wbte_uimpexp_help-widget') !== -1) &&
								    handlerStr.indexOf('#wt_ds_help-widget_hidden_checkbox') !== -1) {
									handler.handler = function() {}; // Disable original handler
								}
							}
						});
					}
				} catch (err) {}
				
				// Unified click handler for all widgets
				$(document).on('click.wt_ds_help_widget', function (e) {
					var $target = $(e.target);
					if ($target.is('input[type="checkbox"]') || $target.is('label') || $target.closest('label').length) {
						return;
					}
					
					var allWidgets = $('.wbte_oimpexp_help-widget, .wbte_pimpexp_help-widget, .wbte_uimpexp_help-widget');
					var clickedWidget = null;
					
					allWidgets.each(function() {
						var $widget = $(this);
						if ($widget.is(e.target) || $widget.has(e.target).length) {
							clickedWidget = $widget;
							return false;
						}
					});
					
					if (!clickedWidget) {
						allWidgets.each(function() {
							var $checkbox = $(this).find('#wt_ds_help-widget_hidden_checkbox');
							if ($checkbox.length && $checkbox.is(':checked')) {
								$checkbox.prop('checked', false);
							}
						});
					}
				});
			};
		}
		
		// Override Set function for all widget objects
		$.each(widgetObjects, function(index, widgetObj) {
			widgetObj.Set = window.wt_ds_unified_help_widget_set;
			widgetObj.Set();
		});
	}
	
	// Override Set() immediately to prevent original handlers
	if (typeof wbte_oimpexp_help_widget !== 'undefined' || 
	    typeof wbte_pimpexp_help_widget !== 'undefined' || 
	    typeof wbte_uimpexp_help_widget !== 'undefined') {
		var tempWidgets = [];
		if (typeof wbte_oimpexp_help_widget !== 'undefined') tempWidgets.push(wbte_oimpexp_help_widget);
		if (typeof wbte_pimpexp_help_widget !== 'undefined') tempWidgets.push(wbte_pimpexp_help_widget);
		if (typeof wbte_uimpexp_help_widget !== 'undefined') tempWidgets.push(wbte_uimpexp_help_widget);
		
		$.each(tempWidgets, function(index, widgetObj) {
			if (widgetObj.Set && typeof widgetObj.Set === 'function') {
				widgetObj._originalSet = widgetObj.Set;
				widgetObj.Set = function() {}; // No-op
			}
		});
	}
	
	// Initialize on DOM ready
	$(document).ready(function() {
		setTimeout(function() {
			extendHelpWidget();
		}, 300);
	});
})(jQuery);
