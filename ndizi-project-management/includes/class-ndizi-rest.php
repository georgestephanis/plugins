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
		add_action( 'admin_init', array( __CLASS__, 'register_core_data_entities' ) );
		add_action( 'template_redirect', array( __CLASS__, 'register_core_data_entities' ) );
	}

	/**
	 * Register custom entities with core data.
	 *
	 * This will allow us to use WP convenience methods, such as `useEntityRecords`.
	 */
	public static function register_core_data_entities() {
		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			return;
		}

		wp_add_inline_script(
			'wp-core-data',
			"wp.data.dispatch( 'core' ).addEntities( [
				{
					name: 'time-entry',
					plural: 'time-entries',
					label: '" . esc_js( __( 'Time Entries', 'ndizi-project-management' ) ) . "',
					kind: 'ndizi',
					baseURL: '/ndizi/v1/time',
					baseURLParams: { context: 'edit' },
				}
			] );",
			'after'
		);
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
				'args'                => array(
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
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
				'args'                => array(
					'per_page'   => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'       => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'project_id' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
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
					'end_time'    => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// List and create time logs
		register_rest_route(
			$namespace,
			'/time',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_time_logs' ),
					'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_time_log' ),
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
						'user_id'     => array(
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
						'description' => array(
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'duration'    => array(
							'required'          => true,
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
						'end_time'    => array(
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
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
					'args'                => array(
						'id'          => array(
							'sanitize_callback' => 'absint',
						),
						'project_id'  => array(
							'sanitize_callback' => 'absint',
						),
						'task_id'     => array(
							'sanitize_callback' => 'absint',
						),
						'description' => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'start_time'  => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'end_time'    => array(
							'sanitize_callback' => 'sanitize_text_field',
						),
						'duration'    => array(
							'sanitize_callback' => 'absint',
						),
						'billable'    => array(
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_time_log' ),
					'permission_callback' => array( __CLASS__, 'check_time_log_permission' ),
					'args'                => array(
						'id' => array(
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		// Register REST routes for active modules
		Ndizi_Project_Management::register_active_rest_routes();
	}

	/**
	 * Register invoicing specific REST routes.
	 * Called dynamically if invoicing module is active.
	 */
	public static function register_invoicing_routes() {
		$namespace = 'ndizi/v1';

		// Stripe Payment Endpoint
		register_rest_route(
			$namespace,
			'/invoices/(?P<id>\d+)/pay',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_stripe_checkout_session' ),
				'permission_callback' => array( __CLASS__, 'check_invoice_pay_permission' ),
			)
		);

		// Stripe Webhook Endpoint
		register_rest_route(
			$namespace,
			'/stripe/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Register calendar specific REST routes.
	 * Called dynamically if calendar module is active.
	 */
	public static function register_calendar_routes() {
		$namespace = 'ndizi/v1';

		// Calendar iCal feed Endpoint
		register_rest_route(
			$namespace,
			'/calendar/ical',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_calendar_ical' ),
				'permission_callback' => '__return_true',
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

		return array_values( $project_ids );
	}

	/**
	 * Get list of active projects
	 */
	public static function get_projects( $request ) {
		$per_page = min( absint( $request->get_param( 'per_page' ) ?: 100 ), 200 );
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );

		$args = array(
			'post_type'      => 'ndizi_project',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
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

		$query       = new WP_Query( $args );
		$projects    = $query->posts;
		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		if ( ! empty( $projects ) ) {
			$project_ids = wp_list_pluck( $projects, 'ID' );
			update_meta_cache( 'post', $project_ids );

			$client_ids = array_filter(
				array_unique(
					array_map(
						function ( $p ) {
							return (int) get_post_meta( $p->ID, '_ndizi_client_id', true );
						},
						$projects
					)
				)
			);
			if ( ! empty( $client_ids ) ) {
				_prime_post_caches( $client_ids );
			}
		}

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

		$rest_response = new WP_REST_Response( $response, 200 );
		$rest_response->header( 'X-WP-Total', $total );
		$rest_response->header( 'X-WP-TotalPages', $total_pages );
		return $rest_response;
	}

	/**
	 * Get list of active tasks
	 */
	public static function get_tasks( $request ) {
		$per_page   = min( absint( $request->get_param( 'per_page' ) ?: 100 ), 200 );
		$page       = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$project_id = $request->get_param( 'project_id' );

		$args = array(
			'post_type'      => 'ndizi_task',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
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

		$query       = new WP_Query( $args );
		$tasks       = $query->posts;
		$total       = (int) $query->found_posts;
		$total_pages = (int) $query->max_num_pages;

		if ( ! empty( $tasks ) ) {
			$task_ids = wp_list_pluck( $tasks, 'ID' );
			update_meta_cache( 'post', $task_ids );

			$project_ids = array_filter(
				array_unique(
					array_map(
						function ( $t ) {
							return (int) get_post_meta( $t->ID, '_ndizi_project_id', true );
						},
						$tasks
					)
				)
			);
			if ( ! empty( $project_ids ) ) {
				_prime_post_caches( $project_ids );
			}
		}

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

		$rest_response = new WP_REST_Response( $response, 200 );
		$rest_response->header( 'X-WP-Total', $total );
		$rest_response->header( 'X-WP-TotalPages', $total_pages );
		return $rest_response;
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
		$now_ts               = time();
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

		$timer_id = Ndizi_Time_Service::start_timer(
			$user_id,
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'billable'    => $billable,
			)
		);
		if ( is_wp_error( $timer_id ) ) {
			$code        = $timer_id->get_error_code();
			$status_code = in_array( $code, array( 'invalid_project', 'invalid_task', 'date_locked' ), true ) ? 400 : ( 'db_error' === $code ? 500 : 403 );
			return new WP_REST_Response( array( 'error' => $timer_id->get_error_message() ), $status_code );
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
		$stopped = Ndizi_Time_Service::stop_timer( get_current_user_id() );
		if ( is_wp_error( $stopped ) ) {
			$status_code = 'db_error' === $stopped->get_error_code() ? 500 : 400;
			return new WP_REST_Response( array( 'error' => $stopped->get_error_message() ), $status_code );
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
		$end_time    = $request->get_param( 'end_time' );

		$entry_id = Ndizi_Time_Service::log_time_manual(
			$user_id,
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'duration'    => $duration,
				'billable'    => $billable,
				'start_time'  => $start_time,
				'end_time'    => $end_time,
			)
		);
		if ( is_wp_error( $entry_id ) ) {
			$code        = $entry_id->get_error_code();
			$status_code = in_array( $code, array( 'invalid_project', 'invalid_task', 'invalid_duration', 'date_locked' ), true ) ? 400 : ( 'db_error' === $code ? 500 : 403 );
			return new WP_REST_Response( array( 'error' => $entry_id->get_error_message() ), $status_code );
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
		$per_page = $request->get_param( 'per_page' );
		if ( ! $per_page ) {
			$per_page = 20;
		} else {
			// Cap the page size so a client cannot request an unbounded result
			// set (which would amplify the per-row lookups below) and clamp to
			// at least 1.
			$per_page = min( 100, max( 1, intval( $per_page ) ) );
		}

		$page = $request->get_param( 'page' );
		if ( ! $page ) {
			$page = 1;
		} else {
			// Force a valid page so the computed offset can never go negative.
			$page = max( 1, intval( $page ) );
		}

		$args = array(
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		// Project filter
		$project_id = $request->get_param( 'project_id' );
		if ( $project_id ) {
			$args['project_id'] = intval( $project_id );
		}

		// User filter (managers only, team members are forced to current user)
		$can_manage = Ndizi_Roles::current_user_can( 'ndizi_manage_time' );
		if ( ! $can_manage ) {
			$args['user_id'] = get_current_user_id();
		} else {
			$filter_user = $request->get_param( 'user_id' );
			if ( $filter_user ) {
				$args['user_id'] = intval( $filter_user );
			}
		}

		// Billable status filter
		$billable = $request->get_param( 'billable' );
		if ( null !== $billable && '' !== $billable ) {
			if ( 'yes' === $billable || '1' === $billable || true === $billable || 1 === intval( $billable ) ) {
				$args['billable'] = 1;
			} elseif ( 'no' === $billable || '0' === $billable || false === $billable || 0 === intval( $billable ) ) {
				$args['billable'] = 0;
			}
		}

		// Approved status filter
		$approved = $request->get_param( 'approved' );
		if ( null !== $approved && '' !== $approved ) {
			if ( 'yes' === $approved || '1' === $approved || true === $approved || 1 === intval( $approved ) ) {
				$args['approved'] = 1;
			} elseif ( 'no' === $approved || '0' === $approved || false === $approved || 0 === intval( $approved ) ) {
				$args['approved'] = 0;
			}
		}

		// Search filter
		$search = $request->get_param( 'search' );
		if ( ! $search ) {
			$search = $request->get_param( 's' );
		}
		if ( $search ) {
			$args['search'] = sanitize_text_field( $search );
		}

		// Order & Orderby
		$orderby = $request->get_param( 'orderby' );
		if ( $orderby ) {
			$args['orderby'] = sanitize_key( $orderby );
		}
		$order = $request->get_param( 'order' );
		if ( $order ) {
			$args['order'] = strtoupper( sanitize_key( $order ) ) === 'ASC' ? 'ASC' : 'DESC';
		}

		$total_entries = Ndizi_DB::get_time_entries_count( $args );
		$total_pages   = ceil( $total_entries / $per_page );

		$logs = Ndizi_DB::get_time_entries( $args );

		// Include names for easier rendering in custom table/react views
		foreach ( $logs as $log ) {
			$project           = get_post( $log->project_id );
			$log->project_name = $project ? $project->post_title : __( 'Deleted Project', 'ndizi-project-management' );

			if ( $log->task_id ) {
				$task           = get_post( $log->task_id );
				$log->task_name = $task ? $task->post_title : '';
			} else {
				$log->task_name = '';
			}

			$user           = get_userdata( $log->user_id );
			$log->user_name = $user ? $user->display_name : '';
		}

		$response = new WP_REST_Response( $logs, 200 );
		$response->header( 'X-WP-Total', $total_entries );
		$response->header( 'X-WP-TotalPages', $total_pages );

		return $response;
	}

	/**
	 * Create a new time entry
	 */
	public static function create_time_log( $request ) {
		$project_id  = intval( $request->get_param( 'project_id' ) );
		$task_id     = intval( $request->get_param( 'task_id' ) );
		$user_id     = intval( $request->get_param( 'user_id' ) );
		$description = sanitize_text_field( $request->get_param( 'description' ) );
		$duration    = intval( $request->get_param( 'duration' ) );
		$billable    = $request->get_param( 'billable' ) ? 1 : 0;
		$start_time  = sanitize_text_field( $request->get_param( 'start_time' ) );
		$end_time    = sanitize_text_field( $request->get_param( 'end_time' ) );

		$can_manage = Ndizi_Roles::current_user_can( 'ndizi_manage_time' );
		if ( ! $can_manage || ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$result = Ndizi_Time_Service::log_time_manual(
			$user_id,
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'duration'    => $duration,
				'billable'    => $billable,
				'start_time'  => $start_time,
				'end_time'    => $end_time,
			)
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 400 );
		}

		$new_entry = Ndizi_DB::get_time_entry( $result );
		if ( ! $new_entry ) {
			return new WP_REST_Response( array( 'message' => __( 'Failed to retrieve newly created entry.', 'ndizi-project-management' ) ), 500 );
		}

		$project                 = get_post( $new_entry->project_id );
		$new_entry->project_name = $project ? $project->post_title : '';
		if ( $new_entry->task_id ) {
			$task                 = get_post( $new_entry->task_id );
			$new_entry->task_name = $task ? $task->post_title : '';
		} else {
			$new_entry->task_name = '';
		}
		$user                 = get_userdata( $new_entry->user_id );
		$new_entry->user_name = $user ? $user->display_name : '';

		return new WP_REST_Response( $new_entry, 201 );
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

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot update time entry. The existing time entry is in a locked period.', 'ndizi-project-management' ) ), 400 );
		}

		if ( $request->has_param( 'start_time' ) ) {
			$new_start = $request->get_param( 'start_time' );
			if ( Ndizi_DB::is_date_locked( $new_start ) ) {
				return new WP_REST_Response( array( 'error' => __( 'Cannot update time entry. The new start date is locked.', 'ndizi-project-management' ) ), 400 );
			}
		}

		// Build data list from params.
		$params = array( 'project_id', 'task_id', 'description', 'start_time', 'end_time', 'duration', 'billable' );
		$data   = array();

		foreach ( $params as $param ) {
			if ( $request->has_param( $param ) ) {
				$data[ $param ] = $request->get_param( $param );
			}
		}

		// Approval is a manager-only action: only users who can manage all time
		// may set the approved flag, and `approved_by` is always recorded as the
		// acting manager (never trusted from client input) to prevent a team
		// member from self-approving their own entries.
		if ( $request->has_param( 'approved' ) && Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			$is_approved         = rest_sanitize_boolean( $request->get_param( 'approved' ) );
			$data['approved']    = $is_approved ? 1 : 0;
			$data['approved_by'] = $is_approved ? get_current_user_id() : 0;
		}

		$updated = Ndizi_DB::update_time_entry( $id, $data );

		if ( ! $updated ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to update time entry', 'ndizi-project-management' ) ), 500 );
		}

		$updated_entry = Ndizi_DB::get_time_entry( $id );
		if ( $updated_entry ) {
			$project                     = get_post( $updated_entry->project_id );
			$updated_entry->project_name = $project ? $project->post_title : '';
			if ( $updated_entry->task_id ) {
				$task                     = get_post( $updated_entry->task_id );
				$updated_entry->task_name = $task ? $task->post_title : '';
			} else {
				$updated_entry->task_name = '';
			}
			$user                     = get_userdata( $updated_entry->user_id );
			$updated_entry->user_name = $user ? $user->display_name : '';
		}

		return new WP_REST_Response( $updated_entry, 200 );
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

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot delete time entry. The time entry is in a locked period.', 'ndizi-project-management' ) ), 400 );
		}

		$deleted = Ndizi_DB::delete_time_entry( $id );

		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to delete time entry', 'ndizi-project-management' ) ), 500 );
		}

		return new WP_REST_Response( array( 'id' => intval( $id ) ), 200 );
	}

	/**
	 * Permission check: pay invoice
	 *
	 * @param WP_REST_Request $request REST Request.
	 * @return bool
	 */
	public static function check_invoice_pay_permission( $request ) {
		$invoice_id = $request->get_param( 'id' );
		if ( ! $invoice_id || 'ndizi_invoice' !== get_post_type( $invoice_id ) ) {
			return false;
		}

		if ( current_user_can( 'ndizi_view_reports' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$token = $request->get_param( 'token' );
		if ( $token && class_exists( 'Ndizi_Portal' ) ) {
			$token_client_id = Ndizi_Portal::get_client_id_by_token( $token );
			if ( $token_client_id ) {
				$project_id = get_post_meta( $invoice_id, '_ndizi_project_id', true );
				$client_id  = $project_id ? get_post_meta( $project_id, '_ndizi_client_id', true ) : 0;
				if ( $client_id && (int) $client_id === (int) $token_client_id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Callback to generate a Stripe Checkout session
	 *
	 * @param WP_REST_Request $request REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_stripe_checkout_session( $request ) {
		$invoice_id = $request->get_param( 'id' );
		$token      = $request->get_param( 'token' );

		$stripe_secret = Ndizi_Project_Management::get_secret( 'ndizi_stripe_secret_key' );
		if ( ! $stripe_secret ) {
			return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured on this site.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		$amount     = (float) get_post_meta( $invoice_id, '_ndizi_invoice_amount', true );
		$project_id = get_post_meta( $invoice_id, '_ndizi_project_id', true );

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invoice has no valid amount to charge.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}
		$project_title = get_the_title( $project_id );

		// Store the long-lived portal token in a short-lived transient so Stripe URLs
		// only carry a one-time reference key, not the credential itself.
		$payment_ref = wp_generate_uuid4();
		set_transient( 'ndizi_stripe_ref_' . $payment_ref, $token, HOUR_IN_SECONDS );

		$success_url = add_query_arg(
			array(
				'ndizi_payment'     => 'success',
				'ndizi_payment_ref' => $payment_ref,
			),
			get_permalink( $project_id )
		);
		$cancel_url  = add_query_arg(
			array(
				'ndizi_payment'     => 'cancel',
				'ndizi_payment_ref' => $payment_ref,
			),
			get_permalink( $project_id )
		);

		$line_items = array(
			array(
				'price_data' => array(
					'currency'     => 'usd',
					'product_data' => array(
						/* translators: 1: invoice ID, 2: project title */
						'name'        => sprintf( __( 'Invoice #%1$d - Project: %2$s', 'ndizi-project-management' ), $invoice_id, $project_title ),
						'description' => __( 'Project Management and Time Tracking Services', 'ndizi-project-management' ),
					),
					'unit_amount'  => round( $amount * 100 ),
				),
				'quantity'   => 1,
			),
		);

		$body = array(
			'payment_method_types' => array( 'card' ),
			'line_items'           => $line_items,
			'mode'                 => 'payment',
			'success_url'          => $success_url,
			'cancel_url'           => $cancel_url,
			'client_reference_id'  => $invoice_id,
			'metadata'             => array(
				'invoice_id' => $invoice_id,
			),
		);

		$stripe_payload = http_build_query( $body );

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $stripe_secret,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $stripe_payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'stripe_api_error', $response->get_error_message(), array( 'status' => 500 ) );
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $response_body ) ) {
			return new WP_Error( 'stripe_api_error', __( 'Unexpected response from Stripe.', 'ndizi-project-management' ), array( 'status' => 502 ) );
		}

		if ( isset( $response_body['error'] ) ) {
			return new WP_Error( 'stripe_api_error', $response_body['error']['message'], array( 'status' => 400 ) );
		}

		if ( empty( $response_body['url'] ) ) {
			return new WP_Error( 'stripe_api_error', __( 'No checkout URL returned by Stripe.', 'ndizi-project-management' ), array( 'status' => 502 ) );
		}

		return new WP_REST_Response( array( 'url' => $response_body['url'] ), 200 );
	}

	/**
	 * Callback to handle Stripe webhooks
	 *
	 * @param WP_REST_Request $request REST Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_stripe_webhook( $request ) {
		$stripe_webhook_secret = Ndizi_Project_Management::get_secret( 'ndizi_stripe_webhook_secret' );
		$sig_header            = $request->get_header( 'stripe-signature' );
		$payload               = $request->get_body();

		if ( ! $sig_header || ! $stripe_webhook_secret ) {
			return new WP_Error( 'bad_request', __( 'Missing signature or webhook secret.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		// Parse the Stripe-Signature header manually to handle multiple v1= values.
		$sig_parts = array();
		foreach ( explode( ',', $sig_header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 === count( $kv ) ) {
				$key = trim( $kv[0] );
				if ( 't' === $key ) {
					$sig_parts['t'] = trim( $kv[1] );
				} elseif ( 'v1' === $key ) {
					$sig_parts['v1'][] = trim( $kv[1] );
				}
			}
		}

		if ( empty( $sig_parts['t'] ) || empty( $sig_parts['v1'] ) ) {
			return new WP_Error( 'invalid_signature', __( 'Invalid signature header format.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		$timestamp      = $sig_parts['t'];
		$signed_payload = $timestamp . '.' . $payload;
		$calculated_sig = hash_hmac( 'sha256', $signed_payload, $stripe_webhook_secret );

		$sig_valid = false;
		foreach ( $sig_parts['v1'] as $expected_sig ) {
			if ( hash_equals( $calculated_sig, $expected_sig ) ) {
				$sig_valid = true;
				break;
			}
		}

		if ( ! $sig_valid ) {
			return new WP_Error( 'signature_mismatch', __( 'Webhook signature mismatch.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return new WP_Error( 'timestamp_expired', __( 'Webhook timestamp expired.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		$event = json_decode( $payload, true );
		if ( ! $event ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON payload.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		if ( isset( $event['type'] ) && 'checkout.session.completed' === $event['type'] ) {
			$session    = $event['data']['object'] ?? array();
			$invoice_id = isset( $session['client_reference_id'] ) ? intval( $session['client_reference_id'] ) : 0;

			// Async payment methods (e.g. bank transfers) complete the session before funds
			// actually settle, so payment_status may still be 'unpaid' here. Only act when
			// payment is confirmed captured.
			if ( ( $session['payment_status'] ?? '' ) !== 'paid' ) {
				return new WP_REST_Response( array( 'received' => true ), 200 );
			}

			if ( $invoice_id && 'ndizi_invoice' === get_post_type( $invoice_id ) ) {
				// Idempotency: skip if already marked paid so Stripe retries don't
				// fire ndizi_invoice_paid a second time (duplicate emails/webhooks).
				if ( 'paid' === get_post_meta( $invoice_id, '_ndizi_invoice_status', true ) ) {
					return new WP_REST_Response( array( 'received' => true ), 200 );
				}

				update_post_meta( $invoice_id, '_ndizi_invoice_status', 'paid' );
				do_action( 'ndizi_invoice_paid', $invoice_id );
			}
		}

		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	/**
	 * Output public token-secured iCal feed
	 *
	 * @param WP_REST_Request $request REST Request.
	 */
	public static function get_calendar_ical( $request ) {
		$token = $request->get_param( 'token' );
		if ( empty( $token ) ) {
			status_header( 403 );
			echo 'Forbidden: Missing Token';
			exit;
		}

		$is_admin_feed = false;
		$client_id     = 0;

		$global_token = get_option( 'ndizi_calendar_feed_token', '' );
		if ( $global_token && hash_equals( $global_token, $token ) ) {
			$is_admin_feed = true;
		} elseif ( class_exists( 'Ndizi_Portal' ) ) {
			// Check if token matches a client.
			$found = Ndizi_Portal::get_client_id_by_token( $token );
			if ( $found ) {
				$client_id = $found;
			}
		}

		if ( ! $is_admin_feed && ! $client_id ) {
			status_header( 403 );
			echo 'Forbidden: Invalid Token';
			exit;
		}

		// Query projects.
		$project_args = array(
			'post_type'      => 'ndizi_project',
			'posts_per_page' => -1,
		);
		if ( ! $is_admin_feed ) {
			$project_args['meta_query'] = array(
				array(
					'key'   => '_ndizi_client_id',
					'value' => $client_id,
				),
			);
		}
		$projects = get_posts( $project_args );

		// Query tasks.
		$task_args = array(
			'post_type'      => 'ndizi_task',
			'posts_per_page' => -1,
		);
		if ( ! $is_admin_feed ) {
			if ( empty( $projects ) ) {
				// No projects, so no tasks.
				$tasks = array();
			} else {
				$project_ids             = wp_list_pluck( $projects, 'ID' );
				$task_args['meta_query'] = array(
					array(
						'key'     => '_ndizi_project_id',
						'value'   => $project_ids,
						'compare' => 'IN',
					),
				);
				$tasks                   = get_posts( $task_args );
			}
		} else {
			$tasks = get_posts( $task_args );
		}

		// Helper to escape iCal values.
		$esc_ical = function ( $str ) {
			$str = str_replace( '\\', '\\\\', $str );
			$str = str_replace( ',', '\\,', $str );
			$str = str_replace( ';', '\\;', $str );
			$str = str_replace( "\n", '\\n', $str );
			$str = str_replace( "\r", '', $str );
			return $str;
		};

		// Output iCal headers.
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ndizi-calendar.ics"' );

		echo "BEGIN:VCALENDAR\r\n";
		echo "VERSION:2.0\r\n";
		echo "PRODID:-//Ndizi Project Management//NONSGML v1.0//EN\r\n";
		echo "CALSCALE:GREGORIAN\r\n";
		echo "METHOD:PUBLISH\r\n";

		// Add project end dates as events.
		foreach ( $projects as $project ) {
			$end_date = get_post_meta( $project->ID, '_ndizi_project_end_date', true );
			if ( ! $end_date ) {
				continue;
			}
			$ical_date = gmdate( 'Ymd', strtotime( $end_date ) );
			$dtstamp   = gmdate( 'Ymd\THis\Z', get_post_modified_time( 'U', true, $project ) );
			/* translators: %s: project title */
			$title = sprintf( __( 'Project End: %s', 'ndizi-project-management' ), $project->post_title );
			$desc  = $project->post_content;

			echo "BEGIN:VEVENT\r\n";
			echo 'UID:ndizi-project-' . intval( $project->ID ) . "\r\n";
			echo 'DTSTAMP:' . esc_html( $dtstamp ) . "\r\n";
			echo 'DTSTART;VALUE=DATE:' . esc_html( $ical_date ) . "\r\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'SUMMARY:' . $esc_ical( $title ) . "\r\n";
			if ( ! empty( $desc ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo 'DESCRIPTION:' . $esc_ical( $desc ) . "\r\n";
			}
			echo "END:VEVENT\r\n";
		}

		// Add task due dates as events.
		foreach ( $tasks as $task ) {
			$due_date = get_post_meta( $task->ID, '_ndizi_task_due_date', true );
			if ( ! $due_date ) {
				continue;
			}
			$ical_date = gmdate( 'Ymd', strtotime( $due_date ) );
			$dtstamp   = gmdate( 'Ymd\THis\Z', get_post_modified_time( 'U', true, $task ) );
			$status    = get_post_meta( $task->ID, '_ndizi_task_status', true );
			/* translators: 1: task title, 2: task status */
			$title = sprintf( __( 'Task Due: %1$s (%2$s)', 'ndizi-project-management' ), $task->post_title, $status );
			$desc  = $task->post_content;

			echo "BEGIN:VEVENT\r\n";
			echo 'UID:ndizi-task-' . intval( $task->ID ) . "\r\n";
			echo 'DTSTAMP:' . esc_html( $dtstamp ) . "\r\n";
			echo 'DTSTART;VALUE=DATE:' . esc_html( $ical_date ) . "\r\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'SUMMARY:' . $esc_ical( $title ) . "\r\n";
			if ( ! empty( $desc ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo 'DESCRIPTION:' . $esc_ical( $desc ) . "\r\n";
			}
			echo "END:VEVENT\r\n";
		}

		echo "END:VCALENDAR\r\n";
		exit;
	}
}
