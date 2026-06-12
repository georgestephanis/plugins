import './standalone-style.scss';
import { formatTime, createTimer } from '../shared/timer.js';

/* global ndizi_standalone, jQuery, Notification, navigator */
/* eslint-disable camelcase, no-alert, no-console */

jQuery( document ).ready( function ( $ ) {
	const cfg = ndizi_standalone;
	let projectsData = [];
	let selectedMode = 'timer';
	let idleNotificationSent = false;

	const clock = createTimer( function ( elapsed ) {
		$( '#timer-clock-display' ).text( formatTime( elapsed ) );

		if ( elapsed > 28800 ) {
			if ( ! idleNotificationSent ) {
				if (
					'Notification' in window &&
					Notification.permission === 'granted'
				) {
					new Notification( 'Ndizi Idle Timer Warning', {
						body: 'Your timer has been running for over 8 hours. Please check in or stop your timer.',
					} );
					idleNotificationSent = true;
				}
			}
		} else {
			idleNotificationSent = false;
		}
	} );

	// Register Service Worker for PWA compliance
	if ( 'serviceWorker' in navigator ) {
		navigator.serviceWorker
			.register( 'admin.php?ndizi-action=service-worker' )
			.catch( ( err ) => console.log( 'SW registration failed: ', err ) );
	}

	// PWA App Installation Handler
	let deferredPrompt;
	window.addEventListener( 'beforeinstallprompt', ( e ) => {
		e.preventDefault();
		deferredPrompt = e;
		$( '#pwa-install-banner' ).css( 'display', 'flex' );
	} );

	$( '#pwa-install-btn' ).on( 'click', function () {
		if ( deferredPrompt ) {
			deferredPrompt.prompt();
			deferredPrompt.userChoice.then( ( choiceResult ) => {
				if ( choiceResult.outcome === 'accepted' ) {
					console.log( 'User accepted the PWA install prompt' );
				}
				$( '#pwa-install-banner' ).hide();
				deferredPrompt = null;
			} );
		}
	} );

	// Request notification permission for idle warnings
	if ( 'Notification' in window && Notification.permission === 'default' ) {
		Notification.requestPermission();
	}

	function loadTrackerData() {
		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_get_tracker_data',
				nonce: cfg.nonce,
			},
			success( response ) {
				if (
					response.success &&
					response.data &&
					response.data.projects
				) {
					projectsData = response.data.projects;
					populateProjectDropdown();
				}
			},
		} );
	}

	function populateProjectDropdown() {
		const $select = $( '#project-select' );
		$select.empty();

		if ( projectsData.length === 0 ) {
			$select.append(
				$( '<option>', {
					value: '',
					text: cfg.labels.no_active_projects,
				} )
			);
			return;
		}

		$select.append(
			$( '<option>', { value: '', text: cfg.labels.select_project } )
		);

		const clientsMap = {};
		projectsData.forEach( function ( project ) {
			const client = project.client_name || cfg.labels.internal_client;
			if ( ! clientsMap[ client ] ) {
				clientsMap[ client ] = [];
			}
			clientsMap[ client ].push( project );
		} );

		for ( const clientName in clientsMap ) {
			const $optgroup = $( '<optgroup>', { label: clientName } );
			clientsMap[ clientName ].forEach( function ( project ) {
				$optgroup.append(
					$( '<option>', { value: project.id, text: project.title } )
				);
			} );
			$select.append( $optgroup );
		}

		validateFormState();
	}

	$( '#project-select' ).on( 'change', function () {
		const projectId = parseInt( $( this ).val(), 10 );
		const $taskGroup = $( '#task-select-group' );
		const $taskSelect = $( '#task-select' );

		$taskSelect.empty();
		$taskSelect.append(
			$( '<option>', { value: '0', text: cfg.labels.general_task } )
		);

		if ( ! projectId ) {
			$taskGroup.hide();
			validateFormState();
			return;
		}

		const project = projectsData.find(
			( p ) => parseInt( p.id, 10 ) === projectId
		);
		if ( project && project.tasks && project.tasks.length > 0 ) {
			project.tasks.forEach( function ( task ) {
				$taskSelect.append(
					$( '<option>', { value: task.id, text: task.title } )
				);
			} );
			$taskGroup.fadeIn( 200 );
		} else {
			$taskGroup.hide();
		}
		validateFormState();
	} );

	function validateFormState() {
		const projectId = $( '#project-select' ).val();
		const hasProject = projectId && projectId !== '';
		$( '#btn-start-timer' ).prop(
			'disabled',
			! ( selectedMode === 'timer' && hasProject )
		);
	}

	$( '.tab-btn' ).on( 'click', function () {
		$( '.tab-btn' ).removeClass( 'active' );
		$( this ).addClass( 'active' );

		const mode = $( this ).data( 'mode' );
		selectedMode = mode;

		if ( mode === 'timer' ) {
			$( '#panel-manual-mode' ).hide();
			$( '#panel-timer-mode' ).show();
		} else {
			$( '#panel-timer-mode' ).hide();
			$( '#panel-manual-mode' ).css( 'display', 'flex' );
		}
		validateFormState();
	} );

	$( '#btn-start-timer' ).on( 'click', function () {
		const projectId = $( '#project-select' ).val();
		if ( ! projectId ) {
			return;
		}

		const taskId = $( '#task-select' ).val() || 0;
		const description = $( '#desc-input' ).val();
		const billable = $( '#billable-check' ).is( ':checked' ) ? 1 : 0;
		const projectTitle = $( '#project-select option:selected' ).text();
		const taskTitle =
			taskId !== '0' ? $( '#task-select option:selected' ).text() : '';

		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_start_timer_action',
				project_id: projectId,
				task_id: taskId,
				description,
				billable,
				nonce: cfg.nonce,
			},
			success( response ) {
				if ( response.success ) {
					$( 'body' ).addClass( 'timer-running' );
					$( '#lbl-active-project' ).text( projectTitle );
					if ( taskTitle ) {
						$( '#lbl-active-task' ).text( taskTitle ).show();
					} else {
						$( '#lbl-active-task' ).hide();
					}
					$( '#lbl-active-desc' ).text( description || '' );

					clock.start( 0 );

					$( '#new-timer-view' ).hide();
					$( '#active-timer-view' ).fadeIn( 300 );
				} else {
					window.alert(
						response.data.message || 'Error starting timer'
					);
				}
			},
		} );
	} );

	$( '#btn-stop-timer' ).on( 'click', function () {
		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_stop_timer_action',
				nonce: cfg.nonce,
			},
			success( response ) {
				if ( response.success ) {
					clock.stop();
					$( 'body' ).removeClass( 'timer-running' );
					$( '#active-timer-view' ).hide();

					$( '#desc-input' ).val( '' );
					$( '#manual-hours' ).val( '' );
					$( '#manual-minutes' ).val( '' );

					$( '#new-timer-view' ).fadeIn( 300 );
					loadRecentLogs();
				} else {
					window.alert(
						response.data.message || 'Error stopping timer'
					);
				}
			},
		} );
	} );

	$( '#btn-save-manual' ).on( 'click', function () {
		const projectId = $( '#project-select' ).val();
		if ( ! projectId ) {
			window.alert( cfg.labels.please_select_project );
			return;
		}

		const h = parseInt( $( '#manual-hours' ).val(), 10 ) || 0;
		const m = parseInt( $( '#manual-minutes' ).val(), 10 ) || 0;
		const durationSeconds = h * 3600 + m * 60;
		if ( durationSeconds <= 0 ) {
			window.alert( cfg.labels.please_enter_duration );
			return;
		}

		const taskId = $( '#task-select' ).val() || 0;
		const description = $( '#desc-input' ).val();
		const billable = $( '#billable-check' ).is( ':checked' ) ? 1 : 0;

		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_log_time_manual_action',
				project_id: projectId,
				task_id: taskId,
				description,
				duration: durationSeconds,
				billable,
				nonce: cfg.nonce,
			},
			success( response ) {
				if ( response.success ) {
					$( '#manual-hours' ).val( '' );
					$( '#manual-minutes' ).val( '' );
					$( '#desc-input' ).val( '' );

					loadRecentLogs();
					window.alert( cfg.labels.entry_logged );
				} else {
					window.alert(
						response.data.message || 'Error saving time entry'
					);
				}
			},
		} );
	} );

	$( document ).on( 'click', '.btn-delete', function () {
		if ( ! window.confirm( cfg.labels.confirm_delete ) ) {
			return;
		}
		const $item = $( this ).closest( '.log-item' );
		const logId = $( this ).data( 'id' );

		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_delete_log_action',
				log_id: logId,
				nonce: cfg.nonce,
			},
			success( response ) {
				if ( response.success ) {
					$item.fadeOut( 300, function () {
						$( this ).remove();
						if (
							$( '#recent-logs-list' ).children().length === 0
						) {
							$( '#recent-logs-list' ).append(
								'<div class="empty-logs">' +
									$( '<span>' )
										.text( cfg.labels.no_entries )
										.html() +
									'</div>'
							);
						}
					} );
				} else {
					window.alert(
						response.data.message || 'Error deleting log entry'
					);
				}
			},
		} );
	} );

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function loadRecentLogs() {
		$.ajax( {
			url: cfg.ajax_url,
			method: 'POST',
			data: {
				action: 'ndizi_get_recent_user_logs',
				nonce: cfg.nonce,
			},
			success( response ) {
				const $list = $( '#recent-logs-list' );
				$list.empty();

				if (
					response.success &&
					response.data &&
					response.data.entries &&
					response.data.entries.length > 0
				) {
					response.data.entries.forEach( function ( entry ) {
						const taskText = entry.task
							? ' &bull; ' + escHtml( entry.task )
							: '';
						const descText = entry.description
							? escHtml( entry.description )
							: '<em>' +
							  escHtml( cfg.labels.no_description ) +
							  '</em>';
						const billableBadge = entry.billable
							? ' <span style="color: var(--color-emerald); font-weight: 800; font-size: 11px;">$</span>'
							: '';

						const itemHtml = `
							<div class="log-item">
								<div class="log-details">
									<div class="log-proj-task">${ escHtml( entry.project ) }${ taskText }</div>
									<div class="log-desc">${ descText }</div>
									<div class="log-meta">
										<span>${ escHtml( entry.time ) }</span>
										${ billableBadge }
									</div>
								</div>
								<div class="log-right">
									<div class="log-duration">${ escHtml( entry.duration ) }</div>
									<button type="button" class="btn-delete" data-id="${ escHtml( entry.id ) }">
										<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
									</button>
								</div>
							</div>
						`;
						$list.append( itemHtml );
					} );
				} else {
					$list.append(
						'<div class="empty-logs">' +
							$( '<span>' ).text( cfg.labels.no_entries ).html() +
							'</div>'
					);
				}
			},
		} );
	}

	// Initialize
	loadTrackerData();
	loadRecentLogs();
	if ( $( 'body' ).hasClass( 'timer-running' ) ) {
		clock.start( cfg.active_timer_seconds );
	}
} );
