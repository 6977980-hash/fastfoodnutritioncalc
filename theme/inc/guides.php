<?php
/**
 * Nutrition guides (blog posts) built on the site's own data.
 *
 *   [ff_val item="mcdonalds:big-mac" field="calories"]   One live number from a dataset.
 *   [ff_guide_table name="atwater|sodium-by-chain|protein-by-chain|meal-500|breakfast-400|sizes"]
 *   [ff_calorie_needs]                                    Mifflin-St Jeor calorie-needs calculator.
 *
 * The guides themselves (ffnc_guide_posts()) are created once, published,
 * the first time an editor opens wp-admin after a deploy, like the tool
 * pages: a guide that exists is never touched and a deleted one stays
 * deleted. Every number in them comes from the shortcodes above, so the
 * prose cannot drift from the calculators.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * [ff_val]
 * ---------------------------------------------------------------------- */
add_shortcode( 'ff_val', function ( $atts ) {
	$atts = shortcode_atts( array( 'item' => '', 'field' => 'calories' ), $atts, 'ff_val' );
	list( $brand, $id ) = array_pad( explode( ':', $atts['item'] ), 2, '' );
	$item = ffnc_get_brand_item( sanitize_title( $brand ), sanitize_title( $id ) );
	if ( ! $item ) {
		return current_user_can( 'edit_posts' ) ? '[?' . esc_html( $atts['item'] ) . ']' : '—';
	}
	if ( 'name' === $atts['field'] ) {
		return esc_html( $item['name'] );
	}
	return esc_html( ffnc_fmt_num( ffnc_num( $item, sanitize_key( $atts['field'] ) ) ) );
} );

/* -------------------------------------------------------------------------
 * [ff_guide_table]
 * ---------------------------------------------------------------------- */

/** Meal-sized mains: no family sizes, single pieces or breakfast sides. */
function ffnc_guide_meal_mains() {
	return array_values( array_filter( ffnc_all_items(), function ( $x ) {
		return in_array( $x['type'], ffnc_main_types(), true )
			&& ffnc_num( $x['item'], 'calories' ) >= 120
			&& null !== ffnc_num( $x['item'], 'protein' )
			&& ! preg_match( '/family|bucket|hash brown|whole wing|\(1 (piece|wing)\)/i', $x['item']['name'] );
	} ) );
}

