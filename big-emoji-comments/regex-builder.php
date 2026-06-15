<?php

$data = file_get_contents( 'http://www.unicode.org/Public/emoji/2.0/emoji-data.txt' );

$array = preg_split( "/\r\n|\n|\r/", $data );
$array = array_filter( $array, function( $line ) {
        if ( '#' === substr( $line, 0, 1 ) ) {
            return false;
        } elseif ( empty( $line ) ) {
            return false;
        } else {
            return true;
        }
    });
$array = array_map( function( $line ){
    if ( preg_match( '/^([a-f\d\.]+) +; ([a-z_]+) /i', $line, $match ) ) {
        return array( $match[1], $match[2] );
    }
}, $array );

$emoji_by_range = array();
foreach ( $array as $point ) {
    $emoji_by_range[ $point[1] ][] = $point[0];
}

/*
 * OUTPUT!
 */

echo "\t\t// Regex generated from http://www.unicode.org/Public/emoji/2.0/emoji-data.txt\r\n\r\n";

foreach ( $emoji_by_range as $range => $points ) {
    echo "\t\t// Start {$range}\r\n";
    foreach ( $points as $point ) {
        echo "\t\t'\x{" . str_replace( '..', '}-\x{', $point ) . "}' .\r\n";
    }
    echo "\t\t// End {$range}\r\n\r\n";
}