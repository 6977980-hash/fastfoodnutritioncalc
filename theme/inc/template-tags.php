<?php
/**
 * Reusable template helpers: breadcrumbs, related-calculators box,
 * auto-appending that box + FAQ schema after the_content.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the "Related Calculators" internal-linking box for a list of slugs.
 *
 * @param array $slugs Brand slugs.
 * @return string
 */
function ffnc_render_related_calculators( $slugs ) {
	if ( empty( $slugs ) ) {
		return '';
	}
	$out = '<aside class="ffnc-related-calculators">';
	$out .= '<h2>' . esc_html__( 'Related Calculators', 'ffnc' ) . '</h2>';
	$out .= '<ul class="ffnc-related-grid">';
	foreach ( $slugs as $slug ) {
		$brand = ffnc_get_brand( sanitize_title( $slug ) );
		if ( ! $brand ) {
			continue;
		}
		$out .= '<li>';
		$out .= '<a href="' . esc_url( home_url( $brand['url'] ) ) . '">';
		$out .= ffnc_brand_badge_html( $brand, 'sm' );
		$out .= '<span class="ffnc-related-text"><span class="ffnc-related-title">' . esc_html( $brand['title'] ) . '</span>';
		if ( ! empty( $brand['tagline'] ) ) {
			$out .= '<span class="ffnc-related-tagline">' . esc_html( $brand['tagline'] ) . '</span>';
		}
		$out .= '</span></a>';
		$out .= '</li>';
	}
	$out .= '</ul></aside>';
	return $out;
}

/**
 * Build the FAQPage JSON-LD <script> for whichever page id collected
 * [ff_faq_item] entries during rendering, or '' if it collected none.
 *
 * Pulled out of ffnc_append_page_extras() so it can also be called directly
 * by templates that render their FAQ shortcode outside the normal the_content
 * pipeline — front-page.php being the main example: it builds the homepage
 * body by hand (hardcoded sections + a direct do_shortcode() call) rather
 * than looping over posts with the_content(), so the_content filters such as
 * ffnc_append_page_extras() below never run on the front page at all, and
 * the FAQPage schema it (used to) rely on there was silently skipped.
 *
 * @param int $post_id Post/page id the FAQ items were collected under.
 * @return string
 */
function ffnc_get_faq_schema_script( $post_id ) {
	global $ffnc_faq_collector;
	if ( empty( $ffnc_faq_collector[ $post_id ] ) ) {
		return '';
	}

	$schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array(),
	);
	foreach ( $ffnc_faq_collector[ $post_id ] as $qa ) {
		$schema['mainEntity'][] = array(
			'@type'          => 'Question',
			'name'           => $qa['q'],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => $qa['a'],
			),
		);
	}
	return '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}

/**
 * After the_content renders (and shortcodes have executed, so the FAQ
 * collector is populated), append:
 *   1. The "Related Calculators" box, if the page set related brand slugs.
 *   2. FAQPage JSON-LD schema, if the page contains [ff_faq_item] entries.
 *
 * Priority 20 runs after WordPress's own shortcode processing (priority 11).
 */
function ffnc_append_page_extras( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id = get_the_ID();
	$extra   = '';

	$related_raw = get_post_meta( $post_id, '_ffnc_related_brands', true );
	$slugs       = $related_raw ? array_filter( array_map( 'trim', explode( ',', $related_raw ) ) ) : array();
	// Calculator pages without a hand-picked list get menu-similar brands
	// (burgers → burgers, pizza → pizza) instead of no related links at all.
	if ( ! $slugs && '1' === get_post_meta( $post_id, '_ffnc_is_calculator_page', true ) ) {
		$page_brand = ffnc_get_page_brand_slug( $post_id );
		if ( $page_brand ) {
			$slugs = ffnc_default_related_brands( $page_brand );
		}
	}
	// Never link a page to itself.
	$slugs = array_values( array_diff( $slugs, array( ffnc_get_page_brand_slug( $post_id ) ) ) );
	if ( $slugs ) {
		$extra .= ffnc_render_related_calculators( $slugs );
	}

	$extra .= ffnc_get_faq_schema_script( $post_id );

	return $content . $extra;
}
add_filter( 'the_content', 'ffnc_append_page_extras', 20 );

/**
 * Simple breadcrumb trail + BreadcrumbList schema. Call from templates.
 */
