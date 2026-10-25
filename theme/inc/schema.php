<?php
/**
 * Site-wide JSON-LD: Organization / WebSite on the front page, plus
 * SoftwareApplication schema on pages flagged as calculator pages.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_output_head_schema() {
	// If a dedicated SEO plugin (Yoast/Rank Math) is active, it outputs its own
	// WebSite/Organization/Article graph — defer to it entirely so this theme
	// never emits a second, conflicting WebSite/Organization entity with a
	// different `name` value. Mirrors the guard in inc/seo.php's ffnc_head_meta().
	if ( ffnc_seo_plugin_active() ) {
		return;
	}

	$site_id = home_url( '/#website' );
	$org_id  = home_url( '/#organization' );
	$graph   = array();

	// Organization + WebSite are emitted site-wide (as one @graph) so every
	// page can reference them by @id. No SearchAction: Google retired the
	// sitelinks search box, and the internal search results are noindexed.
	$graph[] = array(
		'@type'       => 'Organization',
		'@id'         => $org_id,
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'logo'        => array(
			'@type'  => 'ImageObject',
			'url'    => get_theme_file_uri( 'assets/img/favicon-512.png' ),
			'width'  => 512,
			'height' => 512,
		),
		'description' => __( 'Independent fast food nutrition calculators and nutrition data. Not affiliated with any restaurant brand.', 'ffnc' ),
	);
	$graph[] = array(
		'@type'     => 'WebSite',
		'@id'       => $site_id,
		'name'      => get_bloginfo( 'name' ),
		'url'       => home_url( '/' ),
		'publisher' => array( '@id' => $org_id ),
		'inLanguage' => get_bloginfo( 'language' ),
	);

	if ( is_singular() && get_post_meta( get_the_ID(), '_ffnc_is_calculator_page', true ) ) {
		$slug = ffnc_get_page_brand_slug();
		$meta = $slug ? ffnc_get_dataset_meta( $slug ) : array();
		$app  = array(
			'@type'               => 'WebApplication',
			'@id'                 => get_permalink() . '#calculator',
			'name'                => get_the_title(),
			'url'                 => get_permalink(),
			'applicationCategory' => 'HealthApplication',
			'operatingSystem'     => 'Any (web browser)',
			'isAccessibleForFree' => true,
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'USD',
			),
			'publisher'           => array( '@id' => $org_id ),
			'isPartOf'            => array( '@id' => $site_id ),
			'description'         => ffnc_get_seo_description(),
			'dateModified'        => get_the_modified_date( 'c' ),
		);
		if ( ! empty( $meta['market_label'] ) ) {
			$app['audience'] = array(
				'@type'         => 'Audience',
				'geographicArea' => array( '@type' => 'Country', 'name' => $meta['market_label'] ),
			);
		}
		$graph[] = $app;
	} elseif ( is_singular( 'post' ) ) {
		$article = array(
			'@type'            => 'Article',
			'@id'              => get_permalink() . '#article',
			'headline'         => wp_strip_all_tags( get_the_title() ),
			'datePublished'    => get_the_date( 'c' ),
			'dateModified'     => get_the_modified_date( 'c' ),
			'mainEntityOfPage' => get_permalink(),
			// Written by the site's owner (real person, no invented credentials).
			'author'           => array( '@id' => home_url( '/#owner' ) ),
			'publisher'        => array( '@id' => $org_id ),
			'isPartOf'         => array( '@id' => $site_id ),
		);
		if ( has_post_thumbnail() ) {
			$article['image'] = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		}
		$graph[] = $article;
		$graph[] = ffnc_owner_schema();
	} elseif ( is_page( 'about' ) ) {
		$graph[] = ffnc_owner_schema();
	}

	$schemas = array(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		),
	);

	foreach ( $schemas as $schema ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'ffnc_output_head_schema' );

/**
 * Rank Math adds an "Article" rich snippet to every page by default. Pages on
 * this site (home, calculators, compare, legal, contact) are tools and
 * reference pages, not articles, so that entity misdescribes them. Keep
 * Rank Math's Article/BlogPosting schema on blog posts only.
 *
 * @param array $data JSON-LD entities keyed by id.
 * @return array
 */
function ffnc_rank_math_drop_page_article( $data ) {
	if ( ! is_page() && ! is_front_page() ) {
		return $data;
	}
	foreach ( $data as $key => $entity ) {
		$type = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();
		if ( array_intersect( $type, array( 'Article', 'BlogPosting', 'NewsArticle' ) ) ) {
			unset( $data[ $key ] );
		}
		// The author Person node is only referenced by that Article.
		if ( array( 'Person' ) === $type && isset( $entity['@id'] ) && false !== strpos( $entity['@id'], '/author/' ) ) {
			unset( $data[ $key ] );
		}
	}
	return $data;
}
add_filter( 'rank_math/json_ld', 'ffnc_rank_math_drop_page_article', 99 );
