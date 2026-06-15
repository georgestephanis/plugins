<?php
/**
 * Plugin Name: Update Control
 * Plugin URI: http://github.com/chipbennett/update-control/
 * Description: Adds a manual toggle to the WordPress Admin Interface for managing auto-updates.
 * Author: George Stephanis, Chip Bennett
 * Version: 1.5.1
 * Author URI: http://chipbennett.net
 * Text Domain: update-control
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package StephanisUpdateControl
 */

namespace Stephanis\UpdateControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update Control Class
 */
class Update_Control {

	/**
	 * Enqueue admin script and stylesheets for General settings page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		if ( 'options-general.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'update-control-admin',
			plugins_url( 'update-control.js', __FILE__ ),
			array( 'jquery' ),
			'1.5.1',
			true
		);

		wp_enqueue_style(
			'update-control-admin',
			plugins_url( 'update-control.css', __FILE__ ),
			array(),
			'1.5.1'
		);
	}

	/**
	 * Register automatic updates core/plugins/themes filters based on saved options.
	 */
	public static function setup_upgrade_filters() {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		$options = self::get_options();

		// Do these at priority 1, so other folks can easily override it.

		if ( 'no' === $options['active'] ) {

			add_filter( 'automatic_updater_disabled', '__return_true', 1 );

		} else {

			if ( in_array( $options['core'], array( 'dev', 'major', 'minor' ), true ) ) {
				add_filter( 'allow_' . $options['core'] . '_auto_core_updates', '__return_true', 1 );
			}

			if ( $options['plugin'] ) {
				add_filter( 'auto_update_plugin', '__return_true', 1 );
			}

			if ( $options['theme'] ) {
				add_filter( 'auto_update_theme', '__return_true', 1 );
			}

			if ( ! $options['translation'] ) {
				add_filter( 'auto_update_translation', '__return_false', 1 );
			}

			if ( $options['vcscheck'] ) {
				add_filter( 'automatic_updates_is_vcs_checkout', '__return_false', 1 );
			}

			if ( 'no' === $options['emailactive'] || ! ( $options['successemail'] || $options['failureemail'] || $options['criticalemail'] ) ) {
				add_filter( 'auto_core_update_send_email', '__return_false', 1 );
			} else {
				add_filter( 'auto_core_update_send_email', array( __CLASS__, 'filter_email' ), 1, 2 );
			}

			if ( $options['debugemail'] ) {
				add_filter( 'automatic_updates_send_debug_email', '__return_false', 1 );
			}
		}
	}

	/**
	 * Filters whether to send update emails based on the update status type.
	 *
	 * @param bool   $should_send Whether to send the email.
	 * @param string $type        The email type (e.g. success, fail, critical).
	 * @return bool True if emails should be sent, false otherwise.
	 */
	public static function filter_email( $should_send, $type ) {
		$options = self::get_options();

		if ( 'success' === $type && ! $options['successemail'] ) {
			return false;
		}

		if ( 'fail' === $type && ! $options['failureemail'] ) {
			return false;
		}

		if ( 'critical' === $type && ! $options['criticalemail'] ) {
			return false;
		}

		return $should_send;
	}

	/**
	 * Retrieve saved plugin options combined with defaults.
	 *
	 * @return array The option array.
	 */
	public static function get_options() {
		$defaults = array(
			'active'         => 'yes',
			'core'           => 'minor',
			'plugin'         => false,
			'theme'          => false,
			'translation'    => true,
			'toggleadvanced' => 'hide',
			'vcscheck'       => false,
			'emailactive'    => 'yes',
			'successemail'   => true,
			'failureemail'   => true,
			'criticalemail'  => true,
			'debugemail'     => false,
		);
		$args     = get_option( 'update_control_options', array() );
		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Retrieve a single option value by key.
	 *
	 * @param string $key Option key.
	 * @return mixed Option value or null.
	 */
	public static function get_option( $key ) {
		$options = self::get_options();
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}
		return null;
	}

	/**
	 * Register plugin settings option group and add settings fields.
	 */
	public static function register_settings() {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_settings_section(
			'update-control',
			esc_html__( 'Automatic Updates', 'update-control' ),
			array( __CLASS__, 'update_control_settings_section' ),
			'general'
		);

		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return;
		}

