<?php
/**
 * Template Name: Calculators Hub
 * Template Post Type: page
 *
 * Directory page linking out to every brand calculator — the main internal
 * linking hub that passes link equity to each brand page and back.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
$brands = ffnc_get_brands();
?>
<div class="ffnc-container ffnc-page">
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
	</header>

	<div class="ffnc-page-content">
		<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
	</div>

	<h2 class="screen-reader-text"><?php esc_html_e( 'Restaurant calculators', 'ffnc' ); ?></h2>
	<div class="ffnc-hub-grid">
		<?php foreach ( $brands as $brand ) : ?>
			<?php if ( ! empty( $brand['live'] ) ) : ?>
				<a class="ffnc-hub-card" href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>">
					<?php echo ffnc_brand_badge_html( $brand, 'md' ); ?>
					<span class="ffnc-hub-card-title"><?php echo esc_html( $brand['title'] ); ?></span>
					<span class="ffnc-hub-card-tagline"><?php echo esc_html( $brand['tagline'] ); ?></span>
					<?php $ffnc_m = ffnc_get_dataset_meta( $brand['slug'] ); ?>
					<span class="ffnc-hub-card-meta"><?php
						echo esc_html( sprintf(
							/* translators: 1 items 2 market 3 status */
							__( '%1$d items · %2$s · %3$s', 'ffnc' ),
							count( ffnc_get_brand_items( $brand['slug'] ) ),
							$ffnc_m['market'] ? $ffnc_m['market'] : '—',
							ffnc_dataset_is_verified( $brand['slug'] ) ? sprintf( __( 'verified %s', 'ffnc' ), ffnc_format_review_date( $ffnc_m['last_reviewed'], 'M j, Y' ) ) : __( 'verification pending', 'ffnc' )
						) );
					?></span>
				</a>
			<?php else : ?>
				<span class="ffnc-hub-card ffnc-hub-card-soon" aria-disabled="true">
					<?php echo ffnc_brand_badge_html( $brand, 'md' ); ?>
					<span class="ffnc-hub-card-title"><?php echo esc_html( $brand['title'] ); ?></span>
					<span class="ffnc-hub-card-tagline ffnc-soon-tag"><?php esc_html_e( 'Coming soon', 'ffnc' ); ?></span>
				</span>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
<?php if ( ffnc_methodology_url() ) : ?>
<div class="ffnc-container"><p class="ffnc-table-note"><?php esc_html_e( 'All calculators cover U.S. menus.', 'ffnc' ); ?> <a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'How we source and review the data', 'ffnc' ); ?></a></p></div>
<?php endif; ?>
<?php get_footer(); ?>
