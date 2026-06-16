jQuery(document).ready(function($){
	$('#update_control_active').change(function(){
		if ( 'yes' !== $(this).val() ) {
			$('.update_control_dependency').prop( 'disabled', true ).attr( 'disabled', 'disabled' );
			$('#update_control_toggleadvanced').val('hide').trigger('change');
			$('.update_control_advanced' ).closest('tr').css( 'display', 'none' );
		} else {
			$('.update_control_dependency' ).prop( 'disabled', false ).removeAttr( 'disabled' );
			$('#update_control_toggleadvanced').trigger('change');
		}
	}).trigger('change');

	$('#update_control_toggleadvanced').change(function(){
		var val = $(this).val();
		var details = $('#update_control_toggleadvanced_details');
		if ( details.length ) {
			var is_open = ( 'show' === val );
			if ( details[0].open !== is_open ) {
				details[0].open = is_open;
			}
		}

		if ( 'yes' === $('#update_control_active').val() && 'hide' !== val ) {
			$('.update_control_advanced').closest('tr').css( { 'display' : 'table-row' } );
			$('.update_control_advanced').closest('tr').find( 'th' ).css( { 'display' : 'block', 'padding-left' : '20px' } );
		} else {
			$('.update_control_advanced' ).closest('tr').css( 'display', 'none' );
		}
	}).trigger('change');

	$('#update_control_toggleadvanced_details').on('toggle', function() {
		var is_open = this.open;
		var val = is_open ? 'show' : 'hide';
		var input = $('#update_control_toggleadvanced');
		if ( input.val() !== val ) {
			input.val(val).trigger('change');
		}
	});

	$('#update_control_email_active').change(function(){
		if ( 'yes' !== $(this).val() ) {
			$('.update_control_email_dependency.update_control_advanced').prop( 'disabled', true ).attr( 'disabled', 'disabled' );
			$('.update_control_email_dependency.update_control_advanced').closest('tr').find( 'th' ).children().css( { 'padding-left' : '20px', 'display' : 'block' } );
		} else {
			$('.update_control_email_dependency.update_control_advanced' ).prop( 'disabled', false ).removeAttr( 'disabled' );
			$('.update_control_email_dependency.update_control_advanced').closest('tr').find( 'th' ).children().css( { 'padding-left' : '20px', 'display' : 'block' } );
		}
	}).trigger('change');
});
