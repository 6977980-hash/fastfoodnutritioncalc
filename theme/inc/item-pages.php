<?php
/**
 * Generated pages for the keyword research priorities:
 *
 *   /calories/<brand>-<item>/        one page per menu item (sizes grouped),
 *                                    e.g. /calories/mcdonalds-big-mac/
 *   /calories/<brand>-<meal>/        meal totals, e.g. Big Mac Meal
 *   /compare/<a>-vs-<b>/             head-to-head pages, e.g. Big Mac vs Whopper
 *   [ff_vegetarian_list]             the vegetarian page's list
 *   [ff_item_index]                  the /calories/ hub
 *
 * The pages are ordinary WordPress pages (so the sitemap, Rank Math and
 * breadcrumbs treat them like any other page) whose content is a single
 * shortcode. Every number is read from assets/data at render time, and SEO
 * titles/descriptions are built on the fly, so nothing goes stale when a
 * dataset changes. Hand-picked lists (meals, comparisons, vegetarian items)
 * live in inc/page-catalog.json.
 *
 * Pages are created, published, a batch at a time when an editor opens
 * wp-admin. A page created once is never re-created, so deleting one in
 * WordPress keeps it deleted.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ffnc_page_catalog() {
	static $cat = null;
	if ( null === $cat ) {
		$path = FFNC_DIR . '/inc/page-catalog.json';
		$cat  = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array(); // phpcs:ignore
		$cat  = wp_parse_args( is_array( $cat ) ? $cat : array(), array( 'meals' => array(), 'meal_sets' => array(), 'comparisons' => array(), 'vegetarian' => array(), 'vegetarian_notes' => array(), 'meal_copy' => array() ) );
	}
	return $cat;
}

/**
 * Brand wording for meal pages: default size word, title tag and the extra cut-calories tip.
 *
 * @param string $slug Brand slug.
 * @return array
 */
function ffnc_meal_copy( $slug ) {
	$cat = ffnc_page_catalog();
	return wp_parse_args( isset( $cat['meal_copy'][ $slug ] ) ? $cat['meal_copy'][ $slug ] : array(), array(
		'size'      => '',
		'title_tag' => __( 'With Side & Drink', 'ffnc' ),
		'cut_tip'   => '',
	) );
}

/**
 * "A medium Big Mac Meal" / "A Chicken Sandwich Meal".
 *
 * @param array $meal Resolved meal.
 * @return string
 */
function ffnc_meal_phrase( $meal ) {
	$size = ffnc_meal_copy( $meal['brand'] )['size'];
	return sprintf( /* translators: %s meal with optional size */ __( 'A %s', 'ffnc' ), trim( $size . ' ' . $meal['name'] ) );
}

/* -------------------------------------------------------------------------
 * Item groups: sizes of one product share a page
 * ---------------------------------------------------------------------- */

/**
 * "Chicken McNuggets (10 pc)" => array( 'Chicken McNuggets', '10 pc' ).
 * "Orange Chicken (6.2 oz)"   => array( 'Orange Chicken', '6.2 oz' ).
 * "Little Cajun Fries"        => array( 'Cajun Fries', 'Little' ).
 */
function ffnc_split_item_size( $name ) {
	$base = $name;
	$size = '';
	if ( preg_match( '/^(.*\S)\s*\(([^()]+)\)\s*$/', $name, $m ) ) {
		$base = $m[1];
		$size = $m[2];
	}
	// Five Guys names its fry sizes up front.
	if ( preg_match( '/^(little|regular|large)\s+((?:cajun\s+)?fries)$/i', $base, $m ) ) {
		$base = ucfirst( $m[2] );
		$size = $m[1];
	}
	return array( $base, $size );
}

/** URL slug for an item name: 6" B.M.T. => 6-inch-bmt. */
function ffnc_item_slug( $base ) {
	return sanitize_title( str_replace( array( '"', '.' ), array( ' inch ', '' ), $base ) );
}

function ffnc_brand_display_name( $brand, $base ) {
	return 0 === stripos( $base, $brand['name'] ) ? $base : $brand['name'] . ' ' . $base;
}

/**
 * Every item page for one brand, keyed by group slug.
 *
 * @return array[] slug => array( slug, base, title, items[] (item + size), category )
 */
function ffnc_item_groups( $slug ) {
	static $cache = array();
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	$brand  = ffnc_get_brand( $slug );
	$data   = ffnc_get_brand_data( $slug );
	$groups = array();
	if ( $brand && $data ) {
		foreach ( $data['categories'] as $cat ) {
			foreach ( $cat['items'] as $item ) {
				list( $base, $size ) = ffnc_split_item_size( $item['name'] );
				$g = ffnc_item_slug( $base );
				if ( ! isset( $groups[ $g ] ) ) {
					$groups[ $g ] = array(
						'slug'     => $g,
						'base'     => $base,
						'title'    => ffnc_brand_display_name( $brand, $base ),
						'category' => $cat['name'],
						'items'    => array(),
					);
				}
				$groups[ $g ]['items'][] = $item + array( 'size' => $size, 'category_name' => $cat['name'] );
			}
		}
		foreach ( $groups as $g => $group ) {
			$cals = array_map( function ( $i ) { return (float) ffnc_num( $i, 'calories' ); }, $group['items'] );
			// Sauces, plain veg and single nuggets are not worth a page of their own.
			if ( max( $cals ) < 100 ) {
				unset( $groups[ $g ] );
				continue;
			}
			usort( $groups[ $g ]['items'], function ( $a, $b ) {
				return ffnc_num( $a, 'calories' ) <=> ffnc_num( $b, 'calories' );
			} );
			$groups[ $g ]['thin'] = ffnc_item_group_is_thin( $slug, $groups[ $g ] );
		}
	}
	$cache[ $slug ] = $groups;
	return $groups;
}

/**
 * Groups too thin to stand as a search result of their own: Chipotle's
 * build-your-own ingredients, drinks, coffee and Panera's bread containers.
 * Their pages stay up (noindex, follow) but are left out of the sitemap and
 * of every list that links item pages.
 */
function ffnc_item_group_is_thin( $brand, $group ) {
	$types = ffnc_item_types();
	$ids   = array( 'panera-bread' => array( 'bread-bowl', 'baguette-quarter' ) );
	foreach ( $group['items'] as $i ) {
		$type = isset( $types[ $brand ][ $i['id'] ] ) ? $types[ $brand ][ $i['id'] ] : '';
		if ( ! in_array( $type, array( 'ingredient', 'drink', 'coffee' ), true ) && ! in_array( $i['id'], isset( $ids[ $brand ] ) ? $ids[ $brand ] : array(), true ) ) {
			return false;
		}
	}
	return true;
}

/** Is this generated-page key a thin item page? */
function ffnc_generated_key_is_thin( $key ) {
	list( $kind, $rest ) = array_pad( explode( ':', (string) $key, 2 ), 2, '' );
	if ( 'item' !== $kind ) {
		return false;
	}
	$r = ffnc_resolve_item_key( $rest );
	return $r && ! empty( $r['group']['thin'] );
}

/** The size shown in the headline: medium/regular, else the middle size. */
function ffnc_group_main_item( $group ) {
	foreach ( array( 'medium', 'regular', 'grande' ) as $want ) {
		foreach ( $group['items'] as $i ) {
			if ( strtolower( $i['size'] ) === $want ) {
				return $i;
			}
		}
	}
	return $group['items'][ (int) floor( count( $group['items'] ) / 2 ) ];
}

function ffnc_item_group_slug( $brand, $item_id ) {
	foreach ( ffnc_item_groups( $brand ) as $g ) {
		foreach ( $g['items'] as $i ) {
			if ( $i['id'] === $item_id ) {
				return $g['slug'];
			}
		}
	}
	return '';
}

/* -------------------------------------------------------------------------
 * Page specs + lookups
 * ---------------------------------------------------------------------- */

/**
 * Every generated page: key => array( slug, title, content, parent ).
 * parent: 'calories' (the hub) or 'compare'.
 */
function ffnc_generated_page_specs() {
	static $specs = null;
	if ( null !== $specs ) {
		return $specs;
	}
	$specs = array();
	$cat   = ffnc_page_catalog();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		foreach ( ffnc_item_groups( $slug ) as $g ) {
			$page_slug = 0 === strpos( $g['slug'], $slug ) ? $g['slug'] : $slug . '-' . $g['slug'];
			$specs[ 'item:' . $slug . ':' . $g['slug'] ] = array(
				'slug'    => $page_slug,
				'title'   => sprintf( /* translators: %s item */ __( '%s Calories and Nutrition Facts', 'ffnc' ), $g['title'] ),
				'content' => '[ff_item_page key="' . $slug . ':' . $g['slug'] . '"]',
				'parent'  => 'calories',
			);
		}
	}
	foreach ( $cat['meals'] as $meal ) {
		$brand = ffnc_get_brand( $meal['brand'] );
		if ( ! $brand || ! ffnc_get_brand_item( $meal['brand'], $meal['main'] ) ) {
			continue;
		}
		$specs[ 'meal:' . $meal['brand'] . ':' . $meal['main'] ] = array(
			'slug'    => $meal['brand'] . '-' . sanitize_title( $meal['name'] ),
			'title'   => sprintf( /* translators: %s meal */ __( '%s Calories', 'ffnc' ), ffnc_brand_display_name( $brand, $meal['name'] ) ),
			'content' => '[ff_meal_page key="' . $meal['brand'] . ':' . $meal['main'] . '"]',
			'parent'  => 'calories',
		);
	}
	foreach ( $cat['comparisons'] as $c ) {
		$pair = ffnc_comparison( $c['slug'] );
		if ( ! $pair ) {
			continue;
		}
		$specs[ 'compare:' . $c['slug'] ] = array(
			'slug'    => $c['slug'],
			'title'   => sprintf( /* translators: %s comparison */ __( '%s: Calories and Nutrition Compared', 'ffnc' ), $pair['title'] ),
			'content' => '[ff_compare_page slug="' . $c['slug'] . '"]',
			'parent'  => 'compare',
		);
	}
	return $specs;
}

/** key => post id of every generated page created so far. */
function ffnc_generated_page_ids() {
	$ids = get_option( 'ffnc_gen_pages', array() );
	return is_array( $ids ) ? $ids : array();
}

/**
 * Permalink of a generated page by key, or '' when it is not published.
 * Thin item pages answer '' too, so no list links them.
 */
