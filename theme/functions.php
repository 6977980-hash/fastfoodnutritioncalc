<?php
/**
 * Theme bootstrap file.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Block direct access.
}

define( 'FFNC_VERSION', '1.5.0' );
define( 'FFNC_DIR', get_template_directory() );
define( 'FFNC_URI', get_template_directory_uri() );

/**
 * Core theme setup: title-tag support, menus, thumbnails, HTML5 markup.
 */
function ffnc_setup() {
	load_theme_textdomain( 'ffnc', FFNC_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'ffnc' ),
		'footer'  => __( 'Footer Menu', 'ffnc' ),
		'legal'   => __( 'Legal Menu', 'ffnc' ),
	) );
}
add_action( 'after_setup_theme', 'ffnc_setup' );

/**
 * Sensible content width for embeds.
 */
function ffnc_content_width() {
	$GLOBALS['content_width'] = 780;
}
add_action( 'after_setup_theme', 'ffnc_content_width', 0 );

/** Includes. */
require FFNC_DIR . '/inc/enqueue.php';
require FFNC_DIR . '/inc/customizer.php';
require FFNC_DIR . '/inc/meta-boxes.php';
require FFNC_DIR . '/inc/shortcodes.php';
require FFNC_DIR . '/inc/schema.php';
require FFNC_DIR . '/inc/seo.php';
require FFNC_DIR . '/inc/template-tags.php';
require FFNC_DIR . '/inc/brands-registry.php';
require FFNC_DIR . '/inc/data.php';
require FFNC_DIR . '/inc/trust.php';
require FFNC_DIR . '/inc/site-fixes.php';
require FFNC_DIR . '/inc/reports.php';
require FFNC_DIR . '/inc/setup.php';
require FFNC_DIR . '/inc/content-updates.php';
require FFNC_DIR . '/inc/tools.php';
require FFNC_DIR . '/inc/item-pages.php';

/**
 * Register the two widget areas used in the footer.
 */
function ffnc_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer Column', 'ffnc' ),
		'id'            => 'footer-1',
		'before_widget' => '<div class="ffnc-footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="ffnc-footer-widget-title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'ffnc_widgets_init' );

/**
 * Excerpt length / more string tweaks (used on the blog and hub pages).
 */
add_filter( 'excerpt_length', function() { return 30; } );
add_filter( 'excerpt_more', function() { return '&hellip;'; } );

/* The users-sitemap removal now lives with the other sitemap rules in inc/seo.php. */
