<?php
/**
 * Cross-restaurant tools and data pages.
 *
 *   [ff_tool name="order-ideas|day-planner|same-craving" mode="" brand=""]
 *        Interactive tools (assets/js/tools.js) with every live dataset inlined.
 *   [ff_goal_list goal="high-protein|under-500|low-carb|low-sodium|small-portion"]
 *        Server-rendered ranked list across all chains (indexable).
 *   [ff_cheat_sheet brand="mcdonalds"] / [ff_cheat_sheets]
 *        Printable "best picks" sheet for one / every chain.
 *   [ff_menu_changes]      Every dataset changelog entry, newest first.
 *   [ff_nutrition_report]  Stats across all chains + CSV download link.
 *   [ff_tools_hub]         Cards linking every tool page.
 *
 * Plus: /?ffnc_dataset=csv (all items as CSV), /?ffnc_embed=<brand> (an
 * iframe-able calculator), and the tool pages themselves, created once as
 * published pages the first time an editor opens wp-admin after a deploy.
 *
 * Item "types" (burger, fries, drink...) come from
 * assets/generated/item-types.json, built by scripts/build-item-types.js with
 * the same rules the JS uses.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Types that count as a meal's main item in goal lists and cheat sheets. */
function ffnc_main_types() {
	return array( 'burger', 'chicken-sandwich', 'nuggets', 'pizza', 'sub', 'mexican', 'chicken-piece', 'wings', 'breakfast', 'salad', 'soup', 'entree', 'other' );
}

function ffnc_item_types() {
	static $types = null;
	if ( null === $types ) {
		$path  = FFNC_DIR . '/assets/generated/item-types.json';
		$types = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : array(); // phpcs:ignore
		$types = is_array( $types ) ? $types : array();
	}
	return $types;
}

/**
 * Every item of every live brand, flattened, with brand info and type.
 *
 * @return array[]
 */
function ffnc_all_items() {
	static $all = null;
	if ( null !== $all ) {
		return $all;
	}
	$all   = array();
	$types = ffnc_item_types();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$data = ffnc_get_brand_data( $slug );
		if ( ! $data ) {
			continue;
		}
		foreach ( $data['categories'] as $cat ) {
			foreach ( $cat['items'] as $item ) {
				$all[] = array(
					'brand'     => $slug,
					'brand_name' => $brand['name'],
					'brand_url' => home_url( $brand['url'] ),
					'category'  => $cat['name'],
					'type'      => isset( $types[ $slug ][ $item['id'] ] ) ? $types[ $slug ][ $item['id'] ] : 'other',
					'item'      => $item,
					'reviewed'  => isset( $data['meta']['last_reviewed'] ) ? $data['meta']['last_reviewed'] : '',
				);
			}
		}
	}
	return $all;
}

/** Link that opens a brand calculator with these item ids in the order. */
function ffnc_order_link( $brand_url, $ids ) {
	return add_query_arg( 'o', rawurlencode( implode( ',', (array) $ids ) ), $brand_url );
}

function ffnc_num( $item, $k ) {
	return isset( $item[ $k ] ) && is_numeric( $item[ $k ] ) ? (float) $item[ $k ] : null;
}

/** Optional nutrients some chains publish (saturated fat, sugar, fiber), in grams. */
function ffnc_extra_nutrients() {
	return array(
		'sat_fat' => __( 'Sat. fat', 'ffnc' ),
		'sugar'   => __( 'Sugar', 'ffnc' ),
		'fiber'   => __( 'Fiber', 'ffnc' ),
	);
}

/** Does any of these items publish saturated fat, sugar or fiber? */
function ffnc_items_have_extras( $items ) {
	foreach ( $items as $it ) {
		foreach ( array_keys( ffnc_extra_nutrients() ) as $k ) {
			if ( null !== ffnc_num( $it, $k ) ) {
				return true;
			}
		}
	}
	return false;
}

/** "12g", or an em dash when the value is not published. */
function ffnc_fmt_g( $v ) {
	return null === $v ? '—' : ffnc_fmt_num( $v ) . 'g';
}

/** Net carbs (total carbs minus fiber), or null when either is not published. */
function ffnc_net_carbs( $item ) {
	$c = ffnc_num( $item, 'carbs' );
	$f = ffnc_num( $item, 'fiber' );
	return ( null === $c || null === $f ) ? null : max( 0, $c - $f );
}

/* -------------------------------------------------------------------------
 * [ff_tool]
 * ---------------------------------------------------------------------- */
function ffnc_tools_payload() {
	$brands = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$data = ffnc_get_brand_data( $slug );
		if ( ! $data ) {
			continue;
		}
		$cats = array();
		foreach ( $data['categories'] as $cat ) {
			$items = array();
			foreach ( $cat['items'] as $item ) {
				$items[] = array(
					'id'       => $item['id'],
					'name'     => $item['name'],
					'calories' => ffnc_num( $item, 'calories' ),
					'protein'  => ffnc_num( $item, 'protein' ),
					'carbs'    => ffnc_num( $item, 'carbs' ),
					'fat'      => ffnc_num( $item, 'fat' ),
					'sodium'   => ffnc_num( $item, 'sodium' ),
				);
			}
			$cats[] = array( 'id' => $cat['id'], 'name' => $cat['name'], 'items' => $items );
		}
		$brands[] = array(
			'slug'       => $slug,
			'name'       => $brand['name'],
			'url'        => home_url( $brand['url'] ),
			'categories' => $cats,
		);
	}
	$guide = '';
	$q     = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 1, 's' => 'How Many Calories Should You Eat', 'fields' => 'ids' ) );
	if ( $q ) {
		$guide = get_permalink( $q[0] );
	}
	return array( 'brands' => $brands, 'calorieGuideUrl' => $guide );
}

