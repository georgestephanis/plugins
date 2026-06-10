<?php
/**
 * Plugin Name: Hugh
 * Plugin URI:  https://wordpress.org/plugins/hugh/
 * Description: Democratize coloring.
 * Version:     1.0.3
 * Author:      Michael Arestad and George Stephanis
 * Author URI:  http://blog.michaelarestad.com
 * Text Domain: hugh
 * Domain Path: /languages
 *
 * @package Hugh
 */

/**
 * Core plugin class. Registers hooks, REST routes, and the CSS filter.
 */
class Hugh {

	/**
	 * Register all plugin hooks.
	 *
	 * @return void
	 */
	public static function add_hooks() {
		add_action( 'widgets_init', array( __CLASS__, 'widgets_init' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
		add_filter( 'hugh_css', array( __CLASS__, 'hugh_css' ) );
	}

	/**
	 * Register the Hugh widget.
	 *
	 * @return void
	 */
	public static function widgets_init() {
		register_widget( 'Hugh_Widget' );
	}

	/**
	 * Register the REST API routes.
	 *
	 * @return void
	 */
	public static function rest_api_init() {
		register_rest_route(
			'hugh/v1',
			'/colors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => __CLASS__ . '::rest_get_colors',
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			'hugh/v1',
			'/colors/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => __CLASS__ . '::rest_add_color',
				'permission_callback' => '__return_true',
				'args'                => array(
					'color' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'label' => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST callback: return stored colors, optionally limited to the N most recent.
	 *
	 * @param WP_REST_Request $data The request object.
	 * @return array Array of color entries.
	 */
	public static function rest_get_colors( $data ) {
		$colors = array_values( self::get_colors() );
		if ( $data['limit'] ) {
			// Return only the most recently added colors.
			$colors = array_slice( $colors, -$data['limit'] );
		}
		return $colors;
	}

	/**
	 * REST callback: validate and store a new color entry.
	 *
	 * @param WP_REST_Request $data The request object.
	 * @return array|WP_Error The stored color entry, or a WP_Error on invalid input.
	 */
	public static function rest_add_color( $data ) {
		$new_color = strtolower( (string) $data['color'] );
		$new_label = substr( sanitize_text_field( (string) $data['label'] ), 0, 255 );

		if ( ! preg_match( '/^#[\da-f]{6}$/', $new_color ) ) {
			return new WP_Error( 'bad-color', __( 'The specified color is in an invalid format.', 'hugh' ) );
		}

		return self::add_color( $new_color, $new_label );
	}

	/**
	 * Retrieve the stored color transient.
	 *
	 * @return array Associative array of color entries keyed by hex value.
	 */
	public static function get_colors() {
		$colors = get_transient( 'hugh_colors' );
		if ( ! $colors ) {
			return array();
		}
		return (array) $colors;
	}

	/**
	 * Add a color entry to the transient store, capped at 100 entries.
	 *
	 * @param string $color Hex color value (e.g. '#a1b2c3').
	 * @param string $label Human-readable label for the color.
	 * @return array The stored color entry.
	 */
	public static function add_color( $color, $label ) {
		$colors           = self::get_colors();
		$colors[ $color ] = array(
			'color' => $color,
			'label' => $label,
			'time'  => time(),
		);

		uasort( $colors, array( __CLASS__, 'sort_by_time' ) );

		// Only store 100 colors max.
		if ( count( $colors ) > 100 ) {
			$colors = array_slice( $colors, 0, 100, true );
		}

		set_transient( 'hugh_colors', $colors );

		return $colors[ $color ];
	}

	/**
	 * Sort color entries by timestamp (ascending).
	 *
	 * @param array $a First color entry.
	 * @param array $b Second color entry.
	 * @return int Negative, zero, or positive comparison result.
	 */
	public static function sort_by_time( $a, $b ) {
		return $a['time'] - $b['time'];
	}

	/**
	 * Filter callback: inject theme-specific CSS overrides into the Hugh style template.
	 *
	 * @param string $css The base CSS string passed through the filter.
	 * @return string Minified CSS with any theme-specific rules applied.
	 */
	public static function hugh_css( $css ) {
		$slug = get_template();

		switch ( $slug ) {
			case 'twentyseventeen':
				ob_start();
				?>
.site-content-contain,
.navigation-top,
.main-navigation ul ul,
.main-navigation li li:hover,
.main-navigation li li.focus {
	background-color: {{ data.contrast }};
}
.social-navigation a,
.main-navigation ul ul,
input[type="search"] {
	border-color: {{ data.color }};
}
.main-navigation ul li.menu-item-has-children:after,
.main-navigation ul li.page_item_has_children:after {
	border-color: {{ data.contrast }};
}
.entry-title a,
.entry-meta a,
.entry-footer .cat-links a,
.entry-footer .tags-links a,
.post-navigation a:not(.prev):not(.next):focus,
.post-navigation a:not(.prev):not(.next):hover,
.pagination a:not(.prev):not(.next):focus,
.pagination a:not(.prev):not(.next):hover,
.comments-pagination a:not(.prev):not(.next):focus,
.comments-pagination a:not(.prev):not(.next):hover,
.logged-in-as a,
thead th,
.navigation-top {
	border-bottom-color: {{ data.color }};
}
#page pre,
#page button,
#page .prev.page-numbers,
#page .next.page-numbers {
	color: {{ data.color }};
}
.navigation-top,
.site-footer,
.pagination,
.comments-pagination {
	border-top-color: {{ data.color }};
}
#page .hugh__colorway {
	color: {{ data.color }};
}
#page .social-navigation svg,
#page .page-numbers svg {
	fill: {{ data.color }};
}
#page .hugh__color {
	transition: background-color .3s ease-in-out;
}
				<?php
				$css = ob_get_clean();
				break;
			default:
				return $css;
		}

		// Minify the generated CSS.
		return str_replace( array( "\t", "\r", "\n" ), '', $css );
	}
}

Hugh::add_hooks();

/**
 * Widget class for Hugh — renders the color picker UI and enqueues assets.
 */
class Hugh_Widget extends WP_Widget {

