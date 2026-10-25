<?php
/**
 * Lightweight built-in SEO output so the site is fully optimized even before
 * (or in addition to) installing a plugin like Yoast/Rank Math:
 *   - Custom <title> honoring the per-page SEO Title field
 *   - Meta description
 *   - Canonical URL
 *   - Open Graph + Twitter Card tags
 *   - AdSense / Search Console verification meta (from Customizer)
 *
 * If the site owner activates Yoast SEO or Rank Math later, this file backs
 * off automatically (it checks for those plugins) so tags are never duplicated.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
}

/**
 * Homepage fallback title/description, used only when the "Home" page in
 * wp-admin doesn't have its own SEO Title / Meta Description fields filled
 * in (Page Options box). This is the single most-linked, most important
 * page on the site, so it should never fall back to the bare site tagline.
 * Fill in the Home page's own SEO fields in wp-admin any time to override
 * these defaults with something more tailored.
 */
function ffnc_get_homepage_seo_title() {
	return __( 'Fast Food Calorie & Nutrition Calculator', 'ffnc' );
}

function ffnc_get_homepage_seo_description() {
	return __( 'Free fast food nutrition calculators for 18 U.S. restaurant chains. Build an order and see calories, protein, carbs, fat and sodium, with the data source shown for every menu.', 'ffnc' );
}

function ffnc_get_seo_title() {
	if ( is_singular() ) {
		$custom = get_post_meta( get_the_ID(), '_ffnc_seo_title', true );
		if ( $custom ) {
			return $custom . ' | ' . get_bloginfo( 'name' );
		}
		if ( is_front_page() ) {
			return ffnc_get_homepage_seo_title() . ' | ' . get_bloginfo( 'name' );
		}
	}
	return wp_get_document_title();
}

function ffnc_get_seo_description() {
	if ( is_singular() ) {
		$custom = get_post_meta( get_the_ID(), '_ffnc_meta_description', true );
		if ( $custom ) {
			return $custom;
		}
		if ( is_front_page() ) {
			return ffnc_get_homepage_seo_description();
		}
		if ( has_excerpt() ) {
			return wp_strip_all_tags( get_the_excerpt() );
		}
		// Calculator pages without a hand-written description: describe the
		// actual tool and dataset rather than scraping the first paragraph.
		if ( '1' === get_post_meta( get_queried_object_id(), '_ffnc_is_calculator_page', true ) ) {
			$slug  = ffnc_get_page_brand_slug( get_queried_object_id() );
			$brand = $slug ? ffnc_get_brand( $slug ) : null;
			if ( $brand ) {
				$meta = ffnc_get_dataset_meta( $slug );
				return sprintf(
					/* translators: 1 brand 2 item count 3 market */
					__( 'Free %1$s calorie calculator: add up calories, protein, carbs, fat and sodium for %2$d %3$s menu items and sizes, with a full nutrition table and data source.', 'ffnc' ),
					$brand['name'],
					count( ffnc_get_brand_items( $slug ) ),
					$meta['market'] ? $meta['market'] : ''
				);
			}
		}
	}
	if ( is_home() && get_option( 'page_for_posts' ) ) {
		$custom = get_post_meta( (int) get_option( 'page_for_posts' ), '_ffnc_meta_description', true );
		if ( $custom ) {
			return $custom;
		}
		return __( 'Fast food nutrition guides: how restaurant calories are calculated, protein and sodium comparisons, and how to read menu nutrition facts.', 'ffnc' );
	}
	if ( is_category() && term_description() ) {
		return wp_strip_all_tags( term_description() );
	}
	if ( is_singular() ) {
		// Last resort for singular content: first ~155 characters of the text.
		$text = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', get_queried_object_id() ) ) );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( $text ) {
			return wp_html_excerpt( $text, 155, '…' );
		}
	}
	return get_bloginfo( 'description' );
}

/**
 * The one canonical URL for the current request, or '' when a page must not
 * declare one (search results, 404s). Always absolute, always matching the
 * site's permalink trailing-slash style, never carrying query strings.
 */
