<?php
/**
 * Styles and scripts.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_enqueue_assets() {
	wp_enqueue_style( 'ffnc-main', FFNC_URI . '/assets/css/main.css', array(), FFNC_VERSION );

	wp_enqueue_script( 'ffnc-main', FFNC_URI . '/assets/js/main.js', array(), FFNC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	// Only load the (heavier) calculator engine on pages that actually use it.
	if ( is_page_template( 'page-templates/template-compare.php' ) ) {
		wp_enqueue_script( 'ffnc-compare', FFNC_URI . '/assets/js/compare.js', array(), FFNC_VERSION, true );
	}
	if ( is_singular() && get_post() && has_shortcode( get_post()->post_content, 'ff_calculator' ) ) {
		wp_enqueue_script( 'ffnc-calculator', FFNC_URI . '/assets/js/calculator.js', array(), FFNC_VERSION, true );
		wp_localize_script( 'ffnc-calculator', 'ffncCalcConfig', array(
			'dataUrl' => FFNC_URI . '/assets/data/',
		) );
	}
	// Cross-restaurant tools reuse the calculator engine's maths.
	if ( is_singular() && get_post() && has_shortcode( get_post()->post_content, 'ff_tool' ) ) {
		wp_enqueue_script( 'ffnc-calculator', FFNC_URI . '/assets/js/calculator.js', array(), FFNC_VERSION, true );
		wp_enqueue_script( 'ffnc-tools', FFNC_URI . '/assets/js/tools.js', array( 'ffnc-calculator' ), FFNC_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'ffnc_enqueue_assets' );

/*
 * v1.2.0: removed the fonts.gstatic.com preconnect — the theme uses a system
 * font stack and never loads Google Fonts, so the hint only cost a DNS/TLS
 * handshake on every page.
 */
