<?php
/**
 * Template Name: Compare Menu Items
 * Template Post Type: page
 *
 * Side-by-side comparison of any menu items across restaurants. Uses the
 * exact same JSON datasets as the calculators (inlined once on this page),
 * so there is no second, divergent data source. Selections live only in
 * the browser; query strings (?a=brand) are canonicalized away.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();

$ffnc_compare_data = array();
foreach ( ffnc_get_live_brands() as $ffnc_slug => $ffnc_brand ) {
	$ffnc_d = ffnc_get_brand_data( $ffnc_slug );
	if ( ! $ffnc_d ) {
		continue;
	}
	$ffnc_items = array();
	foreach ( $ffnc_d['categories'] as $ffnc_cat ) {
		foreach ( $ffnc_cat['items'] as $ffnc_it ) {
			$ffnc_items[] = array(
				'id'  => $ffnc_it['id'],
				'n'   => $ffnc_it['name'],
				'c'   => $ffnc_cat['name'],
				'cal' => $ffnc_it['calories'],
				'p'   => $ffnc_it['protein'],
				'cb'  => $ffnc_it['carbs'],
				'f'   => $ffnc_it['fat'],
				's'   => $ffnc_it['sodium'],
			);
		}
	}
	$ffnc_meta = ffnc_get_dataset_meta( $ffnc_slug );
	$ffnc_compare_data[ $ffnc_slug ] = array(
		'name'     => $ffnc_brand['name'],
		'url'      => home_url( $ffnc_brand['url'] ),
		'market'   => $ffnc_meta['market_label'],
		'verified' => ffnc_dataset_is_verified( $ffnc_slug ),
		'items'    => $ffnc_items,
	);
}
?>
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'ffnc-page ffnc-container ffnc-compare-page' ); ?>>
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
	</header>

	<div class="ffnc-page-content">
		<p class="ffnc-lead"><?php esc_html_e( 'Pick up to three menu items from any restaurant to compare calories, protein, carbs, fat and sodium side by side. Values come from the same datasets as our calculators.', 'ffnc' ); ?></p>
	</div>

	<div class="ffnc-compare" data-ffnc-compare>
		<script type="application/json" class="ffnc-compare-data"><?php echo wp_json_encode( $ffnc_compare_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
		<div class="ffnc-compare-pickers">
			<?php for ( $ffnc_i = 1; $ffnc_i <= 3; $ffnc_i++ ) : ?>
				<fieldset class="ffnc-compare-picker">
					<legend><?php echo esc_html( sprintf( __( 'Item %d', 'ffnc' ), $ffnc_i ) ); ?><?php echo 3 === $ffnc_i ? ' ' . esc_html__( '(optional)', 'ffnc' ) : ''; ?></legend>
					<label for="ffnc-cmp-brand-<?php echo (int) $ffnc_i; ?>"><?php esc_html_e( 'Restaurant', 'ffnc' ); ?></label>
					<select id="ffnc-cmp-brand-<?php echo (int) $ffnc_i; ?>" data-cmp-brand="<?php echo (int) $ffnc_i; ?>">
						<option value=""><?php esc_html_e( 'Choose…', 'ffnc' ); ?></option>
						<?php foreach ( $ffnc_compare_data as $ffnc_slug => $ffnc_b ) : ?>
							<option value="<?php echo esc_attr( $ffnc_slug ); ?>"><?php echo esc_html( $ffnc_b['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<label for="ffnc-cmp-item-<?php echo (int) $ffnc_i; ?>"><?php esc_html_e( 'Menu item', 'ffnc' ); ?></label>
					<select id="ffnc-cmp-item-<?php echo (int) $ffnc_i; ?>" data-cmp-item="<?php echo (int) $ffnc_i; ?>" disabled>
						<option value=""><?php esc_html_e( 'Choose a restaurant first', 'ffnc' ); ?></option>
					</select>
				</fieldset>
			<?php endfor; ?>
		</div>
		<div class="ffnc-compare-result" aria-live="polite">
			<p class="ffnc-compare-empty"><?php esc_html_e( 'Choose at least two items to see the comparison.', 'ffnc' ); ?></p>
		</div>
		<noscript><p><?php esc_html_e( 'The comparison tool needs JavaScript. Every item’s values are also listed in the nutrition table on each restaurant’s calculator page.', 'ffnc' ); ?></p></noscript>
	</div>

	<div class="ffnc-page-content">
		<?php the_content(); ?>
		<h2><?php esc_html_e( 'How to read the comparison', 'ffnc' ); ?></h2>
		<p><?php esc_html_e( 'Each column is one standard serving of that item as sold in the United States. The lowest calories and sodium and the highest protein in each row are highlighted. “Protein per 100 calories” helps compare items of very different sizes. These are nutrition facts, not recommendations — what fits your day depends on your own needs.', 'ffnc' ); ?></p>
		<?php if ( ffnc_methodology_url() ) : ?>
			<p><a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'Where these numbers come from and how current they are', 'ffnc' ); ?></a></p>
		<?php endif; ?>
		<h2><?php esc_html_e( 'Compare within one restaurant', 'ffnc' ); ?></h2>
		<ul class="ffnc-list ffnc-list-columns">
			<?php foreach ( ffnc_get_live_brands() as $ffnc_b ) : ?>
				<li><a href="<?php echo esc_url( home_url( $ffnc_b['url'] ) ); ?>"><?php echo esc_html( $ffnc_b['title'] ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
