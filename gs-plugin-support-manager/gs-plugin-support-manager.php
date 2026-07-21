<?php
/**
 * Plugin Name: GS Plugin Support Manager
 * Plugin URI:  https://github.com/georgestephanis/gs-plugin-support-manager
 * Description: Monitored plugin support forum aggregator for WordPress.org plugins with email and webhook notifications.
 * Version:     1.0.0
 * Author:      George Stephanis
 * Author URI:  https://stephanis.info
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gs-plugin-support-manager
 *
 * @package GS_Plugin_Support_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GS_PSM_VERSION', '1.0.0' );
define( 'GS_PSM_FILE', __FILE__ );
define( 'GS_PSM_PATH', plugin_dir_path( __FILE__ ) );
define( 'GS_PSM_URL', plugin_dir_url( __FILE__ ) );

require_once GS_PSM_PATH . 'includes/class-gs-support-manager.php';
require_once GS_PSM_PATH . 'includes/class-gs-support-feed-fetcher.php';
require_once GS_PSM_PATH . 'includes/class-gs-support-notifier.php';
require_once GS_PSM_PATH . 'includes/class-gs-support-admin-ui.php';
require_once GS_PSM_PATH . 'includes/class-gs-support-rest-api.php';

/**
 * Bootstrap the GS Plugin Support Manager plugin.
 *
 * @return GS_Support_Manager
 */
function gs_support_manager(): GS_Support_Manager {
	return GS_Support_Manager::instance();
}

gs_support_manager();
