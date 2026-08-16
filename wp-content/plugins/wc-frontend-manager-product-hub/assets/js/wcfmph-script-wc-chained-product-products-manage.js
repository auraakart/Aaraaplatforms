jQuery(document).ready(function($) {
  $('#_chained_product_detail').find('select').each(function() { 
  	$(this).select2( $wcfm_product_select_args,{ placeholder: 'Search for a product...' } ); 
  } );
	$('#_chained_product_detail').find('.add_multi_input_block').click(function() {
		$('#_chained_product_detail').find('.multi_input_block:last').find('input').val('1');
	  $('#_chained_product_detail').find('.multi_input_block:last').find('select').each(function() { 
	  	$(this).val('');
	    $(this).select2( $wcfm_product_select_args,{ placeholder: 'Search for a product...' } ); 
	  } );
	});
	
	$('.chained_product_detail_variation').each(function() {
		$(this).find('input[type=checkbox]').removeClass('wcfm_ele_hide');
		$(this).find('p.checkbox_title').removeClass('wcfm_ele_hide');
		$(this).find('select').each(function() { $(this).select2( $wcfm_product_select_args,{ placeholder: 'Search for a product...' } ); } );
		$(this).find('.add_multi_input_block').click(function() {
			resetCollapsHeight($('#variations'));
			$(this).find('.chained_product_detail_variation').find('.multi_input_block:last').find('input').val('1');
			$(this).parents('.chained_product_detail_variation').find('.multi_input_block:last').find('select').each(function() { 
				$(this).val('');
				$(this).select2( $wcfm_product_select_args,{ placeholder: 'Search for a product...' } ); 
			} );
		});
	});

});