function ffnc_guide_table_wrap( $caption, $head, $rows ) {
	$th = '';
	foreach ( $head as $h ) {
		$th .= '<th scope="col">' . esc_html( $h ) . '</th>';
	}
	return '<div class="ffnc-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr( $caption ) . '"><table class="ffnc-nutrition-table"><caption class="screen-reader-text">' . esc_html( $caption ) . '</caption><thead><tr>' . $th . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

function ffnc_guide_link( $x ) {
	return ffnc_item_link( $x['brand'], $x['item'] ) . ( empty( $x['item']['availability'] ) ? '' : ' <span class="ffnc-item-avail">(' . esc_html( $x['item']['availability'] ) . ')</span>' );
}

add_shortcode( 'ff_guide_table', function ( $atts ) {
	$atts = shortcode_atts( array( 'name' => '', 'limit' => 10 ), $atts, 'ff_guide_table' );
	$rows = '';
	switch ( $atts['name'] ) {
		case 'atwater':
			// Published calories against 4/4/9 kcal per gram of protein, carbs and fat.
			$list = array_filter( ffnc_all_items(), function ( $x ) {
				foreach ( array( 'calories', 'protein', 'carbs', 'fat' ) as $k ) {
					if ( null === ffnc_num( $x['item'], $k ) ) {
						return false;
					}
				}
				// Food only: drinks are mostly sugar, where rounding alone moves the result.
				return ffnc_num( $x['item'], 'calories' ) >= 100 && ! in_array( $x['type'], array( 'ingredient', 'drink', 'coffee', 'shake' ), true );
			} );
			$list = array_map( function ( $x ) {
				$i      = $x['item'];
				$x['m'] = 4 * ffnc_num( $i, 'protein' ) + 4 * ffnc_num( $i, 'carbs' ) + 9 * ffnc_num( $i, 'fat' );
				$x['g'] = ( ffnc_num( $i, 'calories' ) - $x['m'] ) / ffnc_num( $i, 'calories' );
				return $x;
			}, $list );
			usort( $list, function ( $a, $b ) {
				return abs( $b['g'] ) <=> abs( $a['g'] );
			} );
			foreach ( array_slice( $list, 0, (int) $atts['limit'] ) as $x ) {
				$rows .= '<tr><th scope="row">' . ffnc_guide_link( $x ) . '</th><td>' . esc_html( $x['brand_name'] ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $x['item'], 'calories' ) ) ) . '</td><td>' . esc_html( ffnc_fmt_num( round( $x['m'] ) ) ) . '</td><td>' . esc_html( ( $x['g'] > 0 ? '+' : '' ) . round( $x['g'] * 100 ) . '%' ) . '</td></tr>';
			}
			return ffnc_guide_table_wrap( __( 'Published calories against calories worked out from the macros', 'ffnc' ), array( __( 'Item', 'ffnc' ), __( 'Restaurant', 'ffnc' ), __( 'Published calories', 'ffnc' ), __( 'From protein, carbs and fat', 'ffnc' ), __( 'Difference', 'ffnc' ) ), $rows );

		case 'sodium-by-chain':
			$per = array();
			foreach ( ffnc_guide_meal_mains() as $x ) {
				if ( null !== ffnc_num( $x['item'], 'sodium' ) ) {
					$per[ $x['brand'] ][] = $x;
				}
			}
			$stats = array();
			foreach ( $per as $slug => $list ) {
				usort( $list, function ( $a, $b ) {
					return ffnc_num( $b['item'], 'sodium' ) <=> ffnc_num( $a['item'], 'sodium' );
				} );
				$stats[] = array(
					'list' => $list,
					'avg'  => array_sum( array_map( function ( $x ) { return ffnc_num( $x['item'], 'sodium' ); }, $list ) ) / count( $list ),
				);
			}
			usort( $stats, function ( $a, $b ) {
				return $b['avg'] <=> $a['avg'];
			} );
			foreach ( $stats as $s ) {
				$top   = $s['list'][0];
				$rows .= '<tr><th scope="row"><a href="' . esc_url( $top['brand_url'] ) . '">' . esc_html( $top['brand_name'] ) . '</a></th><td>' . esc_html( sprintf( '%s mg (%d)', number_format_i18n( round( $s['avg'] ) ), count( $s['list'] ) ) ) . '</td><td>' . ffnc_guide_link( $top ) . '</td><td>' . esc_html( number_format_i18n( ffnc_num( $top['item'], 'sodium' ) ) . ' mg (' . round( ffnc_num( $top['item'], 'sodium' ) / 23 ) . '%)' ) . '</td></tr>';
			}
			return ffnc_guide_table_wrap( __( 'Sodium in main items by chain', 'ffnc' ), array( __( 'Chain', 'ffnc' ), __( 'Average main (items counted)', 'ffnc' ), __( 'Saltiest main', 'ffnc' ), __( 'Its sodium (% of 2,300 mg)', 'ffnc' ) ), $rows );

		case 'protein-by-chain':
			$best = array();
			foreach ( ffnc_guide_meal_mains() as $x ) {
				if ( ffnc_num( $x['item'], 'protein' ) < 20 ) {
					continue;
				}
				$d = ffnc_num( $x['item'], 'protein' ) / ffnc_num( $x['item'], 'calories' );
				if ( ! isset( $best[ $x['brand'] ] ) || $d > $best[ $x['brand'] ]['d'] ) {
					$best[ $x['brand'] ] = $x + array( 'd' => $d );
				}
			}
			usort( $best, function ( $a, $b ) {
				return $b['d'] <=> $a['d'];
			} );
			foreach ( $best as $x ) {
				$rows .= '<tr><th scope="row">' . esc_html( $x['brand_name'] ) . '</th><td>' . ffnc_guide_link( $x ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $x['item'], 'calories' ) ) ) . '</td><td>' . esc_html( ffnc_fmt_num( ffnc_num( $x['item'], 'protein' ) ) ) . 'g</td><td>' . esc_html( ffnc_fmt_num( round( $x['d'] * 100, 1 ) ) ) . 'g</td></tr>';
			}
			return ffnc_guide_table_wrap( __( 'Most protein per calorie at each chain', 'ffnc' ), array( __( 'Chain', 'ffnc' ), __( 'Item (20g+ protein)', 'ffnc' ), __( 'Calories', 'ffnc' ), __( 'Protein', 'ffnc' ), __( 'Protein per 100 cal', 'ffnc' ) ), $rows );

		case 'meal-500':
			// Per chain: one main plus at most one side, 500 calories or less, most protein.
			$sides = array_filter( ffnc_all_items(), function ( $x ) {
				return in_array( $x['type'], array( 'fries', 'side' ), true ) && null !== ffnc_num( $x['item'], 'calories' ) && ! preg_match( '/family|bucket/i', $x['item']['name'] );
			} );
			$best = array();
			foreach ( ffnc_guide_meal_mains() as $m ) {
				if ( 'chipotle' === $m['brand'] || 'breakfast' === $m['type'] ) {
					continue;
				}
				$opts = array( null );
				foreach ( $sides as $s ) {
					if ( $s['brand'] === $m['brand'] ) {
						$opts[] = $s;
					}
				}
				foreach ( $opts as $s ) {
					$cal = ffnc_num( $m['item'], 'calories' ) + ( $s ? ffnc_num( $s['item'], 'calories' ) : 0 );
					$pro = ffnc_num( $m['item'], 'protein' ) + ( $s ? (float) ffnc_num( $s['item'], 'protein' ) : 0 );
					if ( $cal > 500 ) {
						continue;
					}
					$cur = isset( $best[ $m['brand'] ] ) ? $best[ $m['brand'] ] : null;
					// A side is only worth adding when the meal still has the most protein.
					if ( ! $cur || $pro > $cur['pro'] || ( $pro === $cur['pro'] && $cal < $cur['cal'] ) ) {
						$best[ $m['brand'] ] = array( 'main' => $m, 'side' => $s, 'cal' => $cal, 'pro' => $pro );
					}
				}
			}
			usort( $best, function ( $a, $b ) {
				return $b['pro'] <=> $a['pro'];
			} );
			foreach ( $best as $b ) {
				$ids   = array_filter( array( $b['main']['item']['id'], $b['side'] ? $b['side']['item']['id'] : '' ) );
				$rows .= '<tr><th scope="row">' . esc_html( $b['main']['brand_name'] ) . '</th><td>' . ffnc_guide_link( $b['main'] ) . ( $b['side'] ? ' + ' . ffnc_guide_link( $b['side'] ) : '' ) . '</td><td>' . esc_html( ffnc_fmt_num( $b['cal'] ) ) . '</td><td>' . esc_html( ffnc_fmt_num( $b['pro'] ) ) . 'g</td><td><a href="' . esc_url( ffnc_order_link( $b['main']['brand_url'], $ids ) ) . '">' . esc_html__( 'Open in calculator', 'ffnc' ) . '</a></td></tr>';
			}
			return ffnc_guide_table_wrap( __( 'Highest-protein meal of 500 calories or less at each chain', 'ffnc' ), array( __( 'Chain', 'ffnc' ), __( 'Meal', 'ffnc' ), __( 'Calories', 'ffnc' ), __( 'Protein', 'ffnc' ), '' ), $rows );

		case 'breakfast-400':
			$list = array_filter( ffnc_guide_meal_mains(), function ( $x ) {
				// A breakfast main, not a plain biscuit or toast side (8g+ protein).
				return 'breakfast' === $x['type'] && ffnc_num( $x['item'], 'calories' ) <= 400 && ffnc_num( $x['item'], 'protein' ) >= 8;
			} );
			usort( $list, function ( $a, $b ) {
				return ffnc_num( $b['item'], 'protein' ) <=> ffnc_num( $a['item'], 'protein' );
			} );
			return ffnc_render_item_rows_table( array_slice( $list, 0, max( 5, (int) $atts['limit'] ) ), __( 'Breakfast items of 400 calories or less', 'ffnc' ) );

		case 'sizes':
			$sets = array(
				array( 'mcdonalds', array( 'fries-small', 'fries-medium', 'fries-large' ) ),
				array( 'mcdonalds', array( 'nuggets-4', 'nuggets-6', 'nuggets-10', 'nuggets-20' ) ),
				array( 'five-guys', array( 'little-fries', 'regular-fries', 'large-fries' ) ),
			);
			foreach ( $sets as $set ) {
				$b     = ffnc_get_brand( $set[0] );
				$cells = array();
				foreach ( $set[1] as $id ) {
					$it = ffnc_get_brand_item( $set[0], $id );
					if ( $it ) {
						$cells[] = ffnc_item_link( $set[0], $it ) . ': ' . esc_html( ffnc_fmt_num( ffnc_num( $it, 'calories' ) ) );
					}
				}
				if ( $b && $cells ) {
					$rows .= '<tr><th scope="row">' . esc_html( $b['name'] ) . '</th><td>' . implode( '<br>', $cells ) . '</td></tr>';
				}
			}
			return ffnc_guide_table_wrap( __( 'Calories by size', 'ffnc' ), array( __( 'Restaurant', 'ffnc' ), __( 'Calories by size', 'ffnc' ) ), $rows );
	}
	return '';
} );

