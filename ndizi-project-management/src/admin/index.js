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

			window.navigator.clipboard
				.writeText( $link.data( 'url' ) )
				.then( function () {
					$link.text( 'Copied!' );
					setTimeout( function () {
						$link.text( original );
					}, 1500 );
				} );
		} );
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
