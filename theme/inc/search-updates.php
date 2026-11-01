<?php
/**
 * Updates driven by live Search Console queries (pulled 2026-11-01).
 *
 * 1. Titles and meta descriptions that use the words people search with
 *    ("calorie calculator", "calorie counter", "nutrition facts", "CFA",
 *    "McD", "Cal-O-Meter"). They override Rank Math and the theme's own
 *    title on the listed pages only. An editor can still change a page's
 *    title in Rank Math by removing its entry here.
 * 2. Extra sections on a few pages, answering the questions behind those
 *    queries. Every number is read from the brand datasets, so it follows
 *    the data when it is re-checked.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Path of the current singular page or post (pages with parents, e.g.
 * "calculators/chipotle-nutrition-calculator"; posts by slug).
 *
 * @return string
 */
function ffnc_su_current_path() {
	if ( ! is_singular() ) {
		return '';
	}
	$id = get_queried_object_id();
	if ( ! $id ) {
		return '';
	}
	return 'page' === get_post_type( $id ) ? (string) get_page_uri( $id ) : (string) get_post_field( 'post_name', $id );
}

/**
 * Search titles and descriptions per page path.
 *
 * @return array<string,array{title:string,desc:string}>
 */
function ffnc_su_seo_map() {
	return array(
		'calculators/panda-express-nutrition-calculator' => array(
			'title' => 'Panda Express Calorie Calculator & Nutrition Counter',
			'desc'  => 'Free Panda Express calorie calculator and nutrition counter. Build a bowl, plate or bigger plate and see calories, protein, carbs, fat and sodium from official U.S. data.',
		),
		'calculators/mcdonalds-calories-calculator' => array(
			'title' => "McDonald's Nutrition & Calorie Calculator (McD Macros)",
			'desc'  => "Free McDonald's nutrition and calorie calculator. Add burgers, fries, drinks and sauces and see calories, protein, carbs, fat and sodium (macros) from official U.S. data.",
		),
		'calculators/chick-fil-a-nutrition-calculator' => array(
			'title' => 'Chick-fil-A (CFA) Calorie & Nutrition Calculator',
			'desc'  => 'Free Chick-fil-A (CFA) calorie and nutrition calculator. Add sandwiches, nuggets, fries and sauces and see calories, protein, carbs, fat and sodium from official U.S. data.',
		),
		'calculators/chipotle-nutrition-calculator' => array(
			'title' => 'Chipotle Nutrition & Calorie Calculator: Macros, Protein',
			'desc'  => 'Build your Chipotle bowl, burrito or salad and count calories, protein, carbs, fat and sodium for every scoop. Free Chipotle macro calculator from official U.S. data.',
		),
		'calculators/subway-nutrition-calculator' => array(
			'title' => 'Subway Nutrition & Calorie Calculator: Build Your Sub',
			'desc'  => 'Free Subway nutrition and calorie calculator. Build a 6-inch or footlong sub with bread, cheese and sauces and see calories, protein, carbs, fat and sodium.',
		),
		'calculators/five-guys-nutrition-calculator' => array(
			'title' => 'Five Guys Nutrition Facts & Calorie Calculator',
			'desc'  => 'Five Guys nutrition facts for every burger, topping, fries size and shake, plus a free calorie calculator that adds up your order from official U.S. data.',
		),
		'calculators/panera-bread-nutrition-calculator' => array(
			'title' => 'Panera Nutrition Facts & Calorie Calculator',
			'desc'  => 'Panera Bread nutrition facts for soups, salads, sandwiches, bagels and bowls, plus a free calorie calculator that adds up your order from official U.S. data.',
		),
		'calculators/pizza-hut-nutrition-calculator' => array(
			'title' => 'Pizza Hut Nutrition Facts & Calorie Calculator',
			'desc'  => 'Pizza Hut nutritional information per slice for every crust and size, plus wings, sides and desserts, with a free calorie calculator for your whole order.',
		),
		'calculators/dominos-calories-calculator' => array(
			'title' => "Domino's Calorie Calculator (Cal-O-Meter Alternative)",
			'desc'  => "Free Domino's calorie calculator: add pizza slices by crust, cheesy bread, wings and desserts and see calories, protein, carbs, fat and sodium from official data.",
		),
		'calculators/kfc-calories-calculator' => array(
			'title' => 'KFC Nutrition & Calorie Calculator: Chicken, Bowls, Sides',
			'desc'  => 'Free KFC nutrition and calorie calculator. Add chicken by the piece, Famous Bowls, sandwiches and sides and see calories, protein, carbs, fat and sodium.',
		),
		'calculators/taco-bell-nutrition-calculator' => array(
			'title' => 'Taco Bell Nutrition & Calorie Calculator',
			'desc'  => 'Free Taco Bell nutrition and calorie calculator. Add tacos, burritos, bowls and drinks and see calories, protein, carbs, fat and sodium from official U.S. data.',
		),
	);
}

