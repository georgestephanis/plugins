<?php
/**
 * Main plugin orchestrator class.
 *
 * @package GS_Support_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GS_Support_Manager class.
 */
class GS_Support_Manager {

	/**
	 * Option key for monitored plugins list.
	 */
	const PLUGINS_OPTION = 'gs_sf_monitored_plugins';

	/**
	 * Option key for plugin settings.
	 */
	const SETTINGS_OPTION = 'gs_sf_settings';

	/**
	 * Option key for feed items storage.
	 */
	const ITEMS_OPTION = 'gs_sf_feed_items';

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'gs_sf_cron_sync';

	/**
	 * Singleton instance.
	 *
	 * @var GS_Support_Manager|null
	 */
	private static $instance = null;

	/**
	 * Fetcher component.
	 *
	 * @var GS_Support_Feed_Fetcher
	 */
	public $fetcher;

	/**
	 * Notifier component.
	 *
	 * @var GS_Support_Notifier
	 */
	public $notifier;

	/**
	 * Admin UI component.
	 *
	 * @var GS_Support_Admin_UI
	 */
	public $admin_ui;

	/**
	 * REST API component.
	 *
	 * @var GS_Support_REST_API
	 */
	public $rest_api;

	/**
	 * Get singleton instance.
	 *
	 * @return GS_Support_Manager
	 */
	public static function instance(): GS_Support_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->fetcher  = new GS_Support_Feed_Fetcher();
		$this->notifier = new GS_Support_Notifier();
		$this->admin_ui = new GS_Support_Admin_UI();
		$this->rest_api = new GS_Support_REST_API();

		register_activation_hook( GS_SF_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( GS_SF_FILE, array( $this, 'deactivate' ) );

		add_action( self::CRON_HOOK, array( $this, 'run_cron_sync' ) );
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register general WordPress hooks.
	 */
	public function register_hooks(): void {
		$this->ensure_cron_scheduled();
	}

	/**
	 * Plugin activation handler.
	 */
	public function activate(): void {
		$this->get_settings(); // Ensures default settings exist.
		$this->get_monitored_plugins(); // Ensures default plugins list exists.
		$this->get_feed_items(); // Ensures items option exists.

		$this->schedule_cron();
	}

	/**
	 * Plugin deactivation handler.
	 */
	public function deactivate(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Ensure cron is scheduled based on settings.
	 */
	public function ensure_cron_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->schedule_cron();
		}
	}

	/**
	 * Schedule cron according to settings interval.
	 */
	public function schedule_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		$settings   = $this->get_settings();
		$recurrence = ! empty( $settings['sync_interval'] ) ? $settings['sync_interval'] : 'hourly';

