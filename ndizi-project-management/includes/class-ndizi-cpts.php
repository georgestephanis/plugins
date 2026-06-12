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
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_block_editor' ), 10, 2 );
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
					'edit_post'              => 'ndizi_manage_clients',
					'read_post'              => 'read',
					'delete_post'            => 'ndizi_manage_clients',
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
				'capabilities'    => array(
					'edit_post'              => 'ndizi_manage_projects',
					'read_post'              => 'read',
					'delete_post'            => 'ndizi_manage_projects',
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
				'capabilities'    => array(
					'edit_post'              => 'ndizi_manage_tasks',
					'read_post'              => 'read',
					'delete_post'            => 'ndizi_manage_tasks',
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
					'capabilities'    => array(
						'edit_post'              => 'ndizi_manage_invoices',
						'read_post'              => 'read',
						'delete_post'            => 'ndizi_manage_invoices',
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
				'capabilities'    => array(
					'edit_post'              => 'ndizi_manage_contacts',
					'read_post'              => 'read',
					'delete_post'            => 'ndizi_manage_contacts',
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
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest'    => true,
				'has_archive'     => false,
			)
		);

		// Time Off Request
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
					'edit_post'              => 'ndizi_manage_time',
					'read_post'              => 'read',
					'delete_post'            => 'ndizi_manage_time',
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
				'supports'        => array( 'title', 'editor' ),
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

		// Time Off Meta
		register_post_meta(
			'ndizi_time_off',
			'_ndizi_time_off_start_date',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_time_off',
			'_ndizi_time_off_end_date',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_time_off',
			'_ndizi_time_off_type',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_time_off',
			'_ndizi_time_off_status',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'string',
			)
		);
		register_post_meta(
			'ndizi_time_off',
			'_ndizi_time_off_user_id',
			array(
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'integer',
			)
		);
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
}