/**
 * @return array{title:string,desc:string}|null
 */
function ffnc_su_seo_for_current() {
	$map  = ffnc_su_seo_map();
	$path = ffnc_su_current_path();
	return isset( $map[ $path ] ) ? $map[ $path ] : null;
}

add_filter( 'rank_math/frontend/title', function ( $title ) {
	$seo = ffnc_su_seo_for_current();
	return $seo ? $seo['title'] : $title;
}, 99 );
add_filter( 'rank_math/frontend/description', function ( $desc ) {
	$seo = ffnc_su_seo_for_current();
	return $seo ? $seo['desc'] : $desc;
}, 99 );
add_filter( 'rank_math/opengraph/facebook/og_title', function ( $title ) {
	$seo = ffnc_su_seo_for_current();
	return $seo ? $seo['title'] : $title;
}, 99 );
add_filter( 'rank_math/opengraph/facebook/og_description', function ( $desc ) {
	$seo = ffnc_su_seo_for_current();
	return $seo ? $seo['desc'] : $desc;
}, 99 );
// Theme's own head output (used when no SEO plugin is active).
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( function_exists( 'ffnc_seo_plugin_active' ) && ffnc_seo_plugin_active() ) {
		return $title;
	}
	$seo = ffnc_su_seo_for_current();
	return $seo ? $seo['title'] : $title;
}, 99 );

/* -------------------------------------------------------------------------
 * Extra sections
 * ---------------------------------------------------------------------- */

/** Number from a dataset item, formatted ("" when missing). */
function ffnc_su_v( $brand, $item_id, $field = 'calories' ) {
	$item = ffnc_get_brand_item( $brand, $item_id );
	return $item && isset( $item[ $field ] ) && is_numeric( $item[ $field ] ) ? ffnc_fmt_num( (float) $item[ $field ] ) : '';
}