/* -------------------------------------------------------------------------
 * [ff_calorie_needs]
 * ---------------------------------------------------------------------- */
add_shortcode( 'ff_calorie_needs', function () {
	$planner = ffnc_page_url( 'fast-food-day-planner' );
	$order   = ffnc_page_url( 'what-to-order' );
	$uid     = wp_unique_id( 'ffnc-tdee-' );
	ob_start();
	?>
	<div class="ffnc-tdee" id="<?php echo esc_attr( $uid ); ?>" data-planner="<?php echo esc_url( $planner ); ?>" data-order="<?php echo esc_url( $order ); ?>">
		<form class="ffnc-tool-form ffnc-tdee-form" onsubmit="return false">
			<fieldset class="ffnc-tdee-units"><legend><?php esc_html_e( 'Units', 'ffnc' ); ?></legend>
				<label><input type="radio" name="u" value="us" checked> <?php esc_html_e( 'lb / ft', 'ffnc' ); ?></label>
				<label><input type="radio" name="u" value="metric"> <?php esc_html_e( 'kg / cm', 'ffnc' ); ?></label>
			</fieldset>
			<fieldset class="ffnc-tdee-units"><legend><?php esc_html_e( 'Sex', 'ffnc' ); ?></legend>
				<label><input type="radio" name="sex" value="f" checked> <?php esc_html_e( 'Female', 'ffnc' ); ?></label>
				<label><input type="radio" name="sex" value="m"> <?php esc_html_e( 'Male', 'ffnc' ); ?></label>
			</fieldset>
			<label><?php esc_html_e( 'Age', 'ffnc' ); ?> <input type="number" name="age" min="18" max="100" value="35" inputmode="numeric"></label>
			<label class="ffnc-tdee-us"><?php esc_html_e( 'Weight (lb)', 'ffnc' ); ?> <input type="number" name="lb" min="70" max="700" value="170" inputmode="decimal"></label>
			<label class="ffnc-tdee-us"><?php esc_html_e( 'Height (feet)', 'ffnc' ); ?> <input type="number" name="ft" min="4" max="7" value="5" inputmode="numeric"></label>
			<label class="ffnc-tdee-us"><?php esc_html_e( 'and inches', 'ffnc' ); ?> <input type="number" name="in" min="0" max="11" value="7" inputmode="numeric"></label>
			<label class="ffnc-tdee-metric" hidden><?php esc_html_e( 'Weight (kg)', 'ffnc' ); ?> <input type="number" name="kg" min="30" max="320" value="77" inputmode="decimal"></label>
			<label class="ffnc-tdee-metric" hidden><?php esc_html_e( 'Height (cm)', 'ffnc' ); ?> <input type="number" name="cm" min="120" max="230" value="170" inputmode="numeric"></label>
			<label><?php esc_html_e( 'Activity', 'ffnc' ); ?>
				<select name="act">
					<option value="1.2"><?php esc_html_e( 'Little or no exercise (×1.2)', 'ffnc' ); ?></option>
					<option value="1.375" selected><?php esc_html_e( 'Light exercise 1–3 days a week (×1.375)', 'ffnc' ); ?></option>
					<option value="1.55"><?php esc_html_e( 'Moderate exercise 3–5 days a week (×1.55)', 'ffnc' ); ?></option>
					<option value="1.725"><?php esc_html_e( 'Hard exercise 6–7 days a week (×1.725)', 'ffnc' ); ?></option>
					<option value="1.9"><?php esc_html_e( 'Very hard exercise or a physical job (×1.9)', 'ffnc' ); ?></option>
				</select>
			</label>
		</form>
		<div class="ffnc-tool-results ffnc-tdee-out" aria-live="polite"></div>
		<noscript><p class="ffnc-calc-error"><?php esc_html_e( 'The calculator needs JavaScript. You can use the formula below by hand.', 'ffnc' ); ?></p></noscript>
	</div>
	<h3><?php esc_html_e( 'The formula', 'ffnc' ); ?></h3>
	<p><?php esc_html_e( 'This calculator uses the Mifflin-St Jeor equation for resting energy (the calories your body burns at rest), then multiplies it by an activity factor to estimate the calories you burn in a day:', 'ffnc' ); ?></p>
	<ul class="ffnc-list">
		<li><?php esc_html_e( 'Men: 10 × weight (kg) + 6.25 × height (cm) − 5 × age (years) + 5', 'ffnc' ); ?></li>
		<li><?php esc_html_e( 'Women: 10 × weight (kg) + 6.25 × height (cm) − 5 × age (years) − 161', 'ffnc' ); ?></li>
		<li><?php esc_html_e( 'Daily calories = resting energy × activity factor (1.2 to 1.9).', 'ffnc' ); ?></li>
	</ul>
	<p class="ffnc-goal-note"><?php esc_html_e( 'Source: Mifflin MD, St Jeor ST, et al. “A new predictive equation for resting energy expenditure in healthy individuals.” American Journal of Clinical Nutrition, 1990;51(2):241–247. The result is an estimate for healthy adults; it is not suitable during pregnancy, for children or for some medical conditions. For a personal target, talk to your doctor or a registered dietitian.', 'ffnc' ); ?></p>
	<script>
	(function () {
		var box = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
		if (!box) { return; }
		var f = box.querySelector('form'), out = box.querySelector('.ffnc-tdee-out');
		function n(name) { return Number(f.elements[name].value) || 0; }
		function fmt(x) { return Math.round(x).toLocaleString('en-US'); }
		function run() {
			var metric = f.elements.u.value === 'metric';
			box.querySelectorAll('.ffnc-tdee-us').forEach(function (e) { e.hidden = metric; });
			box.querySelectorAll('.ffnc-tdee-metric').forEach(function (e) { e.hidden = !metric; });
			var kg = metric ? n('kg') : n('lb') * 0.45359237;
			var cm = metric ? n('cm') : (n('ft') * 12 + n('in')) * 2.54;
			var age = n('age');
			if (kg < 30 || cm < 120 || age < 18) { out.innerHTML = '<p>Enter an adult age, weight and height.</p>'; return; }
			var bmr = 10 * kg + 6.25 * cm - 5 * age + (f.elements.sex.value === 'm' ? 5 : -161);
			var tdee = Math.round(bmr * Number(f.elements.act.value) / 10) * 10;
			var meal = Math.round(tdee * 0.3 / 10) * 10;
			var links = '';
			if (box.dataset.planner) { links += '<a class="ffnc-btn ffnc-btn-solid" href="' + box.dataset.planner + '?t=' + tdee + '">Plan a fast food day at ' + fmt(tdee) + ' calories</a> '; }
			if (box.dataset.order) { links += '<a class="ffnc-btn" href="' + box.dataset.order + '">Orders that fit about ' + fmt(meal) + ' calories</a>'; }
			out.innerHTML = '<p class="ffnc-day-sum">About <strong>' + fmt(tdee) + '</strong> calories a day to keep your weight steady</p>' +
				'<p>Resting energy: ' + fmt(bmr) + ' calories. Split over three meals and a snack, that is roughly ' + fmt(meal) + ' calories per meal.</p>' +
				'<p class="ffnc-item-actions">' + links + '</p>';
		}
		f.addEventListener('input', run);
		f.addEventListener('change', run);
		run();
	})();
	</script>
	<?php
	return ob_get_clean();
} );

