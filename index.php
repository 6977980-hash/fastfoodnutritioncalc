<?php
/**
 * The main template file (blog listing / fallback).
 *
 * @package FastFoodNutritionCalculators
 */

get_header();
?>
<div class="ffnc-container">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<h1 class="ffnc-page-title"><?php single_post_title(); ?></h1>
		<?php
		$ffnc_blog_intro = get_post_field( 'post_content', (int) get_option( 'page_for_posts' ) );
		if ( $ffnc_blog_intro ) {
			echo '<div class="ffnc-page-content ffnc-archive-intro">' . wp_kses_post( wpautop( $ffnc_blog_intro ) ) . '</div>';
		}
		?>
	<?php elseif ( is_archive() ) : ?>
		<h1 class="ffnc-page-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<?php the_archive_description( '<div class="ffnc-page-content ffnc-archive-intro">', '</div>' ); ?>
	<?php elseif ( is_search() ) : ?>
		<h1 class="ffnc-page-title"><?php printf( esc_html__( 'Search results for “%s”', 'ffnc' ), esc_html( get_search_query() ) ); ?></h1>
	<?php endif; ?>

	<div class="ffnc-blog-grid">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class( 'ffnc-blog-card' ); ?>>
				<?php if ( has_post_thumbnail() ) : ?>
					<a href="<?php the_permalink(); ?>" class="ffnc-blog-card-thumb" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?></a>
				<?php endif; ?>
				<h2 class="ffnc-blog-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<div class="ffnc-blog-card-excerpt"><?php the_excerpt(); ?></div>
				<a class="ffnc-read-more" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'ffnc' ); ?><span class="screen-reader-text"> <?php the_title(); ?></span></a>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No posts found.', 'ffnc' ); ?></p>
	<?php endif; ?>
	</div>

	<?php the_posts_pagination(); ?>
</div>
<?php get_footer(); ?>
