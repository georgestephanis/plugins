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
		self::hook_user_authorized_actions();
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
		if ( ! is_admin() && ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style( 'ndizi-adminbar-style', NDIZI_PLUGIN_URL . 'build/adminbar.css', array(), NDIZI_VERSION );
		wp_enqueue_script( 'ndizi-adminbar-script', NDIZI_PLUGIN_URL . 'build/adminbar.js', array( 'jquery', 'wp-util' ), NDIZI_VERSION, true );

		wp_localize_script(
			'ndizi-adminbar-script',
			'ndizi_adminbar',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ndizi-admin-nonce' ),
				'labels'   => array(
					'loading_projects' => __( 'Loading projects...', 'ndizi-project-management' ),
					'select_project'   => __( '-- Select Project --', 'ndizi-project-management' ),
					'select_task'      => __( '-- General --', 'ndizi-project-management' ),
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
				'<span class="ndizi-ab-icon-wrapper"><span class="ndizi-ab-pulse"></span>%s</span><span class="ndizi-ab-label">%s</span>',
				self::get_admin_bar_icon_svg(),
				esc_html( $active_duration_str )
			);
		} else {
			$title = sprintf(
				'<span class="ndizi-ab-icon-wrapper">%s</span><span class="ndizi-ab-label screen-reader-text">%s</span>',
				self::get_admin_bar_icon_svg(),
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
						<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg> 
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
						<option value="0"><?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?></option>
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
						<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg> <?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
					</button>

					<button type="button" class="button ndizi-ab-btn-toggle-manual" id="ndizi-ab-btn-toggle-manual">
						<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg> <?php esc_html_e( 'Log Manual Entry', 'ndizi-project-management' ); ?>
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
						<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-btn-icon-svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg> <?php esc_html_e( 'Log Entry', 'ndizi-project-management' ); ?>
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

			// Fetch all task duration totals in one query instead of one per task.
			$task_durations = array();
			if ( ! empty( $tasks ) ) {
				$task_ids     = wp_list_pluck( $tasks, 'ID' );
				$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$duration_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT task_id, SUM(duration) AS total_duration FROM $time_table WHERE task_id IN ($placeholders) GROUP BY task_id",
						$task_ids
					)
				);
				if ( $duration_rows ) {
					foreach ( $duration_rows as $row ) {
						$task_durations[ (int) $row->task_id ] = intval( $row->total_duration );
					}
				}
			}

			foreach ( $tasks as $task ) {
				$task_logged_sec = isset( $task_durations[ $task->ID ] ) ? $task_durations[ $task->ID ] : 0;

				$response_tasks[] = array(
					'id'           => $task->ID,
					'title'        => $task->post_title,
					'total_logged' => $task_logged_sec,
				);
			}

			$client_id   = get_post_meta( $project->ID, '_ndizi_client_id', true );
			$client_name = '';
			if ( $client_id ) {
				$client = get_post( $client_id );
				if ( $client ) {
					$client_name = $client->post_title;
				}
			}
			if ( empty( $client_name ) ) {
				$client_name = __( 'Internal', 'ndizi-project-management' );
			}

			$response_projects[] = array(
				'id'           => $project->ID,
				'title'        => $project->post_title,
				'client_name'  => $client_name,
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

	/**
	 * Get the selected SVG icon markup for the admin bar
	 */
	public static function get_admin_bar_icon_svg() {
		$selected = get_option( 'ndizi_adminbar_icon', 'banana' );

		switch ( $selected ) {
			case 'clock':
				return '<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-icon-svg ndizi-ab-icon-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
			case 'punch_clock':
				return '<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-icon-svg ndizi-ab-icon-punch" viewBox="0 -960 960 960" fill="currentColor"><path d="M360-120v-80h240v80H360Zm120-160q-100 0-170-70t-70-170q0-100 70-170t170-70q100 0 170 70t70 170q0 100-70 170t-170 70Zm0-80q66 0 113-47t47-113q0-66-47-113t-113-47q-66 0-113 47t-47 113q0 66 47 113t113 47ZM80-560v-80h160v80H80Zm640 0v-80h160v80H720ZM440-440h80v120l-70 70-56-56 46-46v-88Z"/></svg>';
			case 'hourglass':
				return '<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-icon-svg ndizi-ab-icon-hourglass" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14" /><path d="M5 2h14" /><path d="M17 22v-4.172a2 2 0 0 0 -.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22" /><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" /></svg>';
			case 'banana':
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" class="ndizi-ab-icon-svg ndizi-ab-icon-banana" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6v-2a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v2a9.09 9.09 0 0 1 -4 8.08c-2 1.31 -5 1.57 -7 1.59a2 2 0 0 0 -2 2a2 2 0 0 0 1.16 1.81c2.69 1.2 9.46 3.44 14.35 -1.66c4.49 -4.74 1.49 -11.82 1.49 -11.82" /></svg>';
		}
	}
}