		wp_schedule_event( time() + 300, $recurrence, self::CRON_HOOK );
	}

	/**
	 * Execute background feed synchronization.
	 *
	 * @return array Sync result summary.
	 */
	public function run_cron_sync(): array {
		return $this->fetcher->sync_all();
	}

	/**
	 * Get monitored plugins/themes list.
	 *
	 * @return array List of monitored item arrays.
	 */
	public function get_monitored_plugins(): array {
		$plugins = get_option( self::PLUGINS_OPTION, false );
		if ( false === $plugins ) {
			$plugins = array();
			update_option( self::PLUGINS_OPTION, $plugins );
		}
		return is_array( $plugins ) ? $plugins : array();
	}

	/**
	 * Save monitored plugins list.
	 *
	 * @param array $plugins List of plugin arrays.
	 * @return bool Success.
	 */
	public function save_monitored_plugins( array $plugins ): bool {
		return update_option( self::PLUGINS_OPTION, $plugins );
	}

	/**
	 * Add plugin or theme item to monitored list.
	 *
	 * @param string $slug  Item slug.
	 * @param string $type  Item type ('plugin' or 'theme').
	 * @param string $label Optional custom label.
	 * @return bool True if added or updated, false if invalid.
	 */
	public function add_monitored_item( string $slug, string $type = 'plugin', string $label = '' ): bool {
		$slug = sanitize_title( trim( $slug ) );
		if ( empty( $slug ) ) {
			return false;
		}

		$type    = 'theme' === $type ? 'theme' : 'plugin';
		$plugins = $this->get_monitored_plugins();
		if ( empty( $label ) ) {
			$label = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
		}

		$key             = $type . ':' . $slug;
		$plugins[ $key ] = array(
			'key'        => $key,
			'slug'       => $slug,
			'type'       => $type,
			'label'      => sanitize_text_field( $label ),
			'added_at'   => time(),
			'last_sync'  => 0,
			'item_count' => 0,
		);

		return $this->save_monitored_plugins( $plugins );
	}

	/**
	 * Add plugin to monitored list (backward compatibility wrapper).
	 *
	 * @param string $slug  Plugin slug.
	 * @param string $label Optional custom label.
	 * @return bool True if added or updated, false if invalid.
	 */
	public function add_monitored_plugin( string $slug, string $label = '' ): bool {
		return $this->add_monitored_item( $slug, 'plugin', $label );
	}

	/**
	 * Remove plugin or theme from monitored list.
	 *
	 * @param string $key_or_slug Monitored item key or slug.
	 * @return bool True if removed.
	 */
	public function remove_monitored_plugin( string $key_or_slug ): bool {
		$key_or_slug = sanitize_text_field( trim( $key_or_slug ) );
		$plugins     = $this->get_monitored_plugins();
		$target_slug = '';
		$target_type = '';
		$removed     = false;

		if ( isset( $plugins[ $key_or_slug ] ) ) {
			$target_slug = isset( $plugins[ $key_or_slug ]['slug'] ) ? $plugins[ $key_or_slug ]['slug'] : '';
			$target_type = isset( $plugins[ $key_or_slug ]['type'] ) ? $plugins[ $key_or_slug ]['type'] : '';
			unset( $plugins[ $key_or_slug ] );
			$removed = true;
		} else {
			foreach ( $plugins as $k => $item ) {
				if ( ( isset( $item['slug'] ) && $item['slug'] === $key_or_slug ) || $k === $key_or_slug ) {
					$target_slug = isset( $item['slug'] ) ? $item['slug'] : '';
					$target_type = isset( $item['type'] ) ? $item['type'] : '';
					unset( $plugins[ $k ] );
					$removed = true;
					break;
				}
			}
		}

		if ( $removed ) {
			$this->save_monitored_plugins( $plugins );

			if ( ! empty( $target_slug ) ) {
				$this->purge_feed_items_for_slug( $target_slug, $target_type );
			}

			return true;
		}

		return false;
	}

	/**
	 * Purge stored feed items associated with a specific plugin or theme slug.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @param string $type Optional item type ('plugin' or 'theme').
	 * @return bool True if items were purged and saved.
	 */
	public function purge_feed_items_for_slug( string $slug, string $type = '' ): bool {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return false;
		}

		$items   = $this->get_feed_items();
		$initial = count( $items );

		foreach ( $items as $id => $item ) {
			if ( isset( $item['plugin_slug'] ) && $item['plugin_slug'] === $slug ) {
				if ( empty( $type ) || ( isset( $item['item_type'] ) && $item['item_type'] === $type ) ) {
					unset( $items[ $id ] );
				}
			}
		}

		if ( count( $items ) !== $initial ) {
			return $this->save_feed_items( $items );
		}

		return false;
	}

	/**
	 * Extract WordPress.org username from a profile URL or raw username.
	 *
	 * @param string $input Profile URL or username string.
	 * @return string Sanitized username.
	 */
	public function extract_username_from_profile_url( string $input ): string {
		$input = trim( $input );
		if ( empty( $input ) ) {
			return '';
		}

		if ( false !== stripos( $input, 'profiles.wordpress.org' ) ) {
			$path     = wp_parse_url( $input, PHP_URL_PATH );
			$segments = array_values( array_filter( explode( '/', (string) $path ) ) );
			if ( ! empty( $segments[0] ) ) {
				return sanitize_user( $segments[0] );
			}
		}

		if ( 0 === strpos( $input, 'http' ) ) {
			$path     = wp_parse_url( $input, PHP_URL_PATH );
			$segments = array_values( array_filter( explode( '/', (string) $path ) ) );
			if ( ! empty( $segments ) ) {
				return sanitize_user( end( $segments ) );
			}
		}

		return sanitize_user( $input );
	}

	/**
	 * Fetch published/contributed plugins and themes for a WordPress.org profile username.
	 *
	 * @param string $username WordPress.org username.
	 * @return array Map containing 'plugins' and 'themes' lists.
	 */
	public function fetch_author_items_from_wporg( string $username ): array {
		$username = sanitize_user( $username );
		if ( empty( $username ) ) {
			return array(
				'plugins' => array(),
				'themes'  => array(),
			);
		}

		$results = array(
			'plugins' => array(),
			'themes'  => array(),
		);

		// 1. Fetch Plugins via WordPress.org Plugins API.
		$plugins_api_url = sprintf(
			'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[author]=%s&request[per_page]=100',
			rawurlencode( $username )
		);
		$plugins_res     = wp_remote_get( $plugins_api_url, array( 'timeout' => 15 ) );

		if ( ! is_wp_error( $plugins_res ) && wp_remote_retrieve_response_code( $plugins_res ) === 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $plugins_res ), true );
			if ( ! empty( $data['plugins'] ) && is_array( $data['plugins'] ) ) {
				foreach ( $data['plugins'] as $item ) {
					if ( ! empty( $item['slug'] ) ) {
						$results['plugins'][] = array(
							'slug'  => sanitize_title( $item['slug'] ),
							'label' => ! empty( $item['name'] ) ? sanitize_text_field( $item['name'] ) : $item['slug'],
						);
					}
				}
			}
		}

		// 2. Fetch Themes via WordPress.org Themes API.
		$themes_api_url = sprintf(
			'https://api.wordpress.org/themes/info/1.1/?action=query_themes&request[author]=%s&request[per_page]=100',
			rawurlencode( $username )
		);
		$themes_res     = wp_remote_get( $themes_api_url, array( 'timeout' => 15 ) );

		if ( ! is_wp_error( $themes_res ) && wp_remote_retrieve_response_code( $themes_res ) === 200 ) {
			$data = json_decode( wp_remote_retrieve_body( $themes_res ), true );
			if ( ! empty( $data['themes'] ) && is_array( $data['themes'] ) ) {
				foreach ( $data['themes'] as $item ) {
					if ( ! empty( $item['slug'] ) ) {
						$results['themes'][] = array(
							'slug'  => sanitize_title( $item['slug'] ),
							'label' => ! empty( $item['name'] ) ? sanitize_text_field( $item['name'] ) : $item['slug'],
						);
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Get plugin settings.
	 *
	 * @return array Settings map.
	 */
	public function get_settings(): array {
		$defaults = array(
			'sync_interval'    => 'hourly',
			'enable_email'     => 0,
			'email_recipients' => get_option( 'admin_email' ),
			'enable_webhook'   => 0,
			'webhook_url'      => '',
			'max_stored_items' => 500,
		);

		$settings = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
	}

	/**
	 * Save plugin settings.
	 *
	 * @param array $settings New settings map.
	 * @return bool Success.
	 */
	public function save_settings( array $settings ): bool {
		$current = $this->get_settings();
		$updated = wp_parse_args( $settings, $current );
		$saved   = update_option( self::SETTINGS_OPTION, $updated );

		// Reschedule cron if interval changed.
		if ( isset( $settings['sync_interval'] ) && $settings['sync_interval'] !== $current['sync_interval'] ) {
			$this->schedule_cron();
		}

		return $saved;
	}

	/**
	 * Get stored feed items.
	 *
	 * @return array Map of items indexed by item ID.
	 */
	public function get_feed_items(): array {
		$items = get_option( self::ITEMS_OPTION, false );
		if ( false === $items ) {
			$items = array();
			update_option( self::ITEMS_OPTION, $items );
		}
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Save feed items.
	 *
	 * @param array $items Map of feed items.
	 * @return bool Success.
	 */
	public function save_feed_items( array $items ): bool {
		$settings = $this->get_settings();
		$max      = ! empty( $settings['max_stored_items'] ) ? absint( $settings['max_stored_items'] ) : 500;

		// Sort items by pubDate desc.
		uasort(
			$items,
			function ( $a, $b ) {
				$time_a = isset( $a['pub_date'] ) ? (int) $a['pub_date'] : 0;
				$time_b = isset( $b['pub_date'] ) ? (int) $b['pub_date'] : 0;
				return $time_b <=> $time_a;
			}
		);

		// Limit to max stored items to keep option lightweight.
		if ( count( $items ) > $max ) {
			$items = array_slice( $items, 0, $max, true );
		}

		return update_option( self::ITEMS_OPTION, $items );
	}
}
