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
 * "Deals & Combos" section for a brand page: one row per deal in the dataset's
 * `deals` list, with totals added up from the same item values the calculator
 * uses, and a link that opens the calculator with that deal in the order.
 *
 * @param string $slug Brand slug.
 * @return string HTML, or '' when the brand has no deals.
 */
function ffnc_render_deals_section( $slug ) {
	$data = ffnc_get_brand_data( $slug );
	if ( ! $data || empty( $data['deals'] ) ) {
		return '';
	}
	$by_id = array();
	foreach ( $data['categories'] as $cat ) {
		foreach ( $cat['items'] as $item ) {
			$by_id[ $item['id'] ] = $item;
		}
	}
	$keys = array( 'calories', 'protein', 'carbs', 'fat', 'sodium' );
	$rows = array();
	foreach ( $data['deals'] as $deal ) {
		$t  = array_fill_keys( $keys, 0 );
		$ok = ! empty( $deal['items'] );
		foreach ( (array) $deal['items'] as $line ) {
			if ( empty( $by_id[ $line['id'] ] ) ) {
				$ok = false;
				break;
			}
			foreach ( $keys as $k ) {
				$t[ $k ] += (float) ( isset( $by_id[ $line['id'] ][ $k ] ) ? $by_id[ $line['id'] ][ $k ] : 0 ) * max( 1, (int) $line['qty'] );
			}
		}
		if ( $ok ) {
			$rows[] = array( 'deal' => $deal, 'totals' => $t );
		}
	}
	if ( ! $rows ) {
		return '';
	}

	$brand = ffnc_get_brand( $slug );
	$bname = $brand ? $brand['name'] : $data['brand'];
	$meta  = isset( $data['deals_meta'] ) ? $data['deals_meta'] : array();

	ob_start();
	?>
	<section class="ffnc-deals" id="deals">
		<h2><?php echo esc_html( sprintf( /* translators: %s brand */ __( '%s Deals & Combos: Calories and Nutrition', 'ffnc' ), $bname ) ); ?></h2>
		<p><?php esc_html_e( 'Totals add up the official values of every item in the deal. Tap "Build in calculator" to load the deal into the calculator above and change any item.', 'ffnc' ); ?></p>
		<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Deals nutrition table (scrollable)', 'ffnc' ); ?>">
		<table class="ffnc-nutrition-table ffnc-deals-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Deal', 'ffnc' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Fat', 'ffnc' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $r ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $r['deal']['name'] ); ?>
						<?php if ( ! empty( $r['deal']['includes'] ) ) : ?>
							<span class="ffnc-deal-includes"><?php echo esc_html( $r['deal']['includes'] ); ?></span>
						<?php endif; ?>
						<a class="ffnc-deal-build" href="<?php echo esc_url( add_query_arg( 'deal', $r['deal']['id'], get_permalink() ) ); ?>"><?php esc_html_e( 'Build in calculator', 'ffnc' ); ?></a>
					</th>
					<td><?php echo esc_html( ffnc_fmt_num( round( $r['totals']['calories'], 1 ) ) ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( round( $r['totals']['protein'], 1 ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( round( $r['totals']['carbs'], 1 ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( round( $r['totals']['fat'], 1 ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( round( $r['totals']['sodium'], 1 ) ) . 'mg' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="ffnc-deals-note">
			<?php
			echo esc_html( isset( $meta['note'] ) ? $meta['note'] : '' );
			if ( ! empty( $meta['checked'] ) ) {
				echo ' ' . esc_html( sprintf( /* translators: %s date */ __( 'Deal contents checked %s.', 'ffnc' ), ffnc_format_review_date( $meta['checked'], 'F j, Y' ) ) );
			}
			if ( ! empty( $meta['source_url'] ) ) {
				echo ' <a href="' . esc_url( $meta['source_url'] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( ! empty( $meta['source_name'] ) ? $meta['source_name'] : __( 'Source', 'ffnc' ) ) . '</a>';
			}
			?>
		</p>
	</section>
	<?php
	return ob_get_clean();
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
	if ( '1' === get_post_meta( $post_id, '_ffnc_is_calculator_page', true ) && ffnc_get_page_brand_slug( $post_id ) ) {
		$extra .= ffnc_render_deals_section( ffnc_get_page_brand_slug( $post_id ) );
		$extra .= ffnc_render_calculator_tools_links( ffnc_get_page_brand_slug( $post_id ) );
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
/**
 * Hub and tool pages for the header "Tools" menu and the footer "Explore"
 * column. Only published pages are returned, so neither ships a broken link.
 *
 * @return array[] array( url, label )
 */
function ffnc_explore_links() {
	$links = array();
	$pages = array(
		'calories'                             => __( 'Calories by Menu Item', 'ffnc' ),
		'tools'                                => __( 'All Free Tools', 'ffnc' ),
		'what-to-order'                        => __( 'What Should I Order?', 'ffnc' ),
		'fast-food-day-planner'                => __( 'Day Planner', 'ffnc' ),
		'same-craving-other-restaurant'        => __( 'Same Craving, Other Chain', 'ffnc' ),
		'high-protein-fast-food'               => __( 'High-Protein Fast Food', 'ffnc' ),
		'fast-food-under-500-calories'         => __( 'Under 500 Calories', 'ffnc' ),
		'low-carb-fast-food'                   => __( 'Low-Carb Fast Food', 'ffnc' ),
		'low-sodium-fast-food'                 => __( 'Low-Sodium Fast Food', 'ffnc' ),
		'diabetic-friendly-fast-food'          => __( 'Diabetic-Friendly Fast Food', 'ffnc' ),
		'vegetarian-fast-food'                 => __( 'Vegetarian Fast Food', 'ffnc' ),
		'small-portion-high-protein-fast-food' => __( 'Small-Portion, High-Protein', 'ffnc' ),
		'fast-food-cheat-sheets'               => __( 'Cheat Sheets', 'ffnc' ),
		'menu-changes'                         => __( 'Menu Changes', 'ffnc' ),
		'fast-food-nutrition-report'           => __( 'Nutrition Report', 'ffnc' ),
	);
	foreach ( $pages as $path => $label ) {
		$url = ffnc_page_url( $path );
		if ( $url ) {
			$links[ $path ] = array( $url, $label );
		}
	}
	return $links;
}

function ffnc_default_menu() {
	$brands          = ffnc_get_live_brands();
	$hub_url         = esc_url( ffnc_page_url( 'calculators' ) ? ffnc_page_url( 'calculators' ) : home_url( '/calculators/' ) );
	$blog_page_id    = (int) get_option( 'page_for_posts' );
	$explore         = ffnc_explore_links();
	$tools           = array_diff_key( $explore, array_flip( array( 'calories', 'tools', 'menu-changes', 'fast-food-nutrition-report' ) ) );
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

		<?php if ( isset( $explore['calories'] ) ) : ?>
			<li><a href="<?php echo esc_url( $explore['calories'][0] ); ?>"><?php esc_html_e( 'Calories by Item', 'ffnc' ); ?></a></li>
		<?php endif; ?>

		<?php if ( $tools ) : ?>
			<li class="ffnc-menu-item-dropdown">
				<button type="button" class="ffnc-menu-dropdown-toggle" aria-expanded="false">
					<?php esc_html_e( 'Tools', 'ffnc' ); ?>
					<span class="ffnc-caret" aria-hidden="true"></span>
				</button>
				<div class="ffnc-submenu ffnc-submenu-tools">
					<?php if ( isset( $explore['tools'] ) ) : ?>
						<a class="ffnc-submenu-view-all" href="<?php echo esc_url( $explore['tools'][0] ); ?>"><?php esc_html_e( 'View all tools →', 'ffnc' ); ?></a>
					<?php endif; ?>
					<ul>
						<?php foreach ( $tools as $ffnc_l ) : ?>
							<li><a href="<?php echo esc_url( $ffnc_l[0] ); ?>"><span><?php echo esc_html( $ffnc_l[1] ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</li>
		<?php endif; ?>

		<?php if ( $blog_page_id ) : ?>
			<li><a href="<?php echo esc_url( get_permalink( $blog_page_id ) ); ?>"><?php esc_html_e( 'Nutrition Guides', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_compare_url() ) : ?>
			<li><a href="<?php echo esc_url( ffnc_compare_url() ); ?>"><?php esc_html_e( 'Compare', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_methodology_url() ) : ?>
			<li><a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'Methodology', 'ffnc' ); ?></a></li>
		<?php endif; ?>
		<?php if ( ffnc_page_url( 'about' ) ) : ?>
			<li><a href="<?php echo esc_url( ffnc_page_url( 'about' ) ); ?>"><?php esc_html_e( 'About', 'ffnc' ); ?></a></li>
		<?php endif; ?>
	</ul>
	<?php
}
