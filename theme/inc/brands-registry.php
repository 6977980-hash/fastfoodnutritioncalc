<?php
/**
 * Central registry of every brand this theme knows about.
 *
 * This single list drives the homepage brand grid, the /calculators/ hub page,
 * the "related calculators" internal-linking box, and the calculator shortcode.
 * Marking a brand "live" => false lets you list it on the hub as "Coming Soon"
 * (good for internal linking + collecting search interest) before its full
 * calculator + article page is published.
 *
 * To add a new brand once its content and data JSON are ready:
 *   1. Duplicate one of the arrays below and update every field.
 *   2. Add assets/data/{slug}.json following the same structure as the
 *      pilot brands (mcdonalds.json, kfc.json, subway.json).
 *   3. Create a WordPress Page, add [ff_calculator brand="{slug}"] plus your
 *      article content, and set its permalink to match the "url" below.
 *   4. Flip "live" to true.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the full brand registry.
 *
 * @return array
 */
function ffnc_get_brands() {
	static $brands = null;

	if ( null !== $brands ) {
		return $brands;
	}

	$brands = array(
		'mcdonalds'    => array(
			'name'      => "McDonald's",
			'slug'      => 'mcdonalds',
			'color'      => '#d3272c',
			'abbr'       => 'MCD',
			'url'       => '/calculators/mcdonalds-calories-calculator/',
			'title'     => "McDonald's Calories Calculator",
			'tagline'   => 'Build your order and add up calories, protein, carbs and fat across the McDonald\'s menu.',
			'live'      => true,
		),
		'kfc'          => array(
			'name'      => 'KFC',
			'slug'      => 'kfc',
			'color'      => '#8c1515',
			'abbr'       => 'KFC',
			'url'       => '/calculators/kfc-calories-calculator/',
			'title'     => 'KFC Calories Calculator',
			'tagline'   => 'Work out the calories and macros in Original Recipe, Extra Crispy, tenders, sides and bowls.',
			'live'      => true,
		),
		'subway'       => array(
			'name'      => 'Subway',
			'slug'      => 'subway',
			'color'      => '#0a8542',
			'abbr'       => 'SUB',
			'url'       => '/calculators/subway-nutrition-calculator/',
			'title'     => 'Subway Nutrition Calculator',
			'tagline'   => 'Build your 6-inch sandwich and see full nutrition facts before you order.',
			'live'      => true,
		),
		'dunkin'       => array(
			'name'      => "Dunkin'",
			'slug'      => 'dunkin',
			'color'      => '#ff5f9e',
			'abbr'       => 'DNK',
			'url'       => '/calculators/dunkin-calories-calculator/',
			'title'     => "Dunkin' Calories Calculator",
			'tagline'   => 'Coffee, donuts and breakfast sandwiches — calories and macros in one place.',
			'live'      => true,
		),
		'chipotle'     => array(
			'name'      => 'Chipotle',
			'slug'      => 'chipotle',
			'color'      => '#a12e2e',
			'abbr'       => 'CHP',
			'url'       => '/calculators/chipotle-nutrition-calculator/',
			'title'     => 'Chipotle Nutrition Calculator',
			'tagline'   => 'Build your bowl or burrito and total the nutrition as you add each ingredient.',
			'live'      => true,
		),
		'taco-bell'    => array(
			'name'      => 'Taco Bell',
			'slug'      => 'taco-bell',
			'color'      => '#5b2d90',
			'abbr'       => 'TB',
			'url'       => '/calculators/taco-bell-nutrition-calculator/',
			'title'     => 'Taco Bell Nutrition Calculator',
			'tagline'   => 'Tacos, burritos and Cravings Value Menu items with full nutrition breakdowns.',
			'live'      => true,
		),
		'wendys'       => array(
			'name'      => "Wendy's",
			'slug'      => 'wendys',
			'color'      => '#e2273b',
			'abbr'       => 'WEN',
			'url'       => '/calculators/wendys-calories-calculator/',
			'title'     => "Wendy's Calories Calculator",
			'tagline'   => 'Burgers, chicken sandwiches, salads and Frosty — calories and macros side by side.',
			'live'      => true,
		),
		'burger-king'  => array(
			'name'      => 'Burger King',
			'slug'      => 'burger-king',
			'color'      => '#f4a11e',
			'abbr'       => 'BK',
			'url'       => '/calculators/burger-king-nutrition-calculator/',
			'title'     => 'Burger King Nutrition Calculator',
			'tagline'   => 'Whoppers, chicken sandwiches and sides — build your order and see the totals.',
			'live'      => true,
		),
		'dominos'      => array(
			'name'      => "Domino's",
			'slug'      => 'dominos',
			'color'      => '#0078ae',
			'abbr'       => 'DOM',
			'url'       => '/calculators/dominos-calories-calculator/',
			'title'     => "Domino's Calories Calculator",
			'tagline'   => 'Pizza by slice or pie, sides and desserts — calculate calories per serving.',
			'live'      => true,
		),
		'pizza-hut'    => array(
			'name'      => 'Pizza Hut',
			'slug'      => 'pizza-hut',
			'color'      => '#c8112e',
			'abbr'       => 'PH',
			'url'       => '/calculators/pizza-hut-nutrition-calculator/',
			'title'     => 'Pizza Hut Nutrition Calculator',
			'tagline'   => 'Compare crusts, toppings and slice sizes for calories and macros.',
			'live'      => true,
		),
		'chick-fil-a'  => array(
			'name'      => 'Chick-fil-A',
			'slug'      => 'chick-fil-a',
			'color'      => '#dd0033',
			'abbr'       => 'CFA',
			'url'       => '/calculators/chick-fil-a-nutrition-calculator/',
			'title'     => 'Chick-fil-A Nutrition Calculator',
			'tagline'   => 'Chicken sandwiches, nuggets, waffle fries and sauces — full nutrition facts.',
			'live'      => true,
		),
		'panera-bread' => array(
			'name'      => 'Panera Bread',
			'slug'      => 'panera-bread',
			'color'      => '#4c7a2f',
			'abbr'       => 'PNR',
			'url'       => '/calculators/panera-bread-nutrition-calculator/',
			'title'     => 'Panera Bread Nutrition Calculator',
			'tagline'   => 'Soups, salads, sandwiches and bakery items with complete nutrition data.',
			'live'      => true,
		),
		'five-guys'    => array(
			'name'      => 'Five Guys',
			'slug'      => 'five-guys',
			'color'      => '#e21836',
			'abbr'       => '5G',
			'url'       => '/calculators/five-guys-nutrition-calculator/',
			'title'     => 'Five Guys Nutrition Calculator',
			'tagline'   => 'Customize your burger and fries and see how toppings change the totals.',
			'live'      => true,
		),
		'panda-express' => array(
			'name'      => 'Panda Express',
			'slug'      => 'panda-express',
			'color'      => '#e2231a',
			'abbr'       => 'PND',
			'url'       => '/calculators/panda-express-nutrition-calculator/',
			'title'     => 'Panda Express Nutrition Calculator',
			'tagline'   => 'Entrees, sides and plates — build your combo and total the nutrition.',
			'live'      => true,
		),
		'dairy-queen'  => array(
			'name'      => 'Dairy Queen',
			'slug'      => 'dairy-queen',
			'color'      => '#e2124f',
			'abbr'       => 'DQ',
			'url'       => '/calculators/dairy-queen-nutrition-calculator/',
			'title'     => 'Dairy Queen Nutrition Calculator',
			'tagline'   => 'Blizzards, burgers and baskets — calories and macros for the full menu.',
			'live'      => true,
		),
		'popeyes'      => array(
			'name'      => 'Popeyes',
			'slug'      => 'popeyes',
			'color'      => '#e35205',
			'abbr'       => 'PLK',
			'url'       => '/calculators/popeyes-nutrition-calculator/',
			'title'     => 'Popeyes Nutrition Calculator',
			'tagline'   => 'Chicken by the piece, tenders, sandwiches and Cajun sides — add up your order.',
			'live'      => true,
		),
		'qdoba'        => array(
			'name'      => 'QDOBA',
			'slug'      => 'qdoba',
			'color'      => '#7a1e3a',
			'abbr'       => 'QDB',
			'url'       => '/calculators/qdoba-nutrition-calculator/',
			'title'     => 'QDOBA Nutrition Calculator',
			'tagline'   => 'Build your burrito, bowl or tacos scoop by scoop, queso and guac included.',
			'live'      => true,
		),
	);

	return $brands;
}

