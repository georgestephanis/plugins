<?php
/**
 * Regex Builder for Big Emoji Comments.
 *
 * Fetches the latest Unicode Emoji character database and outputs
 * the merged and optimized PCRE Unicode character class ranges.
 *
 * @package BigEmojiComments
 */

// phpcs:disable WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.Security.EscapeOutput
// phpcs:disable Generic.PHP.DeprecatedFunctions

$url  = 'https://www.unicode.org/Public/UCD/latest/ucd/emoji/emoji-data.txt';
$data = file_get_contents( $url );

if ( ! $data ) {
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
	$data = curl_exec( $ch );
	curl_close( $ch );
}

if ( ! $data ) {
	die( "Error: Could not retrieve emoji data.\n" );
}

$lines     = preg_split( "/\r\n|\n|\r/", $data );
$intervals = array();

foreach ( $lines as $line ) {
	$line = trim( $line );
	if ( empty( $line ) || str_starts_with( $line, '#' ) ) {
		continue;
	}
	// Format: codepoint(s) ; property # comment.
	if ( preg_match( '/^([A-F\d\.]+) +; ([A-Za-z_]+)/i', $line, $match ) ) {
		$codepoint = $match[1];
		if ( str_contains( $codepoint, '..' ) ) {
			$parts = explode( '..', $codepoint );
			$start = hexdec( $parts[0] );
			$end   = hexdec( $parts[1] );
		} else {
			$start = hexdec( $codepoint );
			$end   = $start;
		}
		$intervals[] = array( $start, $end );
	}
}

// Sort intervals by start value.
usort(
	$intervals,
	function ( $a, $b ) {
		return $a[0] <=> $b[0];
	}
);

// Merge overlapping or adjacent intervals.
$merged = array();
if ( count( $intervals ) > 0 ) {
	$current = $intervals[0];
	$count   = count( $intervals );
	for ( $i = 1; $i < $count; $i++ ) {
		$next = $intervals[ $i ];
		if ( $next[0] <= $current[1] + 1 ) {
			// Overlapping or adjacent.
			$current[1] = max( $current[1], $next[1] );
		} else {
			$merged[] = $current;
			$current  = $next;
		}
	}
	$merged[] = $current;
}

/*
 * OUTPUT!
 */
echo "\t\t// Regex generated from " . $url . ".\r\n\r\n";

foreach ( $merged as $interval ) {
	$start_hex = strtoupper( dechex( $interval[0] ) );
	$end_hex   = strtoupper( dechex( $interval[1] ) );

	// Pad to at least 4 characters.
	$start_hex = str_pad( $start_hex, 4, '0', STR_PAD_LEFT );
	$end_hex   = str_pad( $end_hex, 4, '0', STR_PAD_LEFT );

	if ( $interval[0] === $interval[1] ) {
		echo "\t\t'\\x{" . $start_hex . "}' .\r\n";
	} else {
		echo "\t\t'\\x{" . $start_hex . "}-\\x{" . $end_hex . "}' .\r\n";
	}
}
