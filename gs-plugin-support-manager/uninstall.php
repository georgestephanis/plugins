<?php
/**
 * Uninstall handler for GS Plugin Support Manager.
 *
 * Deletes all plugin options and settings when uninstalled via WP Admin.
 *
 * @package GS_Plugin_Support_Manager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gs_psm_monitored_plugins' );
delete_option( 'gs_psm_settings' );
delete_option( 'gs_psm_feed_items' );

$timestamp = wp_next_scheduled( 'gs_psm_cron_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'gs_psm_cron_sync' );
}
