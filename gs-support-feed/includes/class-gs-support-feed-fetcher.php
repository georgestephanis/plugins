<?php
/**
 * Feed fetcher and RSS parser class.
 *
 * @package GS_Support_Feed
 */

namespace GeorgeStephanis\GSSupportFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GS_Support_Feed_Fetcher class.
 */
class GS_Support_Feed_Fetcher {

	/**
	 * Build WordPress.org support forum RSS feed URL for a plugin or theme.
	 *
	 * @param string $slug Item slug.
	 * @param string $type Item type ('plugin' or 'theme').
	 * @return string Feed URL.
	 */
	public function get_feed_url( string $slug, string $type = 'plugin' ): string {
		$slug     = sanitize_title( $slug );
		$endpoint = 'theme' === $type ? 'theme' : 'plugin';
		return sprintf( 'https://wordpress.org/support/%s/%s/feed/', $endpoint, $slug );
	}

	/**
	 * Fetch and parse RSS feed for a single plugin or theme.
	 *
	 * @param string $slug Item slug.
	 * @param string $type Item type ('plugin' or 'theme').
	 * @return array List of parsed feed item arrays.
	 */
	public function fetch_plugin_feed( string $slug, string $type = 'plugin' ): array {
		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$url = $this->get_feed_url( $slug, $type );

		// Temporarily reduce feed cache lifetime to 300s for fresh sync.
		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'custom_feed_lifetime' ) );

		$rss = fetch_feed( $url );

		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'custom_feed_lifetime' ) );

		if ( is_wp_error( $rss ) ) {
			return array();
		}

		$maxitems  = $rss->get_item_quantity( 30 );
		$rss_items = $rss->get_items( 0, $maxitems );
		$parsed    = array();

		foreach ( $rss_items as $item ) {
			$title       = (string) $item->get_title();
			$link        = (string) $item->get_permalink();
			$guid        = $item->get_id() ? (string) $item->get_id() : $link;
			$id          = md5( $type . '_' . $slug . '_' . $guid );
			$date        = $item->get_date( 'U' );
			$pub_date    = $date ? (int) $date : time();
			$description = (string) $item->get_description();

			$author_name = '';
			$author      = $item->get_author();
			if ( $author ) {
				$author_name = (string) $author->get_name();
			}

			$parsed[ $id ] = array(
				'id'          => $id,
				'guid'        => esc_url_raw( $guid ),
				'plugin_slug' => sanitize_title( $slug ),
				'item_type'   => 'theme' === $type ? 'theme' : 'plugin',
				'title'       => sanitize_text_field( $title ),
				'link'        => esc_url_raw( $link ),
				'author'      => sanitize_text_field( $author_name ),
				'pub_date'    => $pub_date,
				'description' => wp_kses_post( $description ),
				'read'        => false,
				'first_seen'  => time(),
			);
		}

		return $parsed;
	}

	/**
	 * Feed cache lifetime filter callback.
	 *
	 * @return int Lifetime in seconds.
	 */
	public function custom_feed_lifetime(): int {
		return 300;
	}

	/**
	 * Synchronize all monitored plugins and themes.
	 *
	 * @return array Sync statistics.
	 */
	public function sync_all(): array {
		$manager      = gs_support_manager();
		$plugins      = $manager->get_monitored_plugins();
		$existing     = $manager->get_feed_items();
		$new_items    = array();
		$synced_count = 0;

		foreach ( $plugins as $key => &$plugin_data ) {
			$type    = ! empty( $plugin_data['type'] ) ? $plugin_data['type'] : 'plugin';
			$slug    = ! empty( $plugin_data['slug'] ) ? $plugin_data['slug'] : $key;
			$fetched = $this->fetch_plugin_feed( $slug, $type );
			++$synced_count;
			$plugin_item_count = 0;

			foreach ( $fetched as $item_id => $item ) {
				if ( ! isset( $existing[ $item_id ] ) ) {
					// Brand new item discovered!
					$existing[ $item_id ]  = $item;
					$new_items[ $item_id ] = $item;
				} else {
					// Update description/title if changed, preserve read status & first_seen.
					$existing[ $item_id ]['title']       = $item['title'];
					$existing[ $item_id ]['description'] = $item['description'];
				}
				++$plugin_item_count;
			}

			$plugin_data['last_sync']  = time();
			$plugin_data['item_count'] = $plugin_item_count;
		}
		unset( $plugin_data );

		$manager->save_monitored_plugins( $plugins );
		$manager->save_feed_items( $existing );

		if ( ! empty( $new_items ) ) {
			$manager->notifier->notify_new_items( $new_items );
		}

		return array(
			'plugins_synced'  => $synced_count,
			'new_items_found' => count( $new_items ),
			'total_items'     => count( $existing ),
		);
	}
}
