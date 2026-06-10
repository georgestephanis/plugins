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
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_metadata' ) );
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
					'name'               => __( 'Clients', 'ndizi' ),
					'singular_name'      => __( 'Client', 'ndizi' ),
					'add_new'            => __( 'Add New Client', 'ndizi' ),
					'add_new_item'       => __( 'Add New Client', 'ndizi' ),
					'edit_item'          => __( 'Edit Client', 'ndizi' ),
					'new_item'           => __( 'New Client', 'ndizi' ),
					'view_item'          => __( 'View Client', 'ndizi' ),
					'search_items'       => __( 'Search Clients', 'ndizi' ),
					'not_found'          => __( 'No clients found', 'ndizi' ),
					'not_found_in_trash' => __( 'No clients found in Trash', 'ndizi' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
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
					'name'               => __( 'Projects', 'ndizi' ),
					'singular_name'      => __( 'Project', 'ndizi' ),
					'add_new'            => __( 'Add New Project', 'ndizi' ),
					'add_new_item'       => __( 'Add New Project', 'ndizi' ),
					'edit_item'          => __( 'Edit Project', 'ndizi' ),
					'new_item'           => __( 'New Project', 'ndizi' ),
					'view_item'          => __( 'View Project', 'ndizi' ),
					'search_items'       => __( 'Search Projects', 'ndizi' ),
					'not_found'          => __( 'No projects found', 'ndizi' ),
					'not_found_in_trash' => __( 'No projects found in Trash', 'ndizi' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
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
					'name'               => __( 'Tasks', 'ndizi' ),
					'singular_name'      => __( 'Task', 'ndizi' ),
					'add_new'            => __( 'Add New Task', 'ndizi' ),
					'add_new_item'       => __( 'Add New Task', 'ndizi' ),
					'edit_item'          => __( 'Edit Task', 'ndizi' ),
					'new_item'           => __( 'New Task', 'ndizi' ),
					'view_item'          => __( 'View Task', 'ndizi' ),
					'search_items'       => __( 'Search Tasks', 'ndizi' ),
					'not_found'          => __( 'No tasks found', 'ndizi' ),
					'not_found_in_trash' => __( 'No tasks found in Trash', 'ndizi' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-yes',
				'capability_type' => 'post',
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor', 'comments' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Invoices
		register_post_type(
			'ndizi_invoice',
			array(
				'labels'          => array(
					'name'               => __( 'Invoices', 'ndizi' ),
					'singular_name'      => __( 'Invoice', 'ndizi' ),
					'add_new'            => __( 'Add New Invoice', 'ndizi' ),
					'add_new_item'       => __( 'Add New Invoice', 'ndizi' ),
					'edit_item'          => __( 'Edit Invoice', 'ndizi' ),
					'new_item'           => __( 'New Invoice', 'ndizi' ),
					'view_item'          => __( 'View Invoice', 'ndizi' ),
					'search_items'       => __( 'Search Invoices', 'ndizi' ),
					'not_found'          => __( 'No invoices found', 'ndizi' ),
					'not_found_in_trash' => __( 'No invoices found in Trash', 'ndizi' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-analytics',
				'capability_type' => 'post',
				'hierarchical'    => false,
				'supports'        => array( 'title', 'editor' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Contacts
		register_post_type(
			'ndizi_contact',
			array(
				'labels'          => array(
					'name'               => __( 'Contacts', 'ndizi' ),
					'singular_name'      => __( 'Contact', 'ndizi' ),
					'add_new'            => __( 'Add New Contact', 'ndizi' ),
					'add_new_item'       => __( 'Add New Contact', 'ndizi' ),
					'edit_item'          => __( 'Edit Contact', 'ndizi' ),
					'new_item'           => __( 'New Contact', 'ndizi' ),
					'view_item'          => __( 'View Contact', 'ndizi' ),
					'search_items'       => __( 'Search Contacts', 'ndizi' ),
					'not_found'          => __( 'No contacts found', 'ndizi' ),
					'not_found_in_trash' => __( 'No contacts found in Trash', 'ndizi' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
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
		register_post_meta(
			'ndizi_client',
			'_ndizi_client_auth_key',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
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

		// Invoice Meta
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
