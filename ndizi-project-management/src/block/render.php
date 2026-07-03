<?php
/**
 * Block render template. Variables injected by WordPress:
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Explicitly enqueue localized assets as a fallback
wp_enqueue_style( 'ndizi-portal-style' );
wp_enqueue_script( 'ndizi-portal-script' );

// Parse colors from attributes (with fallbacks)
$ndizi_bg_color     = isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '#f8fafc';
$ndizi_text_color   = isset( $attributes['textColor'] ) ? $attributes['textColor'] : '#0f172a';
$ndizi_button_color = isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '#3A1A4D';
$ndizi_link_color   = isset( $attributes['linkColor'] ) ? $attributes['linkColor'] : '#7B4B9E';

// Generate a scoped unique ID for wrapper
$ndizi_wrapper_id = 'ndizi-portal-' . wp_rand( 1000, 9999 );

// Check if chosen background is a light color
$ndizi_is_light = Ndizi_Portal::is_color_light( $ndizi_bg_color );

// Configure secondary design colors and border widths/opacities dynamically based on luminance
$ndizi_card_bg      = $ndizi_is_light ? '#ffffff' : 'rgba(30, 41, 59, 0.7)';
$ndizi_border_color = $ndizi_is_light ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';
$ndizi_text_muted   = $ndizi_is_light ? '#475569' : '#94a3b8';
$ndizi_input_bg     = $ndizi_is_light ? '#ffffff' : 'rgba(15, 23, 42, 0.6)';
$ndizi_input_border = $ndizi_is_light ? 'rgba(0, 0, 0, 0.15)' : 'rgba(255, 255, 255, 0.1)';
$ndizi_item_bg      = $ndizi_is_light ? 'rgba(0, 0, 0, 0.02)' : 'rgba(15, 23, 42, 0.4)';
$ndizi_item_border  = $ndizi_is_light ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.06)';

// Build scoped styles. Collected as a plain CSS string and attached to the
// enqueued portal stylesheet via wp_add_inline_style() (below) rather than
// echoed inside a raw <style> tag.
$ndizi_inline_css  = "#{$ndizi_wrapper_id} {\n";
$ndizi_inline_css .= '  --ndizi-bg-main: ' . esc_attr( $ndizi_bg_color ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-bg-card: ' . esc_attr( $ndizi_card_bg ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-border-color: ' . esc_attr( $ndizi_border_color ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-text-main: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-text-muted: ' . esc_attr( $ndizi_text_muted ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-primary: ' . esc_attr( $ndizi_button_color ) . " !important;\n";
$ndizi_inline_css .= '  --ndizi-primary-hover: ' . esc_attr( $ndizi_button_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Override outer portal container
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-container {\n";
$ndizi_inline_css .= '  background-color: ' . esc_attr( $ndizi_bg_color ) . " !important;\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Fix company name / header h1 invisibility by overriding transparency
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-header h1 {\n";
$ndizi_inline_css .= "  background: none !important;\n";
$ndizi_inline_css .= '  -webkit-text-fill-color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Apply card background and borders
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-login-card,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-card,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-project-card,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-modal-content {\n";
$ndizi_inline_css .= '  background-color: ' . esc_attr( $ndizi_card_bg ) . " !important;\n";
$ndizi_inline_css .= '  border-color: ' . esc_attr( $ndizi_border_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Subtitles and headings
$ndizi_inline_css .= "#{$ndizi_wrapper_id} h2,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} h3,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} h4,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} label {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Fix completed/in_progress task titles being hardcoded to white (causing low contrast in light mode)
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-task-title {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Fix project description details having low contrast
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-project-details-text {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_muted ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Form controls background, border, text
$ndizi_inline_css .= "#{$ndizi_wrapper_id} input[type=text],\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} input[type=password],\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} select,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} textarea {\n";
$ndizi_inline_css .= '  background-color: ' . esc_attr( $ndizi_input_bg ) . " !important;\n";
$ndizi_inline_css .= '  border-color: ' . esc_attr( $ndizi_input_border ) . " !important;\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Buttons and primary accents (solid backgrounds)
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-btn,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-btn-comment-dialog,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-btn-sm,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-tabs a.ndizi-active-tab {\n";
$ndizi_inline_css .= '  background-color: ' . esc_attr( $ndizi_button_color ) . " !important;\n";
$ndizi_inline_css .= '  border-color: ' . esc_attr( $ndizi_button_color ) . " !important;\n";
$ndizi_inline_css .= "  color: #ffffff !important;\n";
$ndizi_inline_css .= "}\n";

// Links and text highlights
$ndizi_inline_css .= "#{$ndizi_wrapper_id} a {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_link_color ) . ";\n";
$ndizi_inline_css .= "}\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} a:hover {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_link_color ) . " !important;\n";
$ndizi_inline_css .= "  opacity: 0.85;\n";
$ndizi_inline_css .= "}\n";

// Accordion card header text highlights
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-project-card-header:hover h3,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-meta-hours strong {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_link_color ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Tasks and comment items
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-task-item,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-comment-item {\n";
$ndizi_inline_css .= '  background-color: ' . esc_attr( $ndizi_item_bg ) . " !important;\n";
$ndizi_inline_css .= '  border-color: ' . esc_attr( $ndizi_item_border ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Secondary buttons (Sign Out, File uploads)
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-portal-btn-secondary,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-file-upload-label {\n";
if ( $ndizi_is_light ) {
	$ndizi_inline_css .= "  background-color: #ffffff !important;\n";
	$ndizi_inline_css .= "  border-color: rgba(0, 0, 0, 0.15) !important;\n";
	$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
} else {
	$ndizi_inline_css .= "  background-color: transparent !important;\n";
	$ndizi_inline_css .= "  border-color: rgba(255, 255, 255, 0.15) !important;\n";
	$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_color ) . " !important;\n";
}
$ndizi_inline_css .= "}\n";

// Muted texts
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .subtitle,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .desc,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-task-due,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-project-summary-meta,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .ndizi-comment-date,\n";
$ndizi_inline_css .= "#{$ndizi_wrapper_id} .no-items {\n";
$ndizi_inline_css .= '  color: ' . esc_attr( $ndizi_text_muted ) . " !important;\n";
$ndizi_inline_css .= "}\n";

// Attach the scoped overrides to a dedicated token style handle so the CSS
// goes out through the styles pipeline instead of a raw inline <style> tag.
// A separate (src-less) handle is used rather than 'ndizi-portal-style'
// because that base stylesheet is enqueued on wp_enqueue_scripts and may
// already be printed in <head> by the time this render callback runs; a fresh
// handle enqueued here is emitted as a late style in the footer. Multiple
// portal blocks on one page each append their scoped rules to this handle.
if ( ! wp_style_is( 'ndizi-portal-inline', 'registered' ) ) {
	wp_register_style( 'ndizi-portal-inline', false, array( 'ndizi-portal-style' ), NDIZI_VERSION );
}
wp_enqueue_style( 'ndizi-portal-inline' );
wp_add_inline_style( 'ndizi-portal-inline', $ndizi_inline_css );

$ndizi_portal_content = Ndizi_Portal::render_portal_shortcode(
	array(
		'enableTaskSubmission' => isset( $attributes['enableTaskSubmission'] ) ? (bool) $attributes['enableTaskSubmission'] : true,
		'enableTimeOff'        => isset( $attributes['enableTimeOff'] ) ? (bool) $attributes['enableTimeOff'] : false,
	)
);

echo '<div id="' . esc_attr( $ndizi_wrapper_id ) . '" class="ndizi-custom-branded-portal">' . $ndizi_portal_content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
