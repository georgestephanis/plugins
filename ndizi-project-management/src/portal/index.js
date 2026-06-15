import './portal-style.scss';

/**
 * Frontend Portal Script - Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	$( document ).ready( function () {
		initProjectAccordion();
		initTaskCommentModal();
		initStripePayment();
	} );

	/**
	 * Project Card Accordion
	 */
	function initProjectAccordion() {
		$( '.ndizi-project-card-header' ).on( 'click', function ( e ) {
			// Prevent toggle if clicking links or action buttons inside header (if any)
			if ( $( e.target ).closest( 'a, button' ).length ) {
				return;
			}

			const $card = $( this ).closest( '.ndizi-project-card' );
			const $content = $card.find( '.ndizi-project-card-content' );

			$card.toggleClass( 'ndizi-active-project' );
			$content.slideToggle( 300 );
		} );
	}

	/**
	 * Task Comment Overlay Modal
	 */
	function initTaskCommentModal() {
		const $modal = $( '#ndizi_task_comment_modal' );
		if ( ! $modal.length ) {
			return;
		}

		// Open Modal on comment click
		$( document ).on( 'click', '.ndizi-btn-comment-dialog', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			const taskId = $btn.data( 'post-id' );
			const title = $btn.data( 'title' );

			$( '#ndizi_modal_task_title' ).text( 'Discussion: ' + title );
			$( '#ndizi_modal_discussion_container' ).html(
				'<div class="no-items"><span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span> Loading messages...</div>'
			);

			$modal.show();

			// Fetch discussion HTML via AJAX. Use jQuery against the localized
			// admin-ajax URL rather than wp.ajax, which depends on wp-util and a
			// global ajaxurl that are not guaranteed to exist on the frontend.
			const ajaxUrl =
				typeof window.ndizi_portal !== 'undefined' &&
				window.ndizi_portal.ajax_url
					? window.ndizi_portal.ajax_url
					: '/wp-admin/admin-ajax.php';

			$.post( ajaxUrl, {
				action: 'ndizi_load_task_discussion',
				task_id: taskId,
			} )
				.done( function ( response ) {
					if ( response && response.success && response.data.html ) {
						$( '#ndizi_modal_discussion_container' ).html(
							response.data.html
						);
					} else {
						$( '#ndizi_modal_discussion_container' ).html(
							'<div class="ndizi-portal-alert alert-error">Error loading discussion thread.</div>'
						);
					}
				} )
				.fail( function () {
					$( '#ndizi_modal_discussion_container' ).html(
						'<div class="ndizi-portal-alert alert-error">Error loading discussion thread.</div>'
					);
				} );
		} );

		// Close modal button
		$( '.ndizi-portal-modal-close-btn' ).on( 'click', function () {
			$modal.hide();
			$( '#ndizi_modal_discussion_container' ).empty();
		} );

		// Close modal on background click
		$modal.on( 'click', function ( e ) {
			if ( $( e.target ).hasClass( 'ndizi-portal-modal' ) ) {
				$modal.hide();
				$( '#ndizi_modal_discussion_container' ).empty();
			}
		} );
	}

	/**
	 * Stripe Payment processing
	 */
	function initStripePayment() {
		$( document ).on( 'click', '.ndizi-pay-invoice-btn', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			const invoiceId = $btn.data( 'invoice-id' );
			const token = $btn.data( 'token' );

			$btn.prop( 'disabled', true ).text( 'Redirecting...' );

			const restUrl =
				typeof window.ndizi_portal !== 'undefined' &&
				window.ndizi_portal.rest_url
					? window.ndizi_portal.rest_url
					: '/wp-json/ndizi/v1';

			$.ajax( {
				url: `${ restUrl }/invoices/${ invoiceId }/pay`,
				method: 'POST',
				dataType: 'json',
				contentType: 'application/json',
				data: JSON.stringify( { token } ),
			} )
				.done( function ( response ) {
					if ( response && response.url ) {
						window.location.href = response.url;
					} else {
						// eslint-disable-next-line no-alert
						window.alert( 'Error generating checkout session.' );
						$btn.prop( 'disabled', false ).html(
							'<span class="dashicons dashicons-cart"></span> Pay Online'
						);
					}
				} )
				.fail( function ( xhr ) {
					let errorMsg = 'Error initiating payment.';
					if ( xhr.responseJSON && xhr.responseJSON.message ) {
						errorMsg = xhr.responseJSON.message;
					}
					// eslint-disable-next-line no-alert
					window.alert( errorMsg );
					$btn.prop( 'disabled', false ).html(
						'<span class="dashicons dashicons-cart"></span> Pay Online'
					);
				} );
		} );
	}
} )( window.jQuery );
