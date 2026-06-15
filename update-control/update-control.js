jQuery(document).ready(function($){
	$('#update_control_active').change(function(){
		if ( 'yes' !== $(this).val() ) {
			$('.update_control_dependency').attr( 'readonly', 'readonly' );
			$('#update_control_toggleadvanced').val('hide').trigger('change');
			$('.update_control_advanced' ).closest('tr').css( 'display', 'none' );
		} else {
			$('.update_control_dependency' ).removeAttr( 'readonly' );
			$('#update_control_toggleadvanced').trigger('change');
		}
	}).trigger('change');

	$('#update_control_toggleadvanced').change(function(){
		if ( 'yes' === $('#update_control_active').val() && 'hide' !== $(this).val() ) {
			$('.update_control_advanced').closest('tr').css( { 'display' : 'table-row' } );
			$('.update_control_advanced').closest('tr').find( 'th' ).css( { 'display' : 'block', 'padding-left' : '20px' } );
		} else {
			$('.update_control_advanced' ).closest('tr').css( 'display', 'none' );
		}
	}).trigger('change');

	$('#update_control_email_active').change(function(){
		if ( 'yes' !== $(this).val() ) {
			$('.update_control_email_dependency.update_control_advanced').attr( 'readonly', 'readonly' );
			$('.update_control_email_dependency.update_control_advanced').closest('tr').find( 'th' ).children().css( { 'padding-left' : '20px', 'display' : 'block' } );
		} else {
			$('.update_control_email_dependency.update_control_advanced' ).removeAttr( 'readonly' );
			$('.update_control_email_dependency.update_control_advanced').closest('tr').find( 'th' ).children().css( { 'padding-left' : '20px', 'display' : 'block' } );
		}
	}).trigger('change');
});