/* -------------------------------------------------------------------------
 * The guides
 * ---------------------------------------------------------------------- */
function ffnc_guide_posts() {
	$l = function ( $path, $text ) {
		return '<a href="/' . $path . '/">' . $text . '</a>';
	};
	$calc = function ( $slug, $text ) {
		$b = ffnc_get_brand( $slug );
		return '<a href="' . ( $b ? $b['url'] : '/calculators/' ) . '">' . $text . '</a>';
	};
	return array(
		'how-fast-food-chains-measure-calories' => array(
			'title'   => 'How Fast Food Chains Work Out Their Calorie Counts',
			'excerpt' => 'Where the calorie numbers on a fast food menu come from, why they are rounded, and why calories and macros do not always add up, with examples from our data.',
			'content' => "<p>Every calorie number in our calculators comes from the restaurant's own published nutrition information. But how does a chain arrive at \"[ff_val item=\"mcdonalds:big-mac\" field=\"calories\"] calories\" for a Big Mac in the first place, and how exact is it? Here is what the numbers are, and what they are not.</p>\n\n<h2>The rule chains follow in the U.S.</h2>\n<p>Since 2018, U.S. restaurant chains with 20 or more locations have had to post calories on their menus and menu boards and to provide more nutrition information (fat, carbohydrate, protein, sodium and more) in writing on request. That is the FDA's menu labeling rule. The rule does not make chains send every item to a lab. It asks for a \"reasonable basis\" for the numbers, and lists the accepted methods: nutrient databases, cookbooks, laboratory analyses and other reasonable means, such as the Nutrition Facts label on a packaged ingredient.</p>\n<p>In practice most chains calculate: they take the standard recipe (how many grams of patty, bun, cheese and sauce), look up each ingredient in a nutrient database or the supplier's data, and add it up. Some test finished items in a lab as well. Either way the number describes the standard recipe, not the exact sandwich you get on a given day.</p>\n\n<h2>Why the numbers are round</h2>\n<p>Menu calories are rounded: to the nearest 5 calories up to 50, and to the nearest 10 above that. That is why almost every item ends in a zero. Five Guys is an exception in our data because it publishes nutrition by component (bun, patty, toppings) and we add those parts up, so its totals end in other digits, such as [ff_val item=\"five-guys:hamburger\" field=\"calories\"] calories for a Hamburger.</p>\n\n<h2>When calories and macros don't add up</h2>\n<p>A gram of protein or carbohydrate gives about 4 calories and a gram of fat about 9 (the Atwater factors). If you multiply out an item's protein, carbs and fat you usually land close to the published calories, but rarely exactly: fiber gives fewer calories than other carbs, each value is rounded on its own, and a calculated recipe can use different factors. We checked every item in our calculators this way. These are the ones where the gap is largest:</p>\n[ff_guide_table name=\"atwater\" limit=\"8\"]\n<p>A gap of a few percent is normal. The Five Guys burgers stand out because the guide's own calorie and macro figures do not match each other. We show the restaurant's official numbers as published and note this on the " . $calc( 'five-guys', 'Five Guys calculator' ) . ", rather than \"correcting\" them ourselves.</p>\n\n<h2>How much can the real food differ?</h2>\n<p>Portions are made by hand, so the fries in your bag or the sauce on your sandwich will not weigh exactly what the recipe says. Restaurants also change recipes and suppliers. That is why we show the date we last checked each chain, link to the official source on every calculator page, and log every change on " . $l( 'menu-changes', 'Menu Changes' ) . ". Treat any single number as a good estimate of a standard serving, not a lab result for your meal.</p>\n\n<h2>What this means when you order</h2>\n<ul>\n<li>Compare like with like: published numbers are most useful for comparing items on the same menu, which were all worked out the same way.</li>\n<li>Customizations change everything. \"No mayo\" or \"extra cheese\" is not in the base number; our calculators add the add-ons the chain publishes.</li>\n<li>Check the date. Our " . $l( 'methodology', 'methodology page' ) . " lists when every dataset was last checked against the official source.</li>\n</ul>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li><a href=\"https://www.fda.gov/food/food-labeling-nutrition/menu-labeling-requirements\" rel=\"noopener\" target=\"_blank\">FDA: Menu Labeling Requirements</a></li>\n<li><a href=\"https://www.ecfr.gov/current/title-21/chapter-I/subchapter-B/part-101/subpart-A/section-101.11\" rel=\"noopener\" target=\"_blank\">21 CFR 101.11: Nutrition labeling of standard menu items in covered establishments</a></li>\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'how-to-read-a-restaurant-nutrition-guide' => array(
			'title'   => 'How to Read a Restaurant Nutrition Guide (PDF) Without Getting Lost',
			'excerpt' => 'Serving sizes, per-ingredient tables, "less than 1 g" and footnotes: how to read a fast food nutrition PDF and add up your own order.',
			'content' => "<p>Most chains publish a nutrition guide: a long PDF or web table with every item, size and sauce. It is the most reliable source there is, and it is also easy to misread. Here is how we read them when we build our calculators, and what to watch for.</p>\n\n<h2>1. Find the serving the row describes</h2>\n<p>Every row is one serving as named, made with the standard recipe. Sizes are separate rows, and they do not scale evenly. A medium is not simply twice a small:</p>\n[ff_guide_table name=\"sizes\"]\n<p>So always read the exact size you order. Pizza chains list nutrition per slice, and wings are often listed per wing, so multiply by what you actually eat.</p>\n\n<h2>2. Check whether the table lists finished items or ingredients</h2>\n<p>Some chains publish finished items (a Big Mac is [ff_val item=\"mcdonalds:big-mac\" field=\"calories\"] calories). Others publish ingredients or components and leave the adding up to you. Chipotle lists each ingredient by portion, for example a flour tortilla for a burrito is [ff_val item=\"chipotle:flour-tortilla-burrito\" field=\"calories\"] calories and a portion of chicken [ff_val item=\"chipotle:chicken\" field=\"calories\"]. Five Guys lists bun, patty and each topping. For these chains, a burrito or burger is the sum of every part, which is exactly what our " . $calc( 'chipotle', 'Chipotle calculator' ) . " does.</p>\n\n<h2>3. Read the small print on sandwiches and salads</h2>\n<p>A \"standard recipe\" can include more than you think. Subway's 6-inch subs are listed with the bread, cheese, vegetables and default sauce Subway uses, which is why a 6\" Oven-Roasted Turkey is [ff_val item=\"subway:turkey-breast\" field=\"calories\"] calories as served. Salads are often listed with the default dressing and chicken. If you order it differently, the number changes.</p>\n\n<h2>4. Know the units</h2>\n<ul>\n<li>Calories, grams (fat, carbohydrate, protein) and milligrams (sodium). 1,000 mg of sodium is 1 gram.</li>\n<li>\"Less than 1 g\" or \"&lt;1\" means a small amount that rounds below one gram. We store it as 0.</li>\n<li>Total carbohydrate includes fiber and sugar. Many guides list them on separate lines.</li>\n<li>% Daily Value, where shown, is based on a 2,000-calorie day and 2,300 mg of sodium.</li>\n</ul>\n\n<h2>5. Look for the date and the country</h2>\n<p>Every guide has an \"updated\" date, usually in the footer. Nutrition changes when recipes change, so a guide from two years ago can be wrong today. Guides are also country-specific: the same item can have a different recipe in another country. Our data covers U.S. menus only, and every calculator page shows the guide we used and the date we checked it.</p>\n\n<h2>6. Add up your order</h2>\n<p>Add every item, side, drink and sauce, each at the size you order. That is tedious with a PDF and quick with a calculator: pick a chain from our " . $l( 'calculators', 'list of calculators' ) . " and every add-on the chain publishes is counted for you. If something in our data doesn't match the guide you are looking at, please " . $l( 'report-an-error', 'report it' ) . ".</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li><a href=\"https://www.fda.gov/food/nutrition-facts-label/how-understand-and-use-nutrition-facts-label\" rel=\"noopener\" target=\"_blank\">FDA: How to Understand and Use the Nutrition Facts Label</a></li>\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'sodium-in-fast-food' => array(
			'title'   => 'Sodium in Fast Food: Which Chains and Items Have the Most',
			'excerpt' => 'Average and highest sodium per main item at 18 U.S. chains, how that compares with the 2,300 mg daily limit, and easy ways to cut back.',
			'content' => "<p>Calories get most of the attention, but fast food is often hardest on sodium. The Dietary Guidelines for Americans, 2025–2030 say people aged 14 and over should have less than 2,300 mg of sodium a day. A single fast food main can use half of that or more.</p>\n\n<h2>Sodium by chain</h2>\n<p>This table uses the official sodium figure of every meal-sized main item in our calculators (no sides, drinks or single pieces). The average shows how salty a typical main is at that chain; the second column shows how many items it is based on.</p>\n[ff_guide_table name=\"sodium-by-chain\"]\n<p>Averages depend on which items each chain has in our data, so use them as a rough guide and look at the items themselves.</p>\n\n<h2>Where the sodium comes from</h2>\n<ul>\n<li><strong>Breading and seasoning.</strong> Fried chicken and nuggets are often seasoned and brined. 8 Chick-fil-A Nuggets have [ff_val item=\"chick-fil-a:nuggets-8\" field=\"sodium\"] mg of sodium; the 8-count Grilled Nuggets have [ff_val item=\"chick-fil-a:grilled-nuggets-8\" field=\"sodium\"] mg.</li>\n<li><strong>Cured meats and cheese.</strong> Bacon, ham, pepperoni, salami and cheese add up quickly on subs and pizza.</li>\n<li><strong>Sauces and dressings.</strong> A sauce cup can add a few hundred milligrams; our calculators add the sodium of every sauce the chain publishes.</li>\n<li><strong>Size.</strong> Sodium rises with size like calories do: a large McDonald's fries has [ff_val item=\"mcdonalds:fries-large\" field=\"sodium\"] mg against [ff_val item=\"mcdonalds:fries-small\" field=\"sodium\"] mg for a small.</li>\n</ul>\n\n<h2>Easy ways to cut back</h2>\n<ul>\n<li>Pick grilled over fried, and single over double.</li>\n<li>Skip or halve the sauce, and ask for no added salt on fries where the chain allows it.</li>\n<li>Choose a smaller side, or a side like fruit or a plain salad.</li>\n<li>Balance the day: if lunch is salty, keep the rest of the day lower.</li>\n</ul>\n<p>For a ready list, see " . $l( 'low-sodium-fast-food', 'Lower-Sodium Fast Food: Mains Under 600mg Sodium' ) . ". Every item page also shows what share of the 2,300 mg limit it uses.</p>\n\n<h2>A note on \"low sodium\"</h2>\n<p>\"Low sodium\" has a legal meaning on U.S. food labels: 140 mg or less per serving. Almost no fast food main meets that. When we say \"lower-sodium\" we mean lower than most items on the menu, not the FDA label.</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li><a href=\"https://cdn.realfood.gov/DGA.pdf\" rel=\"noopener\" target=\"_blank\">Dietary Guidelines for Americans, 2025–2030 (sodium: less than 2,300 mg a day for ages 14 and up)</a></li>\n<li><a href=\"https://www.fda.gov/food/nutrition-education-resources-materials/sodium-your-diet\" rel=\"noopener\" target=\"_blank\">FDA: Sodium in Your Diet</a></li>\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'high-protein-low-calorie-fast-food-by-chain' => array(
			'title'   => 'The Best Protein per Calorie at Every Fast Food Chain',
			'excerpt' => 'One pick per chain: the main item with at least 20g of protein and the most protein per calorie, from official nutrition data.',
			'content' => "<p>If you work out, you probably care about one thing at a drive-through: how much protein you get for the calories. Protein per 100 calories is the fairest way to compare. A 1,000-calorie burger can have plenty of protein and still be a poor deal; a small item with 25g is a much better one.</p>\n\n<h2>The best pick at each chain</h2>\n<p>For each chain, this is the meal-sized main with at least 20g of protein and the most protein per calorie, using the restaurant's official numbers. It updates automatically when we recheck a chain.</p>\n[ff_guide_table name=\"protein-by-chain\"]\n\n<h2>What the winners have in common</h2>\n<ul>\n<li><strong>Grilled, not fried.</strong> Breading and frying oil add calories without protein. At Chick-fil-A, 8 Grilled Nuggets give [ff_val item=\"chick-fil-a:grilled-nuggets-8\" field=\"protein\"]g of protein for [ff_val item=\"chick-fil-a:grilled-nuggets-8\" field=\"calories\"] calories, while 8 regular Nuggets give [ff_val item=\"chick-fil-a:nuggets-8\" field=\"protein\"]g for [ff_val item=\"chick-fil-a:nuggets-8\" field=\"calories\"].</li>\n<li><strong>Less bread and sauce.</strong> Bowls, salads with chicken and protein-forward items beat big buns and creamy sauces.</li>\n<li><strong>A bigger portion of the same thing.</strong> Within one item, a larger size often keeps the same ratio, so you can scale up without making it worse.</li>\n</ul>\n\n<h2>How much protein do you need?</h2>\n<p>The Dietary Guidelines for Americans, 2025–2030 set protein goals of 1.2 to 1.6 grams per kilogram of body weight a day, adjusted to your calorie needs. For someone who weighs 80 kg (176 lb) that is roughly 96 to 128 grams a day, so a 30 to 40 gram meal is a solid share. Not sure how many calories you need? Use the calculator in " . $l( 'how-many-calories-should-you-eat-in-a-day', 'How Many Calories Should You Eat in a Day?' ) . ".</p>\n\n<h2>Build a full order</h2>\n<p>One item is rarely a whole meal. " . $l( 'what-to-order', 'What Should I Order?' ) . " builds orders of up to three items that fit your calories and protein, and " . $l( 'high-protein-fast-food', 'High-Protein Fast Food' ) . " ranks every main with 25g or more.</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li><a href=\"https://cdn.realfood.gov/DGA.pdf\" rel=\"noopener\" target=\"_blank\">Dietary Guidelines for Americans, 2025–2030 (protein: 1.2–1.6 g per kg of body weight a day)</a></li>\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'fast-food-on-glp-1-small-portions' => array(
			'title'   => 'Fast Food on a GLP-1 Medication: Small Orders That Still Bring Protein',
			'excerpt' => 'Eating less on Ozempic, Wegovy, Mounjaro or Zepbound? How to pick small fast food orders that still give you protein, with the best items from 18 chains.',
			'content' => "<p>GLP-1 medications such as semaglutide (Ozempic, Wegovy) and tirzepatide (Mounjaro, Zepbound) make many people feel full sooner, so portions shrink. That raises a practical question at a fast food counter: if you can only eat a little, how do you make that little count?</p>\n<p>This guide is general nutrition information, not medical advice. Follow the eating plan from your doctor, prescriber or dietitian, especially on how much protein and how many calories you should aim for.</p>\n\n<h2>The idea: protein first, in a small portion</h2>\n<p>When you eat less food overall, it is easy to fall short on protein. So the most useful fast food items are small ones with a high protein share. The Dietary Guidelines for Americans, 2025–2030 recommend prioritizing protein foods at every meal.</p>\n<p>These items are 400 calories or less with at least 15g of protein, ranked by protein per calorie:</p>\n[ff_goal_list goal=\"small-portion\" limit=\"15\"]\n\n<h2>Tips that help with small appetites</h2>\n<ul>\n<li><strong>Start with the protein.</strong> Eat the chicken, egg or meat first, before fries or bread fill you up.</li>\n<li><strong>Go small or kids-size on sides.</strong> A small fries at McDonald's is [ff_val item=\"mcdonalds:fries-small\" field=\"calories\"] calories against [ff_val item=\"mcdonalds:fries-large\" field=\"calories\"] for a large.</li>\n<li><strong>Drink water or unsweetened drinks.</strong> Sugary drinks add calories with no protein and take up room.</li>\n<li><strong>Save half.</strong> Many items keep well. Ordering a normal size and eating half is fine.</li>\n<li><strong>Watch very rich meals.</strong> Some people find large, greasy meals harder to manage on these medications. Ask your prescriber what applies to you.</li>\n</ul>\n\n<h2>Build a small order</h2>\n<p>Our " . $l( 'small-portion-high-protein-fast-food', 'Small-Portion, High-Protein Fast Food' ) . " page has an order builder set to small appetites: at most two items, ranked by protein per calorie. You can also set your own calorie and protein numbers in " . $l( 'what-to-order', 'What Should I Order?' ) . ".</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li><a href=\"https://cdn.realfood.gov/DGA.pdf\" rel=\"noopener\" target=\"_blank\">Dietary Guidelines for Americans, 2025–2030 (\"Prioritize protein foods at every meal\")</a></li>\n<li><a href=\"https://medlineplus.gov/druginfo/meds/a618008.html\" rel=\"noopener\" target=\"_blank\">MedlinePlus: Semaglutide</a></li>\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'500-calorie-fast-food-meals' => array(
			'title'   => 'How to Build a 500-Calorie Fast Food Meal (Best Pick at Every Chain)',
			'excerpt' => 'A main plus a side for 500 calories or less, with the most protein, at every chain we cover, plus the simple rules behind it.',
			'content' => "<p>500 calories is a common target for a lighter meal: it is a quarter of a 2,000-calorie day, and about what you get if you split 1,600 to 1,800 calories over three meals and a snack. Here is how to hit it at a fast food restaurant without going hungry.</p>\n\n<h2>The best 500-calorie meal at each chain</h2>\n<p>For every chain, we tried each meal-sized main on its own and with each side, kept the combinations at 500 calories or less, and picked the one with the most protein. Drinks are left out, so have water, unsweetened tea, black coffee or a diet soda. Chipotle is left out because its meals are built from ingredients; use the " . $calc( 'chipotle', 'Chipotle calculator' ) . " for that.</p>\n[ff_guide_table name=\"meal-500\"]\n<p>Each \"Open in calculator\" link loads the meal so you can swap the side or add a sauce and see the new total.</p>\n\n<h2>Four rules that make it easy</h2>\n<ol>\n<li><strong>Pick the protein first.</strong> A grilled chicken item, a smaller burger or nuggets give you the most to work with.</li>\n<li><strong>Choose the smallest side, or none.</strong> Fries are the biggest swing: at McDonald's a small is [ff_val item=\"mcdonalds:fries-small\" field=\"calories\"] calories and a large [ff_val item=\"mcdonalds:fries-large\" field=\"calories\"].</li>\n<li><strong>Count the sauce.</strong> Creamy sauces and dressings can add 100 calories or more per cup.</li>\n<li><strong>Drink zero-calorie.</strong> A medium Coca-Cola at McDonald's is [ff_val item=\"mcdonalds:coke-medium\" field=\"calories\"] calories, more than half of this whole meal.</li>\n</ol>\n\n<h2>Need a different number?</h2>\n<p>Find your own daily calories with the calculator in " . $l( 'how-many-calories-should-you-eat-in-a-day', 'How Many Calories Should You Eat in a Day?' ) . ", then use " . $l( 'what-to-order', 'What Should I Order?' ) . " to build orders for any calorie and protein target, or the " . $l( 'fast-food-day-planner', 'Day Planner' ) . " to plan a whole day.</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
		'fast-food-breakfast-under-400-calories' => array(
			'title'   => 'Fast Food Breakfast Under 400 Calories, Ranked by Protein',
			'excerpt' => 'Breakfast sandwiches and wraps at 400 calories or less, most protein first, plus the swaps that keep a fast food breakfast light.',
			'content' => "<p>Breakfast is where fast food calories hide in plain sight: a biscuit sandwich, a sweet coffee and hash browns can pass 1,000 calories before 9 a.m. The good news is that the breakfast menus in our calculators also have sandwiches and wraps that are filling and light.</p>\n\n<h2>Breakfast items of 400 calories or less</h2>\n<p>All of these are 400 calories or less as served, with the most protein first:</p>\n[ff_guide_table name=\"breakfast-400\" limit=\"15\"]\n\n<h2>What makes a breakfast heavier</h2>\n<ul>\n<li><strong>Biscuits and croissants.</strong> They carry far more fat than an English muffin or a wrap. At McDonald's, an Egg McMuffin is [ff_val item=\"mcdonalds:egg-mcmuffin\" field=\"calories\"] calories and a Sausage Biscuit [ff_val item=\"mcdonalds:sausage-biscuit\" field=\"calories\"].</li>\n<li><strong>Sausage.</strong> Swapping sausage for egg, ham or bacon usually saves calories and fat.</li>\n<li><strong>The coffee.</strong> At Dunkin', a medium iced coffee is [ff_val item=\"dunkin:iced-coffee-black-medium\" field=\"calories\"] calories black, [ff_val item=\"dunkin:iced-coffee-cream-medium\" field=\"calories\"] with cream, and [ff_val item=\"dunkin:iced-coffee-cream-sugar-medium\" field=\"calories\"] with cream and sugar.</li>\n<li><strong>Hash browns.</strong> Tasty, but mostly carbs and fat with little protein.</li>\n</ul>\n\n<h2>Plan the rest of the day</h2>\n<p>A lighter breakfast leaves room for lunch and dinner. Add it to the " . $l( 'fast-food-day-planner', 'Day Planner' ) . " to see your whole day, or check the full menu in the " . $calc( 'mcdonalds', 'McDonald\'s' ) . ", " . $calc( 'dunkin', 'Dunkin\'' ) . " and " . $calc( 'chick-fil-a', 'Chick-fil-A' ) . " calculators.</p>\n\n<h2>Sources</h2>\n<ul class=\"ffnc-sources\">\n<li>Menu figures: each restaurant's official U.S. nutrition information, as linked on the " . $l( 'methodology', 'methodology page' ) . ".</li>\n</ul>",
		),
	);
}

/**
 * Create each guide once, published, when an editor opens wp-admin.
 */
function ffnc_ensure_guide_posts() {
	if ( ! current_user_can( 'publish_posts' ) || get_option( 'ffnc_guide_posts_version' ) === FFNC_VERSION ) {
		return;
	}
	$done = (array) get_option( 'ffnc_guide_posts_created', array() );
	foreach ( ffnc_guide_posts() as $slug => $cfg ) {
		if ( in_array( $slug, $done, true ) ) {
			continue;
		}
		if ( ! get_page_by_path( $slug, OBJECT, 'post' ) ) {
			$id = wp_insert_post( array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $cfg['title'],
				'post_name'    => $slug,
				'post_excerpt' => $cfg['excerpt'],
				'post_content' => $cfg['content'],
				'post_author'  => get_current_user_id(),
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ffnc_meta_description', $cfg['excerpt'] );
		}
		$done[] = $slug;
	}
	update_option( 'ffnc_guide_posts_created', $done, false );
	update_option( 'ffnc_guide_posts_version', FFNC_VERSION, false );
}
add_action( 'admin_init', 'ffnc_ensure_guide_posts', 30 );
