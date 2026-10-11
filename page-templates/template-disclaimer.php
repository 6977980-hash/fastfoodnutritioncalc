<?php
/**
 * Template Name: Disclaimer Page
 * Template Post Type: page
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
?>
<div class="ffnc-container ffnc-page ffnc-disclaimer-page">
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
	</header>

	<?php /* [ff_toc] is placed inline within the page content itself — see page.php for why. */ ?>

	<div class="ffnc-page-content ffnc-disclaimer-content">
		<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
	</div>
</div>
<?php get_footer(); ?>
