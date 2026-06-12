( function () {
	'use strict';

		// DOM Elements
	const loader = document.getElementById( 'loader' );
	const loaderText = document.getElementById( 'loader-text' );
	const setupScreen = document.getElementById( 'setup-screen' );
	const trackerScreen = document.getElementById( 'tracker-screen' );

	// Setup elements
	const wpUrlInput = document.getElementById( 'wp-url' );
	const usernameInput = document.getElementById( 'username' );
	const appPasswordInput = document.getElementById( 'app-password' );
	const connectBtn = document.getElementById( 'connect-btn' );
	const setupError = document.getElementById( 'setup-error' );
	const cancelSetupBtn = document.getElementById( 'cancel-setup-btn' );

	// Tracker elements
	const projectSelect = document.getElementById( 'project-select' );
	const taskSelect = document.getElementById( 'task-select' );
	const descInput = document.getElementById( 'desc-input' );
	const toggleTimerBtn = document.getElementById( 'toggle-timer-btn' );
	const disconnectBtn = document.getElementById( 'disconnect-btn' );
	const timerDisplay = document.getElementById( 'timer-display' );
	const timerContainer = document.getElementById( 'timer-container' );
	const userDisplay = document.getElementById( 'user-display' );
	const viewDashboardLink = document.getElementById( 'view-dashboard-link' );
	const trackerError = document.getElementById( 'tracker-error' );
	const siteSelect = document.getElementById( 'site-select' );
	const addSiteBtn = document.getElementById( 'add-site-btn' );

	let activeTimerInterval = null;
	let timerStartTime = null;
	let allTasks = [];
	let sites = [];
	let activeSiteUrl = '';

	// Init
	document.addEventListener( 'DOMContentLoaded', () => {
		checkConnection();

		// Event listeners
		connectBtn.addEventListener( 'click', handleConnect );
		disconnectBtn.addEventListener( 'click', handleDisconnect );
		toggleTimerBtn.addEventListener( 'click', handleToggleTimer );
		projectSelect.addEventListener( 'change', filterTasksByProject );
		cancelSetupBtn.addEventListener( 'click', handleCancelSetup );
		addSiteBtn.addEventListener( 'click', handleAddSite );
		siteSelect.addEventListener( 'change', handleSwitchSite );
	} );

	function showLoader( text = 'Loading...' ) {
		loaderText.textContent = text;
		loader.style.display = 'block';
		setupScreen.classList.remove( 'active' );
		trackerScreen.classList.remove( 'active' );
	}

	function hideLoader() {
		loader.style.display = 'none';
	}

	function checkConnection() {
		showLoader( 'Checking connection...' );
		chrome.storage.local.get(
			[ 'sites', 'activeSiteUrl', 'wpUrl', 'username', 'authHeader' ],
			( data ) => {
				let currentSites = data.sites || [];
				let currentActiveSiteUrl = data.activeSiteUrl || '';

				// Migration of legacy single site credentials
				if ( currentSites.length === 0 && data.wpUrl && data.username && data.authHeader ) {
					const legacySite = {
						wpUrl: data.wpUrl,
						username: data.username,
						authHeader: data.authHeader
					};
					currentSites.push( legacySite );
					currentActiveSiteUrl = legacySite.wpUrl;
					
					// Save the migrated data
					chrome.storage.local.set( {
						sites: currentSites,
						activeSiteUrl: currentActiveSiteUrl
					} );
				}

				sites = currentSites;
				activeSiteUrl = currentActiveSiteUrl;

				if ( sites.length > 0 && activeSiteUrl ) {
					const activeSite = sites.find( s => s.wpUrl === activeSiteUrl );
					if ( activeSite ) {
						updateSiteSwitcherDropdown();
						testAndLoadTracker(
							activeSite.wpUrl,
							activeSite.username,
							activeSite.authHeader
						);
					} else {
						// Fallback to first site if active is missing
						activeSiteUrl = sites[0].wpUrl;
						chrome.storage.local.set( { activeSiteUrl } );
						updateSiteSwitcherDropdown();
						testAndLoadTracker(
							sites[0].wpUrl,
							sites[0].username,
							sites[0].authHeader
						);
					}
				} else {
					hideLoader();
					cancelSetupBtn.style.display = 'none';
					setupScreen.classList.add( 'active' );
				}
			}
		);
	}

	function updateSiteSwitcherDropdown() {
		siteSelect.innerHTML = '';
		sites.forEach( ( s ) => {
			const opt = document.createElement( 'option' );
			opt.value = s.wpUrl;
			// Clean domain name for presentation
			let displayName = s.wpUrl.replace( /https?:\/\//, '' );
			opt.textContent = `${ displayName } (${ s.username })`;
			if ( s.wpUrl === activeSiteUrl ) {
				opt.selected = true;
			}
			siteSelect.appendChild( opt );
		} );
	}

	function handleSwitchSite() {
		const selectedUrl = siteSelect.value;
		if ( ! selectedUrl || selectedUrl === activeSiteUrl ) return;

		const targetSite = sites.find( s => s.wpUrl === selectedUrl );
		if ( ! targetSite ) return;

		if ( activeTimerInterval ) {
			clearInterval( activeTimerInterval );
			activeTimerInterval = null;
		}

		activeSiteUrl = selectedUrl;
		chrome.storage.local.set( { activeSiteUrl }, () => {
			showLoader( `Switching to ${ targetSite.wpUrl }...` );
			testAndLoadTracker( targetSite.wpUrl, targetSite.username, targetSite.authHeader );
		} );
	}

	// Add Site opens setup view
	function handleAddSite() {
		wpUrlInput.value = '';
		usernameInput.value = '';
		appPasswordInput.value = '';
		setupError.style.display = 'none';

		setupScreen.classList.add( 'active' );
		trackerScreen.classList.remove( 'active' );
		cancelSetupBtn.style.display = 'block';
	}

	// Cancel/Back button
	function handleCancelSetup() {
		setupScreen.classList.remove( 'active' );
		trackerScreen.classList.add( 'active' );
	}

	function handleConnect() {
		let wpUrl = wpUrlInput.value.trim();
		const username = usernameInput.value.trim();
		const appPassword = appPasswordInput.value.trim();

		setupError.style.display = 'none';

		if ( ! wpUrl || ! username || ! appPassword ) {
			showSetupError( 'Please fill in all fields.' );
			return;
		}

		// Normalize URL
		if (
			! wpUrl.startsWith( 'http://' ) &&
			! wpUrl.startsWith( 'https://' )
		) {
			wpUrl = 'https://' + wpUrl;
		}
		if ( wpUrl.endsWith( '/' ) ) {
			wpUrl = wpUrl.slice( 0, -1 );
		}

		const authHeader = 'Basic ' + btoa( username + ':' + appPassword );

		showLoader( 'Connecting to ' + wpUrl + '...' );
		testAndLoadTracker( wpUrl, username, authHeader, true );
	}

	function showSetupError( message ) {
		setupError.textContent = message;
		setupError.style.display = 'block';
		setupScreen.classList.add( 'active' );
		hideLoader();
	}

	function showTrackerError( message ) {
		trackerError.textContent = message;
		trackerError.style.display = 'block';
		setTimeout( () => {
			trackerError.style.display = 'none';
		}, 5000 );
	}

	function handleDisconnect() {
		if ( activeTimerInterval ) {
			clearInterval( activeTimerInterval );
			activeTimerInterval = null;
		}

		// Remove the currently active site
		sites = sites.filter( s => s.wpUrl !== activeSiteUrl );

		if ( sites.length > 0 ) {
			// Switch to the first available site
			activeSiteUrl = sites[0].wpUrl;
			chrome.storage.local.set( { sites, activeSiteUrl }, () => {
				updateSiteSwitcherDropdown();
				showLoader( `Switching to ${ activeSiteUrl }...` );
				testAndLoadTracker( sites[0].wpUrl, sites[0].username, sites[0].authHeader );
			} );
		} else {
			// No sites left, purge storage completely
			chrome.storage.local.clear( () => {
				sites = [];
				activeSiteUrl = '';
				timerDisplay.textContent = '00:00:00';
				timerContainer.classList.remove( 'timer-active' );
				projectSelect.value = '';
				taskSelect.value = '';
				descInput.value = '';
				projectSelect.disabled = false;
				taskSelect.disabled = false;
				descInput.disabled = false;
				toggleTimerBtn.textContent = 'Start Timer';
				toggleTimerBtn.style.background = '#818cf8';

				cancelSetupBtn.style.display = 'none';
				trackerScreen.classList.remove( 'active' );
				setupScreen.classList.add( 'active' );
			} );
		}
	}

	function testAndLoadTracker( wpUrl, username, authHeader, save = false ) {
		fetch( `${ wpUrl }/wp-json/ndizi/v1/projects`, {
			method: 'GET',
			headers: {
				Authorization: authHeader,
			},
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error(
						'Authentication failed (HTTP ' + response.status + ')'
					);
				}
				return response.json();
			} )
			.then( ( projects ) => {
				if ( save ) {
					// Add or update the site in the list
					const existingIndex = sites.findIndex( s => s.wpUrl === wpUrl );
					const newSite = { wpUrl, username, authHeader };
					if ( existingIndex > -1 ) {
						sites[existingIndex] = newSite;
					} else {
						sites.push( newSite );
					}
					activeSiteUrl = wpUrl;

					chrome.storage.local.set(
						{ sites, activeSiteUrl },
						() => {
							updateSiteSwitcherDropdown();
							loadTrackerData(
								wpUrl,
								authHeader,
								username,
								projects
							);
						}
					);
				} else {
					loadTrackerData( wpUrl, authHeader, username, projects );
				}
			} )
			.catch( ( err ) => {
				console.error( err );
				if ( save ) {
					showSetupError( 'Failed to connect: ' + err.message );
				} else {
					// Saved credentials failed, redirect to login
					handleDisconnect();
				}
			} );
	}

	function loadTrackerData( wpUrl, authHeader, username, projects ) {
		userDisplay.textContent = `User: ${ username }`;
		viewDashboardLink.href = `${ wpUrl }/wp-admin/admin.php?page=ndizi-tracker-standalone`;

		// Populate projects dropdown
		projectSelect.innerHTML =
			'<option value="">-- Select Project --</option>';
		projects.forEach( ( project ) => {
			const opt = document.createElement( 'option' );
			opt.value = project.ID;
			opt.textContent = project.post_title;
			projectSelect.appendChild( opt );
		} );

		// Fetch tasks
		fetch( `${ wpUrl }/wp-json/ndizi/v1/tasks`, {
			method: 'GET',
			headers: {
				Authorization: authHeader,
			},
		} )
			.then( ( res ) => res.json() )
			.then( ( tasks ) => {
				allTasks = tasks;
				// Fetch active timer
				return fetch( `${ wpUrl }/wp-json/ndizi/v1/time/active`, {
					method: 'GET',
					headers: {
						Authorization: authHeader,
					},
				} );
			} )
			.then( ( res ) => res.json() )
			.then( ( activeTimer ) => {
				hideLoader();
				trackerScreen.classList.add( 'active' );

				if ( activeTimer && activeTimer.id ) {
					// Timer running!
					setupRunningTimer( activeTimer );
				} else {
					// No timer running
					setupIdleState();
				}
			} )
			.catch( ( err ) => {
				console.error( err );
				showTrackerError( 'Failed to load tracking data.' );
				hideLoader();
				trackerScreen.classList.add( 'active' );
			} );
	}

	function filterTasksByProject() {
		const projectId = projectSelect.value;
		taskSelect.innerHTML = '<option value="">-- Select Task --</option>';

		if ( ! projectId ) return;

		const filtered = allTasks.filter( ( t ) => {
			const taskProjId =
				t._ndizi_project_id || ( t.meta && t.meta._ndizi_project_id );
			return parseInt( taskProjId ) === parseInt( projectId );
		} );

		filtered.forEach( ( task ) => {
			const opt = document.createElement( 'option' );
			opt.value = task.ID;
			opt.textContent = task.post_title;
			taskSelect.appendChild( opt );
		} );
	}

	function setupRunningTimer( activeTimer ) {
		projectSelect.value = activeTimer.project_id;
		filterTasksByProject();
		taskSelect.value = activeTimer.task_id || '';
		descInput.value = activeTimer.description || '';

		// Lock inputs
		projectSelect.disabled = true;
		taskSelect.disabled = true;
		descInput.disabled = true;

		// Start ticker
		timerStartTime = new Date( activeTimer.start_time );
		timerContainer.classList.add( 'timer-active' );
		toggleTimerBtn.textContent = 'Stop Timer';
		toggleTimerBtn.style.background = 'var(--danger)';

		if ( activeTimerInterval ) {
			clearInterval( activeTimerInterval );
		}

		updateTicker();
		activeTimerInterval = setInterval( updateTicker, 1000 );
	}

	function setupIdleState() {
		if ( activeTimerInterval ) {
			clearInterval( activeTimerInterval );
		}
		timerDisplay.textContent = '00:00:00';
		timerContainer.classList.remove( 'timer-active' );

		projectSelect.disabled = false;
		taskSelect.disabled = false;
		descInput.disabled = false;

		toggleTimerBtn.textContent = 'Start Timer';
		toggleTimerBtn.style.background = 'var(--primary)';
	}

	function updateTicker() {
		if ( ! timerStartTime ) return;
		const now = new Date();
		// Google API times are UTC. Make sure timezone differences are handled correctly.
		// Both now and timerStartTime should be compared in milliseconds.
		const diffMs = now.getTime() - timerStartTime.getTime();
		if ( diffMs < 0 ) {
			timerDisplay.textContent = '00:00:00';
			return;
		}
		const diffSec = Math.floor( diffMs / 1000 );
		const h = Math.floor( diffSec / 3600 );
		const m = Math.floor( ( diffSec % 3600 ) / 60 );
		const s = diffSec % 60;

		timerDisplay.textContent =
			( h < 10 ? '0' + h : h ) +
			':' +
			( m < 10 ? '0' + m : m ) +
			':' +
			( s < 10 ? '0' + s : s );
	}

	function handleToggleTimer() {
		const activeSite = sites.find( s => s.wpUrl === activeSiteUrl );
		if ( ! activeSite ) return;

		const isRunning =
			timerContainer.classList.contains( 'timer-active' );

		if ( isRunning ) {
			// Stop timer
			toggleTimerBtn.disabled = true;
			toggleTimerBtn.textContent = 'Stopping...';

			fetch( `${ activeSite.wpUrl }/wp-json/ndizi/v1/time/stop`, {
				method: 'POST',
				headers: {
					Authorization: activeSite.authHeader,
				},
			} )
				.then( ( res ) => res.json() )
				.then( ( res ) => {
					toggleTimerBtn.disabled = false;
					if ( res.success || res.id ) {
						setupIdleState();
					} else {
						showTrackerError( 'Error stopping timer.' );
						setupRunningTimer( {
							project_id: projectSelect.value,
							task_id: taskSelect.value,
							description: descInput.value,
							start_time: timerStartTime.toISOString(),
						} );
					}
				} )
				.catch( ( err ) => {
					console.error( err );
					toggleTimerBtn.disabled = false;
					showTrackerError( 'Network error stopping timer.' );
				} );
		} else {
			// Start timer
			const projectId = projectSelect.value;
			const taskId = taskSelect.value || 0;
			const desc = descInput.value.trim();

			if ( ! projectId ) {
				showTrackerError(
					'Please select a project to start tracking.'
				);
				return;
			}

			toggleTimerBtn.disabled = true;
			toggleTimerBtn.textContent = 'Starting...';

			fetch( `${ activeSite.wpUrl }/wp-json/ndizi/v1/time/start`, {
				method: 'POST',
				headers: {
					Authorization: activeSite.authHeader,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( {
					project_id: parseInt( projectId ),
					task_id: parseInt( taskId ),
					description: desc,
					billable: true,
				} ),
			} )
				.then( ( res ) => {
					if ( ! res.ok ) {
						return res.json().then( ( errData ) => {
							throw new Error(
								errData.message || 'Error starting timer.'
							);
						} );
					}
					return res.json();
				} )
				.then( ( res ) => {
					toggleTimerBtn.disabled = false;
					if ( res.id ) {
						// Adjust start time to server-supplied time or current local time
						const serverTime = res.start_time
							? new Date( res.start_time )
							: new Date();
						setupRunningTimer( {
							id: res.id,
							project_id: projectId,
							task_id: taskId,
							description: desc,
							start_time: serverTime.toISOString(),
						} );
					} else {
						showTrackerError( 'Error starting timer.' );
						setupIdleState();
					}
				} )
				.catch( ( err ) => {
					console.error( err );
					toggleTimerBtn.disabled = false;
					showTrackerError(
						err.message || 'Network error starting timer.'
					);
					setupIdleState();
				} );
		}
	}
} )();
