<?php
/**
 * Plugin Name: Big Emoji Comments
 * Plugin URI:  https://github.com/georgestephanis/big-emoji-comments
 * Description: If someone leaves a comment comprised entirely of emoji, make it bigger.
 * Version: 1.2.0
 * Author:      George Stephanis
 * Author URI:  https://georgestephanis.wordpress.com/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires PHP: 7.2
 * Text Domain: big-emoji-comments
 *
 * @package BigEmojiComments
 */

if ( ! defined( 'BIG_EMOJI_SINGLE_SIZE' ) ) {
	define( 'BIG_EMOJI_SINGLE_SIZE', 500 );
}
if ( ! defined( 'BIG_EMOJI_MULTI_SIZE' ) ) {
	define( 'BIG_EMOJI_MULTI_SIZE', 300 );
}
if ( ! defined( 'BIG_EMOJI_DEFAULT_SIZE' ) ) {
	define( 'BIG_EMOJI_DEFAULT_SIZE', 200 );
}

if ( ! function_exists( 'big_emoji_comments' ) ) {
	/**
	 * Render emoji bigger if the comment contains only emojis.
	 *
	 * @param string $content The comment content.
	 * @return string Modified comment content with big emoji styles if applicable.
	 */
	function big_emoji_comments( $content ) {
		$no_markup = trim( wp_kses( $content, array() ) );

		$custom_emojis      = get_option( 'big_emoji_custom_emojis', array() );
		$found_custom_count = 0;
		$temp_no_markup     = $no_markup;

		// Extract custom emoji codes.
		preg_match_all( '/:([a-zA-Z0-9_\-]+):/', $no_markup, $custom_matches );
		if ( ! empty( $custom_matches[1] ) ) {
			foreach ( $custom_matches[1] as $match ) {
				if ( isset( $custom_emojis[ $match ] ) ) {
					++$found_custom_count;
					$temp_no_markup = str_replace( ':' . $match . ':', '', $temp_no_markup );
				}
			}
		}

		$all_emoji_regex = '/^[\s' .
			// Regex generated from https://www.unicode.org/Public/UCD/latest/ucd/emoji/emoji-data.txt.
			'\x{0023}' .
			'\x{002A}' .
			'\x{0030}-\x{0039}' .
			'\x{00A9}' .
			'\x{00AE}' .
			'\x{200D}' .
			'\x{203C}' .
			'\x{2049}' .
			'\x{20E3}' .
			'\x{2122}' .
			'\x{2139}' .
			'\x{2194}-\x{2199}' .
			'\x{21A9}-\x{21AA}' .
			'\x{231A}-\x{231B}' .
			'\x{2328}' .
			'\x{23CF}' .
			'\x{23E9}-\x{23F3}' .
			'\x{23F8}-\x{23FA}' .
			'\x{24C2}' .
			'\x{25AA}-\x{25AB}' .
			'\x{25B6}' .
			'\x{25C0}' .
			'\x{25FB}-\x{25FE}' .
			'\x{2600}-\x{2604}' .
			'\x{260E}' .
			'\x{2611}' .
			'\x{2614}-\x{2615}' .
			'\x{2618}' .
			'\x{261D}' .
			'\x{2620}' .
			'\x{2622}-\x{2623}' .
			'\x{2626}' .
			'\x{262A}' .
			'\x{262E}-\x{262F}' .
			'\x{2638}-\x{263A}' .
			'\x{2640}' .
			'\x{2642}' .
			'\x{2648}-\x{2653}' .
			'\x{265F}-\x{2660}' .
			'\x{2663}' .
			'\x{2665}-\x{2666}' .
			'\x{2668}' .
			'\x{267B}' .
			'\x{267E}-\x{267F}' .
			'\x{2692}-\x{2697}' .
			'\x{2699}' .
			'\x{269B}-\x{269C}' .
			'\x{26A0}-\x{26A1}' .
			'\x{26A7}' .
			'\x{26AA}-\x{26AB}' .
			'\x{26B0}-\x{26B1}' .
			'\x{26BD}-\x{26BE}' .
			'\x{26C4}-\x{26C5}' .
			'\x{26C8}' .
			'\x{26CE}-\x{26CF}' .
			'\x{26D1}' .
			'\x{26D3}-\x{26D4}' .
			'\x{26E9}-\x{26EA}' .
			'\x{26F0}-\x{26F5}' .
			'\x{26F7}-\x{26FA}' .
			'\x{26FD}' .
			'\x{2702}' .
			'\x{2705}' .
			'\x{2708}-\x{270D}' .
			'\x{270F}' .
			'\x{2712}' .
			'\x{2714}' .
			'\x{2716}' .
			'\x{271D}' .
			'\x{2721}' .
			'\x{2728}' .
			'\x{2733}-\x{2734}' .
			'\x{2744}' .
			'\x{2747}' .
			'\x{274C}' .
			'\x{274E}' .
			'\x{2753}-\x{2755}' .
			'\x{2757}' .
			'\x{2763}-\x{2764}' .
			'\x{2795}-\x{2797}' .
			'\x{27A1}' .
			'\x{27B0}' .
			'\x{27BF}' .
			'\x{2934}-\x{2935}' .
			'\x{2B05}-\x{2B07}' .
			'\x{2B1B}-\x{2B1C}' .
			'\x{2B50}' .
			'\x{2B55}' .
			'\x{3030}' .
			'\x{303D}' .
			'\x{3297}' .
			'\x{3299}' .
			'\x{FE0F}' .
			'\x{1F004}' .
			'\x{1F02C}-\x{1F02F}' .
			'\x{1F094}-\x{1F09F}' .
			'\x{1F0AF}-\x{1F0B0}' .
			'\x{1F0C0}' .
			'\x{1F0CF}-\x{1F0D0}' .
			'\x{1F0F6}-\x{1F0FF}' .
			'\x{1F170}-\x{1F171}' .
			'\x{1F17E}-\x{1F17F}' .
			'\x{1F18E}' .
			'\x{1F191}-\x{1F19A}' .
			'\x{1F1AE}-\x{1F1FF}' .
			'\x{1F201}-\x{1F20F}' .
			'\x{1F21A}' .
			'\x{1F22F}' .
			'\x{1F232}-\x{1F23A}' .
			'\x{1F23C}-\x{1F23F}' .
			'\x{1F249}-\x{1F25F}' .
			'\x{1F266}-\x{1F321}' .
			'\x{1F324}-\x{1F393}' .
			'\x{1F396}-\x{1F397}' .
			'\x{1F399}-\x{1F39B}' .
			'\x{1F39E}-\x{1F3F0}' .
			'\x{1F3F3}-\x{1F3F5}' .
			'\x{1F3F7}-\x{1F4FD}' .
			'\x{1F4FF}-\x{1F53D}' .
			'\x{1F549}-\x{1F54E}' .
			'\x{1F550}-\x{1F567}' .
			'\x{1F56F}-\x{1F570}' .
			'\x{1F573}-\x{1F57A}' .
			'\x{1F587}' .
			'\x{1F58A}-\x{1F58D}' .
			'\x{1F590}' .
			'\x{1F595}-\x{1F596}' .
			'\x{1F5A4}-\x{1F5A5}' .
			'\x{1F5A8}' .
			'\x{1F5B1}-\x{1F5B2}' .
			'\x{1F5BC}' .
			'\x{1F5C2}-\x{1F5C4}' .
			'\x{1F5D1}-\x{1F5D3}' .
			'\x{1F5DC}-\x{1F5DE}' .
			'\x{1F5E1}' .
			'\x{1F5E3}' .
			'\x{1F5E8}' .
			'\x{1F5EF}' .
			'\x{1F5F3}' .
			'\x{1F5FA}-\x{1F64F}' .
			'\x{1F680}-\x{1F6C5}' .
			'\x{1F6CB}-\x{1F6D2}' .
			'\x{1F6D5}-\x{1F6E5}' .
			'\x{1F6E9}' .
			'\x{1F6EB}-\x{1F6F0}' .
			'\x{1F6F3}-\x{1F6FF}' .
			'\x{1F7DA}-\x{1F7FF}' .
			'\x{1F80C}-\x{1F80F}' .
			'\x{1F848}-\x{1F84F}' .
			'\x{1F85A}-\x{1F85F}' .
			'\x{1F888}-\x{1F88F}' .
			'\x{1F8AE}-\x{1F8AF}' .
			'\x{1F8BC}-\x{1F8BF}' .
			'\x{1F8C2}-\x{1F8CF}' .
			'\x{1F8D9}-\x{1F8FF}' .
			'\x{1F90C}-\x{1F93A}' .
			'\x{1F93C}-\x{1F945}' .
			'\x{1F947}-\x{1F9FF}' .
			'\x{1FA58}-\x{1FA5F}' .
			'\x{1FA6E}-\x{1FAFF}' .
			'\x{1FC00}-\x{1FFFD}' .
			'\x{E0020}-\x{E007F}' .
			']+$/u';

		$is_emoji_only = false;
		if ( '' === trim( $temp_no_markup ) && $found_custom_count > 0 ) {
			$is_emoji_only = true;
		} elseif ( preg_match( $all_emoji_regex, $temp_no_markup ) ) {
			$is_emoji_only = true;
		}

		if ( $is_emoji_only ) {
			$emoji_only = preg_replace( '/\s+/u', '', $temp_no_markup );
			if ( function_exists( 'grapheme_strlen' ) ) {
				$standard_count = grapheme_strlen( $emoji_only );
			} else {
				$standard_count = preg_match_all( '/\X/u', $emoji_only, $matches );
			}

			$char_count = $standard_count + $found_custom_count;

			$percent = BIG_EMOJI_DEFAULT_SIZE;
			switch ( $char_count ) {
				case 1:
					$percent = BIG_EMOJI_SINGLE_SIZE;
					break;
				case 2:
				case 3:
				case 4:
					$percent = BIG_EMOJI_MULTI_SIZE;
					break;
			}

			$percent = apply_filters( 'big_emoji_comments_percent', $percent, $no_markup );

			// Replace custom emoji codes with image tags.
			$final_content = $content;
			foreach ( $custom_emojis as $name => $emoji_data ) {
				$img_tag       = sprintf(
					'<img src="%1$s" alt=":%2$s:" class="emoji custom-emoji" style="height: 1.25em; width: auto; max-height: 1.25em; vertical-align: -0.2em; display: inline-block;" />',
					esc_url( $emoji_data['url'] ),
					esc_attr( $name )
				);
				$final_content = str_replace( ':' . $name . ':', $img_tag, $final_content );
			}

			$output = sprintf( '<span class="big-emoji" style="font-size:%1$d%%;">%2$s</span>', $percent, $final_content );
			return apply_filters( 'big_emoji_comments_output', $output, $final_content, $percent, $no_markup );
		}

		// Even if not emoji-only, replace custom emoji codes with images.
		$final_content = $content;
		foreach ( $custom_emojis as $name => $emoji_data ) {
			$img_tag       = sprintf(
				'<img src="%1$s" alt=":%2$s:" class="emoji custom-emoji" style="height: 1.25em; width: auto; max-height: 1.25em; vertical-align: -0.2em; display: inline-block;" />',
				esc_url( $emoji_data['url'] ),
				esc_attr( $name )
			);
			$final_content = str_replace( ':' . $name . ':', $img_tag, $final_content );
		}

		return $final_content;
	}
}