function ffnc_generated_page_url( $key ) {
	static $cache = array();
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	if ( ffnc_generated_key_is_thin( $key ) ) {
		$cache[ $key ] = '';
		return '';
	}
	$ids = ffnc_generated_page_ids();
	$url = '';
	if ( ! $cache && $ids && function_exists( '_prime_post_caches' ) ) {
		_prime_post_caches( array_values( $ids ), false, false ); // One query for the whole hub, not one per link.
	}
	if ( ! empty( $ids[ $key ] ) && 'publish' === get_post_status( $ids[ $key ] ) ) {
		$url = get_permalink( $ids[ $key ] );
	}
	$cache[ $key ] = $url;
	return $url;
}

/** Item page URL for a brand item id (its size group's page), or ''. */
function ffnc_item_page_url( $brand, $item_id ) {
	$g = ffnc_item_group_slug( $brand, $item_id );
	return $g ? ffnc_generated_page_url( 'item:' . $brand . ':' . $g ) : '';
}

/** The generated-page key of a post, or ''. */
function ffnc_generated_key( $post_id ) {
	return (string) get_post_meta( $post_id, '_ffnc_gen', true );
}

/**
 * Create the generated pages, a batch per wp-admin load, once each.
 */
function ffnc_ensure_generated_pages() {
	if ( ! current_user_can( 'publish_pages' ) || wp_doing_ajax() ) {
		return;
	}
	$specs = ffnc_generated_page_specs();
	$hash  = md5( implode( '|', array_keys( $specs ) ) );
	if ( get_option( 'ffnc_gen_pages_hash' ) === $hash ) {
		return;
	}
	$ids     = ffnc_generated_page_ids();
	$parents = array();
	foreach ( array( 'calories', 'compare' ) as $p ) {
		$page          = get_page_by_path( $p, OBJECT, 'page' );
		$parents[ $p ] = ( $page && 'publish' === $page->post_status ) ? $page : null;
	}
	if ( ! $parents['compare'] ) {
		$parents['compare'] = $parents['calories'];
	}
	$budget = 60; // Keep each admin page load quick; the rest follow on the next ones.
	foreach ( $specs as $key => $spec ) {
		if ( isset( $ids[ $key ] ) ) {
			continue;
		}
		if ( $budget-- <= 0 ) {
			break;
		}
		$parent = $parents[ $spec['parent'] ];
		$path   = ( $parent ? get_page_uri( $parent ) . '/' : '' ) . $spec['slug'];
		$found  = get_page_by_path( $path, OBJECT, 'page' );
		if ( $found ) {
			$ids[ $key ] = $found->ID;
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $spec['title'],
			'post_name'    => $spec['slug'],
			'post_parent'  => $parent ? $parent->ID : 0,
			'post_content' => $spec['content'],
			'menu_order'   => 10,
		) );
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		update_post_meta( $id, '_ffnc_gen', $key );
		$ids[ $key ] = $id;
	}
	update_option( 'ffnc_gen_pages', $ids, false );
	if ( ! array_diff_key( $specs, $ids ) ) {
		update_option( 'ffnc_gen_pages_hash', $hash, false );
	}
}
add_action( 'admin_init', 'ffnc_ensure_generated_pages', 20 ); // After the tool pages (the hub).

/* -------------------------------------------------------------------------
 * Shared bits
 * ---------------------------------------------------------------------- */

/**
 * Lighter items from the same menu category (same rule as the calculator).
 *
 * @return array{items: array[], keeps_protein: bool} keeps_protein is false when no
 *               lighter item keeps 70% of the protein and the fallback is returned.
 */
function ffnc_item_swaps( $brand, $item, $limit = 3 ) {
	$none  = array( 'items' => array(), 'keeps_protein' => false );
	$cal   = ffnc_num( $item, 'calories' );
	$types = ffnc_item_types();
	if ( null === $cal || ( isset( $types[ $brand ][ $item['id'] ] ) && 'ingredient' === $types[ $brand ][ $item['id'] ] ) ) {
		return $none;
	}
	$lower = array_filter( ffnc_get_brand_items( $brand ), function ( $i ) use ( $item, $cal ) {
		return $i['id'] !== $item['id'] && $i['category_name'] === $item['category_name'] && null !== ffnc_num( $i, 'calories' ) && ffnc_num( $i, 'calories' ) < $cal;
	} );
	$keep = array_filter( $lower, function ( $i ) use ( $item ) {
		return (float) ffnc_num( $i, 'protein' ) >= 0.7 * (float) ffnc_num( $item, 'protein' );
	} );
	if ( $keep ) {
		usort( $keep, function ( $a, $b ) {
			return ffnc_num( $a, 'calories' ) <=> ffnc_num( $b, 'calories' );
		} );
		return array( 'items' => array_slice( $keep, 0, $limit ), 'keeps_protein' => true );
	}
	if ( ! $lower ) {
		return $none;
	}
	usort( $lower, function ( $a, $b ) {
		return ffnc_num( $b, 'calories' ) <=> ffnc_num( $a, 'calories' );
	} );
	return array( 'items' => array_slice( $lower, 0, 1 ), 'keeps_protein' => false );
}

/** Reader-facing plural for an item type ("burgers", "chicken sandwiches"). */
function ffnc_type_plural( $type ) {
	$map = array(
		'burger'           => __( 'burgers', 'ffnc' ),
		'chicken-sandwich' => __( 'chicken sandwiches', 'ffnc' ),
		'nuggets'          => __( 'nuggets and tenders', 'ffnc' ),
		'pizza'            => __( 'pizza slices', 'ffnc' ),
		'sub'              => __( 'subs and sandwiches', 'ffnc' ),
		'mexican'          => __( 'tacos and burritos', 'ffnc' ),
		'chicken-piece'    => __( 'chicken pieces', 'ffnc' ),
		'wings'            => __( 'wings', 'ffnc' ),
		'breakfast'        => __( 'breakfast items', 'ffnc' ),
		'salad'            => __( 'salads', 'ffnc' ),
		'soup'             => __( 'soups', 'ffnc' ),
		'entree'           => __( 'entrees', 'ffnc' ),
		'fries'            => __( 'fries', 'ffnc' ),
		'side'             => __( 'sides', 'ffnc' ),
		'shake'            => __( 'shakes and frozen treats', 'ffnc' ),
		'dessert'          => __( 'desserts', 'ffnc' ),
		'drink'            => __( 'drinks', 'ffnc' ),
		'coffee'           => __( 'coffee drinks', 'ffnc' ),
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : __( 'items', 'ffnc' );
}

/** Single or multi-patty (double, triple, stacked) by the item name. */
function ffnc_item_size_class( $name ) {
	return preg_match( '/\b(double|triple|stacked?|baconator|2 patties|3 patties|quad)\b/i', $name ) ? 'multi' : 'single';
}

/**
 * The similar-calorie item of the same kind at each other chain, nearest
 * calories first. Items of the same size class (single vs double patty)
 * are preferred; another class is used only when a chain has none.
 */
function ffnc_item_at_other_chains( $brand, $item, $limit = 5 ) {
	$types = ffnc_item_types();
	$type  = isset( $types[ $brand ][ $item['id'] ] ) ? $types[ $brand ][ $item['id'] ] : '';
	if ( ! $type || in_array( $type, array( 'ingredient', 'other' ), true ) ) {
		return array();
	}
	$cal   = (float) ffnc_num( $item, 'calories' );
	$class = ffnc_item_size_class( $item['name'] );
	$best  = array();
	foreach ( ffnc_all_items() as $x ) {
		if ( $x['brand'] === $brand || $x['type'] !== $type || null === ffnc_num( $x['item'], 'calories' ) || preg_match( '/family|bucket|\(1 (piece|wing)\)/i', $x['item']['name'] ) ) {
			continue;
		}
		$d = abs( ffnc_num( $x['item'], 'calories' ) - $cal ) + ( ffnc_item_size_class( $x['item']['name'] ) === $class ? 0 : 100000 );
		if ( ! isset( $best[ $x['brand'] ] ) || $d < $best[ $x['brand'] ]['d'] ) {
			$best[ $x['brand'] ] = $x + array( 'd' => $d );
		}
	}
	usort( $best, function ( $a, $b ) {
		return $a['d'] <=> $b['d'];
	} );
	return array_slice( $best, 0, $limit );
}

/** Link to an item's page, or to its calculator with the item in the order. */
function ffnc_item_link( $brand, $item, $label = '' ) {
	$b   = ffnc_get_brand( $brand );
	$url = ffnc_item_page_url( $brand, $item['id'] );
	if ( ! $url && $b ) {
		$url = ffnc_order_link( home_url( $b['url'] ), $item['id'] );
	}
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ? $label : $item['name'] ) . '</a>';
}

/**
 * "Official U.S. values from <source>, last checked <date>." An item with its
 * own official page (source_url) links that page; an item's own last_checked
 * date wins over the dataset's last_reviewed.
 */
