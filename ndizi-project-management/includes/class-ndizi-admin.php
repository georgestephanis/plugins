<?php
/**
 * Admin interface handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Admin {

	/**
	 * Initialize admin hooks
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'save_settings_page' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ) );

		// User profile billing rate fields
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_profile_fields' ) );

		// Custom columns in listings
		add_filter( 'manage_ndizi_client_posts_columns', array( __CLASS__, 'add_client_columns' ) );
		add_action( 'manage_ndizi_client_posts_custom_column', array( __CLASS__, 'render_client_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_project_posts_columns', array( __CLASS__, 'add_project_columns' ) );
		add_action( 'manage_ndizi_project_posts_custom_column', array( __CLASS__, 'render_project_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_task_posts_columns', array( __CLASS__, 'add_task_columns' ) );
		add_action( 'manage_ndizi_task_posts_custom_column', array( __CLASS__, 'render_task_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_invoice_posts_columns', array( __CLASS__, 'add_invoice_columns' ) );
		add_action( 'manage_ndizi_invoice_posts_custom_column', array( __CLASS__, 'render_invoice_columns' ), 10, 2 );

		// Admin menus.
		// Priority 9 so the top-level menu and its Dashboard submenu are registered
		// before core's _add_post_type_submenus() (admin_menu, priority 10) appends
		// the CPT submenus; otherwise the first CPT (clients) becomes the top-level
		// menu's click target instead of the dashboard.
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ), 9 );

		// Hook time entries update via invoice aggregation save
		add_action( 'wp_ajax_ndizi_aggregate_invoice_time', array( __CLASS__, 'ajax_aggregate_invoice_time' ) );
		add_action( 'wp_ajax_ndizi_start_timer_action', array( __CLASS__, 'ajax_start_timer' ) );
		add_action( 'wp_ajax_ndizi_stop_timer_action', array( __CLASS__, 'ajax_stop_timer' ) );
		add_action( 'wp_ajax_ndizi_delete_log_action', array( __CLASS__, 'ajax_delete_log' ) );
		add_action( 'wp_ajax_ndizi_check_active_timer', array( __CLASS__, 'ajax_check_active_timer' ) );
		add_action( 'wp_ajax_ndizi_refresh_logs_table', array( __CLASS__, 'ajax_refresh_logs_table' ) );

		// Restrict task list view for team members
		add_filter( 'pre_get_posts', array( __CLASS__, 'restrict_posts_query' ) );
	}

	/**
	 * Intercept settings save on admin_init hook to run before admin bar is rendered
	 */
	public static function save_settings_page() {
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
	 * Restrict default wp-admin list query for Team Members
	 */
	public static function restrict_posts_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if we are viewing tasks in the admin list
		if ( 'ndizi_task' === $query->get( 'post_type' ) ) {
			// If Team Member (i.e. cannot manage tasks), restrict to assigned tasks.
			if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
				// Merge into any existing meta_query rather than overwriting it,
				// so we don't clobber core/other-plugin list filters.
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query[] = array(
					'key'   => '_ndizi_assigned_user_id',
					'value' => get_current_user_id(),
				);
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Enqueue stylesheet and javascript
	 */
	public static function enqueue_assets() {
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
			array( __CLASS__, 'render_reports_page' )
		);

		// Submenu: Gantt Chart
		if ( Ndizi_Project_Management::is_module_active( 'gantt' ) ) {
			add_submenu_page(
				'ndizi-pm',
				__( 'Ndizi Gantt Chart', 'ndizi-project-management' ),
				__( 'Gantt Chart', 'ndizi-project-management' ),
				'ndizi_view_projects',
				'ndizi-gantt',
				array( __CLASS__, 'render_gantt_page' )
			);
		}

		// Submenu: Settings
		add_submenu_page(
			'ndizi-pm',
			__( 'Ndizi Settings', 'ndizi-project-management' ),
			__( 'Settings', 'ndizi-project-management' ),
			'manage_options',
			'ndizi-settings',
			array( __CLASS__, 'render_settings_page' )
		);

		// Submenu: Standalone Tracker
		add_submenu_page(
			'ndizi-pm',
			__( 'Standalone Tracker', 'ndizi-project-management' ),
			__( 'Standalone Tracker', 'ndizi-project-management' ),
			'ndizi_log_time',
			'ndizi-tracker-standalone',
			array( 'Ndizi_Standalone_Tracker', 'render_standalone_page' )
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
					'meta_key'       => '_ndizi_project_status',
					'meta_value'     => 'active',
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
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_client' ) ); ?>" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
							<span class="dashicons dashicons-networking" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Add New Client', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_project' ) ); ?>" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
							<span class="dashicons dashicons-portfolio" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Create New Project', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_task' ) ); ?>" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
							<span class="dashicons dashicons-yes" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Create New Task', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ndizi_invoice' ) ); ?>" style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-decoration: none; color: #1e293b; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#e2e8f0';">
							<span class="dashicons dashicons-analytics" style="margin-right: 10px; color: #4f46e5;"></span>
							<?php esc_html_e( 'Generate Invoice', 'ndizi-project-management' ); ?>
						</a>
					</div>
				</div>

				<!-- Navigation Quick Links -->
				<div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid #e2e8f0;">
					<h2 style="margin: 0 0 20px 0; font-size: 20px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;"><?php esc_html_e( 'Views & Reports', 'ndizi-project-management' ); ?></h2>
					<div style="display: flex; flex-direction: column; gap: 10px;">
						<a href="#" onclick="window.open('<?php echo esc_url( admin_url( 'admin.php?page=ndizi-tracker-standalone' ) ); ?>', 'ndizi_tracker', 'width=380,height=640,resizable=yes,scrollbars=yes'); return false;" style="display: block; background: #eab308; color: #0f172a; text-align: center; font-weight: 700; padding: 12px; border-radius: 6px; text-decoration: none; transition: background 0.2s; box-shadow: 0 4px 12px rgba(234, 179, 8, 0.15);" onmouseover="this.style.background='#ca8a04';" onmouseout="this.style.background='#eab308';">
							<span class="dashicons dashicons-external" style="margin-right: 6px; vertical-align: middle; font-size: 18px; width: 18px; height: 18px; color: #0f172a;"></span>
							<?php esc_html_e( 'Launch Standalone Tracker', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-reports' ) ); ?>" style="display: block; background: #4f46e5; color: #fff; text-align: center; font-weight: 600; padding: 12px; border-radius: 6px; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#4338ca';" onmouseout="this.style.background='#4f46e5';">
							<?php esc_html_e( 'View Productivity Reports', 'ndizi-project-management' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-gantt' ) ); ?>" style="display: block; background: #f8fafc; border: 1px solid #cbd5e1; color: #1e293b; text-align: center; font-weight: 600; padding: 12px; border-radius: 6px; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0';" onmouseout="this.style.background='#f8fafc';">
							<?php esc_html_e( 'View Gantt Charts', 'ndizi-project-management' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add custom columns to Clients list
	 */
	public static function add_client_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['projects_count'] = __( 'Projects', 'ndizi-project-management' );
				$new_columns['client_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['client_key']     = __( 'Portal Key', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Clients list
	 */
	public static function render_client_columns( $column, $post_id ) {
		if ( 'projects_count' === $column ) {
			$projects = get_posts(
				array(
					'post_type'      => 'ndizi_project',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_client_id',
							'value' => $post_id,
						),
					),
				)
			);
			echo count( $projects );
		} elseif ( 'client_status' === $column ) {
			$status       = get_post_meta( $post_id, '_ndizi_client_status', true );
			$status_label = ( 'archived' === $status ) ? __( 'Archived', 'ndizi-project-management' ) : __( 'Active', 'ndizi-project-management' );
			$status_class = ( 'archived' === $status ) ? 'ndizi-badge-archived' : 'ndizi-badge-active';
			echo '<span class="ndizi-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} elseif ( 'client_key' === $column ) {
			$key = get_post_meta( $post_id, '_ndizi_client_auth_key', true );
			echo '<code>' . esc_html( $key ? $key : '-' ) . '</code>';
		}
	}

	/**
	 * Add custom columns to Projects list
	 */
	public static function add_project_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['project_client'] = __( 'Client', 'ndizi-project-management' );
				$new_columns['project_status'] = __( 'Status', 'ndizi-project-management' );
				$new_columns['project_time']   = __( 'Time Tracked', 'ndizi-project-management' );
				$new_columns['project_budget'] = __( 'Budget', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Projects list
	 */
	public static function render_project_columns( $column, $post_id ) {
		if ( 'project_client' === $column ) {
			$client_id = get_post_meta( $post_id, '_ndizi_client_id', true );
			if ( $client_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">' . esc_html( get_the_title( $client_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'project_status' === $column ) {
			$status       = get_post_meta( $post_id, '_ndizi_project_status', true );
			$status_label = ( 'archived' === $status ) ? __( 'Archived', 'ndizi-project-management' ) : __( 'Active', 'ndizi-project-management' );
			$status_class = ( 'archived' === $status ) ? 'ndizi-badge-archived' : 'ndizi-badge-active';
			echo '<span class="ndizi-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} elseif ( 'project_time' === $column ) {
			$totals = Ndizi_DB::get_time_totals( array( 'project_id' => $post_id ) );
			$sec    = ! empty( $totals ) ? $totals[0]->total_duration : 0;
			$hours  = round( $sec / 3600, 2 );
			echo esc_html( $hours ) . 'h';
		} elseif ( 'project_budget' === $column ) {
			$budget = get_post_meta( $post_id, '_ndizi_project_budget', true );
			echo $budget ? '$' . esc_html( number_format( $budget, 2 ) ) : '-';
		}
	}

	/**
	 * Add custom columns to Tasks list
	 */
	public static function add_task_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['task_project']  = __( 'Project', 'ndizi-project-management' );
				$new_columns['task_assignee'] = __( 'Assignee', 'ndizi-project-management' );
				$new_columns['task_status']   = __( 'Status', 'ndizi-project-management' );
				$new_columns['task_priority'] = __( 'Priority', 'ndizi-project-management' );
				$new_columns['task_due_date'] = __( 'Due Date', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Tasks list
	 */
	public static function render_task_columns( $column, $post_id ) {
		if ( 'task_project' === $column ) {
			$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $project_id ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'task_assignee' === $column ) {
			$assignee_id = get_post_meta( $post_id, '_ndizi_assigned_user_id', true );
			if ( $assignee_id ) {
				$user = get_userdata( $assignee_id );
				echo $user ? esc_html( $user->display_name ) : '-';
			} else {
				echo '<em>' . esc_html__( 'Unassigned', 'ndizi-project-management' ) . '</em>';
			}
		} elseif ( 'task_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_task_status', true );
			$labels = array(
				'open'        => __( 'Open', 'ndizi-project-management' ),
				'in_progress' => __( 'In Progress', 'ndizi-project-management' ),
				'completed'   => __( 'Completed', 'ndizi-project-management' ),
				'cancelled'   => __( 'Cancelled', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Open', 'ndizi-project-management' );
			echo '<span class="ndizi-badge ndizi-task-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'task_priority' === $column ) {
			$priority = get_post_meta( $post_id, '_ndizi_task_priority', true );
			$labels   = array(
				'low'    => __( 'Low', 'ndizi-project-management' ),
				'medium' => __( 'Medium', 'ndizi-project-management' ),
				'high'   => __( 'High', 'ndizi-project-management' ),
			);
			$label    = isset( $labels[ $priority ] ) ? $labels[ $priority ] : __( 'Medium', 'ndizi-project-management' );
			echo '<span class="ndizi-priority-' . esc_attr( $priority ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'task_due_date' === $column ) {
			$due = get_post_meta( $post_id, '_ndizi_task_due_date', true );
			echo $due ? esc_html( $due ) : '-';
		}
	}

	/**
	 * Add custom columns to Invoices list
	 */
	public static function add_invoice_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['invoice_project'] = __( 'Project', 'ndizi-project-management' );
				$new_columns['invoice_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['invoice_amount']  = __( 'Amount', 'ndizi-project-management' );
				$new_columns['invoice_due']     = __( 'Due Date', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Invoices list
	 */
	public static function render_invoice_columns( $column, $post_id ) {
		if ( 'invoice_project' === $column ) {
			$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $project_id ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'invoice_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_invoice_status', true );
			$labels = array(
				'draft' => __( 'Draft', 'ndizi-project-management' ),
				'sent'  => __( 'Sent', 'ndizi-project-management' ),
				'paid'  => __( 'Paid', 'ndizi-project-management' ),
				'void'  => __( 'Void', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Draft', 'ndizi-project-management' );
			echo '<span class="ndizi-badge ndizi-invoice-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'invoice_amount' === $column ) {
			$amount = get_post_meta( $post_id, '_ndizi_invoice_amount', true );
			echo $amount ? '$' . esc_html( number_format( $amount, 2 ) ) : '-';
		} elseif ( 'invoice_due' === $column ) {
			$due = get_post_meta( $post_id, '_ndizi_invoice_due_date', true );
			echo $due ? esc_html( $due ) : '-';
		}
	}

	/**
	 * Add Meta Boxes
	 */
	public static function add_meta_boxes() {
		// Client Meta Box
		add_meta_box( 'ndizi_client_details', __( 'Client Details', 'ndizi-project-management' ), array( __CLASS__, 'render_client_meta_box' ), 'ndizi_client', 'normal', 'high' );

		// Project Meta Box
		add_meta_box( 'ndizi_project_details', __( 'Project Details', 'ndizi-project-management' ), array( __CLASS__, 'render_project_meta_box' ), 'ndizi_project', 'normal', 'high' );
		add_meta_box( 'ndizi_project_time', __( 'Time Log / Tracker', 'ndizi-project-management' ), array( __CLASS__, 'render_project_time_meta_box' ), 'ndizi_project', 'normal', 'default' );

		// Task Meta Box
		add_meta_box( 'ndizi_task_details', __( 'Task Details', 'ndizi-project-management' ), array( __CLASS__, 'render_task_meta_box' ), 'ndizi_task', 'normal', 'high' );

		// Invoice Meta Box
		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			add_meta_box( 'ndizi_invoice_details', __( 'Invoice Details', 'ndizi-project-management' ), array( __CLASS__, 'render_invoice_meta_box' ), 'ndizi_invoice', 'normal', 'high' );
		}

		// Contact Meta Box
		add_meta_box( 'ndizi_contact_details', __( 'Contact Details', 'ndizi-project-management' ), array( __CLASS__, 'render_contact_meta_box' ), 'ndizi_contact', 'normal', 'high' );
	}

	/**
	 * Render Client Meta Box
	 */
	public static function render_client_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_client', 'ndizi_client_nonce' );

		$website = get_post_meta( $post->ID, '_ndizi_client_website', true );
		$address = get_post_meta( $post->ID, '_ndizi_client_address', true );
		$key     = get_post_meta( $post->ID, '_ndizi_client_auth_key', true );
		$status  = get_post_meta( $post->ID, '_ndizi_client_status', true );

		if ( empty( $key ) ) {
			$key = wp_generate_password( 16, false );
		}
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_client_website"><?php esc_html_e( 'Website URL', 'ndizi-project-management' ); ?></label></th>
				<td><input type="url" name="ndizi_client_website" id="ndizi_client_website" value="<?php echo esc_url( $website ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_address"><?php esc_html_e( 'Billing Address', 'ndizi-project-management' ); ?></label></th>
				<td><textarea name="ndizi_client_address" id="ndizi_client_address" class="large-text" rows="3"><?php echo esc_textarea( $address ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_status"><?php esc_html_e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_status" id="ndizi_client_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived / Inactive', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_client_auth_key"><?php esc_html_e( 'Portal Key', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_client_auth_key" id="ndizi_client_auth_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" readonly>
					<button type="button" class="button ndizi-regen-key-btn"><?php esc_html_e( 'Regenerate Key', 'ndizi-project-management' ); ?></button>
					<p class="description"><?php esc_html_e( 'Used for frontend access authentication.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Project Meta Box
	 */
	public static function render_project_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_project', 'ndizi_project_nonce' );

		$client_id   = get_post_meta( $post->ID, '_ndizi_client_id', true );
		$start_date  = get_post_meta( $post->ID, '_ndizi_project_start_date', true );
		$end_date    = get_post_meta( $post->ID, '_ndizi_project_end_date', true );
		$budget      = get_post_meta( $post->ID, '_ndizi_project_budget', true );
		$hourly_rate = get_post_meta( $post->ID, '_ndizi_project_hourly_rate', true );
		$status      = get_post_meta( $post->ID, '_ndizi_project_status', true );

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_client_id"><?php esc_html_e( 'Client', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_id" id="ndizi_client_id" required>
						<option value=""><?php esc_html_e( '-- Select Client --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $clients as $client ) : ?>
							<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $client_id, $client->ID ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_project_start_date"><?php esc_html_e( 'Start Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_start_date" id="ndizi_project_start_date" value="<?php echo esc_attr( $start_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_end_date"><?php esc_html_e( 'End/Target Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_end_date" id="ndizi_project_end_date" value="<?php echo esc_attr( $end_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_budget"><?php esc_html_e( 'Budget ($)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_project_budget" id="ndizi_project_budget" value="<?php echo esc_attr( $budget ); ?>" class="small-text"></td>
			</tr>
			<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
			<tr>
				<th><label for="ndizi_project_hourly_rate"><?php esc_html_e( 'Default Hourly Rate ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_project_hourly_rate" id="ndizi_project_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" class="small-text"></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="ndizi_project_status"><?php esc_html_e( 'Project Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_status" id="ndizi_project_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Project Time Logs Meta Box
	 */
	public static function render_project_time_meta_box( $post ) {
		$user_id           = get_current_user_id();
		$active            = Ndizi_DB::get_active_timer( $user_id );
		$is_active_on_this = $active && ( intval( $active->project_id ) === $post->ID );

		// Load tasks for this project to log against
		$tasks = get_posts(
			array(
				'post_type'      => 'ndizi_task',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_project_id',
						'value' => $post->ID,
					),
				),
			)
		);

		// Load historical logs for this project
		$logs = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $post->ID,
				'number'     => 15,
			)
		);
		?>
		<div class="ndizi-tracker-wrapper">
			<!-- Timer controls -->
			<div class="ndizi-tracker-controls">
				<h4><?php esc_html_e( 'Live Time Tracker', 'ndizi-project-management' ); ?></h4>
				<div class="ndizi-timer-bar <?php echo $is_active_on_this ? 'ndizi-timer-running' : ''; ?>">
					<div class="ndizi-timer-fields">
						<select id="ndizi_tracker_task_id">
							<option value="0"><?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?></option>
							<?php foreach ( $tasks as $task ) : ?>
								<option value="<?php echo esc_attr( $task->ID ); ?>">
									<?php echo esc_html( $task->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" id="ndizi_tracker_desc" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" class="regular-text">
						<label class="ndizi-checkbox-label">
							<input type="checkbox" id="ndizi_tracker_billable" value="1" checked> <?php esc_html_e( 'Billable', 'ndizi-project-management' ); ?>
						</label>
					</div>

					<div class="ndizi-timer-action">
						<span class="ndizi-live-clock">00:00:00</span>
						<?php if ( $is_active_on_this ) : ?>
							<button type="button" class="button button-primary ndizi-btn-stop" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
								<?php esc_html_e( 'Stop', 'ndizi-project-management' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary ndizi-btn-start" data-project-id="<?php echo esc_attr( $post->ID ); ?>" <?php disabled( $active !== false ); ?>>
								<?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $active && ! $is_active_on_this ) : ?>
					<p class="description error-message">
						<?php esc_html_e( 'You already have an active timer running on another project. Stop it first to track here.', 'ndizi-project-management' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<hr>

			<!-- Log List -->
			<div class="ndizi-tracker-logs">
				<h4><?php esc_html_e( 'Recent Time Logs', 'ndizi-project-management' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-date"><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
							<th class="column-user"><?php esc_html_e( 'User', 'ndizi-project-management' ); ?></th>
							<th class="column-task"><?php esc_html_e( 'Task', 'ndizi-project-management' ); ?></th>
							<th class="column-desc"><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
							<th class="column-duration"><?php esc_html_e( 'Duration', 'ndizi-project-management' ); ?></th>
							<th class="column-billable"><?php esc_html_e( 'Billable', 'ndizi-project-management' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Action', 'ndizi-project-management' ); ?></th>
						</tr>
					</thead>
					<tbody id="ndizi_logs_table_body">
						<?php if ( empty( $logs ) ) : ?>
							<tr class="no-items"><td colspan="7"><?php esc_html_e( 'No time logged yet on this project.', 'ndizi-project-management' ); ?></td></tr>
						<?php else : ?>
							<?php
							foreach ( $logs as $log ) :
								$log_user = get_userdata( $log->user_id );
								$log_task = $log->task_id ? get_post( $log->task_id ) : null;
								?>
								<tr id="ndizi-log-row-<?php echo esc_attr( $log->id ); ?>">
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log->start_time ) ) ); ?></td>
									<td><?php echo $log_user ? esc_html( $log_user->display_name ) : '-'; ?></td>
									<td><?php echo $log_task ? esc_html( $log_task->post_title ) : '<em>-</em>'; ?></td>
									<td><?php echo esc_html( $log->description ); ?></td>
									<td><strong><?php echo esc_html( round( $log->duration / 3600, 2 ) ); ?>h</strong></td>
									<td>
										<span class="dashicons <?php echo $log->billable ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
									</td>
									<td>
										<button type="button" class="button button-link ndizi-delete-log-btn" data-id="<?php echo esc_attr( $log->id ); ?>">
											<span class="dashicons dashicons-trash"></span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Task Meta Box
	 */
	public static function render_task_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_task', 'ndizi_task_nonce' );

		$project_id       = get_post_meta( $post->ID, '_ndizi_project_id', true );
		$assignee_id      = get_post_meta( $post->ID, '_ndizi_assigned_user_id', true );
		$status           = get_post_meta( $post->ID, '_ndizi_task_status', true );
		$priority         = get_post_meta( $post->ID, '_ndizi_task_priority', true );
		$due_date         = get_post_meta( $post->ID, '_ndizi_task_due_date', true );
		$task_hourly_rate = get_post_meta( $post->ID, '_ndizi_task_hourly_rate', true );

		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);

		$users = get_users(
			array(
				'capability' => 'ndizi_log_time',
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_project_id" required>
						<option value=""><?php esc_html_e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_assigned_user_id"><?php esc_html_e( 'Assigned To', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_assigned_user_id" id="ndizi_assigned_user_id">
						<option value="0"><?php esc_html_e( 'Unassigned', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $users as $u ) : ?>
							<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $assignee_id, $u->ID ); ?>>
								<?php echo esc_html( $u->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_status"><?php esc_html_e( 'Task Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_status" id="ndizi_task_status">
						<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Open', 'ndizi-project-management' ); ?></option>
						<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'ndizi-project-management' ); ?></option>
						<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'ndizi-project-management' ); ?></option>
						<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_priority"><?php esc_html_e( 'Priority', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_priority" id="ndizi_task_priority">
						<option value="low" <?php selected( $priority, 'low' ); ?>><?php esc_html_e( 'Low', 'ndizi-project-management' ); ?></option>
						<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php esc_html_e( 'Medium', 'ndizi-project-management' ); ?></option>
						<option value="high" <?php selected( $priority, 'high' ); ?>><?php esc_html_e( 'High', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_due_date"><?php esc_html_e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_task_due_date" id="ndizi_task_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
			<tr>
				<th><label for="ndizi_task_hourly_rate"><?php esc_html_e( 'Hourly Rate Override ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_task_hourly_rate" id="ndizi_task_hourly_rate" value="<?php echo esc_attr( $task_hourly_rate ); ?>" class="small-text"></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render Invoice Meta Box
	 */
	public static function render_invoice_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_invoice', 'ndizi_invoice_nonce' );

		$project_id   = get_post_meta( $post->ID, '_ndizi_project_id', true );
		$invoice_date = get_post_meta( $post->ID, '_ndizi_invoice_date', true );
		$due_date     = get_post_meta( $post->ID, '_ndizi_invoice_due_date', true );
		$amount       = get_post_meta( $post->ID, '_ndizi_invoice_amount', true );
		$status       = get_post_meta( $post->ID, '_ndizi_invoice_status', true );

		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);

		// Load billable time entries that belong to this project and either belong to THIS invoice OR have invoice_id = 0
		$time_entries = array();
		if ( $project_id ) {
			global $wpdb;
			$table_name   = Ndizi_DB::get_table_name();
			$time_entries = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table_name WHERE project_id = %d AND billable = 1 AND (invoice_id = 0 OR invoice_id = %d) ORDER BY start_time DESC",
					$project_id,
					$post->ID
				)
			);
		}
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_invoice_project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_invoice_project_id" required>
						<option value=""><?php esc_html_e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<?php $proj_rate = get_post_meta( $project->ID, '_ndizi_project_hourly_rate', true ); ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?> data-rate="<?php echo esc_attr( $proj_rate ); ?>">
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_date"><?php esc_html_e( 'Invoice Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_date" id="ndizi_invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_due_date"><?php esc_html_e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_due_date" id="ndizi_invoice_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_amount"><?php esc_html_e( 'Amount ($)', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="number" step="0.01" name="ndizi_invoice_amount" id="ndizi_invoice_amount" value="<?php echo esc_attr( $amount ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Total amount for this invoice. Can be manually overridden or aggregated from time entries below.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_status"><?php esc_html_e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_invoice_status" id="ndizi_invoice_status">
						<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'ndizi-project-management' ); ?></option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'ndizi-project-management' ); ?></option>
						<option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'ndizi-project-management' ); ?></option>
						<option value="void" <?php selected( $status, 'void' ); ?>><?php esc_html_e( 'Void', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<?php if ( $project_id ) : ?>
				<tr>
					<th><?php esc_html_e( 'Aggregate Time Entries', 'ndizi-project-management' ); ?></th>
					<td>
						<div class="ndizi-invoice-time-picker">
							<p class="description"><?php esc_html_e( 'Select the billable time entries to include on this invoice:', 'ndizi-project-management' ); ?></p>
							<div class="ndizi-invoice-time-scroll">
								<?php if ( empty( $time_entries ) ) : ?>
									<p><em><?php esc_html_e( 'No uninvoiced billable time entries found for this project.', 'ndizi-project-management' ); ?></em></p>
								<?php else : ?>
									<table class="widefat striped">
										<thead>
											<tr>
												<th><input type="checkbox" id="ndizi_select_all_invoice_time"></th>
												<th><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'User', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Rate ($/h)', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Subtotal ($)', 'ndizi-project-management' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $time_entries as $entry ) :
												$entry_user = get_userdata( $entry->user_id );
												$is_linked  = ( intval( $entry->invoice_id ) === $post->ID );

												// Resolve the billing rate hierarchically: Task Override -> User Billing Rate -> Project Default Rate
												$resolved_rate = 0;
												if ( $entry->task_id ) {
													$resolved_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
												}
												if ( ! $resolved_rate && $entry->user_id ) {
													$resolved_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
												}
												if ( ! $resolved_rate && $entry->project_id ) {
													$resolved_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
												}
												$resolved_rate = floatval( $resolved_rate );
												$subtotal      = round( ( $entry->duration / 3600 ) * $resolved_rate, 2 );
												?>
												<tr>
													<td>
														<input type="checkbox" name="ndizi_invoice_time_entries[]" value="<?php echo esc_attr( $entry->id ); ?>" <?php checked( $is_linked ); ?> class="ndizi-invoice-time-checkbox" data-duration="<?php echo esc_attr( $entry->duration ); ?>" data-rate="<?php echo esc_attr( $resolved_rate ); ?>">
													</td>
													<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->start_time ) ) ); ?></td>
													<td><?php echo $entry_user ? esc_html( $entry_user->display_name ) : '-'; ?></td>
													<td><?php echo esc_html( $entry->description ); ?></td>
													<td><strong><?php echo esc_html( round( $entry->duration / 3600, 2 ) ); ?>h</strong></td>
													<td><?php echo $resolved_rate ? '$' . esc_html( number_format( $resolved_rate, 2 ) ) : '-'; ?></td>
													<td><?php echo $resolved_rate ? '$' . esc_html( number_format( $subtotal, 2 ) ) : '-'; ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
									<div style="margin-top: 10px;">
										<?php $project_hourly_rate = get_post_meta( $project_id, '_ndizi_project_hourly_rate', true ); ?>
										<input type="number" id="ndizi_hourly_rate" placeholder="<?php esc_attr_e( 'Rate ($/hour)', 'ndizi-project-management' ); ?>" style="width: 100px;" value="<?php echo esc_attr( $project_hourly_rate ); ?>">
										<button type="button" class="button" id="ndizi_btn_calc_invoice"><?php esc_html_e( 'Calculate & Apply Amount', 'ndizi-project-management' ); ?></button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'Time Entries', 'ndizi-project-management' ); ?></th>
					<td><p class="description"><?php esc_html_e( 'Select a Project and save/update the invoice first to see eligible time entries.', 'ndizi-project-management' ); ?></p></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render Contact Meta Box
	 */
	public static function render_contact_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_contact', 'ndizi_contact_nonce' );

		$email = get_post_meta( $post->ID, '_ndizi_contact_email', true );
		$phone = get_post_meta( $post->ID, '_ndizi_contact_phone', true );
		$role  = get_post_meta( $post->ID, '_ndizi_contact_role', true );

		$assoc_clients = get_post_meta( $post->ID, '_ndizi_associated_clients', true );
		if ( ! is_array( $assoc_clients ) ) {
			$assoc_clients = array();
		}

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_contact_email"><?php esc_html_e( 'Email Address', 'ndizi-project-management' ); ?></label></th>
				<td><input type="email" name="ndizi_contact_email" id="ndizi_contact_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_phone"><?php esc_html_e( 'Phone Number', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_phone" id="ndizi_contact_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_role"><?php esc_html_e( 'Role / Title', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_role" id="ndizi_contact_role" value="<?php echo esc_attr( $role ); ?>" class="regular-text" placeholder="e.g. Project Manager, Billing Contact"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Associated Clients', 'ndizi-project-management' ); ?></label></th>
				<td>
					<div class="ndizi-checkbox-list" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
						<?php foreach ( $clients as $client ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="ndizi_associated_clients[]" value="<?php echo esc_attr( $client->ID ); ?>" <?php checked( in_array( $client->ID, $assoc_clients, true ) ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save Meta Box Submissions
	 */
	public static function save_meta_boxes( $post_id ) {
		// Avoid autosave, revision, and bulk edit saves.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Authorization: the current user must be able to edit this specific post.
		// (Nonces below guard against CSRF; this guards against privilege escalation.)
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify the post type before each save block so a nonce from one post type
		// cannot write metadata onto a post of another type.
		$post_type = get_post_type( $post_id );

		// 1. Client Save
		if ( 'ndizi_client' === $post_type && isset( $_POST['ndizi_client_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_client_nonce'] ) ), 'ndizi_save_client' ) ) {
			if ( isset( $_POST['ndizi_client_website'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_website', esc_url_raw( wp_unslash( $_POST['ndizi_client_website'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_address'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_address', sanitize_textarea_field( wp_unslash( $_POST['ndizi_client_address'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_status', sanitize_text_field( wp_unslash( $_POST['ndizi_client_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_auth_key'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_auth_key', sanitize_text_field( wp_unslash( $_POST['ndizi_client_auth_key'] ) ) );
			}
		}

		// 2. Project Save
		if ( 'ndizi_project' === $post_type && isset( $_POST['ndizi_project_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_project_nonce'] ) ), 'ndizi_save_project' ) ) {
			if ( isset( $_POST['ndizi_client_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_id', intval( $_POST['ndizi_client_id'] ) );
			}
			if ( isset( $_POST['ndizi_project_start_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_start_date', sanitize_text_field( wp_unslash( $_POST['ndizi_project_start_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_project_end_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_end_date', sanitize_text_field( wp_unslash( $_POST['ndizi_project_end_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_project_budget'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_budget', floatval( $_POST['ndizi_project_budget'] ) );
			}
			if ( isset( $_POST['ndizi_project_hourly_rate'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_hourly_rate', floatval( $_POST['ndizi_project_hourly_rate'] ) );
			}
			if ( isset( $_POST['ndizi_project_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_status', sanitize_text_field( wp_unslash( $_POST['ndizi_project_status'] ) ) );
			}
		}

		// 3. Task Save
		if ( 'ndizi_task' === $post_type && isset( $_POST['ndizi_task_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_task_nonce'] ) ), 'ndizi_save_task' ) ) {
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_assigned_user_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_assigned_user_id', intval( $_POST['ndizi_assigned_user_id'] ) );
			}
			if ( isset( $_POST['ndizi_task_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_status', sanitize_text_field( wp_unslash( $_POST['ndizi_task_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_priority'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_priority', sanitize_text_field( wp_unslash( $_POST['ndizi_task_priority'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_due_date', sanitize_text_field( wp_unslash( $_POST['ndizi_task_due_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_hourly_rate'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_hourly_rate', floatval( $_POST['ndizi_task_hourly_rate'] ) );
			}
		}

		// 4. Invoice Save
		if ( 'ndizi_invoice' === $post_type && isset( $_POST['ndizi_invoice_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_nonce'] ) ), 'ndizi_save_invoice' ) ) {
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_date', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_due_date', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_due_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_amount'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_amount', floatval( $_POST['ndizi_invoice_amount'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_status', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_status'] ) ) );
			}

			// Clear all existing time entries linked to this invoice first, then relink selected ones
			global $wpdb;
			$table_name = Ndizi_DB::get_table_name();
			$wpdb->update(
				$table_name,
				array( 'invoice_id' => 0 ),
				array( 'invoice_id' => $post_id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( isset( $_POST['ndizi_invoice_time_entries'] ) && is_array( $_POST['ndizi_invoice_time_entries'] ) ) {
				$selected_entry_ids = array_map( 'intval', wp_unslash( $_POST['ndizi_invoice_time_entries'] ) );
				if ( ! empty( $selected_entry_ids ) ) {
					// Relink all selected entries in a single bulk query rather than one query per entry.
					$placeholders = implode( ',', array_fill( 0, count( $selected_entry_ids ), '%d' ) );
					$wpdb->query(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name and $placeholders are built from trusted internal values.
							"UPDATE $table_name SET invoice_id = %d WHERE id IN ($placeholders)",
							array_merge( array( $post_id ), $selected_entry_ids )
						)
					);
				}
			}
		}

		// 5. Contact Save
		if ( 'ndizi_contact' === $post_type && isset( $_POST['ndizi_contact_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_contact_nonce'] ) ), 'ndizi_save_contact' ) ) {
			if ( isset( $_POST['ndizi_contact_email'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_email', sanitize_email( wp_unslash( $_POST['ndizi_contact_email'] ) ) );
			}
			if ( isset( $_POST['ndizi_contact_phone'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_phone', sanitize_text_field( wp_unslash( $_POST['ndizi_contact_phone'] ) ) );
			}
			if ( isset( $_POST['ndizi_contact_role'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_role', sanitize_text_field( wp_unslash( $_POST['ndizi_contact_role'] ) ) );
			}

			$clients_array = isset( $_POST['ndizi_associated_clients'] ) && is_array( $_POST['ndizi_associated_clients'] ) ? array_map( 'intval', $_POST['ndizi_associated_clients'] ) : array();
			update_post_meta( $post_id, '_ndizi_associated_clients', $clients_array );
		}
	}

	/**
	 * AJAX logic to link time entries to an invoice
	 */
	public static function ajax_aggregate_invoice_time() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_manage_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$invoice_id = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
		$entry_ids  = isset( $_POST['entry_ids'] ) && is_array( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();
		$rate       = isset( $_POST['hourly_rate'] ) ? floatval( $_POST['hourly_rate'] ) : 0;

		if ( ! $invoice_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Invoice ID.', 'ndizi-project-management' ) ) );
		}

		global $wpdb;
		$table_name = Ndizi_DB::get_table_name();

		// Calculate total duration
		$total_sec = 0;
		if ( ! empty( $entry_ids ) ) {
			$ids_placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$total_sec = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(duration) FROM $table_name WHERE id IN ($ids_placeholders)",
					$entry_ids
				)
			);
		}

		$total_hours       = $total_sec ? ( $total_sec / 3600 ) : 0;
		$calculated_amount = round( $total_hours * $rate, 2 );

		wp_send_json_success(
			array(
				'hours'  => round( $total_hours, 2 ),
				'amount' => $calculated_amount,
			)
		);
	}

	public static function ajax_start_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		if ( Ndizi_DB::is_date_locked( current_time( 'mysql' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot start timer. The current date is locked.', 'ndizi-project-management' ) ) );
		}

		$project_id  = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		$task_id     = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$billable    = isset( $_POST['billable'] ) ? intval( $_POST['billable'] ) : 1;

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID is required.', 'ndizi-project-management' ) ) );
		}

		$user_id  = get_current_user_id();
		$timer_id = Ndizi_DB::start_timer( $user_id, $project_id, $task_id, $description, $billable );

		if ( ! $timer_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to start timer. The date may be locked or another error occurred.', 'ndizi-project-management' ) ) );
		}

		wp_send_json_success( array( 'timer_id' => $timer_id ) );
	}

	/**
	 * AJAX logic to stop a timer
	 */
	public static function ajax_stop_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$user_id = get_current_user_id();

		$active = Ndizi_DB::get_active_timer( $user_id );
		if ( $active && Ndizi_DB::is_date_locked( $active->start_time ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot stop timer. The timer start time falls in a locked period.', 'ndizi-project-management' ) ) );
		}

		$stopped = Ndizi_DB::stop_timer( $user_id );

		if ( ! $stopped ) {
			wp_send_json_error( array( 'message' => __( 'No active timer found or failed to stop.', 'ndizi-project-management' ) ) );
		}

		wp_send_json_success( array( 'timer' => $stopped ) );
	}

	/**
	 * AJAX logic to delete a log
	 */
	public static function ajax_delete_log() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		$log_id = isset( $_POST['log_id'] ) ? intval( $_POST['log_id'] ) : 0;
		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'ndizi-project-management' ) ) );
		}

		$log     = Ndizi_DB::get_time_entry( $log_id );
		$user_id = get_current_user_id();

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'ndizi-project-management' ) ) );
		}

		// Authorization: own logs, or users who can manage all time.
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete log. The time entry is in a locked period.', 'ndizi-project-management' ) ) );
		}

		$deleted = Ndizi_DB::delete_time_entry( $log_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete log.', 'ndizi-project-management' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX logic to check running timer
	 */
	public static function ajax_check_active_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		$user_id = get_current_user_id();
		$timer   = Ndizi_DB::get_active_timer( $user_id );

		if ( ! $timer ) {
			wp_send_json_success( array( 'active' => false ) );
		}

		// Add live duration
		$start_ts             = strtotime( $timer->start_time );
		$now_ts               = strtotime( current_time( 'mysql' ) );
		$timer->live_duration = max( 0, $now_ts - $start_ts );

		wp_send_json_success(
			array(
				'active' => true,
				'timer'  => $timer,
			)
		);
	}

	/**
	 * AJAX logic to refresh logs table html
	 */
	public static function ajax_refresh_logs_table() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		$project_id = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid project ID.', 'ndizi-project-management' ) ) );
		}

		$logs = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $project_id,
				'number'     => 15,
			)
		);

		ob_start();
		if ( empty( $logs ) ) {
			echo '<tr class="no-items"><td colspan="7">' . esc_html__( 'No time logged yet on this project.', 'ndizi-project-management' ) . '</td></tr>';
		} else {
			foreach ( $logs as $log ) {
				$log_user = get_userdata( $log->user_id );
				$log_task = $log->task_id ? get_post( $log->task_id ) : null;
				?>
				<tr id="ndizi-log-row-<?php echo esc_attr( $log->id ); ?>">
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log->start_time ) ) ); ?></td>
					<td><?php echo $log_user ? esc_html( $log_user->display_name ) : '-'; ?></td>
					<td><?php echo $log_task ? esc_html( $log_task->post_title ) : '<em>-</em>'; ?></td>
					<td><?php echo esc_html( $log->description ); ?></td>
					<td><strong><?php echo esc_html( round( $log->duration / 3600, 2 ) ); ?>h</strong></td>
					<td>
						<span class="dashicons <?php echo $log->billable ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					</td>
					<td>
						<button type="button" class="button button-link ndizi-delete-log-btn" data-id="<?php echo esc_attr( $log->id ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</td>
				</tr>
				<?php
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}

	/**
	 * Render Reports Dashboard Page (gorgeous custom reports with filters and CSS charts)
	 */
	public static function render_reports_page() {
		// Read-only, bookmarkable report filters from the query string; the page
		// itself is already gated by the ndizi_view_reports capability, and no
		// state is changed here, so a nonce is not appropriate.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$project_id = isset( $_GET['project_id'] ) ? intval( $_GET['project_id'] ) : 0;
		$user_id    = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' ); // first day of month
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-t' ); // last day of month
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Fetch projects and team members for dropdown filters
		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);
		$users    = get_users( array( 'capability' => 'ndizi_log_time' ) );

		// Query aggregate data
		$project_totals = Ndizi_DB::get_time_totals(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'groupby'    => 'project_id',
			)
		);

		$user_totals = Ndizi_DB::get_time_totals(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'groupby'    => 'user_id',
			)
		);

		$overall_seconds          = 0;
		$overall_billable_seconds = 0;
		foreach ( $project_totals as $p_total ) {
			$overall_seconds          += $p_total->total_duration;
			$overall_billable_seconds += $p_total->billable_duration;
		}

		$overall_hours          = round( $overall_seconds / 3600, 2 );
		$overall_billable_hours = round( $overall_billable_seconds / 3600, 2 );
		$billable_percentage    = $overall_hours > 0 ? round( ( $overall_billable_hours / $overall_hours ) * 100 ) : 0;

		// Query detailed entries for profitability calculations
		$detailed_entries = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'number'     => -1,
			)
		);

		$overall_revenue     = 0;
		$overall_cost        = 0;
		$project_margin_data = array();

		foreach ( $detailed_entries as $entry ) {
			// Resolve billing rate hierarchically: Task Override -> User Billing Rate -> Project Default Rate
			$entry_rate = 0;
			if ( $entry->task_id ) {
				$entry_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
			}
			if ( ! $entry_rate && $entry->user_id ) {
				$entry_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
			}
			if ( ! $entry_rate && $entry->project_id ) {
				$entry_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
			}
			$entry_rate    = floatval( $entry_rate );
			$entry_hours   = $entry->duration / 3600;
			$entry_revenue = $entry->billable ? ( $entry_hours * $entry_rate ) : 0;

			// Resolve salary rate (internal cost)
			$salary_rate = 0;
			if ( $entry->user_id ) {
				$salary_rate = get_user_meta( $entry->user_id, '_ndizi_user_salary_rate', true );
			}
			$salary_rate = floatval( $salary_rate );
			$entry_cost  = $entry_hours * $salary_rate;

			$overall_revenue += $entry_revenue;
			$overall_cost    += $entry_cost;

			// Group by project
			if ( ! isset( $project_margin_data[ $entry->project_id ] ) ) {
				$project_margin_data[ $entry->project_id ] = array(
					'hours'   => 0,
					'revenue' => 0,
					'cost'    => 0,
				);
			}
			$project_margin_data[ $entry->project_id ]['hours']   += $entry_hours;
			$project_margin_data[ $entry->project_id ]['revenue'] += $entry_revenue;
			$project_margin_data[ $entry->project_id ]['cost']    += $entry_cost;
		}

		$overall_margin     = $overall_revenue - $overall_cost;
		$overall_margin_pct = $overall_revenue > 0 ? round( ( $overall_margin / $overall_revenue ) * 100, 1 ) : 0;
		?>
		<div class="wrap ndizi-reports-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ndizi Time Reports', 'ndizi-project-management' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Reports filter header -->
			<div class="ndizi-reports-filter-card">
				<form method="get" action="">
					<input type="hidden" name="post_type" value="ndizi_project">
					<input type="hidden" name="page" value="ndizi-reports">

					<div class="ndizi-filter-row">
						<div class="ndizi-filter-col">
							<label for="project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label>
							<select name="project_id" id="project_id">
								<option value="0"><?php esc_html_e( 'All Projects', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $projects as $proj ) : ?>
									<option value="<?php echo esc_attr( $proj->ID ); ?>" <?php selected( $project_id, $proj->ID ); ?>>
										<?php echo esc_html( $proj->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="user_id"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></label>
							<select name="user_id" id="user_id">
								<option value="0"><?php esc_html_e( 'All Members', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $users as $u ) : ?>
									<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>>
										<?php echo esc_html( $u->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="start_date"><?php esc_html_e( 'Start Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>">
						</div>

						<div class="ndizi-filter-col">
							<label for="end_date"><?php esc_html_e( 'End Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>">
						</div>

						<div class="ndizi-filter-col filter-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter Report', 'ndizi-project-management' ); ?></button>
							<?php
							$csv_export_url        = wp_nonce_url(
								add_query_arg( 'ndizi_export_report', 'csv' ),
								'ndizi_export_report_nonce'
							);
							$quickbooks_export_url = wp_nonce_url(
								add_query_arg( 'ndizi_export_report', 'quickbooks_csv' ),
								'ndizi_export_report_nonce'
							);
							?>
							<a href="<?php echo esc_url( $csv_export_url ); ?>" class="button button-secondary" style="background: #10b981 !important; border-color: #10b981 !important; color: #fff !important; line-height: 36px; min-height: 38px;"><?php esc_html_e( 'Export CSV', 'ndizi-project-management' ); ?></a>
							<a href="<?php echo esc_url( $quickbooks_export_url ); ?>" class="button button-secondary" style="background: #059669 !important; border-color: #059669 !important; color: #fff !important; line-height: 36px; min-height: 38px;"><?php esc_html_e( 'Export QuickBooks CSV', 'ndizi-project-management' ); ?></a>
							<a href="edit.php?post_type=ndizi_project&page=ndizi-reports" class="button button-secondary"><?php esc_html_e( 'Reset', 'ndizi-project-management' ); ?></a>
						</div>
					</div>
				</form>
			</div>

			<!-- KPI Cards -->
			<div class="ndizi-kpi-grid">
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Total Hours Tracked', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $overall_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Billable Hours', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val ndizi-kpi-billable"><?php echo esc_html( $overall_billable_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Billable Ratio', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $billable_percentage ); ?>%</span>
					<div class="ndizi-ratio-bar"><div class="ndizi-ratio-fill" style="width: <?php echo esc_attr( $billable_percentage ); ?>%"></div></div>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Total Revenue', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val" style="color: #10b981;">$<?php echo esc_html( number_format( $overall_revenue, 2 ) ); ?></span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Net Margin', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val" style="color: #4f46e5;">$<?php echo esc_html( number_format( $overall_margin, 2 ) ); ?></span>
					<div style="font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500;"><?php echo esc_html( $overall_margin_pct ); ?>% <?php esc_html_e( 'margin', 'ndizi-project-management' ); ?></div>
				</div>
			</div>

			<!-- Graphical/Bar representation grids -->
			<div class="ndizi-charts-grid">
				<!-- Project Hours Chart -->
				<div class="ndizi-chart-card">
					<h3><?php esc_html_e( 'Hours by Project', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $project_totals ) ) : ?>
						<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
						<?php
					else :
						// Find max total to scale widths relative to maximum
						$max_p_total = 1;
						foreach ( $project_totals as $t ) {
							if ( $t->total_duration > $max_p_total ) {
								$max_p_total = $t->total_duration;
							}
						}
						?>
						<div class="ndizi-custom-barchart">
							<?php
							foreach ( $project_totals as $t ) :
								$proj = get_post( $t->group_id );
								if ( ! $proj ) {
									continue;
								}
								$h       = round( $t->total_duration / 3600, 2 );
								$bh      = round( $t->billable_duration / 3600, 2 );
								$percent = round( ( $t->total_duration / $max_p_total ) * 100 );
								?>
								<div class="ndizi-barchart-row">
									<div class="ndizi-barchart-label">
										<a href="<?php echo esc_url( get_edit_post_link( $proj->ID ) ); ?>"><?php echo esc_html( $proj->post_title ); ?></a>
									</div>
									<div class="ndizi-barchart-container">
										<div class="ndizi-barchart-fill" style="width: <?php echo esc_attr( $percent ); ?>%;">
											<span class="ndizi-barchart-val"><?php echo esc_html( $h ); ?>h (<?php echo esc_html( $bh ); ?>h billable)</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Team Member Hours Chart -->
				<div class="ndizi-chart-card">
					<h3><?php esc_html_e( 'Hours by Team Member', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $user_totals ) ) : ?>
						<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
						<?php
					else :
						$max_u_total = 1;
						foreach ( $user_totals as $t ) {
							if ( $t->total_duration > $max_u_total ) {
								$max_u_total = $t->total_duration;
							}
						}
						?>
						<div class="ndizi-custom-barchart">
							<?php
							foreach ( $user_totals as $t ) :
								$usr = get_userdata( $t->group_id );
								if ( ! $usr ) {
									continue;
								}
								$h       = round( $t->total_duration / 3600, 2 );
								$percent = round( ( $t->total_duration / $max_u_total ) * 100 );
								?>
								<div class="ndizi-barchart-row">
									<div class="ndizi-barchart-label"><?php echo esc_html( $usr->display_name ); ?></div>
									<div class="ndizi-barchart-container">
										<div class="ndizi-barchart-fill ndizi-fill-member" style="width: <?php echo esc_attr( $percent ); ?>%;">
											<span class="ndizi-barchart-val"><?php echo esc_html( $h ); ?>h</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Project Profitability & Margins Table -->
			<div class="ndizi-chart-card" style="margin-top: 25px;">
				<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Project Profitability & Margins', 'ndizi-project-management' ); ?></h3>
				<?php if ( empty( $project_margin_data ) ) : ?>
					<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped posts" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: none;">
						<thead>
							<tr>
								<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Budget', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Tracked Hours', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Billed Revenue', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Internal Cost', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Profit Margin', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Margin %', 'ndizi-project-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $project_margin_data as $p_id => $data ) :
								$proj = get_post( $p_id );
								if ( ! $proj ) {
									continue;
								}
								$budget       = floatval( get_post_meta( $p_id, '_ndizi_project_budget', true ) );
								$p_margin     = $data['revenue'] - $data['cost'];
								$p_margin_pct = $data['revenue'] > 0 ? round( ( $p_margin / $data['revenue'] ) * 100, 1 ) : 0;
								?>
								<tr>
									<td style="padding: 12px; font-weight: 600;">
										<a href="<?php echo esc_url( get_edit_post_link( $proj->ID ) ); ?>"><?php echo esc_html( $proj->post_title ); ?></a>
									</td>
									<td style="padding: 12px; text-align: right;">
										<?php echo $budget ? '$' . esc_html( number_format( $budget, 2 ) ) : '<span style="color: #94a3b8;">-</span>'; ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600;">
										<?php echo esc_html( round( $data['hours'], 2 ) ); ?>h
									</td>
									<td style="padding: 12px; text-align: right; color: #10b981; font-weight: 600;">
										$<?php echo esc_html( number_format( $data['revenue'], 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; color: #475569;">
										$<?php echo esc_html( number_format( $data['cost'], 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600; color: <?php echo $p_margin >= 0 ? '#4f46e5' : '#ef4444'; ?>;">
										$<?php echo esc_html( number_format( $p_margin, 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600; color: <?php echo $p_margin >= 0 ? '#4f46e5' : '#ef4444'; ?>;">
										<?php echo esc_html( $p_margin_pct ); ?>%
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
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
							<div class="ndizi-icon-card" style="border: 2px solid <?php echo $current_icon === 'banana' ? '#4f46e5' : '#e2e8f0'; ?>; background: <?php echo $current_icon === 'banana' ? '#f5f3ff' : '#fff'; ?>; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="if(this.previousElementSibling.checked === false) this.style.borderColor='#e2e8f0'">
								<div style="height: 48px; display: flex; align-items: center; justify-content: center; color: <?php echo $current_icon === 'banana' ? '#4f46e5' : '#64748b'; ?>; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6v-2a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v2a9.09 9.09 0 0 1 -4 8.08c-2 1.31 -5 1.57 -7 1.59a2 2 0 0 0 -2 2a2 2 0 0 0 1.16 1.81c2.69 1.2 9.46 3.44 14.35 -1.66c4.49 -4.74 1.49 -11.82 1.49 -11.82" /></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Banana', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Clock -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="clock" <?php checked( $current_icon, 'clock' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border: 2px solid <?php echo $current_icon === 'clock' ? '#4f46e5' : '#e2e8f0'; ?>; background: <?php echo $current_icon === 'clock' ? '#f5f3ff' : '#fff'; ?>; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="if(this.previousElementSibling.checked === false) this.style.borderColor='#e2e8f0'">
								<div style="height: 48px; display: flex; align-items: center; justify-content: center; color: <?php echo $current_icon === 'clock' ? '#4f46e5' : '#64748b'; ?>; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Clock', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Punch Clock -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="punch_clock" <?php checked( $current_icon, 'punch_clock' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border: 2px solid <?php echo $current_icon === 'punch_clock' ? '#4f46e5' : '#e2e8f0'; ?>; background: <?php echo $current_icon === 'punch_clock' ? '#f5f3ff' : '#fff'; ?>; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="if(this.previousElementSibling.checked === false) this.style.borderColor='#e2e8f0'">
								<div style="height: 48px; display: flex; align-items: center; justify-content: center; color: <?php echo $current_icon === 'punch_clock' ? '#4f46e5' : '#64748b'; ?>; margin-bottom: 12px;">
									<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 -960 960 960" fill="currentColor"><path d="M360-120v-80h240v80H360Zm120-160q-100 0-170-70t-70-170q0-100 70-170t170-70q100 0 170 70t70 170q0 100-70 170t-170 70Zm0-80q66 0 113-47t47-113q0-66-47-113t-113-47q-66 0-113 47t-47 113q0 66 47 113t113 47ZM80-560v-80h160v80H80Zm640 0v-80h160v80H720ZM440-440h80v120l-70 70-56-56 46-46v-88Z"/></svg>
								</div>
								<span style="font-size: 14px; font-weight: 600; color: #1e293b;"><?php esc_html_e( 'Punch Clock', 'ndizi-project-management' ); ?></span>
							</div>
						</label>

						<!-- Option: Hourglass -->
						<label style="cursor: pointer; display: block; position: relative;">
							<input type="radio" name="ndizi_adminbar_icon" value="hourglass" <?php checked( $current_icon, 'hourglass' ); ?> style="position: absolute; opacity: 0; width: 0; height: 0;">
							<div class="ndizi-icon-card" style="border: 2px solid <?php echo $current_icon === 'hourglass' ? '#4f46e5' : '#e2e8f0'; ?>; background: <?php echo $current_icon === 'hourglass' ? '#f5f3ff' : '#fff'; ?>; border-radius: 10px; padding: 20px; text-align: center; transition: all 0.2s;" onmouseover="this.style.borderColor='#4f46e5'" onmouseout="if(this.previousElementSibling.checked === false) this.style.borderColor='#e2e8f0'">
								<div style="height: 48px; display: flex; align-items: center; justify-content: center; color: <?php echo $current_icon === 'hourglass' ? '#4f46e5' : '#64748b'; ?>; margin-bottom: 12px;">
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
						$modules_list = array(
							'invoicing'     => array(
								'name' => __( 'Invoicing & Billing', 'ndizi-project-management' ),
								'desc' => __( 'Generate client invoices, track billing and salary rates, analyze margins, and export/print PDF invoices.', 'ndizi-project-management' ),
							),
							'portal'        => array(
								'name' => __( 'Client Portal', 'ndizi-project-management' ),
								'desc' => __( 'Enables frontend portal block and shortcodes for client reviews, task updates, and comments.', 'ndizi-project-management' ),
							),
							'tracker'       => array(
								'name' => __( 'Admin Bar & Quick Tracker', 'ndizi-project-management' ),
								'desc' => __( 'Adds the admin bar quick-timer toggle and a dedicated quick-tracker logger page.', 'ndizi-project-management' ),
							),
							'notifications' => array(
								'name' => __( 'Email Notifications', 'ndizi-project-management' ),
								'desc' => __( 'Sends automated email notifications when tasks are assigned or their status changes.', 'ndizi-project-management' ),
							),
							'gantt'         => array(
								'name' => __( 'Gantt Timeline Charts', 'ndizi-project-management' ),
								'desc' => __( 'Provides interactive timelines for project scheduling and visually tracking completion status.', 'ndizi-project-management' ),
							),
							'integrations'  => array(
								'name' => __( 'Webhooks & Slack Integrations', 'ndizi-project-management' ),
								'desc' => __( 'Sends outbound JSON payloads and formatted Slack alerts when time is logged, tasks change, or invoices transition.', 'ndizi-project-management' ),
							),
						);

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
			update_user_meta( $user_id, '_ndizi_user_billing_rate', floatval( $_POST['ndizi_user_billing_rate'] ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
		if ( isset( $_POST['ndizi_user_salary_rate'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WordPress core handles user profile nonce verification.
			update_user_meta( $user_id, '_ndizi_user_salary_rate', floatval( $_POST['ndizi_user_salary_rate'] ) );
		}
	}
}
