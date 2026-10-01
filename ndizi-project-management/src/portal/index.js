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
		initTaskDiscussions();
		initCompletedToggle();
		openDiscussionFromHash();
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
	 * Task discussions expand inline beneath the task row, like projects do.
	 * The thread is fetched on first open and then kept.
	 */
	function initTaskDiscussions() {
		const ajaxUrl =
			typeof window.ndizi_portal !== 'undefined' &&
			window.ndizi_portal.ajax_url
				? window.ndizi_portal.ajax_url
				: '/wp-admin/admin-ajax.php';

		$( document ).on( 'click', '.ndizi-task-row', function ( e ) {
			// Links inside the row keep their own behaviour.
			if ( $( e.target ).closest( 'a' ).length ) {
				return;
			}
			toggleTaskDiscussion(
				$( this ).find( '.ndizi-btn-comment-dialog' ),
				ajaxUrl
			);
		} );
	}

	/**
	 * Open or close one task's inline discussion panel.
	 *
	 * @param {Object}  $btn      The task's discussion toggle button (jQuery).
	 * @param {string}  ajaxUrl   admin-ajax endpoint.
	 * @param {boolean} forceOpen Open even if currently open.
	 */
	function toggleTaskDiscussion( $btn, ajaxUrl, forceOpen ) {
		const $panel = $( '#' + $btn.attr( 'aria-controls' ) );
		const open = forceOpen || $btn.attr( 'aria-expanded' ) !== 'true';

		$btn.attr( 'aria-expanded', open ? 'true' : 'false' );
		$panel.prop( 'hidden', ! open );

		if ( ! open || $panel.data( 'loaded' ) ) {
			return;
		}

		$panel.html(
			'<div class="no-items"><span class="spinner is-active" style="float:none; margin:0 auto 10px;"></span> Loading messages...</div>'
		);

		$.post( ajaxUrl, {
			action: 'ndizi_load_task_discussion',
			task_id: $btn.data( 'post-id' ),
		} )
			.done( function ( response ) {
				if ( response && response.success && response.data.html ) {
					$panel.html( response.data.html ).data( 'loaded', true );
				} else {
					$panel.html(
						'<div class="ndizi-portal-alert alert-error">Error loading discussion thread.</div>'
					);
				}
			} )
			.fail( function () {
				$panel.html(
					'<div class="ndizi-portal-alert alert-error">Error loading discussion thread.</div>'
				);
			} );
	}

	/**
	 * After posting a message the page reloads with #ndizi-discussion-<id>;
	 * reopen the owning project card and, for tasks, the thread itself.
	 */
	function openDiscussionFromHash() {
		const match = /^#ndizi-discussion-(\d+)$/.exec( window.location.hash );
		if ( ! match ) {
			return;
		}
		const $target = $( '#ndizi-discussion-' + match[ 1 ] );
		const $card = $target.closest( '.ndizi-project-card' );
		if ( ! $card.length ) {
			return;
		}
		$card.addClass( 'ndizi-active-project' );
		$card.find( '.ndizi-project-card-content' ).show();

		const $btn = $card.find(
			'.ndizi-btn-comment-dialog[data-post-id="' + match[ 1 ] + '"]'
		);
		if ( $btn.length ) {
			const ajaxUrl =
				typeof window.ndizi_portal !== 'undefined' &&
				window.ndizi_portal.ajax_url
					? window.ndizi_portal.ajax_url
					: '/wp-admin/admin-ajax.php';
			toggleTaskDiscussion( $btn, ajaxUrl, true );
		}
		$target.get( 0 ).scrollIntoView( { block: 'center' } );
	}

	/**
	 * Hide completed tasks (remembered per browser), with a per-project link
	 * to reveal that project's completed tasks again.
	 */
	function initCompletedToggle() {
		const $toggle = $( '#ndizi_hide_completed' );
		if ( ! $toggle.length ) {
			return;
		}
		const $main = $( '.ndizi-portal-main' );
		const storageKey = 'ndizi_portal_hide_completed';

		const apply = function ( hide ) {
			$main.toggleClass( 'ndizi-hide-completed', hide );
			$toggle.prop( 'checked', hide );
			if ( ! hide ) {
				$( '.ndizi-project-card' ).removeClass(
					'ndizi-show-completed'
				);
				syncLinkLabels();
			}
		};

		const syncLinkLabels = function () {
			$( '.ndizi-show-completed-link' ).each( function () {
				const $link = $( this );
				const shown = $link
					.closest( '.ndizi-project-card' )
					.hasClass( 'ndizi-show-completed' );
				$link.text(
					shown
						? $link.data( 'hide-label' )
						: $link.data( 'show-label' )
				);
			} );
		};

		try {
			apply( window.localStorage.getItem( storageKey ) === '1' );
		} catch ( err ) {
			apply( false );
		}

		$toggle.on( 'change', function () {
			const hide = $( this ).is( ':checked' );
			apply( hide );
			try {
				window.localStorage.setItem( storageKey, hide ? '1' : '0' );
			} catch ( err ) {
				// Storage unavailable; the choice just won't persist.
			}
		} );

		$( document ).on( 'click', '.ndizi-show-completed-link', function () {
			$( this )
				.closest( '.ndizi-project-card' )
				.toggleClass( 'ndizi-show-completed' );
			syncLinkLabels();
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
