import './admin-style.scss';

/* global ndizi_admin */
/* eslint-disable camelcase, no-alert */

/**
 * Admin Script for Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	let timerInterval = null;
	let timerStartTs = 0;

	$( document ).ready( function () {
		initAuthKeyRegen();
		initTimeTracker();
		initInvoiceAggregator();
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
					timerStartTs = Math.floor( Date.now() / 1000 );
					startClockTicker( 0 );

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
					stopClockTicker();
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
					const timer = response.timer;
					const timerProjId = parseInt( timer.project_id );

					// Disable start buttons on other projects, enable stop button on active project
					if ( timerProjId === parseInt( projectId ) ) {
						$( '.ndizi-timer-bar' ).addClass(
							'ndizi-timer-running'
						);
						const $btn = $( '.ndizi-btn-start, .ndizi-btn-stop' );
						$btn.removeClass( 'ndizi-btn-start' )
							.addClass( 'ndizi-btn-stop' )
							.text( 'Stop' )
							.prop( 'disabled', false );

						$( '#ndizi_tracker_task_id' ).val( timer.task_id );
						$( '#ndizi_tracker_desc' ).val( timer.description );
						$( '#ndizi_tracker_billable' ).prop(
							'checked',
							parseInt( timer.billable ) === 1
						);

						// Sync live timer ticker offset
						const startTs = Math.floor(
							new Date(
								timer.start_time.replace( /-/g, '/' )
							).getTime() / 1000
						);
						// Fallback to live_duration if browser timezone mismatches SQL timezone
						const offset =
							timer.live_duration ||
							Math.max(
								0,
								Math.floor( Date.now() / 1000 ) - startTs
							);

						timerStartTs = Math.floor( Date.now() / 1000 ) - offset;
						startClockTicker( offset );
					} else {
						// Timer running on another project, disable start buttons here
						$( '.ndizi-btn-start' ).prop( 'disabled', true );
					}
				}
			} );
	}

	/**
	 * Clock ticking incrementer
	 *
	 * @param {number} startOffset Start Offset in seconds.
	 */
	function startClockTicker( startOffset ) {
		stopClockTicker();

		const formatTime = function ( sec ) {
			const h = Math.floor( sec / 3600 );
			const m = Math.floor( ( sec % 3600 ) / 60 );
			const s = sec % 60;
			return (
				( h < 10 ? '0' + h : h ) +
				':' +
				( m < 10 ? '0' + m : m ) +
				':' +
				( s < 10 ? '0' + s : s )
			);
		};

		$( '.ndizi-live-clock' ).text( formatTime( startOffset ) );

		timerInterval = setInterval( function () {
			const now = Math.floor( Date.now() / 1000 );
			const diff = now - timerStartTs;
			$( '.ndizi-live-clock' ).text( formatTime( diff ) );
		}, 1000 );
	}

	/**
	 * Stop clock ticking interval
	 */
	function stopClockTicker() {
		if ( timerInterval ) {
			clearInterval( timerInterval );
			timerInterval = null;
		}
		$( '.ndizi-live-clock' ).text( '00:00:00' );
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

		// Calculate invoice amount
		$( '#ndizi_btn_calc_invoice' ).on( 'click', function ( e ) {
			e.preventDefault();
			let totalSec = 0;
			const rate = parseFloat( $( '#ndizi_hourly_rate' ).val() ) || 0;

			$( '.ndizi-invoice-time-checkbox:checked' ).each( function () {
				totalSec += parseInt( $( this ).data( 'duration' ) ) || 0;
			} );

			const hours = totalSec / 3600;
			const amount = hours * rate;

			$( '#ndizi_invoice_amount' ).val( amount.toFixed( 2 ) );
		} );
	}
} )( window.jQuery );
