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
					'description' => __( 'Abilities for managing clients, projects, tasks, invoices, contacts, time off, and time tracking.', 'ndizi-project-management' ),
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
				'description'         => __( 'Retrieves projects and their metadata, optionally filtered by ID or status.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => __( 'Optional project ID to fetch a single project.', 'ndizi-project-management' ),
						),
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'archived' ),
							'description' => __( 'Project status to filter by. Defaults to active.', 'ndizi-project-management' ),
						),
					),
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
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Optional task ID to fetch a single task.', 'ndizi-project-management' ),
						),
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
							'id'               => array( 'type' => 'integer' ),
							'title'            => array( 'type' => 'string' ),
							'description'      => array( 'type' => 'string' ),
							'project_id'       => array( 'type' => 'integer' ),
							'project_name'     => array( 'type' => 'string' ),
							'assigned_user_id' => array( 'type' => 'integer' ),
							'status'           => array( 'type' => 'string' ),
							'priority'         => array( 'type' => 'string' ),
							'due_date'         => array( 'type' => 'string' ),
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
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Optional invoice ID to fetch a single invoice.', 'ndizi-project-management' ),
						),
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
							'enum'        => array( 'draft', 'sent', 'partial', 'paid', 'void' ),
							'description' => __( 'Optional invoice status to filter by.', 'ndizi-project-management' ),
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
							'line_items'   => array( 'type' => 'array' ),
							'payments'     => array( 'type' => 'array' ),
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

		self::register_client_abilities();
		self::register_project_write_abilities();
		self::register_task_write_abilities();
		self::register_invoice_write_abilities();
		self::register_contact_abilities();
		self::register_time_off_abilities();
		self::register_time_entry_abilities();
	}

	/**
	 * Whether at least one post of $post_type has $meta_key matching $value.
	 *
	 * @param string $post_type Post type to search.
	 * @param string $meta_key  Meta key to match.
	 * @param mixed  $value     Value to match (LIKE-compared for serialized array meta).
	 * @param string $compare   Meta compare operator.
	 * @return bool
	 */
	private static function post_meta_reference_exists( $post_type, $meta_key, $value, $compare = '=' ) {
		$ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => $meta_key,
						'value'   => $value,
						'compare' => $compare,
					),
				),
			)
		);
		return ! empty( $ids );
	}

	/**
	 * Whether any post of $post_type has $meta_key (an array meta value) containing $value.
	 *
	 * Uses a LIKE query to narrow candidates, then confirms exact membership in PHP so
	 * e.g. client ID 1 doesn't false-positive match a stored ID of 21.
	 *
	 * @param string $post_type Post type to search.
	 * @param string $meta_key  Meta key holding a serialized array of IDs.
	 * @param int    $value     ID to look for.
	 * @return bool
	 */
	private static function post_meta_array_contains( $post_type, $meta_key, $value ) {
		$candidates = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => $meta_key,
						'value'   => $value,
						'compare' => 'LIKE',
					),
				),
			)
		);

		foreach ( $candidates as $post_id ) {
			$stored = get_post_meta( $post_id, $meta_key, true );
			if ( is_array( $stored ) && in_array( intval( $value ), array_map( 'intval', $stored ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks a list of dependent-record rules and returns the labels of any that block deletion.
	 *
	 * Each rule is one of:
	 *  - array( 'source' => 'time_entries', 'column' => 'client_id'|'project_id'|'task_id'|'invoice_id', 'value' => int, 'label' => string )
	 *  - array( 'source' => <post_type>, 'meta_key' => string, 'value' => mixed, 'label' => string, 'array' => true ) — array-meta membership check
	 *  - array( 'source' => <post_type>, 'meta_key' => string, 'value' => mixed, 'label' => string ) — exact scalar meta match
	 *
	 * @param array $checks Dependency rules to evaluate.
	 * @return string[] Labels of dependent record types found (empty if none).
	 */
	private static function find_blocking_dependents( array $checks ) {
		$blockers = array();
		foreach ( $checks as $check ) {
			if ( 'time_entries' === $check['source'] ) {
				if ( Ndizi_DB::get_time_entries_count( array( $check['column'] => $check['value'] ) ) > 0 ) {
					$blockers[] = $check['label'];
				}
			} elseif ( ! empty( $check['array'] ) ) {
				if ( self::post_meta_array_contains( $check['source'], $check['meta_key'], $check['value'] ) ) {
					$blockers[] = $check['label'];
				}
			} elseif ( self::post_meta_reference_exists( $check['source'], $check['meta_key'], $check['value'] ) ) {
				$blockers[] = $check['label'];
			}
		}
		return $blockers;
	}

	/**
	 * Builds the WP_Error returned when a delete is blocked by dependent records.
	 *
	 * @param string[] $blockers Labels of dependent record types found.
	 * @return WP_Error
	 */
	private static function dependents_error( array $blockers ) {
		return new WP_Error(
			'has_dependents',
			sprintf(
				/* translators: %s: comma-separated list of dependent record types, e.g. "projects, invoices" */
				__( 'Cannot delete: it still has related %s. Remove or reassign those first.', 'ndizi-project-management' ),
				implode( ', ', $blockers )
			),
			array( 'status' => 409 )
		);
	}

	/**
	 * Fetches a post and confirms it matches the expected post type.
	 *
	 * @param int    $id        Post ID.
	 * @param string $post_type Expected post type.
	 * @param string $label     Human-readable field label for the error message.
	 * @return WP_Post|WP_Error
	 */
	private static function get_post_of_type( $id, $post_type, $label ) {
		$id   = intval( $id );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || $post_type !== $post->post_type ) {
			return new WP_Error(
				'invalid_reference',
				/* translators: %s: field label, e.g. "client_id" */
				sprintf( __( 'Invalid %s: no matching record found.', 'ndizi-project-management' ), $label ),
				array( 'status' => 400 )
			);
		}
		return $post;
	}

	/**
	 * Sanitizes a string against an allowlist, falling back to a default.
	 *
	 * @param mixed  $value   Raw input value.
	 * @param array  $allowed Allowed values.
	 * @param string $default Fallback value.
	 * @return string
	 */
	private static function sanitize_enum( $value, array $allowed, $default_value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		return in_array( $value, $allowed, true ) ? $value : $default_value;
	}

	/**
	 * Normalizes a meta value that should be a list (array) for JSON output.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array
	 */
	private static function normalize_list( $value ) {
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * Registers client CRUD abilities.
	 */
	private static function register_client_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return current_user_can( 'ndizi_manage_clients' );
		};

		$output_properties = array(
			'id'      => array( 'type' => 'integer' ),
			'name'    => array( 'type' => 'string' ),
			'website' => array( 'type' => 'string' ),
			'address' => array( 'type' => 'string' ),
			'status'  => array( 'type' => 'string' ),
		);

		wp_register_ability(
			'ndizi/get-clients',
			array(
				'label'               => __( 'Get Clients', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves clients, optionally filtered by ID or status.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => __( 'Optional client ID to fetch a single client.', 'ndizi-project-management' ),
						),
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'archived' ),
							'description' => __( 'Optional client status to filter by.', 'ndizi-project-management' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => $output_properties,
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_clients' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/create-client',
			array(
				'label'               => __( 'Create Client', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new client record.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'    => array(
							'type'        => 'string',
							'description' => __( 'Client name.', 'ndizi-project-management' ),
						),
						'website' => array(
							'type'        => 'string',
							'description' => __( 'Client website URL.', 'ndizi-project-management' ),
						),
						'address' => array(
							'type'        => 'string',
							'description' => __( 'Client address.', 'ndizi-project-management' ),
						),
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'archived' ),
							'description' => __( 'Client status. Defaults to active.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'create_client' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-client',
			array(
				'label'               => __( 'Update Client', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing client record.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => __( 'Client ID to update.', 'ndizi-project-management' ),
						),
						'name'    => array(
							'type'        => 'string',
							'description' => __( 'Client name.', 'ndizi-project-management' ),
						),
						'website' => array(
							'type'        => 'string',
							'description' => __( 'Client website URL.', 'ndizi-project-management' ),
						),
						'address' => array(
							'type'        => 'string',
							'description' => __( 'Client address.', 'ndizi-project-management' ),
						),
						'status'  => array(
							'type' => 'string',
							'enum' => array( 'active', 'archived' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'update_client' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-client',
			array(
				'label'               => __( 'Delete Client', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a client, if it has no dependent projects, invoices, contacts, or time entries.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Client ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_client' ),
				'permission_callback' => $permission_callback,
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
	 * Registers project write (create/update/delete) abilities. Reads are covered by ndizi/get-projects.
	 */
	private static function register_project_write_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return current_user_can( 'ndizi_manage_projects' );
		};

		$project_properties = array(
			'title'       => array(
				'type'        => 'string',
				'description' => __( 'Project title.', 'ndizi-project-management' ),
			),
			'client_id'   => array(
				'type'        => 'integer',
				'description' => __( 'Client this project belongs to.', 'ndizi-project-management' ),
			),
			'description' => array(
				'type'        => 'string',
				'description' => __( 'Project description.', 'ndizi-project-management' ),
			),
			'start_date'  => array(
				'type'        => 'string',
				'description' => __( 'Project start date (Y-m-d).', 'ndizi-project-management' ),
			),
			'end_date'    => array(
				'type'        => 'string',
				'description' => __( 'Project end date (Y-m-d).', 'ndizi-project-management' ),
			),
			'budget'      => array(
				'type'        => 'number',
				'description' => __( 'Project budget.', 'ndizi-project-management' ),
			),
			'status'      => array(
				'type'        => 'string',
				'enum'        => array( 'active', 'archived' ),
				'description' => __( 'Project status. Defaults to active.', 'ndizi-project-management' ),
			),
			'hourly_rate' => array(
				'type'        => 'number',
				'description' => __( 'Default hourly rate for this project.', 'ndizi-project-management' ),
			),
		);

		$output_properties = array(
			'id'          => array( 'type' => 'integer' ),
			'title'       => array( 'type' => 'string' ),
			'description' => array( 'type' => 'string' ),
			'client_id'   => array( 'type' => 'integer' ),
			'start_date'  => array( 'type' => 'string' ),
			'end_date'    => array( 'type' => 'string' ),
			'budget'      => array( 'type' => 'number' ),
			'status'      => array( 'type' => 'string' ),
			'hourly_rate' => array( 'type' => 'number' ),
		);

		wp_register_ability(
			'ndizi/create-project',
			array(
				'label'               => __( 'Create Project', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new project for a client.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $project_properties,
					'required'   => array( 'title', 'client_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'create_project' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-project',
			array(
				'label'               => __( 'Update Project', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing project.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge(
						array(
							'id' => array(
								'type'        => 'integer',
								'description' => __( 'Project ID to update.', 'ndizi-project-management' ),
							),
						),
						$project_properties
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'update_project' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-project',
			array(
				'label'               => __( 'Delete Project', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a project, if it has no dependent tasks, invoices, or time entries.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Project ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_project' ),
				'permission_callback' => $permission_callback,
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
	 * Registers task write (create/update/delete) abilities. Reads are covered by ndizi/get-tasks.
	 */
	private static function register_task_write_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return current_user_can( 'ndizi_manage_tasks' );
		};

		$task_properties = array(
			'title'            => array(
				'type'        => 'string',
				'description' => __( 'Task title.', 'ndizi-project-management' ),
			),
			'project_id'       => array(
				'type'        => 'integer',
				'description' => __( 'Project this task belongs to.', 'ndizi-project-management' ),
			),
			'description'      => array(
				'type'        => 'string',
				'description' => __( 'Task description.', 'ndizi-project-management' ),
			),
			'assigned_user_id' => array(
				'type'        => 'integer',
				'description' => __( 'User ID this task is assigned to.', 'ndizi-project-management' ),
			),
			'status'           => array(
				'type'        => 'string',
				'enum'        => array( 'open', 'in_progress', 'completed', 'cancelled' ),
				'description' => __( 'Task status. Defaults to open.', 'ndizi-project-management' ),
			),
			'priority'         => array(
				'type'        => 'string',
				'enum'        => array( 'low', 'medium', 'high' ),
				'description' => __( 'Task priority. Defaults to medium.', 'ndizi-project-management' ),
			),
			'due_date'         => array(
				'type'        => 'string',
				'description' => __( 'Task due date (Y-m-d).', 'ndizi-project-management' ),
			),
			'hourly_rate'      => array(
				'type'        => 'number',
				'description' => __( 'Hourly rate for this task, overriding the project rate.', 'ndizi-project-management' ),
			),
		);

		$output_properties = array(
			'id'               => array( 'type' => 'integer' ),
			'title'            => array( 'type' => 'string' ),
			'description'      => array( 'type' => 'string' ),
			'project_id'       => array( 'type' => 'integer' ),
			'assigned_user_id' => array( 'type' => 'integer' ),
			'status'           => array( 'type' => 'string' ),
			'priority'         => array( 'type' => 'string' ),
			'due_date'         => array( 'type' => 'string' ),
			'hourly_rate'      => array( 'type' => 'number' ),
		);

		wp_register_ability(
			'ndizi/create-task',
			array(
				'label'               => __( 'Create Task', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new task on a project.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $task_properties,
					'required'   => array( 'title', 'project_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'create_task' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-task',
			array(
				'label'               => __( 'Update Task', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing task.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge(
						array(
							'id' => array(
								'type'        => 'integer',
								'description' => __( 'Task ID to update.', 'ndizi-project-management' ),
							),
						),
						$task_properties
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'update_task' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-task',
			array(
				'label'               => __( 'Delete Task', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a task, if it has no dependent time entries.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Task ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_task' ),
				'permission_callback' => $permission_callback,
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
	 * Registers invoice write (create/update/delete) abilities. Reads are covered by ndizi/get-invoices.
	 */
	private static function register_invoice_write_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return Ndizi_REST::check_view_invoices_permission();
		};

		$invoice_properties = array(
			'number'       => array(
				'type'        => 'string',
				'description' => __( 'Invoice number.', 'ndizi-project-management' ),
			),
			'client_id'    => array(
				'type'        => 'integer',
				'description' => __( 'Client this invoice is billed to.', 'ndizi-project-management' ),
			),
			'project_id'   => array(
				'type'        => 'integer',
				'description' => __( 'Project this invoice is for.', 'ndizi-project-management' ),
			),
			'currency'     => array(
				'type'        => 'string',
				'description' => __( 'Currency code. Defaults to the site default currency.', 'ndizi-project-management' ),
			),
			'invoice_date' => array(
				'type'        => 'string',
				'description' => __( 'Invoice date (Y-m-d).', 'ndizi-project-management' ),
			),
			'due_date'     => array(
				'type'        => 'string',
				'description' => __( 'Invoice due date (Y-m-d).', 'ndizi-project-management' ),
			),
			'amount'       => array(
				'type'        => 'number',
				'description' => __( 'Total invoice amount.', 'ndizi-project-management' ),
			),
			'status'       => array(
				'type'        => 'string',
				'enum'        => array( 'draft', 'sent', 'partial', 'paid', 'void' ),
				'description' => __( 'Invoice status. Defaults to draft; otherwise auto-derived from payments unless set explicitly.', 'ndizi-project-management' ),
			),
			'line_items'   => array(
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'description' => array( 'type' => 'string' ),
						'quantity'    => array( 'type' => 'number' ),
						'unit_price'  => array( 'type' => 'number' ),
						'amount'      => array( 'type' => 'number' ),
					),
				),
				'description' => __( 'Line items for this invoice.', 'ndizi-project-management' ),
			),
			'payments'     => array(
				'type'        => 'array',
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'date'   => array( 'type' => 'string' ),
						'amount' => array( 'type' => 'number' ),
						'method' => array( 'type' => 'string' ),
						'note'   => array( 'type' => 'string' ),
					),
				),
				'description' => __( 'Recorded payments for this invoice.', 'ndizi-project-management' ),
			),
		);

		$output_properties = array(
			'id'           => array( 'type' => 'integer' ),
			'number'       => array( 'type' => 'string' ),
			'client_id'    => array( 'type' => 'integer' ),
			'project_id'   => array( 'type' => 'integer' ),
			'currency'     => array( 'type' => 'string' ),
			'invoice_date' => array( 'type' => 'string' ),
			'due_date'     => array( 'type' => 'string' ),
			'amount'       => array( 'type' => 'number' ),
			'balance'      => array( 'type' => 'number' ),
			'status'       => array( 'type' => 'string' ),
			'line_items'   => array( 'type' => 'array' ),
			'payments'     => array( 'type' => 'array' ),
		);

		wp_register_ability(
			'ndizi/create-invoice',
			array(
				'label'               => __( 'Create Invoice', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new invoice for a client and/or project.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => $invoice_properties,
					'required'   => array( 'amount' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'create_invoice' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-invoice',
			array(
				'label'               => __( 'Update Invoice', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing invoice.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge(
						array(
							'id' => array(
								'type'        => 'integer',
								'description' => __( 'Invoice ID to update.', 'ndizi-project-management' ),
							),
						),
						$invoice_properties
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $output_properties,
				),
				'execute_callback'    => array( __CLASS__, 'update_invoice' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-invoice',
			array(
				'label'               => __( 'Delete Invoice', 'ndizi-project-management' ),
				'description'         => __( 'Deletes an invoice, if it has no dependent time entries.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Invoice ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_invoice' ),
				'permission_callback' => $permission_callback,
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
	 * Registers contact CRUD abilities.
	 */
	private static function register_contact_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return current_user_can( 'ndizi_manage_contacts' );
		};

		$contact_output = array(
			'id'                 => array( 'type' => 'integer' ),
			'name'               => array( 'type' => 'string' ),
			'email'              => array( 'type' => 'string' ),
			'phone'              => array( 'type' => 'string' ),
			'role'               => array( 'type' => 'string' ),
			'associated_clients' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		);

		wp_register_ability(
			'ndizi/get-contacts',
			array(
				'label'               => __( 'Get Contacts', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves contacts, optionally filtered by ID or associated client.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'        => array(
							'type'        => 'integer',
							'description' => __( 'Optional contact ID to fetch a single contact.', 'ndizi-project-management' ),
						),
						'client_id' => array(
							'type'        => 'integer',
							'description' => __( 'Optional client ID to filter contacts associated with that client.', 'ndizi-project-management' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => $contact_output,
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_contacts' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/create-contact',
			array(
				'label'               => __( 'Create Contact', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new contact.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'               => array(
							'type'        => 'string',
							'description' => __( 'Contact name.', 'ndizi-project-management' ),
						),
						'email'              => array(
							'type'        => 'string',
							'description' => __( 'Contact email address.', 'ndizi-project-management' ),
						),
						'phone'              => array(
							'type'        => 'string',
							'description' => __( 'Contact phone number.', 'ndizi-project-management' ),
						),
						'role'               => array(
							'type'        => 'string',
							'description' => __( 'Contact role/title.', 'ndizi-project-management' ),
						),
						'associated_clients' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'Client IDs this contact is associated with.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $contact_output,
				),
				'execute_callback'    => array( __CLASS__, 'create_contact' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-contact',
			array(
				'label'               => __( 'Update Contact', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing contact.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'                 => array(
							'type'        => 'integer',
							'description' => __( 'Contact ID to update.', 'ndizi-project-management' ),
						),
						'name'               => array( 'type' => 'string' ),
						'email'              => array( 'type' => 'string' ),
						'phone'              => array( 'type' => 'string' ),
						'role'               => array( 'type' => 'string' ),
						'associated_clients' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $contact_output,
				),
				'execute_callback'    => array( __CLASS__, 'update_contact' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-contact',
			array(
				'label'               => __( 'Delete Contact', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a contact.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Contact ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_contact' ),
				'permission_callback' => $permission_callback,
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
	 * Registers time-off CRUD abilities. Per user decision, all are gated uniformly on
	 * ndizi_manage_time (the ndizi_team_member role has neither this cap nor
	 * ndizi_view_reports, so there is no self-service path to normalize against).
	 */
	private static function register_time_off_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return current_user_can( 'ndizi_manage_time' );
		};

		$time_off_output = array(
			'id'         => array( 'type' => 'integer' ),
			'user_id'    => array( 'type' => 'integer' ),
			'client_id'  => array( 'type' => 'integer' ),
			'start_date' => array( 'type' => 'string' ),
			'end_date'   => array( 'type' => 'string' ),
			'type'       => array( 'type' => 'string' ),
			'status'     => array( 'type' => 'string' ),
		);

		wp_register_ability(
			'ndizi/get-time-off',
			array(
				'label'               => __( 'Get Time Off', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves time-off requests, optionally filtered by ID, user, or status.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => __( 'Optional time-off ID to fetch a single request.', 'ndizi-project-management' ),
						),
						'user_id' => array(
							'type'        => 'integer',
							'description' => __( 'Optional user ID to filter requests.', 'ndizi-project-management' ),
						),
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'approved', 'rejected' ),
							'description' => __( 'Optional status to filter by.', 'ndizi-project-management' ),
						),
					),
				),
				'output_schema'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => $time_off_output,
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_time_off' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/create-time-off',
			array(
				'label'               => __( 'Create Time Off', 'ndizi-project-management' ),
				'description'         => __( 'Creates a new time-off request.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'user_id'    => array(
							'type'        => 'integer',
							'description' => __( 'User this request is for. Defaults to the current user.', 'ndizi-project-management' ),
						),
						'client_id'  => array(
							'type'        => 'integer',
							'description' => __( 'Optional related client.', 'ndizi-project-management' ),
						),
						'start_date' => array(
							'type'        => 'string',
							'description' => __( 'Start date (Y-m-d).', 'ndizi-project-management' ),
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => __( 'End date (Y-m-d).', 'ndizi-project-management' ),
						),
						'type'       => array(
							'type'        => 'string',
							'enum'        => array( 'vacation', 'sick_leave', 'personal', 'other' ),
							'description' => __( 'Time-off type.', 'ndizi-project-management' ),
						),
						'status'     => array(
							'type'        => 'string',
							'enum'        => array( 'pending', 'approved', 'rejected' ),
							'description' => __( 'Request status. Defaults to pending.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'start_date' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $time_off_output,
				),
				'execute_callback'    => array( __CLASS__, 'create_time_off' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-time-off',
			array(
				'label'               => __( 'Update Time Off', 'ndizi-project-management' ),
				'description'         => __( 'Updates an existing time-off request.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => __( 'Time-off ID to update.', 'ndizi-project-management' ),
						),
						'user_id'    => array( 'type' => 'integer' ),
						'client_id'  => array( 'type' => 'integer' ),
						'start_date' => array( 'type' => 'string' ),
						'end_date'   => array( 'type' => 'string' ),
						'type'       => array(
							'type' => 'string',
							'enum' => array( 'vacation', 'sick_leave', 'personal', 'other' ),
						),
						'status'     => array(
							'type' => 'string',
							'enum' => array( 'pending', 'approved', 'rejected' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $time_off_output,
				),
				'execute_callback'    => array( __CLASS__, 'update_time_off' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-time-off',
			array(
				'label'               => __( 'Delete Time Off', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a time-off request.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Time-off ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_time_off' ),
				'permission_callback' => $permission_callback,
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
	 * Registers abilities for reading, updating, and deleting logged time entries.
	 * Creation is already covered by ndizi/log-time-manual. Mirrors the ownership,
	 * approval, and lock-date rules enforced by the equivalent Ndizi_REST endpoints.
	 */
	private static function register_time_entry_abilities() {
		$permission_callback = function ( $input = null ) {
			unset( $input );
			return Ndizi_REST::check_time_log_permission();
		};

		$entry_output = array(
			'id'           => array( 'type' => 'integer' ),
			'client_id'    => array( 'type' => 'integer' ),
			'client_name'  => array( 'type' => 'string' ),
			'project_id'   => array( 'type' => 'integer' ),
			'project_name' => array( 'type' => 'string' ),
			'task_id'      => array( 'type' => 'integer' ),
			'task_name'    => array( 'type' => 'string' ),
			'user_id'      => array( 'type' => 'integer' ),
			'user_name'    => array( 'type' => 'string' ),
			'description'  => array( 'type' => 'string' ),
			'start_time'   => array( 'type' => 'string' ),
			'end_time'     => array( 'type' => 'string' ),
			'duration'     => array( 'type' => 'integer' ),
			'billable'     => array( 'type' => 'boolean' ),
			'approved'     => array( 'type' => 'boolean' ),
			'invoice_id'   => array( 'type' => 'integer' ),
		);

		wp_register_ability(
			'ndizi/get-time-entries',
			array(
				'label'               => __( 'Get Time Entries', 'ndizi-project-management' ),
				'description'         => __( 'Retrieves logged time entries. Team members only see their own entries; managers can filter by any user.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page'       => array(
							'type'        => 'integer',
							'description' => __( 'Page number. Defaults to 1.', 'ndizi-project-management' ),
						),
						'per_page'   => array(
							'type'        => 'integer',
							'description' => __( 'Results per page (max 100). Defaults to 20.', 'ndizi-project-management' ),
						),
						'client_id'  => array( 'type' => 'integer' ),
						'project_id' => array( 'type' => 'integer' ),
						'user_id'    => array(
							'type'        => 'integer',
							'description' => __( 'Managers only; ignored for team members, who always see their own entries.', 'ndizi-project-management' ),
						),
						'billable'   => array( 'type' => 'boolean' ),
						'approved'   => array( 'type' => 'boolean' ),
						'invoiced'   => array(
							'type' => 'string',
							'enum' => array( 'yes', 'no' ),
						),
						'search'     => array( 'type' => 'string' ),
						'start_date' => array(
							'type'        => 'string',
							'description' => __( 'Y-m-d', 'ndizi-project-management' ),
						),
						'end_date'   => array(
							'type'        => 'string',
							'description' => __( 'Y-m-d', 'ndizi-project-management' ),
						),
						'orderby'    => array( 'type' => 'string' ),
						'order'      => array(
							'type' => 'string',
							'enum' => array( 'ASC', 'DESC' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
						'entries'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => $entry_output,
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'get_time_entries' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/update-time-entry',
			array(
				'label'               => __( 'Update Time Entry', 'ndizi-project-management' ),
				'description'         => __( 'Updates a logged time entry. Team members may only edit their own entries; approval is manager-only.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => __( 'Time entry ID to update.', 'ndizi-project-management' ),
						),
						'client_id'   => array( 'type' => 'integer' ),
						'project_id'  => array( 'type' => 'integer' ),
						'task_id'     => array( 'type' => 'integer' ),
						'description' => array( 'type' => 'string' ),
						'start_time'  => array( 'type' => 'string' ),
						'end_time'    => array( 'type' => 'string' ),
						'duration'    => array( 'type' => 'integer' ),
						'billable'    => array( 'type' => 'boolean' ),
						'approved'    => array(
							'type'        => 'boolean',
							'description' => __( 'Manager-only. Ignored for team members.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => $entry_output,
				),
				'execute_callback'    => array( __CLASS__, 'update_time_entry' ),
				'permission_callback' => $permission_callback,
				'meta'                => array(
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);

		wp_register_ability(
			'ndizi/delete-time-entry',
			array(
				'label'               => __( 'Delete Time Entry', 'ndizi-project-management' ),
				'description'         => __( 'Deletes a logged time entry. Team members may only delete their own entries.', 'ndizi-project-management' ),
				'category'            => 'ndizi-pm',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => __( 'Time entry ID to delete.', 'ndizi-project-management' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'delete_time_entry' ),
				'permission_callback' => $permission_callback,
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
		$status = self::sanitize_enum( isset( $input['status'] ) ? $input['status'] : '', array( 'active', 'archived' ), 'active' );

		$args = array(
			'post_type'      => 'ndizi_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_ndizi_project_status',
					'value'   => $status,
					'compare' => '=',
				),
			),
		);

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}

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

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}

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
				'id'               => $task->ID,
				'title'            => $task->post_title,
				'description'      => $task->post_content,
				'project_id'       => $p_id ? intval( $p_id ) : 0,
				'project_name'     => $project ? $project->post_title : '',
				'assigned_user_id' => intval( get_post_meta( $task->ID, '_ndizi_assigned_user_id', true ) ),
				'status'           => (string) get_post_meta( $task->ID, '_ndizi_task_status', true ),
				'priority'         => (string) get_post_meta( $task->ID, '_ndizi_task_priority', true ),
				'due_date'         => (string) get_post_meta( $task->ID, '_ndizi_task_due_date', true ),
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

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}

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
				'line_items'   => self::normalize_list( get_post_meta( $invoice->ID, '_ndizi_invoice_line_items', true ) ),
				'payments'     => self::normalize_list( get_post_meta( $invoice->ID, '_ndizi_invoice_payments', true ) ),
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

	/**
	 * Formats a client post for ability output.
	 *
	 * @param WP_Post $post Client post.
	 * @return array
	 */
	private static function format_client( $post ) {
		return array(
			'id'      => $post->ID,
			'name'    => $post->post_title,
			'website' => (string) get_post_meta( $post->ID, '_ndizi_client_website', true ),
			'address' => (string) get_post_meta( $post->ID, '_ndizi_client_address', true ),
			'status'  => (string) get_post_meta( $post->ID, '_ndizi_client_status', true ),
		);
	}

	/**
	 * Execute callback for get-clients ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_clients( $input = null ) {
		$args = array(
			'post_type'      => 'ndizi_client',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}

		if ( ! empty( $input['status'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ndizi_client_status',
					'value' => sanitize_key( $input['status'] ),
				),
			);
		}

		return array_map( array( __CLASS__, 'format_client' ), get_posts( $args ) );
	}

	/**
	 * Execute callback for create-client ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_client( $input ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ndizi_client',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $input['name'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $input['website'] ) ) {
			update_post_meta( $post_id, '_ndizi_client_website', esc_url_raw( $input['website'] ) );
		}
		if ( isset( $input['address'] ) ) {
			update_post_meta( $post_id, '_ndizi_client_address', sanitize_text_field( $input['address'] ) );
		}
		update_post_meta( $post_id, '_ndizi_client_status', self::sanitize_enum( isset( $input['status'] ) ? $input['status'] : '', array( 'active', 'archived' ), 'active' ) );

		return self::format_client( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-client ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_client( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_client', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( isset( $input['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => sanitize_text_field( $input['name'] ),
				)
			);
		}
		if ( isset( $input['website'] ) ) {
			update_post_meta( $post->ID, '_ndizi_client_website', esc_url_raw( $input['website'] ) );
		}
		if ( isset( $input['address'] ) ) {
			update_post_meta( $post->ID, '_ndizi_client_address', sanitize_text_field( $input['address'] ) );
		}
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post->ID, '_ndizi_client_status', self::sanitize_enum( $input['status'], array( 'active', 'archived' ), 'active' ) );
		}

		return self::format_client( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-client ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_client( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_client', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blockers = self::find_blocking_dependents(
			array(
				array(
					'source'   => 'ndizi_project',
					'meta_key' => '_ndizi_client_id',
					'value'    => $post->ID,
					'label'    => __( 'projects', 'ndizi-project-management' ),
				),
				array(
					'source'   => 'ndizi_invoice',
					'meta_key' => '_ndizi_client_id',
					'value'    => $post->ID,
					'label'    => __( 'invoices', 'ndizi-project-management' ),
				),
				array(
					'source'   => 'ndizi_contact',
					'meta_key' => '_ndizi_associated_clients',
					'value'    => $post->ID,
					'label'    => __( 'contacts', 'ndizi-project-management' ),
					'array'    => true,
				),
				array(
					'source' => 'time_entries',
					'column' => 'client_id',
					'value'  => $post->ID,
					'label'  => __( 'time entries', 'ndizi-project-management' ),
				),
			)
		);
		if ( ! empty( $blockers ) ) {
			return self::dependents_error( $blockers );
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete client.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats a project post for ability output.
	 *
	 * @param WP_Post $post Project post.
	 * @return array
	 */
	private static function format_project( $post ) {
		$client_id = get_post_meta( $post->ID, '_ndizi_client_id', true );

		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'client_id'   => $client_id ? intval( $client_id ) : 0,
			'start_date'  => (string) get_post_meta( $post->ID, '_ndizi_project_start_date', true ),
			'end_date'    => (string) get_post_meta( $post->ID, '_ndizi_project_end_date', true ),
			'budget'      => floatval( get_post_meta( $post->ID, '_ndizi_project_budget', true ) ),
			'status'      => (string) get_post_meta( $post->ID, '_ndizi_project_status', true ),
			'hourly_rate' => floatval( get_post_meta( $post->ID, '_ndizi_project_hourly_rate', true ) ),
		);
	}

	/**
	 * Execute callback for create-project ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_project( $input ) {
		$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'ndizi_project',
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_ndizi_client_id', $client->ID );
		if ( isset( $input['start_date'] ) ) {
			update_post_meta( $post_id, '_ndizi_project_start_date', sanitize_text_field( $input['start_date'] ) );
		}
		if ( isset( $input['end_date'] ) ) {
			update_post_meta( $post_id, '_ndizi_project_end_date', sanitize_text_field( $input['end_date'] ) );
		}
		if ( isset( $input['budget'] ) ) {
			update_post_meta( $post_id, '_ndizi_project_budget', floatval( $input['budget'] ) );
		}
		if ( isset( $input['hourly_rate'] ) ) {
			update_post_meta( $post_id, '_ndizi_project_hourly_rate', max( 0.0, floatval( $input['hourly_rate'] ) ) );
		}
		update_post_meta( $post_id, '_ndizi_project_status', self::sanitize_enum( isset( $input['status'] ) ? $input['status'] : '', array( 'active', 'archived' ), 'active' ) );

		return self::format_project( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-project ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_project( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_project', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_data = array( 'ID' => $post->ID );
		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['description'] );
		}
		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
		}

		if ( isset( $input['client_id'] ) ) {
			$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			update_post_meta( $post->ID, '_ndizi_client_id', $client->ID );
		}
		if ( isset( $input['start_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_project_start_date', sanitize_text_field( $input['start_date'] ) );
		}
		if ( isset( $input['end_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_project_end_date', sanitize_text_field( $input['end_date'] ) );
		}
		if ( isset( $input['budget'] ) ) {
			update_post_meta( $post->ID, '_ndizi_project_budget', floatval( $input['budget'] ) );
		}
		if ( isset( $input['hourly_rate'] ) ) {
			update_post_meta( $post->ID, '_ndizi_project_hourly_rate', max( 0.0, floatval( $input['hourly_rate'] ) ) );
		}
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post->ID, '_ndizi_project_status', self::sanitize_enum( $input['status'], array( 'active', 'archived' ), 'active' ) );
		}

		return self::format_project( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-project ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_project( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_project', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blockers = self::find_blocking_dependents(
			array(
				array(
					'source'   => 'ndizi_task',
					'meta_key' => '_ndizi_project_id',
					'value'    => $post->ID,
					'label'    => __( 'tasks', 'ndizi-project-management' ),
				),
				array(
					'source'   => 'ndizi_invoice',
					'meta_key' => '_ndizi_project_id',
					'value'    => $post->ID,
					'label'    => __( 'invoices', 'ndizi-project-management' ),
				),
				array(
					'source' => 'time_entries',
					'column' => 'project_id',
					'value'  => $post->ID,
					'label'  => __( 'time entries', 'ndizi-project-management' ),
				),
			)
		);
		if ( ! empty( $blockers ) ) {
			return self::dependents_error( $blockers );
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete project.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats a task post for ability output.
	 *
	 * @param WP_Post $post Task post.
	 * @return array
	 */
	private static function format_task( $post ) {
		$project_id = get_post_meta( $post->ID, '_ndizi_project_id', true );

		return array(
			'id'               => $post->ID,
			'title'            => $post->post_title,
			'description'      => $post->post_content,
			'project_id'       => $project_id ? intval( $project_id ) : 0,
			'assigned_user_id' => intval( get_post_meta( $post->ID, '_ndizi_assigned_user_id', true ) ),
			'status'           => (string) get_post_meta( $post->ID, '_ndizi_task_status', true ),
			'priority'         => (string) get_post_meta( $post->ID, '_ndizi_task_priority', true ),
			'due_date'         => (string) get_post_meta( $post->ID, '_ndizi_task_due_date', true ),
			'hourly_rate'      => floatval( get_post_meta( $post->ID, '_ndizi_task_hourly_rate', true ) ),
		);
	}

	/**
	 * Execute callback for create-task ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_task( $input ) {
		$project = self::get_post_of_type( $input['project_id'], 'ndizi_project', 'project_id' );
		if ( is_wp_error( $project ) ) {
			return $project;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'ndizi_task',
				'post_status'  => 'publish',
				'post_title'   => sanitize_text_field( $input['title'] ),
				'post_content' => isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_ndizi_project_id', $project->ID );
		if ( isset( $input['assigned_user_id'] ) ) {
			update_post_meta( $post_id, '_ndizi_assigned_user_id', intval( $input['assigned_user_id'] ) );
		}
		if ( isset( $input['due_date'] ) ) {
			update_post_meta( $post_id, '_ndizi_task_due_date', sanitize_text_field( $input['due_date'] ) );
		}
		if ( isset( $input['hourly_rate'] ) ) {
			update_post_meta( $post_id, '_ndizi_task_hourly_rate', max( 0.0, floatval( $input['hourly_rate'] ) ) );
		}
		update_post_meta( $post_id, '_ndizi_task_status', self::sanitize_enum( isset( $input['status'] ) ? $input['status'] : '', array( 'open', 'in_progress', 'completed', 'cancelled' ), 'open' ) );
		update_post_meta( $post_id, '_ndizi_task_priority', self::sanitize_enum( isset( $input['priority'] ) ? $input['priority'] : '', array( 'low', 'medium', 'high' ), 'medium' ) );

		return self::format_task( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-task ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_task( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_task', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$post_data = array( 'ID' => $post->ID );
		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $input['description'] );
		}
		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
		}

		if ( isset( $input['project_id'] ) ) {
			$project = self::get_post_of_type( $input['project_id'], 'ndizi_project', 'project_id' );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
			update_post_meta( $post->ID, '_ndizi_project_id', $project->ID );
		}
		if ( isset( $input['assigned_user_id'] ) ) {
			update_post_meta( $post->ID, '_ndizi_assigned_user_id', intval( $input['assigned_user_id'] ) );
		}
		if ( isset( $input['due_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_task_due_date', sanitize_text_field( $input['due_date'] ) );
		}
		if ( isset( $input['hourly_rate'] ) ) {
			update_post_meta( $post->ID, '_ndizi_task_hourly_rate', max( 0.0, floatval( $input['hourly_rate'] ) ) );
		}
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post->ID, '_ndizi_task_status', self::sanitize_enum( $input['status'], array( 'open', 'in_progress', 'completed', 'cancelled' ), 'open' ) );
		}
		if ( isset( $input['priority'] ) ) {
			update_post_meta( $post->ID, '_ndizi_task_priority', self::sanitize_enum( $input['priority'], array( 'low', 'medium', 'high' ), 'medium' ) );
		}

		return self::format_task( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-task ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_task( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_task', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blockers = self::find_blocking_dependents(
			array(
				array(
					'source' => 'time_entries',
					'column' => 'task_id',
					'value'  => $post->ID,
					'label'  => __( 'time entries', 'ndizi-project-management' ),
				),
			)
		);
		if ( ! empty( $blockers ) ) {
			return self::dependents_error( $blockers );
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete task.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats an invoice post for ability output.
	 *
	 * @param WP_Post $post Invoice post.
	 * @return array
	 */
	private static function format_invoice( $post ) {
		$client_id  = get_post_meta( $post->ID, '_ndizi_client_id', true );
		$project_id = get_post_meta( $post->ID, '_ndizi_project_id', true );

		return array(
			'id'           => $post->ID,
			'number'       => (string) get_post_meta( $post->ID, '_ndizi_invoice_number', true ),
			'client_id'    => $client_id ? intval( $client_id ) : 0,
			'project_id'   => $project_id ? intval( $project_id ) : 0,
			'currency'     => (string) get_post_meta( $post->ID, '_ndizi_invoice_currency', true ),
			'invoice_date' => (string) get_post_meta( $post->ID, '_ndizi_invoice_date', true ),
			'due_date'     => (string) get_post_meta( $post->ID, '_ndizi_invoice_due_date', true ),
			'amount'       => floatval( get_post_meta( $post->ID, '_ndizi_invoice_amount', true ) ),
			'balance'      => floatval( Ndizi_Invoicing::get_invoice_balance( $post->ID ) ),
			'status'       => (string) get_post_meta( $post->ID, '_ndizi_invoice_status', true ),
			'line_items'   => self::normalize_list( get_post_meta( $post->ID, '_ndizi_invoice_line_items', true ) ),
			'payments'     => self::normalize_list( get_post_meta( $post->ID, '_ndizi_invoice_payments', true ) ),
		);
	}

	/**
	 * Execute callback for create-invoice ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_invoice( $input ) {
		$client_id  = 0;
		$project_id = 0;

		if ( ! empty( $input['client_id'] ) ) {
			$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			$client_id = $client->ID;
		}
		if ( ! empty( $input['project_id'] ) ) {
			$project = self::get_post_of_type( $input['project_id'], 'ndizi_project', 'project_id' );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
			$project_id = $project->ID;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ndizi_invoice',
				'post_status' => 'publish',
				'post_title'  => isset( $input['number'] ) ? sanitize_text_field( $input['number'] ) : '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $client_id ) {
			update_post_meta( $post_id, '_ndizi_client_id', $client_id );
		}
		if ( $project_id ) {
			update_post_meta( $post_id, '_ndizi_project_id', $project_id );
		}
		if ( isset( $input['number'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_number', sanitize_text_field( $input['number'] ) );
		}
		update_post_meta( $post_id, '_ndizi_invoice_currency', isset( $input['currency'] ) ? strtoupper( sanitize_text_field( $input['currency'] ) ) : get_option( 'ndizi_default_currency', 'USD' ) );
		if ( isset( $input['invoice_date'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_date', sanitize_text_field( $input['invoice_date'] ) );
		}
		if ( isset( $input['due_date'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_due_date', sanitize_text_field( $input['due_date'] ) );
		}
		update_post_meta( $post_id, '_ndizi_invoice_amount', floatval( $input['amount'] ) );
		if ( isset( $input['line_items'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_line_items', sanitize_meta( '_ndizi_invoice_line_items', $input['line_items'], 'post', 'ndizi_invoice' ) );
		}
		if ( isset( $input['payments'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_payments', sanitize_meta( '_ndizi_invoice_payments', $input['payments'], 'post', 'ndizi_invoice' ) );
		}
		// Written last: amount/payments writes above trigger Ndizi_Invoicing's automatic
		// status recalculation, so an explicit status must be applied after to win.
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post_id, '_ndizi_invoice_status', self::sanitize_enum( $input['status'], array( 'draft', 'sent', 'partial', 'paid', 'void' ), 'draft' ) );
		}

		return self::format_invoice( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-invoice ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_invoice( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_invoice', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( isset( $input['number'] ) ) {
			wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => sanitize_text_field( $input['number'] ),
				)
			);
			update_post_meta( $post->ID, '_ndizi_invoice_number', sanitize_text_field( $input['number'] ) );
		}
		if ( isset( $input['client_id'] ) ) {
			$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			update_post_meta( $post->ID, '_ndizi_client_id', $client->ID );
		}
		if ( isset( $input['project_id'] ) ) {
			$project = self::get_post_of_type( $input['project_id'], 'ndizi_project', 'project_id' );
			if ( is_wp_error( $project ) ) {
				return $project;
			}
			update_post_meta( $post->ID, '_ndizi_project_id', $project->ID );
		}
		if ( isset( $input['currency'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_currency', strtoupper( sanitize_text_field( $input['currency'] ) ) );
		}
		if ( isset( $input['invoice_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_date', sanitize_text_field( $input['invoice_date'] ) );
		}
		if ( isset( $input['due_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_due_date', sanitize_text_field( $input['due_date'] ) );
		}
		if ( isset( $input['amount'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_amount', floatval( $input['amount'] ) );
		}
		if ( isset( $input['line_items'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_line_items', sanitize_meta( '_ndizi_invoice_line_items', $input['line_items'], 'post', 'ndizi_invoice' ) );
		}
		if ( isset( $input['payments'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_payments', sanitize_meta( '_ndizi_invoice_payments', $input['payments'], 'post', 'ndizi_invoice' ) );
		}
		// Written last so it isn't clobbered by the automatic status recalculation
		// that Ndizi_Invoicing runs whenever amount/payments meta changes above.
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post->ID, '_ndizi_invoice_status', self::sanitize_enum( $input['status'], array( 'draft', 'sent', 'partial', 'paid', 'void' ), 'draft' ) );
		}

		return self::format_invoice( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-invoice ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_invoice( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_invoice', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blockers = self::find_blocking_dependents(
			array(
				array(
					'source' => 'time_entries',
					'column' => 'invoice_id',
					'value'  => $post->ID,
					'label'  => __( 'time entries', 'ndizi-project-management' ),
				),
			)
		);
		if ( ! empty( $blockers ) ) {
			return self::dependents_error( $blockers );
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete invoice.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats a contact post for ability output.
	 *
	 * @param WP_Post $post Contact post.
	 * @return array
	 */
	private static function format_contact( $post ) {
		return array(
			'id'                 => $post->ID,
			'name'               => $post->post_title,
			'email'              => (string) get_post_meta( $post->ID, '_ndizi_contact_email', true ),
			'phone'              => (string) get_post_meta( $post->ID, '_ndizi_contact_phone', true ),
			'role'               => (string) get_post_meta( $post->ID, '_ndizi_contact_role', true ),
			'associated_clients' => self::normalize_list( get_post_meta( $post->ID, '_ndizi_associated_clients', true ) ),
		);
	}

	/**
	 * Execute callback for get-contacts ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_contacts( $input = null ) {
		$args = array(
			'post_type'      => 'ndizi_contact',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}

		$contacts = get_posts( $args );

		if ( ! empty( $input['client_id'] ) ) {
			$client_id = intval( $input['client_id'] );
			$contacts  = array_values(
				array_filter(
					$contacts,
					function ( $contact ) use ( $client_id ) {
						$associated = get_post_meta( $contact->ID, '_ndizi_associated_clients', true );
						return is_array( $associated ) && in_array( $client_id, array_map( 'intval', $associated ), true );
					}
				)
			);
		}

		return array_map( array( __CLASS__, 'format_contact' ), $contacts );
	}

	/**
	 * Resolves a list of client IDs, validating each one exists.
	 *
	 * @param array $client_ids Raw client IDs.
	 * @return int[]|WP_Error
	 */
	private static function resolve_client_ids( $client_ids ) {
		$resolved = array();
		foreach ( (array) $client_ids as $client_id ) {
			$client = self::get_post_of_type( $client_id, 'ndizi_client', 'associated_clients' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			$resolved[] = $client->ID;
		}
		return $resolved;
	}

	/**
	 * Execute callback for create-contact ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_contact( $input ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ndizi_contact',
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $input['name'] ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $input['email'] ) ) {
			update_post_meta( $post_id, '_ndizi_contact_email', sanitize_email( $input['email'] ) );
		}
		if ( isset( $input['phone'] ) ) {
			update_post_meta( $post_id, '_ndizi_contact_phone', sanitize_text_field( $input['phone'] ) );
		}
		if ( isset( $input['role'] ) ) {
			update_post_meta( $post_id, '_ndizi_contact_role', sanitize_text_field( $input['role'] ) );
		}
		if ( isset( $input['associated_clients'] ) ) {
			$client_ids = self::resolve_client_ids( $input['associated_clients'] );
			if ( is_wp_error( $client_ids ) ) {
				return $client_ids;
			}
			update_post_meta( $post_id, '_ndizi_associated_clients', $client_ids );
		}

		return self::format_contact( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-contact ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_contact( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_contact', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( isset( $input['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $post->ID,
					'post_title' => sanitize_text_field( $input['name'] ),
				)
			);
		}
		if ( isset( $input['email'] ) ) {
			update_post_meta( $post->ID, '_ndizi_contact_email', sanitize_email( $input['email'] ) );
		}
		if ( isset( $input['phone'] ) ) {
			update_post_meta( $post->ID, '_ndizi_contact_phone', sanitize_text_field( $input['phone'] ) );
		}
		if ( isset( $input['role'] ) ) {
			update_post_meta( $post->ID, '_ndizi_contact_role', sanitize_text_field( $input['role'] ) );
		}
		if ( isset( $input['associated_clients'] ) ) {
			$client_ids = self::resolve_client_ids( $input['associated_clients'] );
			if ( is_wp_error( $client_ids ) ) {
				return $client_ids;
			}
			update_post_meta( $post->ID, '_ndizi_associated_clients', $client_ids );
		}

		return self::format_contact( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-contact ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_contact( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_contact', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete contact.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats a time-off post for ability output.
	 *
	 * @param WP_Post $post Time-off post.
	 * @return array
	 */
	private static function format_time_off( $post ) {
		return array(
			'id'         => $post->ID,
			'user_id'    => intval( get_post_meta( $post->ID, '_ndizi_time_off_user_id', true ) ),
			'client_id'  => intval( get_post_meta( $post->ID, '_ndizi_time_off_client_id', true ) ),
			'start_date' => (string) get_post_meta( $post->ID, '_ndizi_time_off_start_date', true ),
			'end_date'   => (string) get_post_meta( $post->ID, '_ndizi_time_off_end_date', true ),
			'type'       => (string) get_post_meta( $post->ID, '_ndizi_time_off_type', true ),
			'status'     => (string) get_post_meta( $post->ID, '_ndizi_time_off_status', true ),
		);
	}

	/**
	 * Execute callback for get-time-off ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_time_off( $input = null ) {
		$args = array(
			'post_type'      => 'ndizi_time_off',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(),
		);

		if ( ! empty( $input['id'] ) ) {
			$args['p'] = intval( $input['id'] );
		}
		if ( ! empty( $input['user_id'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_ndizi_time_off_user_id',
				'value' => intval( $input['user_id'] ),
			);
		}
		if ( ! empty( $input['status'] ) ) {
			$args['meta_query'][] = array(
				'key'   => '_ndizi_time_off_status',
				'value' => sanitize_key( $input['status'] ),
			);
		}

		return array_map( array( __CLASS__, 'format_time_off' ), get_posts( $args ) );
	}

	/**
	 * Execute callback for create-time-off ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function create_time_off( $input ) {
		$user_id    = ! empty( $input['user_id'] ) ? intval( $input['user_id'] ) : get_current_user_id();
		$type       = self::sanitize_enum( isset( $input['type'] ) ? $input['type'] : '', array( 'vacation', 'sick_leave', 'personal', 'other' ), 'other' );
		$status     = self::sanitize_enum( isset( $input['status'] ) ? $input['status'] : '', array( 'pending', 'approved', 'rejected' ), 'pending' );
		$start_date = sanitize_text_field( $input['start_date'] );
		$end_date   = isset( $input['end_date'] ) ? sanitize_text_field( $input['end_date'] ) : $start_date;

		$client_id = 0;
		if ( ! empty( $input['client_id'] ) ) {
			$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			$client_id = $client->ID;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'ndizi_time_off',
				'post_status' => 'publish',
				/* translators: 1: time off type, 2: start date, 3: end date */
				'post_title'  => sprintf( __( '%1$s: %2$s to %3$s', 'ndizi-project-management' ), ucfirst( str_replace( '_', ' ', $type ) ), $start_date, $end_date ),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_ndizi_time_off_user_id', $user_id );
		if ( $client_id ) {
			update_post_meta( $post_id, '_ndizi_time_off_client_id', $client_id );
		}
		update_post_meta( $post_id, '_ndizi_time_off_start_date', $start_date );
		update_post_meta( $post_id, '_ndizi_time_off_end_date', $end_date );
		update_post_meta( $post_id, '_ndizi_time_off_type', $type );
		update_post_meta( $post_id, '_ndizi_time_off_status', $status );

		return self::format_time_off( get_post( $post_id ) );
	}

	/**
	 * Execute callback for update-time-off ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_time_off( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_time_off', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( isset( $input['user_id'] ) ) {
			update_post_meta( $post->ID, '_ndizi_time_off_user_id', intval( $input['user_id'] ) );
		}
		if ( isset( $input['client_id'] ) ) {
			$client = self::get_post_of_type( $input['client_id'], 'ndizi_client', 'client_id' );
			if ( is_wp_error( $client ) ) {
				return $client;
			}
			update_post_meta( $post->ID, '_ndizi_time_off_client_id', $client->ID );
		}
		if ( isset( $input['start_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_time_off_start_date', sanitize_text_field( $input['start_date'] ) );
		}
		if ( isset( $input['end_date'] ) ) {
			update_post_meta( $post->ID, '_ndizi_time_off_end_date', sanitize_text_field( $input['end_date'] ) );
		}
		if ( isset( $input['type'] ) ) {
			update_post_meta( $post->ID, '_ndizi_time_off_type', self::sanitize_enum( $input['type'], array( 'vacation', 'sick_leave', 'personal', 'other' ), 'other' ) );
		}
		if ( isset( $input['status'] ) ) {
			update_post_meta( $post->ID, '_ndizi_time_off_status', self::sanitize_enum( $input['status'], array( 'pending', 'approved', 'rejected' ), 'pending' ) );
		}

		return self::format_time_off( get_post( $post->ID ) );
	}

	/**
	 * Execute callback for delete-time-off ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_time_off( $input ) {
		$post = self::get_post_of_type( $input['id'], 'ndizi_time_off', 'id' );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete time-off request.', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $post->ID,
		);
	}

	/**
	 * Formats a time entry row for ability output.
	 *
	 * @param object $log Time entry row from Ndizi_DB.
	 * @return array
	 */
	private static function format_time_entry( $log ) {
		$client  = $log->client_id ? get_post( $log->client_id ) : null;
		$project = $log->project_id ? get_post( $log->project_id ) : null;
		$task    = $log->task_id ? get_post( $log->task_id ) : null;
		$user    = get_userdata( $log->user_id );

		return array(
			'id'           => intval( $log->id ),
			'client_id'    => intval( $log->client_id ),
			'client_name'  => $client ? $client->post_title : '',
			'project_id'   => intval( $log->project_id ),
			'project_name' => $project ? $project->post_title : '',
			'task_id'      => intval( $log->task_id ),
			'task_name'    => $task ? $task->post_title : '',
			'user_id'      => intval( $log->user_id ),
			'user_name'    => $user ? $user->display_name : '',
			'description'  => (string) $log->description,
			'start_time'   => (string) $log->start_time,
			'end_time'     => (string) $log->end_time,
			'duration'     => intval( $log->duration ),
			'billable'     => (bool) $log->billable,
			'approved'     => (bool) $log->approved,
			'invoice_id'   => intval( $log->invoice_id ),
		);
	}

	/**
	 * Execute callback for get-time-entries ability.
	 *
	 * @param array $input Input parameters.
	 * @return array
	 */
	public static function get_time_entries( $input = null ) {
		$per_page = ! empty( $input['per_page'] ) ? min( 100, max( 1, intval( $input['per_page'] ) ) ) : 20;
		$page     = ! empty( $input['page'] ) ? max( 1, intval( $input['page'] ) ) : 1;

		$args = array(
			'number' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		if ( ! empty( $input['client_id'] ) ) {
			$args['client_id'] = intval( $input['client_id'] );
		}
		if ( isset( $input['project_id'] ) && '' !== $input['project_id'] ) {
			$args['project_id'] = intval( $input['project_id'] );
		}

		$can_manage = Ndizi_Roles::current_user_can( 'ndizi_manage_time' );
		if ( ! $can_manage ) {
			$args['user_id'] = get_current_user_id();
		} elseif ( ! empty( $input['user_id'] ) ) {
			$args['user_id'] = intval( $input['user_id'] );
		}

		if ( isset( $input['billable'] ) ) {
			$args['billable'] = $input['billable'] ? 1 : 0;
		}
		if ( isset( $input['approved'] ) ) {
			$args['approved'] = $input['approved'] ? 1 : 0;
		}
		if ( ! empty( $input['invoiced'] ) && in_array( $input['invoiced'], array( 'yes', 'no' ), true ) ) {
			$args['invoiced'] = $input['invoiced'];
		}
		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}
		if ( ! empty( $input['start_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['start_date'] ) ) {
			$args['start_date'] = $input['start_date'];
		}
		if ( ! empty( $input['end_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $input['end_date'] ) ) {
			$args['end_date'] = $input['end_date'];
		}
		if ( ! empty( $input['orderby'] ) ) {
			$args['orderby'] = sanitize_key( $input['orderby'] );
		}
		if ( ! empty( $input['order'] ) ) {
			$args['order'] = 'ASC' === strtoupper( sanitize_key( $input['order'] ) ) ? 'ASC' : 'DESC';
		}

		$total   = Ndizi_DB::get_time_entries_count( $args );
		$entries = Ndizi_DB::get_time_entries( $args );

		return array(
			'total'       => intval( $total ),
			'total_pages' => intval( ceil( $total / $per_page ) ),
			'entries'     => array_map( array( __CLASS__, 'format_time_entry' ), $entries ),
		);
	}

	/**
	 * Execute callback for update-time-entry ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function update_time_entry( $input ) {
		$id      = intval( $input['id'] );
		$user_id = get_current_user_id();
		$log     = Ndizi_DB::get_time_entry( $id );

		if ( ! $log ) {
			return new WP_Error( 'not_found', __( 'Time entry not found', 'ndizi-project-management' ), array( 'status' => 404 ) );
		}
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			return new WP_Error( 'forbidden', __( 'Unauthorized to edit this entry', 'ndizi-project-management' ), array( 'status' => 403 ) );
		}

		$params = array( 'client_id', 'project_id', 'task_id', 'description', 'start_time', 'end_time', 'duration', 'billable' );
		$data   = array();
		foreach ( $params as $param ) {
			if ( isset( $input[ $param ] ) ) {
				$data[ $param ] = $input[ $param ];
			}
		}
		$updating_other_fields = ! empty( $data );

		if ( isset( $input['approved'] ) && Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			$is_approved         = (bool) $input['approved'];
			$data['approved']    = $is_approved ? 1 : 0;
			$data['approved_by'] = $is_approved ? get_current_user_id() : 0;
		}

		if ( $updating_other_fields ) {
			if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
				return new WP_Error( 'locked', __( 'Cannot update time entry. The existing time entry is in a locked period.', 'ndizi-project-management' ), array( 'status' => 400 ) );
			}
			if ( isset( $input['start_time'] ) && Ndizi_DB::is_date_locked( $input['start_time'] ) ) {
				return new WP_Error( 'locked', __( 'Cannot update time entry. The new start date is locked.', 'ndizi-project-management' ), array( 'status' => 400 ) );
			}
		}

		if ( ! Ndizi_DB::update_time_entry( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update time entry', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return self::format_time_entry( Ndizi_DB::get_time_entry( $id ) );
	}

	/**
	 * Execute callback for delete-time-entry ability.
	 *
	 * @param array $input Input parameters.
	 * @return array|WP_Error
	 */
	public static function delete_time_entry( $input ) {
		$id      = intval( $input['id'] );
		$user_id = get_current_user_id();
		$log     = Ndizi_DB::get_time_entry( $id );

		if ( ! $log ) {
			return new WP_Error( 'not_found', __( 'Time entry not found', 'ndizi-project-management' ), array( 'status' => 404 ) );
		}
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			return new WP_Error( 'forbidden', __( 'Unauthorized to delete this entry', 'ndizi-project-management' ), array( 'status' => 403 ) );
		}
		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			return new WP_Error( 'locked', __( 'Cannot delete time entry. The time entry is in a locked period.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}
		if ( ! Ndizi_DB::delete_time_entry( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete time entry', 'ndizi-project-management' ), array( 'status' => 500 ) );
		}

		return array(
			'deleted' => true,
			'id'      => $id,
		);
	}
}