add_filter( 'comment_text', 'big_emoji_comments' );

/**
 * Register emoji icon collection and icons if the Icon Registration API exists.
 */
function big_emoji_comments_register_icons() {
	if ( ! function_exists( 'wp_register_icon_collection' ) || ! function_exists( 'wp_register_icon' ) ) {
		return;
	}

	wp_register_icon_collection(
		'big-emoji-comments',
		array(
			'label'       => __( 'Emoji Reactions', 'big-emoji-comments' ),
			'description' => __( 'Emoji icons for reactions and big comments.', 'big-emoji-comments' ),
		)
	);

	$icons = array(
		'thumbs-up' => array(
			'label'   => __( 'Thumbs Up', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>',
		),
		'heart'     => array(
			'label'   => __( 'Heart', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>',
		),
		'laughing'  => array(
			'label'   => __( 'Laughing Face', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3.5-9c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm7 0c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5 14.67 11 15.5 11zm-7 3c.87 2.21 3.01 4 5.5 4s4.63-1.79 5.5-4h-11z"/></svg>',
		),
		'surprised' => array(
			'label'   => __( 'Surprised Face', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-11c-1.1 0-2 .9-2 2v2c0 1.1.9 2 2 2s2-.9 2-2v-2c0-1.1-.9-2-2-2z"/></svg>',
		),
		'crying'    => array(
			'label'   => __( 'Crying Face', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3.5-9c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm7 0c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5 14.67 11 15.5 11zm-5.5 4c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1zm3-1.5c-1.5 0-2.77.83-3.41 2.05-.12.24-.04.53.18.67.22.14.52.07.66-.15.48-.91 1.43-1.57 2.57-1.57s2.09.66 2.57 1.57c.14.22.44.29.66.15.22-.14.3-.43.18-.67-.64-1.22-1.91-2.05-3.41-2.05z"/></svg>',
		),
		'angry'     => array(
			'label'   => __( 'Angry Face', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-3-9c-.83 0-1.5-.67-1.5-1.5S8.17 8 9 8s1.5.67 1.5 1.5S9.83 11 9 11zm6 0c-.83 0-1.5-.67-1.5-1.5S14.17 8 15 8s1.5.67 1.5 1.5S15.83 11 15 11zm-5.5 3h5c-.28 1.69-1.74 3-3.5 3s-3.22-1.31-3.5-3z"/></svg>',
		),
		'fire'      => array(
			'label'   => __( 'Fire', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 2.69c.05 1.01-.26 1.89-.92 2.63-1.21 1.36-2.52 2.62-3.18 4.41-.69 1.89-.32 3.66.96 5.17.24.28.25.43.02.73-1.07 1.38-1.57 2.97-1.42 4.71.18 2.05 1.15 3.69 3.03 4.58.55.26 1.14.41 1.76.46 2.37.19 4.39-.77 5.76-2.73 1.16-1.66 1.34-3.48.51-5.35-.14-.31-.08-.47.16-.69 1.48-1.37 2.27-3.08 2.12-5.11-.11-1.47-.79-2.67-1.92-3.62-.25-.21-.36-.18-.46.12-.55 1.63-1.71 2.53-3.28 3.12-.29.11-.47.05-.62-.23-1.12-2.14-.94-4.28-.27-6.42.06-.18.06-.37-.04-.54-.15-.27-.4-.36-.69-.17zm-1.89 16.5c-.3-.21-.52-.46-.66-.75-.41-.85-.16-1.84.58-2.39.26-.19.53-.35.81-.5.77-.42 1.2-1.05 1.25-1.92.02-.32.07-.36.35-.19 1.28.77 1.72 2.09 1.16 3.49-.33.82-1 1.33-1.84 1.51-.55.12-1.12.01-1.65-.25z"/></svg>',
		),
		'star'      => array(
			'label'   => __( 'Star', 'big-emoji-comments' ),
			'content' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>',
		),
	);

	foreach ( $icons as $slug => $args ) {
		wp_register_icon( "big-emoji-comments/{$slug}", $args );
	}
}
add_action( 'init', 'big_emoji_comments_register_icons' );

/**
 * Get the list of registered reaction emojis and their corresponding labels.
 *
 * @return array Emoji character to icon slug mapping.
 */
function big_emoji_comments_get_reaction_emojis() {
	$defaults = array(
		'👍'  => 'thumbs-up',
		'❤️' => 'heart',
		'😄'  => 'laughing',
		'😮'  => 'surprised',
		'😢'  => 'crying',
		'😡'  => 'angry',
		'🔥'  => 'fire',
		'⭐'  => 'star',
	);

	$custom_emojis = get_option( 'big_emoji_custom_emojis', array() );
	foreach ( $custom_emojis as $name => $emoji_data ) {
		$defaults[ ':' . $name . ':' ] = $name;
	}

	return $defaults;
}

/**
 * Register the emoji reactions block and its assets.
 */
function big_emoji_comments_register_block() {
	// Register the editor script.
	wp_register_script(
		'big-emoji-comments-editor',
		plugins_url( 'assets/editor.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element' ),
		'1.2.0',
		true
	);

	// Register block frontend styles.
	wp_register_style(
		'big-emoji-comments-style',
		plugins_url( 'assets/style.css', __FILE__ ),
		array(),
		'1.2.0'
	);

	// Register the block.
	register_block_type(
		'big-emoji-comments/reactions',
		array(
			'editor_script'   => 'big-emoji-comments-editor',
			'style'           => 'big-emoji-comments-style',
			'render_callback' => 'big_emoji_comments_render_reactions_block',
		)
	);
}
add_action( 'init', 'big_emoji_comments_register_block' );

/**
 * Render callback for the Emoji Reactions block.
 *
 * @return string Block HTML.
 */
function big_emoji_comments_render_reactions_block() {
	$post_id = get_the_ID();
	if ( ! $post_id ) {
		return '';
	}

	// Only load frontend script if block is rendered.
	wp_enqueue_script(
		'big-emoji-comments-frontend',
		plugins_url( 'assets/frontend.js', __FILE__ ),
		array(),
		'1.2.0',
		true
	);

	wp_localize_script(
		'big-emoji-comments-frontend',
		'bigEmojiCommentsSettings',
		array(
			'restUrl' => esc_url_raw( rest_url( 'big-emoji-comments/v1/react' ) ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
		)
	);

	$reaction_emojis = big_emoji_comments_get_reaction_emojis();
	$counts          = big_emoji_comments_get_reaction_counts( $post_id );
	$custom_emojis   = get_option( 'big_emoji_custom_emojis', array() );

	$output = '<div class="big-emoji-reactions" data-post-id="' . esc_attr( $post_id ) . '">';

	foreach ( $reaction_emojis as $emoji => $slug ) {
		$count = isset( $counts[ $emoji ] ) ? $counts[ $emoji ] : 0;

		$is_custom     = ( 0 === strpos( $emoji, ':' ) && ':' === substr( $emoji, -1 ) );
		$emoji_display = '';

		if ( $is_custom ) {
			$name = trim( $emoji, ':' );
			if ( isset( $custom_emojis[ $name ] ) ) {
				$emoji_display = sprintf(
					'<img src="%1$s" class="emoji-img" alt="%2$s" style="height: 20px; width: auto; max-height: 20px; vertical-align: middle; display: inline-block;" />',
					esc_url( $custom_emojis[ $name ]['url'] ),
					esc_attr( $name )
				);
			} else {
				continue;
			}
		} else {
			$emoji_display = sprintf( '<span class="emoji-icon">%s</span>', esc_html( $emoji ) );
		}

		$output .= sprintf(
			'<button class="big-emoji-reaction-button" data-emoji="%1$s" title="%2$s">
				%3$s
				<span class="emoji-count">%4$d</span>
			</button>',
			esc_attr( $emoji ),
			esc_attr( ucfirst( str_replace( '-', ' ', $slug ) ) ),
			$emoji_display,
			esc_html( $count )
		);
	}

	$output .= '</div>';

	return $output;
}

/**
 * Retrieve reaction counts for a given post.
 *
 * @param int $post_id The post ID.
 * @return array Array of emoji reactions with counts.
 */
function big_emoji_comments_get_reaction_counts( $post_id ) {
	global $wpdb;

	$reaction_emojis = big_emoji_comments_get_reaction_emojis();
	$emojis          = array_keys( $reaction_emojis );

	if ( empty( $emojis ) ) {
		return array();
	}

	$placeholders = implode( ',', array_fill( 0, count( $emojis ), '%s' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$query = $wpdb->prepare(
		'SELECT comment_content, COUNT(*) as count 
         FROM ' . $wpdb->comments . ' 
         WHERE comment_post_ID = %d 
           AND comment_approved = \'1\' 
           AND comment_content IN (' . $placeholders . ')
         GROUP BY comment_content',
		array_merge( array( $post_id ), $emojis )
	);

	$results = $wpdb->get_results( $query );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

	$counts = array_fill_keys( $emojis, 0 );
	foreach ( $results as $row ) {
		if ( isset( $counts[ $row->comment_content ] ) ) {
			$counts[ $row->comment_content ] = (int) $row->count;
		}
	}

	return $counts;
}

/**
 * Register custom REST API route.
 */
function big_emoji_comments_register_rest_route() {
	register_rest_route(
		'big-emoji-comments/v1',
		'/react',
		array(
			'methods'             => 'POST',
			'callback'            => 'big_emoji_comments_handle_react_api',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'big_emoji_comments_register_rest_route' );

/**
 * Handle POST request to reaction REST API.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_REST_Response|WP_Error REST response or error object.
 */
function big_emoji_comments_handle_react_api( $request ) {
	$params  = $request->get_json_params();
	$post_id = isset( $params['post_id'] ) ? (int) $params['post_id'] : 0;
	$emoji   = isset( $params['emoji'] ) ? sanitize_text_field( $params['emoji'] ) : '';

	if ( ! $post_id || ! $emoji ) {
		return new WP_Error( 'invalid_data', __( 'Invalid post ID or emoji reaction.', 'big-emoji-comments' ), array( 'status' => 400 ) );
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'invalid_post', __( 'Post not found.', 'big-emoji-comments' ), array( 'status' => 404 ) );
	}

	if ( ! comments_open( $post_id ) ) {
		return new WP_Error( 'comments_closed', __( 'Comments are closed for this post.', 'big-emoji-comments' ), array( 'status' => 403 ) );
	}

	$reaction_emojis = big_emoji_comments_get_reaction_emojis();
	if ( ! isset( $reaction_emojis[ $emoji ] ) ) {
		return new WP_Error( 'invalid_reaction', __( 'Unsupported emoji reaction.', 'big-emoji-comments' ), array( 'status' => 400 ) );
	}

	$user        = wp_get_current_user();
	$commentdata = array(
		'comment_post_ID'  => $post_id,
		'comment_content'  => $emoji,
		'comment_type'     => 'comment',
		'comment_approved' => 1,
	);

	if ( $user->exists() ) {
		$commentdata['user_id']              = $user->ID;
		$commentdata['comment_author']       = $user->display_name;
		$commentdata['comment_author_email'] = $user->user_email;
	} else {
		$commentdata['comment_author']       = __( 'Anonymous Reaction', 'big-emoji-comments' );
		$commentdata['comment_author_email'] = 'reaction@example.com';
	}

	$comment_id = wp_insert_comment( $commentdata );

	if ( ! $comment_id ) {
		return new WP_Error( 'comment_failed', __( 'Failed to save reaction.', 'big-emoji-comments' ), array( 'status' => 500 ) );
	}

	$counts = big_emoji_comments_get_reaction_counts( $post_id );

	return rest_ensure_response( $counts );
}

/**
 * Register settings menu page.
 */
function big_emoji_comments_register_settings_menu() {
	add_options_page(
		__( 'Big Emoji Comments', 'big-emoji-comments' ),
		__( 'Big Emoji Comments', 'big-emoji-comments' ),
		'manage_options',
		'big-emoji-comments',
		'big_emoji_comments_render_settings_page'
	);
}
add_action( 'admin_menu', 'big_emoji_comments_register_settings_menu' );

/**
 * Enqueue scripts and styles for admin settings page.
 *
 * @param string $hook The current admin page hook.
 */
function big_emoji_comments_admin_assets( $hook ) {
	if ( 'settings_page_big-emoji-comments' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'big-emoji-comments-admin-style',
		plugins_url( 'assets/admin.css', __FILE__ ),
		array(),
		'1.2.0'
	);

	wp_enqueue_script(
		'big-emoji-comments-admin-script',
		plugins_url( 'assets/admin.js', __FILE__ ),
		array(),
		'1.2.0',
		true
	);

	$imported_emojis = get_option( 'big_emoji_custom_emojis', array() );

	wp_localize_script(
		'big-emoji-comments-admin-script',
		'bigEmojiAdminSettings',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'big_emoji_admin_nonce' ),
			'imported' => array_keys( $imported_emojis ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'big_emoji_comments_admin_assets' );

/**
 * Render admin settings page layout.
 */
function big_emoji_comments_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$custom_emojis = get_option( 'big_emoji_custom_emojis', array() );
	?>
	<div class="wrap big-emoji-settings-wrap">
		<h1><?php esc_html_e( 'Big Emoji Comments Settings', 'big-emoji-comments' ); ?></h1>
		
		<div class="privacy-notice">
			<p>
				<strong><?php esc_html_e( 'Privacy Notice:', 'big-emoji-comments' ); ?></strong>
				<?php esc_html_e( 'Searching custom emojis sends queries to slackmojis.com on-demand. No queries or network calls are made spontaneously or in the background.', 'big-emoji-comments' ); ?>
			</p>
		</div>

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Secondary menu', 'big-emoji-comments' ); ?>">
			<a href="#tab-manage" class="nav-tab nav-tab-active"><?php esc_html_e( 'Manage Custom Emojis', 'big-emoji-comments' ); ?></a>
			<a href="#tab-search" class="nav-tab"><?php esc_html_e( 'Search Slackmojis', 'big-emoji-comments' ); ?></a>
		</nav>

		<div id="tab-manage" class="tab-content">
			<h2><?php esc_html_e( 'Your Custom Emojis', 'big-emoji-comments' ); ?></h2>
			<div id="big-emoji-local-grid" class="emoji-grid">
				<?php if ( empty( $custom_emojis ) ) : ?>
					<p><?php esc_html_e( 'No custom emojis imported yet. Go to the "Search Slackmojis" tab to find and add some!', 'big-emoji-comments' ); ?></p>
				<?php else : ?>
					<?php foreach ( $custom_emojis as $emoji ) : ?>
						<div class="emoji-card">
							<img src="<?php echo esc_url( $emoji['url'] ); ?>" class="emoji-img" alt="<?php echo esc_attr( $emoji['name'] ); ?>" />
							<div class="emoji-name">:<?php echo esc_html( $emoji['name'] ); ?>:</div>
							<button class="button button-link-delete delete-btn" data-name="<?php echo esc_attr( $emoji['name'] ); ?>">
								<?php esc_html_e( 'Delete', 'big-emoji-comments' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>

		<div id="tab-search" class="tab-content" style="display: none;">
			<h2><?php esc_html_e( 'Search Custom Emojis from Slackmojis', 'big-emoji-comments' ); ?></h2>
			<form id="big-emoji-search-form" class="big-emoji-search-form">
				<input type="text" id="big-emoji-search-input" placeholder="<?php esc_attr_e( 'Search query...', 'big-emoji-comments' ); ?>" required />
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Search', 'big-emoji-comments' ); ?>" />
			</form>
			<div id="big-emoji-search-results" class="emoji-grid"></div>
		</div>
	</div>
	<?php
}

/**
 * AJAX Handler: Retrieve local custom emojis list.
 */
function big_emoji_comments_ajax_get_local() {
	check_ajax_referer( 'big_emoji_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'big-emoji-comments' ), 403 );
	}

	$custom_emojis = get_option( 'big_emoji_custom_emojis', array() );
	wp_send_json_success( $custom_emojis );
}
add_action( 'wp_ajax_big_emoji_get_local', 'big_emoji_comments_ajax_get_local' );

/**
 * AJAX Handler: Search emojis on Slackmojis.
 */
function big_emoji_comments_ajax_search() {
	check_ajax_referer( 'big_emoji_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'big-emoji-comments' ), 403 );
	}

	$query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
	if ( empty( $query ) ) {
		wp_send_json_error( __( 'Query is required.', 'big-emoji-comments' ) );
	}

	// Try to get cached Slackmojis list.
	$emojis = get_transient( 'big_emoji_slackmojis_cache' );
	if ( false === $emojis ) {
		$response = wp_remote_get( 'https://slackmojis.com/emojis.json' );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( __( 'Failed to fetch emojis from Slackmojis.', 'big-emoji-comments' ) );
		}

		$body   = wp_remote_retrieve_body( $response );
		$emojis = json_decode( $body, true );
		if ( ! is_array( $emojis ) ) {
			wp_send_json_error( __( 'Invalid response format from Slackmojis.', 'big-emoji-comments' ) );
		}

		set_transient( 'big_emoji_slackmojis_cache', $emojis, DAY_IN_SECONDS );
	}

	// Filter emojis by search query (case-insensitive name match).
	$filtered = array();
	foreach ( $emojis as $emoji ) {
		if ( isset( $emoji['name'] ) && false !== stripos( $emoji['name'], $query ) ) {
			$filtered[] = array(
				'name'      => $emoji['name'],
				'image_url' => $emoji['image_url'],
			);
			if ( count( $filtered ) >= 60 ) {
				break;
			}
		}
	}

	wp_send_json_success( $filtered );
}
add_action( 'wp_ajax_big_emoji_search', 'big_emoji_comments_ajax_search' );

/**
 * AJAX Handler: Import custom emoji image file locally.
 */
function big_emoji_comments_ajax_import() {
	check_ajax_referer( 'big_emoji_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'big-emoji-comments' ), 403 );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$url  = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

	if ( empty( $name ) || empty( $url ) ) {
		wp_send_json_error( __( 'Name and image URL are required.', 'big-emoji-comments' ) );
	}

	// Setup custom directory inside uploads.
	$upload_dir = wp_upload_dir();
	$custom_dir = $upload_dir['basedir'] . '/big-emoji-comments';
	if ( ! file_exists( $custom_dir ) ) {
		wp_mkdir_p( $custom_dir );
	}

	// Get file extension and construct local filename.
	$file_extension = pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
	if ( empty( $file_extension ) ) {
		$file_extension = 'png';
	}
	$filename  = sanitize_file_name( $name . '.' . $file_extension );
	$file_path = $custom_dir . '/' . $filename;
	$file_url  = $upload_dir['baseurl'] . '/big-emoji-comments/' . $filename;

	// Download and write file.
	$response = wp_remote_get( $url );
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		wp_send_json_error( __( 'Failed to download emoji image.', 'big-emoji-comments' ) );
	}

	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}

	$written = $wp_filesystem->put_contents( $file_path, wp_remote_retrieve_body( $response ) );
	if ( ! $written ) {
		wp_send_json_error( __( 'Failed to write emoji file locally.', 'big-emoji-comments' ) );
	}

	// Store emoji details in database.
	$custom_emojis          = get_option( 'big_emoji_custom_emojis', array() );
	$custom_emojis[ $name ] = array(
		'name' => $name,
		'url'  => $file_url,
		'path' => $file_path,
	);
	update_option( 'big_emoji_custom_emojis', $custom_emojis );

	wp_send_json_success(
		array(
			'name' => $name,
			'url'  => $file_url,
		)
	);
}
add_action( 'wp_ajax_big_emoji_import', 'big_emoji_comments_ajax_import' );

/**
 * AJAX Handler: Delete custom emoji.
 */
function big_emoji_comments_ajax_delete() {
	check_ajax_referer( 'big_emoji_admin_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'Unauthorized.', 'big-emoji-comments' ), 403 );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( empty( $name ) ) {
		wp_send_json_error( __( 'Name is required.', 'big-emoji-comments' ) );
	}

	$custom_emojis = get_option( 'big_emoji_custom_emojis', array() );
	if ( isset( $custom_emojis[ $name ] ) ) {
		$file_path = $custom_emojis[ $name ]['path'];

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem->exists( $file_path ) ) {
			$wp_filesystem->delete( $file_path );
		}

		unset( $custom_emojis[ $name ] );
		update_option( 'big_emoji_custom_emojis', $custom_emojis );
		wp_send_json_success();
	}

	wp_send_json_error( __( 'Emoji not found.', 'big-emoji-comments' ) );
}
add_action( 'wp_ajax_big_emoji_delete', 'big_emoji_comments_ajax_delete' );
