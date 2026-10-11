<?php
/**
 * A handful of Customizer settings so the site owner can configure
 * AdSense / verification / disclaimer bar text without editing code.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_customize_register( $wp_customize ) {
	$wp_customize->add_section( 'ffnc_site_options', array(
		'title'    => __( 'Fast Food Nutrition Calculators Settings', 'ffnc' ),
		'priority' => 30,
	) );

	// Google AdSense publisher ID (outputs the verification meta tag automatically).
	$wp_customize->add_setting( 'ffnc_adsense_pub_id', array(
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
	$wp_customize->add_control( 'ffnc_adsense_pub_id', array(
		'section'     => 'ffnc_site_options',
		'label'       => __( 'Google AdSense Publisher ID (e.g. ca-pub-1234567890123456)', 'ffnc' ),
		'type'        => 'text',
	) );

	// Google Search Console verification meta content.
	$wp_customize->add_setting( 'ffnc_gsc_verification', array(
		'sanitize_callback' => 'sanitize_text_field',
		'default'           => '',
	) );
	$wp_customize->add_control( 'ffnc_gsc_verification', array(
		'section' => 'ffnc_site_options',
		'label'   => __( 'Google Search Console verification code (content value only)', 'ffnc' ),
		'type'    => 'text',
	) );
}
add_action( 'customize_register', 'ffnc_customize_register' );

/**
 * NOTE: the site used to have a sticky, site-wide "disclaimer bar" here.
 * It has been removed by design: disclaimer content now lives only on the
 * dedicated /disclaimer/ page (linked prominently in the header and footer
 * nav), instead of being repeated in a banner on every single page.
 */
