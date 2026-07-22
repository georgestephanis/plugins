<?php
/**
 * Uninstall handler for GS Support Feed.
 *
 * Deletes all plugin options and settings when uninstalled via WP Admin.
 *
 * @package GS_Support_Feed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gs_sf_monitored_plugins' );
delete_option( 'gs_sf_settings' );
delete_option( 'gs_sf_feed_items' );

$timestamp = wp_next_scheduled( 'gs_sf_cron_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'gs_sf_cron_sync' );
}