/**
 * Convenience getter for a single brand.
 *
 * @param string $slug Brand slug.
 * @return array|null
 */
function ffnc_get_brand( $slug ) {
	$brands = ffnc_get_brands();
	return isset( $brands[ $slug ] ) ? $brands[ $slug ] : null;
}

/**
 * Only the brands that currently have a published calculator + article.
 *
 * @return array
 */
function ffnc_get_live_brands() {
	return array_filter( ffnc_get_brands(), function( $b ) {
		return ! empty( $b['live'] );
	} );
}

/**
 * Accent color for a brand's badge/icon, with a safe fallback.
 *
 * @param array|string $brand Brand array (from ffnc_get_brand()) or a slug.
 * @return string Hex color.
 */
function ffnc_get_brand_color( $brand ) {
	if ( is_string( $brand ) ) {
		$brand = ffnc_get_brand( sanitize_title( $brand ) );
	}
	return ( is_array( $brand ) && ! empty( $brand['color'] ) ) ? $brand['color'] : '#1d5fd6';
}

/**
 * A short (2-3 character) brand abbreviation for badges, with a safe
 * fallback. Used instead of a single initial so visually similar brands
 * (three brands starting with "D", three with "P", etc.) stay distinct
 * and recognizable at a glance.
 *
 * @param array|string $brand Brand array or a slug.
 * @return string
 */
function ffnc_get_brand_abbr( $brand ) {
	if ( is_string( $brand ) ) {
		$brand = ffnc_get_brand( sanitize_title( $brand ) );
	}
	if ( is_array( $brand ) && ! empty( $brand['abbr'] ) ) {
		return $brand['abbr'];
	}
	return is_array( $brand ) && ! empty( $brand['name'] ) ? strtoupper( mb_substr( $brand['name'], 0, 2 ) ) : '?';
}

/**
 * Darken a hex color by a percentage — used to build a two-tone gradient
 * for brand badges (see ffnc_brand_badge_html()) instead of a flat fill,
 * which reads as more "logo-like" without copying any brand's real mark.
 *
 * @param string $hex     e.g. '#1d5fd6'.
 * @param float  $percent 0–1, how much darker (0.35 = 35% darker).
 * @return string
 */
function ffnc_darken_hex( $hex, $percent = 0.35 ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return '#000000';
	}
	list( $r, $g, $b ) = array_map( 'hexdec', str_split( $hex, 2 ) );
	$r = (int) round( $r * ( 1 - $percent ) );
	$g = (int) round( $g * ( 1 - $percent ) );
	$b = (int) round( $b * ( 1 - $percent ) );
	return sprintf( '#%02x%02x%02x', $r, $g, $b );
}
