<?php
/**
 * Plugin Name: Google Tag Manager
 * Plugin URI: https://wordpress.org/plugins/google-tag-manager/
 * Description: This is an implementation of the Tag Management system from Google. It adds a field to the existing General Settings page for the ID, and if specified, outputs the tag management javascript in the page footer.
 * Version: 1.0.4
 * Author: George Stephanis
 * Author URI: https://georgestephanis.wordpress.com
 * License: GPLv2 or later
 *
 * @package Google_Tag_Manager
 */

/**
 * Main plugin class. Registers settings and outputs GTM snippets.
 */
class Google_Tag_Manager {

	/**
	 * Whether the noscript tag has already been printed on this page load.
	 *
	 * @var bool
	 */
	public static $printed_noscript_tag = false;

	/**
	 * Register all hooks.
	 *
	 * @return void
	 */
	public static function go() {
		add_action( 'admin_init', array( __CLASS__, 'register_fields' ) );
		add_action( 'wp_head', array( __CLASS__, 'print_tag' ), 1 );
		add_action( 'wp_body_open', array( __CLASS__, 'print_noscript_tag' ) ); // Core wp_body_open hook added in WP 5.2.
		add_action( 'genesis_before', array( __CLASS__, 'print_noscript_tag' ) ); // Genesis theme framework.
		add_action( 'tha_body_top', array( __CLASS__, 'print_noscript_tag' ) ); // Theme Hook Alliance.
		add_action( 'body_top', array( __CLASS__, 'print_noscript_tag' ) ); // THA unprefixed variant.
		add_action( 'wp_footer', array( __CLASS__, 'print_noscript_tag' ) ); // Fallback for themes without body-open support.
	}

	/**
	 * Register the Google Tag Manager ID setting on the General Settings page.
	 *
	 * @return void
	 */
	public static function register_fields() {
		register_setting(
			'general',
			'google_tag_manager_id',
			array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		add_settings_field( 'google_tag_manager_id', '<label for="google_tag_manager_id">' . esc_html__( 'Google Tag Manager ID', 'google-tag-manager' ) . '</label>', array( __CLASS__, 'fields_html' ), 'general' );
	}

	/**
	 * Render the Google Tag Manager ID settings field.
	 *
	 * @return void
	 */
	public static function fields_html() {
		?>
		<input type="text" id="google_tag_manager_id" name="google_tag_manager_id" placeholder="ABC-DEFG" class="regular-text code" value="<?php echo esc_attr( get_option( 'google_tag_manager_id', '' ) ); ?>" />
		<p class="description"><?php esc_html_e( 'The ID from Google\'s provided code (as emphasized):', 'google-tag-manager' ); ?><br />
			<code>&lt;noscript&gt;&lt;iframe src="//www.googletagmanager.com/ns.html?id=<strong style="color:#c00;">ABC-DEFG</strong>"</code></p>
		<p class="description">
		<?php
			printf(
				wp_kses(
					/* translators: %s: URL to the Google Tag Manager sign-up page */
					__( 'You can get yours <a href="%s">here</a>!', 'google-tag-manager' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( 'https://www.google.com/tagmanager/' )
			);
		?>
		</p>
		<?php
	}

	/**
	 * Output the Google Tag Manager JavaScript snippet in the <head>.
	 *
	 * @return void
	 */
	public static function print_tag() {
		$id = get_option( 'google_tag_manager_id', '' );
		if ( ! $id ) {
			return;
		}
		?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo esc_js( $id ); ?>');</script>
<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * Output the Google Tag Manager noscript iframe immediately after <body>.
	 *
	 * Hooked to several body-open actions to maximise theme compatibility;
	 * a static flag ensures the tag is only printed once regardless of which
	 * hook fires first.
	 *
	 * @return void
	 */
	public static function print_noscript_tag() {
		// Only print the noscript tag once across all the hooks we're trying.
		if ( self::$printed_noscript_tag ) {
			return;
		}
		self::$printed_noscript_tag = true;

		$id = get_option( 'google_tag_manager_id', '' );
		if ( ! $id ) {
			return;
		}
		?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
		<?php
	}
}

Google_Tag_Manager::go();
