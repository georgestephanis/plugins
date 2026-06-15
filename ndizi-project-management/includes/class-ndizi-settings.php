<?php
/**
 * Settings, admin pages, and user profile fields for Ndizi Project Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Settings {

	/**
	 * Initialize settings hooks
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings_page' ) );
		// Priority 9 so the top-level menu and its Dashboard submenu are registered
		// before core's _add_post_type_submenus() (admin_menu, priority 10) appends
		// the CPT submenus; otherwise the first CPT (clients) becomes the top-level
		// menu's click target instead of the dashboard.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ), 9 );
		// Priority 12 so we modify the submenu array after core CPTs are registered
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu_separator' ), 12 );

		// User profile billing rate fields
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_profile_fields' ) );
	}

	/**
	 * Intercept settings save on admin_init hook to run before admin bar is rendered
	 */
	public static function save_settings_page() {
		if ( isset( $_GET['page'] ) && 'ndizi-settings' === $_GET['page'] && isset( $_GET['code'] )
			&& Ndizi_Project_Management::is_module_active( 'calendar' ) ) {

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ndizi-project-management' ) );
			}

			if ( ! isset( $_GET['state'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'ndizi_google_oauth_state' ) ) {
				wp_die( esc_html__( 'Security failed: invalid state parameter.', 'ndizi-project-management' ) );
			}

			$code          = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$client_id     = Ndizi_Project_Management::get_secret( 'ndizi_google_client_id' );
			$client_secret = Ndizi_Project_Management::get_secret( 'ndizi_google_client_secret' );

			if ( $client_id && $client_secret ) {
				$response = wp_remote_post(
					'https://oauth2.googleapis.com/token',
					array(
						'body' => array(
							'code'          => $code,
							'client_id'     => $client_id,
							'client_secret' => $client_secret,
							'redirect_uri'  => admin_url( 'admin.php?page=ndizi-settings' ),
							'grant_type'    => 'authorization_code',
						),
					)
				);

				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), true );

					if ( isset( $body['refresh_token'] ) ) {
						update_option( 'ndizi_google_refresh_token', $body['refresh_token'] );
					}
					if ( isset( $body['access_token'] ) ) {
						update_option( 'ndizi_google_access_token', $body['access_token'] );
						update_option( 'ndizi_google_token_expiry', time() + (int) $body['expires_in'] );
					}
					wp_safe_redirect( admin_url( 'admin.php?page=ndizi-settings&settings-updated=true' ) );
					exit;
				}
			}
		}

		if ( isset( $_POST['ndizi_save_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_save_settings_nonce'] ) ), 'ndizi_save_settings' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ndizi-project-management' ) );
			}

			$updated = false;

			if ( isset( $_POST['ndizi_adminbar_icon'] ) ) {
				$icon = sanitize_key( wp_unslash( $_POST['ndizi_adminbar_icon'] ) );
				if ( in_array( $icon, array( 'banana', 'clock', 'punch_clock', 'hourglass' ), true ) ) {
					update_option( 'ndizi_adminbar_icon', $icon );
					$updated = true;
				}
			}

			if ( isset( $_POST['ndizi_lock_date'] ) ) {
				$lock_date = sanitize_text_field( wp_unslash( $_POST['ndizi_lock_date'] ) );
				update_option( 'ndizi_lock_date', $lock_date );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_stripe_secret_key'] ) ) {
				update_option( 'ndizi_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['ndizi_stripe_secret_key'] ) ) );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_stripe_publishable_key'] ) ) {
				update_option( 'ndizi_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['ndizi_stripe_publishable_key'] ) ) );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_stripe_webhook_secret'] ) ) {
				update_option( 'ndizi_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['ndizi_stripe_webhook_secret'] ) ) );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_google_client_id'] ) ) {
				update_option( 'ndizi_google_client_id', sanitize_text_field( wp_unslash( $_POST['ndizi_google_client_id'] ) ) );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_google_client_secret'] ) ) {
				update_option( 'ndizi_google_client_secret', sanitize_text_field( wp_unslash( $_POST['ndizi_google_client_secret'] ) ) );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_webhook_url'] ) ) {
				$webhook_url = esc_url_raw( wp_unslash( $_POST['ndizi_webhook_url'] ) );
				update_option( 'ndizi_webhook_url', $webhook_url );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_slack_webhook_url'] ) ) {
				$slack_webhook_url = esc_url_raw( wp_unslash( $_POST['ndizi_slack_webhook_url'] ) );
				update_option( 'ndizi_slack_webhook_url', $slack_webhook_url );
				$updated = true;
			}

			if ( isset( $_POST['ndizi_save_settings_nonce'] ) ) {
				$modules = isset( $_POST['ndizi_active_modules'] ) && is_array( $_POST['ndizi_active_modules'] )
					? array_map( 'sanitize_key', wp_unslash( $_POST['ndizi_active_modules'] ) )
					: array();
				update_option( 'ndizi_active_modules', $modules );
				$updated = true;
			}

			if ( $updated ) {
				wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
				exit;
			}
		}
	}

	/**
	 * Enqueue stylesheet and javascript
	 */
	public static function enqueue_assets() {
		$css = '
			#adminmenu .wp-submenu li:has(a[href*="ndizi-pm-separator"]) {
				pointer-events: none;
				border-top: 1px solid rgba(128, 128, 128, 0.15);
				margin: 6px 0;
				padding: 0;
				height: 0;
				overflow: hidden;
			}
			#adminmenu .wp-submenu li:has(a[href*="ndizi-pm-separator"]) a {
				display: none !important;
			}
		';
		wp_add_inline_style( 'common', $css );

		// Only enqueue on our post types or pages
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$ndizi_post_types = array( 'ndizi_client', 'ndizi_project', 'ndizi_task', 'ndizi_invoice', 'ndizi_contact' );
		// Reading the current admin page slug to decide whether to enqueue assets; no state change, so no nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$is_ndizi_page = ( 0 === strpos( $current_page, 'ndizi-' ) );

		// Register (do not enqueue) the shared DataViews bundle so any Ndizi
		// script can declare `ndizi-dataviews` as a dependency. It is built once
		// via `npm run build:vendor` (src/vendor/dataviews.js) and exposes the
		// package on window.ndiziDataViews; webpack maps @wordpress/dataviews to
		// it, so consuming scripts' .asset.php files list this handle for us.
		self::register_dataviews_bundle();

		if ( in_array( $screen->post_type, $ndizi_post_types, true ) || $is_ndizi_page ) {
			wp_enqueue_style( 'ndizi-admin-style', NDIZI_PLUGIN_URL . 'build/admin.css', array(), NDIZI_VERSION );
			wp_enqueue_script( 'ndizi-admin-script', NDIZI_PLUGIN_URL . 'build/admin.js', array( 'jquery' ), NDIZI_VERSION, true );

			wp_localize_script(
				'ndizi-admin-script',
				'ndizi_admin',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ndizi-admin-nonce' ),
					'labels'   => array(
						'timer_started'  => __( 'Timer started!', 'ndizi-project-management' ),
						'timer_stopped'  => __( 'Timer stopped!', 'ndizi-project-management' ),
						'confirm_delete' => __( 'Are you sure you want to delete this entry?', 'ndizi-project-management' ),
					),
				)
			);
		}

		if ( 'ndizi-time-entries' === $current_page ) {
			$asset_path = NDIZI_PLUGIN_DIR . 'build/time-entries.asset.php';
			if ( file_exists( $asset_path ) ) {
				$asset        = include $asset_path;
				$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
				wp_enqueue_script(
					'ndizi-time-entries-app',
					NDIZI_PLUGIN_URL . 'build/time-entries.js',
					$dependencies,
					$asset['version'],
					true
				);

				wp_localize_script(
					'ndizi-time-entries-app',
					'ndizi_time_entries_admin',
					array(
						// apiFetch sources its REST root and nonce from the core
						// wp-api-fetch localization, so the app only needs these
						// view-specific values.
						'can_manage'      => Ndizi_Roles::current_user_can( 'ndizi_manage_time' ),
						'current_user_id' => get_current_user_id(),
						'lock_date'       => get_option( 'ndizi_lock_date', '' ),
					)
				);
			}

			wp_enqueue_style( 'wp-components' );

			// DataViews' stylesheet ships in the shared bundle (registered by
			// self::register_dataviews_bundle()). The app script already depends
			// on the `ndizi-dataviews` script handle; styles are not pulled in by
			// script dependencies, so enqueue the matching style handle here.
			if ( wp_style_is( 'ndizi-dataviews', 'registered' ) ) {
				wp_enqueue_style( 'ndizi-dataviews' );
			}
		}
	}

	/**
	 * Register the shared DataViews bundle as the `ndizi-dataviews` script and
	 * style handles, reading dependencies/version from its generated asset file.
	 *
	 * Registration is idempotent and does not output anything; callers enqueue
	 * the handles (or depend on the script handle) where DataViews is needed.
	 * The bundle is produced by `npm run build:vendor` (see webpack.vendor.js).
	 */
	public static function register_dataviews_bundle() {
		if ( wp_script_is( 'ndizi-dataviews', 'registered' ) ) {
			return;
		}

		$asset_path = NDIZI_PLUGIN_DIR . 'build/vendor-dataviews.asset.php';
		$asset      = file_exists( $asset_path )
			? include $asset_path
			: array(
				'dependencies' => array(),
				'version'      => NDIZI_VERSION,
			);

		wp_register_script(
			'ndizi-dataviews',
			NDIZI_PLUGIN_URL . 'build/vendor-dataviews.js',
			isset( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
			isset( $asset['version'] ) ? $asset['version'] : NDIZI_VERSION,
			true
		);

		$style_path = NDIZI_PLUGIN_DIR . 'build/style-vendor-dataviews.css';
		if ( file_exists( $style_path ) ) {
			wp_register_style(
				'ndizi-dataviews',
				NDIZI_PLUGIN_URL . 'build/style-vendor-dataviews.css',
				array( 'wp-components' ),
				isset( $asset['version'] ) ? $asset['version'] : NDIZI_VERSION,
				'all'
			);
			wp_style_add_data( 'ndizi-dataviews', 'rtl', 'replace' );
		}
	}

	/**
	 * Register Ndizi PM top-level menu and submenus
	 */
	public static function register_admin_pages() {
		// Top level menu
		add_menu_page(
			__( 'Ndizi PM', 'ndizi-project-management' ),
			__( 'Ndizi PM', 'ndizi-project-management' ),
			'ndizi_view_projects',
			'ndizi-pm',
			array( __CLASS__, 'render_dashboard_page' ),
			'dashicons-portfolio',
			30
		);

		// Submenu: Reports
		add_submenu_page(
			'ndizi-pm',
			__( 'Ndizi Reports', 'ndizi-project-management' ),
			__( 'Reports', 'ndizi-project-management' ),
			'ndizi_view_reports',
			'ndizi-reports',
			array( 'Ndizi_Reports', 'render_reports_page' )
		);

		// Submenu: Settings
		add_submenu_page(
			'ndizi-pm',
			__( 'Ndizi Settings', 'ndizi-project-management' ),
			__( 'Settings', 'ndizi-project-management' ),
			'manage_options',
			'ndizi-settings',
			array( __CLASS__, 'render_settings_page' )
		);

		// Submenu: Time Entries
		add_submenu_page(
			'ndizi-pm',
			__( 'Time Entries', 'ndizi-project-management' ),
			__( 'Time Entries', 'ndizi-project-management' ),
			'ndizi_log_time',
			'ndizi-time-entries',
			array( __CLASS__, 'render_time_entries_page' )
		);
	}

	/**
	 * Insert a separator/spacer in the Ndizi submenu
	 */
	public static function add_submenu_separator() {
		global $submenu;

		if ( empty( $submenu['ndizi-pm'] ) || ! is_array( $submenu['ndizi-pm'] ) ) {
			return;
		}

		$global_pages = array();
		$cpt_pages    = array();

		foreach ( $submenu['ndizi-pm'] as $item ) {
			if ( isset( $item[2] ) && ( 0 === strpos( $item[2], 'edit.php?post_type=' ) || 'ndizi-time-entries' === $item[2] ) ) {
				$cpt_pages[] = $item;
			} else {
				$global_pages[] = $item;
			}
		}

		// Sort the global pages based on a preferred order.
		$preferred_order = array(
			'ndizi-pm',
			'ndizi-reports',
			'ndizi-gantt',
			'ndizi-tracker-standalone',
			'ndizi-settings',
		);

		usort(
			$global_pages,
			function ( $a, $b ) use ( $preferred_order ) {
				$pos_a = array_search( $a[2], $preferred_order, true );
				$pos_b = array_search( $b[2], $preferred_order, true );

				if ( false === $pos_a && false === $pos_b ) {
					return 0;
				}
				if ( false === $pos_a ) {
					return 1;
				}
				if ( false === $pos_b ) {
					return -1;
				}

				return $pos_a - $pos_b;
			}
		);

		// Combine them with the separator in the middle.
		$new_submenu = array_merge(
			$global_pages,
			array(
				array(
					'',
					'read',
					'ndizi-pm-separator',
				),
			),
			$cpt_pages
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$submenu['ndizi-pm'] = $new_submenu;
	}



	/**
	 * Initialize Gantt module hooks
	 */
	public static function init_gantt() {
		add_action( 'admin_menu', array( __CLASS__, 'register_gantt_admin_page' ) );
	}

	/**
	 * Register Gantt Chart submenu page under Ndizi PM
	 */
	public static function register_gantt_admin_page() {
		add_submenu_page(
			'ndizi-pm',
			__( 'Ndizi Gantt Chart', 'ndizi-project-management' ),
			__( 'Gantt Chart', 'ndizi-project-management' ),
			'ndizi_view_projects',
			'ndizi-gantt',
			array( __CLASS__, 'render_gantt_page' )
		);
	}

	/**
	 * Render the main Ndizi PM Dashboard Page
	 */
	public static function render_dashboard_page() {
		// Read-only, bookmarkable report filters from the query string
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Calculate stats
		$active_projects = count(
			get_posts(
				array(
					'post_type'      => 'ndizi_project',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_project_status',
							'value' => 'active',
						),
					),
				)
			)
		);

		$open_tasks = count(
			get_posts(
				array(
					'post_type'      => 'ndizi_task',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_ndizi_task_status',
							'value'   => array( 'open', 'in_progress' ),
							'compare' => 'IN',
						),
					),
				)
			)
		);

		global $wpdb;
		$table_name = Ndizi_DB::get_table_name();

		$query      = "SELECT SUM(duration) FROM $table_name WHERE 1=1";
		$query_args = array();

		if ( ! empty( $start_date ) ) {
			$query       .= ' AND start_time >= %s';
			$query_args[] = $start_date . ' 00:00:00';
		}
		if ( ! empty( $end_date ) ) {
			$query       .= ' AND start_time <= %s';
			$query_args[] = $end_date . ' 23:59:59';
		}

		if ( ! empty( $query_args ) ) {
			$total_sec = $wpdb->get_var( $wpdb->prepare( $query, $query_args ) );
		} else {
			$total_sec = $wpdb->get_var( $query );
		}

		$total_hours = $total_sec ? round( $total_sec / 3600, 1 ) : 0;

		$pending_invoices = count(
			get_posts(
				array(
					'post_type'      => 'ndizi_invoice',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_ndizi_invoice_status',
							'value'   => array( 'draft', 'sent' ),
							'compare' => 'IN',
						),
					),
				)
			)
		);

		// Render page
		?>
		<div class="wrap ndizi-dashboard-page" style="max-width: 1200px; margin: 20px auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
			<div style="background: linear-gradient(135deg, #4f46e5, #3b82f6); padding: 40px; border-radius: 12px; color: #fff; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(79, 70, 229, 0.15);">
				<h1 style="margin: 0; font-size: 32px; font-weight: 800; color: #fff;"><?php esc_html_e( 'Welcome to Ndizi Project Management', 'ndizi-project-management' ); ?></h1>
				<p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;"><?php esc_html_e( 'Native WordPress tracking for clients, projects, tasks, timesheets, and invoices.', 'ndizi-project-management' ); ?></p>
			</div>

			<!-- Date Range Filter -->
			<div style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; margin-bottom: 30px;">
				<form method="get" action="admin.php" style="display: flex; flex-wrap: wrap; align-items: center; gap: 15px; margin: 0;">
					<input type="hidden" name="page" value="ndizi-pm">

					<div style="display: flex; align-items: center; gap: 8px;">
						<label for="ndizi_start_date" style="font-weight: 600; color: #475569; font-size: 13px;"><?php esc_html_e( 'From:', 'ndizi-project-management' ); ?></label>
						<input type="date" name="start_date" id="ndizi_start_date" value="<?php echo esc_attr( $start_date ); ?>" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
					</div>

					<div style="display: flex; align-items: center; gap: 8px;">
						<label for="ndizi_end_date" style="font-weight: 600; color: #475569; font-size: 13px;"><?php esc_html_e( 'To:', 'ndizi-project-management' ); ?></label>
						<input type="date" name="end_date" id="ndizi_end_date" value="<?php echo esc_attr( $end_date ); ?>" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
					</div>

					<button type="submit" class="button button-primary" style="background: #4f46e5; border-color: #4f46e5; height: 32px; line-height: 30px; padding: 0 16px; font-weight: 600;"><?php esc_html_e( 'Filter', 'ndizi-project-management' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-pm' ) ); ?>" class="button button-secondary" style="height: 32px; line-height: 30px; padding: 0 16px; font-weight: 600;"><?php esc_html_e( 'Clear', 'ndizi-project-management' ); ?></a>
				</form>
			</div>

			<!-- Stats Grid -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
				<div style="background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-top: 4px solid #4f46e5;">
					<h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;"><?php esc_html_e( 'Active Projects', 'ndizi-project-management' ); ?></h3>
					<div style="font-size: 36px; font-weight: 800; color: #1e293b; margin-top: 10px;"><?php echo intval( $active_projects ); ?></div>
				</div>
				<div style="background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-top: 4px solid #f59e0b;">
					<h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;"><?php esc_html_e( 'Open Tasks', 'ndizi-project-management' ); ?></h3>
					<div style="font-size: 36px; font-weight: 800; color: #1e293b; margin-top: 10px;"><?php echo intval( $open_tasks ); ?></div>
				</div>
				<div style="background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-top: 4px solid #10b981;">
					<h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">
						<?php esc_html_e( 'Total Hours Logged', 'ndizi-project-management' ); ?>
						<?php if ( ! empty( $start_date ) || ! empty( $end_date ) ) : ?>
							<span style="font-size: 11px; text-transform: none; display: block; margin-top: 4px; color: #4f46e5; font-weight: 500;">
								(<?php echo esc_html( $start_date ? $start_date : '...' ); ?> &ndash; <?php echo esc_html( $end_date ? $end_date : '...' ); ?>)
							</span>
						<?php endif; ?>
					</h3>
					<div style="font-size: 36px; font-weight: 800; color: #1e293b; margin-top: 10px;"><?php echo esc_html( $total_hours ); ?>h</div>
				</div>
				<div style="background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; border-top: 4px solid #ec4899;">
					<h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;"><?php esc_html_e( 'Pending Invoices', 'ndizi-project-management' ); ?></h3>
					<div style="font-size: 36px; font-weight: 800; color: #1e293b; margin-top: 10px;"><?php echo intval( $pending_invoices ); ?></div>
				</div>
			</div>

			<!-- Actions & Quicklinks -->
			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: start;">
				<!-- Quick Actions -->
				<div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
					<h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><?php esc_html_e( 'Quick Action Workspace', 'ndizi-project-management' ); ?></h2>
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_client' ) ); ?>" class="ndizi-qa-link" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600;">
							<span class="dashicons dashicons-networking" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Add New Client', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_project' ) ); ?>" class="ndizi-qa-link" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600;">
							<span class="dashicons dashicons-portfolio" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Create New Project', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_task' ) ); ?>" class="ndizi-qa-link" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600;">
							<span class="dashicons dashicons-yes" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Create New Task', 'ndizi-project-management' ); ?>
						</a>
						<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_invoice' ) ); ?>" class="ndizi-qa-link" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600;">
								<span class="dashicons dashicons-analytics" style="margin-right: 10px; color: #4f46e5;"></span>
								<?php esc_html_e( 'Generate Invoice', 'ndizi-project-management' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<!-- Navigation Quick Links -->
				<div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
					<h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><?php esc_html_e( 'Views & Reports', 'ndizi-project-management' ); ?></h2>
					<div style="display: flex; flex-direction: column; gap: 10px;">
						<?php if ( Ndizi_Project_Management::is_module_active( 'tracker' ) ) : ?>
							<a href="#" class="ndizi-qa-link-yellow ndizi-launch-tracker" data-tracker-url="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-tracker-standalone' ) ); ?>" style="display: block; background: #eab308; color: #0f172a; text-align: center; font-weight: 700; padding: 12px; border-radius: 6px; text-decoration: none; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.15);">
								<span class="dashicons dashicons-external" style="margin-right: 6px; vertical-align: middle; font-size: 18px; width: 18px; height: 18px; color: #0f172a;"></span>
								<?php esc_html_e( 'Launch Standalone Tracker', 'ndizi-project-management' ); ?>
							</a>
						<?php endif; ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-reports' ) ); ?>" class="ndizi-qa-link-indigo" style="display: block; background: #4f46e5; color: #fff; text-align: center; font-weight: 600; padding: 12px; border-radius: 6px; text-decoration: none;">
							<?php esc_html_e( 'View Productivity Reports', 'ndizi-project-management' ); ?>
						</a>
						<?php if ( Ndizi_Project_Management::is_module_active( 'gantt' ) ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-gantt' ) ); ?>" class="ndizi-qa-link-ghost" style="display: block; background: #f8fafc; border: 1px solid #cbd5e1; color: #1e293b; text-align: center; font-weight: 600; padding: 12px; border-radius: 6px; text-decoration: none;">
								<?php esc_html_e( 'View Gantt Charts', 'ndizi-project-management' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Gantt Chart Page
	 */
	public static function render_gantt_page() {
		// Query active projects
		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_ndizi_project_status',
						'value'   => 'active',
						'compare' => '=',
					),
				),
			)
		);

		// Compile timelines
		$timeline_data = array();
		$min_time      = null;
		$max_time      = null;

		foreach ( $projects as $proj ) {
			$start = get_post_meta( $proj->ID, '_ndizi_project_start_date', true );
			$end   = get_post_meta( $proj->ID, '_ndizi_project_end_date', true );

			if ( empty( $start ) || empty( $end ) ) {
				continue;
			}

			$start_ts = strtotime( $start );
			$end_ts   = strtotime( $end );

			if ( null === $min_time || $start_ts < $min_time ) {
				$min_time = $start_ts;
			}
			if ( null === $max_time || $end_ts > $max_time ) {
				$max_time = $end_ts;
			}

			// Get task completion percentage
			$tasks = get_posts(
				array(
					'post_type'      => 'ndizi_task',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_project_id',
							'value' => $proj->ID,
						),
					),
				)
			);

			$total_tasks     = count( $tasks );
			$completed_tasks = 0;
			foreach ( $tasks as $t ) {
				$task_status = get_post_meta( $t->ID, '_ndizi_task_status', true );
				if ( 'completed' === $task_status ) {
					++$completed_tasks;
				}
			}
			$progress_pct = $total_tasks > 0 ? round( ( $completed_tasks / $total_tasks ) * 100 ) : 0;

			$timeline_data[] = array(
				'id'          => $proj->ID,
				'title'       => $proj->post_title,
				'start_date'  => $start,
				'end_date'    => $end,
				'start_ts'    => $start_ts,
				'end_ts'      => $end_ts,
				'progress'    => $progress_pct,
				'total_tasks' => $total_tasks,
				'completed'   => $completed_tasks,
			);
		}

		// Fallbacks for empty timeline bounds
		if ( null === $min_time ) {
			$min_time = time();
		}
		if ( null === $max_time ) {
			$max_time = strtotime( '+3 months' );
		}

		// Adjust bounds slightly for margins (pad 1 week)
		$min_time = strtotime( '-7 days', $min_time );
		$max_time = strtotime( '+7 days', $max_time );

		$span_days = max( 1, round( ( $max_time - $min_time ) / 86400 ) );

		// Generate monthly ticks for headers
		$ticks        = array();
		$current_tick = $min_time;
		while ( $current_tick <= $max_time ) {
			$ticks[]      = array(
				'label' => date_i18n( 'M Y', $current_tick ),
				'ts'    => $current_tick,
			);
			$current_tick = strtotime( '+1 month', strtotime( gmdate( 'Y-m-01', strtotime( '+5 days', $current_tick ) ) ) );
		}
		?>
		<div class="wrap ndizi-gantt-page">
			<h1><?php esc_html_e( 'Project Gantt Timelines', 'ndizi-project-management' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Visualizing schedule timelines and task completion rates across active client projects.', 'ndizi-project-management' ); ?></p>
			<hr class="wp-header-end">

			<?php if ( empty( $timeline_data ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'No active projects with both Start and End Dates populated were found to plot in the Gantt chart.', 'ndizi-project-management' ); ?></p></div>
			<?php else : ?>
				<div class="ndizi-gantt-container">
					<!-- Gantt Header (Months) -->
					<div class="ndizi-gantt-header-row">
						<div class="ndizi-gantt-label-col"><strong><?php esc_html_e( 'Project Name', 'ndizi-project-management' ); ?></strong></div>
						<div class="ndizi-gantt-timeline-col">
							<div class="ndizi-gantt-ticks">
								<?php
								foreach ( $ticks as $i => $tick ) :
									$next_ts   = isset( $ticks[ $i + 1 ] ) ? $ticks[ $i + 1 ]['ts'] : $max_time;
									$tick_days = max( 1, round( ( $next_ts - $tick['ts'] ) / 86400 ) );
									$w_pct     = ( $tick_days / $span_days ) * 100;
									?>
									<div class="ndizi-gantt-month-tick" style="width: <?php echo esc_attr( $w_pct ); ?>%">
										<span><?php echo esc_html( $tick['label'] ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<!-- Gantt Rows -->
					<div class="ndizi-gantt-body">
						<?php
						foreach ( $timeline_data as $project ) :
							$offset_days   = round( ( $project['start_ts'] - $min_time ) / 86400 );
							$duration_days = max( 1, round( ( $project['end_ts'] - $project['start_ts'] ) / 86400 ) );

							$left_pct  = ( $offset_days / $span_days ) * 100;
							$width_pct = ( $duration_days / $span_days ) * 100;
							?>
							<div class="ndizi-gantt-row">
								<div class="ndizi-gantt-label-col">
									<span class="ndizi-gantt-project-title">
										<a href="<?php echo esc_url( get_edit_post_link( $project['id'] ) ); ?>"><?php echo esc_html( $project['title'] ); ?></a>
									</span>
									<span class="ndizi-gantt-project-meta">
										<?php echo esc_html( $project['completed'] ); ?>/<?php echo esc_html( $project['total_tasks'] ); ?> <?php esc_html_e( 'Tasks', 'ndizi-project-management' ); ?> (<?php echo esc_html( $project['progress'] ); ?>%)
									</span>
								</div>
								<div class="ndizi-gantt-timeline-col">
									<div class="ndizi-gantt-bar-container">
										<div class="ndizi-gantt-project-bar" style="left: <?php echo esc_attr( $left_pct ); ?>%; width: <?php echo esc_attr( $width_pct ); ?>%;">
											<div class="ndizi-gantt-project-bar-fill" style="width: <?php echo esc_attr( $project['progress'] ); ?>%;"></div>
											<span class="ndizi-gantt-bar-text"><?php echo esc_html( $project['progress'] ); ?>%</span>
										</div>
									</div>
									<div class="ndizi-gantt-gridlines">
										<?php
										foreach ( $ticks as $tick ) :
											$tick_offset = ( round( ( $tick['ts'] - $min_time ) / 86400 ) / $span_days ) * 100;
											?>
											<div class="ndizi-gantt-gridline" style="left: <?php echo esc_attr( $tick_offset ); ?>%"></div>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Ndizi PM Settings Page
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ndizi-project-management' ) );
		}

		// Show Success Notice
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'ndizi-project-management' ) . '</p></div>';
		}

		$current_icon = get_option( 'ndizi_adminbar_icon', 'banana' );

		// Enqueue styles for preview
		wp_enqueue_style( 'ndizi-adminbar-style' );
		?>
		<div class="wrap ndizi-settings-wrap" style="max-width: 800px; margin: 30px auto 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;">
			<h1 style="font-size: 28px; font-weight: 700; color: #0f172a; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
				<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
				<?php esc_html_e( 'Ndizi PM Settings', 'ndizi-project-management' ); ?>
			</h1>

			<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
				<form method="post" action="">
					<?php wp_nonce_field( 'ndizi_save_settings', 'ndizi_save_settings_nonce' ); ?>

					<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 0 0 8px 0;"><?php esc_html_e( 'Admin Bar Icon', 'ndizi-project-management' ); ?></h2>
					<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Choose which icon should display for the time tracker in the WP Admin Bar.', 'ndizi-project-management' ); ?></p>

					<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; margin-bottom: 30px;">

						<!-- Option: Banana -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="banana" <?php checked( $current_icon, 'banana' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border-radius: 10px; padding: 20px; text-align: center;">
								<div class="ndizi-icon-card-icon" style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6v-2a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v2a9.09 9.09 0 0 1 -4 8.08c-2 1.31 -5 1.57 -7 1.59a2 2 0 0 0 -2 2a2 2 0 0 0 1.16 1.81c2.69 1.2 9.46 3.44 14.35 -1.66c4.49 -4.74 1.49 -11.82 1.49 -11.82" /></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Banana', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Clock -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="clock" <?php checked( $current_icon, 'clock' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border-radius: 10px; padding: 20px; text-align: center;">
								<div class="ndizi-icon-card-icon" style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Clock', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Punch Clock -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="punch_clock" <?php checked( $current_icon, 'punch_clock' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border-radius: 10px; padding: 20px; text-align: center;">
								<div class="ndizi-icon-card-icon" style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 -960 960 960" fill="currentColor"><path d="M360-120v-80h240v80H360Zm120-160q-100 0-170-70t-70-170q0-100 70-170t170-70q100 0 170 70t70 170q0 100-70 170t-170 70Zm0-80q66 0 113-47t47-113q0-66-47-113t-113-47q-66 0-113 47t-47 113q0 66 47 113t113 47ZM80-560v-80h160v80H80Zm640 0v-80h160v80H720ZM440-440h80v120l-70 70-56-56 46-46v-88Z"/></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Punch Clock', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Hourglass -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="hourglass" <?php checked( $current_icon, 'hourglass' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border-radius: 10px; padding: 20px; text-align: center;">
								<div class="ndizi-icon-card-icon" style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14" /><path d="M5 2h14" /><path d="M17 22v-4.172a2 2 0 0 0 -.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" /><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" /></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Hourglass', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

					</div>

					<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Active Modules', 'ndizi-project-management' ); ?></h2>
					<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Enable or disable core features to customize the interface and optimize performance.', 'ndizi-project-management' ); ?></p>

					<div style="margin-bottom: 30px; display: flex; flex-direction: column; gap: 16px;">
						<?php
						$modules_list = Ndizi_Project_Management::get_module_registry();

						$active_modules = Ndizi_Project_Management::get_active_modules();
						foreach ( $modules_list as $slug => $mod ) :
							$checked = in_array( $slug, $active_modules, true );
							?>
							<label style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 14px 18px; border: 1px solid <?php echo $checked ? '#e0e7ff' : '#e2e8f0'; ?>; background: <?php echo $checked ? '#f8fafc' : '#fff'; ?>; border-radius: 10px; transition: all 0.2s;">
								<input type="checkbox" name="ndizi_active_modules[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $checked ); ?> style="margin-top: 4px; border: 1px solid #cbd5e1; border-radius: 4px;">
								<div>
									<strong style="display: block; font-size: 14px; color: #1e293b; margin-bottom: 2px;"><?php echo esc_html( $mod['name'] ); ?></strong>
									<span style="display: block; font-size: 12px; color: #64748b; line-height: 1.4;"><?php echo esc_html( $mod['desc'] ); ?></span>
								</div>
							</label>
						<?php endforeach; ?>
					</div>

					<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Time Entry Locking', 'ndizi-project-management' ); ?></h2>
					<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Prevent users from adding, modifying, or deleting time entries logged on or before this date.', 'ndizi-project-management' ); ?></p>

					<div style="margin-bottom: 30px;">
						<?php $lock_date = get_option( 'ndizi_lock_date', '' ); ?>
						<label for="ndizi_lock_date" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Lock Date', 'ndizi-project-management' ); ?></label>
						<input type="date" name="ndizi_lock_date" id="ndizi_lock_date" value="<?php echo esc_attr( $lock_date ); ?>" style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
						<p class="description" style="margin-top: 5px; color: #64748b;"><?php esc_html_e( 'Leave empty to disable locking.', 'ndizi-project-management' ); ?></p>
					</div>

					<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
						<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Stripe Invoicing Settings', 'ndizi-project-management' ); ?></h2>
						<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Configure Stripe payment gateway integration to allow clients to pay invoices online.', 'ndizi-project-management' ); ?></p>

						<?php
						$stripe_secret             = Ndizi_Project_Management::get_secret( 'ndizi_stripe_secret_key' );
						$stripe_secret_locked      = defined( 'NDIZI_STRIPE_SECRET_KEY' );
						$stripe_publishable        = Ndizi_Project_Management::get_secret( 'ndizi_stripe_publishable_key' );
						$stripe_publishable_locked = defined( 'NDIZI_STRIPE_PUBLISHABLE_KEY' );
						$stripe_webhook            = Ndizi_Project_Management::get_secret( 'ndizi_stripe_webhook_secret' );
						$stripe_webhook_locked     = defined( 'NDIZI_STRIPE_WEBHOOK_SECRET' );
						?>
						<div style="margin-bottom: 20px;">
							<label for="ndizi_stripe_secret_key" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Stripe Secret Key', 'ndizi-project-management' ); ?></label>
							<?php if ( $stripe_secret_locked ) : ?>
								<p class="description" style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Set via NDIZI_STRIPE_SECRET_KEY constant.', 'ndizi-project-management' ); ?></p>
							<?php else : ?>
								<input type="password" name="ndizi_stripe_secret_key" id="ndizi_stripe_secret_key" value="<?php echo esc_attr( $stripe_secret ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<?php endif; ?>
						</div>

						<div style="margin-bottom: 20px;">
							<label for="ndizi_stripe_publishable_key" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Stripe Publishable Key', 'ndizi-project-management' ); ?></label>
							<?php if ( $stripe_publishable_locked ) : ?>
								<p class="description" style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Set via NDIZI_STRIPE_PUBLISHABLE_KEY constant.', 'ndizi-project-management' ); ?></p>
							<?php else : ?>
								<input type="text" name="ndizi_stripe_publishable_key" id="ndizi_stripe_publishable_key" value="<?php echo esc_attr( $stripe_publishable ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<?php endif; ?>
						</div>

						<div style="margin-bottom: 30px;">
							<label for="ndizi_stripe_webhook_secret" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Stripe Webhook Signing Secret', 'ndizi-project-management' ); ?></label>
							<?php if ( $stripe_webhook_locked ) : ?>
								<p class="description" style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Set via NDIZI_STRIPE_WEBHOOK_SECRET constant.', 'ndizi-project-management' ); ?></p>
							<?php else : ?>
								<input type="password" name="ndizi_stripe_webhook_secret" id="ndizi_stripe_webhook_secret" value="<?php echo esc_attr( $stripe_webhook ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<?php endif; ?>
							<p class="description" style="margin-top: 5px; color: #64748b;">
								<?php esc_html_e( 'Webhook URL for Stripe Dashboard:', 'ndizi-project-management' ); ?>
								<code><?php echo esc_url( home_url( '/wp-json/ndizi/v1/stripe/webhook' ) ); ?></code>
							</p>
						</div>
					<?php endif; ?>

					<?php if ( Ndizi_Project_Management::is_module_active( 'calendar' ) ) : ?>
						<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Google Calendar Integration', 'ndizi-project-management' ); ?></h2>
						<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Sync due tasks and project milestones with Google Calendar.', 'ndizi-project-management' ); ?></p>

						<?php
						$google_cid        = Ndizi_Project_Management::get_secret( 'ndizi_google_client_id' );
						$google_cid_locked = defined( 'NDIZI_GOOGLE_CLIENT_ID' );
						$google_secret     = Ndizi_Project_Management::get_secret( 'ndizi_google_client_secret' );
						$google_sec_locked = defined( 'NDIZI_GOOGLE_CLIENT_SECRET' );
						?>
						<div style="margin-bottom: 20px;">
							<label for="ndizi_google_client_id" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Google Client ID', 'ndizi-project-management' ); ?></label>
							<?php if ( $google_cid_locked ) : ?>
								<p class="description" style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Set via NDIZI_GOOGLE_CLIENT_ID constant.', 'ndizi-project-management' ); ?></p>
							<?php else : ?>
								<input type="text" name="ndizi_google_client_id" id="ndizi_google_client_id" value="<?php echo esc_attr( $google_cid ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<?php endif; ?>
						</div>

						<div style="margin-bottom: 20px;">
							<label for="ndizi_google_client_secret" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Google Client Secret', 'ndizi-project-management' ); ?></label>
							<?php if ( $google_sec_locked ) : ?>
								<p class="description" style="color: #64748b; font-style: italic;"><?php esc_html_e( 'Set via NDIZI_GOOGLE_CLIENT_SECRET constant.', 'ndizi-project-management' ); ?></p>
							<?php else : ?>
								<input type="password" name="ndizi_google_client_secret" id="ndizi_google_client_secret" value="<?php echo esc_attr( $google_secret ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<?php endif; ?>
							<p class="description" style="margin-top: 5px; color: #64748b;">
								<?php esc_html_e( 'OAuth Redirect URI for Google Cloud Console:', 'ndizi-project-management' ); ?>
								<code><?php echo esc_url( admin_url( 'admin.php?page=ndizi-settings' ) ); ?></code>
							</p>
						</div>

						<div style="margin-bottom: 30px;">
							<?php
							$google_refresh_token = Ndizi_Project_Management::get_secret( 'ndizi_google_refresh_token' );
							if ( $google_refresh_token ) :
								?>
								<p style="color: #16a34a; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 6px;">
									<span class="dashicons dashicons-yes-alt" style="color: #16a34a;"></span>
									<?php esc_html_e( 'Connected to Google Calendar!', 'ndizi-project-management' ); ?>
								</p>
							<?php else : ?>
								<?php
								$auth_url = '';
								if ( $google_cid ) {
									$auth_url = add_query_arg(
										array(
											'client_id'    => $google_cid,
											'redirect_uri' => admin_url( 'admin.php?page=ndizi-settings' ),
											'response_type' => 'code',
											'scope'        => 'https://www.googleapis.com/auth/calendar',
											'access_type'  => 'offline',
											'prompt'       => 'consent',
											'state'        => wp_create_nonce( 'ndizi_google_oauth_state' ),
										),
										'https://accounts.google.com/o/oauth2/v2/auth'
									);
								}
								?>
								<?php if ( $auth_url ) : ?>
									<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Connect to Google Calendar', 'ndizi-project-management' ); ?></a>
								<?php else : ?>
									<p class="description" style="color: #dc2626;"><?php esc_html_e( 'Enter Client ID and Secret, save changes, and then click Connect.', 'ndizi-project-management' ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</div>

						<div style="margin-bottom: 20px; border-top: 1px dashed #e2e8f0; padding-top: 20px;">
							<?php
							$feed_token = get_option( 'ndizi_calendar_feed_token', '' );
							if ( ! $feed_token ) {
								$feed_token = wp_hash( time() . wp_generate_password( 20, false ) );
								update_option( 'ndizi_calendar_feed_token', $feed_token );
							}
							$feed_url = home_url( '/wp-json/ndizi/v1/calendar/ical?token=' . $feed_token );
							?>
							<label style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'iCal Subscription URL', 'ndizi-project-management' ); ?></label>
							<input type="text" value="<?php echo esc_url( $feed_url ); ?>" readonly style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; background-color: #f8fafc;" class="ndizi-select-on-click">
							<p class="description" style="margin-top: 5px; color: #64748b;"><?php esc_html_e( 'Subscribe to this feed URL in Google Calendar, Apple Calendar, or Outlook to view project milestones and task due dates.', 'ndizi-project-management' ); ?></p>
						</div>
					<?php endif; ?>

					<?php if ( Ndizi_Project_Management::is_module_active( 'tracker' ) ) : ?>
						<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Bookmarklet Quick Tracker', 'ndizi-project-management' ); ?></h2>
						<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Drag the button below to your browser bookmarks bar to track time with one click from any website tab.', 'ndizi-project-management' ); ?></p>

						<div style="margin-bottom: 30px;">
							<a href="javascript:(function(){var title=encodeURIComponent(document.title);var url=encodeURIComponent(window.location.href);window.open('<?php echo esc_js( admin_url( 'admin.php?page=ndizi-tracker-standalone' ) ); ?>&desc='+title+' '+url,'ndizi_tracker','width=380,height=640,resizable=yes,scrollbars=yes');})()" style="background:#4f46e5;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);"><?php esc_html_e( 'Track Time ➔', 'ndizi-project-management' ); ?></a>
						</div>
					<?php endif; ?>

					<?php if ( Ndizi_Project_Management::is_module_active( 'integrations' ) ) : ?>
						<h2 style="font-size: 18px; font-weight: 600; color: #1e293b; margin: 30px 0 8px 0; border-top: 1px solid #e2e8f0; padding-top: 24px;"><?php esc_html_e( 'Webhooks & Slack Settings', 'ndizi-project-management' ); ?></h2>
						<p style="color: #64748b; font-size: 14px; margin: 0 0 24px 0;"><?php esc_html_e( 'Configure outbound webhook endpoints to connect Ndizi with external systems or Slack.', 'ndizi-project-management' ); ?></p>

						<div style="margin-bottom: 20px;">
							<?php $webhook_url = get_option( 'ndizi_webhook_url', '' ); ?>
							<label for="ndizi_webhook_url" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Outbound Webhook URL', 'ndizi-project-management' ); ?></label>
							<input type="url" name="ndizi_webhook_url" id="ndizi_webhook_url" value="<?php echo esc_url( $webhook_url ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<p class="description" style="margin-top: 5px; color: #64748b;"><?php esc_html_e( 'The URL where JSON payloads will be POSTed on events.', 'ndizi-project-management' ); ?></p>
						</div>

						<div style="margin-bottom: 30px;">
							<?php $slack_webhook_url = get_option( 'ndizi_slack_webhook_url', '' ); ?>
							<label for="ndizi_slack_webhook_url" style="display: block; font-weight: 600; color: #475569; margin-bottom: 8px;"><?php esc_html_e( 'Slack Webhook URL', 'ndizi-project-management' ); ?></label>
							<input type="url" name="ndizi_slack_webhook_url" id="ndizi_slack_webhook_url" value="<?php echo esc_url( $slack_webhook_url ); ?>" style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px;">
							<p class="description" style="margin-top: 5px; color: #64748b;"><?php esc_html_e( 'Your Slack incoming webhook URL for formatting alerts.', 'ndizi-project-management' ); ?></p>
						</div>
					<?php endif; ?>

					<button type="submit" class="button button-primary" style="background: #4f46e5 !important; border-color: #4f46e5 !important; color: #fff !important; padding: 0 24px !important; height: 40px !important; font-size: 14px !important; border-radius: 6px !important; font-weight: 600 !important; cursor: pointer; transition: background 0.2s;">
						<?php esc_html_e( 'Save Changes', 'ndizi-project-management' ); ?>
					</button>
				</form>
			</div>

			<script>
				jQuery(document).ready(function($) {
					$('input[name="ndizi_adminbar_icon"]').on('change', function() {
						$('input[name="ndizi_adminbar_icon"]').next('.ndizi-icon-card').css({
							'border-color': '#e2e8f0',
							'background': '#fff'
						}).find('div').css('color', '#64748b');

						if($(this).is(':checked')) {
							$(this).next('.ndizi-icon-card').css({
								'border-color': '#4f46e5',
								'background': '#f5f3ff'
							}).find('div').css('color', '#4f46e5');

							// Swap the SVG in the admin bar live!
							var iconVal = $(this).val();
							var iconClass = iconVal === 'punch_clock' ? 'punch' : iconVal;
							var $newSvg = $(this).next('.ndizi-icon-card').find('svg').clone();
							$newSvg.attr('class', 'ndizi-ab-icon-svg ndizi-ab-icon-' + iconClass);
							$newSvg.attr('width', '16');
							$newSvg.attr('height', '16');

							var $iconWrapper = $('#wp-admin-bar-ndizi-time-tracker .ndizi-ab-icon-wrapper');
							if ($iconWrapper.length) {
								$iconWrapper.find('svg').replaceWith($newSvg);
							}
						}
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Add Ndizi Billing Rate to the user profile page.
	 */
	public static function render_user_profile_fields( $user ) {
		if ( ! current_user_can( 'ndizi_manage_time' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			return;
		}

		$billing_rate = get_user_meta( $user->ID, '_ndizi_user_billing_rate', true );
		$salary_rate  = get_user_meta( $user->ID, '_ndizi_user_salary_rate', true );
		?>
		<h2><?php esc_html_e( 'Ndizi Project Management Settings', 'ndizi-project-management' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="ndizi_user_billing_rate"><?php esc_html_e( 'Billing Rate ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="number" name="ndizi_user_billing_rate" id="ndizi_user_billing_rate" value="<?php echo esc_attr( $billing_rate ); ?>" class="regular-text" step="0.01" min="0">
					<p class="description"><?php esc_html_e( 'The hourly billing rate for this user. Used to auto-calculate invoice amounts if no task rate is set.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_user_salary_rate"><?php esc_html_e( 'Salary Rate ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="number" name="ndizi_user_salary_rate" id="ndizi_user_salary_rate" value="<?php echo esc_attr( $salary_rate ); ?>" class="regular-text" step="0.01" min="0">
					<p class="description"><?php esc_html_e( 'The hourly salary rate (internal cost) for this user. Used to calculate internal costs and project margins.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save Ndizi Billing Rate user profile field.
	 */
	public static function save_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'ndizi_manage_time' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
		if ( isset( $_POST['ndizi_user_billing_rate'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
			update_user_meta( $user_id, '_ndizi_user_billing_rate', max( 0.0, floatval( $_POST['ndizi_user_billing_rate'] ) ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
		if ( isset( $_POST['ndizi_user_salary_rate'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
			update_user_meta( $user_id, '_ndizi_user_salary_rate', max( 0.0, floatval( $_POST['ndizi_user_salary_rate'] ) ) );
		}
	}

	public static function render_time_entries_page() {
		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'ndizi-project-management' ) );
		}
		?>
		<div class="wrap">
			<div id="ndizi-time-entries-app"></div>
		</div>
		<?php
	}
}