function ffnc_breadcrumbs() {
	if ( is_front_page() ) {
		return;
	}

	$trail = array( array( 'name' => __( 'Home', 'ffnc' ), 'url' => home_url( '/' ) ) );

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post && 'post' === $post->post_type && get_option( 'page_for_posts' ) ) {
			$blog_id = (int) get_option( 'page_for_posts' );
			$trail[] = array( 'name' => get_the_title( $blog_id ), 'url' => get_permalink( $blog_id ) );
		}
		if ( $post && 'page' === $post->post_type && $post->post_parent ) {
			$ancestors = array_reverse( get_post_ancestors( $post ) );
			foreach ( $ancestors as $ancestor_id ) {
				$trail[] = array( 'name' => get_the_title( $ancestor_id ), 'url' => get_permalink( $ancestor_id ) );
			}
		}
		$trail[] = array( 'name' => get_the_title(), 'url' => get_permalink() );
	} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
		$trail[] = array( 'name' => get_the_title( (int) get_option( 'page_for_posts' ) ), 'url' => '' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$trail[] = array( 'name' => single_term_title( '', false ), 'url' => '' );
	} elseif ( is_search() ) {
		$trail[] = array( 'name' => __( 'Search Results', 'ffnc' ), 'url' => '' );
	} elseif ( is_404() ) {
		$trail[] = array( 'name' => __( 'Page Not Found', 'ffnc' ), 'url' => '' );
	}

	echo '<nav class="ffnc-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ffnc' ) . '"><ol>';
	$last = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		echo '<li>';
		if ( $i !== $last && ! empty( $crumb['url'] ) ) {
			echo '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
		} else {
			echo '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
		}
		echo '</li>';
	}
	echo '</ol></nav>';

	// BreadcrumbList schema.
	$items = array();
	foreach ( $trail as $i => $crumb ) {
		$item = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $crumb['name'],
		);
		if ( ! empty( $crumb['url'] ) ) {
			$item['item'] = $crumb['url'];
		}
		$items[] = $item;
	}
	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $items,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
}

/**
 * Small colored badge used for a brand's "logo" everywhere in the UI
 * (header search results, nav dropdowns, hub grids). Deliberately a
 * stylized initial on a brand-accent background rather than a copy of
 * the brand's real trademarked logo artwork — see the Disclaimer page.
 *
 * @param array  $brand Brand array from ffnc_get_brand()/ffnc_get_brands().
 * @param string $size  'sm' | 'md' | 'lg'.
 * @return string
 */
function ffnc_brand_badge_html( $brand, $size = 'md' ) {
	if ( empty( $brand['name'] ) ) {
		return '';
	}
	$color      = ffnc_get_brand_color( $brand );
	$color_dark = ffnc_darken_hex( $color, 0.32 );
	$abbr       = ffnc_get_brand_abbr( $brand );
	return sprintf(
		'<span class="ffnc-brand-badge ffnc-brand-badge-%1$s" style="--ffnc-brand-color:%2$s;--ffnc-brand-color-dark:%3$s" aria-hidden="true"><span class="ffnc-brand-badge-text">%4$s</span></span>',
		esc_attr( $size ),
		esc_attr( $color ),
		esc_attr( $color_dark ),
		esc_html( $abbr )
	);
}

/**
 * Fallback primary menu so the header never looks broken before a menu is
 * assigned. Instead of a flat 19-item list, this groups every brand page
 * behind one compact "Calculators" dropdown so the header stays clean no
 * matter how many brands are live. (An earlier version also had a separate
 * "Brands" dropdown to the same 15 pages — removed as duplicate navigation
 * to the same content.)
 */
function ffnc_default_menu() {
	$brands          = ffnc_get_live_brands();
	$hub_url         = esc_url( ffnc_page_url( 'calculators' ) ? ffnc_page_url( 'calculators' ) : home_url( '/calculators/' ) );
	$blog_page_id    = (int) get_option( 'page_for_posts' );
	?>
	<ul class="ffnc-menu">
		<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'ffnc' ); ?></a></li>

		<li class="ffnc-menu-item-dropdown">
			<button type="button" class="ffnc-menu-dropdown-toggle" aria-expanded="false">
				<?php esc_html_e( 'Calculators', 'ffnc' ); ?>
				<span class="ffnc-caret" aria-hidden="true"></span>
			</button>
			<div class="ffnc-submenu">
				<a class="ffnc-submenu-view-all" href="<?php echo $hub_url; ?>"><?php esc_html_e( 'View all calculators →', 'ffnc' ); ?></a>
				<ul>
					<?php foreach ( $brands as $brand ) : ?>
						<li>
							<a href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>">
								<?php echo ffnc_brand_badge_html( $brand, 'sm' ); ?>
								<span><?php echo esc_html( $brand['name'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</li>

		<?php if ( $blog_page_id ) : ?>
			<li><a href="<?php echo esc_url( get_permalink( $blog_page_id ) ); ?>"><?php esc_html_e( 'Nutrition Guides', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_compare_url() ) : ?>
			<li><a href="<?php echo esc_url( ffnc_compare_url() ); ?>"><?php esc_html_e( 'Compare', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_methodology_url() ) : ?>
			<li><a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'Methodology', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_page_url( 'disclaimer' ) ) : ?>
			<li><a href="<?php echo esc_url( ffnc_page_url( 'disclaimer' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_page_url( 'about' ) ) : ?>
			<li><a href="<?php echo esc_url( ffnc_page_url( 'about' ) ); ?>"><?php esc_html_e( 'About', 'ffnc' ); ?></a></li>
		<?php endif; ?>
	</ul>
	<?php
}
