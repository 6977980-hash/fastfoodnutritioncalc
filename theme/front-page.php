<?php
/**
 * Homepage template.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();

$brands     = ffnc_get_brands();
$live       = ffnc_get_live_brands();
$live_count = count( $live );
$item_count = ffnc_count_all_items();
$method_url = ffnc_methodology_url();
$compare_url = ffnc_compare_url();
$hub_url    = ffnc_page_url( 'calculators' ) ? ffnc_page_url( 'calculators' ) : home_url( '/calculators/' );
$reviewed   = ffnc_get_recently_reviewed();
?>

<section class="ffnc-hero">
	<div class="ffnc-container ffnc-hero-inner">
		<h1><?php esc_html_e( 'Fast Food Nutrition Calculators for Your Favorite Restaurant Menus', 'ffnc' ); ?></h1>
		<p class="ffnc-hero-sub">
			<?php echo esc_html( sprintf( /* translators: 1 brands 2 items */ __( 'Build your order from %1$d U.S. fast food chains and see calories, protein, carbs, fat and sodium add up as you go — %2$d menu items and sizes, free, independent, with the data source shown on every calculator.', 'ffnc' ), $live_count, $item_count ) ); ?>
		</p>
		<div class="ffnc-hero-ctas">
			<a class="ffnc-btn ffnc-btn-primary" href="<?php echo esc_url( $hub_url ); ?>"><?php esc_html_e( 'Start Calculating', 'ffnc' ); ?></a>
			<a class="ffnc-btn ffnc-btn-ghost" href="#ffnc-how-it-works"><?php esc_html_e( 'How It Works', 'ffnc' ); ?></a>
		</div>
	</div>
</section>

<section class="ffnc-container ffnc-features" id="ffnc-how-it-works">
	<h2><?php esc_html_e( 'Why Use Our Fast Food Calorie Calculators?', 'ffnc' ); ?></h2>
	<div class="ffnc-feature-grid">
		<div class="ffnc-feature-card">
			<span class="ffnc-feature-icon" aria-hidden="true">🍟</span>
			<h3><?php esc_html_e( 'Real Menu Items', 'ffnc' ); ?></h3>
			<p><?php esc_html_e( 'Every calculator is organized the way the menu actually is — by category, with the items people search for most.', 'ffnc' ); ?></p>
		</div>
		<div class="ffnc-feature-card">
			<span class="ffnc-feature-icon" aria-hidden="true">📊</span>
			<h3><?php esc_html_e( 'Full Macro Breakdown', 'ffnc' ); ?></h3>
			<p><?php esc_html_e( 'See calories, protein, carbs, fat and sodium for each item and for your whole order as you build it.', 'ffnc' ); ?></p>
		</div>
		<div class="ffnc-feature-card">
			<span class="ffnc-feature-icon" aria-hidden="true">🧩</span>
			<h3><?php esc_html_e( 'Customizable Orders', 'ffnc' ); ?></h3>
			<p><?php esc_html_e( 'Add extras, sauces and swaps to see how they change your total — before you get to the counter.', 'ffnc' ); ?></p>
		</div>
		<div class="ffnc-feature-card">
			<span class="ffnc-feature-icon" aria-hidden="true">🔍</span>
			<h3><?php esc_html_e( 'Independent & Transparent', 'ffnc' ); ?></h3>
			<p><?php esc_html_e( 'We are not affiliated with any restaurant brand. Every calculator shows its market, the official source we check against, and its review status.', 'ffnc' ); ?></p>
		</div>
	</div>
</section>

<section class="ffnc-container ffnc-brands-section">
	<h2><?php esc_html_e( 'Supported Restaurant Brands', 'ffnc' ); ?></h2>
	<p><?php esc_html_e( 'Pick a brand below to open its calculator and full nutrition guide.', 'ffnc' ); ?></p>
	<div class="ffnc-brand-grid">
		<?php foreach ( $brands as $brand ) : ?>
			<?php if ( ! empty( $brand['live'] ) ) : ?>
				<a class="ffnc-brand-card" href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>">
					<?php echo ffnc_brand_badge_html( $brand, 'md' ); ?>
					<span class="ffnc-brand-name"><?php echo esc_html( $brand['name'] ); ?></span>
					<span class="ffnc-brand-cta"><?php esc_html_e( 'Calculate →', 'ffnc' ); ?></span>
				</a>
			<?php else : ?>
				<span class="ffnc-brand-card ffnc-brand-card-soon" aria-disabled="true">
					<?php echo ffnc_brand_badge_html( $brand, 'md' ); ?>
					<span class="ffnc-brand-name"><?php echo esc_html( $brand['name'] ); ?></span>
					<span class="ffnc-brand-cta ffnc-soon-tag"><?php esc_html_e( 'Coming Soon', 'ffnc' ); ?></span>
				</span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<p class="ffnc-brands-more"><a href="<?php echo esc_url( $hub_url ); ?>"><?php esc_html_e( 'View All Calculators', 'ffnc' ); ?></a></p>
