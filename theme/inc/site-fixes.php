<?php
/**
 * Small front-end fixes (moved here from the FFNC Site Fixes plugin).
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Stop printing the WordPress version in the page source and feeds.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Remove the ?ver=x.y.z WordPress version from core script and style URLs.
function ffnc_strip_wp_version( $src ) {
	if ( $src && strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'ffnc_strip_wp_version', 9999 );
add_filter( 'script_loader_src', 'ffnc_strip_wp_version', 9999 );
