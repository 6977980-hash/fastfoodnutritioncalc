<?php
/**
 * Plugin Name: FFNC Site Fixes
 * Description: Small front-end fixes for fastfoodnutritioncalc.com. Deployed automatically from GitHub.
 * Version:     1.0.0
 * Author:      Fast Food Nutrition Calculators Team
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'FFNC_SITE_FIXES_VERSION', '1.0.0' );

// Stop printing the WordPress version in the page source and feeds.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

// Remove the ?ver=x.y.z WordPress version from core script and style URLs.
function ffnc_site_fixes_strip_wp_version( $src ) {
	if ( $src && strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) !== false ) {
		$src = remove_query_arg( 'ver', $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'ffnc_site_fixes_strip_wp_version', 9999 );
add_filter( 'script_loader_src', 'ffnc_site_fixes_strip_wp_version', 9999 );

// Front-end fixes for broken in-page links (skip link, "How It Works" button).
function ffnc_site_fixes_enqueue() {
	wp_enqueue_script(
		'ffnc-site-fixes',
		plugins_url( 'assets/site-fixes.js', __FILE__ ),
		array(),
		FFNC_SITE_FIXES_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'ffnc_site_fixes_enqueue' );