</section>

<section class="ffnc-container ffnc-stats">
	<div class="ffnc-stats-grid">
		<div class="ffnc-stat"><span class="ffnc-stat-number"><?php echo esc_html( $live_count ); ?></span><span><?php esc_html_e( 'Restaurant Brands', 'ffnc' ); ?></span></div>
		<div class="ffnc-stat"><span class="ffnc-stat-number"><?php echo esc_html( number_format_i18n( $item_count ) ); ?></span><span><?php esc_html_e( 'Menu Items & Sizes', 'ffnc' ); ?></span></div>
		<div class="ffnc-stat"><span class="ffnc-stat-number">100%</span><span><?php esc_html_e( 'Free to Use', 'ffnc' ); ?></span></div>
	</div>
</section>

<section class="ffnc-container ffnc-intro-copy">
	<h2><?php esc_html_e( 'A Free Way to Check Fast Food Calories Before You Order', 'ffnc' ); ?></h2>
	<p><?php esc_html_e( "Menu boards rarely show the full nutrition picture, and adding up calories, protein, carbs and fat by hand for a whole order is tedious. Each calculator lets you tap through a restaurant's menu, add the items and extras you plan to order, and watch the total update instantly. Every calculator page also has a plain HTML nutrition table for the whole menu, so you can scan values without using the calculator at all.", 'ffnc' ); ?></p>
	<p><?php esc_html_e( 'This is an independent resource. We are not affiliated with, endorsed by, or sponsored by McDonald\'s, KFC, Subway or any other restaurant brand on this site, and nothing here is medical or dietary advice.', 'ffnc' ); ?></p>
</section>

<section class="ffnc-container ffnc-home-method" id="ffnc-data">
	<h2><?php esc_html_e( 'How We Source Nutrition Data', 'ffnc' ); ?></h2>
	<ul class="ffnc-method-points">
		<li><strong><?php esc_html_e( 'One dataset per restaurant.', 'ffnc' ); ?></strong> <?php esc_html_e( 'The calculator, the nutrition table and the comparison tool all read the same file, so numbers always match.', 'ffnc' ); ?></li>
		<li><strong><?php esc_html_e( 'Checked against the official source.', 'ffnc' ); ?></strong> <?php esc_html_e( 'Each calculator links to the restaurant’s own U.S. nutrition information and shows whether our data has been verified against it, and when.', 'ffnc' ); ?></li>
		<li><strong><?php esc_html_e( 'U.S. menus only.', 'ffnc' ); ?></strong> <?php esc_html_e( 'Recipes differ by country, so these values should not be used for restaurants outside the United States.', 'ffnc' ); ?></li>
		<li><strong><?php esc_html_e( 'Corrections welcome.', 'ffnc' ); ?></strong> <?php esc_html_e( 'If a number looks wrong you can report it; we review every report against the official source.', 'ffnc' ); ?></li>
	</ul>
	<?php if ( $reviewed ) : ?>
		<h3><?php esc_html_e( 'Recently reviewed data', 'ffnc' ); ?></h3>
		<ul class="ffnc-reviewed-list">
			<?php foreach ( array_slice( $reviewed, 0, 6, true ) as $ffnc_slug => $ffnc_meta ) : $ffnc_b = ffnc_get_brand( $ffnc_slug ); ?>
				<li><a href="<?php echo esc_url( home_url( $ffnc_b['url'] ) ); ?>"><?php echo esc_html( $ffnc_b['name'] ); ?></a> — <time datetime="<?php echo esc_attr( $ffnc_meta['last_reviewed'] ); ?>"><?php echo esc_html( ffnc_format_review_date( $ffnc_meta['last_reviewed'] ) ); ?></time></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<p>
		<?php if ( $method_url ) : ?><a href="<?php echo esc_url( $method_url ); ?>"><?php esc_html_e( 'Read our full data methodology', 'ffnc' ); ?></a> &middot; <?php endif; ?>
		<?php if ( ffnc_page_url( 'disclaimer' ) ) : ?><a href="<?php echo esc_url( ffnc_page_url( 'disclaimer' ) ); ?>"><?php esc_html_e( 'Disclaimer', 'ffnc' ); ?></a> &middot; <?php endif; ?>
		<?php if ( ffnc_page_url( 'about' ) ) : ?><a href="<?php echo esc_url( ffnc_page_url( 'about' ) ); ?>"><?php esc_html_e( 'About this project', 'ffnc' ); ?></a><?php endif; ?>
	</p>