function ffnc_source_line( $brand, $item = null ) {
	$b    = ffnc_get_brand( $brand );
	$meta = ffnc_get_dataset_meta( $brand );
	$src  = $meta['source_url'] ? '<a href="' . esc_url( $meta['source_url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $meta['source_name'] ? $meta['source_name'] : $b['name'] ) . '</a>' : esc_html( $b['name'] );
	if ( $item && ! empty( $item['source_url'] ) ) {
		$src .= ' (' . sprintf(
			/* translators: %s item */
			esc_html__( 'official page for the %s', 'ffnc' ),
			'<a href="' . esc_url( $item['source_url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( $item['name'] ) . '</a>'
		) . ')';
	}
	$date = ffnc_format_review_date( $item && ! empty( $item['last_checked'] ) ? $item['last_checked'] : $meta['last_reviewed'], 'F j, Y' );
	if ( ! ffnc_dataset_is_verified( $brand ) ) {
		return '<p class="ffnc-item-source">' . sprintf(
			/* translators: %s source link */
			esc_html__( 'Reference values compiled from %s; not yet checked line by line against the official source, so treat them as estimates.', 'ffnc' ),
			$src // Escaped above.
		) . '</p>';
	}
	$allergens = $meta['allergen_url'] ? ' ' . sprintf(
		/* translators: %s link */
		esc_html__( 'For allergens, see %s.', 'ffnc' ),
		'<a href="' . esc_url( $meta['allergen_url'] ) . '" rel="nofollow noopener" target="_blank">' . esc_html( sprintf( /* translators: %s brand */ __( '%s official allergen information', 'ffnc' ), $b['name'] ) ) . '</a>'
	) : '';
	return '<p class="ffnc-item-source">' . sprintf(
		/* translators: 1 source link 2 date */
		esc_html__( 'Official U.S. values from %1$s, last checked %2$s. Restaurants change recipes, so values in your area can differ.', 'ffnc' ),
		$src, // Escaped above.
		esc_html( $date ? $date : __( 'recently', 'ffnc' ) )
	) . $allergens . '</p>';
}

/**
 * Deals in the brand's dataset that include any size of this item, with the
 * deal's total from the same item values the calculator uses.
 */
function ffnc_item_deals_block( $slug, $group ) {
	$data = ffnc_get_brand_data( $slug );
	if ( empty( $data['deals'] ) ) {
		return '';
	}
	$ids   = wp_list_pluck( $group['items'], 'id' );
	$brand = ffnc_get_brand( $slug );
	$rows  = array();
	foreach ( $data['deals'] as $deal ) {
		$lines = (array) ( isset( $deal['items'] ) ? $deal['items'] : array() );
		if ( ! array_intersect( $ids, wp_list_pluck( $lines, 'id' ) ) ) {
			continue;
		}
		$cal = 0;
		foreach ( $lines as $l ) {
			$it = ffnc_get_brand_item( $slug, $l['id'] );
			if ( ! $it ) {
				continue 2;
			}
			$cal += (float) ffnc_num( $it, 'calories' ) * max( 1, (int) $l['qty'] );
		}
		$rows[] = '<li><a href="' . esc_url( add_query_arg( 'deal', $deal['id'], home_url( $brand['url'] ) ) ) . '">' . esc_html( $deal['name'] ) . '</a>' .
			( ! empty( $deal['includes'] ) ? ': ' . esc_html( $deal['includes'] ) : '' ) . '. ' .
			esc_html( sprintf( /* translators: %s calories */ __( '%s calories in total.', 'ffnc' ), ffnc_fmt_num( round( $cal, 1 ) ) ) ) . '</li>';
	}
	if ( ! $rows ) {
		return '';
	}
	$meta = isset( $data['deals_meta'] ) ? $data['deals_meta'] : array();
	$note = ! empty( $meta['checked'] ) ? sprintf( /* translators: %s date */ __( 'Deal contents checked %s. Prices and contents vary by location.', 'ffnc' ), ffnc_format_review_date( $meta['checked'], 'F j, Y' ) ) : __( 'Prices and contents vary by location.', 'ffnc' );
	return '<h2>' . esc_html( sprintf( /* translators: %s item */ __( 'Deals that include the %s', 'ffnc' ), $group['base'] ) ) . '</h2><ul class="ffnc-list">' . implode( '', $rows ) . '</ul><p class="ffnc-goal-note">' . esc_html( $note ) . '</p>';
}

/**
 * Entries of the brand's changelog that name this item: added under
 * "New ids", or named at the start of a clause ("...: Chicken Sandwich fat 18 -> 16 g").
 */
function ffnc_item_changes( $slug, $group ) {
	$data = ffnc_get_brand_data( $slug );
	$out  = array();
	if ( empty( $data['meta']['changelog'] ) ) {
		return $out;
	}
	$ids   = wp_list_pluck( $group['items'], 'id' );
	$names = array_unique( array_merge( array( $group['base'] ), wp_list_pluck( $group['items'], 'name' ) ) );
	foreach ( $data['meta']['changelog'] as $c ) {
		$text = (string) $c['change'];
		$added = preg_match( '/New ids:([^.]*)/i', $text, $m ) && array_intersect( $ids, array_map( 'trim', explode( ',', $m[1] ) ) );
		$hit   = $added;
		foreach ( $names as $n ) {
			if ( ! $hit && preg_match( '/(?:^|[:;,]\s|\b(?:and|for)\s)' . preg_quote( $n, '/' ) . '(?=[\s,.;:]|$)/i', $text ) ) {
				$hit = true;
			}
		}
		if ( $hit ) {
			$text  = trim( preg_replace( '/\s*(Removed\/replaced ids|New ids|Removed ids):[^.]*\./i', '', $text ) );
			if ( $added && 0 === stripos( $text, 'Full review' ) ) {
				$text = __( 'Added to our data during a full review against the official U.S. source.', 'ffnc' );
			}
			$out[] = array(
				'date' => $c['date'],
				'text' => ( $added && 0 !== stripos( $text, 'Added' ) ? __( 'First added to our data.', 'ffnc' ) . ' ' : '' ) . $text,
			);
		}
	}
	usort( $out, function ( $a, $b ) {
		return strcmp( $b['date'], $a['date'] );
	} );
	return $out;
}

/** 1 => 1st, 2 => 2nd, 11 => 11th. */
function ffnc_ordinal( $n ) {
	$n = (int) $n;
	$s = ( $n % 100 >= 11 && $n % 100 <= 13 ) ? 'th' : ( array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' )[ $n % 10 ] );
	return $n . $s;
}

/**
 * Where the item sits on its own menu category and among items of the same
 * kind at every chain we cover, by calories and by protein per calorie.
 */
function ffnc_item_rank_block( $slug, $group, $main ) {
	$cal = ffnc_num( $main, 'calories' );
	if ( null === $cal ) {
		return '';
	}
	$types = ffnc_item_types();
	$type  = isset( $types[ $slug ][ $main['id'] ] ) ? $types[ $slug ][ $main['id'] ] : '';
	$dens  = function ( $i ) {
		return (float) ffnc_num( $i, 'protein' ) / max( 1, (float) ffnc_num( $i, 'calories' ) );
	};
	// "the 3rd lightest" in the lower half, "the 2nd highest in calories" in the upper half.
	$pos   = function ( $list, $get ) use ( $cal ) {
		$lighter = count( array_filter( $list, function ( $x ) use ( $get, $cal ) { return $get( $x ) < $cal; } ) );
		$heavier = count( array_filter( $list, function ( $x ) use ( $get, $cal ) { return $get( $x ) > $cal; } ) );
		return $lighter <= $heavier
			? sprintf( /* translators: %s rank */ __( 'the %slightest', 'ffnc' ), $lighter ? ffnc_ordinal( $lighter + 1 ) . ' ' : '' )
			: sprintf( /* translators: %s rank */ __( 'the %shighest in calories', 'ffnc' ), $heavier ? ffnc_ordinal( $heavier + 1 ) . ' ' : '' );
	};
	$lines = array();
	$cat   = array_values( array_filter( ffnc_get_brand_items( $slug ), function ( $i ) use ( $main ) {
		return $i['category_name'] === $main['category_name'] && null !== ffnc_num( $i, 'calories' );
	} ) );
	if ( count( $cat ) >= 3 ) {
		$denser  = count( array_filter( $cat, function ( $i ) use ( $main, $dens ) { return $dens( $i ) > $dens( $main ); } ) );
		$lines[] = sprintf(
			/* translators: 1 item 2 rank 3 count 4 category 5 brand 6 protein rank */
			__( 'The %1$s is %2$s of the %3$d items in the %5$s “%4$s” menu, and %6$s for protein per calorie.', 'ffnc' ),
			$main['name'],
			$pos( $cat, function ( $i ) { return ffnc_num( $i, 'calories' ); } ),
			count( $cat ),
			$main['category_name'],
			ffnc_get_brand( $slug )['name'],
			ffnc_ordinal( $denser + 1 )
		);
	}
	if ( $type && ! in_array( $type, array( 'ingredient', 'other' ), true ) ) {
		$same = array_values( array_filter( ffnc_all_items(), function ( $x ) use ( $type ) {
			return $x['type'] === $type && null !== ffnc_num( $x['item'], 'calories' );
		} ) );
		$chains = count( array_unique( wp_list_pluck( $same, 'brand' ) ) );
		if ( count( $same ) >= 5 && $chains >= 2 ) {
			$avg     = array_sum( array_map( function ( $x ) { return (float) ffnc_num( $x['item'], 'calories' ); }, $same ) ) / count( $same );
			$lines[] = sprintf(
				/* translators: 1 rank 2 count 3 chains 4 average */
				__( 'Across the %2$d items of the same kind we track at %3$d chains, it is %1$s; their average is %4$s calories.', 'ffnc' ),
				$pos( $same, function ( $x ) { return ffnc_num( $x['item'], 'calories' ); } ),
				count( $same ),
				$chains,
				ffnc_fmt_num( round( $avg ) )
			);
		}
	}
	if ( ! $lines ) {
		return '';
	}
	return '<h2>' . esc_html( sprintf( /* translators: %s item */ __( 'How the %s ranks', 'ffnc' ), $group['base'] ) ) . '</h2><ul class="ffnc-list"><li>' . implode( '</li><li>', array_map( 'esc_html', $lines ) ) . '</li></ul>';
}

function ffnc_item_changes_block( $slug, $group ) {
	$rows = ffnc_item_changes( $slug, $group );
	if ( ! $rows ) {
		return '';
	}
	$li = '';
	foreach ( $rows as $r ) {
		$li .= '<li><strong>' . esc_html( ffnc_format_review_date( $r['date'], 'F j, Y' ) ) . ':</strong> ' . esc_html( $r['text'] ) . '</li>';
	}
	$all = function_exists( 'ffnc_page_url' ) ? ffnc_page_url( 'menu-changes' ) : '';
	return '<h2>' . esc_html( sprintf( /* translators: %s item */ __( '%s in our menu change log', 'ffnc' ), $group['base'] ) ) . '</h2><ul class="ffnc-list">' . $li . '</ul>' .
		( $all ? '<p><a href="' . esc_url( $all ) . '">' . esc_html__( 'Every menu change we have logged', 'ffnc' ) . '</a></p>' : '' );
}

function ffnc_macro_chips( $item ) {
	$out = '<ul class="ffnc-macro-chips">';
	foreach ( array( 'protein' => array( __( 'Protein', 'ffnc' ), 'g' ), 'carbs' => array( __( 'Carbs', 'ffnc' ), 'g' ), 'fat' => array( __( 'Fat', 'ffnc' ), 'g' ), 'sodium' => array( __( 'Sodium', 'ffnc' ), 'mg' ) ) as $k => $l ) {
		$v    = ffnc_num( $item, $k );
		$out .= '<li><strong>' . esc_html( null === $v ? '—' : ffnc_fmt_num( $v ) . $l[1] ) . '</strong><span>' . esc_html( $l[0] ) . '</span></li>';
	}
	return $out . '</ul>';
}

/** Exercise minutes at 155 lb (Harvard Health 30-minute table, as in the calculator). */
function ffnc_burn_minutes( $calories ) {
	$per30 = array( 'walk' => 133, 'run' => 288, 'bike' => 288 );
	return array_map( function ( $c ) use ( $calories ) {
		return (int) round( $calories / $c * 30 );
	}, $per30 );
}

function ffnc_render_faq( $qas ) {
	$sc = '';
	foreach ( $qas as $q => $a ) {
		$sc .= '[ff_faq_item question="' . esc_attr( $q ) . '"]' . $a . '[/ff_faq_item]';
	}
	return '<h2>' . esc_html__( 'Questions people ask', 'ffnc' ) . '</h2>' . do_shortcode( '[ff_faq]' . $sc . '[/ff_faq]' );
}

function ffnc_sum_items( $items ) {
	$t = array_fill_keys( array( 'calories', 'protein', 'carbs', 'fat', 'sodium' ), 0 );
	foreach ( $items as $i ) {
		foreach ( $t as $k => $v ) {
			$t[ $k ] = round( $v + (float) ffnc_num( $i, $k ), 1 );
		}
	}
	return $t;
}

function ffnc_missing_page_notice( $brand = '' ) {
	$b = $brand ? ffnc_get_brand( $brand ) : null;
	return '<p class="ffnc-goal-note">' . esc_html__( 'This item is no longer in our nutrition data.', 'ffnc' ) . ( $b ? ' <a href="' . esc_url( home_url( $b['url'] ) ) . '">' . esc_html( sprintf( /* translators: %s calculator */ __( 'See the current menu in the %s', 'ffnc' ), $b['title'] ) ) . '</a>' : '' ) . '</p>';
}

/* -------------------------------------------------------------------------
 * [ff_item_page key="mcdonalds:big-mac"]
 * ---------------------------------------------------------------------- */
function ffnc_resolve_item_key( $key ) {
	$parts = explode( ':', (string) $key );
	if ( 2 !== count( $parts ) ) {
		return null;
	}
	$groups = ffnc_item_groups( sanitize_title( $parts[0] ) );
	return isset( $groups[ $parts[1] ] ) ? array( 'brand' => sanitize_title( $parts[0] ), 'group' => $groups[ $parts[1] ] ) : null;
}

function ffnc_shortcode_item_page( $atts ) {
	$atts = shortcode_atts( array( 'key' => '' ), $atts, 'ff_item_page' );
	$r    = ffnc_resolve_item_key( $atts['key'] );
	if ( ! $r ) {
		return ffnc_missing_page_notice( strtok( $atts['key'], ':' ) );
	}
	$slug   = $r['brand'];
	$group  = $r['group'];
	$brand  = ffnc_get_brand( $slug );
	$main   = ffnc_group_main_item( $group );
	$multi  = count( $group['items'] ) > 1;
	$cal    = ffnc_num( $main, 'calories' );
	$calc   = home_url( $brand['url'] );
	$name   = $group['title'];
	$single = $multi ? $name . ' (' . $main['size'] . ')' : ffnc_brand_display_name( $brand, $main['name'] );
	$cat    = ffnc_page_catalog();
	$extras = ffnc_items_have_extras( $group['items'] );
	ob_start();
	?>
	<div class="ffnc-item-page" style="--ffnc-brand-color:<?php echo esc_attr( ffnc_get_brand_color( $brand ) ); ?>">
		<div class="ffnc-item-hero">
			<p class="ffnc-item-answer">
				<?php
				echo wp_kses_post( sprintf(
					/* translators: 1 item 2 calories */
					__( '%1$s: <strong class="ffnc-item-big">%2$s calories</strong>', 'ffnc' ),
					esc_html( $single ),
					esc_html( ffnc_fmt_num( $cal ) )
				) );
				?>
			</p>
			<?php echo ffnc_macro_chips( $main ); // phpcs:ignore ?>
			<?php if ( ! empty( $main['availability'] ) ) : ?>
				<p class="ffnc-item-avail"><?php echo esc_html( sprintf( /* translators: %s e.g. "not sold at every KFC" */ __( 'Availability: %s. Check your local menu.', 'ffnc' ), $main['availability'] ) ); ?></p>
			<?php endif; ?>
			<p class="ffnc-item-actions">
				<a class="ffnc-btn ffnc-btn-solid" href="<?php echo esc_url( ffnc_order_link( $calc, $main['id'] ) ); ?>"><?php esc_html_e( 'Add it to an order in the calculator', 'ffnc' ); ?></a>
			</p>
			<?php echo ffnc_source_line( $slug, $main ); // phpcs:ignore ?>
		</div>

		<?php if ( $multi ) : ?>
			<h2><?php echo esc_html( sprintf( /* translators: %s item */ __( '%s calories by size', 'ffnc' ), $name ) ); ?></h2>
		<?php else : ?>
			<h2><?php echo esc_html( sprintf( /* translators: %s item */ __( '%s nutrition facts', 'ffnc' ), $name ) ); ?></h2>
		<?php endif; ?>
		<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Nutrition facts', 'ffnc' ); ?>">
		<table class="ffnc-nutrition-table">
			<thead><tr>
				<th scope="col"><?php echo esc_html( $multi ? __( 'Size', 'ffnc' ) : __( 'Item', 'ffnc' ) ); ?></th>
				<th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Fat', 'ffnc' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th>
				<?php if ( $extras ) : foreach ( ffnc_extra_nutrients() as $label ) : ?>
					<th scope="col"><?php echo esc_html( $label ); ?></th>
				<?php endforeach; endif; ?>
				<th scope="col"><?php esc_html_e( '% of 2,000 cal', 'ffnc' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $group['items'] as $it ) : ?>
				<tr>
					<th scope="row"><a href="<?php echo esc_url( ffnc_order_link( $calc, $it['id'] ) ); ?>"><?php echo esc_html( $multi ? $it['size'] : $it['name'] ); ?></a></th>
					<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'protein' ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'carbs' ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'fat' ) ) . 'g' ); ?></td>
					<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'sodium' ) ) . 'mg' ); ?></td>
					<?php if ( $extras ) : foreach ( array_keys( ffnc_extra_nutrients() ) as $k ) : ?>
						<td><?php echo esc_html( ffnc_fmt_g( ffnc_num( $it, $k ) ) ); ?></td>
					<?php endforeach; endif; ?>
					<td><?php echo esc_html( round( (float) ffnc_num( $it, 'calories' ) / 20 ) . '%' ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="ffnc-goal-note">
			<?php
			$sod = ffnc_num( $main, 'sodium' );
			echo esc_html( sprintf(
				/* translators: 1 item 2 percent calories 3 percent sodium */
				__( 'The %1$s is about %2$s%% of a 2,000-calorie day and %3$s%% of the 2,300mg daily sodium limit in the U.S. Dietary Guidelines.', 'ffnc' ),
				$single,
				round( (float) $cal / 20 ),
				null === $sod ? '—' : round( $sod / 23 )
			) );
			?>
		</p>

		<?php
		$meal_links = array();
		foreach ( $cat['meals'] as $meal ) {
			if ( $meal['brand'] === $slug && in_array( $meal['main'], wp_list_pluck( $group['items'], 'id' ), true ) ) {
				$url = ffnc_generated_page_url( 'meal:' . $slug . ':' . $meal['main'] );
				$set = isset( $cat['meal_sets'][ $slug ][0] ) ? $cat['meal_sets'][ $slug ][0] : null;
				if ( $url && $set ) {
					$parts        = array_filter( array_map( function ( $id ) use ( $slug ) { return ffnc_get_brand_item( $slug, $id ); }, array_merge( array( $meal['main'] ), $set['items'] ) ) );
					$tot          = ffnc_sum_items( $parts );
					$meal_links[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $meal['name'] ) . '</a>: ' . esc_html( sprintf( /* translators: 1 calories 2 sides */ __( '%1$s calories with %2$s', 'ffnc' ), ffnc_fmt_num( $tot['calories'] ), $set['label'] ) ) . '</li>';
				}
			}
		}
		if ( $meal_links ) :
			?>
			<h2><?php esc_html_e( 'As a meal', 'ffnc' ); ?></h2>
			<ul class="ffnc-list"><?php echo wp_kses_post( implode( '', $meal_links ) ); ?></ul>
		<?php endif; ?>

		<?php echo ffnc_item_deals_block( $slug, $group ); // phpcs:ignore ?>
		<?php echo ffnc_item_changes_block( $slug, $group ); // phpcs:ignore ?>
		<?php echo ffnc_item_rank_block( $slug, $group, $main ); // phpcs:ignore ?>

		<?php
		$swap_r  = ffnc_item_swaps( $slug, $main );
		$swaps   = $swap_r['items'];
		$addons  = array_filter( (array) ( isset( $main['addons'] ) ? $main['addons'] : array() ), function ( $a ) { return ffnc_num( $a, 'calories' ) > 0; } );
		if ( $swaps || $addons ) :
			?>
			<h2><?php echo esc_html( sprintf( /* translators: %s item */ __( 'How to make a %s order lighter', 'ffnc' ), $brand['name'] ) ); ?></h2>
			<ul class="ffnc-list">
				<?php foreach ( $swaps as $s ) : ?>
					<li>
						<?php
						echo wp_kses_post( sprintf(
							/* translators: 1 item link 2 calories saved 3 protein */
							__( 'Order the %1$s instead: %2$s fewer calories, %3$sg protein.', 'ffnc' ),
							ffnc_item_link( $slug, $s ),
							ffnc_fmt_num( $cal - ffnc_num( $s, 'calories' ) ),
							ffnc_fmt_num( ffnc_num( $s, 'protein' ) )
						) );
						?>
					</li>
				<?php endforeach; ?>
				<?php foreach ( $addons as $a ) : ?>
					<li><?php echo esc_html( sprintf( /* translators: 1 add-on 2 calories */ __( '“%1$s” adds %2$s calories.', 'ffnc' ), $a['name'], ffnc_fmt_num( ffnc_num( $a, 'calories' ) ) ) ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php $burn = ffnc_burn_minutes( (float) $cal ); ?>
		<h2><?php esc_html_e( 'How long it takes to burn off', 'ffnc' ); ?></h2>
		<p>
			<?php
			echo esc_html( sprintf(
				/* translators: 1 item 2 walk 3 run 4 bike */
				__( 'For a 155 lb person, the %1$s takes about %2$d minutes of brisk walking, %3$d minutes of running (5 mph) or %4$d minutes of cycling. Figures from Harvard Health’s calories-burned table; heavier people burn calories faster.', 'ffnc' ),
				$single,
				$burn['walk'],
				$burn['run'],
				$burn['bike']
			) );
			?>
		</p>

		<?php
		$others = ffnc_item_at_other_chains( $slug, $main );
		$vs     = array();
		foreach ( $cat['comparisons'] as $c ) {
			foreach ( array( 'a', 'b' ) as $side ) {
				list( $cb, $ci ) = array_pad( explode( ':', $c[ $side ] ), 2, '' );
				if ( $cb === $slug && in_array( $ci, wp_list_pluck( $group['items'], 'id' ), true ) ) {
					$url  = ffnc_generated_page_url( 'compare:' . $c['slug'] );
					$pair = ffnc_comparison( $c['slug'] );
					if ( $url && $pair ) {
						$vs[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $pair['title'] ) . '</a></li>';
					}
				}
			}
		}
		if ( $others || $vs ) :
			?>
			<h2><?php esc_html_e( 'Compared with other restaurants', 'ffnc' ); ?></h2>
			<?php if ( $vs ) : ?>
				<ul class="ffnc-list"><?php echo wp_kses_post( implode( '', $vs ) ); ?></ul>
			<?php endif; ?>
			<?php if ( $others ) : ?>
				<p><?php echo esc_html( sprintf( /* translators: %s item kind, e.g. "burgers" */ __( 'Similar-calorie %s at other chains (same size where the chain has one):', 'ffnc' ), ffnc_type_plural( ffnc_item_types()[ $slug ][ $main['id'] ] ) ) ); ?></p>
				<ul class="ffnc-list">
					<?php foreach ( $others as $o ) : ?>
						<li><?php echo wp_kses_post( ffnc_item_link( $o['brand'], $o['item'], ffnc_brand_display_name( ffnc_get_brand( $o['brand'] ), $o['item']['name'] ) ) ); ?>: <?php echo esc_html( sprintf( /* translators: 1 cal 2 protein */ __( '%1$s calories, %2$sg protein', 'ffnc' ), ffnc_fmt_num( ffnc_num( $o['item'], 'calories' ) ), ffnc_fmt_num( ffnc_num( $o['item'], 'protein' ) ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		$qas = array();
		$qas[ sprintf( /* translators: %s item */ __( 'How many calories are in the %s?', 'ffnc' ), $single ) ] = sprintf(
			/* translators: 1 item 2 cal 3 protein 4 carbs 5 fat */
			ffnc_dataset_is_verified( $slug )
				? __( 'The %1$s has %2$s calories, with %3$sg of protein, %4$sg of carbs and %5$sg of fat, according to the restaurant’s official U.S. nutrition information.', 'ffnc' )
				: __( 'The %1$s has about %2$s calories, with %3$sg of protein, %4$sg of carbs and %5$sg of fat, in our reference values (not yet checked against the official source).', 'ffnc' ),
			$single,
			ffnc_fmt_num( $cal ),
			ffnc_fmt_num( ffnc_num( $main, 'protein' ) ),
			ffnc_fmt_num( ffnc_num( $main, 'carbs' ) ),
			ffnc_fmt_num( ffnc_num( $main, 'fat' ) )
		);
		if ( $multi ) {
			$sizes = array_map( function ( $i ) { return $i['size'] . ': ' . ffnc_fmt_num( ffnc_num( $i, 'calories' ) ); }, $group['items'] );
			$qas[ sprintf( /* translators: %s item */ __( 'How many calories are in each size of %s?', 'ffnc' ), $name ) ] = implode( '; ', $sizes ) . ' ' . __( 'calories.', 'ffnc' );
		}
		if ( $swaps ) {
			$qas[ sprintf( /* translators: %s brand */ __( 'What is a lighter choice at %s?', 'ffnc' ), $brand['name'] ) ] = $swap_r['keeps_protein']
				? sprintf(
					/* translators: 1 item 2 cal 3 saved */
					__( 'The %1$s has %2$s calories, %3$s fewer, and still keeps most of the protein.', 'ffnc' ),
					$swaps[0]['name'],
					ffnc_fmt_num( ffnc_num( $swaps[0], 'calories' ) ),
					ffnc_fmt_num( $cal - ffnc_num( $swaps[0], 'calories' ) )
				)
				: sprintf(
					/* translators: 1 item 2 cal 3 saved 4 swap protein 5 this item's protein */
					__( 'The %1$s has %2$s calories, %3$s fewer, but less protein (%4$sg vs %5$sg).', 'ffnc' ),
					$swaps[0]['name'],
					ffnc_fmt_num( ffnc_num( $swaps[0], 'calories' ) ),
					ffnc_fmt_num( $cal - ffnc_num( $swaps[0], 'calories' ) ),
					ffnc_fmt_num( ffnc_num( $swaps[0], 'protein' ) ),
					ffnc_fmt_num( ffnc_num( $main, 'protein' ) )
				);
		}
		echo ffnc_render_faq( $qas ); // phpcs:ignore

		$more = array();
		foreach ( ffnc_item_groups( $slug ) as $g ) {
			$url = $g['slug'] !== $group['slug'] ? ffnc_generated_page_url( 'item:' . $slug . ':' . $g['slug'] ) : '';
			if ( $url ) {
				$more[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $g['base'] ) . '</a>';
			}
		}
		if ( $more ) :
			?>
			<h2><?php echo esc_html( sprintf( /* translators: %s brand */ __( 'More %s calories', 'ffnc' ), $brand['name'] ) ); ?></h2>
			<p class="ffnc-item-more"><?php echo wp_kses_post( implode( ' · ', $more ) ); ?></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $calc ); ?>"><?php echo esc_html( sprintf( /* translators: %s calculator */ __( 'Open the full %s', 'ffnc' ), $brand['title'] ) ); ?></a></p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_item_page', 'ffnc_shortcode_item_page' );

/* -------------------------------------------------------------------------
 * [ff_meal_page key="mcdonalds:big-mac"]
 * ---------------------------------------------------------------------- */
function ffnc_resolve_meal( $key ) {
	list( $brand, $main ) = array_pad( explode( ':', (string) $key ), 2, '' );
	$cat = ffnc_page_catalog();
	foreach ( $cat['meals'] as $meal ) {
		if ( $meal['brand'] === $brand && $meal['main'] === $main && ffnc_get_brand_item( $brand, $main ) && ! empty( $cat['meal_sets'][ $brand ] ) ) {
			$sets = array();
			foreach ( $cat['meal_sets'][ $brand ] as $set ) {
				$items = array( ffnc_get_brand_item( $brand, $main ) );
				foreach ( $set['items'] as $id ) {
					$items[] = ffnc_get_brand_item( $brand, $id );
				}
				if ( ! in_array( null, $items, true ) ) {
					$sets[] = array( 'label' => $set['label'], 'items' => $items, 'total' => ffnc_sum_items( $items ) );
				}
			}
			return $sets ? $meal + array( 'sets' => $sets ) : null;
		}
	}
	return null;
}

function ffnc_shortcode_meal_page( $atts ) {
	$atts = shortcode_atts( array( 'key' => '' ), $atts, 'ff_meal_page' );
	$meal = ffnc_resolve_meal( $atts['key'] );
	if ( ! $meal ) {
		return ffnc_missing_page_notice( strtok( $atts['key'], ':' ) );
	}
	$slug  = $meal['brand'];
	$brand = ffnc_get_brand( $slug );
	$calc  = home_url( $brand['url'] );
	$def   = $meal['sets'][0];
	$main  = $def['items'][0];
	ob_start();
	?>
	<div class="ffnc-item-page" style="--ffnc-brand-color:<?php echo esc_attr( ffnc_get_brand_color( $brand ) ); ?>">
		<div class="ffnc-item-hero">
			<p class="ffnc-item-answer">
				<?php
				echo wp_kses_post( sprintf(
					/* translators: 1 "A medium Big Mac Meal" 2 calories 3 sides */
					__( '%1$s has <strong class="ffnc-item-big">%2$s calories</strong> with %3$s.', 'ffnc' ),
					esc_html( ffnc_meal_phrase( $meal ) ),
					esc_html( ffnc_fmt_num( $def['total']['calories'] ) ),
					esc_html( $def['label'] )
				) );
				?>
			</p>
			<?php echo ffnc_macro_chips( $def['total'] ); // phpcs:ignore ?>
			<p class="ffnc-item-actions">
				<a class="ffnc-btn ffnc-btn-solid" href="<?php echo esc_url( ffnc_order_link( $calc, wp_list_pluck( $def['items'], 'id' ) ) ); ?>"><?php esc_html_e( 'Open this meal in the calculator', 'ffnc' ); ?></a>
			</p>
			<?php echo ffnc_source_line( $slug ); // phpcs:ignore ?>
		</div>

		<h2><?php esc_html_e( 'What’s in the meal', 'ffnc' ); ?></h2>
		<div class="ffnc-table-scroll" tabindex="0">
		<table class="ffnc-nutrition-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Item', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Fat', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $def['items'] as $it ) : ?>
				<tr><th scope="row"><?php echo wp_kses_post( ffnc_item_link( $slug, $it ) ); ?></th><td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) ); ?></td><td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'protein' ) ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'carbs' ) ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'fat' ) ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'sodium' ) ) . 'mg' ); ?></td></tr>
			<?php endforeach; ?>
			<tr class="ffnc-total-row"><th scope="row"><?php esc_html_e( 'Meal total', 'ffnc' ); ?></th><td><?php echo esc_html( ffnc_fmt_num( $def['total']['calories'] ) ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $def['total']['protein'] ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $def['total']['carbs'] ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $def['total']['fat'] ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $def['total']['sodium'] ) . 'mg' ); ?></td></tr>
			</tbody>
		</table>
		</div>

		<h2><?php esc_html_e( 'Other ways to order it', 'ffnc' ); ?></h2>
		<div class="ffnc-table-scroll" tabindex="0">
		<table class="ffnc-nutrition-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'With', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $meal['sets'] as $s ) : ?>
				<tr><th scope="row"><a href="<?php echo esc_url( ffnc_order_link( $calc, wp_list_pluck( $s['items'], 'id' ) ) ); ?>"><?php echo esc_html( ucfirst( $s['label'] ) ); ?></a></th><td><?php echo esc_html( ffnc_fmt_num( $s['total']['calories'] ) ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $s['total']['protein'] ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $s['total']['carbs'] ) . 'g' ); ?></td><td><?php echo esc_html( ffnc_fmt_num( $s['total']['sodium'] ) . 'mg' ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="ffnc-goal-note"><?php echo esc_html( sprintf( /* translators: %s brand */ __( '%s does not publish one number for a meal, so we add up the official values of each part. Sauces are not included.', 'ffnc' ), $brand['name'] ) ); ?></p>

		<?php
		$water = null;
		foreach ( $meal['sets'] as $s ) {
			if ( 2 === count( $s['items'] ) ) {
				$water = $s;
			}
		}
		$qas = array();
		$qas[ sprintf( /* translators: %s meal */ __( 'How many calories are in a %s?', 'ffnc' ), $meal['name'] ) ] = sprintf(
			/* translators: 1 "A medium Big Mac Meal" 2 cal 3 sides 4 protein 5 sodium */
			__( '%1$s is %2$s calories with %3$s, with %4$sg of protein and %5$smg of sodium.', 'ffnc' ),
			ffnc_meal_phrase( $meal ),
			ffnc_fmt_num( $def['total']['calories'] ),
			$def['label'],
			ffnc_fmt_num( $def['total']['protein'] ),
			ffnc_fmt_num( $def['total']['sodium'] )
		);
		if ( $water ) {
			$qas[ sprintf( /* translators: %s meal */ __( 'How can I cut calories from a %s?', 'ffnc' ), $meal['name'] ) ] = sprintf(
				/* translators: 1 cal saved 2 total */
				__( 'The drink is the easiest change: water or a zero-calorie drink saves %1$s calories (%2$s total).', 'ffnc' ),
				ffnc_fmt_num( $def['total']['calories'] - $water['total']['calories'] ),
				ffnc_fmt_num( $water['total']['calories'] )
			) . ( ffnc_meal_copy( $slug )['cut_tip'] ? ' ' . ffnc_meal_copy( $slug )['cut_tip'] : '' );
		}
		echo ffnc_render_faq( $qas ); // phpcs:ignore
		$item_url = ffnc_item_page_url( $slug, $main['id'] );
		?>
		<p>
			<?php if ( $item_url ) : ?>
				<a href="<?php echo esc_url( $item_url ); ?>"><?php echo esc_html( sprintf( /* translators: %s item */ __( '%s on its own', 'ffnc' ), $main['name'] ) ); ?></a> ·
			<?php endif; ?>
			<a href="<?php echo esc_url( $calc ); ?>"><?php echo esc_html( $brand['title'] ); ?></a>
		</p>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_meal_page', 'ffnc_shortcode_meal_page' );

/* -------------------------------------------------------------------------
 * [ff_compare_page slug="big-mac-vs-whopper"]
 * ---------------------------------------------------------------------- */
function ffnc_comparison( $slug ) {
	foreach ( ffnc_page_catalog()['comparisons'] as $c ) {
		if ( $c['slug'] !== $slug ) {
			continue;
		}
		$out = array( 'slug' => $slug, 'title' => $c['title'], 'note' => isset( $c['note'] ) ? $c['note'] : '' );
		foreach ( array( 'a', 'b' ) as $side ) {
			list( $b, $id ) = array_pad( explode( ':', $c[ $side ] ), 2, '' );
			$brand          = ffnc_get_brand( $b );
			$item           = ffnc_get_brand_item( $b, $id );
			if ( ! $brand || empty( $brand['live'] ) || ! $item ) {
				return null;
			}
			$out[ $side ] = array(
				'brand' => $b,
				'bname' => $brand['name'],
				'item'  => $item,
				'label' => ffnc_brand_display_name( $brand, $item['name'] ),
			);
		}
		// Same chain on both sides: no need to repeat its name.
		if ( $out['a']['brand'] === $out['b']['brand'] ) {
			$out['a']['label'] = $out['a']['item']['name'];
			$out['b']['label'] = $out['b']['item']['name'];
		}
		return $out;
	}
	return null;
}

function ffnc_shortcode_compare_page( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '' ), $atts, 'ff_compare_page' );
	$p    = ffnc_comparison( $atts['slug'] );
	if ( ! $p ) {
		return ffnc_missing_page_notice();
	}
	$a    = $p['a'];
	$b    = $p['b'];
	$rows = array(
		'calories' => array( __( 'Calories', 'ffnc' ), '', 'low' ),
		'protein'  => array( __( 'Protein', 'ffnc' ), 'g', 'high' ),
		'carbs'    => array( __( 'Carbs', 'ffnc' ), 'g', 'low' ),
		'fat'      => array( __( 'Fat', 'ffnc' ), 'g', 'low' ),
		'sodium'   => array( __( 'Sodium', 'ffnc' ), 'mg', 'low' ),
	);
	$va  = function ( $k ) use ( $a ) { return ffnc_num( $a['item'], $k ); };
	$vb  = function ( $k ) use ( $b ) { return ffnc_num( $b['item'], $k ); };
	$dens = function ( $x ) { return round( (float) ffnc_num( $x['item'], 'protein' ) / max( 1, (float) ffnc_num( $x['item'], 'calories' ) ) * 100, 1 ); };
	$lighter = $va( 'calories' ) <= $vb( 'calories' ) ? $a : $b;
	$heavier = $lighter === $a ? $b : $a;
	$diff    = abs( $va( 'calories' ) - $vb( 'calories' ) );
	ob_start();
	?>
	<div class="ffnc-item-page ffnc-vs-page">
		<p class="ffnc-item-answer">
			<?php
			if ( $diff < 1 ) {
				echo wp_kses_post( sprintf( /* translators: 1 a 2 b 3 cal */ __( 'The %1$s and the %2$s both have <strong class="ffnc-item-big">%3$s calories</strong>.', 'ffnc' ), esc_html( $a['label'] ), esc_html( $b['label'] ), esc_html( ffnc_fmt_num( $va( 'calories' ) ) ) ) );
			} else {
				echo wp_kses_post( sprintf(
					/* translators: 1 lighter 2 diff 3 heavier */
					__( 'The %1$s has <strong class="ffnc-item-big">%2$s fewer calories</strong> than the %3$s.', 'ffnc' ),
					esc_html( $lighter['label'] ),
					esc_html( ffnc_fmt_num( $diff ) ),
					esc_html( $heavier['label'] )
				) );
			}
			?>
		</p>
		<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Side-by-side nutrition', 'ffnc' ); ?>">
		<table class="ffnc-nutrition-table ffnc-vs-table">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Nutrient', 'ffnc' ); ?></th>
				<th scope="col"><?php echo wp_kses_post( ffnc_item_link( $a['brand'], $a['item'], $a['label'] ) ); ?></th>
				<th scope="col"><?php echo wp_kses_post( ffnc_item_link( $b['brand'], $b['item'], $b['label'] ) ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $rows as $k => $r ) : $x = $va( $k ); $y = $vb( $k ); $win = ( null === $x || null === $y || $x == $y ) ? '' : ( ( 'low' === $r[2] ) === ( $x < $y ) ? 'a' : 'b' ); // phpcs:ignore ?>
				<tr>
					<th scope="row"><?php echo esc_html( $r[0] ); ?></th>
					<td class="<?php echo 'a' === $win ? 'ffnc-vs-win' : ''; ?>"><?php echo esc_html( ffnc_fmt_num( $x ) . ( null === $x ? '' : $r[1] ) ); ?></td>
					<td class="<?php echo 'b' === $win ? 'ffnc-vs-win' : ''; ?>"><?php echo esc_html( ffnc_fmt_num( $y ) . ( null === $y ? '' : $r[1] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Protein per 100 calories', 'ffnc' ); ?></th>
					<td class="<?php echo $dens( $a ) > $dens( $b ) ? 'ffnc-vs-win' : ''; ?>"><?php echo esc_html( ffnc_fmt_num( $dens( $a ) ) . 'g' ); ?></td>
					<td class="<?php echo $dens( $b ) > $dens( $a ) ? 'ffnc-vs-win' : ''; ?>"><?php echo esc_html( ffnc_fmt_num( $dens( $b ) ) . 'g' ); ?></td>
				</tr>
			</tbody>
		</table>
		</div>
		<p class="ffnc-goal-note"><?php esc_html_e( 'Highlighted: the better number in each row (fewer calories, carbs, fat and sodium; more protein).', 'ffnc' ); ?> <?php echo esc_html( $p['note'] ); ?></p>

		<h2><?php esc_html_e( 'Which one should you pick?', 'ffnc' ); ?></h2>
		<ul class="ffnc-list">
			<?php
			$more_p = $va( 'protein' ) >= $vb( 'protein' ) ? $a : $b;
			$less_s = ( null !== $va( 'sodium' ) && null !== $vb( 'sodium' ) ) ? ( $va( 'sodium' ) <= $vb( 'sodium' ) ? $a : $b ) : null;
			$best_d = $dens( $a ) >= $dens( $b ) ? $a : $b;
			?>
			<li><?php echo esc_html( sprintf( /* translators: 1 item 2 cal */ __( 'Fewer calories: the %1$s (%2$s).', 'ffnc' ), $lighter['label'], ffnc_fmt_num( ffnc_num( $lighter['item'], 'calories' ) ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: 1 item 2 g */ __( 'More protein: the %1$s (%2$sg).', 'ffnc' ), $more_p['label'], ffnc_fmt_num( ffnc_num( $more_p['item'], 'protein' ) ) ) ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: 1 item 2 g */ __( 'More protein for its calories: the %1$s (%2$sg per 100 calories).', 'ffnc' ), $best_d['label'], ffnc_fmt_num( $dens( $best_d ) ) ) ); ?></li>
			<?php if ( $less_s ) : ?>
				<li><?php echo esc_html( sprintf( /* translators: 1 item 2 mg */ __( 'Less sodium: the %1$s (%2$smg).', 'ffnc' ), $less_s['label'], ffnc_fmt_num( ffnc_num( $less_s['item'], 'sodium' ) ) ) ); ?></li>
			<?php endif; ?>
		</ul>

		<?php
		$qas = array(
			sprintf( /* translators: 1 a 2 b */ __( 'Which has more calories, the %1$s or the %2$s?', 'ffnc' ), $a['label'], $b['label'] ) => ( $diff < 1
				? sprintf( /* translators: 1 cal */ __( 'They are the same: %s calories each.', 'ffnc' ), ffnc_fmt_num( $va( 'calories' ) ) )
				: sprintf( /* translators: 1 heavier 2 cal 3 lighter 4 cal */ __( 'The %1$s, with %2$s calories against %4$s for the %3$s.', 'ffnc' ), $heavier['label'], ffnc_fmt_num( ffnc_num( $heavier['item'], 'calories' ) ), $lighter['label'], ffnc_fmt_num( ffnc_num( $lighter['item'], 'calories' ) ) ) ),
			sprintf( /* translators: 1 a 2 b */ __( 'Which has more protein, the %1$s or the %2$s?', 'ffnc' ), $a['label'], $b['label'] ) => sprintf( /* translators: 1 a 2 g 3 b 4 g */ __( 'The %1$s has %2$sg of protein and the %3$s has %4$sg.', 'ffnc' ), $a['label'], ffnc_fmt_num( $va( 'protein' ) ), $b['label'], ffnc_fmt_num( $vb( 'protein' ) ) ),
		);
		echo ffnc_render_faq( $qas ); // phpcs:ignore
		?>
		<p class="ffnc-item-source"><?php
		echo esc_html( ffnc_dataset_is_verified( $a['brand'] ) && ffnc_dataset_is_verified( $b['brand'] )
			? __( 'Both columns use each restaurant’s official U.S. nutrition values for the standard item, as in our calculators.', 'ffnc' )
			: __( 'Both columns use the same values as our calculators; at least one restaurant’s data is not yet checked against its official source, so treat it as an estimate.', 'ffnc' ) );
		?></p>
		<p>
			<?php foreach ( array( $a, $b ) as $i => $x ) : $bb = ffnc_get_brand( $x['brand'] ); ?>
				<?php echo $i ? ' · ' : ''; ?><a href="<?php echo esc_url( ffnc_order_link( home_url( $bb['url'] ), $x['item']['id'] ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s item */ __( 'Build an order with the %s', 'ffnc' ), $x['label'] ) ); ?></a>
			<?php endforeach; ?>
		</p>
		<?php
		$others = array();
		foreach ( ffnc_page_catalog()['comparisons'] as $c ) {
			$url = $c['slug'] !== $p['slug'] ? ffnc_generated_page_url( 'compare:' . $c['slug'] ) : '';
			$o   = $url ? ffnc_comparison( $c['slug'] ) : null;
			if ( $o ) {
				$others[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $o['title'] ) . '</a>';
			}
		}
		if ( $others ) :
			?>
			<h2><?php esc_html_e( 'More comparisons', 'ffnc' ); ?></h2>
			<p class="ffnc-item-more"><?php echo wp_kses_post( implode( ' · ', $others ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'ff_compare_page', 'ffnc_shortcode_compare_page' );

/* -------------------------------------------------------------------------
 * [ff_vegetarian_list]
 * ---------------------------------------------------------------------- */
add_shortcode( 'ff_vegetarian_list', function () {
	$cat   = ffnc_page_catalog();
	$types = ffnc_item_types();
	$mains = array();
	$other = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		foreach ( isset( $cat['vegetarian'][ $slug ] ) ? $cat['vegetarian'][ $slug ] : array() as $id ) {
			$item = ffnc_get_brand_item( $slug, $id );
			if ( ! $item ) {
				continue;
			}
			$row  = array(
				'brand'      => $slug,
				'brand_name' => $brand['name'],
				'brand_url'  => home_url( $brand['url'] ),
				'item'       => $item,
			);
			$type = isset( $types[ $slug ][ $id ] ) ? $types[ $slug ][ $id ] : 'other';
			$main = in_array( $type, ffnc_main_types(), true ) || in_array( $id, array( 'sofritas', 'baked-potato-plain', 'baked-potato-sour-cream-chive', 'broccoli-cheddar-bowl', 'greek-salad' ), true );
			// A bread bowl is a container, and breakfast sides are not mains; two breakfast items are.
			if ( ( $main && 'breakfast' !== $type && 'bread-bowl' !== $id ) || in_array( $id, array( 'egg-cheese-wake-up-wrap', 'avocado-toast' ), true ) ) {
				$mains[] = $row;
			} else {
				$other[] = $row;
			}
		}
	}
	$by_protein = function ( $x, $y ) {
		return (float) ffnc_num( $y['item'], 'protein' ) <=> (float) ffnc_num( $x['item'], 'protein' );
	};
	usort( $mains, $by_protein );
	usort( $other, function ( $x, $y ) {
		return strcmp( $x['brand_name'], $y['brand_name'] ) ?: ( ffnc_num( $x['item'], 'calories' ) <=> ffnc_num( $y['item'], 'calories' ) );
	} );
	$out  = '<h2>' . esc_html__( 'Vegetarian main items, most protein first', 'ffnc' ) . '</h2>';
	$out .= ffnc_render_item_rows_table( $mains, __( 'Vegetarian main items', 'ffnc' ) );
	$out .= '<h2>' . esc_html__( 'Vegetarian sides, snacks and desserts', 'ffnc' ) . '</h2>';
	$out .= ffnc_render_item_rows_table( $other, __( 'Vegetarian sides and desserts', 'ffnc' ) );
	$notes = '';
	foreach ( $cat['vegetarian_notes'] as $slug => $note ) {
		$b = ffnc_get_brand( $slug );
		if ( $b ) {
			$notes .= '<li><strong>' . esc_html( $b['name'] ) . ':</strong> ' . esc_html( $note ) . '</li>';
		}
	}
	if ( $notes ) {
		$out .= '<h2>' . esc_html__( 'Notes by restaurant', 'ffnc' ) . '</h2><ul class="ffnc-list">' . $notes . '</ul>';
	}
	return $out;
} );

/* -------------------------------------------------------------------------
 * [ff_item_index] for the /calories/ hub
 * ---------------------------------------------------------------------- */
add_shortcode( 'ff_item_index', function () {
	$out = '';
	$nav = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$links = array();
		foreach ( ffnc_item_groups( $slug ) as $g ) {
			$url = ffnc_generated_page_url( 'item:' . $slug . ':' . $g['slug'] );
			if ( $url ) {
				$main    = ffnc_group_main_item( $g );
				$links[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $g['base'] ) . '</a> <span>' . esc_html( sprintf( /* translators: %s calories */ __( '%s cal', 'ffnc' ), ffnc_fmt_num( ffnc_num( $main, 'calories' ) ) ) . ( count( $g['items'] ) > 1 ? ' (' . $main['size'] . ')' : '' ) ) . '</span></li>';
			}
		}
		foreach ( ffnc_page_catalog()['meals'] as $meal ) {
			$url = $meal['brand'] === $slug ? ffnc_generated_page_url( 'meal:' . $slug . ':' . $meal['main'] ) : '';
			if ( $url ) {
				$links[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $meal['name'] ) . '</a></li>';
			}
		}
		if ( $links ) {
			$nav[] = '<a href="#calories-' . esc_attr( $slug ) . '">' . esc_html( $brand['name'] ) . '</a>';
			$out  .= '<section class="ffnc-item-index" id="calories-' . esc_attr( $slug ) . '"><h2>' . esc_html( sprintf( /* translators: %s brand */ __( '%s calories', 'ffnc' ), $brand['name'] ) ) . '</h2><ul class="ffnc-cheat-list ffnc-item-index-list">' . implode( '', $links ) . '</ul><p><a href="' . esc_url( home_url( $brand['url'] ) ) . '">' . esc_html( $brand['title'] ) . '</a></p></section>';
		}
	}
	$vs = array();
	foreach ( ffnc_page_catalog()['comparisons'] as $c ) {
		$url = ffnc_generated_page_url( 'compare:' . $c['slug'] );
		$p   = $url ? ffnc_comparison( $c['slug'] ) : null;
		if ( $p ) {
			$vs[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $p['title'] ) . '</a></li>';
		}
	}
	if ( $vs ) {
		$nav[] = '<a href="#comparisons">' . esc_html__( 'Comparisons', 'ffnc' ) . '</a>';
		$out  .= '<section class="ffnc-item-index" id="comparisons"><h2>' . esc_html__( 'Head-to-head comparisons', 'ffnc' ) . '</h2><ul class="ffnc-list">' . implode( '', $vs ) . '</ul></section>';
	}
	if ( ! $out ) {
		return '<p>' . esc_html__( 'Item pages are being set up. In the meantime, every item is in our calculators.', 'ffnc' ) . '</p>';
	}
	return '<nav class="ffnc-cheat-nav" aria-label="' . esc_attr__( 'Restaurants', 'ffnc' ) . '">' . implode( ' ', $nav ) . '</nav>' . $out;
} );

/* -------------------------------------------------------------------------
 * SEO: live titles and descriptions for generated pages
 * ---------------------------------------------------------------------- */

/**
 * @return array{title:string,desc:string}|null
 */
function ffnc_generated_seo( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}
	$key = ffnc_generated_key( $post_id );
	$out = null;
	list( $kind, $rest ) = array_pad( explode( ':', $key, 2 ), 2, '' );
	if ( 'item' === $kind && ( $r = ffnc_resolve_item_key( $rest ) ) ) { // phpcs:ignore
		$g    = $r['group'];
		$main = ffnc_group_main_item( $g );
		if ( count( $g['items'] ) > 1 ) {
			$sizes = wp_list_pluck( $g['items'], 'size' );
			$out   = array(
				'title' => sprintf( /* translators: 1 item 2 sizes */ __( '%1$s Calories: %2$s Nutrition Facts', 'ffnc' ), $g['title'], implode( ', ', $sizes ) ),
				'desc'  => sprintf(
					/* translators: 1 item 2 list */
					__( '%1$s calories by size: %2$s. Protein, carbs, fat and sodium for each size from official U.S. data, plus lighter swaps.', 'ffnc' ),
					$g['title'],
					implode( ', ', array_map( function ( $i ) { return $i['size'] . ' ' . ffnc_fmt_num( ffnc_num( $i, 'calories' ) ); }, $g['items'] ) )
				),
			);
		} else {
			$out = array(
				'title' => sprintf( /* translators: 1 item 2 cal 3 protein */ __( '%1$s Calories: %2$s Cal, %3$sg Protein & Nutrition', 'ffnc' ), $g['title'], ffnc_fmt_num( ffnc_num( $main, 'calories' ) ), ffnc_fmt_num( ffnc_num( $main, 'protein' ) ) ),
				'desc'  => sprintf(
					/* translators: 1 item 2 cal 3 p 4 c 5 f 6 s */
					__( '%1$s: %2$s calories, %3$sg protein, %4$sg carbs, %5$sg fat and %6$smg sodium (official U.S. values). Lighter swaps, meal totals and how it compares.', 'ffnc' ),
					$g['title'],
					ffnc_fmt_num( ffnc_num( $main, 'calories' ) ),
					ffnc_fmt_num( ffnc_num( $main, 'protein' ) ),
					ffnc_fmt_num( ffnc_num( $main, 'carbs' ) ),
					ffnc_fmt_num( ffnc_num( $main, 'fat' ) ),
					ffnc_fmt_num( ffnc_num( $main, 'sodium' ) )
				),
			);
		}
	} elseif ( 'meal' === $kind && ( $m = ffnc_resolve_meal( $rest ) ) ) { // phpcs:ignore
		$def = $m['sets'][0];
		$out = array(
			'title' => sprintf( /* translators: 1 meal 2 cal 3 size and sides */ __( '%1$s Calories: %2$s Cal (%3$s)', 'ffnc' ), ffnc_brand_display_name( ffnc_get_brand( $m['brand'] ), $m['name'] ), ffnc_fmt_num( $def['total']['calories'] ), ffnc_meal_copy( $m['brand'] )['title_tag'] ),
			'desc'  => sprintf(
				/* translators: 1 "A medium Big Mac Meal" 2 cal 3 sides 4 protein */
				__( '%1$s has %2$s calories with %3$s and %4$sg protein. See every part, other sides and drinks, and how to cut calories.', 'ffnc' ),
				ffnc_meal_phrase( $m ),
				ffnc_fmt_num( $def['total']['calories'] ),
				$def['label'],
				ffnc_fmt_num( $def['total']['protein'] )
			),
		);
	} elseif ( 'compare' === $kind && ( $p = ffnc_comparison( $rest ) ) ) { // phpcs:ignore
		$out = array(
			'title' => sprintf( /* translators: %s comparison */ __( '%s Calories, Protein & Nutrition Compared', 'ffnc' ), $p['title'] ),
			'desc'  => sprintf(
				/* translators: 1 a 2 cal 3 p 4 b 5 cal 6 p */
				__( '%1$s: %2$s calories, %3$sg protein. %4$s: %5$s calories, %6$sg protein. Side-by-side carbs, fat and sodium from official U.S. data, and which to pick.', 'ffnc' ),
				$p['a']['label'],
				ffnc_fmt_num( ffnc_num( $p['a']['item'], 'calories' ) ),
				ffnc_fmt_num( ffnc_num( $p['a']['item'], 'protein' ) ),
				$p['b']['label'],
				ffnc_fmt_num( ffnc_num( $p['b']['item'], 'calories' ) ),
				ffnc_fmt_num( ffnc_num( $p['b']['item'], 'protein' ) )
			),
		);
	}
	$cache[ $post_id ] = $out;
	return $out;
}