function ffnc_shortcode_tool( $atts ) {
	$atts = shortcode_atts( array( 'name' => '', 'mode' => '', 'brand' => '' ), $atts, 'ff_tool' );
	if ( ! in_array( $atts['name'], array( 'order-ideas', 'day-planner', 'same-craving' ), true ) ) {
		return '';
	}
	return '<div class="ffnc-tool" data-tool="' . esc_attr( $atts['name'] ) . '" data-mode="' . esc_attr( $atts['mode'] ) . '" data-brand="' . esc_attr( sanitize_title( $atts['brand'] ) ) . '">' .
		'<script type="application/json" class="ffnc-tool-data">' . wp_json_encode( ffnc_tools_payload(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' .
		ffnc_tool_example( $atts['name'], $atts['mode'] ) .
		'<noscript><p>' . esc_html__( 'Change the inputs with JavaScript turned on; the example above uses the tool’s default settings.', 'ffnc' ) . '</p></noscript></div>';
}
add_shortcode( 'ff_tool', 'ffnc_shortcode_tool' );

/* -------------------------------------------------------------------------
 * Example results printed inside each tool's mount, so the page shows a real
 * answer before JavaScript runs (and to crawlers). tools.js replaces the
 * mount's contents when it boots.
 * ---------------------------------------------------------------------- */
function ffnc_tool_pills( $t ) {
	return '<span class="ffnc-pill ffnc-pill-cal">' . esc_html( ffnc_fmt_num( $t['calories'] ) ) . ' cal</span>' .
		'<span class="ffnc-pill"><abbr title="Protein">P</abbr> ' . esc_html( ffnc_fmt_num( $t['protein'] ) ) . 'g</span>' .
		'<span class="ffnc-pill"><abbr title="Carbohydrates">C</abbr> ' . esc_html( ffnc_fmt_num( $t['carbs'] ) ) . 'g</span>' .
		'<span class="ffnc-pill"><abbr title="Fat">F</abbr> ' . esc_html( ffnc_fmt_num( $t['fat'] ) ) . 'g</span>' .
		'<span class="ffnc-pill">' . esc_html( ffnc_fmt_num( $t['sodium'] ) ) . 'mg sodium</span>';
}

function ffnc_tool_example( $name, $mode = '' ) {
	$fn  = 'ffnc_tool_example_' . str_replace( '-', '_', $name );
	$out = function_exists( $fn ) ? $fn( $mode ) : '';
	return '<div class="ffnc-tool-example">' . ( $out ? $out : '<p class="ffnc-calc-loading">' . esc_html__( 'Loading…', 'ffnc' ) . '</p>' ) . '</div>';
}

/** Order Ideas at the default inputs, from assets/generated/tool-examples.json. */
function ffnc_tool_example_order_ideas( $mode ) {
	$path = FFNC_DIR . '/assets/generated/tool-examples.json';
	$all  = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore
	$key  = 'small-portion' === $mode ? 'small-portion' : 'default';
	if ( empty( $all[ $key ]['brands'] ) ) {
		return '';
	}
	$ex   = $all[ $key ];
	$list = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		if ( isset( $ex['brands'][ $slug ] ) ) {
			$list[] = $ex['brands'][ $slug ] + array( 'slug' => $slug, 'brand' => $brand );
		}
	}
	$small = 'small-portion' === $key;
	usort( $list, function ( $x, $y ) use ( $small ) {
		return $small
			? ( $y['totals']['protein'] / max( 1, $y['totals']['calories'] ) ) <=> ( $x['totals']['protein'] / max( 1, $x['totals']['calories'] ) )
			: $y['totals']['protein'] <=> $x['totals']['protein'];
	} );
	$out = '<p class="ffnc-tool-note">' . esc_html( sprintf(
		/* translators: 1 calories 2 protein */
		__( 'Example: orders of up to %1$s calories with at least %2$sg of protein.', 'ffnc' ),
		ffnc_fmt_num( $ex['inputs']['calories'] ),
		ffnc_fmt_num( $ex['inputs']['protein'] )
	) ) . '</p><div class="ffnc-tool-results">';
	foreach ( array_slice( $list, 0, 6 ) as $i => $r ) {
		$names = array();
		$ids   = array();
		foreach ( $r['lines'] as $l ) {
			$it = ffnc_get_brand_item( $r['slug'], $l['id'] );
			if ( ! $it ) {
				continue 2;
			}
			$names[] = ( $l['qty'] > 1 ? $l['qty'] . ' × ' : '' ) . $it['name'];
			$ids     = array_merge( $ids, array_fill( 0, max( 1, (int) $l['qty'] ), $l['id'] ) );
		}
		$out .= '<div class="ffnc-item-card ffnc-idea"><p class="ffnc-idea-rank">' . esc_html( sprintf( 'Option %d · %s', $i + 1, $r['brand']['name'] ) ) . '</p>' .
			'<p class="ffnc-item-name">' . esc_html( implode( ' + ', $names ) ) . '</p>' .
			'<div class="ffnc-item-pills">' . ffnc_tool_pills( $r['totals'] ) . '</div>' .
			'<a class="ffnc-idea-open" href="' . esc_url( ffnc_order_link( home_url( $r['brand']['url'] ), $ids ) ) . '">' . esc_html( sprintf( /* translators: %s brand */ __( 'Open in the %s calculator', 'ffnc' ), $r['brand']['name'] ) ) . '</a></div>';
	}
	return $out . '</div>';
}

/** Same Craving for a Big Mac: the closest burger at each other chain. */
function ffnc_tool_example_same_craving( $mode ) {
	$src = ffnc_get_brand_item( 'mcdonalds', 'big-mac' );
	if ( ! $src || ! function_exists( 'ffnc_item_at_other_chains' ) ) {
		return '';
	}
	$rows = '';
	foreach ( ffnc_item_at_other_chains( 'mcdonalds', $src, 20 ) as $o ) {
		$b     = ffnc_get_brand( $o['brand'] );
		$rows .= '<tr><th scope="row">' . esc_html( $b['name'] ) . '</th><td>' . wp_kses_post( ffnc_item_link( $o['brand'], $o['item'] ) ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $o['item'], 'calories' ) ) ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $o['item'], 'protein' ) ) ) . 'g</td></tr>';
	}
	if ( ! $rows ) {
		return '';
	}
	return '<p class="ffnc-tool-note">' . esc_html( sprintf(
		/* translators: 1 calories 2 protein */
		__( 'Example: McDonald’s Big Mac, %1$s cal and %2$sg protein. The similar-calorie burger at each other chain:', 'ffnc' ),
		ffnc_fmt_num( ffnc_num( $src, 'calories' ) ),
		ffnc_fmt_num( ffnc_num( $src, 'protein' ) )
	) ) . '</p><div class="ffnc-table-scroll" tabindex="0"><table class="ffnc-nutrition-table"><thead><tr><th scope="col">' . esc_html__( 'Restaurant', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Similar-calorie burger', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Calories', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Protein', 'ffnc' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

/** Day Planner: one example day built from four chains. */
function ffnc_tool_example_day_planner( $mode ) {
	$day = array(
		__( 'Breakfast', 'ffnc' ) => array( 'mcdonalds', 'egg-mcmuffin' ),
		__( 'Lunch', 'ffnc' )     => array( 'subway', 'turkey-breast' ),
		__( 'Dinner', 'ffnc' )    => array( 'chick-fil-a', 'grilled-chicken-sandwich' ),
		__( 'Snacks', 'ffnc' )    => array( 'dunkin', 'glazed-donut' ),
	);
	$rows  = '';
	$items = array();
	foreach ( $day as $meal => $pick ) {
		$b  = ffnc_get_brand( $pick[0] );
		$it = $b && ! empty( $b['live'] ) ? ffnc_get_brand_item( $pick[0], $pick[1] ) : null;
		if ( ! $it ) {
			return '';
		}
		$items[] = $it;
		$rows   .= '<tr><th scope="row">' . esc_html( $meal ) . '</th><td>' . esc_html( $b['name'] ) . ' ' . wp_kses_post( ffnc_item_link( $pick[0], $it ) ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $it, 'protein' ) ) ) . 'g</td></tr>';
	}
	$t = array_fill_keys( array( 'calories', 'protein', 'carbs', 'fat', 'sodium' ), 0 );
	foreach ( $items as $it ) {
		foreach ( $t as $k => $v ) {
			$t[ $k ] = round( $v + (float) ffnc_num( $it, $k ), 1 );
		}
	}
	return '<p class="ffnc-tool-note">' . esc_html__( 'Example day across four chains:', 'ffnc' ) . '</p><div class="ffnc-table-scroll" tabindex="0"><table class="ffnc-nutrition-table"><thead><tr><th scope="col">' . esc_html__( 'Meal', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Item', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Calories', 'ffnc' ) . '</th><th scope="col">' . esc_html__( 'Protein', 'ffnc' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' .
		'<p class="ffnc-day-sum"><strong>' . esc_html( ffnc_fmt_num( $t['calories'] ) ) . '</strong> ' . esc_html__( 'of 2,000 calories', 'ffnc' ) . '</p><div class="ffnc-item-pills">' . ffnc_tool_pills( $t ) . '</div>';
}

