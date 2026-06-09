jQuery(document).ready(function($){
	$('.ndizi-datepicker').datepicker({
		dateFormat	:'yy-mm-dd'
	});
	$('.ndizi-timepicker').timepicker({
		timeFormat		:'hh:mm',
		hour			:1,
		min				:0,
		showButtonPanel	:false 
	});
});

