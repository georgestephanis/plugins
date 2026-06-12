<?php
/**
 * Standalone PWA Time Tracker for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Standalone_Tracker {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_requests' ) );
		add_action( 'wp_ajax_ndizi_get_recent_user_logs', array( __CLASS__, 'ajax_get_recent_user_logs' ) );
	}

	/**
	 * Intercept early PWA/Standalone requests on admin_init
	 */
	public static function handle_requests() {
		// 1. Dynamic Web App Manifest
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ndizi-action'] ) && 'manifest' === $_GET['ndizi-action'] ) {
			self::serve_manifest();
			exit;
		}

		// 2. Service Worker File
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ndizi-action'] ) && 'service-worker' === $_GET['ndizi-action'] ) {
			self::serve_service_worker();
			exit;
		}

		// 3. Standalone Tracker Page View
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'ndizi-tracker-standalone' === $_GET['page'] ) {
			if ( ! Ndizi_Roles::current_user_can( 'ndizi_log_time' ) ) {
				wp_die( esc_html__( 'You do not have permission to log time.', 'ndizi-project-management' ) );
			}
			self::render_standalone_page();
			exit;
		}
	}

	/**
	 * Serve the manifest JSON
	 */
	private static function serve_manifest() {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$manifest = array(
			'name'             => __( 'Ndizi Time Tracker', 'ndizi-project-management' ),
			'short_name'       => __( 'Ndizi Tracker', 'ndizi-project-management' ),
			'description'      => __( 'Standalone distraction-free companion time tracker app.', 'ndizi-project-management' ),
			'start_url'        => admin_url( 'admin.php?page=ndizi-tracker-standalone' ),
			'display'          => 'standalone',
			'background_color' => '#0b0f19',
			'theme_color'      => '#4f46e5',
			'icons'            => array(
				array(
					'src'   => NDIZI_PLUGIN_URL . 'build/icon-192.png',
					'sizes' => '192x192',
					'type'  => 'image/png',
				),
				array(
					'src'   => NDIZI_PLUGIN_URL . 'build/icon-512.png',
					'sizes' => '512x512',
					'type'  => 'image/png',
				),
			),
		);

		echo wp_json_encode( $manifest );
	}

	/**
	 * Serve the Service Worker script
	 */
	private static function serve_service_worker() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$standalone_url = admin_url( 'admin.php?page=ndizi-tracker-standalone' );
		?>
		const CACHE_NAME = 'ndizi-tracker-v1';
		const ASSETS = [
			'<?php echo esc_js( $standalone_url ); ?>'
		];

		self.addEventListener('install', (event) => {
			event.waitUntil(
				caches.open(CACHE_NAME).then((cache) => {
					return cache.addAll(ASSETS).catch(() => {});
				})
			);
			self.skipWaiting();
		});

		self.addEventListener('activate', (event) => {
			event.waitUntil(
				caches.keys().then((keys) => {
					return Promise.all(
						keys.map((key) => {
							if (key !== CACHE_NAME) {
								return caches.delete(key);
							}
						})
					);
				})
			);
			self.clients.claim();
		});

		self.addEventListener('fetch', (event) => {
			// Don't intercept POST requests or admin-ajax.php calls
			if (event.request.method !== 'GET' || event.request.url.includes('admin-ajax.php')) {
				return;
			}
			event.respondWith(
				fetch(event.request)
					.catch(() => {
						return caches.match(event.request);
					})
			);
		});
		<?php
	}

	/**
	 * AJAX logic to fetch the current user's logs for today
	 */
	public static function ajax_get_recent_user_logs() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$user_id = get_current_user_id();
		$today   = current_time( 'Y-m-d' );
		$entries = Ndizi_DB::get_time_entries(
			array(
				'user_id'    => $user_id,
				'start_date' => $today,
				'end_date'   => $today,
				'number'     => 10,
			)
		);

		$response = array();
		foreach ( $entries as $entry ) {
			if ( is_null( $entry->end_time ) ) {
				continue; // skip active running timer
			}
			$project = get_post( $entry->project_id );
			$task    = $entry->task_id ? get_post( $entry->task_id ) : null;

			$h            = floor( $entry->duration / 3600 );
			$m            = floor( ( $entry->duration % 3600 ) / 60 );
			$duration_str = sprintf( '%02dh %02dm', $h, $m );

			$response[] = array(
				'id'          => $entry->id,
				'project'     => $project ? $project->post_title : __( 'Unknown Project', 'ndizi-project-management' ),
				'task'        => $task ? $task->post_title : '',
				'description' => $entry->description,
				'duration'    => $duration_str,
				'billable'    => (bool) $entry->billable,
				'time'        => date_i18n( get_option( 'time_format' ), strtotime( $entry->start_time ) ),
			);
		}

		wp_send_json_success( array( 'entries' => $response ) );
	}

	/**
	 * Output the standalone PWA tracker HTML page
	 */
	public static function render_standalone_page() {
		$user_id      = get_current_user_id();
		$active_timer = Ndizi_DB::get_active_timer( $user_id );
		$duration_sec = 0;

		if ( $active_timer ) {
			$start_ts     = strtotime( $active_timer->start_time );
			$now_ts       = strtotime( current_time( 'mysql' ) );
			$duration_sec = max( 0, $now_ts - $start_ts );
		}

		$h           = floor( $duration_sec / 3600 );
		$m           = floor( ( $duration_sec % 3600 ) / 60 );
		$s           = $duration_sec % 60;
		$ticker_text = sprintf( '%02d:%02d:%02d', $h, $m, $s );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$prefilled_desc = isset( $_GET['desc'] ) ? sanitize_text_field( wp_unslash( $_GET['desc'] ) ) : '';
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php esc_html_e( 'Ndizi Time Tracker', 'ndizi-project-management' ); ?></title>
			<link rel="manifest" href="admin.php?ndizi-action=manifest">
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
			<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
			<style>
				:root {
					--bg-radial: radial-gradient(circle at top, #1e1b4b 0%, #0b0f19 100%);
					--bg-glass: rgba(17, 24, 39, 0.7);
					--bg-glass-card: rgba(30, 41, 59, 0.5);
					--border-glass: rgba(255, 255, 255, 0.08);
					--border-glass-focus: rgba(99, 102, 241, 0.5);
					
					--text-primary: #f8fafc;
					--text-secondary: #94a3b8;
					--text-muted: #64748b;
					
					--color-indigo: #6366f1;
					--color-indigo-glow: rgba(99, 102, 241, 0.25);
					--color-yellow: #eab308;
					--color-yellow-glow: rgba(234, 179, 8, 0.2);
					--color-red: #ef4444;
					--color-red-glow: rgba(239, 68, 68, 0.25);
					--color-emerald: #10b981;
				}

				* {
					box-sizing: border-box;
					margin: 0;
					padding: 0;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
				}

				body {
					background: var(--bg-radial);
					color: var(--text-primary);
					font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
					min-height: 100vh;
					display: flex;
					flex-direction: column;
					align-items: center;
					justify-content: flex-start;
					padding: 24px 16px;
					overflow-y: auto;
				}

				/* Custom Thin Scrollbar */
				::-webkit-scrollbar {
					width: 6px;
				}
				::-webkit-scrollbar-track {
					background: transparent;
				}
				::-webkit-scrollbar-thumb {
					background: rgba(255, 255, 255, 0.1);
					border-radius: 10px;
				}
				::-webkit-scrollbar-thumb:hover {
					background: rgba(255, 255, 255, 0.2);
				}

				.app-container {
					width: 100%;
					max-width: 380px;
					display: flex;
					flex-direction: column;
					gap: 20px;
				}

				/* Glassmorphism Card Wrapper */
				.glass-card {
					background: var(--bg-glass);
					backdrop-filter: blur(20px);
					-webkit-backdrop-filter: blur(20px);
					border: 1px solid var(--border-glass);
					border-radius: 20px;
					padding: 24px;
					box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
				}

				/* App Header */
				.app-header {
					display: flex;
					align-items: center;
					justify-content: space-between;
					margin-bottom: 4px;
				}
				.app-brand {
					display: flex;
					align-items: center;
					gap: 10px;
				}
				.app-logo {
					width: 32px;
					height: 32px;
					color: var(--color-yellow);
					filter: drop-shadow(0 0 8px var(--color-yellow-glow));
				}
				.app-logo path {
					fill: none;
					stroke: currentColor;
					stroke-width: 2.2;
				}
				.app-title {
					font-family: 'Outfit', sans-serif;
					font-size: 20px;
					font-weight: 700;
					letter-spacing: -0.5px;
					background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
				}
				.app-status {
					font-size: 11px;
					background: rgba(99, 102, 241, 0.15);
					color: var(--color-indigo);
					border: 1px solid rgba(99, 102, 241, 0.3);
					padding: 3px 8px;
					border-radius: 12px;
					font-weight: 600;
					letter-spacing: 0.5px;
					text-transform: uppercase;
				}

				/* Install App Banner */
				.install-banner {
					display: none;
					background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.2) 100%);
					border: 1px solid rgba(99, 102, 241, 0.3);
					border-radius: 14px;
					padding: 12px 16px;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
				}
				.install-text {
					font-size: 13px;
					color: var(--text-primary);
					font-weight: 500;
				}
				.install-btn {
					background: var(--color-indigo);
					color: white;
					border: none;
					padding: 6px 12px;
					border-radius: 8px;
					font-size: 12px;
					font-weight: 600;
					cursor: pointer;
					transition: all 0.2s;
				}
				.install-btn:hover {
					background: #4f46e5;
					box-shadow: 0 0 10px rgba(99, 102, 241, 0.4);
				}

				/* Mode Tabs */
				.mode-tabs {
					display: flex;
					background: rgba(0, 0, 0, 0.2);
					border-radius: 10px;
					padding: 3px;
					margin-bottom: 8px;
				}
				.tab-btn {
					flex: 1;
					background: transparent;
					border: none;
					color: var(--text-secondary);
					padding: 8px;
					border-radius: 8px;
					font-size: 13px;
					font-weight: 600;
					cursor: pointer;
					transition: all 0.2s;
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 6px;
				}
				.tab-btn svg {
					width: 16px;
					height: 16px;
				}
				.tab-btn.active {
					background: var(--bg-glass-card);
					color: var(--text-primary);
					box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
				}

				/* Form Controls */
				.form-group {
					display: flex;
					flex-direction: column;
					gap: 6px;
					margin-bottom: 16px;
				}
				.form-label {
					font-size: 11px;
					font-weight: 600;
					color: var(--text-secondary);
					text-transform: uppercase;
					letter-spacing: 0.5px;
				}
				.form-select, .form-input {
					width: 100%;
					background: rgba(0, 0, 0, 0.25);
					border: 1px solid var(--border-glass);
					color: var(--text-primary);
					border-radius: 10px;
					padding: 11px 14px;
					font-size: 14px;
					font-family: inherit;
					outline: none;
					transition: all 0.2s;
				}
				.form-select:focus, .form-input:focus {
					border-color: var(--border-glass-focus);
					box-shadow: 0 0 0 2px var(--color-indigo-glow);
				}
				select.form-select {
					appearance: none;
					-webkit-appearance: none;
					background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
					background-repeat: no-repeat;
					background-position: right 14px center;
					background-size: 16px;
					padding-right: 40px;
				}
				select.form-select option {
					background: #0f172a;
					color: var(--text-primary);
				}

				/* Billable Toggle */
				.toggle-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					padding: 4px 0;
					margin-bottom: 20px;
				}
				.toggle-label-text {
					font-size: 13px;
					font-weight: 500;
					color: var(--text-secondary);
				}
				.switch {
					position: relative;
					display: inline-block;
					width: 44px;
					height: 24px;
				}
				.switch input {
					opacity: 0;
					width: 0;
					height: 0;
				}
				.slider {
					position: absolute;
					cursor: pointer;
					top: 0; left: 0; right: 0; bottom: 0;
					background-color: rgba(255, 255, 255, 0.1);
					transition: .3s;
					border-radius: 24px;
					border: 1px solid var(--border-glass);
				}
				.slider:before {
					position: absolute;
					content: "";
					height: 16px;
					width: 16px;
					left: 3px;
					bottom: 3px;
					background-color: var(--text-secondary);
					transition: .3s;
					border-radius: 50%;
				}
				input:checked + .slider {
					background-color: var(--color-indigo);
					border-color: rgba(99, 102, 241, 0.4);
				}
				input:checked + .slider:before {
					transform: translateX(20px);
					background-color: white;
				}

				/* Buttons */
				.btn {
					width: 100%;
					border: none;
					border-radius: 12px;
					padding: 13px 18px;
					font-size: 14px;
					font-weight: 600;
					cursor: pointer;
					transition: all 0.25s;
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 8px;
				}
				.btn svg {
					width: 18px;
					height: 18px;
				}
				.btn-primary {
					background: var(--color-indigo);
					color: white;
					box-shadow: 0 4px 14px var(--color-indigo-glow);
				}
				.btn-primary:hover:not(:disabled) {
					background: #4f46e5;
					transform: translateY(-1px);
					box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
				}
				.btn-primary:disabled {
					background: rgba(255, 255, 255, 0.05);
					color: var(--text-muted);
					cursor: not-allowed;
					box-shadow: none;
				}
				.btn-danger {
					background: var(--color-red);
					color: white;
					box-shadow: 0 4px 14px var(--color-red-glow);
				}
				.btn-danger:hover {
					background: #dc2626;
					transform: translateY(-1px);
					box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
				}

				/* Manual Log Duration Block */
				.manual-panel {
					display: none;
					flex-direction: column;
					gap: 12px;
				}
				.duration-inputs {
					display: flex;
					align-items: center;
					justify-content: center;
					gap: 12px;
					background: rgba(0, 0, 0, 0.15);
					padding: 12px;
					border-radius: 12px;
					border: 1px solid var(--border-glass);
				}
				.duration-col {
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 4px;
					width: 70px;
				}
				.duration-col input {
					width: 100%;
					background: rgba(0, 0, 0, 0.3);
					border: 1px solid var(--border-glass);
					color: white;
					border-radius: 8px;
					text-align: center;
					padding: 8px;
					font-size: 18px;
					font-weight: 700;
					outline: none;
				}
				.duration-col input:focus {
					border-color: var(--border-glass-focus);
				}
				.duration-col span {
					font-size: 10px;
					color: var(--text-secondary);
					font-weight: 600;
					text-transform: uppercase;
				}
				.duration-sep {
					font-size: 24px;
					font-weight: 700;
					color: var(--text-muted);
					margin-top: -16px;
				}

				/* Running Clock Animation */
				.active-panel {
					display: flex;
					flex-direction: column;
					gap: 16px;
					align-items: center;
				}
				.active-header {
					display: flex;
					flex-direction: column;
					align-items: center;
					gap: 6px;
					width: 100%;
				}
				.active-meta {
					display: flex;
					flex-wrap: wrap;
					gap: 6px;
					justify-content: center;
				}
				.badge {
					font-size: 11px;
					font-weight: 600;
					padding: 3px 9px;
					border-radius: 12px;
				}
				.badge-project {
					background: rgba(99, 102, 241, 0.15);
					color: #a5b4fc;
					border: 1px solid rgba(99, 102, 241, 0.3);
				}
				.badge-task {
					background: rgba(255, 255, 255, 0.05);
					color: var(--text-secondary);
					border: 1px solid var(--border-glass);
				}
				.active-desc {
					font-size: 13px;
					color: var(--text-secondary);
					text-align: center;
					font-style: italic;
					margin-top: 4px;
					word-break: break-word;
				}
				.ticker-clock {
					font-family: 'Outfit', sans-serif;
					font-size: 44px;
					font-weight: 700;
					letter-spacing: 1px;
					background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
					text-shadow: 0 0 15px rgba(255, 255, 255, 0.1);
					margin: 6px 0;
					font-variant-numeric: tabular-nums;
				}
				.timer-running .ticker-clock {
					background: linear-gradient(135deg, var(--color-yellow) 0%, #fbbf24 100%);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
					animation: pulse-glow 2.5s infinite ease-in-out;
				}
				@keyframes pulse-glow {
					0%, 100% { text-shadow: 0 0 10px rgba(234, 179, 8, 0.1); opacity: 0.95; }
					50% { text-shadow: 0 0 25px rgba(234, 179, 8, 0.55); opacity: 1; }
				}

				/* Recent Logs List */
				.recent-section {
					display: flex;
					flex-direction: column;
					gap: 12px;
				}
				.section-title-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
				}
				.section-title {
					font-size: 12px;
					font-weight: 700;
					color: var(--text-secondary);
					text-transform: uppercase;
					letter-spacing: 0.5px;
				}
				.recent-list {
					display: flex;
					flex-direction: column;
					gap: 10px;
					max-height: 180px;
					overflow-y: auto;
					padding-right: 4px;
				}
				.log-item {
					background: rgba(0, 0, 0, 0.18);
					border: 1px solid var(--border-glass);
					border-radius: 12px;
					padding: 12px;
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 12px;
					transition: all 0.2s;
				}
				.log-item:hover {
					background: rgba(0, 0, 0, 0.25);
					border-color: rgba(255, 255, 255, 0.12);
				}
				.log-details {
					display: flex;
					flex-direction: column;
					gap: 3px;
					flex: 1;
					min-width: 0;
				}
				.log-proj-task {
					font-size: 12px;
					font-weight: 600;
					color: #a5b4fc;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
				.log-desc {
					font-size: 12px;
					color: var(--text-secondary);
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
				.log-meta {
					font-size: 10px;
					color: var(--text-muted);
					display: flex;
					align-items: center;
					gap: 6px;
				}
				.log-right {
					display: flex;
					align-items: center;
					gap: 12px;
				}
				.log-duration {
					font-size: 14px;
					font-weight: 700;
					color: var(--text-primary);
					font-variant-numeric: tabular-nums;
				}
				.btn-delete {
					background: transparent;
					border: none;
					color: var(--text-muted);
					cursor: pointer;
					padding: 4px;
					border-radius: 6px;
					transition: all 0.2s;
					display: flex;
					align-items: center;
					justify-content: center;
				}
				.btn-delete:hover {
					color: var(--color-red);
					background: rgba(239, 68, 68, 0.1);
				}
				.btn-delete svg {
					width: 15px;
					height: 15px;
				}
				.empty-logs {
					text-align: center;
					padding: 24px;
					font-size: 12px;
					color: var(--text-muted);
					border: 1px dashed var(--border-glass);
					border-radius: 12px;
				}

				/* Footer Branding */
				.app-footer {
					text-align: center;
					margin-top: 8px;
				}
				.footer-link {
					color: var(--text-muted);
					text-decoration: none;
					font-size: 11px;
					font-weight: 500;
					transition: color 0.2s;
				}
				.footer-link:hover {
					color: var(--text-secondary);
				}

				@media (max-width: 480px) {
					body {
						padding: 12px 8px;
					}
					.app-container {
						max-width: 100%;
						gap: 16px;
					}
					.glass-card {
						padding: 20px 16px;
					}
					.timer-display {
						font-size: 40px !important;
					}
				}
			</style>
		</head>
		<body class="<?php echo $active_timer ? 'timer-running' : ''; ?>">

			<div class="app-container">
				
				<!-- PWA Install Prompt Banner -->
				<div class="install-banner" id="pwa-install-banner">
					<div class="install-text"><?php esc_html_e( 'Install Ndizi Time Tracker app for distraction-free access', 'ndizi-project-management' ); ?></div>
					<button class="install-btn" id="pwa-install-btn"><?php esc_html_e( 'Install', 'ndizi-project-management' ); ?></button>
				</div>

				<div class="glass-card">
					<!-- Header -->
					<div class="app-header">
						<div class="app-brand">
							<svg xmlns="http://www.w3.org/2000/svg" class="app-logo" viewBox="0 0 24 24">
								<path d="M20 6v-2a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v2a9.09 9.09 0 0 1 -4 8.08c-2 1.31 -5 1.57 -7 1.59a2 2 0 0 0 -2 2a2 2 0 0 0 1.16 1.81c2.69 1.2 9.46 3.44 14.35 -1.66c4.49 -4.74 1.49 -11.82 1.49 -11.82" />
							</svg>
							<div class="app-title"><?php esc_html_e( 'Ndizi PM', 'ndizi-project-management' ); ?></div>
						</div>
						<div class="app-status"><?php esc_html_e( 'App Window', 'ndizi-project-management' ); ?></div>
					</div>
				</div>

				<!-- Running Tracker Panel -->
				<div class="glass-card active-timer-section" id="active-timer-view" style="<?php echo $active_timer ? '' : 'display: none;'; ?>">
					<div class="active-panel">
						<div class="active-header">
							<div class="active-meta">
								<span class="badge badge-project" id="lbl-active-project">
									<?php echo $active_timer ? esc_html( get_the_title( $active_timer->project_id ) ) : ''; ?>
								</span>
								<span class="badge badge-task" id="lbl-active-task" style="<?php echo ( $active_timer && $active_timer->task_id ) ? '' : 'display: none;'; ?>">
									<?php echo ( $active_timer && $active_timer->task_id ) ? esc_html( get_the_title( $active_timer->task_id ) ) : ''; ?>
								</span>
							</div>
							<div class="active-desc" id="lbl-active-desc">
								<?php echo $active_timer ? esc_html( $active_timer->description ) : ''; ?>
							</div>
						</div>

						<div class="ticker-clock" id="timer-clock-display"><?php echo esc_html( $ticker_text ); ?></div>

						<button type="button" class="btn btn-danger" id="btn-stop-timer">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
							<?php esc_html_e( 'Stop Tracker', 'ndizi-project-management' ); ?>
						</button>
					</div>
				</div>

				<!-- Inactive Log Form Panel -->
				<div class="glass-card new-timer-section" id="new-timer-view" style="<?php echo $active_timer ? 'display: none;' : ''; ?>">
					
					<!-- Tabs -->
					<div class="mode-tabs">
						<button type="button" class="tab-btn active" id="tab-timer" data-mode="timer">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
							<?php esc_html_e( 'Timer', 'ndizi-project-management' ); ?>
						</button>
						<button type="button" class="tab-btn" id="tab-manual" data-mode="manual">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
							<?php esc_html_e( 'Manual', 'ndizi-project-management' ); ?>
						</button>
					</div>

					<!-- Form Inputs -->
					<div class="form-group" style="margin-top: 14px;">
						<label class="form-label"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label>
						<select id="project-select" class="form-select">
							<option value=""><?php esc_html_e( 'Loading projects...', 'ndizi-project-management' ); ?></option>
						</select>
					</div>

					<div class="form-group" id="task-select-group" style="display: none;">
						<label class="form-label"><?php esc_html_e( 'Task', 'ndizi-project-management' ); ?></label>
						<select id="task-select" class="form-select">
							<option value="0"><?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?></option>
						</select>
					</div>

					<div class="form-group">
						<label class="form-label"><?php esc_html_e( 'Activity Description', 'ndizi-project-management' ); ?></label>
						<input type="text" id="desc-input" class="form-input" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" value="<?php echo esc_attr( $prefilled_desc ); ?>" maxlength="255">
					</div>

					<div class="toggle-row">
						<span class="toggle-label-text"><?php esc_html_e( 'Billable Time', 'ndizi-project-management' ); ?></span>
						<label class="switch">
							<input type="checkbox" id="billable-check" value="1" checked>
							<span class="slider"></span>
						</label>
					</div>

					<!-- Timer Logger Mode Action -->
					<div id="panel-timer-mode">
						<button type="button" class="btn btn-primary" id="btn-start-timer" disabled>
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
							<?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
						</button>
					</div>

					<!-- Manual Logger Mode Actions -->
					<div id="panel-manual-mode" class="manual-panel">
						<div class="duration-inputs">
							<div class="duration-col">
								<input type="number" id="manual-hours" min="0" placeholder="0">
								<span><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></span>
							</div>
							<div class="duration-sep">:</div>
							<div class="duration-col">
								<input type="number" id="manual-minutes" min="0" max="59" placeholder="00">
								<span><?php esc_html_e( 'Min', 'ndizi-project-management' ); ?></span>
							</div>
						</div>
						<button type="button" class="btn btn-primary" style="margin-top: 8px;" id="btn-save-manual">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
							<?php esc_html_e( 'Log Manual Entry', 'ndizi-project-management' ); ?>
						</button>
					</div>

				</div>

				<!-- Today's Logged List -->
				<div class="glass-card recent-section">
					<div class="section-title-row">
						<div class="section-title"><?php esc_html_e( 'Logged Today', 'ndizi-project-management' ); ?></div>
					</div>
					<div class="recent-list" id="recent-logs-list">
						<div class="empty-logs"><?php esc_html_e( 'No entries recorded today.', 'ndizi-project-management' ); ?></div>
					</div>
				</div>

				<!-- Footer branding -->
				<div class="app-footer">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-pm' ) ); ?>" class="footer-link">
						&larr; <?php esc_html_e( 'Back to WordPress Dashboard', 'ndizi-project-management' ); ?>
					</a>
				</div>

			</div>

			<?php
			// Print WordPress bundled jQuery script
			wp_print_scripts( array( 'jquery' ) );
			?>
			<script>
				// Standard AJAX parameters config
				const ndizi_ajax = {
					url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
					nonce: '<?php echo esc_js( wp_create_nonce( 'ndizi-admin-nonce' ) ); ?>'
				};

				jQuery(document).ready(function($) {
					let activeTimerSeconds = <?php echo intval( $duration_sec ); ?>;
					let clockInterval = null;
					let projectsData = [];
					let selectedMode = 'timer';

					// Register Service Worker for PWA compliance
					if ('serviceWorker' in navigator) {
						navigator.serviceWorker.register('admin.php?ndizi-action=service-worker')
							.then(() => console.log('Ndizi PWA Service Worker Registered'))
							.catch((err) => console.log('SW registration failed: ', err));
					}

					// PWA App Installation Handler
					let deferredPrompt;
					window.addEventListener('beforeinstallprompt', (e) => {
						e.preventDefault();
						deferredPrompt = e;
						$('#pwa-install-banner').css('display', 'flex');
					});

					$('#pwa-install-btn').on('click', function() {
						if (deferredPrompt) {
							deferredPrompt.prompt();
							deferredPrompt.userChoice.then((choiceResult) => {
								if (choiceResult.outcome === 'accepted') {
									console.log('User accepted the PWA install prompt');
								}
								$('#pwa-install-banner').hide();
								deferredPrompt = null;
							});
						}
					});

					// Load projects data
					function loadTrackerData() {
						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_get_tracker_data',
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								if (response.success && response.data && response.data.projects) {
									projectsData = response.data.projects;
									populateProjectDropdown();
								} else {
									console.error('Failed to load tracker projects');
								}
							}
						});
					}

					// Populate Project Dropdown (optgroup client name)
					function populateProjectDropdown() {
						const $select = $('#project-select');
						$select.empty();

						if (projectsData.length === 0) {
							$select.append($('<option>', {
								value: '',
								text: '<?php esc_html_e( 'No active projects found.', 'ndizi-project-management' ); ?>'
							}));
							return;
						}

						$select.append($('<option>', {
							value: '',
							text: '<?php esc_html_e( '-- Select Project --', 'ndizi-project-management' ); ?>'
						}));

						// Group projects by client_name
						const clientsMap = {};
						projectsData.forEach(function(project) {
							const client = project.client_name || '<?php esc_html_e( 'Internal', 'ndizi-project-management' ); ?>';
							if (!clientsMap[client]) {
								clientsMap[client] = [];
							}
							clientsMap[client].push(project);
						});

						for (const clientName in clientsMap) {
							const $optgroup = $('<optgroup>', { label: clientName });
							clientsMap[clientName].forEach(function(project) {
								$optgroup.append($('<option>', {
									value: project.id,
									text: project.title
								}));
							});
							$select.append($optgroup);
						}

						// Enable inputs if timer mode is active
						validateFormState();
					}

					// Watch project change to fill tasks
					$('#project-select').on('change', function() {
						const projectId = parseInt($(this).val());
						const $taskGroup = $('#task-select-group');
						const $taskSelect = $('#task-select');

						$taskSelect.empty();
						$taskSelect.append($('<option>', {
							value: '0',
							text: '<?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?>'
						}));

						if (!projectId) {
							$taskGroup.hide();
							validateFormState();
							return;
						}

						const project = projectsData.find(p => p.id === projectId);
						if (project && project.tasks && project.tasks.length > 0) {
							project.tasks.forEach(function(task) {
								$taskSelect.append($('<option>', {
									value: task.id,
									text: task.title
								}));
							});
							$taskGroup.fadeIn(200);
						} else {
							$taskGroup.hide();
						}
						validateFormState();
					});

					// Validate whether start button should be enabled
					function validateFormState() {
						const projectId = $('#project-select').val();
						const hasProject = projectId && projectId !== '';
						
						// Start Timer button is only enabled in timer mode when a project is selected
						if (selectedMode === 'timer' && hasProject) {
							$('#btn-start-timer').prop('disabled', false);
						} else {
							$('#btn-start-timer').prop('disabled', true);
						}
					}

					// Mode switching
					$('.tab-btn').on('click', function() {
						$('.tab-btn').removeClass('active');
						$(this).addClass('active');

						const mode = $(this).data('mode');
						selectedMode = mode;

						if (mode === 'timer') {
							$('#panel-manual-mode').hide();
							$('#panel-timer-mode').show();
						} else {
							$('#panel-timer-mode').hide();
							$('#panel-manual-mode').css('display', 'flex');
						}
						validateFormState();
					});

					// Start Timer Click
					$('#btn-start-timer').on('click', function() {
						const projectId = $('#project-select').val();
						const taskId = $('#task-select').val() || 0;
						const description = $('#desc-input').val();
						const billable = $('#billable-check').is(':checked') ? 1 : 0;

						if (!projectId) return;

						const projectTitle = $('#project-select option:selected').text();
						const taskTitle = taskId !== '0' ? $('#task-select option:selected').text() : '';

						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_start_timer_action',
								project_id: projectId,
								task_id: taskId,
								description: description,
								billable: billable,
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								if (response.success) {
									// Update views
									$('body').addClass('timer-running');
									$('#lbl-active-project').text(projectTitle);
									if (taskTitle) {
										$('#lbl-active-task').text(taskTitle).show();
									} else {
										$('#lbl-active-task').hide();
									}
									$('#lbl-active-desc').text(description || '');
									
									activeTimerSeconds = 0;
									updateClockText();
									
									$('#new-timer-view').hide();
									$('#active-timer-view').fadeIn(300);

									startLiveClock();
								} else {
									alert(response.data.message || 'Error starting timer');
								}
							}
						});
					});

					// Stop Timer Click
					$('#btn-stop-timer').on('click', function() {
						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_stop_timer_action',
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								if (response.success) {
									stopLiveClock();
									$('body').removeClass('timer-running');
									$('#active-timer-view').hide();
									
									// Reset inputs
									$('#desc-input').val('');
									$('#manual-hours').val('');
									$('#manual-minutes').val('');
									
									$('#new-timer-view').fadeIn(300);
									loadRecentLogs();
								} else {
									alert(response.data.message || 'Error stopping timer');
								}
							}
						});
					});

					// Log Manual Entry Click
					$('#btn-save-manual').on('click', function() {
						const projectId = $('#project-select').val();
						const taskId = $('#task-select').val() || 0;
						const description = $('#desc-input').val();
						const billable = $('#billable-check').is(':checked') ? 1 : 0;
						
						const h = parseInt($('#manual-hours').val()) || 0;
						const m = parseInt($('#manual-minutes').val()) || 0;
						const durationSeconds = (h * 3600) + (m * 60);

						if (!projectId) {
							alert('<?php esc_html_e( 'Please select a project.', 'ndizi-project-management' ); ?>');
							return;
						}

						if (durationSeconds <= 0) {
							alert('<?php esc_html_e( 'Please specify hours or minutes.', 'ndizi-project-management' ); ?>');
							return;
						}

						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_log_time_manual_action',
								project_id: projectId,
								task_id: taskId,
								description: description,
								duration: durationSeconds,
								billable: billable,
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								if (response.success) {
									// Reset duration fields
									$('#manual-hours').val('');
									$('#manual-minutes').val('');
									$('#desc-input').val('');
									
									loadRecentLogs();
									alert('<?php esc_html_e( 'Time entry logged successfully!', 'ndizi-project-management' ); ?>');
								} else {
									alert(response.data.message || 'Error saving time entry');
								}
							}
						});
					});

					// Delete Entry Click
					$(document).on('click', '.btn-delete', function() {
						if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this time entry?', 'ndizi-project-management' ) ); ?>')) {
							return;
						}
						const $item = $(this).closest('.log-item');
						const logId = $(this).data('id');

						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_delete_log_action',
								log_id: logId,
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								if (response.success) {
									$item.fadeOut(300, function() {
										$(this).remove();
										if ($('#recent-logs-list').children().length === 0) {
											$('#recent-logs-list').append('<div class="empty-logs"><?php esc_html_e( 'No entries recorded today.', 'ndizi-project-management' ); ?></div>');
										}
									});
								} else {
									alert(response.data.message || 'Error deleting log entry');
								}
							}
						});
					});

					// Load Recent Logs
					function loadRecentLogs() {
						$.ajax({
							url: ndizi_ajax.url,
							method: 'POST',
							data: {
								action: 'ndizi_get_recent_user_logs',
								nonce: ndizi_ajax.nonce
							},
							success: function(response) {
								const $list = $('#recent-logs-list');
								$list.empty();

								if (response.success && response.data && response.data.entries && response.data.entries.length > 0) {
									// Escape a string for safe insertion as HTML text content.
									function escHtml(str) {
										return String(str)
											.replace(/&/g, '&amp;')
											.replace(/</g, '&lt;')
											.replace(/>/g, '&gt;')
											.replace(/"/g, '&quot;')
											.replace(/'/g, '&#039;');
									}

									response.data.entries.forEach(function(entry) {
										const taskText = entry.task ? ' &bull; ' + escHtml(entry.task) : '';
										const descText = entry.description
											? escHtml(entry.description)
											: '<em><?php esc_html_e( 'No description', 'ndizi-project-management' ); ?></em>';
										const billableBadge = entry.billable ? ' <span style="color: var(--color-emerald); font-weight: 800; font-size: 11px;">$</span>' : '';

										const itemHtml = `
											<div class="log-item">
												<div class="log-details">
													<div class="log-proj-task">${escHtml(entry.project)}${taskText}</div>
													<div class="log-desc">${descText}</div>
													<div class="log-meta">
														<span>${escHtml(entry.time)}</span>
														${billableBadge}
													</div>
												</div>
												<div class="log-right">
													<div class="log-duration">${escHtml(entry.duration)}</div>
													<button type="button" class="btn-delete" data-id="${escHtml(entry.id)}">
														<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
													</button>
												</div>
											</div>
										`;
										$list.append(itemHtml);
									});
								} else {
									$list.append('<div class="empty-logs"><?php esc_html_e( 'No entries recorded today.', 'ndizi-project-management' ); ?></div>');
								}
							}
						});
					}

					// Update clock text
					function updateClockText() {
						const h = Math.floor(activeTimerSeconds / 3600);
						const m = Math.floor((activeTimerSeconds % 3600) / 60);
						const s = activeTimerSeconds % 60;
						const timeStr = [
							h.toString().padStart(2, '0'),
							m.toString().padStart(2, '0'),
							s.toString().padStart(2, '0')
						].join(':');
						$('#timer-clock-display').text(timeStr);
					}

					// Start Live Clock
					function startLiveClock() {
						if (clockInterval) clearInterval(clockInterval);
						clockInterval = setInterval(function() {
							activeTimerSeconds++;
							updateClockText();
							checkIdleNotification();
						}, 1000);
					}

					// Stop Live Clock
					function stopLiveClock() {
						if (clockInterval) {
							clearInterval(clockInterval);
							clockInterval = null;
						}
					}

					// Browser Notifications for Idle Timer
					if ('Notification' in window && Notification.permission === 'default') {
						Notification.requestPermission();
					}

					let idleNotificationSent = false;
					function checkIdleNotification() {
						if (activeTimerSeconds > 28800) {
							if (!idleNotificationSent) {
								if ('Notification' in window && Notification.permission === 'granted') {
									new Notification('Ndizi Idle Timer Warning', {
										body: 'Your timer has been running for over 8 hours. Please check in or stop your timer.'
									});
									idleNotificationSent = true;
								}
							}
						} else {
							idleNotificationSent = false;
						}
					}

					// Initialize
					loadTrackerData();
					loadRecentLogs();
					if ($('body').hasClass('timer-running')) {
						startLiveClock();
					}
				});
			</script>
		</body>
		</html>
		<?php
	}
}
