<?php
/**
 * The default template for standard pages (brand calculator pages, About,
 * Contact, Privacy, Terms, etc. all use this unless a specific page template
 * is selected in page-templates/).
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
?>

<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'ffnc-page ffnc-container' ); ?>>
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
	</header>

	<?php
	/**
	 * The [ff_toc] table of contents is placed inline within each page's own
	 * content (see the content HTML files) rather than forced here, so
	 * editors control exactly where it sits — normally right under the
	 * calculator on brand pages.
	 *
	 * The calculator itself is handled differently: ffnc_pull_calculator_shortcode()
	 * (inc/shortcodes.php) strips [ff_calculator] out of the content during
	 * the_content() and hands it back here via $GLOBALS['ffnc_top_calculator_html'],
	 * so it can be placed directly under the title — full width, before the
	 * narrower prose column — regardless of where the shortcode sits in the
	 * page's own content.
	 */
	unset( $GLOBALS['ffnc_top_calculator_html'] );
	ob_start();
	the_content();
	$ffnc_content_html = ob_get_clean();
	?>

	<?php if ( ! empty( $GLOBALS['ffnc_top_calculator_html'] ) ) : ?>
		<div class="ffnc-calculator-top">
			<?php echo $GLOBALS['ffnc_top_calculator_html']; ?>
		</div>
	<?php endif; ?>

	<div class="ffnc-page-content">
		<?php echo $ffnc_content_html; ?>
	</div>
</article>
<?php endwhile; ?>

<?php get_footer(); ?>
