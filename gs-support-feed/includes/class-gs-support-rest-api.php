<?php
/**
 * REST API feed provider class.
 *
 * @package GS_Support_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GS_Support_REST_API class.
 */
class GS_Support_REST_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'gs-support-feed/v1',
			'/feed',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_aggregated_feed' ),
				'permission_callback' => array( $this, 'check_feed_permissions' ),
				'args'                => array(
					'format'   => array(
						'default'           => 'rss',
						'sanitize_callback' => 'sanitize_key',
					),
					'type'     => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'plugin'   => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_title',
					),
					'limit'    => array(
						'default'           => 50,
						'sanitize_callback' => 'absint',
					),
					'feed_key' => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'secret'   => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'key'      => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Check if the request has permission to view the feed.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access, false or WP_Error otherwise.
	 */
	public function check_feed_permissions( WP_REST_Request $request ) {
		// 1. Allow if the current user has manage_options capability.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// 2. Allow if the correct feed key is provided.
		$manager  = gs_support_manager();
		$settings = $manager->get_settings();
		$feed_key = isset( $settings['feed_key'] ) ? $settings['feed_key'] : '';

		if ( empty( $feed_key ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Feed key is not configured.', 'gs-support-feed' ),
				array( 'status' => 403 )
			);
		}

		$provided_key = $request->get_param( 'feed_key' );
		if ( empty( $provided_key ) ) {
			$provided_key = $request->get_param( 'secret' );
		}
		if ( empty( $provided_key ) ) {
			$provided_key = $request->get_param( 'key' );
		}

		if ( hash_equals( $feed_key, (string) $provided_key ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to access this feed. Please provide a valid feed key.', 'gs-support-feed' ),
			array( 'status' => 403 )
		);
	}


	/**
	 * Output or return aggregated feed in RSS or JSON format.
	 *
	 * @param WP_REST_Request $request REST request instance.
	 * @return WP_REST_Response|void
	 */
	public function get_aggregated_feed( WP_REST_Request $request ) {
		$manager  = gs_support_manager();
		$format   = $request->get_param( 'format' );
		$type_flt = $request->get_param( 'type' );
		$plugin   = $request->get_param( 'plugin' );
		$limit    = $request->get_param( 'limit' );
		$limit    = $limit ? min( max( (int) $limit, 1 ), 100 ) : 50;

		$all_items = $manager->get_feed_items();
		$filtered  = array();

		foreach ( $all_items as $item ) {
			$item_type = ! empty( $item['item_type'] ) ? $item['item_type'] : 'plugin';
			if ( ! empty( $type_flt ) && $item_type !== $type_flt ) {
				continue;
			}
			if ( ! empty( $plugin ) && $item['plugin_slug'] !== $plugin ) {
				continue;
			}
			$filtered[] = $item;
			if ( count( $filtered ) >= $limit ) {
				break;
			}
		}

		if ( 'json' === $format ) {
			return new WP_REST_Response(
				array(
					'status' => 'success',
					'total'  => count( $filtered ),
					'items'  => $filtered,
				),
				200
			);
		}

		// Output RSS XML.
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );

		$blog_name        = esc_xml( get_bloginfo( 'name' ) );
		$feed_title       = sprintf( '%s - Monitored Plugin & Theme Support Feed', $blog_name );
		$feed_link        = esc_url( home_url( '/wp-json/gs-support-feed/v1/feed' ) );
		$feed_description = esc_xml( __( 'Unified RSS support forum feed for monitored WordPress.org plugins and themes.', 'gs-support-feed' ) );

		echo '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
		echo '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">' . "\n";
		echo '<channel>' . "\n";
		echo '  <title>' . esc_xml( $feed_title ) . '</title>' . "\n";
		echo '  <link>' . esc_xml( $feed_link ) . '</link>' . "\n";
		echo '  <description>' . esc_xml( $feed_description ) . '</description>' . "\n";
		echo '  <pubDate>' . esc_xml( gmdate( 'r' ) ) . '</pubDate>' . "\n";

		foreach ( $filtered as $item ) {
			$item_type = ! empty( $item['item_type'] ) ? $item['item_type'] : 'plugin';
			$title     = sprintf( '[%s: %s] %s', strtoupper( $item_type ), strtoupper( $item['plugin_slug'] ), $item['title'] );
			$pub_date  = gmdate( 'r', $item['pub_date'] );

			echo '  <item>' . "\n";
			echo '    <title>' . esc_xml( $title ) . '</title>' . "\n";
			echo '    <link>' . esc_xml( $item['link'] ) . '</link>' . "\n";
			echo '    <guid isPermaLink="false">' . esc_xml( $item['id'] ) . '</guid>' . "\n";
			echo '    <pubDate>' . esc_xml( $pub_date ) . '</pubDate>' . "\n";
			if ( ! empty( $item['author'] ) ) {
				echo '    <dc:creator>' . esc_xml( $item['author'] ) . '</dc:creator>' . "\n";
			}
			if ( ! empty( $item['description'] ) ) {
				$description = str_replace( ']]>', ']]&gt;', $item['description'] );
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CDATA content escaped via str_replace for CDATA closing tag.
				echo '    <description><![CDATA[' . $description . ']]></description>' . "\n";
			}
			echo '  </item>' . "\n";
		}

		echo '</channel>' . "\n";
		echo '</rss>';
		exit;
	}
}
