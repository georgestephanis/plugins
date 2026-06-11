import './adminbar-style.scss';

/* global ndizi_adminbar */
/* eslint-disable camelcase, no-alert */

/**
 * Admin Bar Script for Ndizi Project Management
 *
 * @param {Object} $ jQuery instance.
 */
( function ( $ ) {
	'use strict';

	let timerInterval = null;
	let timerStartTs = 0;
	let projectsData = [];
	let selectedProject = null;
	let selectedTask = null;
	let hasLoadedData = false;

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

		// Prevent clicks inside the panel from closing the admin bar dropdown
		$panel.on( 'click', function ( e ) {
			e.stopPropagation();
		} );

		// Check if timer is active on load
		if ( $panel.hasClass( 'ndizi-timer-running' ) ) {
			const offset = parseInt( $panel.data( 'duration' ), 10 ) || 0;
			timerStartTs = Math.floor( Date.now() / 1000 ) - offset;
			startClockTicker( offset );
		}

		// Fetch project data when hovering or clicking the admin bar node for the first time
		$( '#wp-admin-bar-ndizi-time-tracker' ).on(
			'mouseenter click',
			function () {
				if (
					! hasLoadedData &&
					! $panel.hasClass( 'ndizi-timer-running' )
				) {
					loadTrackerData();
				}
			}
		);

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

			$btn.prop( 'disabled', true ).text( 'Starting...' );

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
						desc || 'No description'
					);

					// Start local ticking
					timerStartTs = Math.floor( Date.now() / 1000 );
					startClockTicker( 0 );
				} )
				.fail( function ( err ) {
					window.alert(
						err.message || ndizi_adminbar.labels.error_general
					);
					$btn.prop( 'disabled', false ).text( 'Start Timer' );
				} );
		} );

		// Stop Timer Event
		$( '#ndizi-ab-btn-stop' ).on( 'click', function ( e ) {
			e.preventDefault();
			const $btn = $( this );
			$btn.prop( 'disabled', true ).text( 'Stopping...' );

			wp.ajax
				.post( 'ndizi_stop_timer_action', {
					nonce: ndizi_adminbar.nonce,
				} )
				.done( function () {
					stopClockTicker();

					const $node = $( '#wp-admin-bar-ndizi-time-tracker' );
					$node.removeClass( 'ndizi-timer-active' );
					$node
						.find( '.ndizi-ab-label' )
						.removeClass( 'screen-reader-text' )
						.text( ndizi_adminbar.labels.timer_stopped );

					setTimeout( function () {
						$node
							.find( '.ndizi-ab-label' )
							.addClass( 'screen-reader-text' )
							.text( 'Log Time' );
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

		// Save Manual Entry Event
		$( '#ndizi-ab-btn-save-manual' ).on( 'click', function ( e ) {
			e.preventDefault();
			const projectId = $( '#ndizi-ab-project-select' ).val();

			if ( ! projectId ) {
				window.alert( 'Please select a project.' );
				return;
			}

			const h = parseInt( $( '#ndizi-ab-manual-hours' ).val(), 10 ) || 0;
			const m =
				parseInt( $( '#ndizi-ab-manual-minutes' ).val(), 10 ) || 0;
			const duration = h * 3600 + m * 60;

			if ( duration <= 0 ) {
				window.alert( 'Please enter a valid duration.' );
				return;
			}

			const $btn = $( this );
			const taskId = $( '#ndizi-ab-task-select' ).val() || 0;
			const desc = $( '#ndizi-ab-desc-input' ).val();
			const billable = $( '#ndizi-ab-billable-check' ).is( ':checked' )
				? 1
				: 0;

			$btn.prop( 'disabled', true ).text( 'Saving...' );

			wp.ajax
				.post( 'ndizi_log_time_manual_action', {
					project_id: projectId,
					task_id: taskId,
					description: desc,
					duration,
					billable,
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
							.text( 'Log Time' );
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
				$( '<option>' ).val( '' ).text( 'No active projects' )
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
			const clientName = project.client_name || 'Internal';
			if ( ! groups[ clientName ] ) {
				groups[ clientName ] = [];
			}
			groups[ clientName ].push( project );
		} );

		// Sort client names, keeping 'Internal' at the bottom
		const clientNames = Object.keys( groups ).sort( function ( a, b ) {
			if ( a === 'Internal' ) {
				return 1;
			}
			if ( b === 'Internal' ) {
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
		let label = 'Project: ';

		if ( selectedTask ) {
			totalSeconds = selectedTask.total_logged;
			label = 'Task: ';
		}

		const hours = ( totalSeconds / 3600 ).toFixed( 2 );
		$( '#ndizi-ab-stat-logged' ).text( hours + 'h (' + label + ')' );

		const $budgetRow = $( '#ndizi-ab-stat-budget-row' );
		if ( selectedProject.budget ) {
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

	/**
	 * Starts the localized timer clock ticker in the browser DOM
	 *
	 * @param {number} startOffset Current seconds value to start at.
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

		const updateTime = function ( diff ) {
			const formatted = formatTime( diff );
			$( '#ndizi-ab-ticker-clock' ).text( formatted );
			$( '#wp-admin-bar-ndizi-time-tracker' )
				.find( '.ndizi-ab-label' )
				.removeClass( 'screen-reader-text' )
				.text( formatted );
		};

		updateTime( startOffset );

		timerInterval = setInterval( function () {
			const now = Math.floor( Date.now() / 1000 );
			const diff = now - timerStartTs;
			updateTime( diff );
		}, 1000 );
	}

	/**
	 * Stops the localized timer interval
	 */
	function stopClockTicker() {
		if ( timerInterval ) {
			clearInterval( timerInterval );
			timerInterval = null;
		}
	}
} )( window.jQuery );
