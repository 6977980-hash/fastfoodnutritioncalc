<?php
/**
 * Calculator pages for brands added after launch.
 *
 * The first 15 calculator pages were written by hand in WordPress. Brands
 * added later get their page from here: it is created once, published under
 * /calculators/, when an editor opens wp-admin. Numbers in the text are
 * [ff_val] shortcodes, so they follow the dataset when it is re-checked.
 * A page created once is never re-created, so deleting one in WordPress
 * keeps it deleted; edit the live page in WordPress after that.
 *
 * @package FastFoodNutritionCalculators
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page title, SEO fields and content per brand slug.
 *
 * @return array
 */
function ffnc_brand_page_posts() {
	$v = function ( $item, $field = 'calories' ) {
		return '[ff_val item="' . $item . '" field="' . $field . '"]';
	};
	$calc = function ( $slug, $text ) {
		$b = ffnc_get_brand( $slug );
		return '<a href="' . ( $b ? $b['url'] : '/calculators/' ) . '">' . $text . '</a>';
	};
	return array(
		'popeyes' => array(
			'title'     => 'Popeyes Nutrition Calculator',
			'seo_title' => 'Popeyes Nutrition Calculator: Calories in Chicken, Tenders & Sides',
			'excerpt'   => 'Add up calories, protein, carbs, fat and sodium for Popeyes chicken, tenders, sandwiches, sides and drinks, from Popeyes\' official U.S. nutrition guide.',
			'content'   => "<p>Popeyes is known for Louisiana-style fried chicken, and the calories change a lot with the piece, the side and the sauce. This Popeyes nutrition calculator covers the U.S. menu from Popeyes' official nutrition guide: chicken by the piece, tenders, wings, sandwiches, wraps, sides, breakfast biscuits, drinks and desserts.</p>\n\n[ff_calculator brand=\"popeyes\"]\n\n[ff_disclaimer_note brand=\"Popeyes\"]\n\n[ff_toc]\n\n<h2>How to Use This Popeyes Calculator</h2>\n<p>Add chicken by the piece (breast, thigh, leg or wing; Classic and Spicy have the same values in Popeyes' guide), or pick tenders, wings, a sandwich or a wrap. Sides come in Regular and Large. Dipping sauces are add-ons on chicken, tenders and shrimp, so every sauce cup is counted in your total.</p>\n\n<h2>Popeyes Nutrition Facts, by Category</h2>\n[ff_nutrition_table brand=\"popeyes\"]\n\n<h2>How Many Calories Are in a Popeyes Chicken Sandwich?</h2>\n<p>The Classic Chicken Sandwich is " . $v( 'popeyes:chicken-sandwich-classic' ) . ' calories, with ' . $v( 'popeyes:chicken-sandwich-classic', 'protein' ) . 'g of protein, ' . $v( 'popeyes:chicken-sandwich-classic', 'fat' ) . 'g of fat and ' . $v( 'popeyes:chicken-sandwich-classic', 'sodium' ) . 'mg of sodium. The Spicy Chicken Sandwich has the same calories and ' . $v( 'popeyes:chicken-sandwich-spicy', 'sodium' ) . 'mg of sodium. For a smaller sandwich, a Classic Tender Wrap is ' . $v( 'popeyes:classic-tender-wrap' ) . " calories.</p>\n\n<h2>Which Popeyes Chicken Is Lowest in Calories?</h2>\n<p>Blackened Tenders are not breaded, so they have far fewer calories than the fried tenders: 3 pieces are " . $v( 'popeyes:blackened-tenders-3' ) . ' calories with ' . $v( 'popeyes:blackened-tenders-3', 'protein' ) . 'g of protein, against ' . $v( 'popeyes:tenders-3' ) . ' calories for 3 Classic or Spicy Tenders. Among bone-in pieces, a leg is ' . $v( 'popeyes:chicken-leg' ) . ' calories and a breast ' . $v( 'popeyes:chicken-breast' ) . ' calories with ' . $v( 'popeyes:chicken-breast', 'protein' ) . "g of protein.</p>\n\n<h2>Popeyes Sides: Regular or Large?</h2>\n<p>A Large side is close to three times a Regular. Regular Cajun Fries are " . $v( 'popeyes:cajun-fries-regular' ) . ' calories and Large ' . $v( 'popeyes:cajun-fries-large' ) . '; Regular Homestyle Mac &amp; Cheese is ' . $v( 'popeyes:mac-cheese-regular' ) . ' and Large ' . $v( 'popeyes:mac-cheese-large' ) . '. Regular Mashed Potatoes with Cajun Gravy (' . $v( 'popeyes:mashed-potatoes-regular' ) . ' calories) is the lowest-calorie of the main sides, and a Biscuit adds ' . $v( 'popeyes:biscuit' ) . " calories.</p>\n\n<h2>Frequently Asked Questions About Popeyes Nutrition</h2>\n[ff_faq]\n[ff_faq_item question=\"How many calories are in a Popeyes chicken breast?\"]A Classic or Spicy chicken breast is " . $v( 'popeyes:chicken-breast' ) . ' calories, with ' . $v( 'popeyes:chicken-breast', 'protein' ) . 'g of protein, ' . $v( 'popeyes:chicken-breast', 'fat' ) . 'g of fat and ' . $v( 'popeyes:chicken-breast', 'sodium' ) . "mg of sodium.[/ff_faq_item]\n[ff_faq_item question=\"How many calories are in 3 Popeyes tenders?\"]Three Classic or Spicy Tenders are " . $v( 'popeyes:tenders-3' ) . ' calories with ' . $v( 'popeyes:tenders-3', 'protein' ) . 'g of protein. Three Blackened Tenders are ' . $v( 'popeyes:blackened-tenders-3' ) . " calories. Dipping sauces are extra.[/ff_faq_item]\n[ff_faq_item question=\"Do Popeyes Classic and Spicy chicken have the same calories?\"]Yes. Popeyes lists its bone-in chicken and tenders once for Classic or Spicy. The Classic and Spicy Chicken Sandwiches are both " . $v( 'popeyes:chicken-sandwich-classic' ) . " calories; the spicy one has slightly more sodium.[/ff_faq_item]\n[ff_faq_item question=\"Where does this Popeyes nutrition data come from?\"]The figures come from Popeyes' official USA Nutrition Guide (September 2026), linked from popeyes.com, and were checked on October 25, 2026. Breakfast is sold only at some locations. Confirm current figures with Popeyes, especially for allergies or medical diets.[/ff_faq_item]\n[/ff_faq]\n\n<p>Comparing chicken chains? Try our " . $calc( 'chick-fil-a', 'Chick-fil-A nutrition calculator' ) . ' or ' . $calc( 'kfc', 'KFC calories calculator' ) . ', see <a href="/compare/chick-fil-a-vs-popeyes-chicken-sandwich/">Chick-fil-A vs Popeyes Chicken Sandwich</a>, or browse the <a href="/calculators/">full list of calculators</a>.</p>',
		),
		'qdoba'   => array(
			'title'     => 'QDOBA Nutrition Calculator',
			'seo_title' => 'QDOBA Nutrition Calculator: Build Your Burrito or Bowl',
			'excerpt'   => 'Build a QDOBA burrito, bowl or tacos and add up calories, protein, carbs, fat and sodium for every portion, from QDOBA\'s official U.S. nutrition facts.',
			'content'   => "<p>At QDOBA most people build their own burrito, bowl or tacos, so the calories depend on every scoop: the tortilla, rice, beans, protein, queso and guac. This QDOBA nutrition calculator lists each part by the portion QDOBA publishes, plus the Signature Eats, sides, desserts and drinks, from QDOBA's official U.S. nutrition facts.</p>\n\n[ff_calculator brand=\"qdoba\"]\n\n[ff_disclaimer_note brand=\"QDOBA\"]\n\n[ff_toc]\n\n<h2>How to Build Your QDOBA Order</h2>\n<p>For a burrito, add the 12.5-inch Flour Tortilla, then rice, beans, a protein and your toppings from the Build Your Own sections. For a bowl, skip the tortilla. For tacos, add one tortilla or taco shell per taco. Queso and guacamole come in 2 oz and 4 oz portions. Signature Eats such as the Chicken Queso Burrito are listed as QDOBA serves them.</p>\n\n<h2>QDOBA Nutrition Facts, by Category</h2>\n[ff_nutrition_table brand=\"qdoba\"]\n\n<h2>How Many Calories Are in a QDOBA Burrito?</h2>\n<p>It depends on what goes in. The 12.5-inch flour tortilla alone is " . $v( 'qdoba:flour-tortilla-12-5-inch-burrito' ) . ' calories. Add 4 oz of Cilantro Lime Rice (' . $v( 'qdoba:cilantro-lime-rice-4-oz' ) . '), 4 oz of Black Beans (' . $v( 'qdoba:black-beans-4-oz' ) . ') and Grilled Adobo Chicken (' . $v( 'qdoba:grilled-adobo-chicken-3-5-oz' ) . ') and you are at about 820 calories before queso, guac, cheese or sour cream. As served, the Chicken Queso Burrito is ' . $v( 'qdoba:chicken-queso-burrito' ) . " calories.</p>\n\n<h2>Lighter Ways to Order at QDOBA</h2>\n<p>A bowl instead of a burrito saves the tortilla's " . $v( 'qdoba:flour-tortilla-12-5-inch-burrito' ) . ' calories. The Keto Bowl with Chicken is ' . $v( 'qdoba:keto-bowl-chicken' ) . ' calories with ' . $v( 'qdoba:keto-bowl-chicken', 'protein' ) . 'g of protein. Salsas add very little: Pico de Gallo is ' . $v( 'qdoba:pico-de-gallo-1-oz' ) . ' calories per ounce, while 4 oz of Three Cheese Queso is ' . $v( 'qdoba:three-cheese-queso-4-oz' ) . ' and 4 oz of guacamole ' . $v( 'qdoba:hand-crafted-guacamole-4-oz' ) . ". Chips are the biggest extra: 4 oz of Tortilla Chips are " . $v( 'qdoba:tortilla-chips-4-oz' ) . " calories.</p>\n\n<h2>Frequently Asked Questions About QDOBA Nutrition</h2>\n[ff_faq]\n[ff_faq_item question=\"How many calories are in QDOBA chicken?\"]A 3.5 oz portion of Grilled Adobo Chicken is " . $v( 'qdoba:grilled-adobo-chicken-3-5-oz' ) . ' calories with ' . $v( 'qdoba:grilled-adobo-chicken-3-5-oz', 'protein' ) . 'g of protein and ' . $v( 'qdoba:grilled-adobo-chicken-3-5-oz', 'sodium' ) . "mg of sodium.[/ff_faq_item]\n[ff_faq_item question=\"How many calories do queso and guacamole add at QDOBA?\"]2 oz of Three Cheese Queso is " . $v( 'qdoba:three-cheese-queso-2-oz' ) . ' calories and 2 oz of Hand Crafted Guacamole is ' . $v( 'qdoba:hand-crafted-guacamole-2-oz' ) . " calories.[/ff_faq_item]\n[ff_faq_item question=\"What is the lowest-calorie protein at QDOBA?\"]Among the 3.5 oz proteins, Pork Carnitas has the fewest calories at " . $v( 'qdoba:pork-carnitas-3-5-oz' ) . ' calories per 3.5 oz, followed by Brisket Birria at ' . $v( 'qdoba:brisket-birria-3-5-oz' ) . ' and Ground Beef at ' . $v( 'qdoba:ground-beef-3-5-oz' ) . '. Grilled Steak is ' . $v( 'qdoba:grilled-steak-3-5-oz' ) . " calories.[/ff_faq_item]\n[ff_faq_item question=\"Where does this QDOBA nutrition data come from?\"]The figures come from QDOBA's official Nutrition Facts 2026 (dated September 1, 2026), linked from qdoba.com, and were checked on October 25, 2026. U.S. values only. Confirm current figures with QDOBA, especially for allergies or medical diets.[/ff_faq_item]\n[/ff_faq]\n\n<p>Comparing Mexican chains? Try our " . $calc( 'chipotle', 'Chipotle nutrition calculator' ) . ' or ' . $calc( 'taco-bell', 'Taco Bell nutrition calculator' ) . ', or browse the <a href="/calculators/">full list of calculators</a>.</p>',
		),
		'starbucks' => array(
			'title'     => 'Starbucks Nutrition Calculator',
			'seo_title' => 'Starbucks Nutrition Calculator: Calories in Lattes, Frappuccinos & Food',
			'excerpt'   => 'Calories, sugar, protein, carbs, fat and sodium for Starbucks drinks in Tall, Grande and Venti, plus breakfast and bakery items, from Starbucks\' official U.S. menu.',
			'content'   => "<p>At Starbucks the size and the drink type change the calories far more than most people expect: a Grande Caffè Americano is " . $v( 'starbucks:caffe-americano-grande' ) . ' calories, while a Grande Pumpkin Spice Latte is ' . $v( 'starbucks:pumpkin-spice-latte-grande' ) . ". This Starbucks nutrition calculator covers the U.S. menu from the official nutrition on starbucks.com: hot and iced coffee, lattes, protein drinks, chai and matcha, tea, Refreshers, Frappuccinos and food.</p>\n\n[ff_calculator brand=\"starbucks\"]\n\n[ff_disclaimer_note brand=\"Starbucks\"]\n\n[ff_toc]\n\n<h2>How to Use This Starbucks Calculator</h2>\n<p>Pick your drink in the size you order (Tall is 12 oz, Grande 16 oz and Venti 20 oz hot or 24 oz iced), then add food. Every drink is the standard recipe: for most lattes that means 2% milk and the default number of syrup pumps, with the whipped cream or topping the drink normally comes with. Starbucks does not publish separate values for each milk, syrup pump or topping, so a customized drink will differ from these numbers.</p>\n\n<h2>Starbucks Nutrition Facts, by Category</h2>\n[ff_nutrition_table brand=\"starbucks\"]\n\n<h2>How Many Calories Are in a Starbucks Latte?</h2>\n<p>A Caffè Latte with 2% milk is " . $v( 'starbucks:caffe-latte-tall' ) . ' calories as a Tall, ' . $v( 'starbucks:caffe-latte-grande' ) . ' as a Grande and ' . $v( 'starbucks:caffe-latte-venti' ) . ' as a Venti. The Grande has ' . $v( 'starbucks:caffe-latte-grande', 'protein' ) . 'g of protein and ' . $v( 'starbucks:caffe-latte-grande', 'sugar' ) . 'g of sugar, all of it from the milk. An Iced Caffè Latte has less milk because of the ice: ' . $v( 'starbucks:iced-caffe-latte-grande' ) . ' calories for a Grande. Flavored lattes add syrup: a Grande Caramel Macchiato is ' . $v( 'starbucks:caramel-macchiato-grande' ) . ' calories and a Grande White Chocolate Mocha ' . $v( 'starbucks:white-chocolate-mocha-grande' ) . ".</p>\n\n<h2>Lowest-Calorie Starbucks Drinks</h2>\n<p>Black coffee and espresso are almost free: a Grande Pike Place Roast is " . $v( 'starbucks:medium-roast-pike-place-roast-grande' ) . ' calories, a Grande Cold Brew ' . $v( 'starbucks:cold-brew-grande' ) . ' and a Doppio espresso ' . $v( 'starbucks:espresso-doppio' ) . '. Unsweetened brewed and iced teas are also close to zero. Among milk drinks, a Grande Cappuccino is ' . $v( 'starbucks:cappuccino-grande' ) . ' calories, and a Grande Strawberry Açaí Refresher is ' . $v( 'starbucks:strawberry-acai-refresher-grande' ) . ".</p>\n\n<h2>Most Protein at Starbucks</h2>\n<p>The protein lattes add protein powder to the milk. A Grande Vanilla Protein Latte has " . $v( 'starbucks:vanilla-protein-latte-grande', 'protein' ) . 'g of protein in ' . $v( 'starbucks:vanilla-protein-latte-grande' ) . ' calories, and the Sugar-Free version has ' . $v( 'starbucks:sugar-free-vanilla-protein-latte-grande', 'protein' ) . 'g in ' . $v( 'starbucks:sugar-free-vanilla-protein-latte-grande' ) . ' calories. For food, the Spinach, Feta &amp; Egg White Wrap has ' . $v( 'starbucks:spinach-feta-and-egg-white-wrap', 'protein' ) . 'g of protein in ' . $v( 'starbucks:spinach-feta-and-egg-white-wrap' ) . ' calories, and the Egg White &amp; Roasted Red Pepper Egg Bites are ' . $v( 'starbucks:egg-white-and-roasted-red-pepper-egg-bites' ) . " calories.</p>\n\n<h2>Frequently Asked Questions About Starbucks Nutrition</h2>\n[ff_faq]\n[ff_faq_item question=\"How many calories are in a Grande Pumpkin Spice Latte?\"]A Grande Pumpkin Spice Latte with 2% milk and whipped cream is " . $v( 'starbucks:pumpkin-spice-latte-grande' ) . ' calories, with ' . $v( 'starbucks:pumpkin-spice-latte-grande', 'sugar' ) . 'g of sugar and ' . $v( 'starbucks:pumpkin-spice-latte-grande', 'protein' ) . "g of protein. It is a seasonal drink.[/ff_faq_item]\n[ff_faq_item question=\"How many calories are in a Pink Drink?\"]A Grande Pink Drink is " . $v( 'starbucks:pink-drink-grande' ) . ' calories with ' . $v( 'starbucks:pink-drink-grande', 'sugar' ) . "g of sugar.[/ff_faq_item]\n[ff_faq_item question=\"How many calories are in a Caramel Frappuccino?\"]A Caramel Frappuccino Blended Beverage with whipped cream is " . $v( 'starbucks:caramel-frappuccino-blended-beverage-tall' ) . ' calories as a Tall, ' . $v( 'starbucks:caramel-frappuccino-blended-beverage-grande' ) . ' as a Grande and ' . $v( 'starbucks:caramel-frappuccino-blended-beverage-venti' ) . " as a Venti.[/ff_faq_item]\n[ff_faq_item question=\"Can I see the calories for oat milk or extra syrup?\"]Not here. Starbucks publishes nutrition for each drink as it is normally made, not for each change, and we only use official numbers. The Starbucks app shows the values for a customized drink when you build it there.[/ff_faq_item]\n[ff_faq_item question=\"Where does this Starbucks nutrition data come from?\"]Every value comes from the nutrition panel of the product on starbucks.com (U.S. menu), checked on October 25, 2026. Each item page links to its own Starbucks product page. Seasonal drinks come and go, so confirm current figures with Starbucks, especially for allergies or medical diets.[/ff_faq_item]\n[/ff_faq]\n\n<p>Getting coffee somewhere else? Try our " . $calc( 'dunkin', 'Dunkin\' calories calculator' ) . ' or ' . $calc( 'mcdonalds', 'McDonald\'s calories calculator' ) . ', or browse the <a href="/calculators/">full list of calculators</a>.</p>',
		),
	);
}

