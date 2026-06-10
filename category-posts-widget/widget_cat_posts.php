<?php
/**
 * Plugin Name: Recent Category Posts Widget
 * Plugin URI:  https://georgestephanis.wordpress.com/
 * Description: Displays the most recent posts in the selected category in a simple list.
 * Author:      E. George Stephanis
 * Author URI:  https://georgestephanis.wordpress.com
 * Version:     2.0
 * Text Domain: category-posts-widget
 *
 * @package Category_Posts_Widget
 */

/**
 * Widget that displays the most recent posts from a single category.
 */
class Single_Category_Posts_Widget extends WP_Widget {

	/**
	 * Register the widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'single_category_posts_widget',
			__( 'Single Category Posts Widget', 'category-posts-widget' ),
			array( 'description' => __( 'A widget to display the most recent posts from a single category.', 'category-posts-widget' ) )
		);
	}

	/**
	 * Render the widget settings form in the admin.
	 *
	 * @param array $instance Current saved widget settings.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Recent Posts in Category', 'category-posts-widget' );
		$cat   = isset( $instance['cat'] ) ? intval( $instance['cat'] ) : 0;
		$qty   = isset( $instance['qty'] ) ? intval( $instance['qty'] ) : 5;
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'category-posts-widget' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cat' ) ); ?>"><?php esc_html_e( 'Category:', 'category-posts-widget' ); ?></label>
			<?php
			wp_dropdown_categories(
				array(
					'orderby'       => 'ID',
					'order'         => 'ASC',
					'show_count'    => 1,
					'hide_empty'    => 0,
					'hide_if_empty' => false,
					'echo'          => 1,
					'selected'      => $cat,
					'hierarchical'  => 1,
					'name'          => $this->get_field_name( 'cat' ),
					'id'            => $this->get_field_id( 'cat' ),
					'class'         => 'widefat',
					'taxonomy'      => 'category',
				)
			);
			?>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'qty' ) ); ?>"><?php esc_html_e( 'Quantity:', 'category-posts-widget' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'qty' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'qty' ) ); ?>" type="number" min="1" step="1" value="<?php echo esc_attr( $qty ); ?>" />
		</p>
		<?php
	}

	/**
	 * Sanitize and save the widget settings.
	 *
	 * @param array $new_instance New settings submitted from the form.
	 * @param array $old_instance Previous saved settings (unused).
	 * @return array Sanitized settings array.
	 */
	public function update( $new_instance, $old_instance ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $old_instance not needed; no merge required.
		$instance          = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['cat']   = intval( $new_instance['cat'] );
		$instance['qty']   = intval( $new_instance['qty'] );
		return $instance;
	}

	/**
	 * Render the widget on the front end.
	 *
	 * @param array $args     Display arguments including before_widget and after_widget.
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$before_widget = isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		$after_widget  = isset( $args['after_widget'] ) ? $args['after_widget'] : '';
		$before_title  = isset( $args['before_title'] ) ? $args['before_title'] : '';
		$after_title   = isset( $args['after_title'] ) ? $args['after_title'] : '';

		$title = apply_filters( 'widget_title', $instance['title'] );
		$cat   = $instance['cat'];
		$qty   = (int) $instance['qty'];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-registered sidebar HTML.
		echo $before_widget;
		if ( ! empty( $title ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before/after_title are theme-registered HTML.
			echo $before_title . esc_html( $title ) . $after_title;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output built with esc_url/esc_html in get_cat_posts().
		echo self::get_cat_posts( $cat, $qty );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme-registered sidebar HTML.
		echo $after_widget;
	}

	/**
	 * Build and return an HTML list of recent posts from the specified category.
	 *
	 * @param int $cat The category ID to query.
	 * @param int $qty Number of posts to retrieve.
	 * @return string HTML unordered list, or an empty string if no posts found.
	 */
	public static function get_cat_posts( $cat, $qty ) {
		$posts = get_posts(
			array(
				'cat'         => $cat,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'numberposts' => $qty,
			)
		);

		$post_count  = count( $posts );
		$return_this = '';

		if ( $post_count ) {
			$return_this .= '<ul class="single-category-posts-widget">' . "\n";
		}

		foreach ( $posts as $post ) {
			$return_this .= "\t" . '<li><a href="' . esc_url( get_permalink( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a></li>' . "\n";
		}

		if ( $post_count ) {
			$return_this .= '</ul>' . "\n";
		}

		return $return_this;
	}
}

add_action(
	'widgets_init',
	function () {
		register_widget( 'Single_Category_Posts_Widget' );
	}
);