/**
 * Generated pages answer the theme's own SEO fields (_ffnc_seo_title /
 * _ffnc_meta_description) with live values, unless an editor typed their own.
 */
add_filter( 'get_post_metadata', function ( $value, $post_id, $meta_key, $single ) {
	if ( '_ffnc_seo_title' !== $meta_key && '_ffnc_meta_description' !== $meta_key ) {
		return $value;
	}
	static $busy = false;
	if ( $busy ) {
		return $value;
	}
	$busy  = true;
	$own   = get_post_meta( $post_id, $meta_key, true );
	$seo   = $own ? null : ( ffnc_generated_key( $post_id ) ? ffnc_generated_seo( $post_id ) : null );
	$busy  = false;
	if ( ! $seo ) {
		return $value;
	}
	$v = '_ffnc_seo_title' === $meta_key ? $seo['title'] : $seo['desc'];
	return array( $v ); // get_metadata() takes [0] itself when $single.
}, 10, 4 );

/** Same values for Rank Math, when it handles the head instead of the theme. */
add_filter( 'rank_math/frontend/title', function ( $title ) {
	if ( is_singular( 'page' ) && ffnc_generated_key( get_queried_object_id() ) ) {
		$seo = ffnc_generated_seo( get_queried_object_id() );
		if ( $seo ) {
			return $seo['title'] . ' | ' . get_bloginfo( 'name' );
		}
	}
	return $title;
} );
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	if ( is_singular( 'page' ) && ffnc_generated_key( get_queried_object_id() ) ) {
		$seo = ffnc_generated_seo( get_queried_object_id() );
		if ( $seo ) {
			return $seo['desc'];
		}
	}
	return $desc;
} );

