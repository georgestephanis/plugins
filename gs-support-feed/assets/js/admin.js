( function ( $ ) {
	'use strict';

	$( function () {
		$( '#cb-select-all-top' ).on( 'change', function () {
			$( 'input[name="item_ids[]"]' ).prop( 'checked', this.checked );
		} );

		$( '#gs-sf-profile-import-form' ).on( 'submit', function () {
			var form    = $( this );
			var btn     = form.find( '#gs-sf-import-btn' );
			var spinner = form.find( '#gs-sf-import-spinner' );

			spinner.addClass( 'is-active' );
			setTimeout( function () {
				btn.addClass( 'disabled' ).attr( 'disabled', 'disabled' );
			}, 10 );
		} );

		$( '.toggle-read-btn' ).on( 'click', function ( e ) {
			e.preventDefault();

			var btn    = $( this );
			var itemId = btn.data( 'id' );
			var row    = $( '#item-row-' + itemId );

			btn.prop( 'disabled', true );

			$.post( gsSupportFeed.ajaxUrl, {
				action: 'gs_sf_toggle_read',
				item_id: itemId,
				nonce: gsSupportFeed.nonce
			}, function ( res ) {
				btn.prop( 'disabled', false );

				if ( ! res.success ) {
					return;
				}

				if ( res.data.read ) {
					row.removeClass( 'gs-sf-item-row-unread' ).addClass( 'gs-sf-item-row-read' );
					row.find( '.item-status-col' ).html( '<span class="gs-sf-status-read">' + gsSupportFeed.strings.read + '</span>' );
					btn.text( gsSupportFeed.strings.markUnread );
				} else {
					row.removeClass( 'gs-sf-item-row-read' ).addClass( 'gs-sf-item-row-unread' );
					row.find( '.item-status-col' ).html( '<span class="gs-sf-status-unread">● ' + gsSupportFeed.strings.unread + '</span>' );
					btn.text( gsSupportFeed.strings.markRead );
				}
			} );
		} );
	} );
}( jQuery ) );
