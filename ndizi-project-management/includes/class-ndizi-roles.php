<?php
/**
 * Role and capability management for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Roles {

	/**
	 * Register custom roles on activation
	 */
	public static function add_roles() {
		// Define manager capabilities (can manage all client projects and view reports)
		$manager_caps = array(
			'read'                  => true,
			'edit_posts'            => true,
			'publish_posts'         => true,
			'delete_posts'          => true,
			'edit_others_posts'     => true,
			'delete_others_posts'   => true,
			'upload_files'          => true,
			'ndizi_manage_clients'  => true,
			'ndizi_manage_projects' => true,
			'ndizi_manage_tasks'    => true,
			'ndizi_manage_invoices' => true,
			'ndizi_manage_contacts' => true,
			'ndizi_manage_time'     => true,
			'ndizi_view_reports'    => true,
		);

		// Define team member capabilities (can view projects/tasks, and log their own time)
		$team_member_caps = array(
			'read'                => true,
			'upload_files'        => true,
			'ndizi_view_projects' => true,
			'ndizi_view_tasks'    => true,
			'ndizi_log_time'      => true,
		);

		// Add custom roles
		add_role( 'ndizi_manager', __( 'Ndizi Manager', 'ndizi-project-management' ), $manager_caps );
		add_role( 'ndizi_team_member', __( 'Ndizi Team Member', 'ndizi-project-management' ), $team_member_caps );

		// Give all capabilities to Administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $manager_caps as $cap => $value ) {
				$admin->add_cap( $cap );
			}
			$admin->add_cap( 'ndizi_view_projects' );
			$admin->add_cap( 'ndizi_view_tasks' );
			$admin->add_cap( 'ndizi_log_time' );
		}
	}

	/**
	 * Remove custom roles on deactivation
	 */
	public static function remove_roles() {
		// Remove capabilities from administrator
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$caps_to_remove = array(
				'ndizi_manage_clients',
				'ndizi_manage_projects',
				'ndizi_manage_tasks',
				'ndizi_manage_invoices',
				'ndizi_manage_contacts',
				'ndizi_manage_time',
				'ndizi_view_reports',
				'ndizi_view_projects',
				'ndizi_view_tasks',
				'ndizi_log_time',
			);
			foreach ( $caps_to_remove as $cap ) {
				$admin->remove_cap( $cap );
			}
		}

		// Remove roles
		remove_role( 'ndizi_manager' );
		remove_role( 'ndizi_team_member' );
	}

	/**
	 * Check if user can perform an action based on capabilities
	 */
	public static function current_user_can( $capability ) {
		// Admins can always do everything
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return current_user_can( $capability );
	}
}