/** A generated page whose item left the menu: keep it out of search results. */
function ffnc_generated_page_is_orphan( $post_id ) {
	return '' !== ffnc_generated_key( $post_id ) && null === ffnc_generated_seo( $post_id );
}

/** Generated pages kept out of search: orphans and thin item pages. */
function ffnc_generated_page_is_noindex( $post_id ) {
	return ffnc_generated_page_is_orphan( $post_id ) || ffnc_generated_key_is_thin( ffnc_generated_key( $post_id ) );
}
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_singular( 'page' ) && ffnc_generated_page_is_noindex( get_queried_object_id() ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['max-image-preview'] );
	}
	return $robots;
}, 30 );
/** Rank Math prints its own robots tag. */
add_filter( 'rank_math/frontend/robots', function ( $robots ) {
	if ( is_singular( 'page' ) && ffnc_generated_page_is_noindex( get_queried_object_id() ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
	}
	return $robots;
}, 30 );

/** Keep the same pages out of Rank Math's and core's sitemaps. */
add_filter( 'rank_math/sitemap/entry', function ( $url, $type, $object ) {
	if ( 'post' === $type && $object && isset( $object->ID ) && 'page' === get_post_type( $object ) && ffnc_generated_page_is_noindex( $object->ID ) ) {
		return false;
	}
	return $url;
}, 10, 3 );
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
	if ( 'page' === $post_type ) {
		$skip = array();
		foreach ( ffnc_generated_page_ids() as $key => $id ) {
			if ( ffnc_generated_key_is_thin( $key ) ) {
				$skip[] = (int) $id;
			}
		}
		$args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $skip );
	}
	return $args;
}, 10, 2 );