/* -------------------------------------------------------------------------
 * [ff_goal_list]
 * ---------------------------------------------------------------------- */
function ffnc_goal_definitions() {
	return array(
		'high-protein'  => array(
			'filter' => function ( $i ) { return $i['protein'] >= 25; },
			'sort'   => function ( $a, $b ) { return ( $b['protein'] / max( 1, $b['calories'] ) ) <=> ( $a['protein'] / max( 1, $a['calories'] ) ); },
			'note'   => __( 'Main items with at least 25g of protein, ranked by protein per 100 calories.', 'ffnc' ),
		),
		'under-500'     => array(
			'filter' => function ( $i ) { return $i['calories'] <= 500; },
			'sort'   => function ( $a, $b ) { return $b['protein'] <=> $a['protein']; },
			'note'   => __( 'Main items at 500 calories or less, most protein first.', 'ffnc' ),
		),
		'low-carb'      => array(
			'filter' => function ( $i ) { return null !== $i['carbs'] && $i['carbs'] <= 15; },
			'sort'   => function ( $a, $b ) { return ( $a['carbs'] <=> $b['carbs'] ) ?: ( $b['protein'] <=> $a['protein'] ); },
			'note'   => __( 'Main items with 15g of carbs or less as served, fewest carbs first. Restaurants do not publish "no bun" values, so bunless orders are not listed.', 'ffnc' ),
		),
		'low-sodium'    => array(
			'filter' => function ( $i ) { return null !== $i['sodium'] && $i['sodium'] <= 600; },
			'sort'   => function ( $a, $b ) { return $b['protein'] <=> $a['protein']; },
			'note'   => __( 'Main items with 600mg of sodium or less, most protein first. 600mg is about a quarter of the 2,300mg daily limit; it is our own cut-off, not the FDA’s “low sodium” label, which means 140mg or less per serving.', 'ffnc' ),
		),
		'diabetic'      => array(
			'filter' => function ( $i ) { return null !== $i['carbs'] && $i['carbs'] <= 30; },
			// Mains over 1,500mg of sodium go last, whatever their protein.
			'sort'   => function ( $a, $b ) { return ( ( $a['sodium'] > 1500 ) <=> ( $b['sodium'] > 1500 ) ) ?: ( $b['protein'] <=> $a['protein'] ) ?: ( $a['carbs'] <=> $b['carbs'] ); },
			'note'   => __( 'Main items with 30g of carbohydrate or less as served, most protein first; items over 1,500mg of sodium are listed last and items over 1,000mg are marked. The 30g cut-off is this site’s own, not a medical target.', 'ffnc' ),
			'flag_sodium' => 1000,
		),
		'carb-extras'   => array(
			'extras' => true,
			'filter' => function ( $i ) { return null !== $i['carbs'] && $i['carbs'] >= 30; },
			'sort'   => function ( $a, $b ) { return $b['carbs'] <=> $a['carbs']; },
			'note'   => __( 'Sides, drinks and desserts with 30g of carbs or more, most carbs first.', 'ffnc' ),
		),
		'small-portion' => array(
			'filter' => function ( $i ) { return $i['calories'] <= 400 && $i['protein'] >= 15; },
			'sort'   => function ( $a, $b ) { return ( $b['protein'] / max( 1, $b['calories'] ) ) <=> ( $a['protein'] / max( 1, $a['calories'] ) ); },
			'note'   => __( 'Smaller main items (400 calories or less) with at least 15g of protein, ranked by protein per calorie.', 'ffnc' ),
		),
	);
}

function ffnc_goal_rows( $goal, $limit = 40 ) {
	$defs = ffnc_goal_definitions();
	if ( empty( $defs[ $goal ] ) ) {
		return array();
	}
	$rows   = array();
	$extras = ! empty( $defs[ $goal ]['extras'] ); // Sides, drinks and desserts instead of mains.
	foreach ( ffnc_all_items() as $x ) {
		if ( $extras === in_array( $x['type'], ffnc_main_types(), true ) || 'ingredient' === $x['type'] || preg_match( '/family|bucket/i', $x['item']['name'] ) ) {
			continue;
		}
		$i = array(
			'calories' => ffnc_num( $x['item'], 'calories' ),
			'protein'  => ffnc_num( $x['item'], 'protein' ),
			'carbs'    => ffnc_num( $x['item'], 'carbs' ),
			'sodium'   => ffnc_num( $x['item'], 'sodium' ),
		);
		// Single pieces (one wing, one nugget) are not a meal on their own.
		if ( null === $i['calories'] || null === $i['protein'] || ( ! $extras && $i['calories'] < 120 ) ) {
			continue;
		}
		if ( call_user_func( $defs[ $goal ]['filter'], $i ) ) {
			$rows[] = $x + array( 'n' => $i );
		}
	}
	$sort = $defs[ $goal ]['sort'];
	usort( $rows, function ( $a, $b ) use ( $sort ) {
		return $sort( $a['n'], $b['n'] );
	} );
	return array_slice( $rows, 0, $limit );
}

function ffnc_render_item_rows_table( $rows, $caption, $extra_col = '', $flag_sodium = 0 ) {
	ob_start();
	?>
	<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( $caption ); ?>">
	<table class="ffnc-nutrition-table ffnc-goal-table">
		<caption class="screen-reader-text"><?php echo esc_html( $caption ); ?></caption>
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Item', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Restaurant', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Calories', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Protein', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Carbs', 'ffnc' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Sodium', 'ffnc' ); ?></th>
			<?php if ( 'density' === $extra_col ) : ?><th scope="col"><?php esc_html_e( 'Protein per 100 cal', 'ffnc' ); ?></th><?php endif; ?>
			<?php if ( 'net-carbs' === $extra_col ) : ?><th scope="col"><?php esc_html_e( 'Fiber', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Sugar', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Net carbs', 'ffnc' ); ?></th><?php endif; ?>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $r ) : $it = $r['item']; ?>
			<tr>
				<th scope="row"><a href="<?php echo esc_url( ffnc_item_page_url( $r['brand'], $it['id'] ) ?: ffnc_order_link( $r['brand_url'], $it['id'] ) ); ?>"><?php echo esc_html( $it['name'] ); ?></a><?php if ( ! empty( $it['availability'] ) ) : ?> <span class="ffnc-item-avail"><?php echo esc_html( $it['availability'] ); ?></span><?php endif; ?></th>
				<td><?php echo esc_html( $r['brand_name'] ); ?></td>
				<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) ); ?></td>
				<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'protein' ) ) . 'g' ); ?></td>
				<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'carbs' ) ) . ( null === ffnc_num( $it, 'carbs' ) ? '' : 'g' ) ); ?></td>
				<td><?php echo esc_html( ffnc_fmt_num( ffnc_num( $it, 'sodium' ) ) . ( null === ffnc_num( $it, 'sodium' ) ? '' : 'mg' ) ); ?><?php if ( $flag_sodium && ffnc_num( $it, 'sodium' ) > $flag_sodium ) : ?> <span class="ffnc-flag-high"><?php esc_html_e( 'high sodium', 'ffnc' ); ?></span><?php endif; ?></td>
				<?php if ( 'density' === $extra_col ) : ?><td><?php echo esc_html( ffnc_fmt_num( round( ffnc_num( $it, 'protein' ) / max( 1, ffnc_num( $it, 'calories' ) ) * 100, 1 ) ) . 'g' ); ?></td><?php endif; ?>
				<?php if ( 'net-carbs' === $extra_col ) : ?><td><?php echo esc_html( ffnc_fmt_g( ffnc_num( $it, 'fiber' ) ) ); ?></td><td><?php echo esc_html( ffnc_fmt_g( ffnc_num( $it, 'sugar' ) ) ); ?></td><td><?php echo esc_html( ffnc_fmt_g( ffnc_net_carbs( $it ) ) ); ?></td><?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
	return ob_get_clean();
}

