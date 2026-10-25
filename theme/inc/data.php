<?php
/**
 * Nutrition data layer — the single source of truth.
 *
 * Every nutrition number on the site (calculator, crawlable tables, item
 * fact boxes, the compare tool, the methodology status table) is read from
 * assets/data/{slug}.json through the functions in this file. Nothing else
 * should hard-code nutrition values.
 *
 * Each JSON file carries a "meta" block describing provenance:
 *
 *   market          ISO-ish market code, e.g. "US".
 *   market_label    Human label, e.g. "United States".
 *   source_name     Name of the official reference the data is checked against.
 *   source_url      URL of that official reference.
 *   status          "verified" once every value has been checked against the
 *                   official source on `last_reviewed`, otherwise "pending".
 *   last_reviewed   YYYY-MM-DD of the last full manual review, or null.
 *                   Never set this unless a person actually did the review.
 *   review_detail   Optional sentence when parts were checked on different
 *                   dates ("Checked Sep 25, 2026; items added Oct 11, 2026").
 *   spot_checks     Individual values checked against the official source
 *                   (item id, field, dataset value, official value, date, url).
 *   changelog       Human-readable log of data changes.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load and cache a brand's dataset.
 *
 * @param string $slug Brand slug.
 * @return array|null Decoded dataset or null when missing/invalid.
 */
function ffnc_get_brand_data( $slug ) {
	static $cache = array();
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return null;
	}
	if ( array_key_exists( $slug, $cache ) ) {
		return $cache[ $slug ];
	}
	$path = FFNC_DIR . '/assets/data/' . $slug . '.json';
	$data = null;
	if ( is_readable( $path ) ) {
		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_array( $decoded ) && ! empty( $decoded['categories'] ) && is_array( $decoded['categories'] ) ) {
			$data = $decoded;
		}
	}
	$cache[ $slug ] = $data;
	return $data;
}

/**
 * Provenance metadata for a dataset with safe defaults.
 *
 * @param string $slug Brand slug.
 * @return array
 */
function ffnc_get_dataset_meta( $slug ) {
	$data = ffnc_get_brand_data( $slug );
	$meta = ( $data && ! empty( $data['meta'] ) && is_array( $data['meta'] ) ) ? $data['meta'] : array();
	return wp_parse_args( $meta, array(
		'market'        => '',
		'market_label'  => '',
		'source_name'   => '',
		'source_url'    => '',
		'status'        => 'pending',
		'last_reviewed' => null,
		'review_detail' => '',
		'spot_checks'   => array(),
		'changelog'     => array(),
	) );
}

/**
 * Is this dataset fully verified (status + a real review date)?
 *
 * @param string $slug Brand slug.
 * @return bool
 */
function ffnc_dataset_is_verified( $slug ) {
	$meta = ffnc_get_dataset_meta( $slug );
	return 'verified' === $meta['status'] && ! empty( $meta['last_reviewed'] );
}

/**
 * Human "Month YYYY" for a YYYY-MM-DD string, or '' if empty/invalid.
 *
 * @param string|null $ymd Date.
 * @param string      $format PHP date format.
 * @return string
 */
function ffnc_format_review_date( $ymd, $format = 'F Y' ) {
	if ( empty( $ymd ) ) {
		return '';
	}
	$ts = strtotime( $ymd . ' 00:00:00 UTC' );
	return $ts ? date_i18n( $format, $ts ) : '';
}

/**
 * Flat list of every item in a dataset, each tagged with its category.
 *
 * @param string $slug Brand slug.
 * @return array
 */
function ffnc_get_brand_items( $slug ) {
	$data = ffnc_get_brand_data( $slug );
	if ( ! $data ) {
		return array();
	}
	$items = array();
	foreach ( $data['categories'] as $cat ) {
		foreach ( (array) $cat['items'] as $item ) {
			$item['category_id']   = $cat['id'];
			$item['category_name'] = $cat['name'];
			$items[ $item['id'] ]  = $item;
		}
	}
	return $items;
}

/**
 * Single item lookup.
 *
 * @param string $slug    Brand slug.
 * @param string $item_id Item id.
 * @return array|null
 */
