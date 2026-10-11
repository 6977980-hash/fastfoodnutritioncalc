<?php
/**
 * Single blog post template.
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<article <?php post_class( 'ffnc-page ffnc-container ffnc-single-post' ); ?>>
	<header class="ffnc-page-header">
		<h1 class="ffnc-page-title"><?php the_title(); ?></h1>
		<p class="ffnc-post-meta">
			<?php
			printf(
				/* translators: %s: published date */
				esc_html__( 'Published %s', 'ffnc' ),
				'<time datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>'
			);
			if ( get_the_modified_date( 'Y-m-d' ) !== get_the_date( 'Y-m-d' ) ) {
				echo ' &middot; ';
				printf(
					/* translators: %s: updated date */
					esc_html__( 'Updated %s', 'ffnc' ),
					'<time datetime="' . esc_attr( get_the_modified_date( 'c' ) ) . '">' . esc_html( get_the_modified_date() ) . '</time>'
				);
			}
			echo ' &middot; ' . esc_html( get_bloginfo( 'name' ) );
			?>
		</p>
	</header>

	<?php /* [ff_toc] is placed inline within the post content itself — see page.php for why. */ ?>

	<div class="ffnc-page-content">
		<?php the_content(); ?>
		<?php
		// Every guide links back into the tools. Uses the post's own "Related
		// brand slugs" when set (rendered by ffnc_append_page_extras); otherwise
		// a compact link to the calculators hub and the methodology.
		if ( ! get_post_meta( get_the_ID(), '_ffnc_related_brands', true ) ) :
			?>
			<aside class="ffnc-guide-tools">
				<h2><?php esc_html_e( 'Check the numbers yourself', 'ffnc' ); ?></h2>
				<ul class="ffnc-list">
					<?php if ( ffnc_page_url( 'calculators' ) ) : ?><li><a href="<?php echo esc_url( ffnc_page_url( 'calculators' ) ); ?>"><?php esc_html_e( 'Pick a restaurant calculator', 'ffnc' ); ?></a></li><?php endif; ?>
					<?php if ( ffnc_compare_url() ) : ?><li><a href="<?php echo esc_url( ffnc_compare_url() ); ?>"><?php esc_html_e( 'Compare menu items side by side', 'ffnc' ); ?></a></li><?php endif; ?>
					<?php if ( ffnc_methodology_url() ) : ?><li><a href="<?php echo esc_url( ffnc_methodology_url() ); ?>"><?php esc_html_e( 'Where our nutrition data comes from', 'ffnc' ); ?></a></li><?php endif; ?>
				</ul>
			</aside>
		<?php endif; ?>
	</div>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
