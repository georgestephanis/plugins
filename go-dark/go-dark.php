<?php
/**
 * Plugin Name: Go Dark
 * Plugin URI:  https://wordpress.org/plugins/go-dark/
 * Description: plugin enables websites to 'go dark' on January 18th to protest SOPA/PIPA and Internet Censorship
 * Author:      George Stephanis
 * Author URI:  https://georgestephanis.wordpress.com
 * Version:     1.0.7
 * Text Domain: go-dark
 *
 * @package go-dark
 */

if ( ! class_exists( 'go_dark' ) ) :

	/**
	 * Go Dark main plugin class.
	 */
	class go_dark {

		/**
		 * Bootstrap all hooks.
		 *
		 * @return void
		 */
		public static function go() {
			add_action( 'init', array( __CLASS__, 'init' ) );
		}

		/**
		 * Register hooks on init.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
			if ( ! is_admin() && ! is_feed() ) {
				if ( isset( $_GET['go_dark'] ) || self::is_dark() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					add_action( 'template_redirect', array( __CLASS__, 'show_page' ) );
				}
			}
		}

		/**
		 * Register the admin menu item.
		 *
		 * @return void
		 */
		public static function add_admin_menu() {
			add_menu_page(
				'Go Dark',
				'Go Dark',
				'manage_options',
				'go-dark',
				array( __CLASS__, 'page_go_dark' ),
				'dashicons-hidden'
			);
		}

		/**
		 * Render the admin settings page.
		 *
		 * @return void
		 */
		public static function page_go_dark() {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				self::catch_post();
			}
			?>
		<div class="wrap">
			<h2>Go Dark &mdash; <a href="<?php echo esc_url( home_url( '/?go_dark' ) ); ?>">Display</a></h2>
			<?php if ( self::is_dark() ) : ?>
			<div class="notice notice-warning"><p><strong><?php esc_html_e( 'Your website is currently dark.', 'go-dark' ); ?></strong></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'These settings control the go-dark behavior.', 'go-dark' ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'go_dark_settings', 'go_dark_nonce' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Current time', 'go-dark' ); ?></th>
						<td>
							<?php echo esc_html( wp_date( 'Y/m/d H:i:s' ) ); ?>
							<p class="description">
							<?php
							printf(
								/* translators: %s: timezone identifier, e.g. "America/New_York" */
								esc_html__( 'All times use your site\'s configured timezone (%s).', 'go-dark' ),
								esc_html( wp_timezone_string() )
							);
							?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="go_dark_start"><?php esc_html_e( 'Start', 'go-dark' ); ?></label>
						</th>
						<td>
							<input class="regular-text" type="text" name="go_dark_start" id="go_dark_start"
								value="<?php echo esc_attr( wp_date( 'Y/m/d H:i:s', self::get_start() ) ); ?>"
								placeholder="YYYY/MM/DD HH:MM:SS" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="go_dark_end"><?php esc_html_e( 'End', 'go-dark' ); ?></label>
						</th>
						<td>
							<input class="regular-text" type="text" name="go_dark_end" id="go_dark_end"
								value="<?php echo esc_attr( wp_date( 'Y/m/d H:i:s', self::get_end() ) ); ?>"
								placeholder="YYYY/MM/DD HH:MM:SS" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="go_dark_img"><?php esc_html_e( 'Image', 'go-dark' ); ?></label>
						</th>
						<td>
							<select name="go_dark_img" id="go_dark_img">
								<option value="none" <?php selected( get_option( 'go_dark_img', 'none' ), 'none' ); ?>><?php esc_html_e( 'None', 'go-dark' ); ?></option>
								<option value="sign" <?php selected( get_option( 'go_dark_img', 'none' ), 'sign' ); ?>><?php esc_html_e( 'Sign', 'go-dark' ); ?></option>
								<option value="seal" <?php selected( get_option( 'go_dark_img', 'none' ), 'seal' ); ?>><?php esc_html_e( 'Seal', 'go-dark' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="go_dark_text"><?php esc_html_e( 'Text', 'go-dark' ); ?></label>
						</th>
						<td>
							<?php
							wp_editor(
								get_option( 'go_dark_text', self::get_default_text() ),
								'go_dark_text',
								array(
									'textarea_rows' => 5,
								)
							);
							?>
						</td>
					</tr>
				</table>
					<?php submit_button(); ?>
			</form>
		</div>
			<?php
		}

		/**
		 * Process the settings form POST.
		 *
		 * @return void
		 */
		public static function catch_post() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-dark' ) );
			}

			if ( ! isset( $_POST['go_dark_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['go_dark_nonce'] ), 'go_dark_settings' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'go-dark' ) );
			}

			if ( isset( $_POST['go_dark_start'] ) ) {
				$tz = wp_timezone();
				$dt = date_create( sanitize_text_field( wp_unslash( $_POST['go_dark_start'] ) ), $tz );
				if ( $dt ) {
					update_option( 'go_dark_start', $dt->getTimestamp() );
				}
			}

			if ( isset( $_POST['go_dark_end'] ) ) {
				$tz = wp_timezone();
				$dt = date_create( sanitize_text_field( wp_unslash( $_POST['go_dark_end'] ) ), $tz );
				if ( $dt ) {
					update_option( 'go_dark_end', $dt->getTimestamp() );
				}
			}

			$allowed_images = array( 'none', 'sign', 'seal' );
			$img            = isset( $_POST['go_dark_img'] ) ? sanitize_text_field( wp_unslash( $_POST['go_dark_img'] ) ) : 'none';
			if ( ! in_array( $img, $allowed_images, true ) ) {
				$img = 'none';
			}
			update_option( 'go_dark_img', $img );

			if ( isset( $_POST['go_dark_text'] ) ) {
				update_option( 'go_dark_text', wp_kses( wp_unslash( $_POST['go_dark_text'] ), self::get_allowed_tags() ) );
			}
		}

		/**
		 * Output the go-dark splash page and exit.
		 *
		 * @return void
		 */
		public static function show_page() {
			$font    = 'Just Another Hand';
			$img_opt = get_option( 'go_dark_img', 'none' );

			if ( ! headers_sent() ) {
				status_header( 503 );
				$time_left = self::get_end() - time();
				if ( $time_left > 0 ) {
					header( 'Retry-After: ' . $time_left );
				}
			}

			?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<title><?php esc_html_e( 'Site Temporarily Unavailable', 'go-dark' ); ?></title>
		<link href="<?php echo esc_url( 'https://fonts.googleapis.com/css?family=' . rawurlencode( $font ) ); ?>" rel="stylesheet" type="text/css"> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Custom standalone 503 page; wp_head() is not called. ?>
		<style>
		* { margin:0; padding:0; }
		html {
			min-height:100%; width:100%;
			text-shadow:1px 1px 0 #222;
			font-family:'<?php echo esc_html( $font ); ?>', cursive;
			font-size:24px; color:#eee;
		}
		html {
			background:#111 url('<?php echo esc_url( plugins_url( 'wood.jpg', __FILE__ ) ); ?>') 50% 50% repeat;
		}
		body { display:table; height:100%; width:100%; }
		#blocked { display:table-cell; text-align:center; vertical-align:middle; }
		</style>
		</head>
		<body>
		<div id="blocked">
			<?php
			switch ( $img_opt ) {
				case 'sign':
					echo '<img src="' . esc_url( self::get_img( 'sign' ) ) . '" alt="' . esc_attr__( 'Gone Dark', 'go-dark' ) . '" /><br />';
					break;
				case 'seal':
					echo '<img src="' . esc_url( self::get_img( 'seal' ) ) . '" alt="' . esc_attr__( 'Gone Dark', 'go-dark' ) . '" /><br />';
					break;
			}
			echo wp_kses( get_option( 'go_dark_text', self::get_default_text() ), self::get_allowed_tags() );
			?>
		</div>
		</body>
		</html>
			<?php
			exit;
		}

		/**
		 * Return the image option URL for a given slug.
		 *
		 * @param string $img Image slug.
		 * @return string
		 */
		public static function get_img( $img ) {
			return plugins_url( $img . '.png', __FILE__ );
		}

		/**
		 * Return the wp_kses allowed-tags array including iframe for the Vimeo embed.
		 *
		 * @return array<string, array<string, bool>>
		 */
		public static function get_allowed_tags() {
			$allowed           = wp_kses_allowed_html( 'post' );
			$allowed['iframe'] = array(
				'src'                   => true,
				'width'                 => true,
				'height'                => true,
				'frameborder'           => true,
				'webkitallowfullscreen' => true,
				'mozallowfullscreen'    => true,
				'allowfullscreen'       => true,
			);
			return $allowed;
		}

		/**
		 * Return the default splash page text.
		 *
		 * @return string
		 */
		public static function get_default_text() {
			return sprintf(
				/* translators: 1: site name, 2: go-dark start date/time, 3: go-dark end date/time */
				__( '%1$s has gone dark from %2$s until %3$s to protest SOPA/PIPA and Internet Censorship.', 'go-dark' ),
				get_bloginfo( 'name' ),
				wp_date( self::get_timedate_format(), self::get_start() ),
				wp_date( self::get_timedate_format(), self::get_end() )
			)
			. "\r\n\r\n"
			. '<iframe src="https://player.vimeo.com/video/31100268?byline=0&#038;portrait=0" width="400" height="225" frameborder="0" webkitAllowFullScreen allowFullScreen></iframe>'
			. "\r\n\r\n"
			. '<a href="https://sopastrike.com/">' . esc_html__( 'Learn more.', 'go-dark' ) . '</a>';
		}

		/**
		 * Check whether the site is currently in the go-dark window.
		 *
		 * @return bool
		 */
		public static function is_dark() {
			$now = time();
			return ( $now >= self::get_start() && $now < self::get_end() );
		}

		/**
		 * Return the go-dark start timestamp (UTC).
		 *
		 * @return int
		 */
		public static function get_start() {
			return (int) get_option( 'go_dark_start', self::get_default_start() );
		}

		/**
		 * Return the go-dark end timestamp (UTC).
		 *
		 * @return int
		 */
		public static function get_end() {
			return (int) get_option( 'go_dark_end', self::get_default_end() );
		}

		/**
		 * Return the default go-dark start timestamp.
		 *
		 * @return int
		 */
		public static function get_default_start() {
			return strtotime( '2012/01/18 8:00' );
		}

		/**
		 * Return the default go-dark end timestamp.
		 *
		 * @return int
		 */
		public static function get_default_end() {
			return strtotime( '2012/01/19 8:00' );
		}

		/**
		 * Return the date/time format string for display.
		 *
		 * @return string
		 */
		public static function get_timedate_format() {
			return get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
	}

	go_dark::go();

endif;
