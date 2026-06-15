<?php
/**
 * Uninstall routine for Ndizi Project Management.
 *
 * Runs only when the user deletes the plugin from the WordPress admin. Removes
 * the structures the plugin created on activation: the custom roles/capabilities
 * and the custom time-entries table.
 *
 * Note: Client/Project/Task/Invoice/Contact posts are standard custom post type
 * content and are intentionally left in place so that uninstalling does not
 * silently destroy the user's business records.
 *
 * @package Ndizi_Project_Management
 */

// Exit if accessed directly or not invoked by WordPress as an uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ndizi-roles.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-ndizi-db.php';

// Remove the custom roles and the capabilities granted to the administrator role.
Ndizi_Roles::remove_roles();

// Drop the custom time-entries table.
global $wpdb;
$ndizi_table = Ndizi_DB::get_table_name();

// Table name is derived from $wpdb->prefix and a fixed suffix; it cannot be
// parameterized via prepare(). This runs once, only during uninstall.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS `{$ndizi_table}`" );
