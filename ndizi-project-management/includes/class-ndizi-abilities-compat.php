<?php
/**
 * Redistributable compatibility shim for the WordPress Abilities API.
 *
 * The Abilities API shipped in WP 6.9; WP 7.1 added new hooks (wp_ability_invoked,
 * wp_ability_validate_input/output) and a unified meta.public exposure flag. None of
 * that can be truly polyfilled onto older core — this class only owns the hooks it
 * fires itself, it can't inject calls into WP_Ability::execute() on a version that
 * doesn't already do so. What it can do is detect what's available and give callers
 * a single API that degrades gracefully instead of silently doing nothing (or fatally
 * erroring) on 6.9/7.0.
 *
 * This file has no dependency on anything else in this plugin and can be copied
 * as-is into other plugins that register abilities and want the same 6.9-7.1 safety
 * net.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ndizi_Abilities_Compat' ) ) :

	class Ndizi_Abilities_Compat {

		/**
		 * Whether the Abilities API is present at all (WP 6.9+, or the standalone plugin/package).
		 *
		 * @return bool
		 */
		public static function is_available() {
			return function_exists( 'wp_register_ability' );
		}

		/**
		 * Whether the running core supports the WP 7.1 Abilities API additions.
		 *
		 * @return bool
		 */
		public static function supports_7_1() {
			if ( ! self::is_available() ) {
				return false;
			}
			return version_compare( $GLOBALS['wp_version'], '7.1', '>=' );
		}

		/**
		 * Registers a normalized "ability invoked" listener that works on both
		 * pre-7.1 core and 7.1+.
		 *
		 * On 7.1+ this uses wp_ability_invoked, which fires before normalization,
		 * validation, and permission checks — every invocation attempt is seen.
		 * On 6.9/7.0, where that hook doesn't exist, it falls back to
		 * wp_before_execute_ability, the earliest hook available there; that one
		 * only fires for attempts that already passed permission checks, so calls
		 * blocked by a permission_callback won't be seen pre-7.1.
		 *
		 * The callback always receives ( string $ability_name, mixed $input,
		 * WP_Ability|null $ability ). $ability is populated on both paths.
		 *
		 * @param callable $callback Listener to invoke on ability call attempts.
		 * @return void
		 */
		public static function on_ability_invoked( $callback ) {
			if ( ! self::is_available() || ! is_callable( $callback ) ) {
				return;
			}

			if ( self::supports_7_1() ) {
				add_action(
					'wp_ability_invoked',
					function ( $ability_name, $input, $ability = null ) use ( $callback ) {
						call_user_func( $callback, $ability_name, $input, $ability );
					},
					10,
					3
				);
				return;
			}

			add_action(
				'wp_before_execute_ability',
				function ( $input, $ability ) use ( $callback ) {
					$name = ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ) ? $ability->get_name() : '';
					call_user_func( $callback, $name, $input, $ability );
				},
				10,
				2
			);
		}

		/**
		 * Registers an ability input-validation filter, if the running core supports
		 * it (WP 7.1+). No-op on older core.
		 *
		 * Because this silently does nothing pre-7.1, do not use it as the only
		 * place a piece of validation logic lives — keep an equivalent check in the
		 * ability's execute_callback (or input_schema, where expressible) so
		 * validation still happens on 6.9/7.0. Treat this as an enhancement layer
		 * for 7.1+, not a replacement.
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
		 * @param bool $public   Whether the ability should be publicly exposed via REST/MCP.
		 * @param bool $readonly Whether to mark the ability as informational/side-effect-free.
		 * @return array
		 */
		public static function exposure_meta( $public = true, $readonly = false ) {
			$meta = array(
				'show_in_rest' => $public,
				'public'       => $public,
				'mcp'          => array(
					'public' => $public,
					'type'   => 'tool',
				),
			);

			if ( $readonly ) {
				$meta['readonly'] = true;
			}

			return $meta;
		}
	}

endif;
