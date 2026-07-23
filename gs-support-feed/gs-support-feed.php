<?php
/**
 * Plugin Name: GS Support Feed
 * Plugin URI:  https://github.com/georgestephanis/plugins/tree/main/gs-support-feed
 * Description: Monitored plugin support forum aggregator for WordPress.org plugins with email and webhook notifications.
 * Version:     1.0.0
 * Author:      George Stephanis
 * Author URI:  https://stephanis.info
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gs-support-feed
 *
 * @package GS_Support_Feed
 */

namespace GeorgeStephanis\GSSupportFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GS_SF_VERSION', '1.0.0' );
define( 'GS_SF_FILE', __FILE__ );
define( 'GS_SF_PATH', plugin_dir_path( __FILE__ ) );
define( 'GS_SF_URL', plugin_dir_url( __FILE__ ) );

require_once GS_SF_PATH . 'includes/class-gs-support-manager.php';
require_once GS_SF_PATH . 'includes/class-gs-support-feed-fetcher.php';
require_once GS_SF_PATH . 'includes/class-gs-support-notifier.php';
require_once GS_SF_PATH . 'includes/class-gs-support-admin-ui.php';
require_once GS_SF_PATH . 'includes/class-gs-support-rest-api.php';

/**
 * Bootstrap the GS Support Feed plugin.
 *
 * @return GS_Support_Manager
 */
function gs_support_manager(): GS_Support_Manager {
	return GS_Support_Manager::instance();
}

gs_support_manager();
