<?php
/**
 * Block render template. Variables injected by WordPress:
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

// Explicitly enqueue localized assets as a fallback
wp_enqueue_style( 'ndizi-portal-style' );
wp_enqueue_script( 'ndizi-portal-script' );

// Parse colors from attributes (with fallbacks)
$bg_color     = isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '#f8fafc';
$text_color   = isset( $attributes['textColor'] ) ? $attributes['textColor'] : '#0f172a';
$button_color = isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '#4f46e5';
$link_color   = isset( $attributes['linkColor'] ) ? $attributes['linkColor'] : '#818cf8';

// Generate a scoped unique ID for wrapper
$wrapper_id = 'ndizi-portal-' . wp_rand( 1000, 9999 );

// Check if chosen background is a light color
$is_light = Ndizi_Portal::is_color_light( $bg_color );

// Configure secondary design colors and border widths/opacities dynamically based on luminance
$card_bg      = $is_light ? '#ffffff' : 'rgba(30, 41, 59, 0.7)';
$border_color = $is_light ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';
$text_muted   = $is_light ? '#475569' : '#94a3b8';
$input_bg     = $is_light ? '#ffffff' : 'rgba(15, 23, 42, 0.6)';
$input_border = $is_light ? 'rgba(0, 0, 0, 0.15)' : 'rgba(255, 255, 255, 0.1)';
$item_bg      = $is_light ? 'rgba(0, 0, 0, 0.02)' : 'rgba(15, 23, 42, 0.4)';
$item_border  = $is_light ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.06)';

// Build scoped styles
$inline_css  = "<style>\n";
$inline_css .= "#{$wrapper_id} {\n";
$inline_css .= '  --ndizi-bg-main: ' . esc_attr( $bg_color ) . " !important;\n";
$inline_css .= '  --ndizi-bg-card: ' . esc_attr( $card_bg ) . " !important;\n";
$inline_css .= '  --ndizi-border-color: ' . esc_attr( $border_color ) . " !important;\n";
$inline_css .= '  --ndizi-text-main: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= '  --ndizi-text-muted: ' . esc_attr( $text_muted ) . " !important;\n";
$inline_css .= '  --ndizi-primary: ' . esc_attr( $button_color ) . " !important;\n";
$inline_css .= '  --ndizi-primary-hover: ' . esc_attr( $button_color ) . " !important;\n";
$inline_css .= "}\n";

// Override outer portal container
$inline_css .= "#{$wrapper_id} .ndizi-portal-container {\n";
$inline_css .= '  background-color: ' . esc_attr( $bg_color ) . " !important;\n";
$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= "}\n";

// Fix company name / header h1 invisibility by overriding transparency
$inline_css .= "#{$wrapper_id} .ndizi-portal-header h1 {\n";
$inline_css .= "  background: none !important;\n";
$inline_css .= '  -webkit-text-fill-color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= "}\n";

// Apply card background and borders
$inline_css .= "#{$wrapper_id} .ndizi-portal-login-card,\n";
$inline_css .= "#{$wrapper_id} .ndizi-portal-card,\n";
$inline_css .= "#{$wrapper_id} .ndizi-project-card,\n";
$inline_css .= "#{$wrapper_id} .ndizi-portal-modal-content {\n";
$inline_css .= '  background-color: ' . esc_attr( $card_bg ) . " !important;\n";
$inline_css .= '  border-color: ' . esc_attr( $border_color ) . " !important;\n";
$inline_css .= "}\n";

// Subtitles and headings
$inline_css .= "#{$wrapper_id} h2,\n";
$inline_css .= "#{$wrapper_id} h3,\n";
$inline_css .= "#{$wrapper_id} h4,\n";
$inline_css .= "#{$wrapper_id} label {\n";
$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= "}\n";

// Fix completed/in_progress task titles being hardcoded to white (causing low contrast in light mode)
$inline_css .= "#{$wrapper_id} .ndizi-task-title {\n";
$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= "}\n";

// Fix project description details having low contrast
$inline_css .= "#{$wrapper_id} .ndizi-project-details-text {\n";
$inline_css .= '  color: ' . esc_attr( $text_muted ) . " !important;\n";
$inline_css .= "}\n";

// Form controls background, border, text
$inline_css .= "#{$wrapper_id} input[type=text],\n";
$inline_css .= "#{$wrapper_id} input[type=password],\n";
$inline_css .= "#{$wrapper_id} select,\n";
$inline_css .= "#{$wrapper_id} textarea {\n";
$inline_css .= '  background-color: ' . esc_attr( $input_bg ) . " !important;\n";
$inline_css .= '  border-color: ' . esc_attr( $input_border ) . " !important;\n";
$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
$inline_css .= "}\n";

// Buttons and primary accents (solid backgrounds)
$inline_css .= "#{$wrapper_id} .ndizi-portal-btn,\n";
$inline_css .= "#{$wrapper_id} .ndizi-btn-comment-dialog,\n";
$inline_css .= "#{$wrapper_id} .ndizi-portal-btn-sm,\n";
$inline_css .= "#{$wrapper_id} .ndizi-portal-tabs a.ndizi-active-tab {\n";
$inline_css .= '  background-color: ' . esc_attr( $button_color ) . " !important;\n";
$inline_css .= '  border-color: ' . esc_attr( $button_color ) . " !important;\n";
$inline_css .= "  color: #ffffff !important;\n";
$inline_css .= "}\n";

// Links and text highlights
$inline_css .= "#{$wrapper_id} a {\n";
$inline_css .= '  color: ' . esc_attr( $link_color ) . ";\n";
$inline_css .= "}\n";
$inline_css .= "#{$wrapper_id} a:hover {\n";
$inline_css .= '  color: ' . esc_attr( $link_color ) . " !important;\n";
$inline_css .= "  opacity: 0.85;\n";
$inline_css .= "}\n";

// Accordion card header text highlights
$inline_css .= "#{$wrapper_id} .ndizi-project-card-header:hover h3,\n";
$inline_css .= "#{$wrapper_id} .ndizi-meta-hours strong {\n";
$inline_css .= '  color: ' . esc_attr( $link_color ) . " !important;\n";
$inline_css .= "}\n";

// Tasks and comment items
$inline_css .= "#{$wrapper_id} .ndizi-portal-task-item,\n";
$inline_css .= "#{$wrapper_id} .ndizi-comment-item {\n";
$inline_css .= '  background-color: ' . esc_attr( $item_bg ) . " !important;\n";
$inline_css .= '  border-color: ' . esc_attr( $item_border ) . " !important;\n";
$inline_css .= "}\n";

// Secondary buttons (Sign Out, File uploads)
$inline_css .= "#{$wrapper_id} .ndizi-portal-btn-secondary,\n";
$inline_css .= "#{$wrapper_id} .ndizi-file-upload-label {\n";
if ( $is_light ) {
	$inline_css .= "  background-color: #ffffff !important;\n";
	$inline_css .= "  border-color: rgba(0, 0, 0, 0.15) !important;\n";
	$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
} else {
	$inline_css .= "  background-color: transparent !important;\n";
	$inline_css .= "  border-color: rgba(255, 255, 255, 0.15) !important;\n";
	$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
}
$inline_css .= "}\n";

// Muted texts
$inline_css .= "#{$wrapper_id} .subtitle,\n";
$inline_css .= "#{$wrapper_id} .desc,\n";
$inline_css .= "#{$wrapper_id} .ndizi-task-due,\n";
$inline_css .= "#{$wrapper_id} .ndizi-project-summary-meta,\n";
$inline_css .= "#{$wrapper_id} .ndizi-comment-date,\n";
$inline_css .= "#{$wrapper_id} .no-items {\n";
$inline_css .= '  color: ' . esc_attr( $text_muted ) . " !important;\n";
$inline_css .= "}\n";

$inline_css .= "</style>\n";

$portal_content = Ndizi_Portal::render_portal_shortcode();

echo $inline_css . '<div id="' . esc_attr( $wrapper_id ) . '" class="ndizi-custom-branded-portal">' . $portal_content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
