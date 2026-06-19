import './adminbar-style.scss';
import { formatTime, createTimer } from '../shared/timer.js';

/* global ndizi_adminbar */
/* eslint-disable camelcase, no-alert */

/**
 * Admin Bar Script for Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	let projectsData = [];
	let selectedProject = null;
	let selectedTask = null;
	let hasLoadedData = false;

	const clock = createTimer( function ( elapsed ) {
		const formatted = formatTime( elapsed );
		$( '#ndizi-ab-ticker-clock' ).text( formatted );
		$( '#wp-admin-bar-ndizi-time-tracker' )
			.find( '.ndizi-ab-label' )
			.removeClass( 'screen-reader-text' )
			.text( formatted );

		if ( elapsed > 28800 ) {
			const $panel = $( '#ndizi-ab-panel' );
			if ( ! $panel.find( '.ndizi-ab-warning-banner' ).length ) {
				const $icon = $(
					'<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'
				);
				const $banner = $(
					'<div class="ndizi-ab-warning-banner"></div>'
				)
					.append( $icon )
					.append(
						$( '<span></span>' ).text(
							ndizi_adminbar.labels.idle_warning
						)
					);
				$panel
					.find( '.ndizi-ab-active-timer-view' )
					.find( '.ndizi-ab-section-title' )
					.after( $banner );
			}
			$( '#wp-admin-bar-ndizi-time-tracker' ).addClass(
				'ndizi-timer-idle-warning'
			);
		}
	} );

	$( document ).ready( function () {
		init();
	} );

	/**
	 * Initialize the Admin Bar widget
	 */
	function init() {
		const $panel = $( '#ndizi-ab-panel' );
		if ( ! $panel.length ) {
			return;
		}

		const dialog = document.getElementById( 'ndizi-time-dialog' );

		// Open dialog when clicking the admin bar trigger
		$( '#wp-admin-bar-ndizi-time-tracker > .ab-item' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! hasLoadedData && ! $panel.hasClass( 'ndizi-timer-running' ) ) {
				loadTrackerData();
			}
			dialog && dialog.showModal();
		} );

		// Close button
		$( '#ndizi-dialog-close-btn' ).on( 'click', function () {
			dialog && dialog.close();
		} );

		// Close on backdrop click
		if ( dialog ) {
			dialog.addEventListener( 'click', function ( e ) {
				if ( e.target === dialog ) {
					dialog.close();
				}
			} );
		}

		// Check if timer is active on load
		if ( $panel.hasClass( 'ndizi-timer-running' ) ) {
			const offset = parseInt( $panel.data( 'duration' ), 10 ) || 0;
			clock.start( offset );
		}

		// Project selection change handler
		$( '#ndizi-ab-project-select' ).on( 'change', function () {
			const projectId = parseInt( $( this ).val(), 10 );
			const $taskGroup = $( '#ndizi-ab-task-select-group' );
			const $startBtn = $( '#ndizi-ab-btn-start' );
			const $statsCard = $( '#ndizi-ab-stats-card' );

			if ( ! projectId ) {
				$taskGroup.hide();
				$startBtn.prop( 'disabled', true );
				$statsCard.hide();
				selectedProject = null;
				selectedTask = null;
				return;
			}

			const $taskSelect = $( '#ndizi-ab-task-select' );
			selectedProject =
				projectsData.find( function ( p ) {
					return p.id === projectId;
				} ) || null;

			// Populate tasks dropdown
			$taskSelect.empty();
			$taskSelect.append(
				$( '<option>' )
					.val( '0' )
					.text( ndizi_adminbar.labels.select_task )
			);

			if (
				selectedProject &&
				selectedProject.tasks &&
				selectedProject.tasks.length > 0
			) {
				selectedProject.tasks.forEach( function ( task ) {
					$taskSelect.append(
						$( '<option>' ).val( task.id ).text( task.title )
					);
				} );
				$taskGroup.show();
			} else {
				$taskGroup.hide();
			}

			const isManualOpen = $( '#ndizi-ab-manual-log-panel' ).is(
				':visible'
			);
			$startBtn.prop( 'disabled', isManualOpen );
			selectedTask = null;
			updateStatsCard();
		} );

		// Task selection change handler
		$( '#ndizi-ab-task-select' ).on( 'change', function () {
			const taskId = parseInt( $( this ).val(), 10 );
			if ( selectedProject && taskId ) {
				selectedTask =
					selectedProject.tasks.find( function ( t ) {
						return t.id === taskId;
					} ) || null;
			} else {
				selectedTask = null;
			}
			updateStatsCard();
		} );

		// Toggle manual entry fields
		$( '#ndizi-ab-btn-toggle-manual' ).on( 'click', function ( e ) {
			e.preventDefault();
			const $manualPanel = $( '#ndizi-ab-manual-log-panel' );
			const $startBtn = $( '#ndizi-ab-btn-start' );
			const willOpen = $manualPanel.is( ':hidden' );

			$manualPanel.slideToggle( 200 );

			if ( willOpen ) {
				$startBtn.prop( 'disabled', true );
			} else {
				// Restore to correct state based on whether project is selected
				const projectId = parseInt(
					$( '#ndizi-ab-project-select' ).val(),
					10
				);
				$startBtn.prop( 'disabled', ! projectId );
			}
		} );

		// Start Timer Event
		$( '#ndizi-ab-btn-start' ).on( 'click', function ( e ) {
			e.preventDefault();
			const projectId = $( '#ndizi-ab-project-select' ).val();

			if ( ! projectId ) {
				return;
			}

			const $btn = $( this );
			const taskId = $( '#ndizi-ab-task-select' ).val() || 0;
			const desc = $( '#ndizi-ab-desc-input' ).val();
			const billable = $( '#ndizi-ab-billable-check' ).is( ':checked' )
				? 1
				: 0;

			$btn.prop( 'disabled', true ).text(
				ndizi_adminbar.labels.btn_starting
			);

			wp.ajax
				.post( 'ndizi_start_timer_action', {
					project_id: projectId,
					task_id: taskId,
					description: desc,
					billable,
					nonce: ndizi_adminbar.nonce,
				} )
				.done( function () {
					const $node = $( '#wp-admin-bar-ndizi-time-tracker' );
					$node.addClass( 'ndizi-timer-active' );
					$panel.addClass( 'ndizi-timer-running' );

					// Set running view titles
					$( '#ndizi-ab-active-project' ).text(
						selectedProject ? selectedProject.title : ''
					);
					if ( selectedTask ) {
						$( '#ndizi-ab-active-task' )
							.text( selectedTask.title )
							.show();
					} else {
						$( '#ndizi-ab-active-task' ).hide();
					}
					$( '#ndizi-ab-active-desc' ).text(
						desc || ndizi_adminbar.labels.no_description
					);

					// Start local ticking
					clock.start( 0 );
				} )
				.fail( function ( err ) {
					window.alert(
						err.message || ndizi_adminbar.labels.error_general
					);
					$btn.prop( 'disabled', false ).text(
						ndizi_adminbar.labels.btn_start_timer
					);
				} );
		} );

		// Stop Timer Event
		$( '#ndizi-ab-btn-stop' ).on( 'click', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			$btn.prop( 'disabled', true ).text(
				ndizi_adminbar.labels.btn_stopping
			);

			wp.ajax
				.post( 'ndizi_stop_timer_action', {
					nonce: ndizi_adminbar.nonce,
				} )
				.done( function () {
					clock.stop();

					const $node = $( '#wp-admin-bar-ndizi-time-tracker' );
					$node.removeClass(
						'ndizi-timer-active ndizi-timer-idle-warning'
					);
					$panel.find( '.ndizi-ab-warning-banner' ).remove();
					$node
						.find( '.ndizi-ab-label' )
						.removeClass( 'screen-reader-text' )
						.text( ndizi_adminbar.labels.timer_stopped );

					setTimeout( function () {
						$node
							.find( '.ndizi-ab-label' )
							.addClass( 'screen-reader-text' )
							.text( ndizi_adminbar.labels.btn_log_time );
					}, 3000 );

					$panel.removeClass( 'ndizi-timer-running' );
					$btn.prop( 'disabled', false ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg> Stop Timer'
					);

					// Reset inputs & cache
					$( '#ndizi-ab-desc-input' ).val( '' );
					hasLoadedData = false;
					selectedProject = null;
					selectedTask = null;
					$( '#ndizi-ab-project-select' ).val( '' );
					$( '#ndizi-ab-task-select-group' ).hide();
					$( '#ndizi-ab-stats-card' ).hide();
					$( '#ndizi-ab-btn-start' ).prop( 'disabled', true );
				} )
				.fail( function ( err ) {
					window.alert(
						err.message || ndizi_adminbar.labels.error_general
					);
					$btn.prop( 'disabled', false ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg> Stop Timer'
					);
				} );
		} );

		// Initialise date input to today
		$( '#ndizi-ab-manual-date' ).val( new Date().toISOString().slice( 0, 10 ) );

		// Toggle between disabled (today) and enabled (custom date)
		$( '#ndizi-ab-date-change-btn' ).on( 'click', function () {
			const $input = $( '#ndizi-ab-manual-date' );
			const $btn = $( this );
			if ( $input.prop( 'disabled' ) ) {
				$input.prop( 'disabled', false ).trigger( 'focus' );
				$btn.text( ndizi_adminbar.labels.back_to_today || 'Back to today' );
			} else {
				$input.val( new Date().toISOString().slice( 0, 10 ) ).prop( 'disabled', true );
				$btn.text( ndizi_adminbar.labels.change_date || 'Change date' );
			}
		} );

		// Save Manual Entry Event
		$( '#ndizi-ab-btn-save-manual' ).on( 'click', function ( e ) {
			e.preventDefault();
			const projectId = $( '#ndizi-ab-project-select' ).val();

			if ( ! projectId ) {
				window.alert( ndizi_adminbar.labels.select_project_first );
				return;
			}

			const h = parseInt( $( '#ndizi-ab-manual-hours' ).val(), 10 ) || 0;
			const m =
				parseInt( $( '#ndizi-ab-manual-minutes' ).val(), 10 ) || 0;
			const duration = h * 3600 + m * 60;

			if ( duration <= 0 ) {
				window.alert( ndizi_adminbar.labels.enter_duration );
				return;
			}

			const $btn = $( this );
			const taskId = $( '#ndizi-ab-task-select' ).val() || 0;
			const desc = $( '#ndizi-ab-desc-input' ).val();
			const billable = $( '#ndizi-ab-billable-check' ).is( ':checked' )
				? 1
				: 0;

			$btn.prop( 'disabled', true ).text(
				ndizi_adminbar.labels.btn_saving
			);

			const $dateInput = $( '#ndizi-ab-manual-date' );
			const logDate = $dateInput.prop( 'disabled' ) ? '' : $dateInput.val();

			wp.ajax
				.post( 'ndizi_log_time_manual_action', {
					project_id: projectId,
					task_id: taskId,
					description: desc,
					duration,
					billable,
					log_date: logDate,
					nonce: ndizi_adminbar.nonce,
				} )
				.done( function () {
					$btn.prop( 'disabled', false ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Log Entry'
					);

					const $node = $( '#wp-admin-bar-ndizi-time-tracker' );
					$node
						.find( '.ndizi-ab-label' )
						.removeClass( 'screen-reader-text' )
						.text( ndizi_adminbar.labels.entry_logged );

					setTimeout( function () {
						$node
							.find( '.ndizi-ab-label' )
							.addClass( 'screen-reader-text' )
							.text( ndizi_adminbar.labels.btn_log_time );
					}, 3000 );

					// Reset inputs & cache
					$( '#ndizi-ab-desc-input' ).val( '' );
					$( '#ndizi-ab-manual-hours' ).val( '' );
					$( '#ndizi-ab-manual-minutes' ).val( '' );
	$( '#ndizi-ab-manual-log-panel' ).slideUp( 200 );

					hasLoadedData = false;
					selectedProject = null;
					selectedTask = null;
					$( '#ndizi-ab-project-select' ).val( '' );
					$( '#ndizi-ab-task-select-group' ).hide();
					$( '#ndizi-ab-stats-card' ).hide();
					$( '#ndizi-ab-btn-start' ).prop( 'disabled', true );
				} )
				.fail( function ( err ) {
					window.alert(
						err.message || ndizi_adminbar.labels.error_general
					);
					$btn.prop( 'disabled', false ).html(
						'<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> Log Entry'
					);
				} );
		} );
	}

	/**
	 * Fetch Project and Task arrays via AJAX
	 */
	function loadTrackerData() {
		const $select = $( '#ndizi-ab-project-select' );
		$select.html(
			$( '<option>' )
				.val( '' )
				.text( ndizi_adminbar.labels.loading_projects )
		);

		wp.ajax
			.post( 'ndizi_get_tracker_data', {
				nonce: ndizi_adminbar.nonce,
			} )
			.done( function ( response ) {
				projectsData = response.projects || [];
				hasLoadedData = true;
				populateProjectsDropdown();
			} )
			.fail( function () {
				$select.html(
					$( '<option>' )
						.val( '' )
						.text( ndizi_adminbar.labels.error_general )
				);
			} );
	}

	/**
	 * Populates the Projects Dropdown menu
	 */
	function populateProjectsDropdown() {
		const $select = $( '#ndizi-ab-project-select' );
		$select.empty();

		if ( projectsData.length === 0 ) {
			$select.append(
				$( '<option>' )
					.val( '' )
					.text( ndizi_adminbar.labels.no_active_projects )
			);
			return;
		}

		$select.append(
			$( '<option>' )
				.val( '' )
				.text( ndizi_adminbar.labels.select_project )
		);

		// Group projects by client_name
		const groups = {};
		projectsData.forEach( function ( project ) {
			const clientName =
				project.client_name || ndizi_adminbar.labels.internal_client;
			if ( ! groups[ clientName ] ) {
				groups[ clientName ] = [];
			}
			groups[ clientName ].push( project );
		} );

		// Sort client names, keeping the internal client label at the bottom
		const internalLabel = ndizi_adminbar.labels.internal_client;
		const clientNames = Object.keys( groups ).sort( function ( a, b ) {
			if ( a === internalLabel ) {
				return 1;
			}
			if ( b === internalLabel ) {
				return -1;
			}
			return a.localeCompare( b );
		} );

		clientNames.forEach( function ( clientName ) {
			const $optgroup = $( '<optgroup>' ).attr( 'label', clientName );
			groups[ clientName ].forEach( function ( project ) {
				$optgroup.append(
					$( '<option>' ).val( project.id ).text( project.title )
				);
			} );
			$select.append( $optgroup );
		} );
	}

	/**
	 * Update the stats block with budget and logged hour information
	 */
	function updateStatsCard() {
		const $statsCard = $( '#ndizi-ab-stats-card' );
		if ( ! selectedProject ) {
			$statsCard.hide();
			return;
		}

		$statsCard.show();

		let totalSeconds = selectedProject.total_logged;
		let label = ndizi_adminbar.labels.stat_label_project;

		if ( selectedTask ) {
			totalSeconds = selectedTask.total_logged;
			label = ndizi_adminbar.labels.stat_label_task;
		}

		const hours = ( totalSeconds / 3600 ).toFixed( 2 );
		$( '#ndizi-ab-stat-logged' ).text( hours + 'h (' + label + ')' );

		const $budgetRow = $( '#ndizi-ab-stat-budget-row' );
		if (
			selectedProject.budget !== null &&
			selectedProject.budget !== undefined
		) {
			$( '#ndizi-ab-stat-budget' ).text(
				'$' +
					selectedProject.budget.toLocaleString( undefined, {
						minimumFractionDigits: 2,
						maximumFractionDigits: 2,
					} )
			);
			$budgetRow.show();
		} else {
			$budgetRow.hide();
		}
	}
} )( window.jQuery );