/** Rebuild Rank Math's cached sitemap once after a theme update changes what it lists. */
add_action( 'init', function () {
	if ( get_option( 'ffnc_sitemap_version' ) !== FFNC_VERSION ) {
		update_option( 'ffnc_sitemap_version', FFNC_VERSION, false );
		do_action( 'rank_math/sitemap/invalidate_cache' );
	}
}, 99 );

/* -------------------------------------------------------------------------
 * Links from calculator pages
 * ---------------------------------------------------------------------- */
function ffnc_render_brand_item_links( $slug ) {
	$brand = ffnc_get_brand( $slug );
	$links = array();
	foreach ( ffnc_page_catalog()['meals'] as $meal ) {
		$url = $meal['brand'] === $slug ? ffnc_generated_page_url( 'meal:' . $slug . ':' . $meal['main'] ) : '';
		if ( $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $meal['name'] ) . '</a>';
		}
	}
	foreach ( ffnc_item_groups( $slug ) as $g ) {
		$url = ffnc_generated_page_url( 'item:' . $slug . ':' . $g['slug'] );
		if ( $url ) {
			$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $g['base'] ) . '</a>';
		}
	}
	$vs = array();
	foreach ( ffnc_page_catalog()['comparisons'] as $c ) {
		if ( 0 !== strpos( $c['a'], $slug . ':' ) && 0 !== strpos( $c['b'], $slug . ':' ) ) {
			continue;
		}
		$url = ffnc_generated_page_url( 'compare:' . $c['slug'] );
		$p   = $url ? ffnc_comparison( $c['slug'] ) : null;
		if ( $p ) {
			$vs[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $p['title'] ) . '</a>';
		}
	}
	if ( ! $links && ! $vs ) {
		return '';
	}
	$out = '<aside class="ffnc-more-tools ffnc-item-links">';
	if ( $links ) {
		$out .= '<h2>' . esc_html( sprintf( /* translators: %s brand */ __( 'Calories in popular %s items', 'ffnc' ), $brand['name'] ) ) . '</h2><p class="ffnc-item-more">' . implode( ' · ', $links ) . '</p>';
	}
	if ( $vs ) {
		$out .= '<h3>' . esc_html__( 'Head-to-head', 'ffnc' ) . '</h3><p class="ffnc-item-more">' . implode( ' · ', $vs ) . '</p>';
	}
	return $out . '</aside>';
}

