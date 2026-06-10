<?php
/**
 * Plugin Name: Simple 404 Keyword Insertion
 * Plugin URI:  http://wordpress.org/extend/plugins/simple-404-keyword-insertion/
 * Description: This builds a custom 404 page based off the request string.
 * Author:      George Stephanis
 * Author URI:  https://georgestephanis.wordpress.com
 * Version:     1.0.1
 * License:     GPL-2.0-or-later
 * Text Domain: simple-404-keyword-insertion
 *
 * @package Simple_404_Keyword_Insertion
 */

if ( ! class_exists( 'Simple_404_Keyword_Insertion' ) ) :

	/**
	 * Main plugin class for Simple 404 Keyword Insertion.
	 */
	class Simple_404_Keyword_Insertion {

		/**
		 * Register hooks.
		 */
		public static function go() {
			add_filter( '404_template', array( __CLASS__, 'filter_404_template' ) );
		}

		/**
		 * Handle the 404 template filter.
		 *
		 * Intentionally sends a 200 status (soft 404) so that the keyword-injection
		 * page is served and indexed rather than returning a true 404 response.
		 *
		 * @param string $template The current template path chosen by WordPress.
		 * @return string Template path to use for rendering.
		 */
		public static function filter_404_template( $template ) {
			// Intentional soft 404: return 200 so the keyword-injection page is served.
			status_header( 200 );

			// Override the global $wp_query so the template's main loop renders the 404-page post.
			global $wp_query;
			$wp_query = new WP_Query( array( 'pagename' => '404-page' ) ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $wp_query->have_posts() ) {
				$wp_query->the_post();
			}

			add_shortcode( '404-keywords', array( __CLASS__, 'get_keywords' ) );

			$located = locate_template( array( 'page.php', 'index.php' ) );
			return $located ? $located : $template;
		}

		/**
		 * Shortcode callback: returns sanitised keywords derived from the request URI.
		 *
		 * @return string Space-separated keywords, HTML-escaped for safe output.
		 */
		public static function get_keywords() {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$k           = ' ' . trim( preg_replace( '/\W/', ' ', urldecode( $request_uri ) ) ) . ' ';
			return esc_html( $k );
		}

		/**
		 * Activation hook: create the 404-page placeholder if it does not exist.
		 */
		public static function activate() {
			$existing = get_page_by_path( '404-page', OBJECT, 'page' );

			if ( ! $existing ) {
				$current_user = wp_get_current_user();
				wp_insert_post(
					array(
						'post_title'   => '[404-keywords]',
						'post_content' => 'Here are the keywords: `[404-keywords]`.  Wow, isn&rsquo;t that neato?',
						'post_status'  => 'publish',
						'post_author'  => $current_user->ID,
						'post_type'    => 'page',
						'post_name'    => '404-page',
					)
				);
			}
		}
	}

	Simple_404_Keyword_Insertion::go();
	register_activation_hook( __FILE__, array( 'Simple_404_Keyword_Insertion', 'activate' ) );

endif;
