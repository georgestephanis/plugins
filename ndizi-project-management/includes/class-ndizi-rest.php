<?php
/**
 * REST API routes for Ndizi Project Management (Desktop/Mobile integration)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_REST {

	/**
	 * Register REST API hooks
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public static function register_routes() {
		$namespace = 'ndizi/v1';

		// Projects endpoint
		register_rest_route(
			$namespace,
			'/projects',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_projects' ),
				'permission_callback' => array( __CLASS__, 'check_view_projects_permission' ),
			)
		);

		// Tasks endpoint
		register_rest_route(
			$namespace,
			'/tasks',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_tasks' ),
				'permission_callback' => array( __CLASS__, 'check_view_tasks_permission' ),
			)
		);

		// Active timer endpoint
		register_rest_route(
			$namespace,
			'/time/active',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_active_timer' ),
				'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
			)
		);

		// Start timer endpoint
		register_rest_route(
			$namespace,
			'/time/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'start_timer' ),
				'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
				'args'                => array(
					'project_id'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'task_id'     => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'description' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'billable'    => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		// Stop timer endpoint
		register_rest_route(
			$namespace,
			'/time/stop',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'stop_timer' ),
				'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
			)
		);

		// Log time manual endpoint
		register_rest_route(
			$namespace,
			'/time/log',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'log_time_manual' ),
				'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
				'args'                => array(
					'project_id'  => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'task_id'     => array(
						'required'          => false,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'description' => array(
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'duration'    => array(
						'required'          => true,
						'description'       => 'Duration in seconds',
						'sanitize_callback' => 'absint',
					),
					'billable'    => array(
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'start_time'  => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// List time logs history
		register_rest_route(
			$namespace,
			'/time',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_time_logs' ),
				'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
			)
		);

		// Delete/edit timer endpoint
		register_rest_route(
			$namespace,
			'/time/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_time_log' ),
					'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_time_log' ),
					'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
				),
			)
		);
	}

	/**
	 * Permission check: view projects
	 */
	public static function check_view_projects_permission() {
		return current_user_can( 'ndizi_view_projects' ) || current_user_can( 'ndizi_manage_projects' );
	}

	/**
	 * Permission check: view tasks
	 */
	public static function check_view_tasks_permission() {
		return current_user_can( 'ndizi_view_tasks' ) || current_user_can( 'ndizi_manage_tasks' );
	}

	/**
	 * Permission check: log time
	 */
	public static function check_time_log_permission() {
		return current_user_can( 'ndizi_log_time' ) || current_user_can( 'ndizi_manage_time' );
	}

	/**
	 * Get the IDs of projects the current user is involved in.
	 *
	 * "Involved in" means the project contains at least one task assigned to the
	 * user. Used to scope project/task visibility for non-manager team members.
	 *
	 * @return int[] List of project post IDs (may be empty).
	 */
	private static function get_current_user_project_ids() {
		$tasks = get_posts(
			array(
				'post_type'      => 'ndizi_task',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_assigned_user_id',
						'value' => get_current_user_id(),
					),
				),
			)
		);

		$project_ids = array();
		foreach ( $tasks as $task_id ) {
			$project_id = (int) get_post_meta( $task_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				$project_ids[ $project_id ] = $project_id;
			}
		}

		return array_values( $project_ids );
	}

	/**
	 * Get list of active projects
	 */
	public static function get_projects() {
		$args = array(
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

		// Team members (who cannot manage projects) only see projects they are
		// involved in, i.e. projects containing a task assigned to them.
		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_projects' ) ) {
			$project_ids = self::get_current_user_project_ids();
			if ( empty( $project_ids ) ) {
				return new WP_REST_Response( array(), 200 );
			}
			$args['post__in'] = $project_ids;
		}

		$projects = get_posts( $args );

		$response = array();
		foreach ( $projects as $project ) {
			$client_id = get_post_meta( $project->ID, '_ndizi_client_id', true );
			$client    = $client_id ? get_post( $client_id ) : null;

			$response[] = array(
				'id'          => $project->ID,
				'title'       => $project->post_title,
				'description' => $project->post_content,
				'client_id'   => $client_id ? intval( $client_id ) : null,
				'client_name' => $client ? $client->post_title : '',
				'budget'      => get_post_meta( $project->ID, '_ndizi_project_budget', true ),
				'start_date'  => get_post_meta( $project->ID, '_ndizi_project_start_date', true ),
				'end_date'    => get_post_meta( $project->ID, '_ndizi_project_end_date', true ),
			);
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get list of active tasks
	 */
	public static function get_tasks( $request ) {
		$project_id = $request->get_param( 'project_id' );

		$args = array(
			'post_type'      => 'ndizi_task',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( $project_id ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ndizi_project_id',
					'value' => intval( $project_id ),
				),
			);
		}

		// Team members (who cannot manage tasks) only see tasks assigned to them.
		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}
			$args['meta_query'][] = array(
				'key'   => '_ndizi_assigned_user_id',
				'value' => get_current_user_id(),
			);
		}

		$tasks = get_posts( $args );

		$response = array();
		foreach ( $tasks as $task ) {
			$p_id    = get_post_meta( $task->ID, '_ndizi_project_id', true );
			$project = $p_id ? get_post( $p_id ) : null;

			$response[] = array(
				'id'           => $task->ID,
				'title'        => $task->post_title,
				'description'  => $task->post_content,
				'project_id'   => $p_id ? intval( $p_id ) : null,
				'project_name' => $project ? $project->post_title : '',
				'status'       => get_post_meta( $task->ID, '_ndizi_task_status', true ),
				'priority'     => get_post_meta( $task->ID, '_ndizi_task_priority', true ),
				'due_date'     => get_post_meta( $task->ID, '_ndizi_task_due_date', true ),
			);
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get the currently running timer for the authenticated user
	 */
	public static function get_active_timer() {
		$user_id = get_current_user_id();
		$timer   = Ndizi_DB::get_active_timer( $user_id );

		if ( ! $timer ) {
			return new WP_REST_Response( array( 'active' => false ), 200 );
		}

		// Calculate current live duration
		$start_ts             = strtotime( $timer->start_time );
		$now_ts               = strtotime( current_time( 'mysql' ) );
		$timer->live_duration = max( 0, $now_ts - $start_ts );

		return new WP_REST_Response(
			array(
				'active' => true,
				'timer'  => $timer,
			),
			200
		);
	}

	/**
	 * Start a running timer
	 */
	public static function start_timer( $request ) {
		$user_id     = get_current_user_id();
		$project_id  = $request->get_param( 'project_id' );
		$task_id     = $request->get_param( 'task_id' );
		$description = $request->get_param( 'description' );
		$billable    = $request->get_param( 'billable' );

		$timer_id = Ndizi_DB::start_timer( $user_id, $project_id, $task_id, $description, $billable );

		if ( ! $timer_id ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to start timer', 'ndizi-project-management' ) ), 500 );
		}

		$timer = Ndizi_DB::get_time_entry( $timer_id );
		return new WP_REST_Response(
			array(
				'success' => true,
				'timer'   => $timer,
			),
			201
		);
	}

	/**
	 * Stop active timer
	 */
	public static function stop_timer() {
		$user_id = get_current_user_id();
		$stopped = Ndizi_DB::stop_timer( $user_id );

		if ( ! $stopped ) {
			return new WP_REST_Response( array( 'error' => __( 'No active timer found', 'ndizi-project-management' ) ), 400 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'timer'   => $stopped,
			),
			200
		);
	}

	/**
	 * Log time manually
	 */
	public static function log_time_manual( $request ) {
		$user_id     = get_current_user_id();
		$project_id  = $request->get_param( 'project_id' );
		$task_id     = $request->get_param( 'task_id' );
		$description = $request->get_param( 'description' );
		$duration    = $request->get_param( 'duration' ); // in seconds
		$billable    = $request->get_param( 'billable' );
		$start_time  = $request->get_param( 'start_time' );

		$entry_id = Ndizi_DB::log_time_manual( $user_id, $project_id, $task_id, $description, $duration, $billable, $start_time );

		if ( ! $entry_id ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to log time', 'ndizi-project-management' ) ), 500 );
		}

		$timer = Ndizi_DB::get_time_entry( $entry_id );
		return new WP_REST_Response(
			array(
				'success' => true,
				'timer'   => $timer,
			),
			201
		);
	}

	/**
	 * Get time logs history for the authenticated user
	 */
	public static function get_time_logs( $request ) {
		$user_id = get_current_user_id();
		$args    = array(
			'user_id' => $user_id,
			'number'  => 50, // default limit
		);

		$project_id = $request->get_param( 'project_id' );
		if ( $project_id ) {
			$args['project_id'] = $project_id;
		}

		$logs = Ndizi_DB::get_time_entries( $args );

		// Include titles for easier visualization in external apps
		foreach ( $logs as $log ) {
			$project           = get_post( $log->project_id );
			$log->project_name = $project ? $project->post_title : __( 'Deleted Project', 'ndizi-project-management' );

			if ( $log->task_id ) {
				$task           = get_post( $log->task_id );
				$log->task_name = $task ? $task->post_title : '';
			} else {
				$log->task_name = '';
			}
		}

		return new WP_REST_Response( $logs, 200 );
	}

	/**
	 * Update an existing time entry
	 */
	public static function update_time_log( $request ) {
		$id      = $request->get_param( 'id' );
		$user_id = get_current_user_id();
		$log     = Ndizi_DB::get_time_entry( $id );

		if ( ! $log ) {
			return new WP_REST_Response( array( 'error' => __( 'Time entry not found', 'ndizi-project-management' ) ), 404 );
		}

		// Users can only edit their own logs, unless they can manage all time.
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Unauthorized to edit this entry', 'ndizi-project-management' ) ), 403 );
		}

		// Build data list from params
		$params = array( 'project_id', 'task_id', 'description', 'start_time', 'end_time', 'duration', 'billable' );
		$data   = array();

		foreach ( $params as $param ) {
			if ( $request->has_param( $param ) ) {
				$data[ $param ] = $request->get_param( $param );
			}
		}

		$updated = Ndizi_DB::update_time_entry( $id, $data );

		if ( ! $updated ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to update time entry', 'ndizi-project-management' ) ), 500 );
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'timer'   => Ndizi_DB::get_time_entry( $id ),
			),
			200
		);
	}

	/**
	 * Delete an existing time entry
	 */
	public static function delete_time_log( $request ) {
		$id      = $request->get_param( 'id' );
		$user_id = get_current_user_id();
		$log     = Ndizi_DB::get_time_entry( $id );

		if ( ! $log ) {
			return new WP_REST_Response( array( 'error' => __( 'Time entry not found', 'ndizi-project-management' ) ), 404 );
		}

		// Users can only delete their own logs, unless they can manage all time.
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Unauthorized to delete this entry', 'ndizi-project-management' ) ), 403 );
		}

		$deleted = Ndizi_DB::delete_time_entry( $id );

		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to delete time entry', 'ndizi-project-management' ) ), 500 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
