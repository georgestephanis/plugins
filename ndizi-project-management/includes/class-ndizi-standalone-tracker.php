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
		if ( is_admin() ) {
			add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
			add_action( 'admin_init', array( __CLASS__, 'handle_requests' ) );
		}
		add_action( 'wp_ajax_ndizi_get_recent_user_logs', array( __CLASS__, 'ajax_get_recent_user_logs' ) );
	}

	/**
	 * Register Standalone Tracker submenu page under Ndizi PM
	 */
	public static function register_page() {
		add_submenu_page(
			'ndizi-pm',
			__( 'Standalone Tracker', 'ndizi-project-management' ),
			__( 'Standalone Tracker', 'ndizi-project-management' ),
			'ndizi_log_time',
			'ndizi-tracker-standalone',
			array( __CLASS__, 'render_standalone_page' )
		);
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
			$duration_sec = max( 0, time() - $start_ts );
		}

		$h           = floor( $duration_sec / 3600 );
		$m           = floor( ( $duration_sec % 3600 ) / 60 );
		$s           = $duration_sec % 60;
		$ticker_text = sprintf( '%02d:%02d:%02d', $h, $m, $s );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$prefilled_desc = isset( $_GET['desc'] ) ? sanitize_text_field( wp_unslash( $_GET['desc'] ) ) : '';

		self::enqueue_standalone_assets( $duration_sec );

		include NDIZI_PLUGIN_DIR . 'templates/standalone-tracker.php';
	}

	/**
	 * Register and enqueue the standalone tracker's CSS/JS.
	 *
	 * This page renders its own complete HTML document (it does not run
	 * wp_head()/wp_footer()), so the template prints these handles explicitly
	 * via wp_print_styles()/wp_print_scripts(). Routing the assets through the
	 * enqueue system — rather than hardcoded <link>/<script> tags — keeps the
	 * page consistent with WordPress's dependency, versioning, and inline-data
	 * handling, and lets the Google Fonts request stay opt-in.
	 *
	 * @param int $duration_sec Seconds elapsed on the active timer (0 if none).
	 */
	private static function enqueue_standalone_assets( $duration_sec ) {
		wp_register_style( 'ndizi-standalone', NDIZI_PLUGIN_URL . 'build/standalone.css', array(), NDIZI_VERSION );
		wp_style_add_data( 'ndizi-standalone', 'rtl', 'replace' );

		if ( Ndizi_Project_Management::google_fonts_enabled() ) {
			wp_register_style(
				'ndizi-standalone-fonts',
				'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap',
				array(),
				null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts is a versionless external stylesheet; appending a plugin version query would be incorrect.
			);
			wp_enqueue_style( 'ndizi-standalone-fonts' );
		}

		wp_enqueue_style( 'ndizi-standalone' );

		wp_register_script( 'ndizi-standalone', NDIZI_PLUGIN_URL . 'build/standalone.js', array( 'jquery' ), NDIZI_VERSION, true );
		wp_localize_script(
			'ndizi-standalone',
			'ndizi_standalone',
			array(
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'ndizi-admin-nonce' ),
				'active_timer_seconds' => (int) $duration_sec,
				'labels'               => array(
					'no_active_projects'    => __( 'No active projects found.', 'ndizi-project-management' ),
					'select_project'        => __( '-- Select Project --', 'ndizi-project-management' ),
					'general_task'          => __( '-- General --', 'ndizi-project-management' ),
					'internal_client'       => __( 'Internal', 'ndizi-project-management' ),
					'please_select_project' => __( 'Please select a project.', 'ndizi-project-management' ),
					'please_enter_duration' => __( 'Please specify hours or minutes.', 'ndizi-project-management' ),
					'entry_logged'          => __( 'Time entry logged successfully!', 'ndizi-project-management' ),
					'no_description'        => __( 'No description', 'ndizi-project-management' ),
					'no_entries'            => __( 'No entries recorded today.', 'ndizi-project-management' ),
					'confirm_delete'        => __( 'Are you sure you want to delete this time entry?', 'ndizi-project-management' ),
				),
			)
		);
		wp_enqueue_script( 'ndizi-standalone' );
	}
}