function ffnc_shortcode_goal_list( $atts ) {
	$atts = shortcode_atts( array( 'goal' => 'high-protein', 'limit' => 40 ), $atts, 'ff_goal_list' );
	$defs = ffnc_goal_definitions();
	if ( empty( $defs[ $atts['goal'] ] ) ) {
		return '';
	}
	$rows = ffnc_goal_rows( $atts['goal'], max( 5, min( 100, (int) $atts['limit'] ) ) );
	if ( ! $rows ) {
		return '';
	}
	$density = in_array( $atts['goal'], array( 'high-protein', 'small-portion' ), true ) ? 'density' : '';
	if ( in_array( $atts['goal'], array( 'diabetic', 'carb-extras', 'low-carb' ), true ) && ffnc_items_have_extras( wp_list_pluck( $rows, 'item' ) ) ) {
		$density = 'net-carbs';
	}
	$out     = '<p class="ffnc-goal-note">' . esc_html( $defs[ $atts['goal'] ]['note'] ) . ' ' . esc_html__( 'Values are each restaurant’s published figures for the standard item; tap an item for its full nutrition and lighter swaps.', 'ffnc' ) . ( 'net-carbs' === $density ? ' ' . esc_html__( 'Net carbs are total carbohydrate minus fiber. Fiber and sugar are shown only where the restaurant publishes them; a dash means not published.', 'ffnc' ) : '' ) . '</p>';
	$flag    = isset( $defs[ $atts['goal'] ]['flag_sodium'] ) ? $defs[ $atts['goal'] ]['flag_sodium'] : 0;
	return $out . ffnc_render_item_rows_table( $rows, __( 'Ranked menu items', 'ffnc' ), $density, $flag );
}
add_shortcode( 'ff_goal_list', 'ffnc_shortcode_goal_list' );

/* -------------------------------------------------------------------------
 * [ff_cheat_sheet] / [ff_cheat_sheets]
 * ---------------------------------------------------------------------- */
