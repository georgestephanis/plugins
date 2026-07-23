<?php
/**
 * Email and Webhook notification dispatcher class.
 *
 * @package GS_Support_Feed
 */

namespace GeorgeStephanis\GSSupportFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GS_Support_Notifier class.
 */
class GS_Support_Notifier {

	/**
	 * Process and dispatch notifications for newly discovered items.
	 *
	 * @param array $new_items List of new feed items.
	 */
	public function notify_new_items( array $new_items ): void {
		if ( empty( $new_items ) ) {
			return;
		}

		$settings = gs_support_manager()->get_settings();

		if ( ! empty( $settings['enable_email'] ) && ! empty( $settings['email_recipients'] ) ) {
			$this->send_email_notification( $new_items, $settings );
		}

		if ( ! empty( $settings['enable_webhook'] ) && ! empty( $settings['webhook_url'] ) ) {
			$this->send_webhook_notification( $new_items, $settings );
		}
	}

	/**
	 * Send email notification digest for new support items.
	 *
	 * @param array $new_items List of new items.
	 * @param array $settings Plugin settings.
	 * @return bool Success status.
	 */
	public function send_email_notification( array $new_items, array $settings ): bool {
		$raw_recipients = explode( ',', $settings['email_recipients'] );
		$recipients     = array();

		foreach ( $raw_recipients as $email ) {
			$clean = sanitize_email( trim( $email ) );
			if ( is_email( $clean ) ) {
				$recipients[] = $clean;
			}
		}

		if ( empty( $recipients ) ) {
			return false;
		}

		$count   = count( $new_items );
		$subject = sprintf(
			/* translators: 1: Site name, 2: Item count */
			__( '[%1$s] Support Manager: %2$d New Topic(s) Flagged', 'gs-support-feed' ),
			get_bloginfo( 'name' ),
			$count
		);

		$message  = '<html><body>';
		$message .= '<h2>' . esc_html(
			sprintf(
				/* translators: 1: New items count */
				__( '%d New Support Forum Topic(s) Flagged', 'gs-support-feed' ),
				$count
			)
		) . '</h2>';
		$message .= '<p>' . esc_html__( 'The following new topics were detected across your monitored plugins and themes:', 'gs-support-feed' ) . '</p>';
		$message .= '<ul style="list-style:none; padding:0;">';

		foreach ( $new_items as $item ) {
			$item_type   = ! empty( $item['item_type'] ) ? $item['item_type'] : 'plugin';
			$plugin_slug = esc_html( $item['plugin_slug'] );
			$title       = esc_html( $item['title'] );
			$link        = esc_url( $item['link'] );
			$author      = ! empty( $item['author'] ) ? esc_html( $item['author'] ) : __( 'Anonymous', 'gs-support-feed' );
			$date_str    = esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['pub_date'] ) );

			$message .= '<li style="margin-bottom: 15px; padding: 12px; border: 1px solid #ddd; border-radius: 4px; background: #fafafa;">';
			$message .= '<strong>[' . esc_html( strtoupper( $item_type ) ) . ': ' . $plugin_slug . ']</strong> ';
			$message .= '<a href="' . $link . '" target="_blank" style="font-weight:bold; font-size: 16px;">' . $title . '</a><br/>';
			$message .= '<small style="color: #666;">' . sprintf(
				/* translators: 1: Author name, 2: Date string */
				__( 'Posted by %1$s on %2$s', 'gs-support-feed' ),
				$author,
				$date_str
			) . '</small>';
			if ( ! empty( $item['description'] ) ) {
				$message .= '<div style="margin-top: 8px; font-size: 13px; color: #333;">' . wp_kses_post( wp_trim_words( $item['description'], 40 ) ) . '</div>';
			}
			$message .= '</li>';
		}

		$message .= '</ul>';
		$message .= '<p><a href="' . esc_url( admin_url( 'tools.php?page=gs-support-feed' ) ) . '">' . esc_html__( 'View Unified Feed in WordPress Dashboard', 'gs-support-feed' ) . '</a></p>';
		$message .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		return wp_mail( $recipients, $subject, $message, $headers );
	}

	/**
	 * Send webhook JSON notification payload for new support items.
	 *
	 * @param array $new_items List of new items.
	 * @param array $settings Plugin settings.
	 * @return bool Success status.
	 */
	public function send_webhook_notification( array $new_items, array $settings ): bool {
		$url = esc_url_raw( $settings['webhook_url'] );
		if ( empty( $url ) || ! gs_support_manager()->is_safe_webhook_url( $url ) ) {
			return false;
		}

		$formatted_items = array();
		foreach ( $new_items as $item ) {
			$formatted_items[] = array(
				'id'          => $item['id'],
				'item_type'   => ! empty( $item['item_type'] ) ? $item['item_type'] : 'plugin',
				'plugin_slug' => $item['plugin_slug'],
				'title'       => $item['title'],
				'link'        => $item['link'],
				'author'      => $item['author'],
				'pub_date'    => gmdate( 'c', $item['pub_date'] ),
				'description' => wp_strip_all_tags( $item['description'] ),
			);
		}

		$payload = array(
			'event'     => 'gs_support_manager_new_items',
			'site_name' => get_bloginfo( 'name' ),
			'site_url'  => home_url(),
			'timestamp' => gmdate( 'c' ),
			'count'     => count( $formatted_items ),
			'items'     => $formatted_items,
		);

		$args = array(
			'body'     => wp_json_encode( $payload ),
			'headers'  => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => 'GS-Support-Feed/' . GS_SF_VERSION,
			),
			'timeout'  => 15,
			'blocking' => true,
		);

		$response = wp_safe_remote_post( $url, $args );

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;
	}
}