</section>

<?php if ( $compare_url ) : ?>
<section class="ffnc-container ffnc-home-compare">
	<h2><?php esc_html_e( 'Compare Fast Food Meals Side by Side', 'ffnc' ); ?></h2>
	<p><?php esc_html_e( 'Wondering whether a Whopper or a Big Mac has more protein, or which chicken sandwich has less sodium? Pick up to three items from any of our restaurants and compare them in one table.', 'ffnc' ); ?></p>
	<p><a class="ffnc-btn ffnc-btn-solid" href="<?php echo esc_url( $compare_url ); ?>"><?php esc_html_e( 'Open the comparison tool', 'ffnc' ); ?></a></p>
</section>
<?php endif; ?>

<?php
/**
 * "From the Blog" teaser — only renders once Settings → Reading has a Posts
 * page assigned and at least one post is published, so it never links to a
 * broken/empty listing. Gives the blog its first internal link from the
 * homepage (previously zero paths existed from Home to the blog at all).
 */
$ffnc_blog_page_id = (int) get_option( 'page_for_posts' );
if ( $ffnc_blog_page_id ) {
	$ffnc_recent_posts = get_posts( array( 'numberposts' => 3, 'post_status' => 'publish' ) );
	if ( ! empty( $ffnc_recent_posts ) ) :
		?>
		<section class="ffnc-container ffnc-home-blog">
			<h2><?php esc_html_e( 'Nutrition Guides', 'ffnc' ); ?></h2>
			<ul class="ffnc-home-blog-list">
				<?php foreach ( $ffnc_recent_posts as $ffnc_post ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $ffnc_post ) ); ?>"><?php echo esc_html( get_the_title( $ffnc_post ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<p><a href="<?php echo esc_url( get_permalink( $ffnc_blog_page_id ) ); ?>"><?php esc_html_e( 'View all nutrition guides →', 'ffnc' ); ?></a></p>
		</section>
		<?php
	endif;
}
?>

<section class="ffnc-container ffnc-home-faq">
	<h2><?php esc_html_e( 'Frequently Asked Questions', 'ffnc' ); ?></h2>
	<?php
	$ffnc_faq_method = $method_url ? ' <a href="' . esc_url( $method_url ) . '">' . esc_html__( 'See our methodology.', 'ffnc' ) . '</a>' : '';
	echo do_shortcode( '[ff_faq]
		[ff_faq_item question="Are these fast food calorie calculators official?"]No. This is an independent, unofficial resource. We are not affiliated with, endorsed by, or sponsored by any of the restaurant brands on this site. Brand names are used only to identify which menu each calculator covers.[/ff_faq_item]
		[ff_faq_item question="Where does the nutrition data come from?"]Each restaurant has one dataset that we check against that restaurant\'s official U.S. nutrition information. Every calculator page links to the official source and shows whether the data has been verified and when. Datasets that have not been verified yet are clearly labeled, and their values should be treated as estimates.' . $ffnc_faq_method . '[/ff_faq_item]
		[ff_faq_item question="Do the values apply outside the United States?"]No. Our datasets describe U.S. menus. The same item can have a different recipe, portion size and nutrition in other countries.[/ff_faq_item]
		[ff_faq_item question="Do add-ons and sauces count toward the totals?"]Yes. A selected add-on adds every nutrient the restaurant publishes for it — calories, protein, carbohydrates, fat and sodium. If the restaurant does not publish one of those values for an add-on, the calculator does not count it as zero: it marks that total with a + and names the add-on it excludes.[/ff_faq_item]
		[ff_faq_item question="Is it free to use these calculators?"]Yes. Every calculator is free, with no account or sign-up.[/ff_faq_item]
	[/ff_faq]' );
	// The FAQ shortcode above runs outside the normal the_content pipeline
	// (this template builds the page by hand, not via the Loop), so the
	// FAQPage JSON-LD that ffnc_append_page_extras() would normally attach
	// via the_content never fires here. Emit it directly instead, keyed to
	// the front page's own id, right after the shortcode has populated the
	// FAQ collector — see ffnc_get_faq_schema_script() in inc/template-tags.php.
	echo ffnc_get_faq_schema_script( get_queried_object_id() );
	?>
</section>

<?php get_footer(); ?>