function ffnc_render_cheat_sheet( $slug ) {
	$brand = ffnc_get_brand( $slug );
	if ( ! $brand ) {
		return '';
	}
	$items = array_filter( ffnc_all_items(), function ( $x ) use ( $slug ) {
		return $x['brand'] === $slug && ! preg_match( '/family|bucket/i', $x['item']['name'] ) && null !== ffnc_num( $x['item'], 'calories' );
	} );
	$mains = array_filter( $items, function ( $x ) {
		return in_array( $x['type'], ffnc_main_types(), true ) && ffnc_num( $x['item'], 'calories' ) <= 400;
	} );
	usort( $mains, function ( $a, $b ) {
		return ( (float) ffnc_num( $b['item'], 'protein' ) ) <=> ( (float) ffnc_num( $a['item'], 'protein' ) );
	} );
	$extras = array_filter( $items, function ( $x ) {
		return ! in_array( $x['type'], ffnc_main_types(), true ) && 'ingredient' !== $x['type'];
	} );
	usort( $extras, function ( $a, $b ) {
		return ffnc_num( $a['item'], 'calories' ) <=> ffnc_num( $b['item'], 'calories' );
	} );
	$mains  = array_slice( $mains, 0, 8 );
	$extras = array_slice( $extras, 0, 5 );
	if ( ! $mains && ! $extras ) {
		return '';
	}
	ob_start();
	?>
	<section class="ffnc-cheat" id="<?php echo esc_attr( 'cheat-' . $slug ); ?>">
		<header class="ffnc-cheat-head" style="--ffnc-brand-color:<?php echo esc_attr( ffnc_get_brand_color( $brand ) ); ?>">
			<h2><?php echo esc_html( sprintf( /* translators: %s brand */ __( '%s Cheat Sheet', 'ffnc' ), $brand['name'] ) ); ?></h2>
			<button type="button" class="ffnc-btn ffnc-share ffnc-print" onclick="var s=this.closest('.ffnc-cheat'),h=document.documentElement;h.classList.add('ffnc-printing');s.classList.add('ffnc-print-me');window.print();setTimeout(function(){h.classList.remove('ffnc-printing');s.classList.remove('ffnc-print-me');},1000);"><?php esc_html_e( 'Print / save as PDF', 'ffnc' ); ?></button>
		</header>
		<?php if ( $mains ) : ?>
			<h3><?php esc_html_e( 'Under 400 calories, most protein first', 'ffnc' ); ?></h3>
			<ol class="ffnc-cheat-list">
				<?php foreach ( $mains as $x ) : $it = $x['item']; ?>
					<li><a href="<?php echo esc_url( ffnc_order_link( $x['brand_url'], $it['id'] ) ); ?>"><?php echo esc_html( $it['name'] ); ?></a> <span><?php echo esc_html( sprintf( '%s cal · %sg protein', ffnc_fmt_num( ffnc_num( $it, 'calories' ) ), ffnc_fmt_num( ffnc_num( $it, 'protein' ) ) ) ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<?php if ( $extras ) : ?>
			<h3><?php esc_html_e( 'Lightest sides, drinks and treats', 'ffnc' ); ?></h3>
			<ul class="ffnc-cheat-list">
				<?php foreach ( $extras as $x ) : $it = $x['item']; ?>
					<li><?php echo esc_html( $it['name'] ); ?> <span><?php echo esc_html( sprintf( '%s cal', ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
		<p class="ffnc-cheat-foot"><?php echo esc_html( sprintf( /* translators: 1 brand 2 site */ __( '%1$s official U.S. values · %2$s', 'ffnc' ), $brand['name'], wp_parse_url( home_url(), PHP_URL_HOST ) ) ); ?></p>
	</section>
	<?php
	return ob_get_clean();
}

add_shortcode( 'ff_cheat_sheet', function ( $atts ) {
	$atts = shortcode_atts( array( 'brand' => '' ), $atts, 'ff_cheat_sheet' );
	return ffnc_render_cheat_sheet( sanitize_title( $atts['brand'] ) );
} );

add_shortcode( 'ff_cheat_sheets', function () {
	$nav = array();
	$out = '';
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$sheet = ffnc_render_cheat_sheet( $slug );
		if ( $sheet ) {
			$nav[] = '<a href="#cheat-' . esc_attr( $slug ) . '">' . esc_html( $brand['name'] ) . '</a>';
			$out  .= $sheet;
		}
	}
	return '<nav class="ffnc-cheat-nav" aria-label="' . esc_attr__( 'Cheat sheets', 'ffnc' ) . '">' . implode( ' ', $nav ) . '</nav><div class="ffnc-cheat-grid">' . $out . '</div>';
} );

/* -------------------------------------------------------------------------
 * [ff_menu_changes]
 * ---------------------------------------------------------------------- */
function ffnc_menu_changes() {
	$rows = array();
	foreach ( ffnc_get_live_brands() as $slug => $brand ) {
		$data = ffnc_get_brand_data( $slug );
		if ( ! $data || empty( $data['meta']['changelog'] ) ) {
			continue;
		}
		foreach ( $data['meta']['changelog'] as $c ) {
			if ( false !== stripos( $c['change'], 'Added provenance metadata' ) ) {
				continue; // Bookkeeping, not a menu change.
			}
			// Item ids are for maintainers, not readers.
			$text   = preg_replace( '/\s*(Removed\/replaced ids|New ids|Removed ids):[^.]*\./i', '', $c['change'] );
			$rows[] = array(
				'date'  => $c['date'],
				'brand' => $brand,
				'text'  => trim( $text ),
			);
		}
	}
	usort( $rows, function ( $a, $b ) {
		return strcmp( $b['date'], $a['date'] ) ?: strcmp( $a['brand']['name'], $b['brand']['name'] );
	} );
	return $rows;
}

add_shortcode( 'ff_menu_changes', function () {
	$rows = ffnc_menu_changes();
	if ( ! $rows ) {
		return '';
	}
	$out  = '';
	$last = '';
	foreach ( $rows as $r ) {
		if ( $r['date'] !== $last ) {
			$out .= ( $last ? '</ul>' : '' ) . '<h2 class="ffnc-changes-date">' . esc_html( ffnc_format_review_date( $r['date'], 'F j, Y' ) ) . '</h2><ul class="ffnc-changes">';
			$last = $r['date'];
		}
		$out .= '<li><a href="' . esc_url( home_url( $r['brand']['url'] ) ) . '">' . esc_html( $r['brand']['name'] ) . '</a>: ' . esc_html( $r['text'] ) . '</li>';
	}
	return $out . '</ul>';
} );

/* -------------------------------------------------------------------------
 * [ff_nutrition_report] + CSV
 * ---------------------------------------------------------------------- */
function ffnc_report_top( $filter, $key, $limit = 10, $desc = true ) {
	$rows = array_values( array_filter( ffnc_all_items(), $filter ) );
	usort( $rows, function ( $a, $b ) use ( $key, $desc ) {
		$va = is_callable( $key ) ? $key( $a ) : (float) ffnc_num( $a['item'], $key );
		$vb = is_callable( $key ) ? $key( $b ) : (float) ffnc_num( $b['item'], $key );
		return $desc ? $vb <=> $va : $va <=> $vb;
	} );
	return array_slice( $rows, 0, $limit );
}

add_shortcode( 'ff_nutrition_report', function () {
	$all   = ffnc_all_items();
	$mains = function ( $x ) {
		return in_array( $x['type'], ffnc_main_types(), true ) && ! preg_match( '/family|bucket/i', $x['item']['name'] ) && null !== ffnc_num( $x['item'], 'calories' );
	};
	$latest = '';
	foreach ( $all as $x ) {
		$latest = max( $latest, $x['reviewed'] );
	}
	// For averages and "lightest main": a meal-sized main, so breakfast
	// sides (hash browns) and single pieces (one wing, one nugget) are left
	// out, the same rule the goal lists use.
	$meal = function ( $x ) use ( $mains ) {
		return $mains( $x ) && ffnc_num( $x['item'], 'calories' ) >= 120 && ! preg_match( '/hash brown|whole wing|\(1 (piece|wing)\)/i', $x['item']['name'] );
	};
	$per = array();
	foreach ( array_filter( $all, $meal ) as $x ) {
		$per[ $x['brand'] ][] = $x;
	}
	ob_start();
	?>
	<div class="ffnc-report">
		<ul class="ffnc-report-stats">
			<li><strong><?php echo esc_html( number_format_i18n( count( $all ) ) ); ?></strong> <?php esc_html_e( 'menu items and sizes', 'ffnc' ); ?></li>
			<li><strong><?php echo esc_html( number_format_i18n( count( ffnc_get_live_brands() ) ) ); ?></strong> <?php esc_html_e( 'U.S. chains', 'ffnc' ); ?></li>
			<li><strong><?php echo esc_html( ffnc_format_review_date( $latest, 'M j, Y' ) ); ?></strong> <?php esc_html_e( 'latest official check', 'ffnc' ); ?></li>
		</ul>

		<h2><?php esc_html_e( 'Highest-calorie single items', 'ffnc' ); ?></h2>
		<?php echo ffnc_render_item_rows_table( ffnc_report_top( $mains, 'calories' ), __( 'Highest-calorie items', 'ffnc' ) ); // phpcs:ignore ?>

		<h2><?php esc_html_e( 'Most sodium in one item', 'ffnc' ); ?></h2>
		<?php echo ffnc_render_item_rows_table( ffnc_report_top( function ( $x ) use ( $mains ) { return $mains( $x ) && null !== ffnc_num( $x['item'], 'sodium' ); }, 'sodium' ), __( 'Highest-sodium items', 'ffnc' ) ); // phpcs:ignore ?>

		<h2><?php esc_html_e( 'Most protein per calorie (at least 20g protein)', 'ffnc' ); ?></h2>
		<?php echo ffnc_render_item_rows_table( ffnc_report_top( function ( $x ) use ( $mains ) { return $mains( $x ) && ffnc_num( $x['item'], 'protein' ) >= 20; }, function ( $x ) { return ffnc_num( $x['item'], 'protein' ) / max( 1, ffnc_num( $x['item'], 'calories' ) ); } ), __( 'Most protein per calorie', 'ffnc' ), 'density' ); // phpcs:ignore ?>

		<h2><?php esc_html_e( 'Average main item by chain', 'ffnc' ); ?></h2>
		<div class="ffnc-table-scroll" tabindex="0">
		<table class="ffnc-nutrition-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Chain', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Main items counted', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Average calories', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Average protein', 'ffnc' ); ?></th><th scope="col"><?php esc_html_e( 'Lightest main', 'ffnc' ); ?></th></tr></thead>
			<tbody>
			<?php
			uasort( $per, function ( $a, $b ) {
				$avg = function ( $l ) { return array_sum( array_map( function ( $x ) { return ffnc_num( $x['item'], 'calories' ); }, $l ) ) / count( $l ); };
				return $avg( $a ) <=> $avg( $b );
			} );
			foreach ( $per as $slug => $list ) :
				$cal  = array_sum( array_map( function ( $x ) { return ffnc_num( $x['item'], 'calories' ); }, $list ) ) / count( $list );
				$pro  = array_sum( array_map( function ( $x ) { return (float) ffnc_num( $x['item'], 'protein' ); }, $list ) ) / count( $list );
				$lite = array_reduce( $list, function ( $c, $x ) { return ( ! $c || ffnc_num( $x['item'], 'calories' ) < ffnc_num( $c['item'], 'calories' ) ) ? $x : $c; } );
				?>
				<tr><th scope="row"><a href="<?php echo esc_url( $list[0]['brand_url'] ); ?>"><?php echo esc_html( $list[0]['brand_name'] ); ?></a></th><td><?php echo esc_html( count( $list ) ); ?></td><td><?php echo esc_html( number_format_i18n( round( $cal ) ) ); ?></td><td><?php echo esc_html( number_format_i18n( round( $pro ) ) . 'g' ); ?></td><td><?php echo esc_html( $lite['item']['name'] . ' (' . ffnc_fmt_num( ffnc_num( $lite['item'], 'calories' ) ) . ' cal)' ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="ffnc-goal-note"><?php esc_html_e( '"Main items" leaves out sides, drinks, desserts, sauces, family-size items, breakfast sides such as hash browns and single pieces such as one wing or one nugget. Averages cover only the items in our calculators, not each chain’s full menu, and some chains have far more items than others, so compare averages with care. Chipotle is not in this table because it publishes nutrition by ingredient, not by finished item.', 'ffnc' ); ?></p>

		<h2><?php esc_html_e( 'Download the data', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'Every item in our calculators, with calories, protein, carbs, fat, sodium, the official source and the date we checked it. Free to use in articles and research with a link back to this page.', 'ffnc' ); ?></p>
		<p><a class="ffnc-btn ffnc-btn-solid" href="<?php echo esc_url( add_query_arg( 'ffnc_dataset', 'csv', home_url( '/' ) ) ); ?>" rel="nofollow"><?php esc_html_e( 'Download CSV', 'ffnc' ); ?></a></p>
	</div>
	<?php
	return ob_get_clean();
} );

function ffnc_maybe_send_dataset() {
	if ( empty( $_GET['ffnc_dataset'] ) || 'csv' !== $_GET['ffnc_dataset'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="fast-food-nutrition-' . gmdate( 'Y-m-d' ) . '.csv"' );
	header( 'X-Robots-Tag: noindex' );
	$fh = fopen( 'php://output', 'w' ); // phpcs:ignore
	fputcsv( $fh, array( 'restaurant', 'item', 'category', 'calories', 'protein_g', 'carbs_g', 'fat_g', 'sodium_mg', 'sat_fat_g', 'sugar_g', 'fiber_g', 'official_source', 'last_checked', 'data_from' ) );
	foreach ( ffnc_all_items() as $x ) {
		$it   = $x['item'];
		$data = ffnc_get_brand_data( $x['brand'] );
		fputcsv( $fh, array(
			$x['brand_name'],
			$it['name'],
			$x['category'],
			ffnc_num( $it, 'calories' ),
			ffnc_num( $it, 'protein' ),
			ffnc_num( $it, 'carbs' ),
			ffnc_num( $it, 'fat' ),
			ffnc_num( $it, 'sodium' ),
			ffnc_num( $it, 'sat_fat' ),
			ffnc_num( $it, 'sugar' ),
			ffnc_num( $it, 'fiber' ),
			! empty( $it['source_url'] ) ? $it['source_url'] : ( isset( $data['meta']['source_url'] ) ? $data['meta']['source_url'] : '' ),
			! empty( $it['last_checked'] ) ? $it['last_checked'] : $x['reviewed'],
			home_url( '/' ),
		) );
	}
	fclose( $fh ); // phpcs:ignore
	exit;
}
add_action( 'template_redirect', 'ffnc_maybe_send_dataset', 1 );

/* -------------------------------------------------------------------------
 * Embeddable calculator: /?ffnc_embed=mcdonalds
 * ---------------------------------------------------------------------- */
function ffnc_maybe_render_embed() {
	if ( empty( $_GET['ffnc_embed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$slug  = sanitize_title( wp_unslash( $_GET['ffnc_embed'] ) ); // phpcs:ignore
	$brand = ffnc_get_brand( $slug );
	if ( ! $brand || empty( $brand['live'] ) || ! ffnc_get_brand_data( $slug ) ) {
		return;
	}
	header_remove( 'X-Frame-Options' );
	header( 'X-Robots-Tag: noindex, follow' );
	$page = home_url( $brand['url'] );
	?><!doctype html>
<html lang="en-US">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<link rel="canonical" href="<?php echo esc_url( $page ); ?>">
<title><?php echo esc_html( $brand['title'] ); ?></title>
<link rel="stylesheet" href="<?php echo esc_url( FFNC_URI . '/assets/css/main.css?ver=' . FFNC_VERSION ); ?>">
<style>body{margin:0;padding:8px;background:var(--ffnc-bg,#f5f7fb)}.ffnc-calc-back,.ffnc-theme-toggle{display:none}.ffnc-embed-credit{font:14px/1.4 system-ui,sans-serif;text-align:center;margin:10px 0 4px}</style>
</head>
<body class="ffnc-embed">
<?php echo ffnc_shortcode_calculator( array( 'brand' => $slug ) ); // phpcs:ignore ?>
<p class="ffnc-embed-credit"><?php echo wp_kses_post( sprintf( /* translators: 1 link */ __( 'Calculator by %s', 'ffnc' ), '<a href="' . esc_url( $page ) . '" target="_blank" rel="noopener">' . esc_html( get_bloginfo( 'name' ) ) . '</a>' ) ); ?></p>
<script>window.ffncCalcConfig = { dataUrl: <?php echo wp_json_encode( FFNC_URI . '/assets/data/' ); ?> };</script>
<script src="<?php echo esc_url( FFNC_URI . '/assets/js/calculator.js?ver=' . FFNC_VERSION ); ?>"></script>
</body>
</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'ffnc_maybe_render_embed', 1 );

/** "Embed this calculator" box for calculator pages. */
function ffnc_render_embed_box( $slug ) {
	$brand = ffnc_get_brand( $slug );
	if ( ! $brand ) {
		return '';
	}
	$src  = add_query_arg( 'ffnc_embed', $slug, home_url( '/' ) );
	$code = '<iframe src="' . esc_url( $src ) . '" title="' . esc_attr( $brand['title'] ) . '" width="100%" height="900" style="border:0;max-width:1200px" loading="lazy"></iframe>' .
		'<p><a href="' . esc_url( home_url( $brand['url'] ) ) . '">' . esc_html( $brand['title'] ) . '</a></p>';
	return '<details class="ffnc-embed-box"><summary>' . esc_html__( 'Embed this calculator on your site', 'ffnc' ) . '</summary>' .
		'<p>' . esc_html__( 'Free for blogs, dietitians and gyms. Paste this code where you want the calculator to appear; please keep the link under it.', 'ffnc' ) . '</p>' .
		'<label class="screen-reader-text" for="ffnc-embed-' . esc_attr( $slug ) . '">' . esc_html__( 'Embed code', 'ffnc' ) . '</label>' .
		'<textarea id="ffnc-embed-' . esc_attr( $slug ) . '" readonly rows="4" onclick="this.select()">' . esc_textarea( $code ) . '</textarea></details>';
}

/* -------------------------------------------------------------------------
 * Tool pages (created once, published) + hub
 * ---------------------------------------------------------------------- */
function ffnc_tool_pages() {
	return array(
		'tools'                                 => array(
			'title'   => __( 'Free Fast Food Nutrition Tools', 'ffnc' ),
			'seo'     => __( 'Free Fast Food Nutrition Tools: Order Ideas, Day Planner & More', 'ffnc' ),
			'desc'    => __( 'Free tools that turn fast food nutrition data into decisions: order ideas for your calories and protein, a day planner across chains, closest matches at other restaurants, cheat sheets and more.', 'ffnc' ),
			'content' => '[ff_tools_hub]',
			'tool'    => '',
		),
		'what-to-order'                         => array(
			'title'   => __( 'What Should I Order? Fast Food Orders for Your Calories and Protein', 'ffnc' ),
			'seo'     => __( 'What to Order at Fast Food: Orders That Fit Your Calories & Protein', 'ffnc' ),
			'desc'    => __( 'Tell us how many calories you have left and how much protein you want. We build ready-to-order meals from McDonald’s, Taco Bell, Subway and 11 more chains.', 'ffnc' ),
			'content' => "<p>Most nutrition sites show you a menu and leave the maths to you. This tool works the other way round: give it your calorie budget and protein goal, and it builds orders that fit, from each restaurant's published nutrition values.</p>\n\n[ff_tool name=\"order-ideas\"]\n\n<h2>How the orders are picked</h2>\n<p>We try every combination of up to three menu items (drinks left out) at each restaurant, keep the ones within your calories that meet your protein and any carb or sodium limit, and show the orders with the most protein. Tick <em>Small appetite</em> to see at most two items, ranked by protein per calorie instead.</p>",
			'tool'    => 'order-ideas',
		),
		'small-portion-high-protein-fast-food'  => array(
			'title'   => __( 'Small-Portion, High-Protein Fast Food Orders', 'ffnc' ),
			'seo'     => __( 'Small-Portion High-Protein Fast Food (GLP-1 Friendly Orders)', 'ffnc' ),
			'desc'    => __( 'Smaller fast food orders that still bring the protein: items under 400 calories ranked by protein per calorie, plus an order builder for small appetites.', 'ffnc' ),
			'content' => "<p>If you eat smaller portions, for example while taking a GLP-1 medication, or you simply aren't very hungry, the goal is usually the same: get enough protein from a small amount of food. These are the fast food items that do that best, using each chain's official numbers.</p>\n<p>This page is general nutrition information, not medical advice. Follow the eating plan your doctor or dietitian gave you.</p>\n\n[ff_tool name=\"order-ideas\" mode=\"small-portion\"]\n\n<h2>Most protein per calorie, under 400 calories</h2>\n[ff_goal_list goal=\"small-portion\" limit=\"30\"]",
			'tool'    => 'order-ideas',
		),
		'fast-food-day-planner'                 => array(
			'title'   => __( 'Fast Food Day Planner: Add Up a Whole Day Across Restaurants', 'ffnc' ),
			'seo'     => __( 'Fast Food Day Planner: Breakfast, Lunch & Dinner Calories', 'ffnc' ),
			'desc'    => __( 'Plan breakfast at Dunkin’, lunch at Chipotle and dinner at Subway, and see your whole day’s calories, protein, carbs, fat and sodium against your target.', 'ffnc' ),
			'content' => "<p>Real days don't happen at one restaurant. Add breakfast, lunch, dinner and snacks from any of our chains and see the day's total against your own calorie target. Your plan stays saved in this browser, and you can copy a link to share it.</p>\n\n[ff_tool name=\"day-planner\"]",
			'tool'    => 'day-planner',
		),
		'same-craving-other-restaurant'         => array(
			'title'   => __( 'Same Craving, Different Restaurant', 'ffnc' ),
			'seo'     => __( 'Same Craving, Different Chain: Closest Fast Food Matches', 'ffnc' ),
			'desc'    => __( 'Pick a menu item and see the closest match at every other chain, with calories and protein, plus the lightest option of the same kind.', 'ffnc' ),
			'content' => "<p>Want a Big Mac but you're at Wendy's? Craving nuggets but a Chick-fil-A is closer? Pick what you want and see the closest item of the same kind at every other chain we cover, and the lightest one.</p>\n\n[ff_tool name=\"same-craving\"]",
			'tool'    => 'same-craving',
		),
		'high-protein-fast-food'                => array(
			'title'   => __( 'High-Protein Fast Food: Most Protein per Calorie', 'ffnc' ),
			'seo'     => __( 'High-Protein Fast Food Ranked by Protein per Calorie', 'ffnc' ),
			'desc'    => __( 'Every fast food main with 25g+ protein from 18 chains, ranked by protein per 100 calories using official nutrition data.', 'ffnc' ),
			'content' => "<p>Protein per calorie is the fairest way to compare: a 1,200-calorie burger can have lots of protein and still be a poor deal. Here is every main item with at least 25g of protein across our 18 chains, best ratio first.</p>\n\n[ff_goal_list goal=\"high-protein\" limit=\"40\"]\n\n<p>Want a full order instead of one item? Use <a href=\"/what-to-order/\">What Should I Order?</a></p>",
			'tool'    => '',
		),
		'fast-food-under-500-calories'          => array(
			'title'   => __( 'Fast Food Under 500 Calories (Most Protein First)', 'ffnc' ),
			'seo'     => __( 'Fast Food Under 500 Calories: 40 Best Picks by Protein', 'ffnc' ),
			'desc'    => __( 'Fast food main items at 500 calories or less from 18 chains, sorted by protein, with official nutrition values.', 'ffnc' ),
			'content' => "<p>Every main item here is 500 calories or less as served, sorted so the most filling (highest-protein) choices come first.</p>\n\n[ff_goal_list goal=\"under-500\" limit=\"40\"]",
			'tool'    => '',
		),
		'low-carb-fast-food'                    => array(
			'title'   => __( 'Low-Carb Fast Food: Mains With 15g of Carbs or Less', 'ffnc' ),
			'seo'     => __( 'Low-Carb & Keto-Friendly Fast Food (15g Carbs or Less)', 'ffnc' ),
			'desc'    => __( 'Fast food main items with 15g of carbs or less as served, from official nutrition data for 18 chains.', 'ffnc' ),
			'content' => "<p>These items are low in carbs exactly as the restaurant serves them. We don't list \"no bun\" versions because restaurants don't publish their numbers, and we only show published values.</p>\n\n[ff_goal_list goal=\"low-carb\" limit=\"40\"]",
			'tool'    => '',
		),
		'low-sodium-fast-food'                  => array(
			'title'   => __( 'Lower-Sodium Fast Food: Mains Under 600mg Sodium', 'ffnc' ),
			'seo'     => __( 'Lower-Sodium Fast Food: Main Items Under 600mg Sodium', 'ffnc' ),
			'desc'    => __( 'Fast food main items with 600mg of sodium or less, from official nutrition data for 18 chains.', 'ffnc' ),
			'content' => "<p>Fast food is often salty. These main items have 600mg of sodium or less, sorted by protein. Remember that fries, sauces and some drinks add sodium too.</p>\n<p>Why 600mg? It is about a quarter of the 2,300mg a day the Dietary Guidelines for Americans set as the upper limit for adults. It is our own cut-off for a lower-sodium main, not the FDA's \"low sodium\" label, which means 140mg or less per serving.</p>\n\n[ff_goal_list goal=\"low-sodium\" limit=\"40\"]",
			'tool'    => '',
		),
		'diabetic-friendly-fast-food'           => array(
			'title'   => __( 'Lower-Carb Fast Food for Diabetes: Mains Under 30g Carbs', 'ffnc' ),
			'seo'     => __( 'Lower-Carb Fast Food for Diabetes: Mains Under 30g Carbs', 'ffnc' ),
			'desc'    => __( 'Fast food main items with 30g of carbs or less, ranked by protein, plus the sides and drinks that add the most carbs. Official nutrition data for 18 chains.', 'ffnc' ),
			'content' => "<p>If you have diabetes, the number that matters most on a fast food menu is usually carbohydrate. These are the main items with 30g of carbs or less exactly as served, with the most protein first, from each restaurant's official U.S. nutrition data.</p>\n<p>The 30g per item cut-off is this site's own, not a medical target. Many fast food mains are also very salty, so items over 1,000mg of sodium are marked and items over 1,500mg are listed last.</p>\n<p>This page is general nutrition information, not medical advice. Your doctor, diabetes educator or dietitian can tell you how many carbs fit your own plan. Where a restaurant publishes fiber and sugar, the table also shows them and the net carbs (total carbohydrate minus fiber); a dash means the restaurant does not publish that value.</p>\n\n[ff_goal_list goal=\"diabetic\" limit=\"40\"]\n\n<h2>Where the carbs hide: sides, drinks and desserts</h2>\n<p>A regular soda or sweet tea and a side of fries often carry more carbs than the main. Water, unsweetened tea, diet soda or black coffee add none.</p>\n[ff_goal_list goal=\"carb-extras\" limit=\"20\"]\n\n<p>Want to add up a whole order? Every item links to its full nutrition, and each restaurant's calculator shows the carb total as you build an order. For the lowest-carb picks, see <a href=\"/low-carb-fast-food/\">Low-Carb Fast Food</a>.</p>",
			'tool'    => '',
		),
		'vegetarian-fast-food'                  => array(
			'title'   => __( 'Vegetarian Fast Food Options at 18 Chains', 'ffnc' ),
			'seo'     => __( 'Vegetarian Fast Food: Meat-Free Options With Calories & Protein', 'ffnc' ),
			'desc'    => __( 'Meat-free fast food items from Taco Bell, Subway, Chipotle, Domino’s, Panera and more, with calories and protein from official nutrition data.', 'ffnc' ),
			'content' => "<p>These items contain no meat, poultry or fish according to each restaurant's published ingredients. Many contain dairy or egg, so they are vegetarian, not vegan. Most chains cook in shared fryers and on shared grills, so check the restaurant's own allergen and ingredient guide if that matters to you.</p>\n\n[ff_vegetarian_list]",
			'tool'    => '',
		),
		'calories'                              => array(
			'title'   => __( 'Fast Food Calories by Menu Item', 'ffnc' ),
			'seo'     => __( 'Fast Food Calories by Item: Big Mac, McNuggets, Whopper & More', 'ffnc' ),
			'desc'    => __( 'Calories, protein, carbs, fat and sodium for every menu item in our calculators, one page per item, plus meal totals and head-to-head comparisons.', 'ffnc' ),
			'content' => "<p>Looking for one number rather than a whole calculator? Every item below has its own page with calories by size, full nutrition, lighter swaps and how it compares with other chains.</p>\n\n[ff_item_index]",
			'tool'    => '',
		),
		'fast-food-cheat-sheets'                => array(
			'title'   => __( 'Fast Food Cheat Sheets: Best Picks at 18 Chains', 'ffnc' ),
			'seo'     => __( 'Printable Fast Food Cheat Sheets: Under 400 Calories', 'ffnc' ),
			'desc'    => __( 'One printable card per chain: the highest-protein items under 400 calories and the lightest sides and drinks.', 'ffnc' ),
			'content' => "<p>Save or print the card for the places you go most. Each one lists the highest-protein items under 400 calories and the lightest sides, drinks and treats.</p>\n\n[ff_cheat_sheets]",
			'tool'    => '',
		),
		'menu-changes'                          => array(
			'title'   => __( 'Fast Food Menu & Nutrition Changes', 'ffnc' ),
			'seo'     => __( 'Fast Food Menu Changes & Nutrition Updates Log', 'ffnc' ),
			'desc'    => __( 'Every change we make to our fast food nutrition data, newest first: new items, corrected values and official guide updates.', 'ffnc' ),
			'content' => "<p>Each time we check a restaurant's official nutrition information, we log what changed here: new items, corrected values and new guide editions.</p>\n\n[ff_menu_changes]",
			'tool'    => '',
		),
		'fast-food-nutrition-report'            => array(
			'title'   => __( 'Fast Food Nutrition Report 2026', 'ffnc' ),
			'seo'     => __( 'Fast Food Nutrition Report 2026: Data From 18 U.S. Chains', 'ffnc' ),
			'desc'    => __( 'The highest-calorie and saltiest fast food items, the best protein per calorie, and average calories by chain, from official data. Free CSV download.', 'ffnc' ),
			'content' => "<p>We keep official U.S. nutrition data for 18 fast food chains up to date for our calculators. Here is what that data says, updated automatically whenever we recheck a chain.</p>\n\n[ff_nutrition_report]",
			'tool'    => '',
		),
	);
}

/**
 * Create each tool page once, published, the first time someone who can
 * publish pages opens wp-admin after a deploy. A page that already exists is
 * never touched, and a page created once is never re-created (so deleting
 * one in WordPress keeps it deleted).
 */
function ffnc_ensure_tool_pages() {
	if ( ! current_user_can( 'publish_pages' ) || get_option( 'ffnc_tool_pages_version' ) === FFNC_VERSION ) {
		return;
	}
	$done = (array) get_option( 'ffnc_tool_pages_created', array() );
	foreach ( ffnc_tool_pages() as $slug => $cfg ) {
		if ( in_array( $slug, $done, true ) ) {
			continue;
		}
		if ( ! get_page_by_path( $slug, OBJECT, 'page' ) ) {
			$id = wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $cfg['title'],
				'post_name'    => $slug,
				'post_content' => $cfg['content'],
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ffnc_seo_title', $cfg['seo'] );
			update_post_meta( $id, '_ffnc_meta_description', $cfg['desc'] );
		}
		$done[] = $slug;
	}
	update_option( 'ffnc_tool_pages_created', $done, false );
	update_option( 'ffnc_tool_pages_version', FFNC_VERSION, false );
}
add_action( 'admin_init', 'ffnc_ensure_tool_pages' );

add_shortcode( 'ff_tools_hub', function () {
	$out = '<div class="ffnc-tools-grid">';
	foreach ( ffnc_tool_pages() as $slug => $cfg ) {
		if ( 'tools' === $slug ) {
			continue;
		}
		$url  = ffnc_page_url( $slug );
		$out .= '<a class="ffnc-tool-card" href="' . esc_url( $url ? $url : home_url( '/' . $slug . '/' ) ) . '"><strong>' . esc_html( $cfg['title'] ) . '</strong><span>' . esc_html( $cfg['desc'] ) . '</span></a>';
	}
	return $out . '</div>';
} );

/** Links to the tools from every calculator page (after the deals table). */
function ffnc_render_calculator_tools_links( $slug ) {
	$brand = ffnc_get_brand( $slug );
	if ( ! $brand ) {
		return '';
	}
	$links = array();
	$add   = function ( $page, $label, $args = array() ) use ( &$links ) {
		$url = ffnc_page_url( $page );
		if ( $url ) {
			$links[] = '<li><a href="' . esc_url( add_query_arg( $args, $url ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
	};
	if ( 'chipotle' !== $slug ) {
		$add( 'what-to-order', sprintf( /* translators: %s brand */ __( 'What should I order at %s? (orders for your calories and protein)', 'ffnc' ), $brand['name'] ), array( 'brand' => $slug ) );
		$add( 'same-craving-other-restaurant', sprintf( /* translators: %s brand */ __( 'Closest %s items at other chains', 'ffnc' ), $brand['name'] ), array( 'brand' => $slug ) );
	}
	$sheets = ffnc_page_url( 'fast-food-cheat-sheets' );
	if ( $sheets ) {
		$links[] = '<li><a href="' . esc_url( $sheets . '#cheat-' . $slug ) . '">' . esc_html( sprintf( /* translators: %s brand */ __( 'Printable %s cheat sheet', 'ffnc' ), $brand['name'] ) ) . '</a></li>';
	}
	$add( 'fast-food-day-planner', __( 'Plan a whole day across restaurants', 'ffnc' ) );
	$out = ffnc_render_brand_item_links( $slug );
	if ( $links ) {
		$out .= '<aside class="ffnc-more-tools"><h2>' . esc_html__( 'More free tools', 'ffnc' ) . '</h2><ul class="ffnc-list">' . implode( '', $links ) . '</ul></aside>';
	}
	return $out . ffnc_render_embed_box( $slug );
}
