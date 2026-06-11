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
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ) );

		// Custom columns in listings
		add_filter( 'manage_ndizi_client_posts_columns', array( __CLASS__, 'add_client_columns' ) );
		add_action( 'manage_ndizi_client_posts_custom_column', array( __CLASS__, 'render_client_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_project_posts_columns', array( __CLASS__, 'add_project_columns' ) );
		add_action( 'manage_ndizi_project_posts_custom_column', array( __CLASS__, 'render_project_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_task_posts_columns', array( __CLASS__, 'add_task_columns' ) );
		add_action( 'manage_ndizi_task_posts_custom_column', array( __CLASS__, 'render_task_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_invoice_posts_columns', array( __CLASS__, 'add_invoice_columns' ) );
		add_action( 'manage_ndizi_invoice_posts_custom_column', array( __CLASS__, 'render_invoice_columns' ), 10, 2 );

		// Admin menus
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_pages' ) );

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
		$is_ndizi_page    = isset( $_GET['page'] ) && ( strpos( $_GET['page'], 'ndizi-' ) === 0 );

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
		// Calculate stats
		$active_projects = count(
			get_posts(
				array(
					'post_type'      => 'ndizi_project',
					'posts_per_page' => -1,
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
		$table_name  = Ndizi_DB::get_table_name();
		$total_sec   = $wpdb->get_var( "SELECT SUM(duration) FROM $table_name" );
		$total_hours = $total_sec ? round( $total_sec / 3600, 1 ) : 0;

		$pending_invoices = count(
			get_posts(
				array(
					'post_type'      => 'ndizi_invoice',
					'posts_per_page' => -1,
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
					<h3 style="margin: 0; font-size: 14px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;"><?php esc_html_e( 'Total Hours Logged', 'ndizi-project-management' ); ?></h3>
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
		add_meta_box( 'ndizi_invoice_details', __( 'Invoice Details', 'ndizi-project-management' ), array( __CLASS__, 'render_invoice_meta_box' ), 'ndizi_invoice', 'normal', 'high' );

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
				<th><label for="ndizi_client_website"><?php _e( 'Website URL', 'ndizi-project-management' ); ?></label></th>
				<td><input type="url" name="ndizi_client_website" id="ndizi_client_website" value="<?php echo esc_url( $website ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_address"><?php _e( 'Billing Address', 'ndizi-project-management' ); ?></label></th>
				<td><textarea name="ndizi_client_address" id="ndizi_client_address" class="large-text" rows="3"><?php echo esc_textarea( $address ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_status"><?php _e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_status" id="ndizi_client_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php _e( 'Archived / Inactive', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_client_auth_key"><?php _e( 'Portal Key', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_client_auth_key" id="ndizi_client_auth_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" readonly>
					<button type="button" class="button ndizi-regen-key-btn"><?php _e( 'Regenerate Key', 'ndizi-project-management' ); ?></button>
					<p class="description"><?php _e( 'Used for frontend access authentication.', 'ndizi-project-management' ); ?></p>
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

		$client_id  = get_post_meta( $post->ID, '_ndizi_client_id', true );
		$start_date = get_post_meta( $post->ID, '_ndizi_project_start_date', true );
		$end_date   = get_post_meta( $post->ID, '_ndizi_project_end_date', true );
		$budget     = get_post_meta( $post->ID, '_ndizi_project_budget', true );
		$status     = get_post_meta( $post->ID, '_ndizi_project_status', true );

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_client_id"><?php _e( 'Client', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_id" id="ndizi_client_id" required>
						<option value=""><?php _e( '-- Select Client --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $clients as $client ) : ?>
							<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $client_id, $client->ID ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_project_start_date"><?php _e( 'Start Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_start_date" id="ndizi_project_start_date" value="<?php echo esc_attr( $start_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_end_date"><?php _e( 'End/Target Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_end_date" id="ndizi_project_end_date" value="<?php echo esc_attr( $end_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_budget"><?php _e( 'Budget ($)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_project_budget" id="ndizi_project_budget" value="<?php echo esc_attr( $budget ); ?>" class="small-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_status"><?php _e( 'Project Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_status" id="ndizi_project_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php _e( 'Archived', 'ndizi-project-management' ); ?></option>
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
				<h4><?php _e( 'Live Time Tracker', 'ndizi-project-management' ); ?></h4>
				<div class="ndizi-timer-bar <?php echo $is_active_on_this ? 'ndizi-timer-running' : ''; ?>">
					<div class="ndizi-timer-fields">
						<select id="ndizi_tracker_task_id">
							<option value="0"><?php _e( '-- Log general to project --', 'ndizi-project-management' ); ?></option>
							<?php foreach ( $tasks as $task ) : ?>
								<option value="<?php echo esc_attr( $task->ID ); ?>">
									<?php echo esc_html( $task->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" id="ndizi_tracker_desc" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" class="regular-text">
						<label class="ndizi-checkbox-label">
							<input type="checkbox" id="ndizi_tracker_billable" value="1" checked> <?php _e( 'Billable', 'ndizi-project-management' ); ?>
						</label>
					</div>

					<div class="ndizi-timer-action">
						<span class="ndizi-live-clock">00:00:00</span>
						<?php if ( $is_active_on_this ) : ?>
							<button type="button" class="button button-primary ndizi-btn-stop" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
								<?php _e( 'Stop', 'ndizi-project-management' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary ndizi-btn-start" data-project-id="<?php echo esc_attr( $post->ID ); ?>" <?php disabled( $active !== false ); ?>>
								<?php _e( 'Start Timer', 'ndizi-project-management' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $active && ! $is_active_on_this ) : ?>
					<p class="description error-message">
						<?php _e( 'You already have an active timer running on another project. Stop it first to track here.', 'ndizi-project-management' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<hr>

			<!-- Log List -->
			<div class="ndizi-tracker-logs">
				<h4><?php _e( 'Recent Time Logs', 'ndizi-project-management' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-date"><?php _e( 'Date', 'ndizi-project-management' ); ?></th>
							<th class="column-user"><?php _e( 'User', 'ndizi-project-management' ); ?></th>
							<th class="column-task"><?php _e( 'Task', 'ndizi-project-management' ); ?></th>
							<th class="column-desc"><?php _e( 'Description', 'ndizi-project-management' ); ?></th>
							<th class="column-duration"><?php _e( 'Duration', 'ndizi-project-management' ); ?></th>
							<th class="column-billable"><?php _e( 'Billable', 'ndizi-project-management' ); ?></th>
							<th class="column-actions"><?php _e( 'Action', 'ndizi-project-management' ); ?></th>
						</tr>
					</thead>
					<tbody id="ndizi_logs_table_body">
						<?php if ( empty( $logs ) ) : ?>
							<tr class="no-items"><td colspan="7"><?php _e( 'No time logged yet on this project.', 'ndizi-project-management' ); ?></td></tr>
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

		$project_id  = get_post_meta( $post->ID, '_ndizi_project_id', true );
		$assignee_id = get_post_meta( $post->ID, '_ndizi_assigned_user_id', true );
		$status      = get_post_meta( $post->ID, '_ndizi_task_status', true );
		$priority    = get_post_meta( $post->ID, '_ndizi_task_priority', true );
		$due_date    = get_post_meta( $post->ID, '_ndizi_task_due_date', true );

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
				<th><label for="ndizi_project_id"><?php _e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_project_id" required>
						<option value=""><?php _e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_assigned_user_id"><?php _e( 'Assigned To', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_assigned_user_id" id="ndizi_assigned_user_id">
						<option value="0"><?php _e( 'Unassigned', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $users as $u ) : ?>
							<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $assignee_id, $u->ID ); ?>>
								<?php echo esc_html( $u->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_status"><?php _e( 'Task Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_status" id="ndizi_task_status">
						<option value="open" <?php selected( $status, 'open' ); ?>><?php _e( 'Open', 'ndizi-project-management' ); ?></option>
						<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php _e( 'In Progress', 'ndizi-project-management' ); ?></option>
						<option value="completed" <?php selected( $status, 'completed' ); ?>><?php _e( 'Completed', 'ndizi-project-management' ); ?></option>
						<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php _e( 'Cancelled', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_priority"><?php _e( 'Priority', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_priority" id="ndizi_task_priority">
						<option value="low" <?php selected( $priority, 'low' ); ?>><?php _e( 'Low', 'ndizi-project-management' ); ?></option>
						<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php _e( 'Medium', 'ndizi-project-management' ); ?></option>
						<option value="high" <?php selected( $priority, 'high' ); ?>><?php _e( 'High', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_due_date"><?php _e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_task_due_date" id="ndizi_task_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
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
				<th><label for="ndizi_invoice_project_id"><?php _e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_invoice_project_id" required>
						<option value=""><?php _e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_date"><?php _e( 'Invoice Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_date" id="ndizi_invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_due_date"><?php _e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_due_date" id="ndizi_invoice_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_amount"><?php _e( 'Amount ($)', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="number" step="0.01" name="ndizi_invoice_amount" id="ndizi_invoice_amount" value="<?php echo esc_attr( $amount ); ?>" class="small-text">
					<p class="description"><?php _e( 'Total amount for this invoice. Can be manually overridden or aggregated from time entries below.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_status"><?php _e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_invoice_status" id="ndizi_invoice_status">
						<option value="draft" <?php selected( $status, 'draft' ); ?>><?php _e( 'Draft', 'ndizi-project-management' ); ?></option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>><?php _e( 'Sent', 'ndizi-project-management' ); ?></option>
						<option value="paid" <?php selected( $status, 'paid' ); ?>><?php _e( 'Paid', 'ndizi-project-management' ); ?></option>
						<option value="void" <?php selected( $status, 'void' ); ?>><?php _e( 'Void', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<?php if ( $project_id ) : ?>
				<tr>
					<th><?php _e( 'Aggregate Time Entries', 'ndizi-project-management' ); ?></th>
					<td>
						<div class="ndizi-invoice-time-picker">
							<p class="description"><?php _e( 'Select the billable time entries to include on this invoice:', 'ndizi-project-management' ); ?></p>
							<div class="ndizi-invoice-time-scroll">
								<?php if ( empty( $time_entries ) ) : ?>
									<p><em><?php _e( 'No uninvoiced billable time entries found for this project.', 'ndizi-project-management' ); ?></em></p>
								<?php else : ?>
									<table class="widefat striped">
										<thead>
											<tr>
												<th><input type="checkbox" id="ndizi_select_all_invoice_time"></th>
												<th><?php _e( 'Date', 'ndizi-project-management' ); ?></th>
												<th><?php _e( 'User', 'ndizi-project-management' ); ?></th>
												<th><?php _e( 'Description', 'ndizi-project-management' ); ?></th>
												<th><?php _e( 'Hours', 'ndizi-project-management' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $time_entries as $entry ) :
												$entry_user = get_userdata( $entry->user_id );
												$is_linked  = ( intval( $entry->invoice_id ) === $post->ID );
												?>
												<tr>
													<td>
														<input type="checkbox" name="ndizi_invoice_time_entries[]" value="<?php echo esc_attr( $entry->id ); ?>" <?php checked( $is_linked ); ?> class="ndizi-invoice-time-checkbox" data-duration="<?php echo esc_attr( $entry->duration ); ?>">
													</td>
													<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->start_time ) ) ); ?></td>
													<td><?php echo $entry_user ? esc_html( $entry_user->display_name ) : '-'; ?></td>
													<td><?php echo esc_html( $entry->description ); ?></td>
													<td><strong><?php echo esc_html( round( $entry->duration / 3600, 2 ) ); ?>h</strong></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
									<div style="margin-top: 10px;">
										<input type="number" id="ndizi_hourly_rate" placeholder="<?php esc_attr_e( 'Rate ($/hour)', 'ndizi-project-management' ); ?>" style="width: 100px;">
										<button type="button" class="button" id="ndizi_btn_calc_invoice"><?php _e( 'Calculate & Apply Amount', 'ndizi-project-management' ); ?></button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php _e( 'Time Entries', 'ndizi-project-management' ); ?></th>
					<td><p class="description"><?php _e( 'Select a Project and save/update the invoice first to see eligible time entries.', 'ndizi-project-management' ); ?></p></td>
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
				<th><label for="ndizi_contact_email"><?php _e( 'Email Address', 'ndizi-project-management' ); ?></label></th>
				<td><input type="email" name="ndizi_contact_email" id="ndizi_contact_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_phone"><?php _e( 'Phone Number', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_phone" id="ndizi_contact_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_role"><?php _e( 'Role / Title', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_role" id="ndizi_contact_role" value="<?php echo esc_attr( $role ); ?>" class="regular-text" placeholder="e.g. Project Manager, Billing Contact"></td>
			</tr>
			<tr>
				<th><label><?php _e( 'Associated Clients', 'ndizi-project-management' ); ?></label></th>
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

		// 1. Client Save
		if ( isset( $_POST['ndizi_client_nonce'] ) && wp_verify_nonce( $_POST['ndizi_client_nonce'], 'ndizi_save_client' ) ) {
			if ( isset( $_POST['ndizi_client_website'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_website', esc_url_raw( $_POST['ndizi_client_website'] ) );
			}
			if ( isset( $_POST['ndizi_client_address'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_address', sanitize_textarea_field( $_POST['ndizi_client_address'] ) );
			}
			if ( isset( $_POST['ndizi_client_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_status', sanitize_text_field( $_POST['ndizi_client_status'] ) );
			}
			if ( isset( $_POST['ndizi_client_auth_key'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_auth_key', sanitize_text_field( $_POST['ndizi_client_auth_key'] ) );
			}
		}

		// 2. Project Save
		if ( isset( $_POST['ndizi_project_nonce'] ) && wp_verify_nonce( $_POST['ndizi_project_nonce'], 'ndizi_save_project' ) ) {
			if ( isset( $_POST['ndizi_client_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_id', intval( $_POST['ndizi_client_id'] ) );
			}
			if ( isset( $_POST['ndizi_project_start_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_start_date', sanitize_text_field( $_POST['ndizi_project_start_date'] ) );
			}
			if ( isset( $_POST['ndizi_project_end_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_end_date', sanitize_text_field( $_POST['ndizi_project_end_date'] ) );
			}
			if ( isset( $_POST['ndizi_project_budget'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_budget', floatval( $_POST['ndizi_project_budget'] ) );
			}
			if ( isset( $_POST['ndizi_project_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_status', sanitize_text_field( $_POST['ndizi_project_status'] ) );
			}
		}

		// 3. Task Save
		if ( isset( $_POST['ndizi_task_nonce'] ) && wp_verify_nonce( $_POST['ndizi_task_nonce'], 'ndizi_save_task' ) ) {
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_assigned_user_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_assigned_user_id', intval( $_POST['ndizi_assigned_user_id'] ) );
			}
			if ( isset( $_POST['ndizi_task_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_status', sanitize_text_field( $_POST['ndizi_task_status'] ) );
			}
			if ( isset( $_POST['ndizi_task_priority'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_priority', sanitize_text_field( $_POST['ndizi_task_priority'] ) );
			}
			if ( isset( $_POST['ndizi_task_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_due_date', sanitize_text_field( $_POST['ndizi_task_due_date'] ) );
			}
		}

		// 4. Invoice Save
		if ( isset( $_POST['ndizi_invoice_nonce'] ) && wp_verify_nonce( $_POST['ndizi_invoice_nonce'], 'ndizi_save_invoice' ) ) {
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_date', sanitize_text_field( $_POST['ndizi_invoice_date'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_due_date', sanitize_text_field( $_POST['ndizi_invoice_due_date'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_amount'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_amount', floatval( $_POST['ndizi_invoice_amount'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_status', sanitize_text_field( $_POST['ndizi_invoice_status'] ) );
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
				foreach ( $_POST['ndizi_invoice_time_entries'] as $entry_id ) {
					$wpdb->update(
						$table_name,
						array( 'invoice_id' => $post_id ),
						array( 'id' => intval( $entry_id ) ),
						array( '%d' ),
						array( '%d' )
					);
				}
			}
		}

		// 5. Contact Save
		if ( isset( $_POST['ndizi_contact_nonce'] ) && wp_verify_nonce( $_POST['ndizi_contact_nonce'], 'ndizi_save_contact' ) ) {
			if ( isset( $_POST['ndizi_contact_email'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_email', sanitize_email( $_POST['ndizi_contact_email'] ) );
			}
			if ( isset( $_POST['ndizi_contact_phone'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_phone', sanitize_text_field( $_POST['ndizi_contact_phone'] ) );
			}
			if ( isset( $_POST['ndizi_contact_role'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_role', sanitize_text_field( $_POST['ndizi_contact_role'] ) );
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

	/**
	 * AJAX logic to start a timer
	 */
	public static function ajax_start_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$project_id  = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		$task_id     = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( $_POST['description'] ) : '';
		$billable    = isset( $_POST['billable'] ) ? intval( $_POST['billable'] ) : 1;

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID is required.', 'ndizi-project-management' ) ) );
		}

		$user_id  = get_current_user_id();
		$timer_id = Ndizi_DB::start_timer( $user_id, $project_id, $task_id, $description, $billable );

		if ( ! $timer_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to start timer.', 'ndizi-project-management' ) ) );
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

		// Authorization
		if ( $log->user_id !== $user_id && ! current_user_can( 'administrator' ) && ! current_user_can( 'ndizi_manager' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
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
		// Handle filters
		$project_id = isset( $_GET['project_id'] ) ? intval( $_GET['project_id'] ) : 0;
		$user_id    = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : gmdate( 'Y-m-01' ); // first day of month
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : gmdate( 'Y-m-t' ); // last day of month

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
		?>
		<div class="wrap ndizi-reports-page">
			<h1 class="wp-heading-inline"><?php _e( 'Ndizi Time Reports', 'ndizi-project-management' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Reports filter header -->
			<div class="ndizi-reports-filter-card">
				<form method="get" action="">
					<input type="hidden" name="post_type" value="ndizi_project">
					<input type="hidden" name="page" value="ndizi-reports">

					<div class="ndizi-filter-row">
						<div class="ndizi-filter-col">
							<label for="project_id"><?php _e( 'Project', 'ndizi-project-management' ); ?></label>
							<select name="project_id" id="project_id">
								<option value="0"><?php _e( 'All Projects', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $projects as $proj ) : ?>
									<option value="<?php echo esc_attr( $proj->ID ); ?>" <?php selected( $project_id, $proj->ID ); ?>>
										<?php echo esc_html( $proj->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="user_id"><?php _e( 'Team Member', 'ndizi-project-management' ); ?></label>
							<select name="user_id" id="user_id">
								<option value="0"><?php _e( 'All Members', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $users as $u ) : ?>
									<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>>
										<?php echo esc_html( $u->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="start_date"><?php _e( 'Start Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>">
						</div>

						<div class="ndizi-filter-col">
							<label for="end_date"><?php _e( 'End Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>">
						</div>

						<div class="ndizi-filter-col filter-actions">
							<button type="submit" class="button button-primary"><?php _e( 'Filter Report', 'ndizi-project-management' ); ?></button>
							<a href="edit.php?post_type=ndizi_project&page=ndizi-reports" class="button button-secondary"><?php _e( 'Reset', 'ndizi-project-management' ); ?></a>
						</div>
					</div>
				</form>
			</div>

			<!-- KPI Cards -->
			<div class="ndizi-kpi-grid">
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php _e( 'Total Hours Tracked', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $overall_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php _e( 'Billable Hours', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val ndizi-kpi-billable"><?php echo esc_html( $overall_billable_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php _e( 'Billable Ratio', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $billable_percentage ); ?>%</span>
					<div class="ndizi-ratio-bar"><div class="ndizi-ratio-fill" style="width: <?php echo esc_attr( $billable_percentage ); ?>%"></div></div>
				</div>
			</div>

			<!-- Graphical/Bar representation grids -->
			<div class="ndizi-charts-grid">
				<!-- Project Hours Chart -->
				<div class="ndizi-chart-card">
					<h3><?php _e( 'Hours by Project', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $project_totals ) ) : ?>
						<p class="no-data-msg"><?php _e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
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
					<h3><?php _e( 'Hours by Team Member', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $user_totals ) ) : ?>
						<p class="no-data-msg"><?php _e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
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
			<h1><?php _e( 'Project Gantt Timelines', 'ndizi-project-management' ); ?></h1>
			<p class="description"><?php _e( 'Visualizing schedule timelines and task completion rates across active client projects.', 'ndizi-project-management' ); ?></p>
			<hr class="wp-header-end">

			<?php if ( empty( $timeline_data ) ) : ?>
				<div class="notice notice-warning inline"><p><?php _e( 'No active projects with both Start and End Dates populated were found to plot in the Gantt chart.', 'ndizi-project-management' ); ?></p></div>
			<?php else : ?>
				<div class="ndizi-gantt-container">
					<!-- Gantt Header (Months) -->
					<div class="ndizi-gantt-header-row">
						<div class="ndizi-gantt-label-col"><strong><?php _e( 'Project Name', 'ndizi-project-management' ); ?></strong></div>
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
										<?php echo esc_html( $project['completed'] ); ?>/<?php echo esc_html( $project['total_tasks'] ); ?> <?php _e( 'Tasks', 'ndizi-project-management' ); ?> (<?php echo esc_html( $project['progress'] ); ?>%)
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
}
