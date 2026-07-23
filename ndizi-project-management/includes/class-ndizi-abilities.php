<?php
/**
 * Register Abilities and callbacks for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Abilities {

	/**
	 * Initialize Abilities support
	 */
	public static function init() {
		// Only register hooks if the Abilities API functions exist.
		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_categories' ) );
			add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		}
	}

	/**
	 * Register ability categories
	 */
	public static function register_categories() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'ndizi-pm',
				array(
					'label'       => __( 'Ndizi Project Management', 'ndizi-project-management' ),
					'description' => __( 'Abilities for managing projects, tasks, and logging time.', 'ndizi-project-management' ),
				)
			);
		}
	}

	/**
	 * Register Ndizi abilities
	 */
	public static function register_abilities() {
		// Get Projects Ability
		wp_register_ability(
			'ndizi/get-projects',
			array(
				'label'               => __( 'Get Projects', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves active projects and their metadata.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'          => array( 'type' => 'integer' ),
							'title'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
							'client_id'   => array( 'type' => 'integer' ),
							'client_name' => array( 'type' => 'string' ),
							'budget'      => array( 'type' => 'number' ),
							'start_date'  => array( 'type' => 'string' ),
							'end_date'    => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_projects' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_view_projects_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Get Tasks Ability
		wp_register_ability(
			'ndizi/get-tasks',
			array(
				'label'               => __( 'Get Tasks', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves active tasks, optionally filtered by project ID.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'project_id' => array(
							'type'        => 'integer',
							'description' => __( 'Optional project ID to filter tasks.', 'ndizi-project-management' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'           => array( 'type' => 'integer' ),
							'title'        => array( 'type' => 'string' ),
							'description'  => array( 'type' => 'string' ),
							'project_id'   => array( 'type' => 'integer' ),
							'project_name' => array( 'type' => 'string' ),
							'status'       => array( 'type' => 'string' ),
							'priority'     => array( 'type' => 'string' ),
							'due_date'     => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_tasks' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_view_tasks_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Get Active Timer Ability
		wp_register_ability(
			'ndizi/get-active-timer',
			array(
				'label'               => __( 'Get Active Timer', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves the currently running timer for the authenticated user.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'active' => array( 'type' => 'boolean' ),
						'timer'  => array(
							'type'       => 'object',
							'properties' => array(
								'id'            => array( 'type' => 'integer' ),
								'project_id'    => array( 'type' => 'integer' ),
								'task_id'       => array( 'type' => 'integer' ),
								'user_id'       => array( 'type' => 'integer' ),
								'description'   => array( 'type' => 'string' ),
								'start_time'    => array( 'type' => 'string' ),
								'live_duration' => array( 'type' => 'integer' ),
								'billable'      => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_active_timer' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_time_log_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Start Timer Ability
		wp_register_ability(
			'ndizi/start-timer',
			array(
				'label'               => __( 'Start Timer', 'ndizi-project-management' ),
				'description'         => __( 'Starts a running timer for the authenticated user.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'project_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The project ID.', 'ndizi-project-management' ),
						),
						'task_id'     => array(
							'type'        => 'integer',
							'description' => __( 'Optional task ID.', 'ndizi-project-management' ),
							'default'     => 0,
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Optional timer description.', 'ndizi-project-management' ),
							'default'     => '',
						),
						'billable'    => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the time is billable.', 'ndizi-project-management' ),
							'default'     => true,
						),
					),
					'required'   => array( 'project_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'timer'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'project_id'  => array( 'type' => 'integer' ),
								'task_id'     => array( 'type' => 'integer' ),
								'description' => array( 'type' => 'string' ),
								'start_time'  => array( 'type' => 'string' ),
								'billable'    => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'start_timer' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_time_log_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Stop Timer Ability
		wp_register_ability(
			'ndizi/stop-timer',
			array(
				'label'               => __( 'Stop Timer', 'ndizi-project-management' ),
				'description'         => __( 'Stops the currently running active timer.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'timer'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'project_id'  => array( 'type' => 'integer' ),
								'task_id'     => array( 'type' => 'integer' ),
								'description' => array( 'type' => 'string' ),
								'start_time'  => array( 'type' => 'string' ),
								'end_time'    => array( 'type' => 'string' ),
								'duration'    => array( 'type' => 'integer' ),
								'billable'    => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'stop_timer' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_time_log_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Log Time Manual Ability
		wp_register_ability(
			'ndizi/log-time-manual',
			array(
				'label'               => __( 'Log Time Manually', 'ndizi-project-management' ),
				'description'         => __( 'Logs a time entry manually with a specified duration.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'project_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The project ID.', 'ndizi-project-management' ),
						),
						'task_id'     => array(
							'type'        => 'integer',
							'description' => __( 'Optional task ID.', 'ndizi-project-management' ),
							'default'     => 0,
						),
						'description' => array(
							'type'        => 'string',
							'description' => __( 'Optional timer description.', 'ndizi-project-management' ),
							'default'     => '',
						),
						'duration'    => array(
							'type'        => 'integer',
							'description' => __( 'Duration in seconds.', 'ndizi-project-management' ),
						),
						'billable'    => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the time is billable.', 'ndizi-project-management' ),
							'default'     => true,
						),
						'start_time'  => array(
							'type'        => 'string',
							'description' => __( 'Optional start datetime string (Y-m-d H:i:s). Defaults to current time.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'project_id', 'duration' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'timer'   => array(
							'type'       => 'object',
							'properties' => array(
								'id'          => array( 'type' => 'integer' ),
								'project_id'  => array( 'type' => 'integer' ),
								'task_id'     => array( 'type' => 'integer' ),
								'description' => array( 'type' => 'string' ),
								'start_time'  => array( 'type' => 'string' ),
								'end_time'    => array( 'type' => 'string' ),
								'duration'    => array( 'type' => 'integer' ),
								'billable'    => array( 'type' => 'boolean' ),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'log_time_manual' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_time_log_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		// Get Invoices Ability
		wp_register_ability(
			'ndizi/get-invoices',
			array(
				'label'               => __( 'Get Invoices', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves invoices, optionally filtered by project ID, client ID, or status.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'project_id' => array(
							'type'        => 'integer',
							'description' => __( 'Optional project ID to filter invoices.', 'ndizi-project-management' ),
						),
						'client_id'  => array(
							'type'        => 'integer',
							'description' => __( 'Optional client ID to filter invoices.', 'ndizi-project-management' ),
						),
						'status'     => array(
							'type'        => 'string',
							'description' => __( 'Optional invoice status to filter by (e.g. draft, sent, paid, overdue, void).', 'ndizi-project-management' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'           => array( 'type' => 'integer' ),
							'number'       => array( 'type' => 'string' ),
							'project_id'   => array( 'type' => 'integer' ),
							'project_name' => array( 'type' => 'string' ),
							'client_id'    => array( 'type' => 'integer' ),
							'client_name'  => array( 'type' => 'string' ),
							'status'       => array( 'type' => 'string' ),
							'amount'       => array( 'type' => 'number' ),
							'balance'      => array( 'type' => 'number' ),
							'currency'     => array( 'type' => 'string' ),
							'invoice_date' => array( 'type' => 'string' ),
							'due_date'     => array( 'type' => 'string' ),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_invoices' ),
				'permission_callback' => function ( $input = null ) {
					unset( $input );
					return Ndizi_REST::check_view_invoices_permission();
				},
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Execute callback for get-projects ability.
	 *
	 * @return array
	 */
	public static function get_projects( $input = null ) {
		unset( $input );
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
							'value' => get_current_user_id(),
						),
					),
				)
			);

			if ( ! empty( $tasks ) ) {
				update_meta_cache( 'post', $tasks );
			}

			$project_ids = array();
			foreach ( $tasks as $task_id ) {
				$project_id = (int) get_post_meta( $task_id, '_ndizi_project_id', true );
				if ( $project_id ) {
					$project_ids[ $project_id ] = $project_id;
				}
			}
			$project_ids = array_values( $project_ids );

			if ( empty( $project_ids ) ) {
				return array();
			}
			$args['post__in'] = $project_ids;
		}

		$projects = get_posts( $args );
		$response = array();

		foreach ( $projects as $project ) {
			$client_id = get_post_meta( $project->ID, '_ndizi_client_id', true );
			$client    = $client_id ? get_post( $client_id ) : null;
			$budget    = get_post_meta( $project->ID, '_ndizi_project_budget', true );

			$response[] = array(
				'id'          => $project->ID,
				'title'       => $project->post_title,
				'description' => $project->post_content,
				'client_id'   => $client_id ? intval( $client_id ) : 0,
				'client_name' => $client ? $client->post_title : '',
				'budget'      => $budget ? floatval( $budget ) : 0.0,
				'start_date'  => (string) get_post_meta( $project->ID, '_ndizi_project_start_date', true ),
				'end_date'    => (string) get_post_meta( $project->ID, '_ndizi_project_end_date', true ),
			);
		}

		return $response;
	}

	/**
	 * Execute callback for get-tasks ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_tasks( $input ) {
		$project_id = isset( $input['project_id'] ) ? intval( $input['project_id'] ) : 0;

		$args = array(
			'post_type'      => 'ndizi_task',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( $project_id ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ndizi_project_id',
					'value' => $project_id,
				),
			);
		}

		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
			if ( ! isset( $args['meta_query'] ) ) {
				$args['meta_query'] = array();
			}
			$args['meta_query'][] = array(
				'key'   => '_ndizi_assigned_user_id',
				'value' => get_current_user_id(),
			);
		}

		$tasks    = get_posts( $args );
		$response = array();

		foreach ( $tasks as $task ) {
			$p_id    = get_post_meta( $task->ID, '_ndizi_project_id', true );
			$project = $p_id ? get_post( $p_id ) : null;

			$response[] = array(
				'id'           => $task->ID,
				'title'        => $task->post_title,
				'description'  => $task->post_content,
				'project_id'   => $p_id ? intval( $p_id ) : 0,
				'project_name' => $project ? $project->post_title : '',
				'status'       => (string) get_post_meta( $task->ID, '_ndizi_task_status', true ),
				'priority'     => (string) get_post_meta( $task->ID, '_ndizi_task_priority', true ),
				'due_date'     => (string) get_post_meta( $task->ID, '_ndizi_task_due_date', true ),
			);
		}

		return $response;
	}

	/**
	 * Execute callback for get-invoices ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_invoices( $input ) {
		$args = array(
			'post_type'      => 'ndizi_invoice',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(),
		);

		if ( ! empty( $input['project_id'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_ndizi_project_id',
				'value' => intval( $input['project_id'] ),
			);
		}

		if ( ! empty( $input['client_id'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_ndizi_client_id',
				'value' => intval( $input['client_id'] ),
			);
		}

		if ( ! empty( $input['status'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_ndizi_invoice_status',
				'value' => sanitize_text_field( $input['status'] ),
			);
		}

		$invoices = get_posts( $args );
		$response = array();

		foreach ( $invoices as $invoice ) {
			$project_id = get_post_meta( $invoice->ID, '_ndizi_project_id', true );
			$project    = $project_id ? get_post( $project_id ) : null;
			$client_id  = get_post_meta( $invoice->ID, '_ndizi_client_id', true );
			$client     = $client_id ? get_post( $client_id ) : null;

			$response[] = array(
				'id'           => $invoice->ID,
				'number'       => (string) get_post_meta( $invoice->ID, '_ndizi_invoice_number', true ),
				'project_id'   => $project_id ? intval( $project_id ) : 0,
				'project_name' => $project ? $project->post_title : '',
				'client_id'    => $client_id ? intval( $client_id ) : 0,
				'client_name'  => $client ? $client->post_title : '',
				'status'       => (string) get_post_meta( $invoice->ID, '_ndizi_invoice_status', true ),
				'amount'       => floatval( get_post_meta( $invoice->ID, '_ndizi_invoice_amount', true ) ),
				'balance'      => floatval( Ndizi_Invoicing::get_invoice_balance( $invoice->ID ) ),
				'currency'     => (string) get_post_meta( $invoice->ID, '_ndizi_invoice_currency', true ),
				'invoice_date' => (string) get_post_meta( $invoice->ID, '_ndizi_invoice_date', true ),
				'due_date'     => (string) get_post_meta( $invoice->ID, '_ndizi_invoice_due_date', true ),
			);
		}

		return $response;
	}

	/**
	 * Execute callback for get-active-timer ability.
	 *
	 * @return array
	 */
	public static function get_active_timer( $input = null ) {
		unset( $input );
		$user_id = get_current_user_id();
		$timer   = Ndizi_DB::get_active_timer( $user_id );

		if ( ! $timer ) {
			return array( 'active' => false );
		}

		$start_ts      = strtotime( $timer->start_time );
		$now_ts        = time();
		$live_duration = max( 0, $now_ts - $start_ts );

		return array(
			'active' => true,
			'timer'  => array(
				'id'            => intval( $timer->id ),
				'project_id'    => intval( $timer->project_id ),
				'task_id'       => intval( $timer->task_id ),
				'user_id'       => intval( $timer->user_id ),
				'description'   => (string) $timer->description,
				'start_time'    => (string) $timer->start_time,
				'live_duration' => intval( $live_duration ),
				'billable'      => (bool) $timer->billable,
			),
		);
	}

	/**
	 * Execute callback for start-timer ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function start_timer( $input ) {
		$user_id     = get_current_user_id();
		$project_id  = intval( $input['project_id'] );
		$task_id     = isset( $input['task_id'] ) ? intval( $input['task_id'] ) : 0;
		$description = isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '';
		$billable    = isset( $input['billable'] ) ? (bool) $input['billable'] : true;

		$timer_id = Ndizi_Time_Service::start_timer(
			$user_id,
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'billable'    => $billable ? 1 : 0,
			)
		);
		if ( is_wp_error( $timer_id ) ) {
			return $timer_id;
		}

		$timer = Ndizi_DB::get_time_entry( $timer_id );

		return array(
			'success' => true,
			'timer'   => array(
				'id'          => intval( $timer->id ),
				'project_id'  => intval( $timer->project_id ),
				'task_id'     => intval( $timer->task_id ),
				'description' => (string) $timer->description,
				'start_time'  => (string) $timer->start_time,
				'billable'    => (bool) $timer->billable,
			),
		);
	}

	/**
	 * Execute callback for stop-timer ability.
	 *
	 * @return array|WP_Error
	 */
	public static function stop_timer( $input = null ) {
		unset( $input );

		$stopped = Ndizi_Time_Service::stop_timer( get_current_user_id() );
		if ( is_wp_error( $stopped ) ) {
			return $stopped;
		}

		return array(
			'success' => true,
			'timer'   => array(
				'id'          => intval( $stopped->id ),
				'project_id'  => intval( $stopped->project_id ),
				'task_id'     => intval( $stopped->task_id ),
				'description' => (string) $stopped->description,
				'start_time'  => (string) $stopped->start_time,
				'end_time'    => (string) $stopped->end_time,
				'duration'    => intval( $stopped->duration ),
				'billable'    => (bool) $stopped->billable,
			),
		);
	}

	/**
	 * Execute callback for log-time-manual ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function log_time_manual( $input ) {
		$user_id     = get_current_user_id();
		$project_id  = intval( $input['project_id'] );
		$task_id     = isset( $input['task_id'] ) ? intval( $input['task_id'] ) : 0;
		$description = isset( $input['description'] ) ? sanitize_text_field( $input['description'] ) : '';
		$duration    = intval( $input['duration'] );
		$billable    = isset( $input['billable'] ) ? (bool) $input['billable'] : true;
		$start_time  = isset( $input['start_time'] ) ? sanitize_text_field( $input['start_time'] ) : '';
		$end_time    = isset( $input['end_time'] ) ? sanitize_text_field( $input['end_time'] ) : '';

		$entry_id = Ndizi_Time_Service::log_time_manual(
			$user_id,
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'duration'    => $duration,
				'billable'    => $billable ? 1 : 0,
				'start_time'  => $start_time,
				'end_time'    => $end_time,
			)
		);
		if ( is_wp_error( $entry_id ) ) {
			return $entry_id;
		}

		$timer = Ndizi_DB::get_time_entry( $entry_id );

		return array(
			'success' => true,
			'timer'   => array(
				'id'          => intval( $timer->id ),
				'project_id'  => intval( $timer->project_id ),
				'task_id'     => intval( $timer->task_id ),
				'description' => (string) $timer->description,
				'start_time'  => (string) $timer->start_time,
				'end_time'    => (string) $timer->end_time,
				'duration'    => intval( $timer->duration ),
				'billable'    => (bool) $timer->billable,
			),
		);
	}
}
