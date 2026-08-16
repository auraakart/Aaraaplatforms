/**
 * Design System Extensions
 *
 * Guards the DS-library help widget against a cross-plugin interaction bug.
 *
 * Background: the design-system library (admin/wt-ds/js/script.js) ships with the
 * Order, Product and User basic plugins. Each copy of the library registers a
 * document-level click-outside-to-close handler that only knows about its own
 * plugin's widget selector (e.g. the Order copy checks .wbte_oimpexp_help-widget).
 * When multiple basic plugins are active on the same site, a click on Product's
 * widget bubbles to document, where Order's library sees "click was outside my
 * widget" and closes Order's help panel by accident (and vice versa).
 *
 * Fix: attach a click guard to each help widget that stops the click from
 * bubbling to document when it originated INSIDE a widget. Every library copy's
 * own close-outside logic then fires only for genuinely-outside clicks, which is
 * the intended behaviour. Public jQuery API only; no library files touched.
 */
(function($) {
	'use strict';

	if (window.wt_ds_help_widget_extensions_initialized) {
		return;
	}
	window.wt_ds_help_widget_extensions_initialized = true;

	var HELP_WIDGET_SELECTOR = '.wbte_oimpexp_help-widget, .wbte_pimpexp_help-widget, .wbte_uimpexp_help-widget';

	function bindWidgetClickGuards() {
		$(HELP_WIDGET_SELECTOR).each(function() {
			var $widget = $(this);

			$widget.off('click.wt_ds_help_widget_guard').on('click.wt_ds_help_widget_guard', function(e) {
				// Let anchors keep their default behaviour (navigation) and bubble so the
				// library closes the widget on link-click, matching prior UX.
				if ($(e.target).closest('a').length) {
					return;
				}
				e.stopPropagation();
			});
		});
	}

	$(document).ready(bindWidgetClickGuards);
	$(window).on('load', bindWidgetClickGuards);
})(jQuery);
