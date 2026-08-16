'use strict';

( function ( $ ) {
    let $product_vendor = GetURLParameter('vendor');

    const rnbInventory = {
        init: function () {
            this.document = $( document );
            this.table = $( '#wcfm-rnb-inventory' );

            this.initDataTable();
            this.bindEvents();
            this.showSearchFilters();
        },

        bindEvents: function() {
            this.vendorSelectorHandler();
            this.inventoryDeleteHandler();
        },

        vendorSelectorHandler: function() {
            const vendorSelector = $('#dropdown_vendor');
            
            if( vendorSelector.length > 0 ) {
                vendorSelector.on('change', (event) => {
                    $product_vendor = vendorSelector.val();
                    this.table.ajax.reload();
                })
                .select2( $wcfm_vendor_select_args );
            }
        },

        inventoryDeleteHandler: function() {
            const inventoryDataTable = this.table;

            this.document.on('click', '.wcfm_rnb_inventory_delete', (event) => {
                event.preventDefault();

                const confirmDelete = confirm(rnb_inventory.l10n.invetory_delete_confirm);
				
                if ( confirmDelete ) {
                    const rnbInventoryWrapper = $( '#wcfm-rnb-inventory_wrapper' );

                    rnbInventoryWrapper.block( {
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    } );

                    var data = {
                        action: 'delete_wcfm_rnb_inventory',
                        inventory_id: $( event.currentTarget ).data( 'inventory_id' ),
                        wcfm_ajax_nonce: wcfm_params.wcfm_ajax_nonce
                    }

                    $.ajax( {
                        type: 'POST',
                        url: wcfm_params.ajax_url,
                        data: data,
                        success: function ( response ) {
                            if ( inventoryDataTable ) inventoryDataTable.ajax.reload();
                            rnbInventoryWrapper.unblock();
                        },
                    } );
                };

				return false;
            });
        },

        initDataTable: function () {
            this.table = this.table.DataTable( {
                deferRender : true,
                processing  : true,
                serverSide  : true,
                responsive  : true,
                pageLength  : parseInt(dataTables_config.pageLength),
                language    : $.parseJSON(dataTables_language),
                columnDefs  : [
                    { targets: '_all', orderable: false },
                    { responsivePriority: 1, targets: 0, orderable: false },
                    { responsivePriority: 2, targets: [-3, -2, -1], orderable: false },
                ],
                'ajax': {
                    "type": "POST",
                    "url": wcfm_params.ajax_url,
                    "data": function ( d ) {
                        d.action = 'wcfm_ajax_controller',
                        d.controller = 'wcfm-rnb-inventory',
                        d.status = GetURLParameter('status'),
                        d.vendor = $product_vendor,
                        d.wcfm_ajax_nonce = wcfm_params.wcfm_ajax_nonce
                    },
                    "complete": function () {
                        initiateTip();

                        // Fire wcfm-rnb-inventory table refresh complete
                        $( document.body ).trigger( 'updated_wcfm-rnb-inventory' );
                    }
                }
            } );
        },

        showSearchFilters: function() {
            const filterWrap = $('.wcfm_filters_wrap');
            if( filterWrap.length > 0 ) {
                $('.dataTable').before( filterWrap );
                filterWrap.css( 'display', 'block' );
            }
        },
    };

    $(document).ready(() => {
		rnbInventory.init();
	});
} )( jQuery );
