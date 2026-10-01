<?php
/**
 * External links: a repeatable label + URL postmeta shared by Ndizi's CPTs.
 *
 * Records pointing back at the system a record was mirrored from (an Asana task, a
 * GitHub PR, a FreshBooks invoice, …) so the link survives the round trip. One helper
 * owns the meta key, sanitisation, REST schema, admin editor and front-end output so the
 * clients, projects, tasks and invoices all behave identically.
 *
 * @package Ndizi_Project_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_External_Links {

	/**
	 * Postmeta key holding the list of `array( 'label' => string, 'url' => string )`.
	 */
	const META_KEY = '_ndizi_external_links';

	/**
	 * Hard cap on links per record, so a runaway client can't bloat a single meta row.
	 */
	const MAX_LINKS = 25;

	/**
	 * URL schemes accepted for stored links.
	 *
	 * @return string[]
	 */
	public static function allowed_protocols() {
		return array( 'http', 'https', 'mailto' );
	}

	/**
	 * Post types that carry external links, mapped to the capability that may edit them.
	 *
	 * Invoices are only included while the invoicing module is active, matching where the
	 * `ndizi_invoice` CPT itself is registered.
	 *
	 * @return array<string,string>
	 */
	public static function get_post_types() {
		$types = array(
			'ndizi_client'  => 'ndizi_manage_clients',
			'ndizi_project' => 'ndizi_manage_projects',
			'ndizi_task'    => 'ndizi_manage_tasks',
		);

		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			$types['ndizi_invoice'] = 'ndizi_manage_invoices';
		}

		return $types;
	}

	/**
	 * JSON schema for a list of links. Shared by REST meta and the Abilities API so both
	 * surfaces describe the field identically.
	 *
	 * @return array
	 */
	public static function get_schema() {
		return array(
			'type'     => 'array',
			'maxItems' => self::MAX_LINKS,
			'items'    => array(
				'type'                 => 'object',
				'properties'           => array(
					'label' => array(
						'type'        => 'string',
						'description' => __( 'Link text. Falls back to the URL host when empty.', 'ndizi-project-management' ),
					),
					'url'   => array(
						'type'        => 'string',
						'format'      => 'uri',
						'description' => __( 'Absolute http(s) or mailto URL.', 'ndizi-project-management' ),
					),
				),
				'required'             => array( 'url' ),
				'additionalProperties' => false,
			),
		);
	}

	/**
	 * Registers the meta on every supported post type.
	 */
	public static function register_meta() {
		foreach ( self::get_post_types() as $post_type => $capability ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'show_in_rest'      => array( 'schema' => self::get_schema() ),
					'single'            => true,
					'type'              => 'array',
					'default'           => array(),
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'auth_callback'     => static function () use ( $capability ) {
						return current_user_can( $capability );
					},
				)
			);
		}
	}

	/**
	 * Normalises arbitrary input into a clean list of links.
	 *
	 * Rows without a usable URL are dropped (an empty editor row is therefore a no-op),
	 * labels are plain text, and URLs are restricted to {@see self::allowed_protocols()}.
	 *
	 * @param mixed $links Raw list of `array( 'label' => …, 'url' => … )` rows.
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function sanitize( $links ) {
		if ( ! is_array( $links ) ) {
			return array();
		}

		$clean = array();
		foreach ( $links as $link ) {
			if ( ! is_array( $link ) || empty( $link['url'] ) || ! is_string( $link['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( trim( $link['url'] ), self::allowed_protocols() );
			if ( '' === $url ) {
				continue;
			}

			$clean[] = array(
				'label' => isset( $link['label'] ) && is_string( $link['label'] ) ? sanitize_text_field( $link['label'] ) : '',
				'url'   => $url,
			);

			if ( count( $clean ) >= self::MAX_LINKS ) {
				break;
			}
		}

		return $clean;
	}

	/**
	 * Strict counterpart to {@see self::sanitize()} for API input: rejects the whole list
	 * rather than silently dropping rows, so a caller learns its link was not stored.
	 *
	 * @param mixed $links Raw list of links.
	 * @return true|WP_Error
	 */
	public static function validate( $links ) {
		// A lone { label, url } object decodes to an associative array; require a real list so it is rejected rather than wiping stored links.
		if ( ! is_array( $links ) || ( array() !== $links && array_keys( $links ) !== range( 0, count( $links ) - 1 ) ) ) {
			return new WP_Error( 'invalid_external_links', __( 'external_links must be an array of { label, url } objects.', 'ndizi-project-management' ), array( 'status' => 400 ) );
		}

		if ( count( $links ) > self::MAX_LINKS ) {
			/* translators: %d: maximum number of links */
			return new WP_Error( 'invalid_external_links', sprintf( __( 'A record can have at most %d external links.', 'ndizi-project-management' ), self::MAX_LINKS ), array( 'status' => 400 ) );
		}

		foreach ( $links as $index => $link ) {
			$url = is_array( $link ) && isset( $link['url'] ) && is_string( $link['url'] ) ? trim( $link['url'] ) : '';
			if ( '' === $url || '' === esc_url_raw( $url, self::allowed_protocols() ) ) {
				/* translators: %d: zero-based index of the offending link */
				return new WP_Error( 'invalid_external_links', sprintf( __( 'external_links[%d] needs a valid http(s) or mailto url.', 'ndizi-project-management' ), $index ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Returns a record's links.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function get_links( $post_id ) {
		$links = get_post_meta( (int) $post_id, self::META_KEY, true );
		return is_array( $links ) ? self::sanitize( $links ) : array();
	}

	/**
	 * Replaces a record's links. An empty list removes the meta row entirely.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $links   Raw list of links; sanitised here.
	 * @return array<int,array{label:string,url:string}> The links as stored.
	 */
	public static function set_links( $post_id, $links ) {
		$clean = self::sanitize( $links );

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, self::META_KEY );
		} else {
			update_post_meta( $post_id, self::META_KEY, $clean );
		}

		return $clean;
	}

	/**
	 * The label to display for a link: its stored label, or the URL host as a fallback.
	 *
	 * @param array $link A single link row.
	 * @return string
	 */
	public static function get_display_label( $link ) {
		if ( isset( $link['label'] ) && '' !== $link['label'] ) {
			return $link['label'];
		}

		$host = wp_parse_url( $link['url'], PHP_URL_HOST );
		if ( $host ) {
			return preg_replace( '/^www\./', '', $host );
		}

		// mailto: has no host; show the address.
		return preg_replace( '/^mailto:/i', '', $link['url'] );
	}

	/**
	 * Markup for a record's links as an unordered list, or an empty string if it has none.
	 *
	 * @param int|array $source Post ID, or an already-fetched list of links.
	 * @return string Escaped HTML.
	 */
	public static function render_list( $source ) {
		$links = is_array( $source ) ? self::sanitize( $source ) : self::get_links( $source );
		if ( empty( $links ) ) {
			return '';
		}

		$html = '<ul class="ndizi-external-links">';
		foreach ( $links as $link ) {
			$html .= sprintf(
				'<li><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></li>',
				esc_url( $link['url'] ),
				esc_html( self::get_display_label( $link ) )
			);
		}
		$html .= '</ul>';

		return $html;
	}

	/**
	 * Renders the repeatable label/URL editor as a `<tr>` for the CPT meta boxes.
	 *
	 * Rows are submitted as `ndizi_external_links[<n>][label|url]`; the sibling hidden
	 * `ndizi_external_links_present` field lets {@see self::save_from_request()} tell
	 * "all links removed" apart from "this form never had the editor".
	 *
	 * @param int $post_id Post ID.
	 */
	public static function render_editor_row( $post_id ) {
		$links = self::get_links( $post_id );
		?>
		<tr class="ndizi-external-links-editor">
			<th><?php esc_html_e( 'External Links', 'ndizi-project-management' ); ?></th>
			<td>
				<input type="hidden" name="ndizi_external_links_present" value="1">
				<div class="ndizi-external-links-rows">
					<?php foreach ( $links as $index => $link ) : ?>
						<?php self::render_editor_input_row( $index, $link ); ?>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button ndizi-add-external-link"><?php esc_html_e( 'Add Link', 'ndizi-project-management' ); ?></button>
				<p class="description"><?php esc_html_e( 'Links to the original record in another system (e.g. an Asana task or GitHub pull request).', 'ndizi-project-management' ); ?></p>
				<script type="text/template" class="ndizi-external-link-template">
					<?php self::render_editor_input_row( '__INDEX__', array() ); ?>
				</script>
			</td>
		</tr>
		<?php
	}

	/**
	 * A single label/URL input pair.
	 *
	 * @param int|string $index Row index used in the input names.
	 * @param array      $link  Existing link values, or empty for a blank row.
	 */
	private static function render_editor_input_row( $index, $link ) {
		?>
		<p class="ndizi-external-link-row">
			<input type="text" name="ndizi_external_links[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( isset( $link['label'] ) ? $link['label'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Label (e.g. Asana)', 'ndizi-project-management' ); ?>" aria-label="<?php esc_attr_e( 'Link label', 'ndizi-project-management' ); ?>">
			<input type="url" name="ndizi_external_links[<?php echo esc_attr( $index ); ?>][url]" value="<?php echo esc_attr( isset( $link['url'] ) ? $link['url'] : '' ); ?>" class="regular-text" placeholder="https://" aria-label="<?php esc_attr_e( 'Link URL', 'ndizi-project-management' ); ?>">
			<button type="button" class="button-link ndizi-remove-external-link" aria-label="<?php esc_attr_e( 'Remove link', 'ndizi-project-management' ); ?>"><span class="dashicons dashicons-trash"></span></button>
		</p>
		<?php
	}

	/**
	 * Persists the editor's rows from the current request.
	 *
	 * Callers must already have verified their meta box nonce and the user's capability.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_from_request( $post_id ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Reason: callers verify the meta box nonce before delegating here.
		if ( ! isset( $_POST['ndizi_external_links_present'] ) ) {
			return;
		}

		$raw = isset( $_POST['ndizi_external_links'] ) && is_array( $_POST['ndizi_external_links'] )
			? wp_unslash( $_POST['ndizi_external_links'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Reason: sanitised per-row by self::sanitize().
			: array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		self::set_links( $post_id, array_values( $raw ) );
	}
}