/** Sum of fields over several items of one brand. */
function ffnc_su_sum( $brand, $ids ) {
	$t = array( 'calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0, 'sodium' => 0 );
	foreach ( $ids as $id ) {
		$item = ffnc_get_brand_item( $brand, $id );
		if ( ! $item ) {
			return null;
		}
		foreach ( $t as $k => $v ) {
			$t[ $k ] += isset( $item[ $k ] ) && is_numeric( $item[ $k ] ) ? (float) $item[ $k ] : 0;
		}
	}
	return array_map( 'ffnc_fmt_num', $t );
}

function ffnc_su_link( $path, $text ) {
	$url = ffnc_page_url( $path );
	return $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' : esc_html( $text );
}

function ffnc_su_section_chipotle() {
	$need = array( 'chicken', 'steak', 'barbacoa', 'carnitas', 'sofritas', 'flour-tortilla-burrito', 'white-rice', 'black-beans', 'supergreens', 'fajita-veggies', 'tomatillo-green-salsa', 'cheese' );
	foreach ( $need as $id ) {
		if ( ! ffnc_get_brand_item( 'chipotle', $id ) ) {
			return '';
		}
	}
	$low  = ffnc_su_sum( 'chipotle', array( 'supergreens', 'chicken', 'fajita-veggies', 'tomatillo-green-salsa', 'cheese' ) );
	$rows = '';
	foreach ( array( 'chicken', 'steak', 'barbacoa', 'carnitas', 'sofritas' ) as $id ) {
		$item  = ffnc_get_brand_item( 'chipotle', $id );
		$rows .= '<li>' . esc_html( $item['name'] ) . ': ' . esc_html( ffnc_su_v( 'chipotle', $id, 'protein' ) ) . 'g protein, ' . esc_html( ffnc_su_v( 'chipotle', $id ) ) . ' calories, ' . esc_html( ffnc_su_v( 'chipotle', $id, 'carbs' ) ) . 'g carbs</li>';
	}
	$h  = '<h2>Chipotle Macro Calculator: How to Count a Bowl</h2>';
	$h .= '<p>Chipotle publishes nutrition for each ingredient, so a bowl or burrito is the sum of every scoop. This is how to count your macros (protein, carbs and fat) with the calculator above:</p>';
	$h .= '<ol>';
	$h .= '<li>Pick the base. A burrito adds the flour tortilla (' . esc_html( ffnc_su_v( 'chipotle', 'flour-tortilla-burrito' ) ) . ' calories, ' . esc_html( ffnc_su_v( 'chipotle', 'flour-tortilla-burrito', 'carbs' ) ) . 'g carbs). A bowl has no tortilla, and a salad starts with Supergreens (' . esc_html( ffnc_su_v( 'chipotle', 'supergreens' ) ) . ' calories).</li>';
	$h .= '<li>Add rice and beans. A standard scoop of white rice is ' . esc_html( ffnc_su_v( 'chipotle', 'white-rice' ) ) . ' calories with ' . esc_html( ffnc_su_v( 'chipotle', 'white-rice', 'carbs' ) ) . 'g carbs; black beans are ' . esc_html( ffnc_su_v( 'chipotle', 'black-beans' ) ) . ' calories with ' . esc_html( ffnc_su_v( 'chipotle', 'black-beans', 'protein' ) ) . 'g protein.</li>';
	$h .= '<li>Add one protein (4 oz). Ask for double and add it twice.</li>';
	$h .= '<li>Add salsas and toppings. Salsas are light; cheese, sour cream, queso and guacamole are where calories and fat add up.</li>';
	$h .= '</ol>';
	$h .= '<h3>How much protein is in each Chipotle protein?</h3><ul>' . $rows . '</ul>';
	if ( $low ) {
		$h .= '<h3>A lower-carb Chipotle bowl</h3>';
		$h .= '<p>A salad bowl of Supergreens, chicken, fajita vegetables, tomatillo-green chili salsa and cheese comes to ' . esc_html( $low['calories'] ) . ' calories, ' . esc_html( $low['protein'] ) . 'g protein and ' . esc_html( $low['carbs'] ) . 'g carbs. Rice and a tortilla are the two biggest carb sources on the menu, so leaving them out cuts carbs the most.</p>';
	}
	$h .= '<p>Looking for more options like this? See ' . ffnc_su_link( 'high-protein-fast-food', 'high-protein fast food' ) . ' and ' . ffnc_su_link( 'low-carb-fast-food', 'low-carb fast food' ) . ', or compare with the ' . ffnc_su_link( 'calculators/qdoba-nutrition-calculator', 'QDOBA nutrition calculator' ) . '.</p>';
	return $h;
}

function ffnc_su_section_dominos() {
	if ( ! ffnc_get_brand_item( 'dominos', 'cheese-slice' ) || ! ffnc_get_brand_item( 'dominos', 'cheesy-bread-1' ) ) {
		return '';
	}
	$h  = '<h2>Looking for Domino&#8217;s Cal-O-Meter?</h2>';
	$h .= '<p>Domino&#8217;s own nutrition tool on its website is called the Cal-O-Meter. This calculator does the same job in one place: pick the crust and number of slices, add sides, wings, dips and desserts, and the total for calories, protein, carbs, fat and sodium updates as you go. It is independent and not made by Domino&#8217;s; the values come from Domino&#8217;s published U.S. nutrition information.</p>';
	$h .= '<ul>';
	$h .= '<li>Cheese Pizza, medium Hand Tossed: ' . esc_html( ffnc_su_v( 'dominos', 'cheese-slice' ) ) . ' calories and ' . esc_html( ffnc_su_v( 'dominos', 'cheese-slice', 'carbs' ) ) . 'g carbs per slice.</li>';
	if ( ffnc_get_brand_item( 'dominos', 'thin-cheese-slice' ) ) {
		$h .= '<li>Cheese Pizza, Crunchy Thin: ' . esc_html( ffnc_su_v( 'dominos', 'thin-cheese-slice' ) ) . ' calories and ' . esc_html( ffnc_su_v( 'dominos', 'thin-cheese-slice', 'carbs' ) ) . 'g carbs per slice.</li>';
	}
	$h .= '<li>Stuffed Cheesy Bread: ' . esc_html( ffnc_su_v( 'dominos', 'cheesy-bread-1' ) ) . ' calories per piece.</li>';
	if ( ffnc_get_brand_item( 'dominos', 'plain-wings-4' ) ) {
		$h .= '<li>Plain Wings, no sauce: ' . esc_html( ffnc_su_v( 'dominos', 'plain-wings-4' ) ) . ' calories for 4 pieces.</li>';
	}
	$h .= '</ul>';
	return $h;
}

function ffnc_su_section_kfc() {
	$ids = array( 'or-thigh', 'ec-thigh', 'sc-thigh', 'kgc-thigh', 'famous-bowl' );
	foreach ( $ids as $id ) {
		if ( ! ffnc_get_brand_item( 'kfc', $id ) ) {
			return '';
		}
	}
	$h  = '<h2>How Many Calories Are in a KFC Chicken Thigh?</h2>';
	$h .= '<p>It depends on the recipe. Per thigh, from KFC&#8217;s official U.S. nutrition guide:</p><ul>';
	foreach ( array( 'or-thigh', 'ec-thigh', 'sc-thigh', 'kgc-thigh' ) as $id ) {
		$item = ffnc_get_brand_item( 'kfc', $id );
		$h   .= '<li>' . ffnc_item_link( 'kfc', $item ) . ': ' . esc_html( ffnc_su_v( 'kfc', $id ) ) . ' calories, ' . esc_html( ffnc_su_v( 'kfc', $id, 'protein' ) ) . 'g protein, ' . esc_html( ffnc_su_v( 'kfc', $id, 'carbs' ) ) . 'g carbs, ' . esc_html( ffnc_su_v( 'kfc', $id, 'sodium' ) ) . 'mg sodium</li>';
	}
	$h .= '</ul>';
	$bowl = ffnc_get_brand_item( 'kfc', 'famous-bowl' );
	$h .= '<h2>How Many Calories Are in a KFC Famous Bowl?</h2>';
	$h .= '<p>The ' . ffnc_item_link( 'kfc', $bowl, 'Famous Bowl' ) . ' (mashed potatoes, gravy, corn, chicken and cheese) is ' . esc_html( ffnc_su_v( 'kfc', 'famous-bowl' ) ) . ' calories, with ' . esc_html( ffnc_su_v( 'kfc', 'famous-bowl', 'protein' ) ) . 'g protein, ' . esc_html( ffnc_su_v( 'kfc', 'famous-bowl', 'carbs' ) ) . 'g carbs and ' . esc_html( ffnc_su_v( 'kfc', 'famous-bowl', 'sodium' ) ) . 'mg sodium.</p>';
	return $h;
}

/** One row per chain: its lowest-sodium main item (200+ calories). */
function ffnc_su_lowest_sodium_rows() {
	$best = array();
	foreach ( ffnc_all_items() as $x ) {
		$lunch = array( 'burger', 'chicken-sandwich', 'nuggets', 'pizza', 'sub', 'mexican', 'chicken-piece', 'wings', 'entree' );
		if ( ! in_array( $x['type'], $lunch, true ) || preg_match( '/family|bucket/i', $x['item']['name'] ) ) {
			continue;
		}
		$cal = ffnc_num( $x['item'], 'calories' );
		$na  = ffnc_num( $x['item'], 'sodium' );
		if ( null === $cal || null === $na || $cal < 200 || ffnc_num( $x['item'], 'protein' ) < 15 ) {
			continue;
		}
		if ( ! isset( $best[ $x['brand'] ] ) || $na < ffnc_num( $best[ $x['brand'] ]['item'], 'sodium' ) ) {
			$best[ $x['brand'] ] = $x;
		}
	}
	$rows = array_values( $best );
	usort( $rows, function ( $a, $b ) {
		return ffnc_num( $a['item'], 'sodium' ) <=> ffnc_num( $b['item'], 'sodium' );
	} );
	return $rows;
}

/** Burgers ordered by sodium, lowest first. */
function ffnc_su_burger_rows( $limit = 8 ) {
	$rows = array();
	foreach ( ffnc_all_items() as $x ) {
		if ( 'burger' === $x['type'] && null !== ffnc_num( $x['item'], 'sodium' ) && ffnc_num( $x['item'], 'calories' ) >= 200 ) {
			$rows[] = $x;
		}
	}
	usort( $rows, function ( $a, $b ) {
		return ffnc_num( $a['item'], 'sodium' ) <=> ffnc_num( $b['item'], 'sodium' );
	} );
	return array_slice( $rows, 0, $limit );
}

function ffnc_su_section_low_sodium() {
	$h    = '';
	$rows = ffnc_su_lowest_sodium_rows();
	if ( $rows ) {
		$h .= '<h2>Best Low-Sodium Pick at Each Fast Food Chain</h2>';
		$h .= '<p>The lunch or dinner main with the least sodium at each chain, lowest first. Only mains with at least 200 calories and 15g of protein are counted, so single pieces, snacks and family sizes are left out.</p>';
		$h .= ffnc_render_item_rows_table( $rows, __( 'Lowest-sodium main item by chain', 'ffnc' ) );
	}
	$burgers = ffnc_su_burger_rows();
	if ( $burgers ) {
		$h .= '<h2>Lower-Sodium Fast Food Burgers</h2>';
		$h .= '<p>Burgers are some of the saltiest mains because the bun, cheese, pickles and sauce all add sodium. These have the least, as served:</p>';
		$h .= ffnc_render_item_rows_table( $burgers, __( 'Burgers with the least sodium', 'ffnc' ) );
		$h .= '<p>Skipping cheese, bacon and pickles lowers sodium further, but restaurants do not publish values for those changes, so they are not counted here.</p>';
	}
	if ( ffnc_get_brand_item( 'kfc', 'kgc-drumstick' ) && ffnc_get_brand_item( 'kfc', 'or-breast' ) ) {
		$h .= '<h2>Low-Sodium Options at KFC</h2>';
		$h .= '<p>Kentucky Grilled Chicken has far less sodium than fried chicken: a grilled drumstick is ' . esc_html( ffnc_su_v( 'kfc', 'kgc-drumstick', 'sodium' ) ) . 'mg and a grilled breast ' . esc_html( ffnc_su_v( 'kfc', 'kgc-breast', 'sodium' ) ) . 'mg, against ' . esc_html( ffnc_su_v( 'kfc', 'or-breast', 'sodium' ) ) . 'mg for an Original Recipe breast. For sides, corn on the cob (' . esc_html( ffnc_su_v( 'kfc', 'corn-on-the-cob', 'sodium' ) ) . 'mg) and coleslaw (' . esc_html( ffnc_su_v( 'kfc', 'coleslaw', 'sodium' ) ) . 'mg) are the lowest; mashed potatoes with gravy are ' . esc_html( ffnc_su_v( 'kfc', 'mashed-potatoes-gravy', 'sodium' ) ) . 'mg. Ask your local KFC whether grilled chicken is on the menu there.</p>';
	}
	return $h;
}

function ffnc_su_section_840() {
	$h  = '<h2>Is 840 Calories a Lot for One Meal?</h2>';
	$h .= '<p>For many adults it is a large meal, but not an unusual one. On the 2,000-calorie reference used for U.S. nutrition labels, 840 calories is 42% of the day. Eating three meals that size would come to 2,520 calories before snacks and drinks, which is more than many adults need to maintain their weight. For a very active person on 3,000 calories a day, 840 is close to an even share of three meals.</p>';
	$h .= '<p>At a fast food restaurant, 840 calories is easy to reach: a burger, medium fries and a regular soda often land between 900 and 1,200. What matters more than one meal is the total for the day and what the meal gives you, such as protein and fiber, against how much sodium and saturated fat it has. You can add up your own order with our ' . ffnc_su_link( 'calculators', 'fast food calorie calculators' ) . ' or see ' . ffnc_su_link( 'fast-food-under-500-calories', 'fast food under 500 calories' ) . ' for lighter meals.</p>';
	$h .= '<p>This is general information, not personal advice. A registered dietitian or your doctor can give you a calorie target that fits you.</p>';
	return $h;
}

/**
 * Section HTML per page path.
 *
 * @return array<string,callable>
 */
function ffnc_su_sections() {
	return array(
		'calculators/chipotle-nutrition-calculator' => 'ffnc_su_section_chipotle',
		'calculators/dominos-calories-calculator'   => 'ffnc_su_section_dominos',
		'calculators/kfc-calories-calculator'       => 'ffnc_su_section_kfc',
		'low-sodium-fast-food'                      => 'ffnc_su_section_low_sodium',
		'how-many-calories-should-you-eat-in-a-day' => 'ffnc_su_section_840',
	);
}

/**
 * Insert the page's section before its FAQ (or Sources) heading, or at the
 * end when there is neither. Runs after shortcodes (11) and before the
 * calculator page extras (19/20).
 */
function ffnc_su_insert_sections( $content ) {
	if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$sections = ffnc_su_sections();
	$path     = ffnc_su_current_path();
	if ( ! isset( $sections[ $path ] ) || false !== strpos( $content, 'ffnc-search-section' ) ) {
		return $content;
	}
	$html = call_user_func( $sections[ $path ] );
	if ( '' === $html ) {
		return $content;
	}
	$html = '<div class="ffnc-search-section">' . $html . '</div>';
	if ( preg_match( '/<h2[^>]*>\s*(Frequently Asked Questions|FAQ|Sources)/i', $content, $m, PREG_OFFSET_CAPTURE ) ) {
		return substr( $content, 0, $m[0][1] ) . $html . "\n" . substr( $content, $m[0][1] );
	}
	return $content . $html;
}
add_filter( 'the_content', 'ffnc_su_insert_sections', 15 );
