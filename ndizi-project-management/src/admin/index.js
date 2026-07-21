import './admin-style.scss';
import { formatTime, createTimer } from '../shared/timer.js';

/* global ndizi_admin */
/* eslint-disable camelcase, no-alert */

/**
 * Admin Script for Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	const clock = createTimer(
		( elapsed ) => $( '.ndizi-live-clock' ).text( formatTime( elapsed ) ),
		() => $( '.ndizi-live-clock' ).text( '00:00:00' )
	);

	$( document ).ready( function () {
		initAuthKeyRegen();
		initTimeTracker();
		initInvoiceAggregator();
		initLineItemsEditor();
		initPaymentsEditor();
		initTrackerLauncher();
		initSelectOnClick();
		initCopyPortalLink();
	} );

	/**
	 * Client Portal Key Generator
	 */
	function initAuthKeyRegen() {
		$( '.ndizi-regen-key-btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			const chars =
				'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
			// Use the Web Crypto API for a cryptographically secure token; this
			// value is an authentication credential, so Math.random() (which is
			// not suitable for secrets) must not be used.
			let key = 'ndizi_';
			const randomValues = new Uint32Array( 16 );
			window.crypto.getRandomValues( randomValues );
			for ( let i = 0; i < randomValues.length; i++ ) {
				key += chars.charAt( randomValues[ i ] % chars.length );
			}
			$( '#ndizi_client_auth_key' ).val( key );
		} );
	}

	/**
	 * Time Tracker Ticker & REST API Integrations
	 */
	function initTimeTracker() {
		const $tracker = $( '.ndizi-tracker-wrapper' );
		if ( ! $tracker.length ) {
			return;
		}

		const projectId = $( '.ndizi-btn-start, .ndizi-btn-stop' )
			.first()
			.data( 'project-id' );

		// Sync timer state on load
		checkActiveTimer( projectId );

		// Start Timer Event
		$( document ).on( 'click', '.ndizi-btn-start', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			const taskId = $( '#ndizi_tracker_task_id' ).val();
			const desc = $( '#ndizi_tracker_desc' ).val();
			const billable = $( '#ndizi_tracker_billable' ).is( ':checked' )
				? 1
				: 0;

			$btn.prop( 'disabled', true ).text( 'Starting...' );

			wp.ajax
				.post( 'ndizi_start_timer_action', {
					project_id: projectId,
					task_id: taskId,
					description: desc,
					billable,
					nonce: ndizi_admin.nonce,
				} )
				.done( function () {
					// Success, transition to running state
					$( '.ndizi-timer-bar' ).addClass( 'ndizi-timer-running' );
					$btn.removeClass( 'ndizi-btn-start' )
						.addClass( 'ndizi-btn-stop' )
						.text( 'Stop' )
						.prop( 'disabled', false );

					// Set ticker
					clock.start( 0 );

					refreshLogsTable( projectId );
				} )
				.fail( function ( err ) {
					window.alert( err.message || 'Error starting timer.' );
					$btn.prop( 'disabled', false ).text( 'Start Timer' );
				} );
		} );

		// Stop Timer Event
		$( document ).on( 'click', '.ndizi-btn-stop', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			$btn.prop( 'disabled', true ).text( 'Stopping...' );

			wp.ajax
				.post( 'ndizi_stop_timer_action', {
					nonce: ndizi_admin.nonce,
				} )
				.done( function () {
					// Reset ticker
					clock.stop();
					$( '.ndizi-timer-bar' ).removeClass(
						'ndizi-timer-running'
					);
					$btn.removeClass( 'ndizi-btn-stop' )
						.addClass( 'ndizi-btn-start' )
						.text( 'Start Timer' )
						.prop( 'disabled', false );

					// Reset form fields
					$( '#ndizi_tracker_desc' ).val( '' );

					refreshLogsTable( projectId );

					// Enable other start buttons if any
					$( '.ndizi-btn-start' ).prop( 'disabled', false );
				} )
				.fail( function ( err ) {
					window.alert( err.message || 'Error stopping timer.' );
					$btn.prop( 'disabled', false ).text( 'Stop' );
				} );
		} );

		// Delete Log Event
		$( document ).on( 'click', '.ndizi-delete-log-btn', function ( e ) {
			e.preventDefault();

			if ( ! window.confirm( ndizi_admin.labels.confirm_delete ) ) {
				return;
			}

			const $btn = $( this );
			const logId = $btn.data( 'id' );
			$btn.prop( 'disabled', true );

			wp.ajax
				.post( 'ndizi_delete_log_action', {
					log_id: logId,
					nonce: ndizi_admin.nonce,
				} )
				.done( function () {
					$( '#ndizi-log-row-' + logId ).fadeOut( function () {
						$( this ).remove();
						if ( $( '#ndizi_logs_table_body tr' ).length === 0 ) {
							$( '#ndizi_logs_table_body' ).html(
								'<tr class="no-items"><td colspan="7">No time logged yet on this project.</td></tr>'
							);
						}
					} );
				} )
				.fail( function ( err ) {
					window.alert( err.message || 'Error deleting log.' );
					$btn.prop( 'disabled', false );
				} );
		} );
	}

	/**
	 * Verify active timer via AJAX on load
	 *
	 * @param {number} projectId Project ID.
	 */
	function checkActiveTimer( projectId ) {
		wp.ajax
			.post( 'ndizi_check_active_timer', {
				nonce: ndizi_admin.nonce,
			} )
			.done( function ( response ) {
				if ( response.active ) {
					const timerData = response.timer;
					const timerProjId = parseInt( timerData.project_id, 10 );

					// Disable start buttons on other projects, enable stop button on active project
					if ( timerProjId === parseInt( projectId, 10 ) ) {
						$( '.ndizi-timer-bar' ).addClass(
							'ndizi-timer-running'
						);
						const $btn = $( '.ndizi-btn-start, .ndizi-btn-stop' );
						$btn.removeClass( 'ndizi-btn-start' )
							.addClass( 'ndizi-btn-stop' )
							.text( 'Stop' )
							.prop( 'disabled', false );

						$( '#ndizi_tracker_task_id' ).val( timerData.task_id );
						$( '#ndizi_tracker_desc' ).val( timerData.description );
						$( '#ndizi_tracker_billable' ).prop(
							'checked',
							parseInt( timerData.billable, 10 ) === 1
						);

						// Sync live timer ticker offset
						const startTs = Math.floor(
							new Date(
								timerData.start_time.replace( /-/g, '/' )
							).getTime() / 1000
						);
						// Fallback to live_duration if browser timezone mismatches SQL timezone
						const offset =
							timerData.live_duration ||
							Math.max(
								0,
								Math.floor( Date.now() / 1000 ) - startTs
							);

						clock.start( offset );
					} else {
						// Timer running on another project, disable start buttons here
						$( '.ndizi-btn-start' ).prop( 'disabled', true );
					}
				}
			} );
	}

	/**
	 * Re-query project logs and rebuild table markup
	 *
	 * @param {number} projectId Project ID.
	 */
	function refreshLogsTable( projectId ) {
		wp.ajax
			.post( 'ndizi_refresh_logs_table', {
				project_id: projectId,
				nonce: ndizi_admin.nonce,
			} )
			.done( function ( response ) {
				if ( response.html ) {
					$( '#ndizi_logs_table_body' ).html( response.html );
				}
			} );
	}

	/**
	 * Invoice Aggregate Time Entries
	 */
	function initInvoiceAggregator() {
		// Select All Checkbox
		$( '#ndizi_select_all_invoice_time' ).on( 'change', function () {
			$( '.ndizi-invoice-time-checkbox' ).prop(
				'checked',
				$( this ).is( ':checked' )
			);
		} );

		// Update hourly rate fallback input on project change
		$( '#ndizi_invoice_project_id' ).on( 'change', function () {
			const selectedOption = $( this ).find( 'option:selected' );
			const rate = selectedOption.attr( 'data-rate' ) || '';
			$( '#ndizi_hourly_rate' ).val( rate );
		} );

		// Calculate invoice amount
		$( '#ndizi_btn_calc_invoice' ).on( 'click', function ( e ) {
			e.preventDefault();
			let totalAmount = 0;
			const defaultRate =
				parseFloat( $( '#ndizi_hourly_rate' ).val() ) || 0;

			$( '.ndizi-invoice-time-checkbox:checked' ).each( function () {
				const duration =
					parseInt( $( this ).data( 'duration' ), 10 ) || 0;
				const entryRateAttr = $( this ).attr( 'data-rate' );
				const rate =
					entryRateAttr !== undefined && entryRateAttr !== ''
						? parseFloat( entryRateAttr )
						: defaultRate;
				totalAmount += ( duration / 3600 ) * rate;
			} );

			$( '#ndizi_invoice_amount' ).val( totalAmount.toFixed( 2 ) );
		} );
	}

	/**
	 * Dynamic Line Items Table Editor
	 */
	function initLineItemsEditor() {
		const $table = $( '#ndizi_line_items_table' );
		if ( ! $table.length ) {
			return;
		}

		function calcRow( $row ) {
			const qty = parseFloat( $row.find( '.ndizi-li-qty' ).val() ) || 0;
			const price =
				parseFloat( $row.find( '.ndizi-li-price' ).val() ) || 0;
			$row.find( '.ndizi-li-amount' ).val( ( qty * price ).toFixed( 2 ) );
		}

		$( document ).on(
			'input change',
			'.ndizi-li-qty, .ndizi-li-price',
			function () {
				calcRow( $( this ).closest( 'tr' ) );
			}
		);

		$( '#ndizi_add_line_item_btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			const newRowHtml = `
				<tr class="ndizi-line-item-row">
					<td><input type="text" name="ndizi_line_items_desc[]" value="" class="large-text"></td>
					<td><input type="number" step="0.01" name="ndizi_line_items_qty[]" value="1" class="small-text ndizi-li-qty"></td>
					<td><input type="number" step="0.01" name="ndizi_line_items_price[]" value="0.00" class="small-text ndizi-li-price"></td>
					<td><input type="number" step="0.01" name="ndizi_line_items_amount[]" value="0.00" class="small-text ndizi-li-amount" readonly></td>
					<td><button type="button" class="button button-secondary ndizi-remove-li-row">&times;</button></td>
				</tr>`;
			$( '#ndizi_line_items_body' ).append( newRowHtml );
		} );

		$( document ).on( 'click', '.ndizi-remove-li-row', function ( e ) {
			e.preventDefault();
			const $rows = $( '#ndizi_line_items_body tr' );
			if ( $rows.length > 1 ) {
				$( this ).closest( 'tr' ).remove();
			} else {
				$( this ).closest( 'tr' ).find( 'input' ).val( '' );
				$( this ).closest( 'tr' ).find( '.ndizi-li-qty' ).val( '1' );
				$( this )
					.closest( 'tr' )
					.find( '.ndizi-li-price' )
					.val( '0.00' );
				$( this )
					.closest( 'tr' )
					.find( '.ndizi-li-amount' )
					.val( '0.00' );
			}
		} );

		$( '#ndizi_calc_line_items_btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			let total = 0;
			$( '#ndizi_line_items_body tr' ).each( function () {
				calcRow( $( this ) );
				total +=
					parseFloat( $( this ).find( '.ndizi-li-amount' ).val() ) ||
					0;
			} );
			$( '#ndizi_invoice_amount' )
				.val( total.toFixed( 2 ) )
				.trigger( 'change' );
		} );
	}

	/**
	 * Dynamic Payments Table Editor
	 */
	function initPaymentsEditor() {
		const $table = $( '#ndizi_payments_table' );
		if ( ! $table.length ) {
			return;
		}

		function recalc() {
			let paid = 0;
			$( '#ndizi_payments_body .ndizi-pay-amount' ).each( function () {
				paid += parseFloat( $( this ).val() ) || 0;
			} );
			const amount =
				parseFloat( $( '#ndizi_invoice_amount' ).val() ) || 0;
			$( '#ndizi_payments_total' ).text( paid.toFixed( 2 ) );
			$( '#ndizi_payments_balance' ).text(
				( amount - paid ).toFixed( 2 )
			);
		}

		$( document ).on(
			'input change',
			'.ndizi-pay-amount, #ndizi_invoice_amount',
			recalc
		);

		$( '#ndizi_add_payment_btn' ).on( 'click', function ( e ) {
			e.preventDefault();
			const newRowHtml = `
				<tr class="ndizi-payment-row">
					<td><input type="date" name="ndizi_payments_date[]" value=""></td>
					<td><input type="number" step="0.01" name="ndizi_payments_amount[]" value="0.00" class="small-text ndizi-pay-amount"></td>
					<td><input type="text" name="ndizi_payments_method[]" value="" class="regular-text"></td>
					<td><input type="text" name="ndizi_payments_note[]" value="" class="large-text"></td>
					<td><button type="button" class="button button-secondary ndizi-remove-pay-row">&times;</button></td>
				</tr>`;
			$( '#ndizi_payments_body' ).append( newRowHtml );
		} );

		$( document ).on( 'click', '.ndizi-remove-pay-row', function ( e ) {
			e.preventDefault();
			$( this ).closest( 'tr' ).remove();
			recalc();
		} );

		recalc();
	}

	/**
	 * Launch Standalone Tracker
	 */
	function initTrackerLauncher() {
		$( document ).on( 'click', '.ndizi-launch-tracker', function ( e ) {
			e.preventDefault();
			const url = $( this ).data( 'tracker-url' );
			if ( url ) {
				window.open(
					url,
					'ndizi_tracker',
					'width=380,height=640,resizable=yes,scrollbars=yes'
				);
			}
		} );
	}

	/**
	 * Copy a client's portal login link to the clipboard from the row action
	 */
	function initCopyPortalLink() {
		$( document ).on( 'click', '.ndizi-copy-portal-link', function ( e ) {
			e.preventDefault();
			const $link = $( this );
			const original = $link.text();
			const url = $link.data( 'url' );

			const showCopied = function () {
				$link.text( 'Copied!' );
				setTimeout( function () {
					$link.text( original );
				}, 1500 );
			};

			if ( window.navigator.clipboard ) {
				window.navigator.clipboard
					.writeText( url )
					.then( showCopied, function () {
						copyWithFallback( url, showCopied );
					} );
			} else {
				copyWithFallback( url, showCopied );
			}
		} );
	}

	/**
	 * Copy text using a temporary input + execCommand, for non-secure
	 * contexts (HTTP) or browsers without the Clipboard API.
	 *
	 * @param {string}   text     Text to copy.
	 * @param {Function} onCopied Called only if the copy actually succeeded.
	 */
	function copyWithFallback( text, onCopied ) {
		const $tempInput = $( '<input>' ).val( text ).css( {
			position: 'fixed',
			top: '-1000px',
		} );
		$( 'body' ).append( $tempInput );
		$tempInput[ 0 ].select();
		let succeeded = false;
		try {
			succeeded = document.execCommand( 'copy' );
		} catch ( err ) {
			succeeded = false;
		}
		$tempInput.remove();
		if ( succeeded ) {
			onCopied();
		}
	}

	/**
	 * Select text inside readonly input fields on click
	 */
	function initSelectOnClick() {
		$( document ).on( 'click', '.ndizi-select-on-click', function () {
			this.select();
		} );
	}
} )( window.jQuery );
