<?php
/**
 * 404 template — useful routes back into the site.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
$ffnc_hub = ffnc_page_url( 'calculators' );
?>
<div class="ffnc-container ffnc-page ffnc-404">
	<h1><?php esc_html_e( 'Page Not Found', 'ffnc' ); ?></h1>
	<p><?php esc_html_e( "The page you're looking for doesn't exist or may have moved. Use the search box at the top to find a restaurant or menu item, or pick a calculator below.", 'ffnc' ); ?></p>

	<h2><?php esc_html_e( 'Restaurant calculators', 'ffnc' ); ?></h2>
	<ul class="ffnc-list ffnc-list-columns">
		<?php foreach ( ffnc_get_live_brands() as $brand ) : ?>
			<li><a href="<?php echo esc_url( home_url( $brand['url'] ) ); ?>"><?php echo esc_html( $brand['title'] ); ?></a></li>
		<?php endforeach; ?>
	</ul>

	<h2><?php esc_html_e( 'Other useful pages', 'ffnc' ); ?></h2>
	<ul class="ffnc-list">
		<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'ffnc' ); ?></a></li>
		<?php if ( $ffnc_hub ) : ?><li><a href="<?php echo esc_url( $ffnc_hub ); ?>"><?php esc_html_e( 'All calculators', 'ffnc' ); ?></a></li><?php endif; ?>
		<?php if ( ffnc_compare_url() ) : ?><li><a href="<?php echo esc_url( ffnc_compare_url() ); ?>"><?php esc_html_e( 'Compare menu items', 'ffnc' ); ?></a></li><?php endif; ?>
		<?php if ( get_option( 'page_for_posts' ) ) : ?><li><a href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ); ?>"><?php esc_html_e( 'Nutrition guides', 'ffnc' ); ?></a></li><?php endif; ?>
		<?php if ( ffnc_methodology_url() ) : ?><li><a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'How we source nutrition data', 'ffnc' ); ?></a></li><?php endif; ?>
	</ul>
</div>
<?php get_footer(); ?>