		add_settings_field(
			'update_control_active',
			sprintf( '<label for="update_control_active">%1$s</label>', esc_html__( 'Automatic Updates Enabled?', 'update-control' ) ),
			array( __CLASS__, 'update_control_active_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_core',
			sprintf( '<label for="update_control_core">%1$s</label>', esc_html__( 'Automatic Core Update Level?', 'update-control' ) ),
			array( __CLASS__, 'update_control_core_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_plugin',
			sprintf( '<label for="update_control_plugin">%1$s</label>', esc_html__( 'Permit Automatic Plugin Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_plugin_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_theme',
			sprintf( '<label for="update_control_theme">%1$s</label>', esc_html__( 'Permit Automatic Theme Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_theme_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_translation',
			sprintf( '<label for="update_control_translation">%1$s</label>', esc_html__( 'Permit Automatic Translation Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_translation_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_toggleadvanced',
			sprintf( '<label for="update_control_toggleadvanced">%1$s</label>', esc_html__( 'Advanced Settings', 'update-control' ) ),
			array( __CLASS__, 'update_control_toggleadvanced_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_vcscheck',
			sprintf( '<label for="update_control_vcscheck">%1$s</label>', esc_html__( 'Enable updates for VCS installations?', 'update-control' ) ),
			array( __CLASS__, 'update_control_vcscheck_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_active',
			sprintf( '<label for="update_control_email_active">%1$s</label>', esc_html__( 'Update Emails Enabled?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_active_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_success',
			sprintf( '<label for="update_control_email_success">%1$s</label>', esc_html__( 'Send Emails for Successful Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_success_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_failure',
			sprintf( '<label for="update_control_email_failure">%1$s</label>', esc_html__( 'Send Emails for Failed Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_failure_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_critical',
			sprintf( '<label for="update_control_email_critical">%1$s</label>', esc_html__( 'Send Emails for Critically Failed Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_critical_cb' ),
			'general',
			'update-control'
		);

		$wp_version = $GLOBALS['wp_version'];
		if ( false !== strpos( $wp_version, '-' ) ) {
			add_settings_field(
				'update_control_email_debug',
				sprintf( '<label for="update_control_email_debug">%1$s</label>', esc_html__( 'Disable Debug Emails for Development Versions?', 'update-control' ) ),
				array( __CLASS__, 'update_control_email_debug_cb' ),
				'general',
				'update-control'
			);
		}

		register_setting( 'general', 'update_control_options', array( __CLASS__, 'sanitize_options' ) );
	}

	/**
	 * Output descriptions and instructions for the settings section.
	 */
	public static function update_control_settings_section() {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) : ?>
			<p id="update-control-settings-section">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: PHP constant name */
						__( 'You have the %s constant set. Automatic updates are disabled.', 'update-control' ),
						'<code>AUTOMATIC_UPDATER_DISABLED</code>'
					),
					array( 'code' => array() )
				);
				?>
			</p>
		<?php else : ?>
			<p id="update-control-settings-section">
				<?php esc_html_e( 'This section lets you specify what areas of your WordPress install will be permitted to auto-update.', 'update-control' ); ?>
			</p>
			<?php
		endif;
	}

	/**
	 * Output markup for 'Automatic Updates Enabled?' field.
	 */
	public static function update_control_active_cb() {
		?>
		<select id="update_control_active" name="update_control_options[active]">
			<option <?php selected( 'yes' === self::get_option( 'active' ) ); ?> value="yes"><?php esc_html_e( 'Yes', 'update-control' ); ?></option>
			<option <?php selected( 'no' === self::get_option( 'active' ) ); ?> value="no"><?php esc_html_e( 'No', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Automatic Core Update Level?' field.
	 */
	public static function update_control_core_cb() {
		?>
		<select class="update_control_dependency" id="update_control_core" name="update_control_options[core]">
			<option <?php selected( 'minor' === self::get_option( 'core' ) ); ?> value="minor"><?php esc_html_e( 'Minor Updates', 'update-control' ); ?></option>
			<option <?php selected( 'major' === self::get_option( 'core' ) ); ?> value="major"><?php esc_html_e( 'Major Updates', 'update-control' ); ?></option>
			<option <?php selected( 'dev' === self::get_option( 'core' ) ); ?> value="dev"><?php esc_html_e( 'Development Updates', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Permit Automatic Plugin Updates?' field.
	 */
	public static function update_control_plugin_cb() {
		?>
		<input type="checkbox" class="update_control_dependency" id="update_control_plugin" name="update_control_options[plugin]" <?php checked( self::get_option( 'plugin' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Permit Automatic Theme Updates?' field.
	 */
	public static function update_control_theme_cb() {
		?>
		<input type="checkbox" class="update_control_dependency" id="update_control_theme" name="update_control_options[theme]" <?php checked( self::get_option( 'theme' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Permit Automatic Translation Updates?' field.
	 */
	public static function update_control_translation_cb() {
		?>
		<input type="checkbox" class="update_control_dependency" id="update_control_translation" name="update_control_options[translation]" <?php checked( self::get_option( 'translation' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Advanced Settings' field.
	 */
	public static function update_control_toggleadvanced_cb() {
		?>
		<select class="update_control_dependency" id="update_control_toggleadvanced" name="update_control_options[toggleadvanced]">
			<option <?php selected( 'show' === self::get_option( 'toggleadvanced' ) ); ?> value="show"><?php esc_html_e( 'Show', 'update-control' ); ?></option>
			<option <?php selected( 'hide' === self::get_option( 'toggleadvanced' ) ); ?> value="hide"><?php esc_html_e( 'Hide', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Enable updates for VCS installations?' field.
	 */
	public static function update_control_vcscheck_cb() {
		?>
		<input type="checkbox" class="update_control_advanced" id="update_control_vcscheck" name="update_control_options[vcscheck]" <?php checked( self::get_option( 'vcscheck' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Update Emails Enabled?' field.
	 */
	public static function update_control_email_active_cb() {
		?>
		<select class="update_control_advanced" id="update_control_email_active" name="update_control_options[emailactive]">
			<option <?php selected( 'yes' === self::get_option( 'emailactive' ) ); ?> value="yes"><?php esc_html_e( 'Yes', 'update-control' ); ?></option>
			<option <?php selected( 'no' === self::get_option( 'emailactive' ) ); ?> value="no"><?php esc_html_e( 'No', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Successful Updates?' field.
	 */
	public static function update_control_email_success_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_success" name="update_control_options[successemail]" <?php checked( self::get_option( 'successemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Failed Updates?' field.
	 */
	public static function update_control_email_failure_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_failure" name="update_control_options[failureemail]" <?php checked( self::get_option( 'failureemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Critically Failed Updates?' field.
	 */
	public static function update_control_email_critical_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_critical" name="update_control_options[criticalemail]" <?php checked( self::get_option( 'criticalemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Disable Debug Emails for Development Versions?' field.
	 */
	public static function update_control_email_debug_cb() {
		?>
		<input type="checkbox" class="update_control_advanced" id="update_control_email_debug" name="update_control_options[debugemail]" <?php checked( self::get_option( 'debugemail' ) ); ?> />
		<?php
	}

	/**
	 * Sanitize plugin options on save.
	 *
	 * @param array $options Raw options sent by option page.
	 * @return array Sanitized options.
	 */
	public static function sanitize_options( $options ) {
		$options = wp_parse_args( (array) $options, self::get_options() );

		$options['active']         = ( in_array( $options['active'], array( 'yes', 'no' ), true ) ? $options['active'] : 'yes' );
		$options['core']           = ( in_array( $options['core'], array( 'minor', 'major', 'dev' ), true ) ? $options['core'] : 'minor' );
		$options['plugin']         = ! empty( $options['plugin'] );
		$options['theme']          = ! empty( $options['theme'] );
		$options['translation']    = ! empty( $options['translation'] );
		$options['toggleadvanced'] = ( isset( $options['toggleadvanced'] ) && in_array( $options['toggleadvanced'], array( 'show', 'hide' ), true ) ? $options['toggleadvanced'] : 'hide' );
		$options['vcscheck']       = ! empty( $options['vcscheck'] );
		$options['emailactive']    = ( in_array( $options['emailactive'], array( 'yes', 'no' ), true ) ? $options['emailactive'] : 'yes' );
		$options['successemail']   = ! empty( $options['successemail'] );
		$options['failureemail']   = ! empty( $options['failureemail'] );
		$options['criticalemail']  = ! empty( $options['criticalemail'] );
		$options['debugemail']     = ! empty( $options['debugemail'] );

		return $options;
	}
}
add_action( 'admin_init', array( __NAMESPACE__ . '\Update_Control', 'register_settings' ) );
add_action( 'init', array( __NAMESPACE__ . '\Update_Control', 'setup_upgrade_filters' ) );
add_action( 'admin_enqueue_scripts', array( __NAMESPACE__ . '\Update_Control', 'enqueue_admin_scripts' ) );