	/**
	 * Set up the widget name and description.
	 */
	public function __construct() {
		$widget_ops = array(
			'class_name'  => 'hugh_widget',
			'description' => __( 'Hugh is classy.', 'hugh' ),
		);
		parent::__construct( 'hugh_widget', __( 'Hugh Widget', 'hugh' ), $widget_ops );
	}

	/**
	 * Output the widget HTML and enqueue the required assets.
	 *
	 * @param array $args     Display arguments including before_widget and after_widget.
	 * @param array $instance Saved widget settings (unused).
	 * @return void
	 */
	public function widget( $args, $instance ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $instance intentionally unused.
		wp_enqueue_style( 'hugh', plugins_url( 'hugh.css', __FILE__ ), array(), '1.0.3' );
		wp_enqueue_script( 'hugh', plugins_url( 'hugh.js', __FILE__ ), array( 'wp-util' ), '1.0.3', true );
		wp_localize_script(
			'hugh',
			'Hugh',
			array(
				'root'      => esc_url_raw( rest_url() ),
				'namespace' => 'hugh/v1',
				'colors'    => array_values( Hugh::get_colors() ),
			)
		);
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-registered sidebar HTML.
		echo $args['before_widget'];
		?>
		<h1 class="widget-title"><?php esc_html_e( 'Make a color decision', 'hugh' ); ?></h1>

		<form class="hugh__form">
			<input class="hugh__color" type="color" id="hugh_color" value="#ffffff" />
			<input class="hugh__label hugh__screen-reader-text" type="text" id="hugh_label" placeholder="<?php esc_attr_e( 'Leave a secret note', 'hugh' ); ?>" />
			<button class="hugh__submit" type="submit"><?php esc_html_e( 'Share!', 'hugh' ); ?></button>
		</form>

		<div class="hugh__colorways"></div>

		<script type="text/html" id="tmpl-color-template">
			<a href="#" aria-label="recently used color" class="hugh__colorway" style="background-color:{{ data.color }}" title="{{ data.label }}" data-color="{{ data.color }}">
				<span class="hugh__screen-reader-text">{{ data.label }}</span>
				<div class="hugh__colorway-accent" style="background-color:{{ data.contrast }}"></div>
			</a>
		</script>
		<script type="text/html" id="tmpl-style-template">
		<?php ob_start(); ?>
			body,
			body.custom-background,
			html {
				background-color: {{ data.color }};
				color: {{ data.contrast }};
				transition: background-color .3s ease-in-out, color .3s ease-in-out;
			}
			#wpadminbar,
			#wpadminbar a,
			.ab-sub-wrapper,
			#wpadminbar#wpadminbar .ab-label.ab-label,
			#wpadminbar .ab-item:before,
			#wpadminbar .ab-icon:before,
			#wpadminbar #adminbarsearch:before {
				color: {{ data.contrast }} !important;
				transition: color .3s ease-in-out;
			}
			#wpadminbar,
			#wpadminbar a,
			.ab-sub-wrapper  {
				background-color: {{ data.color }} !important;
				transition: background-color .3s ease-in-out;
			}
			#wpadminbar a:hover,
			#wpadminbar a:focus,
			#wpadminbar .ab-item:hover:before,
			#wpadminbar .ab-item:focus:before,
			#wpadminbar .ab-item:hover .ab-icon:before,
			#wpadminbar .ab-item:focus .ab-icon:before,
			#wpadminbar#wpadminbar .ab-item.ab-item:hover .ab-label,
			#wpadminbar#wpadminbar .ab-item.ab-item:focus .ab-label {
				color: {{ data.color }} !important;
			}
			#wpadminbar a:hover,
			#wpadminbar a:focus {
				background-color: {{ data.contrast }} !important;
			}
		<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS from trusted plugin filter. ?>
		<?php echo apply_filters( 'hugh_css', ob_get_clean() ); ?>
		</script>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-registered sidebar HTML.
		echo $args['after_widget'];
	}

	/**
	 * Output the widget settings form (no configurable settings).
	 *
	 * @param array $instance Current widget settings.
	 * @return void
	 */
	public function form( $instance ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- no settings to render.
		echo '<p>' . esc_html__( 'No settings for this widget.', 'hugh' ) . '</p>';
	}

	/**
	 * Save widget settings (no configurable settings).
	 *
	 * @param array $new_instance New widget settings.
	 * @param array $old_instance Previous widget settings.
	 * @return array Unchanged settings array.
	 */
	public function update( $new_instance, $old_instance ) {
		return $old_instance;
	}
}
