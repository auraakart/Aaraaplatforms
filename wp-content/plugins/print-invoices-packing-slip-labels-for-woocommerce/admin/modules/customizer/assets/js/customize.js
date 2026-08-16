var pklist_customize = {};
(function ($) {
	'use strict';
	$(function () {
		pklist_customize.Set();
		preview_pdf.Set();
	});

	$('.wf_invoice_date_sele').on('change', function () {
		var vl = $(this).val();
		$('.wf_invoice_date_txt').val('');
	});
	$('body').on('click', '.wf_missing_wrn', function (e) {
		e.preventDefault();
		if (confirm(wf_woocommerce_packing_list_customizer.labels.leaving_page_wrn)) {
			window.location.href = $(this).attr('href');
		} else {
			return false;
		}
	});

	$(document).ready(function () {
		if ($('.wf_customizer_main').length > 0) {
			$('.wf_customizer_main').on('input change', 'input, select, textarea', function () {
				if ($('.wf_pklist_save_theme').length > 0) {
					$('.wf_pklist_save_theme').removeClass('btn-disable');
				}
			});

			if ($('.wf_customizer_main .wp-color-result').length > 0) {
				$('.wf_customizer_main .wp-color-result').on('click', function () {
					if ($('.wf_pklist_save_theme').length > 0) {
						$('.wf_pklist_save_theme').removeClass('btn-disable');
					}
				});
			}

			if ($('.wf_template_name_field').length > 0) {
				$('.wf_template_name_field').on('input', function () {
					if ($('.wf_pklist_save_theme').length > 0) {
						$('.wf_pklist_save_theme').removeClass('btn-disable');
					}
				});
			}
		}
	});

	pklist_customize =
	{
		template_base: 0,
		template_id: 0,
		pendingHtmlRead: 0,
		template_is_active: 0,
		enable_code_view: true,
		open_first_panel: false,
		initial_tmr: null,
		updt_frm_cde_vew_tmr: null,
		to_hide_css: 'wfte_hidden',
		text_right_css: 'wfte_text_right',
		text_left_css: 'wfte_text_left',
		text_center_css: 'wfte_text_center',
		Set: function () {
			this.template_id = wf_woocommerce_packing_list_customizer.template_id;
			this.template_is_active = wf_woocommerce_packing_list_customizer.template_is_active;
			this.enable_code_view = wf_woocommerce_packing_list_customizer.enable_code_view;
			this.open_first_panel = wf_woocommerce_packing_list_customizer.open_first_panel;

			/* js tab view issue */
			if ($('div[data-id="wf_woocommerce_packing_list-customize"]').is(':visible')) {
				this.loadTemplateData(pklist_customize.template_id);
				this.regEvents();
			} else {
				this.initial_tmr = setInterval(function () {
					if ($('div[data-id="wf_woocommerce_packing_list-customize"]').is(':visible')) {
						clearInterval(pklist_customize.initial_tmr);
						pklist_customize.loadTemplateData(pklist_customize.template_id);
						pklist_customize.regEvents();
					}
				}, 1000);
			}
		},
		regEvents: function () {
			this.defaultThemes();
			this.myThemes();
			this.regThemeActivation();
			this.regDeleteTheme();
			this.regCustomizeThemeClick();
			this.dropDownMenu();
			if (this.enable_code_view) {
				this.designAndCodeViewTab();
			}
			this.regSaveTheme();
		},

		regCustomizeThemeClick: function () {
			$(document).on("click", '.wf_customize_theme', function (event) {
				pklist_customize.template_id = $(this).attr('data-id');
				wf_popup.hidePopup();
				pklist_customize.loadTemplateData(pklist_customize.template_id);
			});
		},
		regDeleteTheme: function () {
			$(document).on("click", '.wf_delete_theme', function (event) {
				if (confirm(wf_woocommerce_packing_list_customizer.labels.sure)) {
					var template_id = $(this).attr('data-id');
					pklist_customize.loadMyThemes('delete', template_id);
				}
			});
		},
		regThemeActivation: function () {
			$(document).on("click", '.wf_activate_theme', function (event) {
				pklist_customize.setLoader();
				var template_id = $(this).attr('data-id');
				pklist_customize.loadMyThemes('activate', template_id);
			});
		},
		loadMyThemes: function (template_action, template_id) {
			var data = {
				_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
				action: "wfpklist_customizer_ajax",
				customizer_action: "my_templates",
				template_type: wf_woocommerce_packing_list_customizer.template_type,
			};
			if (template_action) {
				data.template_action = template_action;
				data.template_id = template_id;
			}
			$('.wf_my_template_list').addClass('wf_loader_bg').html('');
			$.ajax({
				type: 'POST',
				url: wf_woocommerce_packing_list_customizer.ajax_url,
				data: data,
				dataType: 'json',
				success: function (data) {
					$('.wf_my_template_list').removeClass('wf_loader_bg');
					if (1 === data.status || "1" === data.status) {
						$('.wf_my_template_list').html(data.html);
						if (template_action) {
							if ("activate" === template_action) {
								pklist_customize.loadTemplateData(template_id);
							}
							else if ("delete" === template_action && template_id == pklist_customize.template_id) {
								window.location.reload();
							}
						}
					} else {
						$('.wf_my_template_list').html(data.msg);
					}
				},
				error: function () {
					$('.wf_my_template_list').removeClass('wf_loader_bg');
					$('.wf_my_template_list').html(wf_woocommerce_packing_list_customizer.labels.error);
				}
			});
		},
		myThemes: function () {
			/* my template popup */
			$('.wf_pklist_my_templates').on('click', function () {
				var popup_elm = $('.wf_my_template');
				wf_popup.showPopup(popup_elm);
				$('.wf_my_template_search').val('');
				$('.wf_dropdown[data-target="wf_customizer_drp_menu"]').hide();
				pklist_customize.loadMyThemes();
			});

			/* my template search */
			$('.wf_my_template_search').on('keyup', function () {
				var vl = $(this).val().trim();
				if ("" !== vl) {
					vl = vl.toLowerCase();
					$('.wf_my_template_item').hide();
					var kk = $('.wf_my_template_item').filter(function () {
						var name = $(this).find('.wf_my_template_item_name').text();
						name = name.toLowerCase();
						if (-1 !== name.search(vl)) {
							return true;
						} else {
							return false;
						}
					});
					kk.show();
				} else {
					$('.wf_my_template_item').show();
				}
			});
		},
		regSaveTheme: function () {
			$('.wt_pklist_dc_save_activate_btn').on('click', function () {
				if (0 === pklist_customize.template_id || "0" === pklist_customize.template_id) /* new theme, then prompt for name */ {
					$('.wf_template_name_wrn').hide();
					$('.wf_template_name_field').val('');
					// wf_popup.showPopup($('.wf_template_name'));
					$('.wf_pklist_popup .wf_template_create_btn').addClass('wt_save_activate');
					$('.wf_template_name_field').focus();
				} else {
					pklist_customize.saveTheme(true);
				}
			});

			$('.wf_pklist_save_theme, .wf_pklist_save_theme_sub').on('click', function () {
				if (0 === pklist_customize.template_id || "0" === pklist_customize.template_id) /* new theme, then prompt for name */ {
					pklist_customize.saveTheme();
				} else {
					pklist_customize.saveTheme();
				}
			});
			$('.wf_template_create_btn').on('click', function () {
				var name = $('.wf_template_name_field').val().trim();
				if ("" === name) {
					$('.wf_template_name_wrn').show();
					$('.wf_template_name_field').focus();
				} else {
					if ($(this).hasClass('wt_save_activate')) {
						pklist_customize.saveTheme(true);
					} else {
						pklist_customize.saveTheme();
					}
					$('.wf_template_name_wrn').hide();
					wf_popup.hidePopup();
				}
			});
			$('.wf_template_name_field').keypress(function (e) {
				if (13 === e.keyCode || "13" === e.keyCode) /* save */ {
					$('.wf_template_create_btn').trigger('click');
				}
			});
		},
		saveTheme: function (do_current_theme_activate = false, silentSuccess = false, skipLoader = false) {
			let templateName = $('.wf_template_name_field').val();
			if (templateName === '') {
				wf_notify_msg.error(wf_woocommerce_packing_list_customizer.labels.template_required);
				return;
			}
			var data = {
				_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
				action: "wfpklist_customizer_ajax",
				customizer_action: "save_theme",
				template_type: wf_woocommerce_packing_list_customizer.template_type,
				template_id: pklist_customize.template_id,
				codeview_html: pklist_customize.getCodeViewHtml(),
				def_template: pklist_customize.template_base,
				name: $('.wf_template_name_field').val(),
			};
			if (!skipLoader) {
				this.setLoader();
			}
			$.ajax({
				type: 'POST',
				url: wf_woocommerce_packing_list_customizer.ajax_url,
				data: data,
				dataType: 'json',
				success: function (data) {
					if (!skipLoader) {
						pklist_customize.removeLoader();
					}

					if (1 === data.status || "1" === data.status) {
						pklist_customize.template_id = data.template_id;
						pklist_customize.template_is_active = data.is_active;
						if (do_current_theme_activate === true) {
							var template_id = data.template_id;
							pklist_customize.loadMyThemes('activate', template_id);
						} else {
							pklist_customize.setCurrentThemeActionBtns();
							$('.wf_cst_theme_name').html(data.name);
							if (!silentSuccess) {
								wf_notify_msg.success(data.msg);
							}
						}

						if (data.new_entry === 1) {
							$('.wf_template_create_btn').hide();
							$('.wt_save_activate').hide();
							$('.wf_pklist_save_theme').show();
						}
					} else {
						wf_notify_msg.error(data.msg);
					}
				},
				error: function () {
					if (!skipLoader) {
						pklist_customize.removeLoader();
					}
					wf_notify_msg.error(wf_woocommerce_packing_list_customizer.labels.error);
				}
			});
		},
		designAndCodeViewTab: function () {
			/* design/code tabview */
			$('.wf_cst_tabhead').on('click', function () {
				var trgt = $(this).attr('data-target');
				if ($('.' + trgt).is(':visible')) {
					return false;
				}
				$('.wf_cst_tabhead').css({ 'background': '#ebebeb' });
				$(this).css({ 'background': '#f5f5f5' });
				$('.wf_customize_inner').hide();
				$('.' + trgt).show();
				if ("wf_customize_code_container" === trgt) {
					//pklist_customize.disableSidePanelMain();
					wf_code_editor.refresh();
				} else {
					var codeview_html = wf_code_editor.getDoc().getValue();
					var textarea_vl = $('#wfte_code').val();
					if (codeview_html != textarea_vl) {
						pklist_customize.updateFromCodeView();
					} else {
						if (pklist_customize.pendingHtmlRead == 1) {
							pklist_customize.readHTML();
						} else {
							//pklist_customize.enableSidePanelMain();
						}
					}
				}
			});
		},
		disableSidePanelMain: function () {
			$('.wf_customize_sidebar').css({ 'opacity': '.1', 'cursor': 'not-allowed' });
		},
		enableSidePanelMain: function () {
			$('.wf_customize_sidebar').css({ 'opacity': '1' });
		},
		isCodeView: function () {
			return $('.wf_customize_code_container').is(':visible');
		},
		dropDownMenu: function () {
			/* main customizer dropdown */
			$('.wf_customizer_drp_menu').on('click', function () {
				var drp_menu = $('.wf_dropdown[data-target="wf_customizer_drp_menu"]');
				if (drp_menu.is(':visible')) {
					drp_menu.hide();
				} else {
					var pos = $(this).position();
					var t = pos.top + ($(this).height() / 2) + 2;
					var l = pos.left - drp_menu.outerWidth() + $(this).outerWidth();
					drp_menu.css({ 'display': 'block', 'left': l, 'top': t, 'opacity': 0 }).stop(true, true).animate({ 'top': t + 5, 'opacity': 1 });
				}
			});

			$('body, body *').on('click', function (e) {
				var drp_menu = $('.wf_dropdown[data-target="wf_customizer_drp_menu"]');
				if (drp_menu.is(':visible')) {
					if ($(e.target).hasClass('wf_dropdown') === false && $(e.target).hasClass('wf_customizer_drp_menu') === false && $(e.target).hasClass('dashicons') === false) {
						drp_menu.hide();
					}
				}
			});
		},
		defaultThemes: function () {
			/* default template popup */
			$('.wf_pklist_new_template').on('click', function () {
				var popup_elm = $('.wf_default_template_list');
				wf_popup.showPopup(popup_elm);
				$('.wf_dropdown[data-target="wf_customizer_drp_menu"]').hide();
			});


			/* default template choose */
			$('.wf_default_template_list_item').on('click', function () {

				$('.wf_pklist_save_theme').css('display', 'none');
				$('.wf_template_create_btn').show();
				$('.wt_save_activate').show();

				pklist_customize.template_base = $(this).attr('data-id');
				pklist_customize.template_id = 0;
				wf_popup.hidePopup();
				pklist_customize.loadTemplateData(pklist_customize.template_id);
				if ("addresslabel" === wf_woocommerce_packing_list_customizer.template_type) {
					$(".wf_cst_change_addrlabel").val(pklist_customize.template_base);
				}
			});
		},
		updateCustomizerOnCodeChange: function () {
			/*
			wf_code_editor.on('changes',function(cm,change){
				
			});
			*/
		},
		updateFromCodeView: function () {
			var data = {
				_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
				action: "wfpklist_customizer_ajax",
				customizer_action: "update_from_codeview",
				template_type: wf_woocommerce_packing_list_customizer.template_type,
				codeview_html: pklist_customize.getCodeViewHtml(),
			};
			this.setLoader();
			$.ajax({
				type: 'POST',
				url: wf_woocommerce_packing_list_customizer.ajax_url,
				data: data,
				dataType: 'json',
				success: function (data) {
					pklist_customize.removeLoader();
					if (1 === data.status || "1" === data.status) {
						$('#wfte_code').val(data.codeview_html);
						if (pklist_customize.enable_code_view) {
							wf_code_editor.getDoc().setValue(data.codeview_html);
							wf_code_editor.refresh();
						}
						$('.wf_customize_container .wf_customize_vis_container').html(data.html);
						pklist_customize.readHTML();
					} else {
						wf_notify_msg.error(data.msg);
					}
				},
				error: function () {
					pklist_customize.removeLoader();
					wf_notify_msg.error(wf_woocommerce_packing_list_customizer.labels.error);
				}
			});
		},
		setCurrentThemeActionBtns: function () {
			var act_btn_elms = $('.wf_activate_theme_current, .wf_delete_theme_current');
			var save_activate_btn = $('.wt_pklist_dc_save_activate_btn');
			act_btn_elms.attr('data-id', this.template_id).hide();
			save_activate_btn.attr('data-id', this.template_id).hide();
			if (this.template_id > 0) {
				$('.wf_pklist_new_template').html(wf_woocommerce_packing_list_customizer.labels.create_new);
				$('.wf_new_template_wrn_sub').show(); /* save button and msg at new template popup */
			} else {
				$('.wf_pklist_new_template').html(wf_woocommerce_packing_list_customizer.labels.change_theme);
				$('.wf_new_template_wrn_sub').hide(); /* save button and msg at new template popup */
			}

			if ((0 === this.template_is_active || "0" === this.template_is_active) && this.template_id > 0) {
				act_btn_elms.show();
				save_activate_btn.show();
			}

			if ((0 === this.template_is_active || '0' === this.template_is_active) && (0 === this.template_id || '0' === this.template_id)) {
				save_activate_btn.show();
			}
		},
		loadTemplateData: function (template_id) {
			var data = {
				_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
				action: "wfpklist_customizer_ajax",
				customizer_action: "get_template_data",
				template_type: wf_woocommerce_packing_list_customizer.template_type,
				template_id: template_id,
				def_template: pklist_customize.template_base,
			};
			this.setLoader();
			$.ajax({
				type: 'GET',
				url: wf_woocommerce_packing_list_customizer.ajax_url,
				data: data,
				dataType: 'json',
				success: function (data) {
					pklist_customize.removeLoader();
					if (1 === data.status || "1" === data.status) {

						$('.wf_template_name_field').val(data.name);
						$('.wf_cst_theme_name').html(data.name);
						$('#wfte_code').val(data.codeview_html);
						if (pklist_customize.enable_code_view) {
							wf_code_editor.getDoc().setValue(data.codeview_html);
						}
						$('.wf_customize_container .wf_customize_vis_container').html(data.html);

						pklist_customize.template_is_active = data.is_active;
						pklist_customize.setCurrentThemeActionBtns();

						if ((0 === data.qrocode_compatible) || ("0" === data.qrocode_compatible)) {
							$('.template_qr_compatible_err').show();
							$("#template_qr_compatible_err_val").val(1);
						} else if ((3 === data.qrocode_compatible) || ("3" === data.qrocode_compatible)) {
							$('.template_qr_compatible_err').hide();
							$("#template_qr_compatible_err_val").val(3);
						} else {
							$('.template_qr_compatible_err').hide();
							$("#template_qr_compatible_err_val").val(2);
						}

						if ("undefined" !== typeof wf_woocommerce_packing_list_customizer.labels.basic_template_wrn && "undefined" !== typeof wf_woocommerce_packing_list_customizer.labels.pro_template_wrn) {
							if (false === data.is_pro_customizer && (2 === data.wt_template_version || "2" === data.wt_template_version)) {
								$('.customizer_template_warning_div').show();
								$('.customizer_template_warning_div').html('<div class="notice notice-error customizer_template_warning_div_error"><p class="customizer_template_warning_div_error_msg">' + wf_woocommerce_packing_list_customizer.labels.pro_template_wrn + '</p></div>');
							} else if (true === data.is_pro_customizer && (1 === data.wt_template_version || "1" === data.wt_template_version)) {
								$('.customizer_template_warning_div').show();
								$('.customizer_template_warning_div').html('<div class="notice notice-error customizer_template_warning_div_error"><p class="customizer_template_warning_div_error_msg">' + wf_woocommerce_packing_list_customizer.labels.basic_template_wrn + '</p></div>');
							} else if (0 === data.wt_template_version || "0" === data.wt_template_version) {
								$('.customizer_template_warning_div').hide();
								$('.customizer_template_warning_div').html('');
							}
						}

						if (pklist_customize.isCodeView() && pklist_customize.enable_code_view) {
							wf_code_editor.refresh();
							pklist_customize.pendingHtmlRead = 1;
						} else {
							pklist_customize.readHTML();
						}

						/* Reset fields to default values when loading a new template */
						if (0 === pklist_customize.template_id || "0" === pklist_customize.template_id) {
							pklist_customize.resetFieldsToDefault();
						}

						// move to the customizer property when hovering the respective element
						$(".wfte_template_element").hover(function () {
							var req_elem = $(this);
							var req_id = req_elem.attr('data-hover-id');
							req_elem.addClass('template_element_hover');
							if (0 !== $('.wf_side_panel[data-type="' + req_id + '"]').length) {
								$('.wf_side_panel[data-type="' + req_id + '"]').css('border', '1px dotted #3157A6');
							}
						}, function () {
							$(this).removeClass('template_element_hover');
							var req_id = $(this).attr('data-hover-id');
							if (0 !== $('.wf_side_panel[data-type="' + req_id + '"]').length) {
								$('.wf_side_panel[data-type="' + req_id + '"]').css('border', 'transparent');
							}
						});

						$(".wfte_template_element").on('click', function () {
							$(".wf_side_panel_content").css({ "display": "none" });
							$(this).addClass('template_element_hover');
							var req_id = $(this).attr('data-hover-id');
							var container = $('.wf_customize_sidebar');
							var scrollTo = $('.wf_side_panel[data-type="' + req_id + '"]');
							var req_elem = $(this);
							if (0 !== scrollTo.length) {
								container.animate({
									scrollTop: scrollTo.offset().top - req_elem.offset().top + container.scrollTop()
								}, 200);
								scrollTo.children('.wf_side_panel_content').css({ "display": "block", "opacity": "1" });
							}
						});
					} else {
						if (pklist_customize.enable_code_view) {
							wf_code_editor.getDoc().setValue('');
							wf_code_editor.refresh();
						} else {
							$('#wfte_code').val('');
						}
						pklist_customize.disablePanel($('.wf_side_panel'));
						wf_notify_msg.error(data.msg);
					}

					pklist_customize.render_page_properties_from_main_div();
				},
				error: function () {
					if (pklist_customize.enable_code_view) {
						wf_code_editor.getDoc().setValue('');
						wf_code_editor.refresh();
					} else {
						$('#wfte_code').val('');
					}
					pklist_customize.disablePanel($('.wf_side_panel'));
					pklist_customize.removeLoader();
					wf_notify_msg.error(wf_woocommerce_packing_list_customizer.labels.error);
				}
			});
		},
		setLoader: function () {
			var h = $('.wf-tab-content[data-id="wf_woocommerce_packing_list-customize"]').height();
			$('.wf_cst_loader').css({ 'display': 'block', 'height': h });
			/* save button at popup */
			$('.wf_pklist_save_theme_sub').hide();
			$('.wf_pklist_save_theme_sub_loading').show();
		},
		removeLoader: function () {
			$('.wf_cst_loader').hide();
			/* save button at popup */
			$('.wf_pklist_save_theme_sub').show();
			$('.wf_pklist_save_theme_sub_loading').hide();
		},
		disablePanel: function (elm) {
			elm.css({ 'opacity': '.5', 'cursor': 'not-allowed' }).attr('data-disabled', 1);
			elm.find('.wf_side_panel_hd').css({ 'cursor': 'not-allowed' }).trigger('click');
			elm.find('.wf_side_panel_toggle .wf_slide_switch').css({ 'visibility': 'hidden' }).prop('disabled', true);
			elm.find('.wf_side_panel_toggle .wf_slider').css({ 'cursor': 'not-allowed' });
		},

		proDisablePanel: function (elm) {
			elm.addClass('wt_pro_customizer_element');
			elm.attr('data-disabled', 1);
			elm.find('.wf_side_panel_toggle .wf_slide_switch').css({ 'visibility': 'hidden' }).prop('disabled', true);
			elm.find('.wf_side_panel_toggle .wf_slider').css({ 'cursor': 'not-allowed' });
			elm.find('.wf_side_panel_content').hide();
		},

		enablePanel: function (elm) {
			elm.css({ 'opacity': '1', 'cursor': 'pointer' }).attr('data-disabled', 0);
			elm.find('.wf_side_panel_hd').css({ 'cursor': 'pointer' });
			elm.find('.wf_side_panel_toggle .wf_slide_switch').css({ 'visibility': 'visible' }).prop('disabled', false);
			elm.find('.wf_side_panel_toggle .wf_slider').css({ 'cursor': 'pointer' });
		},
		getRotationDegrees: function (obj) {
			var matrix = obj.css("-webkit-transform") ||
				obj.css("-moz-transform") ||
				obj.css("-ms-transform") ||
				obj.css("-o-transform") ||
				obj.css("transform");
			if ("none" !== matrix) {
				var values = matrix.split('(')[1].split(')')[0].split(',');
				var a = values[0];
				var b = values[1];
				var angle = Math.round(Math.atan2(b, a) * (180 / Math.PI));
			} else { var angle = 0; }
			return angle;
		},
		readHTML: function () {
			var qrocode_compatible = $("#template_qr_compatible_err_val").val();
			/* unbinding events */
			this.deRegPanelEvents();

			/* checking fields available */
			$('.wf_side_panel').each(function () {
				var type = $(this).attr('data-type');
				if ("barcode" === type) {
					$(this).children().find('select').children('option[value="img_qrcode"]').prop('disabled', false);
					if (("1" === qrocode_compatible || 1 === qrocode_compatible || "3" === qrocode_compatible || 3 === qrocode_compatible)) {
						$(this).children().find('select').children('option[value="img_barcode"]').prop('selected', true);
						$(this).children().find('select').children('option[value="img_qrcode"]').prop('disabled', true);
					}
					var tgt_elm = $('.wf_customize_container').find('.wfte_' + type);
					if (tgt_elm.length > 0) {
						if ($('.wfte_' + type).hasClass('wfte_hidden')) {
							$('.wfte_' + type).children('.wfte_img_qrcode').addClass('wfte_hidden');
							$('.wfte_' + type).children('.wfte_img_barcode').removeClass('wfte_hidden');
						}
					}
				}


				if (0 === $('.wf_customize_container').find('.wfte_' + type).length) {
					if (0 === $(this).attr('data-non-customize') || "0" === $(this).attr('data-non-customize')) {
						if (type.indexOf("_pro_element") >= 0) {
							pklist_customize.proDisablePanel($(this));
						} else {
							pklist_customize.disablePanel($(this));
						}

					}
				} else {
					pklist_customize.enablePanel($(this));
				}
			});

			/* checking elements status */
			$('.wf_side_panel_toggle .wf_slide_switch').each(function () {
				pklist_customize.toggleElement($(this), 1);
			});

			$('.wf_side_panel .wf_cst_switcher').each(function () {
				pklist_customize.switchElement($(this), 1);
			});

			$('.wf_side_panel .wf_cst_toggler').each(function () {
				pklist_customize.toggleSubElement($(this), 1);
			});

			/* reading elements properties */
			var units = ['px', 'pt', 'em', 'rem', '', 'pc', 'cm', 'mm', 'in', '%', 'ex', 'vw', 'vh', 'vmax', 'vmin'];
			$('.wf_side_panel .wf_cst_change, .wf_side_panel .wf_cst_keyup, .wf_side_panel .wf_cst_keypress, .wf_side_panel .wf_cst_click').each(function () {
				var elm = $(this);
				var tgt_elm_class = elm.attr('data-elm');
				var prop = elm.attr('data-prop');
				if (typeof tgt_elm_class != 'undefined' && typeof prop != 'undefined') {
					/* multiple prop,elm  */
					var tgt_elm_class_ar = pklist_customize.getAttributItems(tgt_elm_class);
					tgt_elm_class = tgt_elm_class_ar[0].trim();
					var prop_ar = pklist_customize.getAttributItems(prop);
					prop = prop_ar[0].trim();

					var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
					var vl = '';
					if (tgt_elm.length > 0) {
						$(this).closest('.wf_side_panel_frmgrp').show();
						if ("html" === prop) {
							if (tgt_elm.length > 0) {
								vl = tgt_elm.html();
								vl = pklist_customize.br2nl(vl);
								elm.val(vl);
							}
						}
						else if ("rotate" === prop) {
							vl = pklist_customize.getRotationDegrees(tgt_elm);
							elm.val(vl);
							if (elm.siblings('.addonblock').length > 0) /* unit block exists */ {
								elm.attr('data-unit', 'deg');
								elm.siblings('.addonblock').find('input[type="text"]').val('deg');
							}
						}
						else {
							var prop_ar = prop.split('-');
							if ("attr" === prop_ar[0]) /*  attribute not CSS */ {
								prop = prop.substr(5);
								vl = tgt_elm.attr(prop);
								 if (typeof vl !== 'undefined') {
									elm.val(vl);
								} else {
									/* Use default value if attribute is not set */
									var default_val = elm.attr('data-default');
									if (typeof default_val !== 'undefined') {
										elm.val(default_val);
									}
								}
							} else {
								vl = tgt_elm.css(prop);
								if ("opacity" === prop) {
									vl = parseFloat(vl);
								}
								if (elm.hasClass('wf-color-field')) /* color field */ {
									elm.val(vl).attr('data-default', vl).iris('color', vl);
								} else {
									if (elm.siblings('.addonblock').length > 0) /* unit block exists */ {
										if ("inherit" !== vl)  /* avoid conflict with `in` unit */ {
											for (var s = 0; s < units.length; s++) {
												if (vl.search(units[s]) != -1) //unit found
												{
													elm.attr('data-unit', units[s]);
													elm.siblings('.addonblock').find('input[type="text"]').val(units[s]);
													break;
												}
											}
											var unt = elm.attr('data-unit');
											vl = vl.replace(unt, '');
											elm.val(vl);
										} else {
											elm.val(vl);
										}
									} else {
										elm.val(vl);
									}
								}
							}
						}
					} else {
						$(this).closest('.wf_side_panel_frmgrp').hide();
					}
				}
			});

			/* show missing text  */
			this.missingWarning();

			/* binding events */
			this.regPanelEvents();

			/* apply general settings restrictions */
			this.applyGeneralSettingsRestrictions();

			pklist_customize.pendingHtmlRead = 0;
			if (pklist_customize.open_first_panel) {
				jQuery('.wf_side_panel .wf_side_panel_hd:eq(0)').trigger('click');
				pklist_customize.open_first_panel = false; /* only first time */
			}
		},
		resetFieldsToDefault: function () {
			/* Reset Due Date Period select to default value (30_days) */
			var dueDateSelect = $('.wf_due_date_period_sele');
			if (dueDateSelect.length > 0) {
				dueDateSelect.val('30_days');
				/* Update the template element attribute */
				var tgt_elm = $('.wf_customize_container').find('.wfte_due_date');
				if (tgt_elm.length > 0) {
					tgt_elm.attr('data-due-date-period', '30_days');
				}
			}
		},
		applyGeneralSettingsRestrictions: function () {
			var generalSettings = wf_woocommerce_packing_list_customizer.general_settings;
			
			if (typeof generalSettings === 'undefined' || !generalSettings) {
				return;
			}

			if (typeof generalSettings.include_customer_note !== 'undefined' && generalSettings.include_customer_note === 'No') {
				this.disableSidePanelByType('customer_note', 'customer_note');
			}

			if (typeof generalSettings.include_footer !== 'undefined' && generalSettings.include_footer === 'No') {
				this.disableSidePanelByType('footer', 'footer');
			}

			if (typeof generalSettings.include_image !== 'undefined' && generalSettings.include_image === 'No') {
				this.disableProductTableCheckbox('product_table_head_image', 'image');
			}

			if (typeof generalSettings.include_sku !== 'undefined' && generalSettings.include_sku === 'No') {
				this.disableProductTableCheckbox('product_table_head_sku', 'sku');
			}
		},
		disableSidePanelByType: function (panelType, settingName) {
			var panel = $('.wf_side_panel[data-type="' + panelType + '"]');
			if (panel.length > 0) {
				panel.attr('data-general-toggled-off', 1);
				
				var toggle = panel.find('.wf_side_panel_toggle .wf_slide_switch');
				toggle.prop('checked', false);
				
				var tgt_elm = $('.wf_customize_container').find('.wfte_' + panelType);
				if (tgt_elm.length > 0) {
					tgt_elm.addClass('wfte_hidden');
				}
				
				var code_view_dom = this.getCodeViewHtmlDom();
				var code_tgt_elm = code_view_dom.find('.wfte_' + panelType);
				if (code_tgt_elm.length > 0) {
					code_tgt_elm.addClass('wfte_hidden');
					this.updateCodeViewHtml(code_view_dom);
				}
			}
		},
		disableProductTableCheckbox: function (checkboxElm, columnType) {
			var checkbox = $('.wf_side_panel .wf_cst_toggler[data-elm="' + checkboxElm + '"]');
			if (checkbox.length > 0) {
				checkbox.prop('checked', false);
				
				var tgt_elm = $('.wf_customize_container').find('.wfte_' + checkboxElm);
				if (tgt_elm.length > 0) {
					var nodeName = tgt_elm.prop('nodeName').toLowerCase();
					if ("th" === nodeName || "td" === nodeName) {
						var ind = tgt_elm.index() + 1;
						tgt_elm.parents('table').find('td:nth-child(' + ind + '),th:nth-child(' + ind + ')').addClass('wfte_hidden');
					} else {
						tgt_elm.addClass('wfte_hidden');
					}
				}
				
				var code_view_dom = this.getCodeViewHtmlDom();
				var code_tgt_elm = code_view_dom.find('.wfte_' + checkboxElm);
				if (code_tgt_elm.length > 0) {
					var col_type = code_tgt_elm.attr('col-type');
					if (col_type && "-" !== col_type.charAt(0)) {
						col_type = '-' + col_type;
						code_tgt_elm.attr('col-type', col_type);
					}
					this.updateCodeViewHtml(code_view_dom);
				}
			}
		},
		missingWarning: function () {
			var missing_warn_elm = ['company_logo_img', 'image_signature', 'from_address_val', 'company_name'];
			var missing_warn_txt = [
				wf_woocommerce_packing_list_customizer.labels.logo_missing,
				wf_woocommerce_packing_list_customizer.labels.signature_missing,
				wf_woocommerce_packing_list_customizer.labels.from_address_missing,
				wf_woocommerce_packing_list_customizer.labels.company_missing
			];
			var missing_warn_url = [
				wf_woocommerce_packing_list_customizer.urls.general_settings,
				wf_woocommerce_packing_list_customizer.urls.module_general_settings,
				wf_woocommerce_packing_list_customizer.urls.general_settings,
				wf_woocommerce_packing_list_customizer.urls.general_settings,
			];
			for (var i = 0; i < missing_warn_elm.length; i++) {
				var elm = $('.wf_customize_container').find('.wfte_' + missing_warn_elm[i]);
				if (elm.length > 0) {
					if ("img" === elm.prop('nodeName').toLowerCase()) {
						if ("" === elm.attr('src')) {
							elm.replaceWith('<a href="' + missing_warn_url[i] + '" class="wf_missing_wrn wfte_' + missing_warn_elm[i] + '">' + missing_warn_txt[i] + '</a>');
						}
					} else {
						if ("" === elm.html()) {
							elm.html('<a href="' + missing_warn_url[i] + '" class="wf_missing_wrn">' + missing_warn_txt[i] + '</a>');
						}
					}
				}
			}
		},
		deRegPanelEvents: function () {
			$('.wf_side_panel_toggle .wf_slide_switch, .wf_side_panel .wf_cst_click').unbind('click');
			$('.wf_side_panel_toggle .wf_slide_switch').unbind('change');
			$('.wf_side_panel .wf_cst_change, .wf_side_panel .wf_cst_switcher, .wf_inptgrp .addonblock input[type="text"]').unbind('change');
			$('.wf_side_panel .wf_cst_keyup').unbind('keyup');
			$('.wf_side_panel .wf_cst_keypress').unbind('keypress');
		},
		regPanelEvents: function () {
			/* Use "change" so :checked matches the new state (click can run before the checkbox toggles). */
			$('.wf_side_panel_toggle .wf_slide_switch').on('change', function () {
				pklist_customize.toggleElement($(this), 0);
			});

			$('.wf_side_panel .wf_cst_change').on('change', function () {
				pklist_customize.applyProp($(this));
			});
			$('.wf_side_panel .wf_cst_keyup').on('keyup', function () {
				pklist_customize.applyProp($(this));
			});
			$('.wf_side_panel .wf_cst_keypress').on('keypress', function () {
				pklist_customize.applyProp($(this));
			});

			$('.wf_side_panel .wf_cst_click').on('click', function () {
				pklist_customize.applyProp($(this));
			});

			$('.wf_side_panel .wf_cst_switcher').on('change', function () {
				pklist_customize.switchElement($(this), 0);
			});

			$('.wf_side_panel .wf_cst_toggler').on('change', function () {
				pklist_customize.toggleSubElement($(this), 0);
			});

			$('.wf_inptgrp .addonblock input[type="text"]').on('change', function () {
				var inpt = $(this).parent('.addonblock').siblings('input[type="text"]');
				inpt.attr('data-unit', $(this).val());
				pklist_customize.applyProp(inpt);
			});
		},
		getBoxTogglingELm: function (sub_tgt_elm_class) {
			/* for pdf compatibility images are inside a container */
			var elm_clss_arr = sub_tgt_elm_class.split('_');
			return elm_clss_arr[elm_clss_arr.length - 1] === 'box' ? sub_tgt_elm_class.slice(0, -4) : sub_tgt_elm_class;
		},
		switchElement: function (elm, rev) {
			var prnt = elm.parents('.wf_side_panel');
			var tgt_elm_class = prnt.attr('data-type');
			if (typeof tgt_elm_class != 'undefined') {
				var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
				if (1 === rev || "1" === rev) {
					var selected_val = '';
					elm.find('option').each(function () {
						var sub_tgt_elm_class = $(this).attr('value');
						var sub_tgt_elm = tgt_elm.find('.wfte_' + sub_tgt_elm_class);

						var toggling_elm = pklist_customize.getBoxTogglingELm(sub_tgt_elm_class);

						/* hiding customizing options of inactive elements */
						$('input[data-elm="' + toggling_elm + '"]').parents('.wf_side_panel_frmgrp').hide();
						if ("" === selected_val && sub_tgt_elm.length > 0 && sub_tgt_elm.is(':visible')) {
							selected_val = sub_tgt_elm_class;

							/* showing customizing options of active element */
							$('input[data-elm="' + toggling_elm + '"]').parents('.wf_side_panel_frmgrp').show();

							/* hidden element width height issue */
							setTimeout(function () {
								$('input[data-elm="' + toggling_elm + '"]').each(function () {
									if ("width" === $(this).attr('data-prop')) {
										$(this).val($('.wfte_' + toggling_elm).width());
									}
									else if ("height" === $(this).attr('data-prop')) {
										$(this).val($('.wfte_' + toggling_elm).height());
									}
								});
							}, 500)

						}
					});
					elm.val(selected_val);
				} else {
					/* code view */
					var code_view_dom = this.getCodeViewHtmlDom();
					var code_tgt_elm = code_view_dom.find('.wfte_' + tgt_elm_class);

					elm.find('option').each(function () {
						var sub_tgt_elm_class = $(this).attr('value');
						tgt_elm.find('.wfte_' + sub_tgt_elm_class).addClass(pklist_customize.to_hide_css);
						code_tgt_elm.find('.wfte_' + sub_tgt_elm_class).addClass(pklist_customize.to_hide_css);

						/* hiding customizing options of inactive elements */
						var toggling_elm = pklist_customize.getBoxTogglingELm(sub_tgt_elm_class);
						$('input[data-elm="' + toggling_elm + '"]').parents('.wf_side_panel_frmgrp').hide();
					});
					var cr_active_sub_elm_clss = elm.val();
					tgt_elm.find('.wfte_' + cr_active_sub_elm_clss).removeClass(pklist_customize.to_hide_css);
					code_tgt_elm.find('.wfte_' + cr_active_sub_elm_clss).removeClass(pklist_customize.to_hide_css);

					this.updateCodeViewHtml(code_view_dom);
					this.scheduleAutosaveAfterVisibilityToggle();

					/* showing customizing options of active element */
					var cr_toggling_elm = pklist_customize.getBoxTogglingELm(cr_active_sub_elm_clss);
					$('input[data-elm="' + cr_toggling_elm + '"]').parents('.wf_side_panel_frmgrp').show();

					/* hidden element width height issue */
					var elm_clss_arr = cr_active_sub_elm_clss.split('_');
					/* for pdf compatibility images are inside a container */
					var toggling_elm = elm_clss_arr[elm_clss_arr.length - 1] === 'box' ? cr_active_sub_elm_clss.slice(0, -4) : cr_active_sub_elm_clss;
					$('input[data-elm="' + toggling_elm + '"]').each(function () {
						if ("width" === $(this).attr('data-prop')) {
							$(this).val($('.wfte_' + toggling_elm).width());
						}
						else if ("height" === $(this).attr('data-prop')) {
							$(this).val($('.wfte_' + toggling_elm).height());
						}
					});
				}
			}
		},
		tableToggleLastColCss: function (tgt_elm) {
			var nodeName = tgt_elm.prop('nodeName').toLowerCase();
			if ("th" === nodeName) {
				tgt_elm.parents('tr').find('th').removeClass('wfte_right_column');
				tgt_elm.parents('tr').find('th:visible:last').addClass('wfte_right_column');
			}
		},
		toggleSubElement: function (elm, rev) {
			var tgt_elm_class = elm.attr('data-elm');
			if (typeof tgt_elm_class != 'undefined') {
				var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
				if (tgt_elm.length > 0) {
					$('[data-elm="' + tgt_elm_class + '"]').parents('.wf_side_panel_frmgrp').show();
					if (1 === rev || "1" === rev) {
						if (tgt_elm.is(':visible')) {
							elm.prop('checked', true);
						} else {
							elm.prop('checked', false);
						}
					} else {
						/* code view */
						var code_view_dom = this.getCodeViewHtmlDom();
						var code_tgt_elm = code_view_dom.find('.wfte_' + tgt_elm_class);
						if (elm.is(':checked')) {
							var nodeName = tgt_elm.prop('nodeName').toLowerCase();
							if ("th" === nodeName || "td" === nodeName) {
								var col_type = code_tgt_elm.attr('col-type');
								if ("-" === col_type.charAt(0)) /* hidden */ {
									col_type = col_type.substring(1);
									code_tgt_elm.attr('col-type', col_type)
								}
								var ind = tgt_elm.index() + 1;
								tgt_elm.parents('table').find('td:nth-child(' + ind + '),th:nth-child(' + ind + ')').removeClass('wfte_hidden');
								this.tableToggleLastColCss(tgt_elm);
							} else {
								code_tgt_elm.removeClass(pklist_customize.to_hide_css);
								tgt_elm.removeClass(pklist_customize.to_hide_css);
							}
						} else {
							var nodeName = tgt_elm.prop('nodeName').toLowerCase();
							if ("th" === nodeName || "td" === nodeName) {
								var col_type = code_tgt_elm.attr('col-type');
								if ("-" !== col_type.charAt(0)) /* hidden */ {
									col_type = '-' + col_type;
									code_tgt_elm.attr('col-type', col_type)
								}
								var ind = tgt_elm.index() + 1;
								tgt_elm.parents('table').find('td:nth-child(' + ind + '),th:nth-child(' + ind + ')').addClass('wfte_hidden');
								this.tableToggleLastColCss(tgt_elm);
							} else {
								code_tgt_elm.addClass(pklist_customize.to_hide_css);
								tgt_elm.addClass(pklist_customize.to_hide_css);
							}
						}
						this.updateCodeViewHtml(code_view_dom);
						this.pushLinkedGeneralForDataTypeAttribute(elm.attr('data-elm'), elm.is(':checked'));
						this.scheduleAutosaveAfterVisibilityToggle();
					}
				} else {
					/* hiding customizing options of inactive elements */
					$('[data-elm="' + tgt_elm_class + '"]').parents('.wf_side_panel_frmgrp').hide();
				}
			}
		},
		equalizeClassAndAttribute: function (small_arr, big_arr_ln) {
			var out_arr = [small_arr[0]];
			for (var i = 0; i < big_arr_ln; i++) {
				if (i > 0) {
					/* if not exists, use previous val */
					var vl = (typeof small_arr[i] == 'undefined') ? out_arr[i - 1].trim() : small_arr[i].trim();
					out_arr[i] = vl;
				}
			}
			return out_arr;
		},
		getAttributItems: function (vl) {
			var array = vl.split("|");
			var filtered = array.filter(function (el) {
				return el !== null && el !== "" && el !== " ";
			});
			return filtered;
		},
		toggleElement: function (elm, rev) {
			var tgt_elm_class = elm.attr('data-type');
			if (typeof tgt_elm_class != 'undefined') {
				var tgt_elm_class_arr = this.getAttributItems(tgt_elm_class);
				if (1 === rev || "1" === rev) {
					tgt_elm_class = tgt_elm_class_arr[0].trim(); /* take first element  */
					var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
					if (tgt_elm.is(':visible')) {
						elm.prop('checked', true);
					} else {
						elm.prop('checked', false);
						if (tgt_elm_class.indexOf("_pro_element") >= 0) {
							var pro_elem = tgt_elm_class.split('_pro_element');
							var tgt_elm_pro = $('.wf_customize_container').find('.wfte_' + pro_elem[0]);
							if (tgt_elm_pro.is(':visible')) {
								elm.prop('checked', true);
							}
						}
					}
				} else {
					/* code view */
					var code_view_dom = this.getCodeViewHtmlDom();

					for (var e = 0; e < tgt_elm_class_arr.length; e++) {
						var tgt_elm_class = tgt_elm_class_arr[e];
						var code_tgt_elm = code_view_dom.find('.wfte_' + tgt_elm_class);
						var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
						if (elm.is(':checked')) {
							tgt_elm.removeClass(pklist_customize.to_hide_css);
							code_tgt_elm.removeClass(pklist_customize.to_hide_css);
						} else {
							tgt_elm.addClass(pklist_customize.to_hide_css);
							code_tgt_elm.addClass(pklist_customize.to_hide_css);
						}
						this.toggleChildElmPanel(tgt_elm);
					}
					this.updateCodeViewHtml(code_view_dom);
					this.pushLinkedGeneralForDataTypeAttribute(elm.attr('data-type'), elm.is(':checked'));
					this.scheduleAutosaveAfterVisibilityToggle();
				}
			}
		},
		toggleChildElmPanel: function (main_elm) {
			$('.wf_side_panel_toggle .wf_slide_switch').each(function () {
				var elm = $(this);
				var tgt_elm_class = elm.attr('data-type');
				if (typeof tgt_elm_class != 'undefined') {
					var sub_tgt_elm_class_arr = pklist_customize.getAttributItems(tgt_elm_class);
					for (var r = 0; r < sub_tgt_elm_class_arr.length; r++) {
						var tgt_elm_class = sub_tgt_elm_class_arr[r].trim();
						var tgt_elm = main_elm.find('.wfte_' + tgt_elm_class);
						if (tgt_elm.length > 0) /* child of current main elm */ {
							if (tgt_elm.is(':visible')) {
								elm.prop('checked', true);
								pklist_customize.enablePanel(elm.parents('.wf_side_panel'));
							} else {
								if (main_elm.is(':visible')) {
									elm.prop('checked', false);
									pklist_customize.enablePanel(elm.parents('.wf_side_panel'));
								} else {
									elm.prop('checked', false);
									pklist_customize.disablePanel(elm.parents('.wf_side_panel'));
								}
							}
						}
					}
				}
			});
		},
		applyProp: function (elm) {
			var tgt_elm_class = elm.attr('data-elm');
			var prop = elm.attr('data-prop');
			if (typeof tgt_elm_class != 'undefined' && typeof prop != 'undefined') {
				/* code view */
				var code_view_dom = this.getCodeViewHtmlDom();
				var tgt_elm_class_arr = this.getAttributItems(tgt_elm_class);
				var prop_data_arr = this.getAttributItems(prop);
				if (tgt_elm_class_arr.length > prop_data_arr.length) {
					prop_data_arr = this.equalizeClassAndAttribute(prop_data_arr, tgt_elm_class_arr.length);
				} else {
					tgt_elm_class_arr = this.equalizeClassAndAttribute(tgt_elm_class_arr, prop_data_arr.length);
				}

				for (var rr = 0; rr < tgt_elm_class_arr.length; rr++) {
					var tgt_elm_class = tgt_elm_class_arr[rr].trim();
					var prop = (typeof prop_data_arr[rr] == 'undefined') ? prop_data_arr[0].trim() : prop_data_arr[rr].trim();
					var tgt_elm = $('.wf_customize_container').find('.wfte_' + tgt_elm_class);
					var code_tgt_elm = code_view_dom.find('.wfte_' + tgt_elm_class);
					if ("html" === prop) {
						var nl2brTxt = this.nl2br(elm.val().replace(/<\/?[^>]+(>|$)/g, ""));
						tgt_elm.html(nl2brTxt);
						code_tgt_elm.html('__[' + nl2brTxt + ']__');
						/* add code view data */
					} else {
						var prop_ar = prop.split('-');
						if ("attr" === prop_ar[0]) /*  attribute not CSS */ {
							prop = prop.substr(5);
							/*var prop_val=elm.val()+elm.attr('data-unit'); */
							var prop_val = elm.val().replace(/<\/?[^>]+(>|$)/g, "");

							if ("data-img-width" === prop || "data-img-height" === prop) /* product image size: store px value + live-resize preview image */ {
								if ("" !== prop_val.toString().trim()) {
									prop_val = parseFloat(prop_val) + 'px';
								}
								tgt_elm.attr(prop, prop_val);
								code_tgt_elm.attr(prop, prop_val);
								if ("th" === tgt_elm.prop('nodeName').toLowerCase()) {
									var img_col_ind = tgt_elm.index() + 1;
									var img_css_prop = ("data-img-width" === prop) ? 'width' : 'height';
									tgt_elm.parents('table').find('td:nth-child(' + img_col_ind + ') img.wfte_product_image_thumb').css(img_css_prop, prop_val);
								}
							} else {
								tgt_elm.attr(prop, prop_val);
								code_tgt_elm.attr(prop, prop_val);

								/*preview elm */
								var prv_elm_class = elm.attr('data-preview_elm');
								if (typeof prv_elm_class != 'undefined') {
									if ("" !== prv_elm_class) {
										$('.wfte_' + prv_elm_class).html(elm.val() + elm.attr('data-unit'));
									}
								} else {
									clearTimeout(pklist_customize.updt_frm_cde_vew_tmr);
									this.updt_frm_cde_vew_tmr = setTimeout(function () {
										pklist_customize.updateFromCodeView();
									}, 1000);
								}
							}
						} else {
							if ("" !== elm.val().trim()) {
								var prop_val = elm.val().replace(/<\/?[^>]+(>|$)/g, "") + elm.attr('data-unit');
							} else {
								var prop_val = '';
							}
							if ("text-align" === prop) /* for rtl support we use classes for text align */ {
								var new_class = this.text_left_css;
								var old_class = this.text_right_css + ' ' + this.text_center_css;
								if ("right" === prop_val || "end" === prop_val) {
									new_class = this.text_right_css;
									old_class = this.text_left_css + ' ' + this.text_center_css;
								} else if ("center" === prop_val) {
									new_class = this.text_center_css;
									old_class = this.text_left_css + ' ' + this.text_right_css;
								}
								var nodeName = tgt_elm.prop('nodeName').toLowerCase();
								if ("th" === nodeName) /* if node is table head then apply the prop to corresponding column */ {
									var ind = tgt_elm.index() + 1;
									tgt_elm.parents('table').find('td:nth-child(' + ind + '),th:nth-child(' + ind + ')').addClass(new_class).removeClass(old_class);
								} else {
									tgt_elm.addClass(new_class).removeClass(old_class);
								}
								code_tgt_elm.addClass(new_class).removeClass(old_class);
							}
							else if ("rotate" === prop) {
								var style_vl = tgt_elm.attr('style');
								var transform_vl = '';
								if ("" === prop_val) {
									prop_val = '0deg';
								}
								prop_val = 'rotate(' + prop_val + ')';
								tgt_elm.css('transform', prop_val);
								code_tgt_elm.css('transform', prop_val);
							}
							else {
								tgt_elm.css(prop, prop_val);
								code_tgt_elm.css(prop, prop_val);
							}

						}
					}
				}
				this.updateCodeViewHtml(code_view_dom);
			}
		},
		updateCodeViewHtml: function (htmlDom) {
			var txt = htmlDom.html();
			var mapObj = wf_woocommerce_packing_list_customizer.img_url_placeholders;
			$.each(mapObj, function (key, val) {
				txt = txt.replace(val, key);
			});
			$('#wfte_code').val(txt);
			if (pklist_customize.enable_code_view) {
				wf_code_editor.getDoc().setValue(txt);
			}
		},
		getCodeViewHtml: function () {
			if (pklist_customize.enable_code_view) {
				return wf_code_editor.getDoc().getValue();
			} else {
				return $('#wfte_code').val();
			}
		},
		getCodeViewHtmlDom: function () {
			var txt = this.getCodeViewHtml();
			var mapObj = wf_woocommerce_packing_list_customizer.img_url_placeholders;
			$.each(mapObj, function (key, val) {
				txt = txt.replace(key, val);
			});
			return $('<div />').html(txt);
		},
		br2nl: function (str) {
			str = str.replace(/<br>/g, "\r");
			return str.replace(/<br \/>/g, "\r");
		},
		nl2br: function (str) {
			var breakTag = '<br />';
			return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
		},
		strpos: function (haystack, needle, offset) {
			var i = (haystack + '').indexOf(needle, (offset || 0));
			return i === -1 ? false : i;
		},
		/**
		 * Keep Code view / stored template HTML in sync when premium feature toggles change (packing slip, delivery note, dispatch label).
		 */
		syncPremiumToggleToCodeView: function ($checkbox) {
			var targetElements = $checkbox.attr('data-target-element');
			if (typeof targetElements === 'undefined') {
				return;
			}
			var elementsArr = this.getAttributItems(targetElements);
			var isChecked = $checkbox.is(':checked');
			var code_view_dom = this.getCodeViewHtmlDom();
			var e, elementClass, code_tgt_elm, tgt_elm;
			for (e = 0; e < elementsArr.length; e++) {
				elementClass = elementsArr[e].trim();
				code_tgt_elm = code_view_dom.find('.wfte_' + elementClass);
				tgt_elm = $('.wf_customize_container').find('.wfte_' + elementClass);
				if (isChecked) {
					tgt_elm.removeClass(this.to_hide_css);
					code_tgt_elm.removeClass(this.to_hide_css);
				} else {
					tgt_elm.addClass(this.to_hide_css);
					code_tgt_elm.addClass(this.to_hide_css);
				}
				this.toggleChildElmPanel(tgt_elm);
			}
			this.updateCodeViewHtml(code_view_dom);
		},
		/** When customizer show/hide matches a General tab option, persist it and refresh radios on the General tab. */
		pushLinkedGeneralForDataTypeAttribute: function (attrVal, isVisible) {
			if (!attrVal || typeof attrVal === 'undefined') {
				return;
			}
			var docTypes = { packinglist: 1, deliverynote: 1, dispatchlabel: 1 };
			var tt = wf_woocommerce_packing_list_customizer.template_type;
			if (!docTypes[tt]) {
				return;
			}
			var map = wf_woocommerce_packing_list_customizer.general_option_sync_map || {};
			var keys = this.getAttributItems(attrVal);
			var i, lk;
			for (i = 0; i < keys.length; i++) {
				lk = keys[i].trim();
				if (map[lk]) {
					this.ajaxSyncLinkedGeneralOption(lk, isVisible);
					break;
				}
			}
		},
		ajaxSyncLinkedGeneralOption: function (linkKey, isVisible) {
			var map = wf_woocommerce_packing_list_customizer.general_option_sync_map || {};
			if (!map[linkKey]) {
				return;
			}
			var val = isVisible ? 'Yes' : 'No';
			$.ajax({
				type: 'POST',
				url: wf_woocommerce_packing_list_customizer.ajax_url,
				dataType: 'json',
				data: {
					action: 'wfpklist_customizer_ajax',
					customizer_action: 'sync_linked_general_option',
					_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
					template_type: wf_woocommerce_packing_list_customizer.template_type,
					link_key: linkKey,
					option_value: val
				},
				success: function (data) {
					if ((1 === data.status || '1' === data.status) && data.option_key && !data.skipped) {
						pklist_customize.updateGeneralTabRadioInputs(data.option_key, data.option_value);
					}
				}
			});
		},
		updateGeneralTabRadioInputs: function (optionKey, value) {
			if (!optionKey) {
				return;
			}
			var $yes = $('input[type="radio"][name="' + optionKey + '"][value="Yes"]');
			var $no = $('input[type="radio"][name="' + optionKey + '"][value="No"]');
			if ($yes.length && $no.length) {
				if ('Yes' === value) {
					$yes.prop('checked', true);
				} else {
					$no.prop('checked', true);
				}
			}
		},
		/** Debounced template save after show/hide toggles (sidebar switches + premium panel) for packing slip, delivery note, dispatch label */
		_visibilityToggleAutosaveTimer: null,
		scheduleAutosaveAfterVisibilityToggle: function () {
			var autosaveTypes = { packinglist: 1, deliverynote: 1, dispatchlabel: 1 };
			var tt = wf_woocommerce_packing_list_customizer.template_type;
			if (!autosaveTypes[tt]) {
				return;
			}
			if (!pklist_customize.template_id || 0 === pklist_customize.template_id || '0' === pklist_customize.template_id) {
				return;
			}
			clearTimeout(pklist_customize._visibilityToggleAutosaveTimer);
			pklist_customize._visibilityToggleAutosaveTimer = setTimeout(function () {
				/* skipLoader: avoid full-screen overlay on each toggle; show standard success message from server */
				pklist_customize.saveTheme(false, false, true);
			}, 600);
		},
		render_page_properties_from_main_div: function () {
			// temporary div for applying the page attributes from adc_main div
			var adc_main_elm = $('.wf_customize_vis_container').find('.wfte_invoice_basic_main');
			if (adc_main_elm.length > 0) {
				var targetDiv = adc_main_elm[0];
				var computedStyle = window.getComputedStyle(targetDiv);
				var main_padding_l = computedStyle.paddingLeft;
				var main_padding_r = computedStyle.paddingRight;
				var main_padding_t = computedStyle.paddingTop;
				var main_padding_b = computedStyle.paddingBottom;
				var main_margin_l = computedStyle.marginBottom;
				var main_margin_r = computedStyle.marginRight;
				var main_margin_t = computedStyle.marginTop;
				var main_margin_b = computedStyle.marginBottom;
				var main_bg_color = computedStyle.backgroundColor;
				var main_border_style = computedStyle.borderStyle;
				var main_border_width = computedStyle.borderWidth;
				var main_border_color = computedStyle.borderColor;
				var main_border_radius = computedStyle.borderRadius;

				$('.wf_customize_vis_container').css({
					'padding-left': main_padding_l,
					'padding-right': main_padding_r,
					'padding-top': main_padding_t,
					'padding-bottom': main_padding_b,
					'margin-left': main_margin_l,
					'margin-right': main_margin_r,
					'margin-top': main_margin_t,
					'margin-bottom': main_margin_b,
					'background-color': main_bg_color,
					'border-style': main_border_style,
					'border-width': main_border_width,
					'border-color': main_border_color,
					'border-radius': main_border_radius
				});
				adc_main_elm.css({
					'padding-left': '',
					'padding-right': '',
					'padding-top': '',
					'padding-bottom': '',
					'margin-left': '',
					'margin-right': '',
					'margin-top': '',
					'margin-bottom': '',
					'background-color': '',
					'background': '',
					'border-style': '',
					'border-width': '',
					'border-color': '',
					'border-radius': ''
				});
			}
		}
	};

	/* sample pdf generation */
	var preview_pdf =
	{
		onprg: false,
		Set: function () {
			$('.wf_sample_pdf_options_btn').on('click', function () {
				var elm = $('.wf_sample_pdf_options');
				if ($(this).hasClass('show_ord_preview')) {
					$(this).removeClass("show_ord_preview");
					$(".show_ord_preview_div").hide();
					elm.css({ 'border-color': '#fff' });
				} else {
					$(this).addClass("show_ord_preview");
					$(".show_ord_preview_div").show();
					elm.css({ 'border-color': '#efefef' });
				}
			});

			$('[name="wf_sample_pdf_order_no"]').keyup(function (e) {
				var order_id = $(this).val();
				$('.wf_sample_pdf_order_no_preview').html(order_id);
				if (13 === e.keyCode || "13" === e.keyCode) {
					$('.wf_download_sample_pdf').trigger('click');
				}
			});

			var sample_pdf_order_id = $('[name="wf_sample_pdf_order_no"]').val();
			$('.wf_sample_pdf_order_no_preview').html(sample_pdf_order_id);


			$('.wf_download_sample_pdf').on('click', function () {
				preview_pdf.generate_pdf($(this));
			});
		},
		generate_pdf: function (elm) {
			if (this.onprg) { return false; }
			var order_id = $('[name="wf_sample_pdf_order_no"]').val().trim();
			if ("" !== order_id) {
				var codeview_html = (typeof wf_code_editor != 'undefined' ? wf_code_editor.getDoc().getValue() : $('#wfte_code').val());
				var data =
				{
					action: 'wfpklist_customizer_ajax',
					customizer_action: 'prepare_sample_pdf',
					_wpnonce: wf_woocommerce_packing_list_customizer.nonces.main,
					template_type: wf_woocommerce_packing_list_customizer.template_type,
					codeview_html: codeview_html,
					order_id: order_id
				};

				var html_bck = elm.html();
				//$('.wf_sample_pdf_options').css({'height':'40px'});
				$('.wf_sample_pdf_options_btn').hide();
				var spinner = $('.wf_sample_pdf_options').find('.spinner');
				spinner.show().css({ 'visibility': 'visible' });
				elm.html(wf_woocommerce_packing_list_customizer.labels.generating + '...');
				this.onprg = true;

				jQuery.ajax({
					url: wf_pklist_params.ajaxurl,
					type: 'POST',
					data: data,
					dataType: 'json',
					success: function (data) {
						preview_pdf.onprg = false;
						$('.wf_sample_pdf_options_btn').show();
						$('.wf_download_sample_pdf').html(html_bck);
						spinner.hide().css({ 'visibility': 'hidden' });
						if (1 === data.status || "1" === data.status) {
							var preview_url = data.pdf_url.replace(/&amp;/g, '&');
							window.open(preview_url);
						} else {
							wf_notify_msg.error(data.msg);
						}
					},
					error: function () {
						preview_pdf.onprg = false;
						$('.wf_sample_pdf_options_btn').show();
						$('.wf_download_sample_pdf').html(html_bck);
						spinner.hide().css({ 'visibility': 'hidden' });
						wf_notify_msg.error(wf_woocommerce_packing_list_invoice.msgs.error);
					}
				});
			} else {
				wf_notify_msg.error(wf_woocommerce_packing_list_customizer.labels.enter_order_id);
				$('.wf_sample_pdf_options').css({ 'height': '120px', 'border-color': '#efefef' });
				$('[name="wf_sample_pdf_order_no"]').focus();
			}
		}
	}

	/* Premium feature toggles (packing slip, delivery note, dispatch label, etc.) */
	$(document).ready(function () {
		if ($('.wt_pklist_feature_toggle').length === 0) {
			return;
		}
		var hiddenClass = 'wfte_hidden';
		var $preview = $('#wt_packinglist_preview');
		if ($preview.length === 0) {
			$preview = $('.wf_customize_vis_container');
		}
		if ($preview.length === 0) {
			return;
		}

		function getAttributItems(vl) {
			var array = vl.split("|");
			return array.filter(function (el) {
				return el !== null && el !== "" && el !== " ";
			});
		}

		function toggleElement(elm, isInit) {
			var targetElements = elm.attr('data-target-element');
			if (typeof targetElements !== 'undefined') {
				var elementsArr = getAttributItems(targetElements);
				var isChecked = elm.is(':checked');

				for (var i = 0; i < elementsArr.length; i++) {
					var elementClass = elementsArr[i].trim();
					var $targetElm = $preview.find('.wfte_' + elementClass);
					var $targetTd = $preview.find('.' + elementClass);

					if (isChecked) {
						$targetElm.removeClass(hiddenClass);
						$targetTd.removeClass(hiddenClass);
						if (!isInit) {
							highlightElement($targetElm);
							highlightElement($targetTd);
						}
					} else {
						$targetElm.addClass(hiddenClass);
						$targetTd.addClass(hiddenClass);
					}
				}
			}
		}

		function highlightElement(elm) {
			elm.addClass('wfte_highlight');
			setTimeout(function () {
				elm.removeClass('wfte_highlight');
			}, 500);
		}

		$('.wt_pklist_feature_toggle').each(function () {
			toggleElement($(this), true);
		});

		$(document).on('change', '.wt_pklist_feature_toggle', function () {
			var $cb = $(this);
			toggleElement($cb, false);
			pklist_customize.syncPremiumToggleToCodeView($cb);
			pklist_customize.pushLinkedGeneralForDataTypeAttribute($cb.attr('data-target-element'), $cb.is(':checked'));
			$('.wf_side_panel_toggle .wf_slide_switch').each(function () {
				pklist_customize.toggleElement($(this), 1);
			});
			pklist_customize.scheduleAutosaveAfterVisibilityToggle();
		});
	});

})(jQuery);
if (wf_woocommerce_packing_list_customizer.enable_code_view) {
	var mixedMode = ({
		name: "htmlmixed",
	},
	{
		name: 'css'
	});

	var wf_code_editor = CodeMirror.fromTextArea(document.getElementById("wfte_code"), {
		lineNumbers: true,
		mode: mixedMode,
		lineWrapping: true
	});
}