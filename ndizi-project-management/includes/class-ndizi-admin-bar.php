<?php
/**
 * Admin Bar Time Logger interface for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Admin_Bar {

	/**
	 * Initialize the Admin Bar module hooks
	 */
	public static function init() {
		// Only hook if the user is logged in and is authorized to log time
		add_action( 'init', array( __CLASS__, 'hook_user_authorized_actions' ) );
	}

	/**
	 * Hook actions if the current logged-in user can log time
	 */
	public static function hook_user_authorized_actions() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! Ndizi_Roles::current_user_can( 'ndizi_log_time' ) && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			return;
		}

		// Enqueue scripts & styles for both admin and frontend when admin bar is active
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// Render the Admin Bar Node
		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_node' ), 999 );

		// AJAX Endpoints
		add_action( 'wp_ajax_ndizi_get_tracker_data', array( __CLASS__, 'ajax_get_tracker_data' ) );
		add_action( 'wp_ajax_ndizi_log_time_manual_action', array( __CLASS__, 'ajax_log_time_manual' ) );
	}

	/**
	 * Enqueue frontend/backend scripts & stylesheets
	 */
	public static function enqueue_assets() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style( 'ndizi-adminbar-style', NDIZI_PLUGIN_URL . 'build/adminbar.css', array(), NDIZI_VERSION );
		wp_enqueue_script( 'ndizi-adminbar-script', NDIZI_PLUGIN_URL . 'build/adminbar.js', array( 'jquery', 'wp-ajax' ), NDIZI_VERSION, true );

		wp_localize_script(
			'ndizi-adminbar-script',
			'ndizi_adminbar',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ndizi-admin-nonce' ),
				'labels'   => array(
					'loading_projects' => __( 'Loading projects...', 'ndizi-project-management' ),
					'select_project'   => __( '-- Select Project --', 'ndizi-project-management' ),
					'select_task'      => __( '-- General to Project --', 'ndizi-project-management' ),
					'timer_started'    => __( 'Timer started!', 'ndizi-project-management' ),
					'timer_stopped'    => __( 'Timer stopped!', 'ndizi-project-management' ),
					'entry_logged'     => __( 'Time entry logged!', 'ndizi-project-management' ),
					'error_general'    => __( 'An error occurred. Please try again.', 'ndizi-project-management' ),
				),
			)
		);
	}

	/**
	 * Add custom time tracker node to the WP Admin Bar (on the right)
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin Bar object.
	 */
	public static function add_admin_bar_node( $wp_admin_bar ) {
		$user_id      = get_current_user_id();
		$active_timer = Ndizi_DB::get_active_timer( $user_id );
		$node_class   = 'ndizi-ab-time-tracker';
		$duration_sec = 0;

		if ( $active_timer ) {
			$node_class .= ' ndizi-timer-active';
			// Calculate initial live duration
			$start_ts     = strtotime( $active_timer->start_time );
			$now_ts       = strtotime( current_time( 'mysql' ) );
			$duration_sec = max( 0, $now_ts - $start_ts );

			$h                   = floor( $duration_sec / 3600 );
			$m                   = floor( ( $duration_sec % 3600 ) / 60 );
			$s                   = $duration_sec % 60;
			$active_duration_str = sprintf( '%02d:%02d:%02d', $h, $m, $s );

			$title = sprintf(
				'<span class="ndizi-ab-icon-wrapper"><span class="ndizi-ab-pulse"></span><span class="dashicons dashicons-clock"></span></span><span class="ndizi-ab-label">%s</span>',
				esc_html( $active_duration_str )
			);
		} else {
			$title = sprintf(
				'<span class="ndizi-ab-icon-wrapper"><span class="dashicons dashicons-clock"></span></span><span class="ndizi-ab-label">%s</span>',
				esc_html__( 'Log Time', 'ndizi-project-management' )
			);
		}

		// Main (Parent) Menu Item
		$wp_admin_bar->add_node(
			array(
				'id'     => 'ndizi-time-tracker',
				'title'  => $title,
				'href'   => '#',
				'meta'   => array(
					'class' => esc_attr( $node_class ),
					'title' => esc_attr__( 'Ndizi Time Tracker', 'ndizi-project-management' ),
				),
				'parent' => 'top-secondary', // Alignment on the right side
			)
		);

		// Submenu Content Container Node
		$wp_admin_bar->add_node(
			array(
				'id'     => 'ndizi-time-tracker-panel',
				'parent' => 'ndizi-time-tracker',
				'title'  => self::get_panel_html( $active_timer, $duration_sec ),
				'meta'   => array(
					'class' => 'ndizi-ab-panel-wrapper',
				),
			)
		);
	}

	/**
	 * Generate the custom HTML panel contents for the dropdown
	 *
	 * @param object|false $active_timer Active timer row or false.
	 * @param int          $duration_sec Current running timer duration in seconds.
	 * @return string HTML output.
	 */
	private static function get_panel_html( $active_timer, $duration_sec = 0 ) {
		ob_start();

		$project_title = '';
		$task_title    = '';

		if ( $active_timer ) {
			$project = get_post( $active_timer->project_id );
			if ( $project ) {
				$project_title = $project->post_title;
			}
			if ( $active_timer->task_id ) {
				$task = get_post( $active_timer->task_id );
				if ( $task ) {
					$task_title = $task->post_title;
				}
			}
		}

		$h           = floor( $duration_sec / 3600 );
		$m           = floor( ( $duration_sec % 3600 ) / 60 );
		$s           = $duration_sec % 60;
		$ticker_text = sprintf( '%02d:%02d:%02d', $h, $m, $s );
		?>
		<div class="ndizi-ab-panel <?php echo $active_timer ? 'ndizi-timer-running' : ''; ?>" id="ndizi-ab-panel" data-duration="<?php echo esc_attr( $duration_sec ); ?>">
			<!-- ACTIVE TIMER STATE -->
			<div class="ndizi-ab-active-timer-view">
				<div class="ndizi-ab-section-title"><?php esc_html_e( 'Running Tracker', 'ndizi-project-management' ); ?></div>
				
				<div class="ndizi-ab-running-details">
					<div class="ndizi-ab-proj-tag" id="ndizi-ab-active-project"><?php echo esc_html( $project_title ); ?></div>
					<?php if ( $task_title ) : ?>
						<div class="ndizi-ab-task-tag" id="ndizi-ab-active-task"><?php echo esc_html( $task_title ); ?></div>
					<?php endif; ?>
					<div class="ndizi-ab-desc-tag" id="ndizi-ab-active-desc"><?php echo esc_html( $active_timer ? $active_timer->description : '' ); ?></div>
				</div>

				<div class="ndizi-ab-ticker" id="ndizi-ab-ticker-clock"><?php echo esc_html( $ticker_text ); ?></div>

				<div class="ndizi-ab-actions">
					<button type="button" class="button ndizi-ab-btn-stop" id="ndizi-ab-btn-stop">
						<span class="dashicons dashicons-controls-pause"></span> 
						<?php
						esc_html_e( 'Stop Timer', 'ndizi-project-management' );
						?>
					</button>
				</div>
			</div>

			<!-- INACTIVE STATE (LOG OR START TIMER) -->
			<div class="ndizi-ab-new-timer-view">
				<div class="ndizi-ab-section-title"><?php esc_html_e( 'Log / Track Time', 'ndizi-project-management' ); ?></div>

				<div class="ndizi-ab-form-group">
					<select id="ndizi-ab-project-select" class="ndizi-ab-select">
						<option value=""><?php esc_html_e( 'Loading projects...', 'ndizi-project-management' ); ?></option>
					</select>
				</div>

				<div class="ndizi-ab-form-group" id="ndizi-ab-task-select-group" style="display: none;">
					<select id="ndizi-ab-task-select" class="ndizi-ab-select">
						<option value="0"><?php esc_html_e( '-- General to Project --', 'ndizi-project-management' ); ?></option>
					</select>
				</div>

				<div class="ndizi-ab-form-group">
					<input type="text" id="ndizi-ab-desc-input" class="ndizi-ab-input" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" maxlength="255">
				</div>

				<div class="ndizi-ab-form-group ndizi-ab-flex-row">
					<label class="ndizi-ab-checkbox-label">
						<input type="checkbox" id="ndizi-ab-billable-check" value="1" checked>
						<span><?php esc_html_e( 'Billable Time', 'ndizi-project-management' ); ?></span>
					</label>
				</div>

				<!-- Stats card (Total Logged / Budget) -->
				<div class="ndizi-ab-stats-card" id="ndizi-ab-stats-card" style="display: none;">
					<div class="ndizi-ab-stat-item">
						<span class="ndizi-ab-stat-label"><?php esc_html_e( 'Time Logged:', 'ndizi-project-management' ); ?></span>
						<span class="ndizi-ab-stat-value" id="ndizi-ab-stat-logged">0h</span>
					</div>
					<div class="ndizi-ab-stat-item" id="ndizi-ab-stat-budget-row">
						<span class="ndizi-ab-stat-label"><?php esc_html_e( 'Project Budget:', 'ndizi-project-management' ); ?></span>
						<span class="ndizi-ab-stat-value" id="ndizi-ab-stat-budget">-</span>
					</div>
				</div>

				<!-- Action Buttons -->
				<div class="ndizi-ab-actions-grid">
					<button type="button" class="button button-primary ndizi-ab-btn-start" id="ndizi-ab-btn-start" disabled>
						<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
					</button>

					<button type="button" class="button ndizi-ab-btn-toggle-manual" id="ndizi-ab-btn-toggle-manual">
						<span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Log Manual Entry', 'ndizi-project-management' ); ?>
					</button>
				</div>

				<!-- Manual entry duration panel (expands inline) -->
				<div class="ndizi-ab-manual-log-panel" id="ndizi-ab-manual-log-panel" style="display: none;">
					<div class="ndizi-ab-duration-fields">
						<div class="ndizi-ab-duration-input-col">
							<input type="number" id="ndizi-ab-manual-hours" class="ndizi-ab-input" min="0" placeholder="0">
							<span class="ndizi-ab-duration-label"><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></span>
						</div>
						<div class="ndizi-ab-duration-separator">:</div>
						<div class="ndizi-ab-duration-input-col">
							<input type="number" id="ndizi-ab-manual-minutes" class="ndizi-ab-input" min="0" max="59" placeholder="00">
							<span class="ndizi-ab-duration-label"><?php esc_html_e( 'Minutes', 'ndizi-project-management' ); ?></span>
						</div>
					</div>
					
					<button type="button" class="button button-secondary ndizi-ab-btn-save-manual" id="ndizi-ab-btn-save-manual">
						<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Log Entry', 'ndizi-project-management' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX endpoint to retrieve all projects and tasks the user can log time under
	 */
	public static function ajax_get_tracker_data() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$user_id = get_current_user_id();

		// Query active projects
		$project_args = array(
			'post_type'      => 'ndizi_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_ndizi_project_status',
					'value'   => 'active',
					'compare' => '=',
				),
			),
		);

		// Scope visible projects for team members (non-managers)
		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_projects' ) ) {
			$tasks = get_posts(
				array(
					'post_type'      => 'ndizi_task',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_assigned_user_id',
							'value' => $user_id,
						),
					),
				)
			);

			$project_ids = array();
			foreach ( $tasks as $task_id ) {
				$p_id = (int) get_post_meta( $task_id, '_ndizi_project_id', true );
				if ( $p_id ) {
					$project_ids[ $p_id ] = $p_id;
				}
			}

			if ( empty( $project_ids ) ) {
				wp_send_json_success( array( 'projects' => array() ) );
			}

			$project_args['post__in'] = array_values( $project_ids );
		}

		$projects          = get_posts( $project_args );
		$response_projects = array();

		global $wpdb;
		$time_table = Ndizi_DB::get_table_name();

		foreach ( $projects as $project ) {
			$budget = get_post_meta( $project->ID, '_ndizi_project_budget', true );

			// Get total logged time for the project in seconds
			$totals             = Ndizi_DB::get_time_totals( array( 'project_id' => $project->ID ) );
			$project_logged_sec = ! empty( $totals ) ? intval( $totals[0]->total_duration ) : 0;

			// Query tasks for this project
			$task_args = array(
				'post_type'      => 'ndizi_task',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_project_id',
						'value' => $project->ID,
					),
				),
			);

			// Scope tasks for team members to only their assignments
			if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
				$task_args['meta_query'][] = array(
					'key'   => '_ndizi_assigned_user_id',
					'value' => $user_id,
				);
			}

			$tasks          = get_posts( $task_args );
			$response_tasks = array();

			foreach ( $tasks as $task ) {
				// Query duration total directly to avoid bloated query loop
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$task_logged_sec = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT SUM(duration) FROM $time_table WHERE task_id = %d",
						$task->ID
					)
				);
				$task_logged_sec = $task_logged_sec ? intval( $task_logged_sec ) : 0;

				$response_tasks[] = array(
					'id'           => $task->ID,
					'title'        => $task->post_title,
					'total_logged' => $task_logged_sec,
				);
			}

			$response_projects[] = array(
				'id'           => $project->ID,
				'title'        => $project->post_title,
				'budget'       => $budget ? floatval( $budget ) : null,
				'total_logged' => $project_logged_sec,
				'tasks'        => $response_tasks,
			);
		}

		wp_send_json_success( array( 'projects' => $response_projects ) );
	}

	/**
	 * AJAX endpoint to log completed time manually
	 */
	public static function ajax_log_time_manual() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$project_id  = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		$task_id     = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$duration    = isset( $_POST['duration'] ) ? intval( $_POST['duration'] ) : 0; // in seconds
		$billable    = isset( $_POST['billable'] ) ? intval( $_POST['billable'] ) : 1;

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID is required.', 'ndizi-project-management' ) ) );
		}
		if ( $duration <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Duration must be greater than zero.', 'ndizi-project-management' ) ) );
		}

		$user_id  = get_current_user_id();
		$entry_id = Ndizi_DB::log_time_manual( $user_id, $project_id, $task_id, $description, $duration, $billable );

		if ( ! $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to log time entry.', 'ndizi-project-management' ) ) );
		}

		wp_send_json_success( array( 'entry_id' => $entry_id ) );
	}
}
