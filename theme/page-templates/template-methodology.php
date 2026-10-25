<?php
/**
 * Template Name: Methodology (data sources)
 * Template Post Type: page
 *
 * Explains exactly how the site's nutrition data is sourced, normalized,
 * reviewed and corrected. The live status table is generated from the same
 * datasets the calculators use, so it can never drift from reality.
 * Anything the editor writes in the page body appears first.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
$ffnc_items_total = ffnc_count_all_items();
$ffnc_brand_total = count( ffnc_get_live_brands() );
$ffnc_report_url  = ffnc_report_error_url();
$ffnc_contact_url = ffnc_page_url( 'contact' );
?>
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'ffnc-page ffnc-container ffnc-methodology' ); ?>>
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
		<?php
		// The status table below changes with every dataset review, so the
		// visible date is the newer of the page edit and the latest review.
		$ffnc_reviewed = array_filter( wp_list_pluck( ffnc_get_recently_reviewed(), 'last_reviewed' ) );
		$ffnc_latest   = $ffnc_reviewed ? max( $ffnc_reviewed ) : '';
		$ffnc_updated  = max( get_the_modified_date( 'Y-m-d' ), (string) $ffnc_latest );
		$ffnc_owner    = ffnc_site_owner();
		?>
		<p class="ffnc-post-meta"><?php printf( esc_html__( 'Page updated %s', 'ffnc' ), '<time datetime="' . esc_attr( $ffnc_updated ) . '">' . esc_html( ffnc_format_review_date( $ffnc_updated, get_option( 'date_format' ) ) ) . '</time>' ); ?> &middot; <?php echo esc_html( sprintf( /* translators: %s name */ __( 'Maintained by %s', 'ffnc' ), $ffnc_owner['name'] ) ); ?></p>
	</header>

	<div class="ffnc-page-content">
		<?php the_content(); ?>

		<p class="ffnc-lead"><?php echo esc_html( sprintf(
			/* translators: 1 items 2 brands */
			__( 'This page explains where the %1$d menu items and sizes across our %2$d restaurant calculators come from, how we check them, and how to tell us when something is wrong.', 'ffnc' ),
			$ffnc_items_total,
			$ffnc_brand_total
		) ); ?></p>

		<h2 id="where-data-comes-from"><?php esc_html_e( 'Where the nutrition data comes from', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'Each restaurant has one dataset. The reference we check it against is the restaurant’s own published nutrition information for its U.S. menu — the official nutrition calculator, nutrition guide or PDF. Every calculator page links to that official source.', 'ffnc' ); ?></p>
		<p><?php esc_html_e( 'Our priority order for sources is: (1) the restaurant’s official nutrition information, (2) the restaurant’s official menu documentation, (3) other primary public documentation. We do not fill gaps with guesses — if a value is not published, we leave it out rather than estimate it.', 'ffnc' ); ?></p>

		<h2 id="review-status"><?php esc_html_e( 'Current review status of every dataset', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'A dataset is marked “Verified” only after every value in it has been compared with the official source, and the date of that review is recorded. Until then it is marked “Pending” and its values should be treated as estimates. This table is generated automatically from the data files the calculators use.', 'ffnc' ); ?></p>
		<?php echo do_shortcode( '[ff_dataset_status]' ); ?>

		<h2 id="serving-sizes"><?php esc_html_e( 'How serving sizes are handled', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'Every value is for one standard menu serving exactly as named (for example “French Fries (Medium)” or “Chicken McNuggets (10 pc)”), made with the standard recipe and no customizations. Sizes are separate menu items rather than scaled from each other, because restaurants do not scale portions linearly.', 'ffnc' ); ?></p>
		<p><?php esc_html_e( 'A few restaurants publish nutrition by ingredient or component instead of by finished item. Chipotle is therefore calculated ingredient by ingredient, and Five Guys burgers and hot dogs are the sum of the official bun and patty (or hot dog) values — the item name says so. Where a source lists a value as “less than 1 g”, it is stored as 0 g.', 'ffnc' ); ?></p>

		<h2 id="how-calculations-work"><?php esc_html_e( 'How the calculators add things up', 'ffnc' ); ?></h2>
		<ul class="ffnc-list">
			<li><?php esc_html_e( 'Line total = (menu item + each selected add-on) × quantity.', 'ffnc' ); ?></li>
			<li><?php esc_html_e( 'Order total = the sum of every line, rounded to one decimal place for display.', 'ffnc' ); ?></li>
			<li><?php esc_html_e( 'Add-ons (sauces, cheese, toppings) add every nutrient the restaurant publishes for them — calories, protein, carbohydrates, fat and sodium — to the line and order totals.', 'ffnc' ); ?></li>
			<li><?php esc_html_e( 'If the official source does not publish a value (for example, a sauce whose sodium is not listed, or an add-on for which the restaurant only states the calories it adds), that value is shown as “—” and is not counted. It is never treated as zero: the affected total is marked with “+” to show it is a minimum, and the calculator names the item or add-on it excludes.', 'ffnc' ); ?></li>
			<li><?php esc_html_e( 'The calculator, the nutrition tables and the comparison tool all read the same data file, so the numbers always match across the site.', 'ffnc' ); ?></li>
		</ul>
		<p><?php esc_html_e( 'The arithmetic is covered by automated tests that recompute every item, every add-on and multi-item orders for every restaurant.', 'ffnc' ); ?></p>

		<h2 id="discrepancies"><?php esc_html_e( 'How discrepancies are handled', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'When a value in our data differs from the official source, the official source wins. Known differences found in spot checks are flagged directly in the nutrition table next to the item until the full dataset review is complete. We never change a value just to match another website.', 'ffnc' ); ?></p>

		<h2 id="why-values-change"><?php esc_html_e( 'Why nutrition values change', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'Restaurants reformulate recipes, change suppliers, resize portions and rotate limited-time items. Published values are also averages — the food you receive can vary with preparation. That is why every dataset shows when it was last reviewed.', 'ffnc' ); ?></p>

		<h2 id="markets"><?php esc_html_e( 'Country and market limitations', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'All current datasets describe U.S. menus. The same product name can have a different recipe and nutrition in other countries, so these values should not be used for restaurants outside the United States. We will only add another country when a separate, reliable official dataset exists for it.', 'ffnc' ); ?></p>

		<h2 id="report-errors"><?php esc_html_e( 'How to report an error', 'ffnc' ); ?></h2>
		<p>
			<?php esc_html_e( 'If a number looks wrong, tell us which restaurant, item and value, and ideally link to the official page you checked. We review every report against the official source before changing anything.', 'ffnc' ); ?>
			<?php if ( $ffnc_report_url ) : ?>
				<a href="<?php echo esc_url( $ffnc_report_url ); ?>" rel="nofollow"><?php esc_html_e( 'Report a nutrition data error', 'ffnc' ); ?></a>.
			<?php elseif ( $ffnc_contact_url ) : ?>
				<a href="<?php echo esc_url( $ffnc_contact_url ); ?>"><?php esc_html_e( 'Contact us', 'ffnc' ); ?></a>.
			<?php endif; ?>
		</p>

		<h2 id="independence"><?php esc_html_e( 'Independence and limits of this site', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'This is an independent website. It is not affiliated with, endorsed by, or sponsored by any restaurant brand; brand names are used only to identify which menu a calculator covers. The site does not provide medical, dietary or allergy advice, and no content here has been reviewed by a dietitian or medical professional. The site is run by its founder and editor, who checks every dataset against the official source. For allergies or medical diets, always use the restaurant’s official allergen information and consult a qualified professional.', 'ffnc' ); ?></p>
	</div>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
