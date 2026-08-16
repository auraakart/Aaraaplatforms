'use strict';

( function ( $ ) {
	const rnbInventoryManage = {
		init: function () {
			// custom class to show/hide fields
			this.customHiddenClass = 'wcfm_custom_hide';

			this.addNewTaxonomy = {
				class: {
					btn: 'wcfm_add_new_taxonomy',
					form: 'wcfm_add_new_taxonomy_form',
					toggle: 'wcfm_add_new_taxonomy_form_hide',
					add: 'wcfm_add_taxonomy_bt',
					taxField: 'wcfm_new_tax_ele',
					parentTaxField: 'wcfm_new_parent_taxt_ele',
				},
			};

			this.bindEvents();
		},

		bindEvents: function () {
			this.createToggleEventHandler( {
				selector: 'pricing_type',
				eventType: 'change',
				dependencyClass: 'pricing_type_dependency',
			} );

			this.createToggleEventHandler( {
				selector: 'hourly_pricing_type',
				eventType: 'change',
				dependencyClass: 'hourly_pricing_type_dependency',
			} );

			this.taxonomyFormEventHandler();
			this.taxonomyAddEventHandler();

			this.invetorySubmitHandler();

			document.addEventListener( "animationstart", this.createDateTimePicker, false ); // standard + firefox
			document.addEventListener( "MSAnimationStart", this.createDateTimePicker, false ); // IE
			document.addEventListener( "webkitAnimationStart", this.createDateTimePicker, false ); // Chrome + Safari
		},

		toggleFieldVisibility: function ( visibleElements, dependentElements, className ) {
			// hide all dependent fields
			for ( let element of dependentElements ) {
				element.classList.add( className );
			}

			// show only related fields
			for ( let element of visibleElements ) {
				element.classList.remove( className );
			}
		},

		createToggleEventHandler: function ( options ) {
			const triggerElement = document.getElementById( options.selector );

			// exit early if element not found
			if ( triggerElement === null ) return;

			// list of all dependent fields
			const dependentElements = document.getElementsByClassName( options.dependencyClass );

			// Attach a event listener on change to toggle fields
			triggerElement.addEventListener( options.eventType, ( event ) => {
				event.preventDefault();

				// list of all related fields
				const visibleElements = document.getElementsByClassName( "show_if_" + event.currentTarget.value );

				this.toggleFieldVisibility( visibleElements, dependentElements, this.customHiddenClass );
			} );

			// manually trigger change once
			triggerElement.dispatchEvent( new Event( options.eventType ), { bubble: true } );
		},

		taxonomyFormEventHandler: function () {
			const buttons = $( '.' + this.addNewTaxonomy.class.btn );

			buttons.each( ( index, button ) => {
				$( button ).on( 'click', ( event ) => {
					event.preventDefault();
					$( button ).parent()
						.find( '.' + this.addNewTaxonomy.class.form )
						.toggleClass( this.addNewTaxonomy.class.toggle );
				} );
			} );
		},

		taxonomyAddEventHandler: function () {
			const buttons = $( '.' + this.addNewTaxonomy.class.add );

			buttons.each( ( index, element ) => {
				const button = $( element );

				button.on( 'click', ( event ) => {
					event.preventDefault();

					const wrapper = button.parent();
					const new_term = wrapper.find( '.' + this.addNewTaxonomy.class.taxField ).val();

					if ( new_term ) {
						const taxonomy = button.data( 'taxonomy' );
						const parent_term = wrapper.find( '.' + this.addNewTaxonomy.class.parentTaxField ).val();

						const data = {
							action: 'wcfm_add_taxonomy_new_term',
							taxonomy: taxonomy,
							new_term: new_term,
							parent_term: parent_term,
							wcfm_ajax_nonce: window.wcfm_params.wcfm_ajax_nonce
						};

						wrapper.block( {
							message: null,
							overlayCSS: {
								background: '#fff',
								opacity: 0.6
							}
						} );

						$.ajax( {
							type: 'POST',
							url: window.wcfm_params.ajax_url,
							data: data,
							success: ( response ) => {
								if ( response ) {
									if ( response.error ) {
										// Error.
										window.alert( response.error );
									} else {
										// Success.
										$( '.rnb_inventory_taxonomy_checklist_' + taxonomy ).prepend( response );
										wrapper.find( '.' + this.addNewTaxonomy.class.taxField ).val( '' );
										wrapper.find( '.' + this.addNewTaxonomy.class.parentTaxField ).val( 0 );
									}
									wrapper.toggleClass( this.addNewTaxonomy.class.toggle );
									wrapper.unblock();
								}
							}
						} );
					}
				} );
			} );
		},

		invetorySubmitHandler: function () {
			const formSubmitBtns = [
				{ btn: '#wcfm_rnb_inventory_submit_button', status: 'publish' },
				{ btn: '#wcfm_rnb_inventory_draft_button', status: 'draft' },
			];

			for ( const formSubmitBtn of formSubmitBtns ) {
				$( formSubmitBtn.btn ).on( 'click', ( event ) => {
					event.preventDefault();

					this.submitForm( {
						status: formSubmitBtn.status
					} );
				} );
			}
		},

		createDateTimePicker: function ( event ) {
			if ( event.animationName === "nodeInserted" ) {
				const dateTimePickerFields = $( event.target ).find( '.wcfm_datetimepicker' );

				dateTimePickerFields.each( ( i, element ) => {
					const dateField = $( element ).removeClass('hasDatepicker');
					const dateFormat = dateField.data( 'date_format' );

					const options = {
						dateFormat: dateFormat || wcfm_datepicker_params.dateFormat,
						closeText: wcfm_datepicker_params.closeText,
						currentText: wcfm_datepicker_params.currentText,
						monthNames: wcfm_datepicker_params.monthNames,
						monthNamesShort: wcfm_datepicker_params.monthNamesShort,
						dayNames: wcfm_datepicker_params.dayNames,
						dayNamesShort: wcfm_datepicker_params.dayNamesShort,
						dayNamesMin: wcfm_datepicker_params.dayNamesMin,
						firstDay: wcfm_datepicker_params.firstDay,
						isRTL: wcfm_datepicker_params.isRTL,
						timeFormat: 'h:mm tt',
						changeMonth: true,
						changeYear: true,
						yearRange: '1920:2030'
					};

					dateField.datetimepicker( options );
				} );
			}
		},

		submitForm: function ( options = { status: 'draft' } ) {
			$( '.wcfm_submit_button' ).hide();

			const { status } = options;

			// Validations
			const $is_valid = this.inventoryManageFormValidate( status === 'publish' );

			if ( $is_valid ) {
				$( '#wcfm_rnb_inventory_manage_form' ).block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				} );

				const data = {
					action: 'wcfm_ajax_controller',
					controller: 'wcfm-rnb-inventory-manage',
					form_data: $( '#wcfm_rnb_inventory_manage_form' ).serialize(),
					status: status,
					wcfm_ajax_nonce: window.wcfm_params.wcfm_ajax_nonce
				}

				$.post( window.wcfm_params.ajax_url, data, function ( response ) {
					if ( response ) {
						$( '.wcfm-message' ).html( '' ).removeClass( 'wcfm-error' ).removeClass( 'wcfm-success' ).slideUp();
						wcfm_notification_sound.play();
						if ( response.status ) {
							$( '#wcfm_rnb_inventory_manage_form .wcfm-message' ).html( '<span class="wcicon-status-completed"></span>' + response.message ).addClass( 'wcfm-success' ).slideDown( "slow", function () {
								if ( response.redirect ) window.location = response.redirect;
							} );
						} else {
							$( '#wcfm_rnb_inventory_manage_form .wcfm-message' ).html( '<span class="wcicon-status-cancelled"></span>' + response.message ).addClass( 'wcfm-error' ).slideDown();
						}
						if ( response.id ) $( '#inventory_id' ).val( response.id );
						$( '#wcfm_rnb_inventory_manage_form' ).unblock();
						$( '.wcfm_submit_button' ).show();
					}
				} );
			} else {
				$( '.wcfm_submit_button' ).show();
			}
		},

		inventoryManageFormValidate: function ( is_publish ) {
			let product_form_is_valid = true;
			const inventoryForm = $( '#wcfm_rnb_inventory_manage_form' );
			const formInputTitle = inventoryForm.find( '#title' );
			const title = $.trim( formInputTitle.val() );

			$( '.wcfm-message' ).html( '' ).removeClass( 'wcfm-error' ).removeClass( 'wcfm-success' ).slideUp();
			formInputTitle.removeClass( 'wcfm_validation_failed' ).addClass( 'wcfm_validation_success' );

			if ( title.length == 0 ) {
				formInputTitle.removeClass( 'wcfm_validation_success' ).addClass( 'wcfm_validation_failed' );
				product_form_is_valid = false;
				$( '#wcfm_rnb_inventory_manage_form .wcfm-message' ).html( '<span class="wcicon-status-cancelled"></span>' + window.rnb_inventory_manage.l10n.no_title ).addClass( 'wcfm-error' ).slideDown();
				wcfm_notification_sound.play();

				return product_form_is_valid;
			}

			if ( is_publish ) {
				const events = ['wcfm_rnb_inventory_manage_form_validate', 'wcfm_form_validate'];
				const documentBody = $( document.body );

				for ( let event of events ) {
					// these events can change the global window.$wcfm_is_valid_form variable
					documentBody.trigger( event, inventoryForm );
				}

				// return the global form valid status
				return window.$wcfm_is_valid_form;
			}

			return product_form_is_valid;
		}
	};

	$( document ).ready( () => {
		rnbInventoryManage.init();
	} );

} )( jQuery );