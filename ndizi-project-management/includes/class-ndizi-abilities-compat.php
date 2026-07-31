<?php
/**
 * Redistributable compatibility shim for the WordPress Abilities API.
 *
 * The Abilities API shipped in WP 6.9; WP 7.1 added the wp_ability_invoked
 * action, the wp_ability_validate_input/output filters, and the JSON Schema
 * client-preparation helpers (wp_prepare_json_schema_for_client(),
 * wp_get_json_schema_allowed_keywords(), the wp_json_schema_allowed_keywords
 * filter). This shim makes those usable transparently on 6.9/7.0:
 *
 * - wp_prepare_json_schema_for_client() / wp_get_json_schema_allowed_keywords()
 *   are declared as real global functions when core doesn't already provide
 *   them, so calling code just calls the real WP function name either way.
 * - The wp_ability_invoked action is bridged from the earlier
 *   wp_before_execute_ability hook when core doesn't fire it natively, so
 *   calling code just does add_action( 'wp_ability_invoked', ... ) either way.
 *   Note the bridge only sees invocations that already passed permission
 *   checks (wp_before_execute_ability fires after that on pre-7.1 core),
 *   whereas native 7.1 wp_ability_invoked fires before permission checks too.
 *
 * wp_ability_validate_input/output cannot be polyfilled this way — they gate
 * a step inside WP_Ability::execute() itself, which isn't reachable from
 * outside on pre-7.1 core. Filters added to those hooks simply never run
 * pre-7.1; keep an equivalent check elsewhere (e.g. the ability's
 * execute_callback) if validation logic must also work on 6.9/7.0.
 *
 * This file has no dependency on anything else in this plugin and can be
 * copied as-is into other plugins that register abilities and want the same
 * 6.9-7.1 safety net. Requiring it is enough — it installs its polyfills
 * immediately (see the init() call at the bottom of this file).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ndizi_Abilities_Compat' ) ) :

	class Ndizi_Abilities_Compat {

		/**
		 * Initialized flag.
		 *
		 * @var bool Guards init() so its function_exists()-gated declarations only run once.
		 */
		private static $initialized = false;

		/**
		 * Whether the Abilities API is present at all (WP 6.9+, or the standalone plugin/package).
		 *
		 * @return bool
		 */
		public static function is_available() {
			return function_exists( 'wp_register_ability' );
		}

		/**
		 * Whether the running core supports the WP 7.1 Abilities API additions natively.
		 *
		 * During the 7.1 dev cycle $wp_version looks like '7.1-alpha-58900' or
		 * '7.1-beta1-59001' or '7.1-RC1-59200' — a plain version_compare() treats
		 * those pre-release suffixes as *older* than '7.1', which would wrongly
		 * report false (and double up the wp_ability_invoked bridge, see
		 * maybe_bridge_ability_invoked_action()) for the entire beta/RC cycle.
		 * Strip everything from the first hyphen so betas/RCs of 7.1 compare as 7.1.
		 *
		 * @return bool
		 */
		public static function supports_7_1() {
			if ( ! self::is_available() ) {
				return false;
			}

			$version = isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '0';
			$version = preg_replace( '/-.*/', '', $version );

			return version_compare( $version, '7.1', '>=' );
		}

		/**
		 * Installs the polyfills/bridges described in the class docblock. Idempotent
		 * and safe to call from multiple places (or not at all — it also runs once
		 * automatically when this file is required).
		 *
		 * @return void
		 */
		public static function init() {
			if ( self::$initialized || ! self::is_available() ) {
				return;
			}
			self::$initialized = true;

			self::maybe_polyfill_json_schema_functions();
			self::maybe_bridge_ability_invoked_action();
		}

		/**
		 * Declares wp_prepare_json_schema_for_client() and
		 * wp_get_json_schema_allowed_keywords() as real global functions when core
		 * (pre-7.1) doesn't already provide them, backed by this class's own
		 * implementation of the documented behavior.
		 *
		 * @return void
		 */
		private static function maybe_polyfill_json_schema_functions() {
			if ( ! function_exists( 'wp_get_json_schema_allowed_keywords' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
				function wp_get_json_schema_allowed_keywords( $schema_profile = 'draft-04' ) {
					// phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed
					return Ndizi_Abilities_Compat::get_json_schema_allowed_keywords( $schema_profile );
				}
			}

			if ( ! function_exists( 'wp_prepare_json_schema_for_client' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
				function wp_prepare_json_schema_for_client( $schema, $schema_profile = 'draft-04' ) {
					// phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed
					return Ndizi_Abilities_Compat::prepare_json_schema_for_client( $schema, $schema_profile );
				}
			}
		}

		/**
		 * Bridges wp_ability_invoked onto wp_before_execute_ability when core
		 * doesn't fire wp_ability_invoked natively (pre-7.1). No-op on 7.1+, where
		 * core already fires it and a bridge would double it up.
		 *
		 * @return void
		 */
		private static function maybe_bridge_ability_invoked_action() {
			if ( self::supports_7_1() ) {
				return;
			}

			add_action(
				'wp_before_execute_ability',
				function ( $input, $ability ) {
					$name = ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ) ? $ability->get_name() : '';
					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					do_action( 'wp_ability_invoked', $name, $input, $ability );
				},
				10,
				2
			);
		}

		/**
		 * Fallback implementation of WP 7.1's wp_get_json_schema_allowed_keywords(),
		 * used by the polyfilled wp_get_json_schema_allowed_keywords() on pre-7.1 core.
		 *
		 * @param string $schema_profile 'draft-04' (default) or 'rest-api'.
		 * @return string[] Allowed schema keywords for the given profile.
		 */
		public static function get_json_schema_allowed_keywords( $schema_profile = 'draft-04' ) {
			$keywords = array(
				'type',
				'title',
				'description',
				'default',
				'enum',
				'const',
				'format',
				'items',
				'properties',
				'additionalProperties',
				'required',
				'minimum',
				'maximum',
				'minLength',
				'maxLength',
				'minItems',
				'maxItems',
				'pattern',
				'oneOf',
				'allOf',
				'anyOf',
			);

			if ( 'rest-api' === $schema_profile ) {
				$keywords[] = 'context';
				$keywords[] = 'readonly';
			}

			/**
			 * Filters the JSON Schema keywords retained by wp_prepare_json_schema_for_client().
			 *
			 * Same filter name as WP 7.1 core, so a filter callback written against
			 * core's version behaves identically when this fallback is in use.
			 *
			 * @param string[] $keywords       Allowed keywords for the profile.
			 * @param string   $schema_profile 'draft-04' or 'rest-api'.
			 */
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			return apply_filters( 'wp_json_schema_allowed_keywords', $keywords, $schema_profile );
		}

		/**
		 * Fallback implementation of WP 7.1's wp_prepare_json_schema_for_client(),
		 * used by the polyfilled wp_prepare_json_schema_for_client() on pre-7.1 core.
		 *
		 * Converts internal WordPress schema conventions into portable JSON Schema
		 * draft-04 output: strips server-only sanitize_callback/validate_callback/
		 * arg_options keys, converts legacy property-level `'required' => true` into
		 * a Draft 4-style `required` array, and normalizes empty-array defaults on
		 * object-typed schemas to serialize as `{}` rather than `[]`.
		 *
		 * @param array  $schema         Schema to prepare. Non-array input is returned unmodified.
		 * @param string $schema_profile 'draft-04' (default) or 'rest-api'.
		 * @return array|mixed Prepared schema, or the original value if not an array.
		 */
		public static function prepare_json_schema_for_client( $schema, $schema_profile = 'draft-04' ) {
			if ( ! is_array( $schema ) ) {
				return $schema;
			}

			unset( $schema['sanitize_callback'], $schema['validate_callback'], $schema['arg_options'] );

			if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
				$required = ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) ? $schema['required'] : array();

				foreach ( $schema['properties'] as $property_name => $property ) {
					if ( ! is_array( $property ) ) {
						continue;
					}

					if ( ! empty( $property['required'] ) && true === $property['required'] ) {
						$required[] = $property_name;
					}
					unset( $property['required'] );

					$schema['properties'][ $property_name ] = self::prepare_json_schema_for_client( $property, $schema_profile );
				}

				if ( ! empty( $required ) ) {
					$schema['required'] = array_values( array_unique( $required ) );
				}
			}

			if ( isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
				$schema['items'] = self::prepare_json_schema_for_client( $schema['items'], $schema_profile );
			}

			foreach ( array( 'oneOf', 'allOf', 'anyOf' ) as $combiner ) {
				if ( isset( $schema[ $combiner ] ) && is_array( $schema[ $combiner ] ) ) {
					foreach ( $schema[ $combiner ] as $index => $sub_schema ) {
						$schema[ $combiner ][ $index ] = self::prepare_json_schema_for_client( $sub_schema, $schema_profile );
					}
				}
			}

			if ( isset( $schema['type'] ) && 'object' === $schema['type']
				&& isset( $schema['default'] ) && is_array( $schema['default'] ) && empty( $schema['default'] ) ) {
				$schema['default'] = new stdClass();
			}

			return $schema;
		}

		/**
		 * Registers an ability input-validation filter, if the running core supports
		 * it (WP 7.1+). No-op on older core — see the class docblock for why this
		 * one can't be bridged like wp_ability_invoked can.
		 *
		 * @param string   $ability_name Ability name to scope to, or '' to run for every ability.
		 * @param callable $callback     function( $valid, $value, $ability_name ) — return true or a WP_Error.
		 * @return void
		 */
		public static function maybe_add_validation_filter( $ability_name, $callback ) {
			if ( ! self::supports_7_1() || ! is_callable( $callback ) ) {
				return;
			}

			add_filter(
				'wp_ability_validate_input',
				function ( $valid, $value, $name ) use ( $ability_name, $callback ) {
					if ( '' !== $ability_name && $name !== $ability_name ) {
						return $valid;
					}
					return call_user_func( $callback, $valid, $value, $name );
				},
				10,
				3
			);
		}

		/**
		 * Builds a 'meta' array for wp_register_ability() covering REST/MCP exposure
		 * across 6.9-7.1+: sets the legacy show_in_rest + mcp.public pair that 6.9/7.0
		 * require, plus the unified meta.public flag that 7.1 also accepts. Unknown
		 * meta keys are inert on older core, so this is safe on 6.9+ regardless of
		 * which version is actually running.
		 *
		 * @param bool $is_public   Whether the ability should be publicly exposed via REST/MCP.
		 * @param bool $is_readonly Whether to mark the ability as informational/side-effect-free.
		 * @return array
		 */
		public static function exposure_meta( $is_public = true, $is_readonly = false ) {
			$meta = array(
				'show_in_rest' => $is_public,
				'public'       => $is_public,
				'mcp'          => array(
					'public' => $is_public,
					'type'   => 'tool',
				),
			);

			if ( $is_readonly ) {
				$meta['readonly'] = true;
			}

			return $meta;
		}
	}

endif;

// Install the polyfills/bridges immediately: requiring this file is enough,
// callers don't need to remember to call Ndizi_Abilities_Compat::init().
Ndizi_Abilities_Compat::init();
