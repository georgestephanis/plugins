<?php
/*
Plugin Name: Ndizi Project Management
Plugin URI: https://wordpress.org/plugins/ndizi-project-management/
Description: Ndizi Project Management adds a complete project management system to WordPress.
Author: George Stephanis
Author URI: https://georgestephanis.wordpress.com
Version: 1.0.0-alpha.2
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
define( 'NDIZI_VERSION', '1.0.0-alpha.2' );

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
	 * Get the module registry.
	 *
	 * @return array[] Declarative module configuration.
	 */
	public static function get_module_registry() {
		return array(
			'invoicing'     => array(
				'name'        => __( 'Invoicing & Billing', 'ndizi-project-management' ),
				'desc'        => __( 'Invoice CPT, printable template, CSV/JSON and QuickBooks exports, and Stripe online payment.', 'ndizi-project-management' ),
				'includes'    => array(
					'includes/class-ndizi-invoicing.php',
				),
				'init'        => array( 'Ndizi_Invoicing', 'init' ),
				'rest_routes' => array( 'Ndizi_REST', 'register_invoicing_routes' ),
			),
			'portal'        => array(
				'name'     => __( 'Client Portal', 'ndizi-project-management' ),
				'desc'     => __( 'Enables frontend portal block and shortcodes for client reviews, task updates, and comments.', 'ndizi-project-management' ),
				'includes' => array(
					'includes/class-ndizi-portal.php',
				),
				'init'     => array( 'Ndizi_Portal', 'init' ),
			),
			'tracker'       => array(
				'name'     => __( 'Admin Bar & Quick Tracker', 'ndizi-project-management' ),
				'desc'     => __( 'Adds the admin bar quick-timer toggle and a dedicated quick-tracker logger page.', 'ndizi-project-management' ),
				'includes' => array(
					'includes/class-ndizi-admin-bar.php',
					'includes/class-ndizi-standalone-tracker.php',
				),
				'init'     => array(
					array( 'Ndizi_Admin_Bar', 'init' ),
					array( 'Ndizi_Standalone_Tracker', 'init' ),
				),
			),
			'notifications' => array(
				'name'     => __( 'Email Notifications', 'ndizi-project-management' ),
				'desc'     => __( 'Sends automated email notifications when tasks are assigned or their status changes.', 'ndizi-project-management' ),
				'includes' => array(
					'includes/class-ndizi-notifications.php',
				),
				'init'     => array( 'Ndizi_Notifications', 'init' ),
			),
			'gantt'         => array(
				'name'     => __( 'Gantt Timeline Charts', 'ndizi-project-management' ),
				'desc'     => __( 'Provides interactive timelines for project scheduling and visually tracking completion status.', 'ndizi-project-management' ),
				'includes' => array(),
				'init'     => array( 'Ndizi_Admin', 'init_gantt' ),
			),
			'integrations'  => array(
				'name'     => __( 'Webhooks & Slack Integrations', 'ndizi-project-management' ),
				'desc'     => __( 'Sends outbound JSON payloads and formatted Slack alerts when time is logged, tasks change, or invoices transition.', 'ndizi-project-management' ),
				'includes' => array(
					'includes/class-ndizi-webhooks.php',
				),
				'init'     => array( 'Ndizi_Webhooks', 'init' ),
			),
			'calendar'      => array(
				'name'        => __( 'Google Calendar Sync', 'ndizi-project-management' ),
				'desc'        => __( 'Sync due tasks and project milestones with Google Calendar.', 'ndizi-project-management' ),
				'includes'    => array(
					'includes/class-ndizi-calendar.php',
				),
				'init'        => array( 'Ndizi_Calendar', 'init' ),
				'rest_routes' => array( 'Ndizi_REST', 'register_calendar_routes' ),
			),
		);
	}

	/**
	 * Get list of active modules
	 *
	 * @return array Slugs of active modules.
	 */
	public static function get_active_modules() {
		$all_modules = array_keys( self::get_module_registry() );
		$active      = get_option( 'ndizi_active_modules', null );

		if ( null === $active ) {
			return $all_modules;
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

		// Dynamic module dependency loading
		$registry = self::get_module_registry();
		foreach ( self::get_active_modules() as $module ) {
			if ( isset( $registry[ $module ] ) && ! empty( $registry[ $module ]['includes'] ) ) {
				foreach ( $registry[ $module ]['includes'] as $file ) {
					require_once NDIZI_PLUGIN_DIR . $file;
				}
			}
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

			// Activate any registry modules not yet in the stored option so new
			// modules (e.g. 'calendar') default to on for existing installs.
			// Runs once per version bump; user toggles made after this are preserved.
			$stored_modules = get_option( 'ndizi_active_modules', null );
			if ( null !== $stored_modules ) {
				$new_modules = array_diff( array_keys( self::get_module_registry() ), (array) $stored_modules );
				if ( ! empty( $new_modules ) ) {
					update_option( 'ndizi_active_modules', array_merge( (array) $stored_modules, $new_modules ) );
				}
			}

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

		// Initialize Admin Dashboards & Meta Boxes (only in wp-admin)
		if ( is_admin() ) {
			Ndizi_Admin::init();
		}

		// Dynamically bootstrap active modules
		$registry = self::get_module_registry();
		foreach ( self::get_active_modules() as $module ) {
			if ( ! isset( $registry[ $module ] ) ) {
				continue;
			}
			$mod = $registry[ $module ];
			if ( ! empty( $mod['init'] ) ) {
				// If the init property is a single valid callable, wrap it in an array for consistent processing.
				// Otherwise, assume it's already an array of callables.
				$callbacks = is_callable( $mod['init'] ) ? array( $mod['init'] ) : $mod['init'];

				foreach ( (array) $callbacks as $callback ) {
					if ( is_callable( $callback ) ) {
						call_user_func( $callback );
					}
				}
			}
		}
	}

	/**
	 * Register REST routes for all active modules
	 */
	public static function register_active_rest_routes() {
		$registry = self::get_module_registry();
		foreach ( self::get_active_modules() as $module ) {
			if ( ! isset( $registry[ $module ] ) ) {
				continue;
			}
			$mod = $registry[ $module ];
			if ( ! empty( $mod['rest_routes'] ) && is_callable( $mod['rest_routes'] ) ) {
				call_user_func( $mod['rest_routes'] );
			}
		}
	}
}

// Start the plugin
Ndizi_Project_Management::init();