/* -------------------------------------------------------------------------
 * /compare/ hub: example result and the list of head-to-head pages
 * ---------------------------------------------------------------------- */

/** Links to every published comparison page, as <li> items. */
function ffnc_comparison_links() {
	$out = array();
	foreach ( ffnc_page_catalog()['comparisons'] as $c ) {
		$url = ffnc_generated_page_url( 'compare:' . $c['slug'] );
		$p   = $url ? ffnc_comparison( $c['slug'] ) : null;
		if ( $p ) {
			$out[] = '<li><a href="' . esc_url( $url ) . '">' . esc_html( $p['title'] ) . '</a>: ' . esc_html( sprintf(
				/* translators: 1 a cal 2 b cal */
				__( '%1$s vs %2$s calories', 'ffnc' ),
				ffnc_fmt_num( ffnc_num( $p['a']['item'], 'calories' ) ),
				ffnc_fmt_num( ffnc_num( $p['b']['item'], 'calories' ) )
			) ) . '</li>';
		}
	}
	return $out;
}

/**
 * A server-rendered example comparison shown in the compare tool's result
 * area until the visitor picks their own items (and for visitors without JS).
 */
function ffnc_compare_example( $slug = 'big-mac-vs-whopper' ) {
	$p = ffnc_comparison( $slug );
	if ( ! $p ) {
		return '';
	}
	$rows = array(
		'calories' => array( __( 'Calories', 'ffnc' ), '', 'low' ),
		'protein'  => array( __( 'Protein', 'ffnc' ), 'g', 'high' ),
		'carbs'    => array( __( 'Carbs', 'ffnc' ), 'g', 'low' ),
		'fat'      => array( __( 'Fat', 'ffnc' ), 'g', 'low' ),
		'sodium'   => array( __( 'Sodium', 'ffnc' ), 'mg', 'low' ),
	);
	$body = '';
	foreach ( $rows as $k => $r ) {
		$x     = ffnc_num( $p['a']['item'], $k );
		$y     = ffnc_num( $p['b']['item'], $k );
		$win   = ( null === $x || null === $y || $x == $y ) ? '' : ( ( 'low' === $r[2] ) === ( $x < $y ) ? 'a' : 'b' ); // phpcs:ignore
		$body .= '<tr><th scope="row">' . esc_html( $r[0] ) . '</th>' .
			'<td' . ( 'a' === $win ? ' class="ffnc-cmp-best"' : '' ) . '>' . esc_html( ffnc_fmt_num( $x ) . ( null === $x ? '' : $r[1] ) ) . '</td>' .
			'<td' . ( 'b' === $win ? ' class="ffnc-cmp-best"' : '' ) . '>' . esc_html( ffnc_fmt_num( $y ) . ( null === $y ? '' : $r[1] ) ) . '</td></tr>';
	}
	$head = '';
	foreach ( array( $p['a'], $p['b'] ) as $side ) {
		$head .= '<th scope="col">' . wp_kses_post( ffnc_item_link( $side['brand'], $side['item'], $side['label'] ) ) . '</th>';
	}
	$diff = abs( (float) ffnc_num( $p['a']['item'], 'calories' ) - (float) ffnc_num( $p['b']['item'], 'calories' ) );
	$url  = ffnc_generated_page_url( 'compare:' . $slug );
	return '<p class="ffnc-compare-summary">' . sprintf(
		/* translators: 1 comparison 2 calories */
		esc_html__( 'Example, %1$s: the calorie difference is %2$s.', 'ffnc' ),
		$url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $p['title'] ) . '</a>' : esc_html( $p['title'] ),
		'<strong>' . esc_html( sprintf( /* translators: %s calories */ __( '%s calories', 'ffnc' ), ffnc_fmt_num( $diff ) ) ) . '</strong>'
	) . '</p>' .
		'<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr__( 'Example comparison table (scrollable)', 'ffnc' ) . '"><table class="ffnc-nutrition-table ffnc-compare-table"><thead><tr><th scope="col">' . esc_html__( 'Per serving', 'ffnc' ) . '</th>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>' .
		'<p class="ffnc-compare-empty">' . esc_html__( 'Pick your own items above to replace this example.', 'ffnc' ) . '</p>';
}
