<?php
/*
Plugin Name: Ndizi Project Management
Plugin URI: https://wordpress.org/plugins/ndizi-project-management/
Description: Ndizi Project Management adds a complete project management system to WordPress.
Author: George Stephanis
Author URI: https://georgestephanis.wordpress.com
Version: 1.0.0-alpha
Requires at least: 6.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ndizi-project-management
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'NDIZI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NDIZI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NDIZI_VERSION', '1.0.0-alpha' );

/**
 * Main Plugin Class
 */
class Ndizi_Project_Management {

	/**
	 * Initialize the plugin
	 */
	public static function init() {
		// Include all core files
		self::includes();

		// Hook activation/deactivation
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

		// Run core hooks
		add_action( 'init', array( __CLASS__, 'bootstrap' ) );
	}

	/**
	 * Get list of active modules
	 *
	 * @return array Slugs of active modules.
	 */
	public static function get_active_modules() {
		$default_modules = array( 'invoicing', 'portal', 'tracker', 'notifications', 'gantt', 'integrations' );
		$active          = get_option( 'ndizi_active_modules', null );

		if ( null === $active ) {
			return $default_modules;
		}

		return (array) $active;
	}

	/**
	 * Check if a specific module is active
	 *
	 * @param string $module Module slug.
	 * @return bool True if active, false otherwise.
	 */
	public static function is_module_active( $module ) {
		return in_array( $module, self::get_active_modules(), true );
	}

	/**
	 * Read a secret option, preferring a matching PHP constant when defined.
	 *
	 * Constant naming: upper-case the option name.
	 * Example: `ndizi_stripe_secret_key` → `NDIZI_STRIPE_SECRET_KEY`.
	 *
	 * @param string $option_name The wp_options key (e.g. 'ndizi_stripe_secret_key').
	 * @return string The value from the constant, or from wp_options, or '' if unset.
	 */
	public static function get_secret( $option_name ) {
		$constant = strtoupper( $option_name );
		if ( defined( $constant ) ) {
			return (string) constant( $constant );
		}
		return (string) get_option( $option_name, '' );
	}

	/**
	 * Include plugin dependencies
	 */
	private static function includes() {
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-db.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-cpts.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-roles.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-rest.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-admin.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-cli.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-abilities.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-calendar.php';

		// Conditional modules dependencies
		if ( self::is_module_active( 'portal' ) ) {
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-portal.php';
		}
		if ( self::is_module_active( 'notifications' ) ) {
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-notifications.php';
		}
		if ( self::is_module_active( 'invoicing' ) ) {
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-integrations.php';
		}
		if ( self::is_module_active( 'tracker' ) ) {
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-admin-bar.php';
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-standalone-tracker.php';
		}
		if ( self::is_module_active( 'integrations' ) ) {
			require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-webhooks.php';
		}
	}

	/**
	 * Run on plugin activation
	 */
	public static function activate() {
		// Include DB class in case it hasn't been loaded yet
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-db.php';
		require_once NDIZI_PLUGIN_DIR . 'includes/class-ndizi-roles.php';

		// Create database tables
		Ndizi_DB::create_table();

		// Add custom roles & capabilities
		Ndizi_Roles::add_roles();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation
	 */
	public static function deactivate() {
		// Only flush rewrite rules on deactivation. Roles, capabilities, and the
		// custom table are destructive to remove and would punish a temporary
		// deactivation, so that cleanup lives in uninstall.php instead.
		flush_rewrite_rules();
	}

	/**
	 * Bootstrap hooks for loaded components
	 */
	public static function bootstrap() {
		// Load translations (kept for older WP targets; wp.org auto-loads when slug matches the text domain).
		load_plugin_textdomain( 'ndizi-project-management', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Initialize Custom Post Types & Meta
		Ndizi_CPTs::init();

		// Automated DB schema upgrade check — skip on anonymous front-end requests to avoid
		// running dbDelta() on every page load during an upgrade window.
		if ( get_option( 'ndizi_db_version' ) !== NDIZI_VERSION
			&& ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) )
		) {
			Ndizi_DB::create_table();
			update_option( 'ndizi_db_version', NDIZI_VERSION );
		}

		// Initialize Abilities API support
		Ndizi_Abilities::init();

		// Initialize CLI Commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Ndizi_CLI::init();
		}

		// Initialize REST API Routes
		Ndizi_REST::init();

		// Initialize Google Calendar Integration
		Ndizi_Calendar::init();

		// Initialize Admin Dashboards & Meta Boxes (only in wp-admin)
		if ( is_admin() ) {
			Ndizi_Admin::init();
			if ( self::is_module_active( 'tracker' ) ) {
				Ndizi_Standalone_Tracker::init();
			}
		}

		// Initialize Client Frontend Portal
		if ( self::is_module_active( 'portal' ) ) {
			Ndizi_Portal::init();
		}

		// Initialize Notifications
		if ( self::is_module_active( 'notifications' ) ) {
			Ndizi_Notifications::init();
		}

		// Initialize Integrations
		if ( self::is_module_active( 'invoicing' ) ) {
			Ndizi_Integrations::init();
		}

		// Initialize Webhooks & Slack
		if ( self::is_module_active( 'integrations' ) ) {
			Ndizi_Webhooks::init();
		}

		// Initialize Admin Bar Logger
		if ( self::is_module_active( 'tracker' ) ) {
			Ndizi_Admin_Bar::init();
		}
	}
}

// Start the plugin
Ndizi_Project_Management::init();
