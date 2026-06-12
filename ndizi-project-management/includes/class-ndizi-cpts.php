<?php
/**
 * Register CPTs and metadata for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_CPTs {

	/**
	 * Initialize custom post types and taxonomies
	 */
	public static function init() {
		self::register_post_types();
		self::register_metadata();
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
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
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
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'comments' ),
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
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'comments' ),
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
					'hierarchical'    => false,
					'supports'        => array( 'title', 'editor' ),
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
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);
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
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_address',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		// The auth key is a portal access credential and is intentionally NOT exposed via REST.
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_auth_key',
			array(
				'show_in_rest'  => false,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'ndizi_manage_clients' );
				},
			)
		);
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_status',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'active', // active, archived
			)
		);

		// Project Meta
		register_post_meta(
			'ndizi_project',
			'_ndizi_client_id',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_start_date',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_end_date',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_budget',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'number',
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_status',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'active', // active, archived
			)
		);
		register_post_meta(
			'ndizi_project',
			'_ndizi_project_hourly_rate',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'number',
			)
		);

		// Task Meta
		register_post_meta(
			'ndizi_task',
			'_ndizi_project_id',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_assigned_user_id',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_status',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'open', // open, in_progress, completed, cancelled
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_priority',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
				'default'      => 'medium', // low, medium, high
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_due_date',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_task',
			'_ndizi_task_hourly_rate',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'number',
			)
		);

		// Invoice Meta
		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_project_id',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'integer',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_date',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'string',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_due_date',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'string',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_amount',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'number',
				)
			);
			register_post_meta(
				'ndizi_invoice',
				'_ndizi_invoice_status',
				array(
					'show_in_rest' => true,
					'single'       => true,
					'type'         => 'string',
					'default'      => 'draft', // draft, sent, paid, void
				)
			);
		}

		// Contact Meta
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_email',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_phone',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_contact_role',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_contact',
			'_ndizi_associated_clients',
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type' => 'integer',
						),
					),
				),
				'single'       => true,
				'type'         => 'array',
			)
		);
	}
}