function ffnc_get_canonical_url() {
	if ( is_search() || is_404() ) {
		return '';
	}
	if ( is_singular() ) {
		$url  = wp_get_canonical_url(); // Handles <!--nextpage--> pagination.
		return $url ? $url : get_permalink();
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$url  = $term ? get_term_link( $term ) : '';
	} elseif ( is_author() ) {
		$url = get_author_posts_url( (int) get_query_var( 'author' ) );
	} elseif ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( get_query_var( 'post_type' ) );
	} elseif ( is_date() ) {
		// Date archives are noindexed; still give them a clean self-canonical.
		$url = home_url( user_trailingslashit( $GLOBALS['wp']->request ) );
		return $url;
	} else {
		$url = home_url( user_trailingslashit( $GLOBALS['wp']->request ) );
	}
	if ( is_wp_error( $url ) || ! $url ) {
		return '';
	}
	if ( $paged > 1 ) {
		global $wp_rewrite;
		$url = user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $paged, 'paged' );
	}
	return $url;
}

/**
 * Should the current request be noindexed?
 *
 * - Search results and 404s.
 * - Date and author archives (a single-author site's author archive and the
 *   month archives are near-duplicates of the blog listing).
 * - Tag archives (not used as curated hubs).
 * - Category archives with fewer than 5 posts (thin duplicates of the posts).
 * - Any post/page an editor explicitly marked "noindex" in Page Options.
 */
function ffnc_should_noindex() {
	if ( is_search() || is_404() || is_date() || is_author() || is_tag() ) {
		return true;
	}
	if ( is_category() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->count ) && $term->count < 5 ) {
			return true;
		}
	}
	if ( is_singular() && '1' === get_post_meta( get_queried_object_id(), '_ffnc_noindex', true ) ) {
		return true;
	}
	return false;
}

/**
 * Robots directives go through core's wp_robots API so there is exactly one
 * <meta name="robots"> tag (v1.1.8 printed its own tag next to core's).
 */
function ffnc_wp_robots( $robots ) {
	if ( ffnc_seo_plugin_active() ) {
		return $robots;
	}
	if ( ffnc_should_noindex() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['max-image-preview'] );
	} else {
		$robots['max-image-preview'] = 'large';
		$robots['max-snippet']       = '-1';
	}
	return $robots;
}
add_filter( 'wp_robots', 'ffnc_wp_robots', 20 );

/**
 * Core prints its own rel=canonical on singular pages; the theme prints one
 * for every indexable URL, so remove core's to avoid two canonical tags.
 */