function ffnc_get_brand_item( $slug, $item_id ) {
	$items = ffnc_get_brand_items( $slug );
	return isset( $items[ $item_id ] ) ? $items[ $item_id ] : null;
}

/**
 * Total menu items across all datasets (used for honest homepage stats).
 *
 * @return int
 */
function ffnc_count_all_items() {
	$total = 0;
	foreach ( array_keys( ffnc_get_live_brands() ) as $slug ) {
		$total += count( ffnc_get_brand_items( $slug ) );
	}
	return $total;
}

/**
 * Spot-check results keyed by item id (so tables can flag known differences).
 *
 * @param string $slug Brand slug.
 * @return array item_id => list of checks.
 */
function ffnc_get_spot_checks_by_item( $slug ) {
	$meta = ffnc_get_dataset_meta( $slug );
	$out  = array();
	foreach ( (array) $meta['spot_checks'] as $check ) {
		if ( empty( $check['item'] ) ) {
			continue;
		}
		$out[ $check['item'] ][] = $check;
	}
	return $out;
}

/**
 * Datasets sorted by most recent real review date (only reviewed ones).
 *
 * @return array slug => meta
 */
function ffnc_get_recently_reviewed() {
	$out = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$meta = ffnc_get_dataset_meta( $slug );
		if ( ! empty( $meta['last_reviewed'] ) ) {
			$out[ $slug ] = $meta;
		}
	}
	uasort( $out, function ( $a, $b ) {
		return strcmp( (string) $b['last_reviewed'], (string) $a['last_reviewed'] );
	} );
	return $out;
}

/**
 * Permalink of a published page by path, or '' if it doesn't exist.
 * Used everywhere the theme links to an optional page so it never
 * outputs a link that would 404.
 *
 * @param string $path Page path, e.g. 'methodology' or 'calculators'.
 * @return string
 */
function ffnc_page_url( $path ) {
	static $cache = array();
	if ( isset( $cache[ $path ] ) ) {
		return $cache[ $path ];
	}
	$page = get_page_by_path( $path );
	$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
	$cache[ $path ] = $url;
	return $url;
}

/**
 * The URL of the first published page using a given page template, or ''.
 * Lets the theme find the Methodology / Compare pages even if the owner
 * picked a different slug.
 *
 * @param string $template e.g. 'page-templates/template-methodology.php'.
 * @param string $fallback_path Path to try first.
 * @return string
 */
function ffnc_template_page_url( $template, $fallback_path = '' ) {
	static $cache = array();
	$key = $template . '|' . $fallback_path;
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$url = $fallback_path ? ffnc_page_url( $fallback_path ) : '';
	if ( ! $url ) {
		$pages = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => $template, // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'         => 'ids',
		) );
		$url = $pages ? get_permalink( $pages[0] ) : '';
	}
	$cache[ $key ] = $url;
	return $url;
}

/** Convenience getters for the key trust/tool pages. */
function ffnc_methodology_url() {
	return ffnc_template_page_url( 'page-templates/template-methodology.php', 'methodology' );
}
function ffnc_compare_url() {
	return ffnc_template_page_url( 'page-templates/template-compare.php', 'compare' );
}
function ffnc_report_error_url() {
	return ffnc_page_url( 'report-an-error' );
}

/**
 * Brand slug for the current calculator page — from the [ff_calculator]
 * shortcode in its content, so no extra configuration is needed.
 *
 * @param int|null $post_id Post id.
 * @return string
 */
function ffnc_get_page_brand_slug( $post_id = null ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	if ( preg_match( '/\[ff_calculator[^\]]*brand=["\']?([a-z0-9\-]+)/i', $post->post_content, $m ) ) {
		return sanitize_title( $m[1] );
	}
	return '';
}

/**
 * Round a nutrient for display (integers stay integers, others 1dp).
 *
 * @param mixed $n Number.
 * @return string
 */
function ffnc_fmt_num( $n ) {
	if ( null === $n || '' === $n ) {
		return '—';
	}
	$n = (float) $n;
	return ( floor( $n ) === $n ) ? number_format_i18n( $n ) : number_format_i18n( $n, 1 );
}
