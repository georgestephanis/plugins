<?php
/**
 * Register CPTs and metadata for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_CPTs {

	/**
	 * Stash of old meta values captured before update_post_meta writes.
	 *
	 * @var array
	 */
	private static $prev_meta_values = array();

	/**
	 * Initialize custom post types and taxonomies
	 */
	public static function init() {
		self::register_post_types();
		self::register_metadata();
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );

		// Meta updates (Assignments and Statuses) to fire canonical events
		add_action( 'added_post_meta', array( __CLASS__, 'handle_added_post_meta' ), 10, 4 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'capture_old_task_meta' ), 10, 3 );
		add_action( 'updated_post_meta', array( __CLASS__, 'handle_updated_post_meta' ), 10, 4 );
	}

	/**
	 * Register CPTs
	 */
	public static function register_post_types() {
		// Clients
		register_post_type(
			'ndizi_client',
			array(
				'labels'          => array(
					'name'               => __( 'Clients', 'ndizi-project-management' ),
					'singular_name'      => __( 'Client', 'ndizi-project-management' ),
					'add_new'            => __( 'Add New Client', 'ndizi-project-management' ),
					'add_new_item'       => __( 'Add New Client', 'ndizi-project-management' ),
					'edit_item'          => __( 'Edit Client', 'ndizi-project-management' ),
					'new_item'           => __( 'New Client', 'ndizi-project-management' ),
					'view_item'          => __( 'View Client', 'ndizi-project-management' ),
					'search_items'       => __( 'Search Clients', 'ndizi-project-management' ),
					'not_found'          => __( 'No clients found', 'ndizi-project-management' ),
					'not_found_in_trash' => __( 'No clients found in Trash', 'ndizi-project-management' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'ndizi-pm',
				'menu_icon'       => 'dashicons-networking',
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_posts'             => 'ndizi_manage_clients',
					'edit_others_posts'      => 'ndizi_manage_clients',
					'publish_posts'          => 'ndizi_manage_clients',
					'read_private_posts'     => 'ndizi_manage_clients',
					'create_posts'           => 'ndizi_manage_clients',
					'delete_posts'           => 'ndizi_manage_clients',
					'delete_private_posts'   => 'ndizi_manage_clients',
					'delete_published_posts' => 'ndizi_manage_clients',
					'delete_others_posts'    => 'ndizi_manage_clients',
					'edit_private_posts'     => 'ndizi_manage_clients',
					'edit_published_posts'   => 'ndizi_manage_clients',
				),
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Projects
		register_post_type(
			'ndizi_project',
			array(
				'labels'          => array(
					'name'               => __( 'Projects', 'ndizi-project-management' ),
					'singular_name'      => __( 'Project', 'ndizi-project-management' ),
					'add_new'            => __( 'Add New Project', 'ndizi-project-management' ),
					'add_new_item'       => __( 'Add New Project', 'ndizi-project-management' ),
					'edit_item'          => __( 'Edit Project', 'ndizi-project-management' ),
					'new_item'           => __( 'New Project', 'ndizi-project-management' ),
					'view_item'          => __( 'View Project', 'ndizi-project-management' ),
					'search_items'       => __( 'Search Projects', 'ndizi-project-management' ),
					'not_found'          => __( 'No projects found', 'ndizi-project-management' ),
					'not_found_in_trash' => __( 'No projects found in Trash', 'ndizi-project-management' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'ndizi-pm',
				'menu_icon'       => 'dashicons-portfolio',
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_posts'             => 'ndizi_manage_projects',
					'edit_others_posts'      => 'ndizi_manage_projects',
					'publish_posts'          => 'ndizi_manage_projects',
					'read_private_posts'     => 'ndizi_manage_projects',
					'create_posts'           => 'ndizi_manage_projects',
					'delete_posts'           => 'ndizi_manage_projects',
					'delete_private_posts'   => 'ndizi_manage_projects',
					'delete_published_posts' => 'ndizi_manage_projects',
					'delete_others_posts'    => 'ndizi_manage_projects',
					'edit_private_posts'     => 'ndizi_manage_projects',
					'edit_published_posts'   => 'ndizi_manage_projects',
				),
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'comments', 'custom-fields' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Tasks
		register_post_type(
			'ndizi_task',
			array(
				'labels'          => array(
					'name'               => __( 'Tasks', 'ndizi-project-management' ),
					'singular_name'      => __( 'Task', 'ndizi-project-management' ),
					'add_new'            => __( 'Add New Task', 'ndizi-project-management' ),
					'add_new_item'       => __( 'Add New Task', 'ndizi-project-management' ),
					'edit_item'          => __( 'Edit Task', 'ndizi-project-management' ),
					'new_item'           => __( 'New Task', 'ndizi-project-management' ),
					'view_item'          => __( 'View Task', 'ndizi-project-management' ),
					'search_items'       => __( 'Search Tasks', 'ndizi-project-management' ),
					'not_found'          => __( 'No tasks found', 'ndizi-project-management' ),
					'not_found_in_trash' => __( 'No tasks found in Trash', 'ndizi-project-management' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'ndizi-pm',
				'menu_icon'       => 'dashicons-yes',
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_posts'             => 'ndizi_manage_tasks',
					'edit_others_posts'      => 'ndizi_manage_tasks',
					'publish_posts'          => 'ndizi_manage_tasks',
					'read_private_posts'     => 'ndizi_manage_tasks',
					'create_posts'           => 'ndizi_manage_tasks',
					'delete_posts'           => 'ndizi_manage_tasks',
					'delete_private_posts'   => 'ndizi_manage_tasks',
					'delete_published_posts' => 'ndizi_manage_tasks',
					'delete_others_posts'    => 'ndizi_manage_tasks',
					'edit_private_posts'     => 'ndizi_manage_tasks',
					'edit_published_posts'   => 'ndizi_manage_tasks',
				),
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'comments', 'custom-fields' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Invoices
		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			register_post_type(
				'ndizi_invoice',
				array(
					'labels'          => array(
						'name'               => __( 'Invoices', 'ndizi-project-management' ),
						'singular_name'      => __( 'Invoice', 'ndizi-project-management' ),
						'add_new'            => __( 'Add New Invoice', 'ndizi-project-management' ),
						'add_new_item'       => __( 'Add New Invoice', 'ndizi-project-management' ),
						'edit_item'          => __( 'Edit Invoice', 'ndizi-project-management' ),
						'new_item'           => __( 'New Invoice', 'ndizi-project-management' ),
						'view_item'          => __( 'View Invoice', 'ndizi-project-management' ),
						'search_items'       => __( 'Search Invoices', 'ndizi-project-management' ),
						'not_found'          => __( 'No invoices found', 'ndizi-project-management' ),
						'not_found_in_trash' => __( 'No invoices found in Trash', 'ndizi-project-management' ),
					),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => 'ndizi-pm',
					'menu_icon'       => 'dashicons-analytics',
					'capability_type' => 'post',
					'capabilities'    => array(
						'edit_posts'             => 'ndizi_manage_invoices',
						'edit_others_posts'      => 'ndizi_manage_invoices',
						'publish_posts'          => 'ndizi_manage_invoices',
						'read_private_posts'     => 'ndizi_manage_invoices',
						'create_posts'           => 'ndizi_manage_invoices',
						'delete_posts'           => 'ndizi_manage_invoices',
						'delete_private_posts'   => 'ndizi_manage_invoices',
						'delete_published_posts' => 'ndizi_manage_invoices',
						'delete_others_posts'    => 'ndizi_manage_invoices',
						'edit_private_posts'     => 'ndizi_manage_invoices',
						'edit_published_posts'   => 'ndizi_manage_invoices',
					),
					'map_meta_cap'    => true,
					'hierarchical'    => false,
					'supports'        => array( 'title', 'editor', 'custom-fields' ),
					'show_in_rest'    => true,
					'has_archive'     => false,
				)
			);
		}

		// Contacts
		register_post_type(
			'ndizi_contact',
			array(
				'labels'          => array(
					'name'               => __( 'Contacts', 'ndizi-project-management' ),
					'singular_name'      => __( 'Contact', 'ndizi-project-management' ),
					'add_new'            => __( 'Add New Contact', 'ndizi-project-management' ),
					'add_new_item'       => __( 'Add New Contact', 'ndizi-project-management' ),
					'edit_item'          => __( 'Edit Contact', 'ndizi-project-management' ),
					'new_item'           => __( 'New Contact', 'ndizi-project-management' ),
					'view_item'          => __( 'View Contact', 'ndizi-project-management' ),
					'search_items'       => __( 'Search Contacts', 'ndizi-project-management' ),
					'not_found'          => __( 'No contacts found', 'ndizi-project-management' ),
					'not_found_in_trash' => __( 'No contacts found in Trash', 'ndizi-project-management' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'ndizi-pm',
				'menu_icon'       => 'dashicons-businessman',
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_posts'             => 'ndizi_manage_contacts',
					'edit_others_posts'      => 'ndizi_manage_contacts',
					'publish_posts'          => 'ndizi_manage_contacts',
					'read_private_posts'     => 'ndizi_manage_contacts',
					'create_posts'           => 'ndizi_manage_contacts',
					'delete_posts'           => 'ndizi_manage_contacts',
					'delete_private_posts'   => 'ndizi_manage_contacts',
					'delete_published_posts' => 'ndizi_manage_contacts',
					'delete_others_posts'    => 'ndizi_manage_contacts',
					'edit_private_posts'     => 'ndizi_manage_contacts',
					'edit_published_posts'   => 'ndizi_manage_contacts',
				),
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Time Off Request
		if ( Ndizi_Project_Management::is_module_active( 'time_off' ) ) {
			register_post_type(
				'ndizi_time_off',
				array(
					'labels'          => array(
						'name'               => __( 'Time Off Requests', 'ndizi-project-management' ),
						'singular_name'      => __( 'Time Off Request', 'ndizi-project-management' ),
						'add_new'            => __( 'Request Time Off', 'ndizi-project-management' ),
						'add_new_item'       => __( 'Request Time Off', 'ndizi-project-management' ),
						'edit_item'          => __( 'Edit Request', 'ndizi-project-management' ),
						'new_item'           => __( 'New Request', 'ndizi-project-management' ),
						'view_item'          => __( 'View Request', 'ndizi-project-management' ),
						'search_items'       => __( 'Search Requests', 'ndizi-project-management' ),
						'not_found'          => __( 'No requests found', 'ndizi-project-management' ),
						'not_found_in_trash' => __( 'No requests found in Trash', 'ndizi-project-management' ),
					),
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => 'ndizi-pm',
					'menu_icon'       => 'dashicons-calendar-alt',
					'capability_type' => 'post',
					'capabilities'    => array(
						'edit_posts'             => 'ndizi_manage_time',
						'edit_others_posts'      => 'ndizi_manage_time',
						'publish_posts'          => 'ndizi_manage_time',
						'read_private_posts'     => 'ndizi_manage_time',
						'create_posts'           => 'ndizi_manage_time',
						'delete_posts'           => 'ndizi_manage_time',
						'delete_private_posts'   => 'ndizi_manage_time',
						'delete_published_posts' => 'ndizi_manage_time',
						'delete_others_posts'    => 'ndizi_manage_time',
						'edit_private_posts'     => 'ndizi_manage_time',
						'edit_published_posts'   => 'ndizi_manage_time',
					),
					'map_meta_cap'    => true,
					'hierarchical'    => false,
					'supports'        => array( 'title', 'editor', 'custom-fields' ),
					'show_in_rest'    => true,
					'has_archive'     => false,
				)
			);
		}
	}

	/**
	 * Register metadata schemas for CPTs to expose in REST API
	 */
	public static function register_metadata() {
		// Client Meta
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_website',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
			)
		);
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_address',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		// The auth key is a portal access credential and is intentionally NOT exposed via REST.
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_auth_key',
			array(
				'show_in_rest'      => false,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'ndizi_manage_clients' );
				},
			)
		);
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_status',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'active', // active, archived
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			'ndizi_client',
			'_ndizi_external_source',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_client',
			'_ndizi_external_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Project Meta
		register_post_meta(
			'ndizi_project',
			'_ndizi_client_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_start_date',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_end_date',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_budget',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'sanitize_callback' => function ( $value ) {
					return floatval( $value );
				},
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_status',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'active', // active, archived
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_hourly_rate',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'sanitize_callback' => function ( $value ) {
					return floatval( $value );
				},
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_external_source',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_external_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Task Meta
		register_post_meta(
			'ndizi_task',
			'_ndizi_project_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_assigned_user_id',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_status',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'open', // open, in_progress, completed, cancelled
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_priority',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'default'           => 'medium', // low, medium, high
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_due_date',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_hourly_rate',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'number',
				'sanitize_callback' => function ( $value ) {
					return floatval( $value );
				},
			)
		);

		// Invoice Meta
		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_client_id',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_project_id',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_number',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_currency',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => get_option( 'ndizi_default_currency', 'USD' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_date',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_due_date',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_amount',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'number',
					'sanitize_callback' => function ( $value ) {
						return floatval( $value );
					},
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_status',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'default'           => 'draft', // draft, sent, paid, void
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_line_items',
				array(
					'show_in_rest'      => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'description' => array( 'type' => 'string' ),
									'quantity'    => array( 'type' => 'number' ),
									'unit_price'  => array( 'type' => 'number' ),
									'amount'      => array( 'type' => 'number' ),
								),
							),
						),
					),
					'single'            => true,
					'type'              => 'array',
					'sanitize_callback' => function ( $items ) {
						if ( ! is_array( $items ) ) {
							return array();
						}
						$clean = array();
						foreach ( $items as $item ) {
							if ( ! is_array( $item ) ) {
								continue;
							}
							$clean[] = array(
								'description' => isset( $item['description'] ) ? sanitize_text_field( $item['description'] ) : '',
								'quantity'    => isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0.0,
								'unit_price'  => isset( $item['unit_price'] ) ? floatval( $item['unit_price'] ) : 0.0,
								'amount'      => isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0.0,
							);
						}
						return $clean;
					},
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_external_source',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_external_id',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}

		// Contact Meta
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_email',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_phone',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_role',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_associated_clients',
			array(
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'single'            => true,
				'type'              => 'array',
				'sanitize_callback' => function ( $value ) {
					return array_map( 'absint', (array) $value );
				},
			)
		);

		// Time Off Meta
		if ( Ndizi_Project_Management::is_module_active( 'time_off' ) ) {
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_start_date',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_end_date',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_type',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_status',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_user_id',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			);
			register_post_meta(
				'ndizi_time_off',
				'_ndizi_time_off_client_id',
				array(
					'show_in_rest'      => true,
					'single'            => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				)
			);
		}
	}

	/**
	 * Disable Gutenberg block editor for Ndizi post types.
	 *
	 * @param bool   $use_block_editor Whether to use the block editor.
	 * @param string $post_type        The post type slug.
	 * @return bool
	 */
	public static function disable_block_editor( $use_block_editor, $post_type ) {
		$ndizi_post_types = array(
			'ndizi_client',
			'ndizi_project',
			'ndizi_task',
			'ndizi_invoice',
			'ndizi_contact',
			'ndizi_time_off',
		);

		if ( in_array( $post_type, $ndizi_post_types, true ) ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 * Cache the current meta value before it is overwritten.
	 *
	 * @param mixed  $check      Whether to bypass filtering metadata.
	 * @param int    $object_id  Object ID.
	 * @param string $meta_key   Meta key.
	 * @return mixed
	 */
	public static function capture_old_task_meta( $check, $object_id, $meta_key ) {
		if ( in_array( $meta_key, array( '_ndizi_assigned_user_id', '_ndizi_task_status', '_ndizi_invoice_status' ), true ) ) {
			self::$prev_meta_values[ $object_id . ':' . $meta_key ] = get_post_meta( $object_id, $meta_key, true );
		}
		return $check;
	}

	/**
	 * Handler for added_post_meta
	 */
	public static function handle_added_post_meta( $_mid, $object_id, $meta_key, $_meta_value ) {
		if ( ! in_array( $meta_key, array( '_ndizi_assigned_user_id', '_ndizi_task_status', '_ndizi_invoice_status' ), true ) ) {
			return;
		}
		self::handle_meta_change( $object_id, $meta_key, $_meta_value );
	}

	/**
	 * Handler for updated_post_meta
	 */
	public static function handle_updated_post_meta( $_mid, $object_id, $meta_key, $_meta_value ) {
		if ( ! in_array( $meta_key, array( '_ndizi_assigned_user_id', '_ndizi_task_status', '_ndizi_invoice_status' ), true ) ) {
			return;
		}

		$cache_key = $object_id . ':' . $meta_key;
		$old_value = isset( self::$prev_meta_values[ $cache_key ] ) ? self::$prev_meta_values[ $cache_key ] : '';
		unset( self::$prev_meta_values[ $cache_key ] );

		if ( $_meta_value === $old_value ) {
			return;
		}
		self::handle_meta_change( $object_id, $meta_key, $_meta_value, $old_value );
	}

	/**
	 * Process meta updates to dispatch core actions
	 */
	private static function handle_meta_change( $object_id, $meta_key, $new_val, $old_val = '' ) {
		$post_type = get_post_type( $object_id );

		if ( 'ndizi_task' === $post_type ) {
			if ( '_ndizi_assigned_user_id' === $meta_key ) {
				$assignee_id = intval( $new_val );
				if ( $assignee_id > 0 ) {
					do_action( 'ndizi_task_assigned', $object_id, $assignee_id );
				}
			} elseif ( '_ndizi_task_status' === $meta_key ) {
				do_action( 'ndizi_task_status_changed', $object_id, $new_val, $old_val );
			}
		} elseif ( 'ndizi_invoice' === $post_type ) {
			if ( '_ndizi_invoice_status' === $meta_key ) {
				do_action( 'ndizi_invoice_status_changed', $object_id, $new_val, $old_val );
			}
		}
	}
}
