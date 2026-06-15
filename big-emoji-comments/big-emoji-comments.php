<?php
/**
 * Plugin Name: Big Emoji Comments
 * Plugin URI:  https://github.com/georgestephanis/big-emoji-comments
 * Description: If someone leaves a comment comprised entirely of emoji, make it bigger.
 * Version: 1.1.0
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

		if ( preg_match( $all_emoji_regex, $no_markup ) ) {
			$emoji_only = preg_replace( '/\s+/u', '', $no_markup );
			if ( function_exists( 'grapheme_strlen' ) ) {
				$char_count = grapheme_strlen( $emoji_only );
			} else {
				$char_count = preg_match_all( '/\X/u', $emoji_only, $matches );
			}

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

			$output = sprintf( '<span class="big-emoji" style="font-size:%1$d%%;">%2$s</span>', $percent, $content );
			return apply_filters( 'big_emoji_comments_output', $output, $content, $percent, $no_markup );
		}

		return $content;
	}
}

add_filter( 'comment_text', 'big_emoji_comments' );
