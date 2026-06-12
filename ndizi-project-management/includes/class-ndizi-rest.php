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

		if ( Ndizi_DB::is_date_locked( current_time( 'mysql' ) ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot start timer. The current date is locked.', 'ndizi-project-management' ) ), 400 );
		}

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

		$active = Ndizi_DB::get_active_timer( $user_id );
		if ( $active && Ndizi_DB::is_date_locked( $active->start_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot stop timer. The timer start time falls in a locked period.', 'ndizi-project-management' ) ), 400 );
		}

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

		$check_time = empty( $start_time ) ? current_time( 'mysql' ) : $start_time;
		if ( Ndizi_DB::is_date_locked( $check_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot log time. The target start date is locked.', 'ndizi-project-management' ) ), 400 );
		}

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

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot update time entry. The existing time entry is in a locked period.', 'ndizi-project-management' ) ), 400 );
		}

		if ( $request->has_param( 'start_time' ) ) {
			$new_start = $request->get_param( 'start_time' );
			if ( Ndizi_DB::is_date_locked( $new_start ) ) {
				return new WP_REST_Response( array( 'error' => __( 'Cannot update time entry. The new start date is locked.', 'ndizi-project-management' ) ), 400 );
			}
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

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			return new WP_REST_Response( array( 'error' => __( 'Cannot delete time entry. The time entry is in a locked period.', 'ndizi-project-management' ) ), 400 );
		}

		$deleted = Ndizi_DB::delete_time_entry( $id );

		if ( ! $deleted ) {
			return new WP_REST_Response( array( 'error' => __( 'Failed to delete time entry', 'ndizi-project-management' ) ), 500 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
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
		if ( $token ) {
			$project_id = get_post_meta( $invoice_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				$client_id = get_post_meta( $project_id, '_ndizi_client_id', true );
				if ( $client_id ) {
					$expected_token = get_post_meta( $client_id, '_ndizi_client_auth_key', true );
					if ( $expected_token && hash_equals( $expected_token, $token ) ) {
						return true;
					}
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

		$stripe_secret = get_option( 'ndizi_stripe_secret_key', '' );
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
		$stripe_webhook_secret = get_option( 'ndizi_stripe_webhook_secret', '' );
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

			if ( $invoice_id && 'ndizi_invoice' === get_post_type( $invoice_id ) ) {
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
		} else {
			// Check if token matches a client.
			$clients = get_posts(
				array(
					'post_type'      => 'ndizi_client',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_client_auth_key',
							'value' => $token,
						),
					),
				)
			);
			if ( ! empty( $clients ) ) {
				$client_id = $clients[0]->ID;
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