/**
 * Create each brand's calculator page once, published under /calculators/.
 */
function ffnc_ensure_brand_pages() {
	if ( ! current_user_can( 'publish_pages' ) || wp_doing_ajax() || get_option( 'ffnc_brand_pages_version' ) === FFNC_VERSION ) {
		return;
	}
	$done   = (array) get_option( 'ffnc_brand_pages_created', array() );
	$parent = get_page_by_path( 'calculators', OBJECT, 'page' );
	foreach ( ffnc_brand_page_posts() as $slug => $cfg ) {
		$brand = ffnc_get_brand( $slug );
		if ( in_array( $slug, $done, true ) || ! $brand || ! ffnc_get_brand_data( $slug ) ) {
			continue;
		}
		$name = basename( untrailingslashit( $brand['url'] ) );
		if ( ! get_page_by_path( ( $parent ? get_page_uri( $parent ) . '/' : '' ) . $name, OBJECT, 'page' ) ) {
			$id = wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $cfg['title'],
				'post_name'    => $name,
				'post_parent'  => $parent ? $parent->ID : 0,
				'post_excerpt' => $cfg['excerpt'],
				'post_content' => $cfg['content'],
				'post_author'  => get_current_user_id(),
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ffnc_is_calculator_page', '1' );
			update_post_meta( $id, '_ffnc_seo_title', $cfg['seo_title'] );
			update_post_meta( $id, '_ffnc_meta_description', $cfg['excerpt'] );
		}
		$done[] = $slug;
	}
	update_option( 'ffnc_brand_pages_created', $done, false );
	update_option( 'ffnc_brand_pages_version', FFNC_VERSION, false );
}
add_action( 'admin_init', 'ffnc_ensure_brand_pages', 15 ); // Before the item pages that link to it.