function ffnc_remove_core_canonical() {
	if ( ! ffnc_seo_plugin_active() ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}
add_action( 'wp', 'ffnc_remove_core_canonical' );

function ffnc_head_meta() {
	if ( ffnc_seo_plugin_active() ) {
		return; // Defer entirely to the dedicated SEO plugin.
	}

	$description = ffnc_get_seo_description();
	$canonical   = ffnc_get_canonical_url();
	$og_url      = $canonical ? $canonical : home_url( '/' );

	if ( $description ) {
		echo "\n" . '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	if ( $canonical && ! ffnc_should_noindex() ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
	}

	// Open Graph.
	$is_article = is_singular( 'post' );
	echo '<meta property="og:type" content="' . ( $is_article ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', get_bloginfo( 'language' ) ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( ffnc_get_seo_title() ) . '">' . "\n";
	if ( $description ) {
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( $og_url ) . '">' . "\n";
	if ( $is_article ) {
		echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c' ) ) . '">' . "\n";
		echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . "\n";
	}

	// Social share image — per-page featured image first, else the theme's
	// own artwork (no brand logos).
	$social_image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$social_image = get_the_post_thumbnail_url( get_the_ID(), 'large' );
	}
	if ( ! $social_image ) {
		$social_image = get_theme_file_uri( 'assets/img/social-share.png' );
		echo '<meta property="og:image:width" content="1200">' . "\n";
		echo '<meta property="og:image:height" content="630">' . "\n";
	}
	echo '<meta property="og:image" content="' . esc_url( $social_image ) . '">' . "\n";
	echo '<meta property="og:image:alt" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

	// Twitter.
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . esc_attr( ffnc_get_seo_title() ) . '">' . "\n";
	if ( $description ) {
		echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
	}
	echo '<meta name="twitter:image" content="' . esc_url( $social_image ) . '">' . "\n";

	// AdSense + Search Console verification (only outputs if the owner filled these in).
	$adsense_id = get_theme_mod( 'ffnc_adsense_pub_id', '' );
	if ( $adsense_id ) {
		echo '<meta name="google-adsense-account" content="' . esc_attr( $adsense_id ) . '">' . "\n";
	}
	$gsc = get_theme_mod( 'ffnc_gsc_verification', '' );
	if ( $gsc ) {
		echo '<meta name="google-site-verification" content="' . esc_attr( $gsc ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'ffnc_head_meta', 1 );

/**
 * Default favicon (fork + bar-chart mark) so the site never ships without
 * one. If the site owner sets a custom Site Icon in Appearance → Customize
 * → Site Identity, WordPress core already outputs that instead — this only
 * fires as a fallback.
 */
function ffnc_default_favicon() {
	if ( has_site_icon() ) {
		return;
	}
	echo '<link rel="icon" href="' . esc_url( FFNC_URI . '/assets/img/favicon.svg' ) . '" type="image/svg+xml">' . "\n";
	echo '<link rel="alternate icon" href="' . esc_url( FFNC_URI . '/assets/img/favicon.ico' ) . '">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( FFNC_URI . '/assets/img/apple-touch-icon.png' ) . '">' . "\n";
}
add_action( 'wp_head', 'ffnc_default_favicon', 1 );

/**
 * Override the <title> tag output when a custom SEO title is set.
 */
function ffnc_document_title_parts( $parts ) {
	if ( is_singular() ) {
		$custom = get_post_meta( get_the_ID(), '_ffnc_seo_title', true );
		if ( $custom ) {
			$parts['title'] = $custom;
		} elseif ( is_front_page() ) {
			$parts['title'] = ffnc_get_homepage_seo_title();
		}
	}
	return $parts;
}
add_filter( 'document_title_parts', 'ffnc_document_title_parts' );

/**
 * Redirect WordPress's default standalone attachment pages straight to the
 * file itself. Attachment pages are near-empty, near-duplicate content
 * (just the image + a little boilerplate) that shouldn't be indexed as
 * their own page — a well-established SEO best practice, not specific to
 * this site's current low media count.
 */
function ffnc_redirect_attachment_pages() {
	if ( ! is_attachment() ) {
		return;
	}
	$url = wp_get_attachment_url( get_the_ID() );
	wp_safe_redirect( $url ? $url : home_url( '/' ), 301 );
	exit;
}
add_action( 'template_redirect', 'ffnc_redirect_attachment_pages' );

/* -------------------------------------------------------------------------
 * XML sitemap hygiene (core wp-sitemap.xml)
 * ---------------------------------------------------------------------- */

/** Tag archives are noindexed, so keep them out of the sitemap. */
add_filter( 'wp_sitemaps_taxonomies', function ( $taxonomies ) {
	unset( $taxonomies['post_tag'], $taxonomies['post_format'] );
	return $taxonomies;
} );

/** Thin (noindexed) categories out of the sitemap — same <5 rule as robots. */
add_filter( 'wp_sitemaps_taxonomies_query_args', function ( $args, $taxonomy ) {
	if ( 'category' === $taxonomy ) {
		$thin = get_terms( array( 'taxonomy' => 'category', 'fields' => 'ids', 'hide_empty' => false, 'number' => 0 ) );
		$thin = array_filter( (array) $thin, function ( $id ) {
			$t = get_term( $id, 'category' );
			return $t && ! is_wp_error( $t ) && $t->count < 5;
		} );
		if ( $thin ) {
			$args['exclude'] = array_values( $thin );
		}
	}
	return $args;
}, 10, 2 );

/** Pages/posts marked noindex out of the sitemap. */
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
		'relation' => 'OR',
		array( 'key' => '_ffnc_noindex', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_ffnc_noindex', 'value' => '1', 'compare' => '!=' ),
	);
	return $args;
} );

/** lastmod for posts/pages so crawlers can see what changed. */
add_filter( 'wp_sitemaps_posts_entry', function ( $entry, $post ) {
	$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
	return $entry;
}, 10, 2 );

/** Single-author site: users sitemap is thin and leaks the login name. */
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}, 10, 2 );

/**
 * robots.txt (virtual — only applies if no physical robots.txt file exists).
 * Keep everything crawlable except admin and internal search results;
 * core already appends the Sitemap: line when sitemaps are enabled.
 */
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public ) {
		return $output;
	}
	if ( false === strpos( $output, 'Disallow: /?s=' ) ) {
		$output = str_replace( "Allow: /wp-admin/admin-ajax.php\n", "Allow: /wp-admin/admin-ajax.php\nDisallow: /?s=\nDisallow: /search/\n", $output );
	}
	return $output;
}, 20, 2 );
